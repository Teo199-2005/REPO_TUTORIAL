<?php

namespace App\Commands;

use App\Models\TeacherScheduleModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Verification probe for the schedule conflict engine.
 *
 * Strictly read-only: it only calls the model's SELECT-based methods
 * (findConflicts / findExisting*Conflicts / assignedTeachers / scheduledTeachers),
 * never an insert, update or delete.
 *
 * The overlap rules are the only thing standing between the admin UI and a
 * double-booked section, teacher or room, so this command proves them against
 * the live timetable instead of against fixtures:
 *
 *     php spark schedule:conflict-probe
 *
 * It is data-driven - no id is hard-coded - so it works on any database. For
 * every stored block it computes the expected verdict with plain PHP (the
 * overlap predicate is trivial to state independently) and compares it with
 * what the engine answers. Any disagreement is a FAIL and the command exits
 * non-zero, so it can gate a deployment.
 */
class ScheduleConflictProbe extends BaseCommand
{
    protected $group       = 'Schedule';
    protected $name        = 'schedule:conflict-probe';
    protected $description = 'Probe the schedule conflict engine against the live DB (read-only).';
    protected $usage       = 'schedule:conflict-probe';

    private int $pass = 0;
    private int $fail = 0;

    /** @var list<string> */
    private array $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    /** @var list<array<string, mixed>> Every stored block. */
    private array $rows = [];

    public function run(array $params)
    {
        $model = model(TeacherScheduleModel::class);

        $this->rows = $this->loadRows();

        CLI::write('== Stored blocks ==', 'white');
        $this->dumpRows();

        $real = $this->realBlocks();
        CLI::write(
            PHP_EOL . sprintf(
                '  %d stored row(s), %d real weekday block(s), %d assignment placeholder(s).',
                count($this->rows),
                count($real),
                count($this->rows) - count($real)
            ),
            'dark_gray'
        );

        CLI::write(PHP_EOL . '== Per-block verdicts (engine vs. plain-PHP ground truth) ==', 'white');
        foreach ($real as $row) {
            $this->probeRow($model, $row, $real);
        }

        CLI::write(PHP_EOL . '== Overlapping pairs in the live data ==', 'white');
        $this->probePairs($model, $real);

        CLI::write(PHP_EOL . '== Touching blocks must never be reported ==', 'white');
        $this->probeTouching($model, $real);

        CLI::write(PHP_EOL . '== Guards ==', 'white');
        $this->probeGuards($model, $real);

        CLI::write(PHP_EOL . '== Teacher pre-selection on the section page ==', 'white');
        $this->probeAssignedTeachers($model);

        CLI::write(PHP_EOL . str_repeat('-', 60));
        CLI::write(
            'PASS ' . CLI::color((string) $this->pass, 'green')
            . '   FAIL ' . CLI::color((string) $this->fail, $this->fail === 0 ? 'green' : 'red'),
            'white'
        );

        return $this->fail === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }


    /**
     * Every stored block, with the item id resolved the way the engine does
     * (domain_id first for non-numerical rows, subject_id otherwise).
     *
     * @return list<array<string, mixed>>
     */
    private function loadRows(): array
    {
        return db_connect()->query(
            'SELECT id, section_id, teacher_id, day_of_week, start_time, end_time, room, school_year,
                    subject_id, domain_id,
                    COALESCE(domain_id, subject_id) AS item_id
             FROM teacher_schedules
             ORDER BY section_id, day_of_week, start_time'
        )->getResultArray();
    }

    /**
     * The blocks that occupy a visible slot: a weekday with a positive length.
     *
     * Assignment placeholder rows ('TBD'/'' day, 00:00:00-00:00:00) are written
     * by the assignment flow and can never overlap anything.
     *
     * @return list<array<string, mixed>>
     */
    private function realBlocks(): array
    {
        return array_values(array_filter($this->rows, function (array $row): bool {
            return in_array((string) $row['day_of_week'], $this->weekdays, true)
                && $this->clock($row['start_time']) < $this->clock($row['end_time']);
        }));
    }

    /**
     * One stored block: the engine's verdict per rule must match the count of
     * genuinely overlapping rows found with plain PHP.
     */
    private function probeRow(TeacherScheduleModel $model, array $row, array $real): void
    {
        $others = array_values(array_filter($real, static fn (array $o): bool => (int) $o['id'] !== (int) $row['id']));

        $expectedSection = $this->countOverlaps($row, $others, 'section_id');
        $expectedTeacher = $this->countOverlaps($row, $others, 'teacher_id');
        $expectedRoom    = $this->trimRoom($row['room']) === '' ? 0 : $this->countOverlaps($row, $others, 'room');

        // checkDuplicateItem is off: for an existing row its own "one subject
        // per day" status says nothing about the overlap rules.
        $conflicts = $model->findConflicts($this->slot($row), [], false);
        $types     = array_count_values(array_column($conflicts, 'type'));

        $label = sprintf(
            '#%s %s %s-%s sec=%s t=%s room=%s',
            $row['id'],
            $row['day_of_week'],
            substr($this->clock($row['start_time']), 0, 5),
            substr($this->clock($row['end_time']), 0, 5),
            $row['section_id'],
            $row['teacher_id'],
            $this->trimRoom($row['room']) === '' ? '(none)' : $row['room']
        );

        $this->check(
            $label . ' -> section (' . $expectedSection . ' overlapping)',
            ($types['section'] ?? 0) === ($expectedSection > 0 ? 1 : 0),
            $this->describe($conflicts)
        );
        $this->check(
            $label . ' -> teacher (' . $expectedTeacher . ' overlapping)',
            ($types['teacher'] ?? 0) === ($expectedTeacher > 0 ? 1 : 0),
            $this->describe($conflicts)
        );
        $this->check(
            $label . ' -> room (' . $expectedRoom . ' overlapping)',
            ($types['room'] ?? 0) === ($expectedRoom > 0 ? 1 : 0),
            $this->describe($conflicts)
        );
    }


    /**
     * Every overlapping pair in the database must show up in the banner list of
     * the section and of the teacher it belongs to.
     */
    private function probePairs(TeacherScheduleModel $model, array $real): void
    {
        $sectionPairs = 0;
        foreach ($this->scopeIds($real, 'section_id') as $sectionId) {
            $truth   = $this->pairwiseOverlaps($real, 'section_id', $sectionId);
            $found   = $model->findExistingSectionConflicts($sectionId);
            $sectionPairs += count($truth);

            $this->check(
                'section ' . $sectionId . ' banner reports ' . count($truth) . ' pair(s)',
                count($found) === count($truth),
                'engine=' . count($found) . ' truth=' . count($truth)
            );
        }

        $teacherPairs = 0;
        foreach ($this->scopeIds($real, 'teacher_id') as $teacherId) {
            $truth   = $this->pairwiseOverlaps($real, 'teacher_id', $teacherId);
            $found   = $model->findExistingTeacherConflicts($teacherId);
            $teacherPairs += count($truth);

            $this->check(
                'teacher ' . $teacherId . ' banner reports ' . count($truth) . ' pair(s)',
                count($found) === count($truth),
                'engine=' . count($found) . ' truth=' . count($truth)
            );
        }

        CLI::write(
            '  ' . CLI::color('INFO', 'yellow') . '  live data holds '
            . $sectionPairs . ' overlapping section pair(s) and '
            . $teacherPairs . ' overlapping teacher pair(s); both counts are validated above.',
            'white'
        );
    }

    /**
     * Two blocks that merely touch (09:00-10:00 and 10:00-11:00) share no
     * minute, so the engine must stay silent for them.
     */
    private function probeTouching(TeacherScheduleModel $model, array $real): void
    {
        $checked = 0;

        foreach ($real as $row) {
            $touching = array_filter(
                $real,
                fn (array $o): bool => (int) $o['id'] !== (int) $row['id']
                    && (string) $o['day_of_week'] === (string) $row['day_of_week']
                    && ((int) $o['section_id'] === (int) $row['section_id']
                        || (int) $o['teacher_id'] === (int) $row['teacher_id']
                        || ($this->trimRoom($row['room']) !== ''
                            && $this->trimRoom($o['room']) === $this->trimRoom($row['room'])))
                    && $this->clock($o['start_time']) === $this->clock($row['end_time'])
            );

            if ($touching === []) {
                continue;
            }

            $checked++;
            $conflicts = $model->findConflicts($this->slot($row), [], false);
            $this->check(
                'touching pair at block #' . $row['id'] . ' is not a conflict',
                $conflicts === [],
                $this->describe($conflicts)
            );
        }

        if ($checked === 0) {
            // Not a failure: the live timetable simply has no back-to-back
            // block, and this command must not write to find one.
            CLI::write(
                '  ' . CLI::color('INFO', 'yellow') . '  no touching pair exists in the live data '
                . '(covered by tests/unit/ScheduleOverlapTest.php).',
                'white'
            );
        }
    }

    /**
     * Malformed input must be rejected by the caller, never turned into an
     * invented conflict by the engine.
     */
    private function probeGuards(TeacherScheduleModel $model, array $real): void
    {
        if ($real === []) {
            CLI::write('  ' . CLI::color('INFO', 'yellow') . '  no real block to build guards from.', 'white');

            return;
        }

        $base   = $real[0];
        $sample = [
            'section_id'  => (int) $base['section_id'],
            'teacher_id'  => (int) $base['teacher_id'],
            'item_id'     => (int) $base['item_id'],
            'day_of_week' => (string) $base['day_of_week'],
            'start_time'  => substr($this->clock($base['start_time']), 0, 5),
            'end_time'    => substr($this->clock($base['end_time']), 0, 5),
            'room'        => (string) $base['room'],
        ];

        $this->check(
            'empty day yields no conflict',
            $model->findConflicts(array_merge($sample, ['day_of_week' => ''])) === []
        );
        $this->check(
            'reversed time range yields no conflict',
            $model->findConflicts(array_merge($sample, ['start_time' => '09:00', 'end_time' => '08:00'])) === []
        );
        $this->check(
            'a block does not collide with itself',
            $model->findConflicts(array_merge($sample, ['id' => (int) $base['id']]), [], false) === [],
            $this->describe($model->findConflicts(array_merge($sample, ['id' => (int) $base['id']]), [], false))
        );

        // Two overlapping blocks submitted in one save are invisible to the
        // database, so they have to be caught in memory.
        $sibling  = array_merge($sample, ['item_id' => 0]);
        $proposal = array_merge($sample, ['id' => (int) $base['id']]);

        $this->check(
            'sibling blocks in one save are rejected',
            $model->findConflicts($proposal, [$sibling], false) !== [],
            'the in-memory comparison found nothing'
        );
    }


    /**
     * How many of $others share $key with $row, fall on the same weekday and
     * overlap it in time. Plain PHP on purpose: this is the ground truth the
     * SQL engine is compared against.
     */
    private function countOverlaps(array $row, array $others, string $key): int
    {
        $count = 0;

        foreach ($others as $other) {
            if ((int) ($other[$key] ?? 0) !== (int) ($row[$key] ?? 0)
                || strcasecmp((string) $other['day_of_week'], (string) $row['day_of_week']) !== 0) {
                continue;
            }

            if ($key === 'room'
                && ($this->trimRoom($row['room']) === ''
                    || $this->trimRoom($other['room']) !== $this->trimRoom($row['room']))) {
                continue;
            }

            if ($this->clock($other['start_time']) < $this->clock($row['end_time'])
                && $this->clock($row['start_time']) < $this->clock($other['end_time'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Every overlapping pair inside one scope (one section, or one teacher).
     *
     * @return list<array{0: mixed, 1: mixed}>
     */
    private function pairwiseOverlaps(array $real, string $key, int $scopeId): array
    {
        $scoped = array_values(array_filter($real, static fn (array $r): bool => (int) $r[$key] === $scopeId));
        $pairs  = [];

        foreach ($scoped as $i => $a) {
            foreach ($scoped as $j => $b) {
                if ($j <= $i || (string) $a['day_of_week'] !== (string) $b['day_of_week']) {
                    continue;
                }

                if ($this->clock($a['start_time']) < $this->clock($b['end_time'])
                    && $this->clock($b['start_time']) < $this->clock($a['end_time'])) {
                    $pairs[] = [$a['id'], $b['id']];
                }
            }
        }

        return $pairs;
    }

    /**
     * The section / teacher ids holding at least one real block.
     *
     * @return list<int>
     */
    private function scopeIds(array $real, string $key): array
    {
        $ids = [];

        foreach ($real as $row) {
            $ids[(int) $row[$key]] = true;
        }

        return array_keys($ids);
    }

    /**
     * A stored row as the engine's $slot payload.
     */
    private function slot(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'section_id'  => (int) $row['section_id'],
            'teacher_id'  => (int) $row['teacher_id'],
            'item_id'     => (int) $row['item_id'],
            'day_of_week' => (string) $row['day_of_week'],
            'start_time'  => $this->clock($row['start_time']),
            'end_time'    => $this->clock($row['end_time']),
            'room'        => trim((string) $row['room']),
        ];
    }

    /**
     * 'HH:MM:SS' as both MySQL and the engine's string comparison expect it.
     */
    private function clock(mixed $time): string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return '';
        }

        $parts = explode(':', $time);
        $hour  = (int) ($parts[0] ?? 0);
        $min   = (int) ($parts[1] ?? 0);
        $sec   = (int) ($parts[2] ?? 0);

        return sprintf('%02d:%02d:%02d', $hour, $min, $sec);
    }

    /**
     * Rooms compare case-insensitively, like MySQL's default collation.
     */
    private function trimRoom(mixed $room): string
    {
        return strtolower(trim((string) $room));
    }

    private function check(string $label, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->pass++;
            CLI::write('  ' . CLI::color('PASS', 'green') . '  ' . $label);

            return;
        }

        $this->fail++;
        CLI::write('  ' . CLI::color('FAIL', 'red') . '  ' . $label . ($detail !== '' ? ' -> ' . $detail : ''));
    }

    private function describe(array $conflicts): string
    {
        if ($conflicts === []) {
            return '[]';
        }

        $parts = [];
        foreach ($conflicts as $conflict) {
            $parts[] = ($conflict['type'] ?? '?') . ': ' . ($conflict['message'] ?? '');
        }

        return implode(' | ', $parts);
    }

    /**
     * True for the rows the assignment flow writes: a 'TBD'/'' day carrying no
     * timetable slot. Those rows hold who teaches an item, not when.
     */
    private function isPlaceholder(array $row): bool
    {
        return ! in_array((string) $row['day_of_week'], $this->weekdays, true);
    }

    /**
     * The item a row holds, resolved the way the assignment lookup resolves it:
     * through the column that belongs to the row's own section.
     *
     * This is NOT the COALESCE(domain_id, subject_id) the conflict engine uses.
     * A domain id left in a numerical section's row - the ids collide, so stray
     * values exist - belongs to no subject that section can schedule, and the
     * assignment lookup deliberately ignores it. Returns 0 for such a row.
     */
    private function assignmentItemId(array $row): int
    {
        $column = schedule_item_column((int) $row['section_id']);

        return (int) ($row[$column] ?? 0);
    }

    /**
     * The Teacher field the section page pre-selects.
     *
     * The page takes the FIRST entry of the choices list, so the model must
     * resolve the teacher the Sections page recorded - current school year
     * first, newest row next - and that entry must be the one flagged
     * `assigned`. The expectation is recomputed here from the already-loaded
     * rows, so both sides are derived independently.
     */
    private function probeAssignedTeachers(TeacherScheduleModel $model): void
    {
        $currentYear  = get_current_school_year();
        $placeholders = [];
        $expected     = [];

        $stray = 0;

        foreach ($this->rows as $row) {
            if (! $this->isPlaceholder($row)) {
                continue;
            }

            $sectionId = (int) $row['section_id'];
            $itemId    = $this->assignmentItemId($row);

            if ($itemId <= 0) {
                $stray++;

                continue;
            }

            $key       = $sectionId . ':' . $itemId;
            $isCurrent = (string) $row['school_year'] === $currentYear;

            $placeholders[$sectionId][$itemId] = true;

            if (! isset($expected[$key])
                || ($isCurrent && ! $expected[$key]['current'])
                || ($isCurrent === $expected[$key]['current'] && (int) $row['id'] > $expected[$key]['row'])
            ) {
                $expected[$key] = [
                    'current' => $isCurrent,
                    'row'     => (int) $row['id'],
                    'teacher' => (int) $row['teacher_id'],
                ];
            }
        }

        if ($expected === []) {
            CLI::write('  INFO  no assignment placeholder rows exist; nothing to resolve.', 'dark_gray');
        }

        foreach ($expected as $key => $want) {
            [$sectionId, $itemId] = array_map('intval', explode(':', (string) $key));

            $resolved = $model->assignedTeachers($sectionId, [$itemId]);
            $actual   = $resolved[$itemId] ?? null;

            $this->check(
                sprintf('section %d item %d -> assigned teacher %d (row #%d)', $sectionId, $itemId, $want['teacher'], $want['row']),
                $actual === $want['teacher'],
                sprintf('the model resolved %s', var_export($actual, true))
            );

            $choices = $model->teacherChoices($actual, 0, $model->teacherChoicesPool());
            $first   = $choices[0] ?? [];

            $this->check(
                sprintf('section %d item %d -> is the first, flagged choice', $sectionId, $itemId),
                (int) ($first['id'] ?? 0) === $want['teacher'] && ($first['assigned'] ?? false) === true,
                'the list led with ' . json_encode($first)
            );
        }

        // A section/item pair that only ever had a real block must NOT be
        // reported as assigned: the assignment query matches assignment rows only.
        foreach ($this->rows as $row) {
            if ($this->isPlaceholder($row)) {
                continue;
            }

            $sectionId = (int) $row['section_id'];
            $itemId    = $this->assignmentItemId($row);

            if ($itemId <= 0 || isset($placeholders[$sectionId][$itemId])) {
                continue;
            }

            $assigned = $model->assignedTeachers($sectionId, [$itemId]);

            $this->check(
                sprintf('section %d item %d has a block but no assignment -> resolved as unassigned', $sectionId, $itemId),
                $assigned === [],
                'the assignment query matched a timetable row: ' . json_encode($assigned)
            );

            break;
        }

        // The fallback path: for a pair that has a real block, scheduledTeachers()
        // must resolve one of the teachers who actually teaches it.
        foreach ($this->rows as $row) {
            if ($this->isPlaceholder($row)) {
                continue;
            }

            $sectionId = (int) $row['section_id'];
            $itemId    = $this->assignmentItemId($row);

            if ($itemId <= 0) {
                continue;
            }

            $teaches = [];
            foreach ($this->rows as $candidate) {
                if (! $this->isPlaceholder($candidate)
                    && (int) $candidate['section_id'] === $sectionId
                    && $this->assignmentItemId($candidate) === $itemId
                ) {
                    $teaches[] = (int) $candidate['teacher_id'];
                }
            }

            $teaches  = array_values(array_unique($teaches));
            $fallback = $model->scheduledTeachers($sectionId, [$itemId]);
            $resolved = $fallback[$itemId] ?? null;

            $this->check(
                sprintf('section %d item %d -> fallback resolves one of its %d teacher(s)', $sectionId, $itemId, count($teaches)),
                $resolved !== null && in_array($resolved, $teaches, true),
                sprintf('the fallback resolved %s, expected one of %s', var_export($resolved, true), json_encode($teaches))
            );

            break;
        }

        if ($stray > 0) {
            CLI::write(
                sprintf(
                    '  INFO  %d row(s) hold an item id their section cannot schedule (stray domain ids); skipped.',
                    $stray
                ),
                'dark_gray'
            );
        }

        // The assignment query must match assignment rows only: an item id that
        // nothing references has to resolve to no teacher at all.
        $sectionIds = array_values(array_unique(array_map('intval', array_column($this->rows, 'section_id'))));

        $unknownItem = 0;
        foreach ($this->rows as $row) {
            $unknownItem = max($unknownItem, $this->assignmentItemId($row));
        }
        $unknownItem += 1000;

        foreach ($sectionIds as $sectionId) {
            $this->check(
                sprintf('section %d -> an item nothing references resolves to no teacher', $sectionId),
                $model->assignedTeachers($sectionId, [$unknownItem]) === []
                    && $model->scheduledTeachers($sectionId, [$unknownItem]) === [],
                'an unknown item id matched schedule rows'
            );
        }

        $this->check(
            'an unassigned subject flags no teacher as assigned',
            array_filter(
                $model->teacherChoices(null, 0, $model->teacherChoicesPool()),
                static fn (array $choice): bool => (bool) $choice['assigned']
            ) === [],
            'a teacher was flagged assigned with no assignment row'
        );

        // The Teacher field is fixed to the assignment: every real weekday block
        // must point at the teacher the Sections page recorded for its item.
        // Pairs with no assignment row are skipped (the grid blocks those saves
        // instead); the fallback (who already has a block) does NOT count.
        $pairs = [];

        foreach ($this->rows as $row) {
            if (! $this->isPlaceholder($row)) {
                continue;
            }

            $pairs[(int) $row['section_id'] . ':' . $this->assignmentItemId($row)] = true;
        }

        $expectedWinners = [];

        foreach (array_keys($pairs) as $key) {
            [$sectionId, $itemId] = array_map('intval', explode(':', (string) $key));

            if ($itemId <= 0) {
                continue;
            }

            $resolved = $model->assignedTeachers($sectionId, [$itemId]);

            $expectedWinners[$key] = $resolved[$itemId] ?? null;
        }

        $drift = 0;

        foreach ($this->rows as $row) {
            if ($this->isPlaceholder($row)) {
                continue;
            }

            $sectionId = (int) $row['section_id'];
            $itemId    = $this->assignmentItemId($row);

            if ($itemId <= 0) {
                continue;
            }

            $key      = $sectionId . ':' . $itemId;
            $expected = $expectedWinners[$key] ?? null;

            if ($expected === null) {
                continue;
            }

            $this->check(
                sprintf(
                    'block #%d (section %d item %d) stores its assigned teacher %d',
                    (int) $row['id'],
                    $sectionId,
                    $itemId,
                    $expected
                ),
                (int) $row['teacher_id'] === $expected,
                sprintf(
                    'the block stores teacher %d but the assignment says %d',
                    (int) $row['teacher_id'],
                    $expected
                )
            );

            if ((int) $row['teacher_id'] !== $expected) {
                $drift++;
            }
        }

        if ($drift > 0) {
            CLI::write(
                '  INFO  fix a drifted block with: UPDATE teacher_schedules SET teacher_id = <assignee>, updated_at = NOW() WHERE id = <block> AND section_id = <section>;',
                'dark_gray'
            );
        }
    }

    private function dumpRows(): void
    {
        foreach ($this->rows as $row) {
            CLI::write(sprintf(
                '  #%-4s sec=%-4s t=%-5s %-9s %s-%s room=%-10s %s',
                $row['id'],
                $row['section_id'],
                $row['teacher_id'],
                $row['day_of_week'] === '' ? '(blank)' : $row['day_of_week'],
                $this->clock($row['start_time']) === '' ? '--:--' : substr($this->clock($row['start_time']), 0, 5),
                $this->clock($row['end_time']) === '' ? '--:--' : substr($this->clock($row['end_time']), 0, 5),
                $this->trimRoom($row['room']) === '' ? '(none)' : $row['room'],
                $row['school_year']
            ), 'dark_gray');
        }
    }
}
