<?php
namespace App\Models;

use CodeIgniter\Model;

class TeacherScheduleModel extends Model
{
    protected $table = 'teacher_schedules';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'teacher_id', 'subject_id', 'subject_name', 'domain_id', 'section_id', 'day_of_week',
        'start_time', 'end_time', 'room', 'school_year'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Every schedule row of a teacher, ready for the weekly grid.
     *
     * Rows are always inserted with the current school year
     * (Admin\Schedules::save()), so a row carrying any other year is legacy or
     * hand-edited data. Resolving a SINGLE year used to hide every other day:
     * one Monday row stamped 2028-2029 made the Tuesday-Friday rows
     * (2026-2027) vanish from My Schedule even though the admin grid still
     * listed them. Without an explicit year the teacher therefore receives all
     * of their rows, collapsed to one row per day/time/section slot with the
     * current school year preferred.
     *
     * @param string|null $schoolYear Explicit year; null returns every year.
     * @param bool $includeAdvisedSections When true, blocks of the sections this
     *        teacher ADVISES are returned as well as their own blocks. The
     *        adviser of a section is the one who needs its full timetable even
     *        though the individual subjects are taught by colleagues, so My
     *        Schedule was empty for an adviser whose blocks were all assigned
     *        to other teachers. The union is per row, so a block the teacher
     *        both teaches and advises is still returned once.
     */
    public function getTeacherSchedule($teacherId, $schoolYear = null, bool $includeAdvisedSections = false)
    {
        helper('grade_level');
        helper('school_year');

        $explicitYear = ($schoolYear !== null && $schoolYear !== '');

        // Non-numerical sections schedule developmental domains rather than
        // subjects, so the display name must fall back to the domain name from
        // sned_categories. That join is only possible when the domain_id column
        // exists (the self-healing migration may not have run yet on this host).
        $withDomains = function_exists('schedule_domain_column_ready')
            && schedule_domain_column_ready();

        // teacher_name labels a block when it is shown to someone other than
        // its teacher (an adviser reading their section's timetable).
        $sql = 'SELECT ts.*,
                COALESCE(sub.subject_name, ' . ($withDomains ? 'sc.name, ' : '') . 'ts.subject_name) AS subject_name,
                sub.subject_code, sec.section_name, sec.grade_level,
                CONCAT(t.first_name, " ", t.last_name) AS teacher_name
            FROM teacher_schedules ts
            LEFT JOIN subjects sub ON sub.id = ts.subject_id
            LEFT JOIN sections sec ON sec.id = ts.section_id
            LEFT JOIN teachers t ON t.id = ts.teacher_id'
            . ($withDomains ? ' LEFT JOIN sned_categories sc ON sc.id = ts.domain_id' : '')
            . ' WHERE ' . ($includeAdvisedSections
                ? '(ts.teacher_id = ? OR ts.section_id IN (SELECT s2.id FROM sections s2 WHERE s2.adviser_id = ?))'
                : 'ts.teacher_id = ?')
            . ($explicitYear ? ' AND ts.school_year = ?' : '');

        $params = $includeAdvisedSections ? [$teacherId, $teacherId] : [$teacherId];
        if ($explicitYear) {
            $params[] = $schoolYear;
        }

        $rows = $this->db
            ->query($sql, $params)
            ->getResultArray();

        if ($rows === []) {
            return [];
        }

        if (! $explicitYear) {
            $rows = $this->collapseScheduleYears($rows, (string) get_current_school_year());
        }

        return $this->sortScheduleRows($rows);
    }

    /**
     * Keeps one row per day/time/section slot.
     *
     * A slot scheduled in two school years must not be listed twice, so the
     * current school year wins; between rows of the same year the newest one
     * wins so re-saving a slot refreshes the grid.
     */
    private function collapseScheduleYears(array $rows, string $currentYear): array
    {
        $bySlot = [];

        foreach ($rows as $row) {
            $key = strtolower((string) ($row['day_of_week'] ?? ''))
                . '|' . substr((string) ($row['start_time'] ?? ''), 0, 5)
                . '-' . substr((string) ($row['end_time'] ?? ''), 0, 5)
                . '|' . (int) ($row['section_id'] ?? 0);

            if (! isset($bySlot[$key])) {
                $bySlot[$key] = $row;
                continue;
            }

            $keptYear      = (string) ($bySlot[$key]['school_year'] ?? '');
            $candidateYear = (string) ($row['school_year'] ?? '');

            $preferCandidate = $candidateYear === $currentYear && $keptYear !== $currentYear;
            if (! $preferCandidate && $keptYear === $candidateYear) {
                $preferCandidate = (int) ($row['id'] ?? 0) > (int) ($bySlot[$key]['id'] ?? 0);
            }

            if ($preferCandidate) {
                $bySlot[$key] = $row;
            }
        }

        return array_values($bySlot);
    }

    /**
     * Orders rows the way a week is read - Monday first.
     *
     * ORDER BY day_of_week sorts alphabetically, which puts Friday ahead of
     * Monday, so the grid's time-slot rows came out shuffled.
     */
    private function sortScheduleRows(array $rows): array
    {
        $week = [
            'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
            'friday' => 5, 'saturday' => 6, 'sunday' => 7,
        ];

        usort($rows, static function (array $a, array $b) use ($week): int {
            $dayA = $week[strtolower((string) ($a['day_of_week'] ?? ''))] ?? 99;
            $dayB = $week[strtolower((string) ($b['day_of_week'] ?? ''))] ?? 99;

            if ($dayA !== $dayB) {
                return $dayA <=> $dayB;
            }

            return strcmp((string) ($a['start_time'] ?? ''), (string) ($b['start_time'] ?? ''));
        });

        return $rows;
    }

    /* =====================================================================
     * Conflict engine
     *
     * Every writer of teacher_schedules used to hand-roll its own overlap
     * query, and each copy drifted, so a block rejected on one page could be
     * accepted on another. All schedule writes now validate through here.
     *
     * Rules enforced for a proposed block:
     *   - section   : the section already has a block whose time overlaps
     *                 (any subject / domain).
     *   - teacher   : the teacher already teaches an overlapping block in ANY
     *                 section.
     *   - room      : the room is already occupied by an overlapping block.
     *   - duplicate : the same subject/domain is already scheduled in the
     *                 section on that day (any time) - the long-standing
     *                 "one subject per section per day" rule.
     *
     * Checks deliberately span school years: the section grid and the
     * teacher's My Schedule both display every year, so a row from another
     * year still occupies a visible slot and must be honoured. Every message
     * therefore names the blocking row's school year. Assignment placeholder
     * rows (''/'TBD' day, 00:00:00-00:00:00) can never overlap and are kept
     * out by the weekday + overlap predicates themselves.
     * ===================================================================== */

    /**
     * Every conflict a proposed schedule block would create.
     *
     * @param array $slot {
     *     @var int    $section_id  Required.
     *     @var int    $teacher_id  Required.
     *     @var int    $item_id     Subject id (numerical) or domain id
     *                              (non-numerical section). Required.
     *     @var string $day_of_week Monday-Friday. Required.
     *     @var string $start_time  'HH:MM' or 'HH:MM:SS'. Required.
     *     @var string $end_time    Must be later than start_time. Required.
     *     @var string $room        Optional; empty means "no room".
     *     @var int    $id          Row being edited; excluded from every
     *                              lookup so updating a block cannot collide
     *                              with itself.
     * }
     * @param array $siblings           Other blocks validated in the same
     *                                  save. They are not in the database yet,
     *                                  so they are compared in memory - this
     *                                  is what stops one click from writing
     *                                  two overlapping rows.
     * @param bool  $checkDuplicateItem False when the caller wants overlap
     *                                  checks only (e.g. a time-only edit).
     * @param array $excludeIds         Extra row ids to ignore. The teacher
     *                                  page passes the ids it is about to
     *                                  delete-and-replace, so replacing a
     *                                  teacher's own grid cannot collide with
     *                                  itself.
     *
     * @return list<array{type: string, message: string}> Empty when clean.
     */
    public function findConflicts(array $slot, array $siblings = [], bool $checkDuplicateItem = true, array $excludeIds = []): array
    {
        $sectionId = (int) ($slot['section_id'] ?? 0);
        $teacherId = (int) ($slot['teacher_id'] ?? 0);
        $itemId    = (int) ($slot['item_id'] ?? 0);
        $day       = (string) ($slot['day_of_week'] ?? '');
        $start     = schedule_normalize_time((string) ($slot['start_time'] ?? ''));
        $end       = schedule_normalize_time((string) ($slot['end_time'] ?? ''));
        $room      = trim((string) ($slot['room'] ?? ''));
        $ownId     = (int) ($slot['id'] ?? 0);

        // Malformed slots are the caller's job to reject; there is nothing
        // meaningful to compare here.
        if ($sectionId <= 0 || $teacherId <= 0 || $itemId <= 0 || $day === ''
            || $start === null || $end === null || $end <= $start) {
            return [];
        }

        $excludedSql = $this->excludeSql(array_merge($excludeIds, $ownId > 0 ? [$ownId] : []));
        $conflicts   = [];


        // --- 1. Section conflict: any other block overlapping this slot -----
        $row = $this->db->query(
            'SELECT ts.*, ' . schedule_item_name_select() . ", sec.section_name,
                   CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
            FROM teacher_schedules ts
            " . schedule_item_name_joins() . '
            LEFT JOIN sections sec ON sec.id = ts.section_id
            LEFT JOIN teachers t ON t.id = ts.teacher_id
            WHERE ts.section_id = ?
              AND ts.day_of_week = ?
              AND ts.start_time < ?
              AND ts.end_time > ?' . $excludedSql . '
            LIMIT 1',
            [$sectionId, $day, $end, $start]
        )->getRowArray();

        if ($row !== null) {
            $conflicts[] = [
                'type'    => 'section',
                'message' => sprintf(
                    'Section conflict: %s already has %s with %s on %s from %s to %s (%s).',
                    $row['section_name'] ?: ('section #' . $sectionId),
                    $this->rowItemLabel($row),
                    $row['teacher_name'] ?: 'an unassigned teacher',
                    $day,
                    $this->clock($row['start_time']),
                    $this->clock($row['end_time']),
                    $row['school_year'] ?? 'unknown school year'
                ),
            ];
        }


        // --- 2. Teacher conflict: overlapping block anywhere -----------------
        $row = $this->db->query(
            'SELECT ts.*, ' . schedule_item_name_select() . ", sec.section_name,
                   CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
            FROM teacher_schedules ts
            " . schedule_item_name_joins() . '
            LEFT JOIN sections sec ON sec.id = ts.section_id
            LEFT JOIN teachers t ON t.id = ts.teacher_id
            WHERE ts.teacher_id = ?
              AND ts.day_of_week = ?
              AND ts.start_time < ?
              AND ts.end_time > ?' . $excludedSql . '
            LIMIT 1',
            [$teacherId, $day, $end, $start]
        )->getRowArray();

        if ($row !== null) {
            $conflicts[] = [
                'type'    => 'teacher',
                'message' => sprintf(
                    'Teacher conflict: %s is already scheduled for %s in %s on %s from %s to %s (%s).',
                    $row['teacher_name'] ?: 'This teacher',
                    $this->rowItemLabel($row),
                    $row['section_name'] ?: ('section #' . (int) $row['section_id']),
                    $day,
                    $this->clock($row['start_time']),
                    $this->clock($row['end_time']),
                    $row['school_year'] ?? 'unknown school year'
                ),
            ];
        }


        // --- 3. Room conflict: overlapping block in the same room ------------
        if ($room !== '') {
            $roomConflict = $this->findRoomConflict($room, $day, $start, $end, array_merge($excludeIds, $ownId > 0 ? [$ownId] : []));

            if ($roomConflict !== null) {
                $conflicts[] = [
                    'type'    => 'room',
                    'message' => sprintf(
                        'Room conflict: %s is already occupied by %s for %s in %s on %s from %s to %s (%s).',
                        $room,
                        $roomConflict['teacher_name'] ?: 'another teacher',
                        $this->rowItemLabel($roomConflict),
                        $roomConflict['section_name'] ?: ('section #' . (int) $roomConflict['section_id']),
                        $day,
                        $this->clock($roomConflict['start_time']),
                        $this->clock($roomConflict['end_time']),
                        $roomConflict['school_year'] ?? 'unknown school year'
                    ),
                ];
            }
        }

        // --- 4. Duplicate item on the same day -------------------------------
        if ($checkDuplicateItem) {
            $row = $this->db->query(
                'SELECT ts.*, ' . schedule_item_name_select() . ', sec.section_name
                FROM teacher_schedules ts
                ' . schedule_item_name_joins() . '
                LEFT JOIN sections sec ON sec.id = ts.section_id
                WHERE ts.section_id = ?
                  AND ts.day_of_week = ?
                  AND ' . schedule_item_id_expr() . ' = ?' . $excludedSql . '
                LIMIT 1',
                [$sectionId, $day, $itemId]
            )->getRowArray();

            if ($row !== null) {
                $conflicts[] = [
                    'type'    => 'duplicate',
                    'message' => sprintf(
                        'Duplicate conflict: %s is already scheduled in %s on %s from %s to %s (%s). A subject or domain may only appear once per day in a section.',
                        $this->rowItemLabel($row),
                        $row['section_name'] ?: ('section #' . $sectionId),
                        $day,
                        $this->clock($row['start_time']),
                        $this->clock($row['end_time']),
                        $row['school_year'] ?? 'unknown school year'
                    ),
                ];
            }
        }


        // --- 5. Sibling blocks from the same save ----------------------------
        // They are not in the database yet, so the queries above cannot see
        // them; compare in memory instead.
        foreach ($siblings as $sibling) {
            if (! is_array($sibling)) {
                continue;
            }

            $sibStart = schedule_normalize_time((string) ($sibling['start_time'] ?? ''));
            $sibEnd   = schedule_normalize_time((string) ($sibling['end_time'] ?? ''));

            if ($sibStart === null || $sibEnd === null
                || strcasecmp((string) ($sibling['day_of_week'] ?? ''), $day) !== 0
                || ! schedule_times_overlap($start, $end, $sibStart, $sibEnd)) {
                continue;
            }

            $when = $day . ' ' . substr($sibStart, 0, 5) . '-' . substr($sibEnd, 0, 5);
            $what = (string) ($sibling['item_name'] ?? ('item #' . (int) ($sibling['item_id'] ?? 0)));
            $who  = (string) ($sibling['teacher_name'] ?? ('teacher #' . (int) ($sibling['teacher_id'] ?? 0)));

            if ((int) ($sibling['section_id'] ?? 0) === $sectionId) {
                $conflicts[] = [
                    'type'    => 'section',
                    'message' => sprintf(
                        'Section conflict: this save also adds %s to %s on %s, which overlaps this block.',
                        $what,
                        (string) ($sibling['section_name'] ?? ('section #' . $sectionId)),
                        $when
                    ),
                ];
            }

            if ((int) ($sibling['teacher_id'] ?? 0) === $teacherId) {
                $conflicts[] = [
                    'type'    => 'teacher',
                    'message' => sprintf(
                        'Teacher conflict: this save also assigns %s to %s (%s), which overlaps this block.',
                        $who,
                        $what,
                        $when
                    ),
                ];
            }

            if ($room !== '' && strcasecmp(trim((string) ($sibling['room'] ?? '')), $room) === 0) {
                $conflicts[] = [
                    'type'    => 'room',
                    'message' => sprintf(
                        'Room conflict: %s is also given to %s (%s) by this same save.',
                        $room,
                        $what,
                        $when
                    ),
                ];
            }

            if ($checkDuplicateItem
                && (int) ($sibling['section_id'] ?? 0) === $sectionId
                && (int) ($sibling['item_id'] ?? 0) === $itemId) {
                $conflicts[] = [
                    'type'    => 'duplicate',
                    'message' => sprintf(
                        'Duplicate conflict: this save schedules %s twice on %s in %s.',
                        $what,
                        $day,
                        (string) ($sibling['section_name'] ?? ('section #' . $sectionId))
                    ),
                ];
            }
        }

        return $conflicts;
    }


    /**
     * The overlapping block occupying a room, or null when it is free.
     *
     * Standalone so a room-only edit (Admin\Schedules::updateRoom) can run
     * just this check instead of re-validating the untouched teacher/section
     * assignments around it.
     */
    public function findRoomConflict(string $room, string $day, string $startTime, string $endTime, array $excludeIds = []): ?array
    {
        $room = trim($room);
        $day  = trim($day);

        if ($room === '' || $day === '') {
            return null;
        }

        $start = schedule_normalize_time($startTime);
        $end   = schedule_normalize_time($endTime);

        if ($start === null || $end === null || $end <= $start) {
            return null;
        }

        return $this->db->query(
            'SELECT ts.*, ' . schedule_item_name_select() . ", sec.section_name,
                   CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
            FROM teacher_schedules ts
            " . schedule_item_name_joins() . '
            LEFT JOIN sections sec ON sec.id = ts.section_id
            LEFT JOIN teachers t ON t.id = ts.teacher_id
            WHERE ts.room = ?
              AND ts.day_of_week = ?
              AND ts.start_time < ?
              AND ts.end_time > ?' . $this->excludeSql($excludeIds) . '
            LIMIT 1',
            [$room, $day, $end, $start]
        )->getRowArray();
    }

    /**
     * Every overlapping pair of blocks a section already holds.
     *
     * Returned so the section schedule page can warn about conflicts that
     * pre-date the validation (legacy rows, rows from another school year)
     * instead of letting the admin discover them slot by slot.
     *
     * @return list<array{ids: list<int>, message: string}>
     */
    public function findExistingSectionConflicts(int $sectionId): array
    {
        $rows = $this->timetableRows('ts.section_id = ?', [$sectionId]);

        return $this->pairwiseConflicts($rows, 'section ' . ($rows[0]['section_name'] ?? ('#' . $sectionId)));
    }

    /**
     * Every overlapping pair of blocks a teacher already holds - the teacher
     * cannot be in two rooms at once.
     *
     * @return list<array{ids: list<int>, message: string}>
     */
    public function findExistingTeacherConflicts(int $teacherId): array
    {
        $rows = $this->timetableRows('ts.teacher_id = ?', [$teacherId]);

        $label = $rows[0]['teacher_name'] ?? ('teacher #' . $teacherId);

        return $this->pairwiseConflicts($rows, $label);
    }


    /**
     * Real timetable blocks (Mon-Fri) of one section or one teacher, with the
     * labels the conflict messages need.
     *
     * @return list<array<string, mixed>>
     */
    private function timetableRows(string $where, array $params): array
    {
        $days = array_map(static fn (string $day): string => "'" . $day . "'", schedule_conflict_weekdays());

        return $this->db->query(
            'SELECT ts.id, ts.section_id, ts.teacher_id, ts.day_of_week,
                   ts.start_time, ts.end_time, ts.room, ts.school_year,
                   ' . schedule_item_name_select() . ', sec.section_name,
                   CONCAT(t.first_name, \' \', t.last_name) AS teacher_name
            FROM teacher_schedules ts
            ' . schedule_item_name_joins() . '
            LEFT JOIN sections sec ON sec.id = ts.section_id
            LEFT JOIN teachers t ON t.id = ts.teacher_id
            WHERE ' . $where . '
              AND ts.day_of_week IN (' . implode(',', $days) . ')
            ORDER BY ts.day_of_week, ts.start_time',
            $params
        )->getResultArray();
    }

    /**
     * Pairwise overlap scan over already-stored rows.
     *
     * @param list<array<string, mixed>> $rows Output of timetableRows().
     *
     * @return list<array{ids: list<int>, message: string}>
     */
    private function pairwiseConflicts(array $rows, string $scopeLabel): array
    {
        $conflicts = [];
        $count     = count($rows);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $rows[$i];
                $b = $rows[$j];

                if (strcasecmp((string) $a['day_of_week'], (string) $b['day_of_week']) !== 0) {
                    continue;
                }

                if (! schedule_times_overlap(
                    (string) $a['start_time'],
                    (string) $a['end_time'],
                    (string) $b['start_time'],
                    (string) $b['end_time']
                )) {
                    continue;
                }

                $crossYear = ((string) ($a['school_year'] ?? '')) !== ((string) ($b['school_year'] ?? ''));

                $conflicts[] = [
                    'ids' => [(int) $a['id'], (int) $b['id']],
                    'message' => sprintf(
                        'Schedule conflict in %s: %s with %s (%s-%s) overlaps %s with %s (%s-%s) on %s%s.',
                        $a['section_name'] ?: $scopeLabel,
                        $this->rowItemLabel($a),
                        $a['teacher_name'] ?: 'an unassigned teacher',
                        substr((string) $a['start_time'], 0, 5),
                        substr((string) $a['end_time'], 0, 5),
                        $this->rowItemLabel($b),
                        $b['teacher_name'] ?: 'an unassigned teacher',
                        substr((string) $b['start_time'], 0, 5),
                        substr((string) $b['end_time'], 0, 5),
                        $a['day_of_week'],
                        $crossYear
                            ? ' (across school years ' . $a['school_year'] . ' / ' . $b['school_year'] . ')'
                            : ''
                    ),
                ];
            }
        }

        return $conflicts;
    }


    /**
     * The teacher the Sections page assigned to each item in a section.
     *
     * Saving a teacher for a subject ("Assign Teachers" on the Sections page)
     * writes a teacher_schedules row that holds the pair but no timetable slot
     * (see schedule_assignment_day_sql()). That row - not the timetable - is the
     * record of who teaches the subject, so the section page reads it back
     * instead of guessing from whichever rows happen to exist. The current
     * school year wins; the newest row breaks ties.
     *
     * @param list<int> $itemIds Subject ids, or developmental domain ids for a
     *                           non-numerical section.
     *
     * @return array<int, int> item id => teacher id
     */
    public function assignedTeachers(int $sectionId, array $itemIds): array
    {
        return $this->preferredTeacherRows($sectionId, $itemIds, schedule_assignment_day_sql());
    }

    /**
     * The teacher who already has a real block for each item in a section.
     *
     * Fallback for data that predates the assignment rows: a subject that only
     * ever existed as timetable entries still knows who taught it.
     *
     * @param list<int> $itemIds
     *
     * @return array<int, int> item id => teacher id
     */
    public function scheduledTeachers(int $sectionId, array $itemIds): array
    {
        return $this->preferredTeacherRows($sectionId, $itemIds, schedule_weekday_day_sql());
    }

    /**
     * item id => teacher id for one class of teacher_schedules row.
     *
     * Ordered in PHP rather than SQL so "current school year first, newest row
     * next" is one readable loop instead of an expression inside ORDER BY, which
     * placeholders cannot portably reach.
     *
     * @param list<int> $itemIds
     *
     * @return array<int, int>
     */
    private function preferredTeacherRows(int $sectionId, array $itemIds, string $dayPredicate): array
    {
        $itemIds = array_values(array_unique(array_filter(
            array_map('intval', $itemIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($sectionId <= 0 || $itemIds === []) {
            return [];
        }

        $column = schedule_item_column($sectionId);
        $marks  = implode(',', array_fill(0, count($itemIds), '?'));

        // Both the column name (schedule_item_column) and the day predicate
        // (schedule_assignment_day_sql) are built from constants, never input.
        // The rows are aliased `ts` because those predicates are written for it.
        $rows = $this->db->query(
            "SELECT ts.{$column} AS item_id, ts.teacher_id, ts.school_year
             FROM teacher_schedules ts
             WHERE ts.section_id = ? AND ts.{$column} IN ({$marks}) AND {$dayPredicate}
             ORDER BY ts.id DESC",
            array_merge([$sectionId], $itemIds)
        )->getResultArray();

        $currentYear = get_current_school_year();
        $preferred   = [];
        $stale       = [];

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];

            if ((string) $row['school_year'] === $currentYear) {
                if (! isset($preferred[$itemId])) {
                    $preferred[$itemId] = (int) $row['teacher_id'];
                }
            } elseif (! isset($stale[$itemId])) {
                $stale[$itemId] = (int) $row['teacher_id'];
            }
        }

        return $preferred + $stale;
    }

    /**
     * Candidate teachers for one subject, the assigned one first.
     *
     * The section page pre-selects the FIRST entry, so this order is the
     * contract: the assigned teacher, then the section adviser, then every other
     * active teacher. The full staff list is kept because a block may still need
     * to be assigned by hand to someone the Sections page has not recorded yet -
     * which is why the teacher dropdown is editable rather than a fixed label.
     *
     * @param array<int, array<string, mixed>> $teachers Every teacher row, ordered by name.
     *
     * @return list<array{id: int, name: string, assigned: bool, adviser: bool}>
     */
    public function teacherChoices(?int $assignedTeacherId, int $adviserId, array $teachers): array
    {
        $names    = [];
        $isActive = [];

        foreach ($teachers as $teacher) {
            $teacherId            = (int) $teacher['id'];
            $names[$teacherId]    = (string) $teacher['name'];
            $isActive[$teacherId] = ($teacher['employment_status'] ?? '') === 'active';
        }

        $order = [];

        // The assigned teacher heads the list even when no longer active: the
        // block still has to show who it belongs to.
        foreach ([$assignedTeacherId, $adviserId] as $teacherId) {
            $teacherId = (int) $teacherId;

            if ($teacherId > 0 && ! in_array($teacherId, $order, true)) {
                $order[] = $teacherId;
            }
        }

        foreach (array_keys($names) as $teacherId) {
            if ($isActive[$teacherId] && ! in_array($teacherId, $order, true)) {
                $order[] = $teacherId;
            }
        }

        $choices = [];

        foreach ($order as $teacherId) {
            $choices[] = [
                'id'       => $teacherId,
                'name'     => $names[$teacherId] ?? ('Teacher #' . $teacherId),
                'assigned' => $teacherId === (int) $assignedTeacherId,
                'adviser'  => $teacherId === $adviserId,
            ];
        }

        return $choices;
    }

    /**
     * Every teacher, ordered by name, carrying the columns teacherChoices()
     * needs. One query feeds every subject's dropdown on the section page.
     *
     * @return list<array<string, mixed>>
     */
    public function teacherChoicesPool(): array
    {
        return $this->db->table('teachers')
            ->select('id, CONCAT(first_name, " ", last_name) as name, employment_status', false)
            ->orderBy('last_name', 'ASC')
            ->orderBy('first_name', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Display label of a row's scheduled item, tolerating rows whose subject
     * was deleted (subject_name NULL) or that pre-date the domain column.
     */
    private function rowItemLabel(array $row): string
    {
        $label = trim((string) ($row['item_name'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        if (trim((string) ($row['subject_name'] ?? '')) !== '') {
            return (string) $row['subject_name'];
        }

        return 'an unnamed item';
    }

    /**
     * 'HH:MM:SS' (or 'HH:MM') rendered as a readable 12-hour clock.
     */
    private function clock(string $time): string
    {
        $normalized = schedule_normalize_time($time);

        return $normalized === null ? $time : date('g:i A', strtotime($normalized));
    }

    /**
     * SQL fragment ignoring a fixed list of row ids. Ids are cast to int, so
     * the fragment cannot carry user input.
     */
    private function excludeSql(array $ids): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id): bool => $id > 0)));

        return $ids === [] ? '' : ' AND ts.id NOT IN (' . implode(',', $ids) . ')';
    }
}
