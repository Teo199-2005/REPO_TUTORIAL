<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Schedules extends BaseController
{
    public function index()
    {
        $sectionModel = new \App\Models\SectionModel();
        $sections = $sectionModel->getSectionsWithAdviser(get_current_school_year());
        
        return view('admin/schedules', [
            'title' => 'Section Schedules - CSCS Tap n Track',
            'sections' => $sections
        ]);
    }
    
    public function section($sectionId)
    {
        $sectionModel = new \App\Models\SectionModel();
        $db = \Config\Database::connect();
        
        // Get section with adviser information
        $section = $db->query(
            "SELECT s.*, CONCAT(t.first_name, ' ', t.last_name) as adviser_name
             FROM sections s
             LEFT JOIN teachers t ON t.id = s.adviser_id
             WHERE s.id = ?",
            [$sectionId]
        )->getRowArray();
        
        if (!$section) {
            return redirect()->to('admin/schedules')->with('error', 'Section not found');
        }
        
        return view('admin/section_schedule', [
            'title' => 'Manage Schedule - ' . $section['section_name'],
            'section' => $section,
            'isNonNumerical' => ($section['grading_type'] ?? 'numerical') === 'non_numerical',
            // Overlaps that pre-date the validation (legacy rows, rows from an
            // older school year) are reported up-front instead of letting the
            // admin rediscover them one rejected block at a time.
            'conflicts' => model(\App\Models\TeacherScheduleModel::class)
                ->findExistingSectionConflicts((int) $sectionId),
        ]);
    }
    
    public function getSchedules($sectionId, $day)
    {
        $db = \Config\Database::connect();

        // Non-numerical sections store a developmental-domain id in
        // subject_id, so the label must come from sned_categories.
        $section = $db->table('sections')->where('id', (int) $sectionId)->get()->getRowArray();
        $isNonNumerical = $section && ($section['grading_type'] ?? 'numerical') === 'non_numerical';

        if ($isNonNumerical) {
            $schedules = $db->query(
                "SELECT ts.*, sc.name AS subject_name, CONCAT(t.first_name, ' ', t.last_name) as teacher_name, " . $this->itemIdExpr() . " AS item_id
                 FROM teacher_schedules ts
                 LEFT JOIN sned_categories sc ON sc.id = " . $this->itemIdExpr() . "
                 LEFT JOIN teachers t ON t.id = ts.teacher_id
                 WHERE ts.section_id = ? AND ts.day_of_week = ?
                 ORDER BY ts.start_time",
                [$sectionId, $day]
            )->getResultArray();
        } else {
            $schedules = $db->query(
                "SELECT ts.*, s.subject_name, CONCAT(t.first_name, ' ', t.last_name) as teacher_name, " . $this->itemIdExpr() . " AS item_id
                 FROM teacher_schedules ts
                 LEFT JOIN subjects s ON s.id = ts.subject_id
                 LEFT JOIN teachers t ON t.id = ts.teacher_id
                 WHERE ts.section_id = ? AND ts.day_of_week = ?
                 ORDER BY ts.start_time",
                [$sectionId, $day]
            )->getResultArray();
        }
        
        return $this->response->setJSON(['success' => true, 'schedules' => $schedules]);
    }
    
    public function form($sectionId, $day)
    {
        $db = \Config\Database::connect();
        $subjects = $db->table('section_subjects ss')
            ->select('s.*')
            ->join('subjects s', 's.id = ss.subject_id')
            ->where('ss.section_id', $sectionId)
            ->get()->getResultArray();
        
        $teachers = $db->table('teachers')->where('employment_status', 'active')->get()->getResultArray();
        
        $html = '<div class="modal fade" id="scheduleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form onsubmit="saveSchedule(event)" action="' . base_url('admin/schedules/save') . '">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Schedule - ' . $day . '</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="section_id" value="' . $sectionId . '">
                            <input type="hidden" name="day_of_week" value="' . $day . '">
                            <div class="mb-3">
                                <label>Subject</label>
                                <select name="subject_id" class="form-select" required>';
        foreach ($subjects as $s) {
            $html .= '<option value="' . $s['id'] . '">' . esc($s['subject_name']) . '</option>';
        }
        $html .= '</select>
                            </div>
                            <div class="mb-3">
                                <label>Teacher</label>
                                <select name="teacher_id" class="form-select" required>';
        foreach ($teachers as $t) {
            $html .= '<option value="' . $t['id'] . '">' . esc($t['first_name'] . ' ' . $t['last_name']) . '</option>';
        }
        $html .= '</select>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label>Start Time</label>
                                    <input type="time" name="start_time" class="form-control" required>
                                </div>
                                <div class="col-6 mb-3">
                                    <label>End Time</label>
                                    <input type="time" name="end_time" class="form-control" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Room</label>
                                <input type="text" name="room" class="form-control">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';
        
        return $this->response->setBody($html);
    }
    
    public function save()
    {
        $db = \Config\Database::connect();

        // --- Validate before touching the database ---------------------------
        // This server runs MySQL without STRICT_TRANS_TABLES, so an invalid
        // day_of_week is NOT rejected: it is silently stored as '' (an empty
        // ENUM) and creates a row no day tab can ever display. A missing id
        // violates a NOT NULL column. Both used to surface in the UI as the
        // useless "Failed to save changes. Unknown error".
        $parsed = $this->validatedSlot([
            'section_id'  => $this->request->getPost('section_id'),
            'subject_id'  => $this->request->getPost('subject_id'),
            'teacher_id'  => $this->request->getPost('teacher_id'),
            'day_of_week' => $this->request->getPost('day_of_week'),
            'start_time'  => $this->request->getPost('start_time'),
            'end_time'    => $this->request->getPost('end_time'),
            'room'        => $this->request->getPost('room'),
        ]);

        if (! $parsed['ok']) {
            return $this->jsonError((string) $parsed['error']);
        }

        $slot = $parsed['slot'];

        // Non-numerical sections schedule developmental domains, which must be
        // stored in domain_id: subject_id carries a FOREIGN KEY to subjects and
        // domain ids collide with subject ids, so a domain id written there
        // would silently attach an unrelated subject.
        $isNonNumerical = is_non_numerical_section((int) $slot['section_id']);
        if ($isNonNumerical && ! schedule_domain_column_ready()) {
            return $this->jsonError(
                'This section is graded non-numerically, but the database is missing the '
                . 'teacher_schedules.domain_id column required to schedule its developmental domains. '
                . 'Add the column, then try again.'
            );
        }

        // --- Conflict gate ---------------------------------------------------
        // One shared engine (TeacherScheduleModel::findConflicts) rejects every
        // way this block could collide with the timetable: another block at an
        // overlapping time in the same section, the teacher already being
        // booked elsewhere at the same time, the room already occupied, or the
        // same subject/domain appearing twice in this section on one day.
        $conflicts = model(\App\Models\TeacherScheduleModel::class)->findConflicts($slot);

        if ($conflicts !== []) {
            return $this->conflictErrorResponse($conflicts);
        }

        $data = [
            'section_id' => $slot['section_id'],
            'teacher_id' => $slot['teacher_id'],
            'day_of_week' => $slot['day_of_week'],
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'room' => $slot['room'] === '' ? null : $slot['room'],
            'school_year' => get_current_school_year(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($isNonNumerical) {
            $data['domain_id']  = $slot['item_id'];
            $data['subject_id'] = null;
        } else {
            $data['subject_id'] = $slot['item_id'];
        }

        if ($db->table('teacher_schedules')->insert($data)) {
            audit_event('schedule.created', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'schedule',
                'resource_id'   => (string) $db->insertID(),
                'description'   => 'Class schedule block added',
                'after'         => [
                    'section_id'  => $slot['section_id'],
                    'teacher_id'  => $slot['teacher_id'],
                    'day_of_week' => $slot['day_of_week'],
                    'time'        => $slot['start_time'] . '-' . $slot['end_time'],
                    'room'        => $slot['room'],
                ],
            ]);

            return $this->response->setJSON(['success' => true]);
        }
        return $this->response->setJSON(['success' => false, 'error' => 'Failed to save']);
    }
    
    /**
     * Save every new block added in one "Save Schedules" click.
     *
     * The page used to POST each block in parallel, so two blocks added in the
     * same click were checked by two independent requests that could not see
     * each other - and could both land on the same section slot. One request
     * validates the whole set here: the model compares the blocks against each
     * other (as siblings) as well as against the stored timetable, then writes
     * them inside a single transaction.
     *
     * Body: JSON { schedules: [ { key, section_id, subject_id, teacher_id,
     * day_of_week, start_time, end_time, room, item_name?, section_name?,
     * teacher_name? }, ... ] }. "key" echoes the row id the view attached to
     * each block so a rejected block can be flagged on the exact row.
     */
    public function saveBatch()
    {
        // Accepts both shapes: the section page posts a multipart "schedules"
        // array (so the global CSRF token travels in the X-CSRF-TOKEN header),
        // while a JSON body is still honoured for programmatic callers.
        $input  = $this->request->getJSON(true);
        $blocks = is_array($input) ? ($input['schedules'] ?? []) : [];

        if (! is_array($blocks) || $blocks === []) {
            $blocks = $this->request->getPost('schedules');
        }

        if (! is_array($blocks) || $blocks === []) {
            return $this->jsonError('No schedule data was submitted.');
        }

        $scheduleModel = model(\App\Models\TeacherScheduleModel::class);
        $validated     = [];
        $errors        = [];

        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $parsed = $this->validatedSlot($block);

            if (! $parsed['ok']) {
                $errors[] = [
                    'key'     => (string) ($block['key'] ?? ''),
                    'message' => (string) $parsed['error'],
                ];
                continue;
            }

            $slot = array_merge($parsed['slot'], [
                // Labels the conflict messages quote when a sibling block is
                // the culprit (the sibling is not in the database yet).
                'item_name'    => trim((string) ($block['item_name'] ?? '')),
                'section_name' => trim((string) ($block['section_name'] ?? '')),
                'teacher_name' => trim((string) ($block['teacher_name'] ?? '')),
            ]);

            $slot['key'] = (string) ($block['key'] ?? ('#' . ((int) $index + 1)));

            $conflicts = $scheduleModel->findConflicts($slot, $validated);

            if ($conflicts !== []) {
                foreach ($conflicts as $conflict) {
                    $errors[] = [
                        'key'     => $slot['key'],
                        'message' => (string) $conflict['message'],
                    ];
                }
                continue;
            }

            $validated[] = $slot;
        }

        if ($errors !== []) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => implode('<br>', array_column($errors, 'message')),
                'errors'  => $errors,
            ], 409);
        }

        if ($validated === []) {
            return $this->jsonError('No schedule data was submitted.');
        }

        // Non-numerical sections cannot be written without the domain column;
        // fail before the transaction starts instead of half-way through it.
        foreach ($validated as $slot) {
            if (is_non_numerical_section((int) $slot['section_id']) && ! schedule_domain_column_ready()) {
                return $this->jsonError(
                    'This section is graded non-numerically, but the database is missing the '
                    . 'teacher_schedules.domain_id column required to schedule its developmental domains. '
                    . 'Add the column, then try again.'
                );
            }
        }

        $db         = \Config\Database::connect();
        $now        = date('Y-m-d H:i:s');
        $schoolYear = get_current_school_year();

        $db->transStart();

        $saved = 0;
        $savedKeys = [];
        foreach ($validated as $slot) {
            $data = [
                'section_id'  => $slot['section_id'],
                'teacher_id'  => $slot['teacher_id'],
                'day_of_week' => $slot['day_of_week'],
                'start_time'  => $slot['start_time'],
                'end_time'    => $slot['end_time'],
                'room'        => $slot['room'] === '' ? null : $slot['room'],
                'school_year' => $schoolYear,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];

            if (is_non_numerical_section((int) $slot['section_id'])) {
                $data['domain_id']  = $slot['item_id'];
                $data['subject_id'] = null;
            } else {
                $data['subject_id'] = $slot['item_id'];
            }

            if ($db->table('teacher_schedules')->insert($data)) {
                $saved++;
                // Echoed back so the view can keep the block that was written
                // and only discard the rows the server rejected.
                $savedKeys[] = (string) ($slot['key'] ?? '');
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->jsonError('The schedules could not be saved (database error).', 500);
        }

        audit_event('schedule.batch_saved', [
            'category'      => 'data',
            'status'        => 'success',
            'resource_type' => 'schedule',
            'description'   => 'Schedule batch saved (' . (int) $saved . ' block' . ((int) $saved === 1 ? '' : 's') . ')',
            'metadata'      => ['saved' => (int) $saved],
        ]);

        return $this->response->setJSON(['success' => true, 'saved' => $saved, 'saved_keys' => $savedKeys]);
    }

    public function delete($id)
    {
        $db = \Config\Database::connect();

        $existing = $db->table('teacher_schedules')->where('id', $id)->get()->getRowArray();

        if ($db->table('teacher_schedules')->where('id', $id)->delete()) {
            audit_event('schedule.deleted', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'schedule',
                'resource_id'   => (string) $id,
                'description'   => 'Class schedule block deleted',
                'before'        => $existing === null ? null : [
                    'section_id'  => $existing['section_id'] ?? null,
                    'teacher_id'  => $existing['teacher_id'] ?? null,
                    'day_of_week' => $existing['day_of_week'] ?? null,
                    'start_time'  => $existing['start_time'] ?? null,
                    'end_time'    => $existing['end_time'] ?? null,
                ],
            ]);

            return $this->response->setJSON(['success' => true]);
        }
        return $this->response->setJSON(['success' => false]);
    }
    
    /**
     * Update the room of an existing block.
     *
     * Only the room check is re-run: the teacher and the subject/domain of the
     * row are untouched, so re-validating them could reject an edit the row
     * itself already satisfies. The check still goes through the shared engine,
     * which compares real overlap - the query this replaces demanded an exact
     * start/end match, so an occupied room looked free whenever two blocks
     * merely overlapped instead of starting together.
     */
    public function updateRoom()
    {
        $db = \Config\Database::connect();

        $scheduleId = (int) $this->request->getPost('schedule_id');
        $room       = trim((string) $this->request->getPost('room'));

        if ($scheduleId <= 0) {
            return $this->jsonError('No schedule row was selected for this room.');
        }

        if (strlen($room) > schedule_room_max_length()) {
            return $this->jsonError('The room may not be longer than ' . schedule_room_max_length() . ' characters.');
        }

        $currentSchedule = $db->table('teacher_schedules')->where('id', $scheduleId)->get()->getRowArray();

        if (! $currentSchedule) {
            return $this->jsonError('Schedule not found', 404);
        }

        // findConflicts() checks the section and the teacher too; those are not
        // being edited here, so only the room verdict is acted on. A legacy
        // section overlap must not make the room field read-only.
        $conflicts = array_values(array_filter(
            model(\App\Models\TeacherScheduleModel::class)->findConflicts([
                'id'          => $scheduleId,
                'section_id'  => (int) $currentSchedule['section_id'],
                'teacher_id'  => (int) $currentSchedule['teacher_id'],
                'item_id'     => $this->rowItemId($currentSchedule),
                'day_of_week' => (string) $currentSchedule['day_of_week'],
                'start_time'  => (string) $currentSchedule['start_time'],
                'end_time'    => (string) $currentSchedule['end_time'],
                'room'        => $room,
            ], [], false),
            static fn (array $conflict): bool => ($conflict['type'] ?? '') === 'room'
        ));

        if ($conflicts !== []) {
            return $this->conflictErrorResponse($conflicts);
        }

        $updated = $db->table('teacher_schedules')->where('id', $scheduleId)->update([
            'room'       => $room === '' ? null : $room,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($updated) {
            return $this->response->setJSON(['success' => true]);
        }

        return $this->jsonError('Failed to update room', 500);
    }
    
    /**
     * Replace the subject/domain and the teacher of an existing block.
     *
     * Both dropdowns can collide: the new teacher may already be booked
     * elsewhere, and the new subject may already run in this section on that
     * day. The shared engine re-checks the whole block (section, teacher, room,
     * duplicate) with this row excluded, so a block cannot be edited into a
     * conflict - the check this replaces only looked at the teacher, so a
     * subject/domain could be edited onto a slot another block already used.
     */
    public function updateBothFields()
    {
        $db = \Config\Database::connect();

        $scheduleId = (int) $this->request->getPost('schedule_id');
        $itemId     = (int) $this->request->getPost('subject_id');
        $teacherId  = (int) $this->request->getPost('teacher_id');

        if ($scheduleId <= 0) {
            return $this->jsonError('No schedule row was selected.');
        }

        if ($itemId <= 0) {
            return $this->jsonError('Select a subject (or developmental domain) for this block.');
        }

        if ($teacherId <= 0) {
            return $this->jsonError('Select a teacher for this block.');
        }

        $currentSchedule = $db->table('teacher_schedules')->where('id', $scheduleId)->get()->getRowArray();

        if (! $currentSchedule) {
            return $this->jsonError('Schedule not found', 404);
        }

        $sectionId      = (int) $currentSchedule['section_id'];
        $isNonNumerical = is_non_numerical_section($sectionId);

        if ($isNonNumerical && ! schedule_domain_column_ready()) {
            return $this->jsonError(
                'The database is missing teacher_schedules.domain_id, so this non-numerical '
                . "section's developmental domains cannot be scheduled yet."
            );
        }

        // The row's own id is excluded inside the engine, so the block is not
        // reported as conflicting with itself.
        $conflicts = model(\App\Models\TeacherScheduleModel::class)->findConflicts([
            'id'          => $scheduleId,
            'section_id'  => $sectionId,
            'teacher_id'  => $teacherId,
            'item_id'     => $itemId,
            'day_of_week' => (string) $currentSchedule['day_of_week'],
            'start_time'  => (string) $currentSchedule['start_time'],
            'end_time'    => (string) $currentSchedule['end_time'],
            'room'        => trim((string) ($currentSchedule['room'] ?? '')),
        ]);

        if ($conflicts !== []) {
            return $this->conflictErrorResponse($conflicts);
        }

        $data = [
            'teacher_id' => $teacherId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // The replaced item may be a subject or a developmental domain; it has
        // to land in the column that matches the section's grading type.
        if ($isNonNumerical) {
            $data['domain_id']  = $itemId;
            $data['subject_id'] = null;
        } else {
            $data['subject_id'] = $itemId;

            if (schedule_domain_column_ready()) {
                $data['domain_id'] = null;
            }
        }

        if ($db->table('teacher_schedules')->where('id', $scheduleId)->update($data)) {
            return $this->response->setJSON(['success' => true]);
        }

        return $this->jsonError('Failed to update schedule', 500);
    }
    
    /**
     * Move a whole stored time slot to new times.
     *
     * The row's times are stored DATA, not just a label: the grid groups blocks
     * by start/end, so everything the section has in that slot moves together.
     * Every moved block is re-validated against the timetable with the moved
     * rows excluded, so a slot can never be dragged onto an occupied teacher,
     * room or section.
     */
    public function updateTime()
    {
        $db = \Config\Database::connect();

        $sectionId  = (int) $this->request->getPost('section_id');
        $day        = trim((string) $this->request->getPost('day_of_week'));
        $oldStart   = schedule_normalize_time((string) $this->request->getPost('old_start_time'));
        $oldEnd     = schedule_normalize_time((string) $this->request->getPost('old_end_time'));
        $newStart   = schedule_normalize_time((string) $this->request->getPost('start_time'));
        $newEnd     = schedule_normalize_time((string) $this->request->getPost('end_time'));

        if ($sectionId <= 0 || ! in_array($day, schedule_allowed_days(), true)) {
            return $this->jsonError('The section or the day of this time slot is invalid.');
        }

        if ($oldStart === null || $oldEnd === null || $newStart === null || $newEnd === null) {
            return $this->jsonError('Enter a valid start and end time (HH:MM).');
        }

        if ($newEnd <= $newStart) {
            return $this->jsonError('The end time must be later than the start time.');
        }

        $rows = $db->table('teacher_schedules')
            ->where('section_id', $sectionId)
            ->where('day_of_week', $day)
            ->where('start_time', $oldStart)
            ->where('end_time', $oldEnd)
            ->get()->getResultArray();

        if ($rows === []) {
            return $this->jsonError('That time slot is no longer on the server. Reload the page and try again.', 404);
        }

        $model     = model(\App\Models\TeacherScheduleModel::class);
        $moveIds   = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $conflicts = [];

        foreach ($rows as $row) {
            $conflicts = array_merge($conflicts, $model->findConflicts([
                'id'          => (int) $row['id'],
                'section_id'  => $sectionId,
                'teacher_id'  => (int) $row['teacher_id'],
                'item_id'     => $this->rowItemId($row),
                'day_of_week' => $day,
                'start_time'  => $newStart,
                'end_time'    => $newEnd,
                'room'        => trim((string) ($row['room'] ?? '')),
            ], [], true, $moveIds));
        }

        if ($conflicts !== []) {
            return $this->conflictErrorResponse($conflicts);
        }

        $db->transStart();

        foreach ($moveIds as $id) {
            $db->table('teacher_schedules')->where('id', $id)->update([
                'start_time' => $newStart,
                'end_time'   => $newEnd,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->jsonError('The time slot could not be moved (database error).', 500);
        }

        return $this->response->setJSON(['success' => true, 'moved' => count($moveIds)]);
    }

    public function subjects($sectionId)
    {
        $db = \Config\Database::connect();
        $subjects = $db->table('section_subjects ss')
            ->select('s.*')
            ->join('subjects s', 's.id = ss.subject_id')
            ->where('ss.section_id', $sectionId)
            ->where('ss.is_active', 1)
            ->get()->getResultArray();
        
        return $this->response->setJSON(['success' => true, 'subjects' => $subjects]);
    }
    
    public function subjectTeachers($sectionId)
    {
        $db = \Config\Database::connect();

        // Non-numerical sections schedule developmental domains instead of
        // subjects. Only the domains the admin ticked for this section
        // (checkboxes on the Sections page) may be scheduled; a section that
        // was never configured falls back to every shared domain.
        $section = $db->table('sections')->where('id', (int) $sectionId)->get()->getRowArray();
        $isNonNumerical = $section && ($section['grading_type'] ?? 'numerical') === 'non_numerical';

        if ($isNonNumerical) {
            $categoryModel = new \App\Models\SnedCategoryModel();
            $selectedIds = section_selected_domain_ids((int) $sectionId);

            if ($selectedIds === null) {
                $domains = $categoryModel->getActiveCategories((int) $sectionId, (int) ($section['grade_level'] ?? 0));
            } elseif ($selectedIds !== []) {
                $domains = $db->table('sned_categories')
                    ->whereIn('id', $selectedIds)
                    ->where('is_active', 1)
                    ->orderBy('display_order', 'ASC')
                    ->get()->getResultArray();
            } else {
                $domains = [];
            }

            $items = $domains;
        } else {
            // Numerical sections schedule subjects.
            $items = $db->table('section_subjects ss')
                ->select('s.id, s.subject_name')
                ->join('subjects s', 's.id = ss.subject_id')
                ->where('ss.section_id', $sectionId)
                ->where('ss.is_active', 1)
                ->get()->getResultArray();
        }

        // Both modes are normalised to the same shape - an id plus a name, where
        // the name is the subject for numerical sections and the developmental
        // domain for non-numerical ones - so the view's JS works unchanged.
        $items = array_values(array_filter(array_map(
            static function (array $item): array {
                return [
                    'id'   => (int) ($item['id'] ?? 0),
                    'name' => (string) ($item['subject_name'] ?? $item['name'] ?? ''),
                ];
            },
            $items
        ), static fn (array $item): bool => $item['id'] > 0));

        $itemIds = array_column($items, 'id');

        // Who the Teacher column must pre-select: the teacher the Sections page
        // recorded for the item, falling back to whoever already teaches it.
        $model     = model(\App\Models\TeacherScheduleModel::class);
        $preferred = $model->assignedTeachers((int) $sectionId, $itemIds)
            + $model->scheduledTeachers((int) $sectionId, $itemIds);

        $teachers = $model->teacherChoicesPool();

        $adviserId = (int) ($section['adviser_id'] ?? 0);

        $result = [];
        foreach ($items as $item) {
            $assignedTeacherId = isset($preferred[$item['id']]) ? (int) $preferred[$item['id']] : null;

            $result[$item['id']] = [
                'subject_name'        => $item['name'],
                'assigned_teacher_id' => $assignedTeacherId,
                'teachers'            => $model->teacherChoices($assignedTeacherId, $adviserId, $teachers),
            ];
        }

        return $this->response->setJSON([
            'success'          => true,
            'subject_teachers' => $result,
            'domain_mode'      => $isNonNumerical,
        ]);
    }
    
    public function sectionSchedule($sectionId)
    {
        $db = \Config\Database::connect();
        
        // Non-numerical sections store a developmental domain in domain_id
        // (subject_id stays NULL), so both joins resolve the label by section
        // type instead of blindly joining subjects.
        $isNonNumerical = is_non_numerical_section((int) $sectionId);
        $nameExpr = $isNonNumerical
            ? 'sc.name'
            : 'sub.subject_name';
        $domainJoin = $isNonNumerical
            ? 'LEFT JOIN sned_categories sc ON sc.id = ' . $this->itemIdExpr()
            : 'LEFT JOIN subjects sub ON sub.id = ts.subject_id';

        $classSchedules = $db->query(
            "SELECT ts.*, {$nameExpr} AS subject_name, 
                   CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM teacher_schedules ts
            {$domainJoin}
            LEFT JOIN teachers t ON t.id = ts.teacher_id
            WHERE ts.section_id = ? AND ts.school_year = ?
            ORDER BY ts.day_of_week, ts.start_time",
            [$sectionId, get_current_school_year()]
        )->getResultArray();
        
        // Organize schedules by day and time
        $schedules = [];
        foreach ($classSchedules as $schedule) {
            $timeSlot = date('H:i', strtotime($schedule['start_time'])) . '-' . date('H:i', strtotime($schedule['end_time']));
            $schedules[strtolower($schedule['day_of_week'])][$timeSlot] = $schedule;
        }
        
        return $this->response->setJSON(['success' => true, 'schedules' => $schedules]);
    }
    
    /**
     * Validate and normalise one submitted block.
     *
     * Thin wrapper over the shared gate (schedule_validate_slot) so the section
     * page, the batch save and the teacher page accept and reject exactly the
     * same input. Historically each write path carried its own copy of these
     * checks and they drifted, which is how an invalid day could be stored as
     * '' on one page and rejected on another.
     *
     * @param  array<string, mixed> $input Raw request values.
     * @return array{ok: bool, error?: string, slot?: array<string, mixed>}
     */
    private function validatedSlot(array $input): array
    {
        return schedule_validate_slot($input);
    }

    /**
     * Reject a request with the conflict list the UI displays.
     *
     * Every conflict is returned, not just the first one: a save blocked by
     * three different collisions should say so in one reply instead of making
     * the admin fix one, resubmit, and discover the next.
     *
     * @param list<array{type?: string, message?: string}> $conflicts
     */
    private function conflictErrorResponse(array $conflicts)
    {
        $messages = [];

        foreach ($conflicts as $conflict) {
            $messages[] = (string) ($conflict['message'] ?? 'Schedule conflict.');
        }

        return $this->jsonError(implode('<br>', $messages), 409, $conflicts);
    }

    /**
     * The subject / developmental domain id a stored row holds.
     *
     * Non-numerical sections keep it in domain_id, but rows written before that
     * column existed hold the domain id in subject_id; numerical rows always
     * have domain_id NULL. Mirrors schedule_item_id_expr() in PHP.
     *
     * @param array<string, mixed> $row A teacher_schedules row.
     */
    private function rowItemId(array $row): int
    {
        if (schedule_domain_column_ready() && ! empty($row['domain_id'])) {
            return (int) $row['domain_id'];
        }

        return (int) ($row['subject_id'] ?? 0);
    }
    
    /**
     * SQL expression resolving a schedule row's item id. Non-numerical sections
     * tolerate rows written before domain_id existed (domain id sits in
     * subject_id); for numerical rows domain_id is NULL so this returns
     * subject_id unchanged.
     */
    private function itemIdExpr(string $alias = 'ts'): string
    {
        return schedule_domain_column_ready()
            ? "COALESCE({$alias}.domain_id, {$alias}.subject_id)"
            : "{$alias}.subject_id";
    }

    /**
     * Reject a request with a structured JSON payload.
     *
     * The schedule views read {success, error}. Returning a bare string, an
     * HTML error page or a 500 would be reported in the UI as the useless
     * "Unknown error", so every rejection goes through here. Conflict
     * rejections additionally carry the structured list in `conflicts`, which
     * is what the teacher page renders as a per-row report.
     *
     * @param list<array<string, mixed>> $conflicts
     */
    private function jsonError(string $message, int $status = 400, array $conflicts = [])
    {
        $payload = ['success' => false, 'error' => $message];

        if ($conflicts !== []) {
            $payload['conflicts'] = $conflicts;
        }

        return $this->response
            ->setStatusCode($status)
            ->setJSON($payload);
    }
}

