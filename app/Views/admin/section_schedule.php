<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style>
.time-editable {
    display: flex;
    justify-content: center;
    align-items: center;
}

.time-editable .time-text:hover {
    background: #e7f3ff;
    color: #0056b3;
}

.time-inputs {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    min-width: 200px;
}

.time-input {
    width: 85px;
    font-size: 0.8125rem;
    text-align: center;
    border: var(--hairline);
    border-radius: var(--radius-sm);
    padding: 4px 6px;
    background: #fff;
    box-shadow: var(--shadow-sm);
    transition: all 0.2s ease;
}

.time-input:focus {
    outline: none;
    border-color: #0056b3;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
    transform: scale(1.05);
}

.time-display {
    transition: all 0.2s ease;
    padding: 4px 8px;
    border-radius: 4px;
}

.time-display:hover {
    background: #e3f2fd;
    cursor: pointer;
}

.room-input-existing {
    border: 1px solid #ced4da;
    transition: all 0.2s ease;
}

.room-input-existing:hover {
    border-color: #007bff;
    box-shadow: 0 0 0 0.1rem rgba(0, 123, 255, 0.25);
}

.room-input-existing:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* A block the last save rejected. Kept subtle so a legitimate warning does not
   look like the page is broken. */
.schedule-conflict {
    border-color: #dc3545 !important;
    background: #fff5f5;
    box-shadow: 0 0 0 0.15rem rgba(220, 53, 69, 0.25);
}
</style>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-calendar-week me-2 text-primary"></i>Manage Schedule</h2>
    <h4 class="mb-2"><?= esc($section['section_name']) ?> - Grade <?= $section['grade_level'] ?>
        <?php if (!empty($isNonNumerical)): ?>
            <span class="badge bg-info ms-1 align-middle">Non-Numerical</span>
        <?php endif; ?>
    </h4>
    <?php if (!empty($isNonNumerical)): ?>
        <div class="alert alert-info py-2 px-3 small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            This section is graded <strong>non-numerically</strong>: schedule blocks are its <strong>developmental domains</strong> (chosen on the Sections page), not subjects. Progress in each block is assessed with symbols.
        </div>
    <?php endif; ?>
    <?php
    // Overlaps the validation would now refuse: legacy rows, or rows that
    // entered through another page. They are listed here because the grid
    // itself cannot show two blocks in one time slot at a glance, and because
    // such a pair now blocks every new block that would touch those times.
    $conflicts = $conflicts ?? [];
    ?>
    <p class="text-muted mb-4"><i class="bi bi-person-badge me-1"></i>Section Adviser: <?= !empty($section['adviser_name']) ? esc($section['adviser_name']) : 'No adviser assigned' ?></p>

    <?php if (!empty($conflicts)): ?>
        <div class="alert alert-danger py-3 px-3 mb-3">
            <h6 class="alert-heading mb-2">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <?= count($conflicts) ?> existing schedule conflict<?= count($conflicts) === 1 ? '' : 's' ?> in this section
            </h6>
            <ul class="mb-2 ps-3 small">
                <?php foreach ($conflicts as $conflict): ?>
                    <li><?= esc($conflict['message']) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="small mb-1">
                New blocks overlapping these times are rejected until the duplicates are removed. Conflicts that
                involve another school year are usually stale rows from a previous year &mdash; untick the block(s) you
                do not need and press <strong>Remove Selected</strong> on that day, or edit the times here to separate
                them.
            </div>
        </div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-calendar3-week me-2 text-muted"></i>Weekly Schedule</h5>
        <button class="btn btn-success" onclick="saveAllSchedules()">
            <i class="bi bi-check-circle me-2"></i>Save Schedules
        </button>
    </div>
    
    <ul class="nav nav-tabs" role="tablist">
        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $index => $day): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" id="<?= $day ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= $day ?>" type="button" role="tab" style="font-size: 1.5rem;">
                    <i class="bi bi-calendar-day me-1"></i> <?= $day ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
    
    <div class="tab-content mt-3">
        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $index => $day): ?>
            <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" id="<?= $day ?>" role="tabpanel">
                <div id="schedule-<?= $day ?>">
                    <div class="text-center py-4">
                        <div class="spinner-border"></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
const sectionId = <?= $section['id'] ?>;
const sectionName = '<?= esc($section['section_name']) ?>';
const gradeLevel = <?= $section['grade_level'] ?>;

/* Stored overlaps, keyed by schedule id, injected by Admin\Schedules::section().
   Server-side rendering cannot highlight them: the day grids are built by
   displaySchedules() from /admin/schedules/get/<section>/<day>, so the map is
   applied there instead. */
const sectionConflicts = <?= json_encode(
    array_reduce($conflicts ?? [], static function (array $carry, array $conflict): array {
        foreach ((array) ($conflict['ids'] ?? []) as $conflictId) {
            $carry[(int) $conflictId] = (string) ($conflict['message'] ?? 'Schedule conflict.');
        }

        return $carry;
    }, []),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

document.addEventListener('DOMContentLoaded', () => {
    ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'].forEach(day => {
        loadSchedules(day);
    });
});

function loadSchedules(day) {
    fetch(`<?= base_url('admin/schedules/get/') ?>${sectionId}/${day}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                displaySchedules(day, data.schedules);
            }
        });
}

function displaySchedules(day, schedules) {
    const container = document.getElementById(`schedule-${day}`);
    
    const existingTimeSlots = new Set();
    schedules.forEach(s => {
        const timeKey = s.start_time.substring(0,5) + '-' + s.end_time.substring(0,5);
        existingTimeSlots.add(timeKey);
    });
    
    const timeSlots = Array.from(existingTimeSlots).sort().map(timeKey => {
        const [start, end] = timeKey.split('-');
        return {start, end};
    });
    
    let html = '<table class="table table-bordered"><thead><tr><th width="15%"><i class="bi bi-clock me-1 text-muted"></i>Time</th><th><i class="bi bi-list-ul me-1 text-muted"></i>Schedule</th></tr></thead><tbody>';
    
    timeSlots.forEach((slot, index) => {
        const slotSchedules = schedules.filter(s => 
            s.start_time.substring(0,5) === slot.start && s.end_time.substring(0,5) === slot.end
        );
        
        html += `<tr data-day="${day}" data-time-index="${index}"><td class="fw-bold text-center">
            <div class="time-editable" data-day="${day}" data-index="${index}" onclick="editTime(this)"${slotSchedules.length > 0 ? ` data-schedule-ids="${slotSchedules.map(s => s.id).join(',')}"` : ''}>
                <span class="time-text" style="cursor: pointer; padding: 8px; border-radius: 4px; display: inline-block;">${slot.start}-${slot.end}</span>
                <div class="time-inputs" style="display: none; gap: 8px; align-items: center;">
                    <input type="time" class="form-control start-time" value="${slot.start}" style="width: 140px; min-width: 140px;">
                    <span style="font-weight: bold;">-</span>
                    <input type="time" class="form-control end-time" value="${slot.end}" style="width: 140px; min-width: 140px;">
                    <button type="button" class="btn btn-sm btn-success save-time-btn" style="margin-left: 8px;">
                        <i class="bi bi-check"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger cancel-time-btn" style="margin-left: 4px;" onclick="this.closest('.time-editable').querySelector('.time-text').style.display='inline-block'; this.closest('.time-inputs').style.display='none';">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        </td><td>`;
        
        if (slotSchedules.length > 0) {
            slotSchedules.forEach(s => {
                const conflictMessage = sectionConflicts[s.id] || '';
                const blockClass = conflictMessage ? 'bg-white border border-danger border-2' : 'bg-light border';

                html += `<div class="p-3 mb-2 ${blockClass} rounded"${conflictMessage ? ` title="${conflictMessage.replace(/"/g, '&quot;')}"` : ''}>
                    <div class="row">
                        <div class="col-md-1 d-flex align-items-center"><input type="checkbox" class="form-check-input schedule-checkbox" value="${s.id}" style="width: 20px; height: 20px; accent-color: #dc3545;"></div>
                        <div class="col-md-3"><label class="form-label"><i class="bi <?= !empty($isNonNumerical) ? 'bi-diagram-3' : 'bi-book' ?> me-1 text-muted"></i><?= !empty($isNonNumerical) ? 'Developmental Domain' : 'Subject' ?></label><select class="form-select subject-select-existing" data-schedule-id="${s.id}" data-current="${itemKey(s)}"><option value="${itemKey(s)}">${s.subject_name}</option></select></div>
                        <div class="col-md-3"><label class="form-label"><i class="bi bi-person me-1 text-muted"></i>Teacher</label><input type="text" class="form-control teacher-display-existing" readonly value="${s.teacher_name || 'Not Assigned'}" data-current="${s.teacher_id}" data-current-subject="${itemKey(s)}"></div>
                        <div class="col-md-4"><label class="form-label"><i class="bi bi-door-open me-1 text-muted"></i>Room <small class="text-muted">(editable)</small></label><input type="text" class="form-control room-input-existing" value="${s.room || ''}" data-schedule-id="${s.id}" placeholder="Enter room"></div>
                    </div>
                    ${conflictMessage ? `<div class="alert alert-danger py-1 px-2 mt-2 mb-0 small"><i class="bi bi-exclamation-triangle-fill me-1"></i>${conflictMessage}</div>` : ''}
                </div>`;
            });
        } else {
            html += `<div class="p-3 bg-white border rounded new-schedule" data-day="${day}" data-start="${slot.start}" data-end="${slot.end}">
                <div class="row">
                    <div class="col-md-3"><label class="form-label"><i class="bi <?= !empty($isNonNumerical) ? 'bi-diagram-3' : 'bi-book' ?> me-1 text-muted"></i><?= !empty($isNonNumerical) ? 'Developmental Domain' : 'Subject' ?></label><select class="form-select subject-select"><option value=""><?= !empty($isNonNumerical) ? 'Select Domain' : 'Select Subject' ?></option></select></div>
                    <div class="col-md-3"><label class="form-label"><i class="bi bi-person me-1 text-muted"></i>Teacher</label><input type="text" class="form-control teacher-display" readonly placeholder="<?= !empty($isNonNumerical) ? 'Select Domain First' : 'Select Subject First' ?>"><select class="form-select teacher-select d-none"></select></div>
                    <div class="col-md-3"><label class="form-label"><i class="bi bi-door-open me-1 text-muted"></i>Room</label><input type="text" class="form-control room-input" placeholder="Room" style="pointer-events: auto;"></div>
                    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-danger w-100" onclick="removeEmptyScheduleRow(this)"><i class="bi bi-trash me-2"></i>Remove</button></div>
                </div>
            </div>`;
        }
        
        html += '</td></tr>';
    });
    
    html += '</tbody></table>';
    html += `<div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary" onclick="addNewTimeSlot('${day}')"><i class="bi bi-plus-circle me-2"></i>Add Time Slot</button>
        <button class="btn btn-danger" onclick="deleteSelectedSchedules('${day}')"><i class="bi bi-trash me-2"></i>Remove Selected</button>
    </div>`;
    container.innerHTML = html;
    loadSubjectsAndTeachers(day);
    
    // Mark existing room inputs as modified for batch save
    setTimeout(() => {
        container.querySelectorAll('.room-input-existing').forEach(input => {
            input.addEventListener('input', function() {
                this.dataset.modified = 'true';
            });
        });
    }, 100);
    
    // Load subjects and teachers for existing schedules after data is loaded
    if (window.subjectTeachers) {
        loadSubjectsForExisting(container);
        addExistingEventListeners(container);
    } else {
        // Wait for subject teachers data to load
        setTimeout(() => {
            if (window.subjectTeachers) {
                loadSubjectsForExisting(container);
                addExistingEventListeners(container);
            }
        }, 500);
    }
}

/**
 * The assignee's bare name carried by a block's hidden teacher select.
 *
 * The visible Teacher field is a display, not a picker; the hidden select
 * holds exactly one option (the assignee, or nothing when unassigned).
 * Conflict messages name teachers plainly, so the comparison has to use the
 * name exactly as the assignment recorded it.
 */
function selectedTeacherName(teacherSelect) {
    const option = teacherSelect ? teacherSelect.selectedOptions?.[0] : null;

    // A placeholder choice ("Select Subject First", "No teacher assigned ...")
    // is not a teacher, and carries the empty string as its value.
    if (!option || option.value === '') return '';

    return String(option.dataset.name || option.textContent || '').trim();
}

/**
 * The item a stored block belongs to.
 *
 * A domain section keeps its item in domain_id, which getSchedules() resolves
 * into `item_id`; the subject_id fallback keeps a block renderable if an older
 * cached response arrives.
 */
function itemKey(schedule) {
    return schedule.item_id || schedule.subject_id || '';
}

/**
 * Show the teacher the Sections page assigned to a subject.
 *
 * The Teacher field is a fixed display, not a picker: who teaches a subject in a
 * section is decided once on the Sections page ("Assign Teachers") and every
 * block for that subject follows it. This used to be an editable dropdown fed by
 * a UNION whose order MySQL never promised, so it could show the section adviser
 * or an arbitrary active teacher instead of the assignee.
 *
 * `teacherField` is the read-only display input. A new block also carries a
 * hidden select in the same row; it holds the assigned teacher so
 * saveAllSchedules() can submit the id. The id is mirrored on the input as
 * data-teacher-id, which is what the subject-change handler persists.
 *
 * When nothing is assigned the field is left empty with an explanation and the
 * save is refused, because there is no teacher to write and teacher_id is NOT
 * NULL in the database.
 */
function setAssignedTeacher(teacherField, subjectId) {
    if (!teacherField) return;

    const entry = window.subjectTeachers ? window.subjectTeachers[subjectId] : null;
    const assignedId = entry && entry.assigned_teacher_id ? String(entry.assigned_teacher_id) : '';
    const teacher = assignedId === ''
        ? null
        : (entry.teachers || []).find(candidate => String(candidate.id) === assignedId) || null;

    teacherField.value = teacher ? teacher.name : '';
    teacherField.dataset.teacherId = teacher ? String(teacher.id) : '';
    teacherField.placeholder = teacher
        ? ''
        : (entry ? 'No teacher assigned - assign one on the Sections page' : 'Select Subject First');

    const hidden = teacherField.closest('.row') ? teacherField.closest('.row').querySelector('.teacher-select') : null;

    if (hidden) {
        hidden.innerHTML = '';
        hidden.value = '';

        if (teacher) {
            const option = new Option(teacher.name, teacher.id);
            option.dataset.name = teacher.name;
            hidden.appendChild(option);
        }
    }
}

function loadSubjectsForSelect(selectEl, day) {
    if (window.subjectTeachers) {
        Object.keys(window.subjectTeachers).forEach(subjectId => {
            const subject = window.subjectTeachers[subjectId];
            selectEl.innerHTML += `<option value="${subjectId}">${subject.subject_name}</option>`;
        });
        
        selectEl.addEventListener('change', function() {
            const row = this.closest('.row');
            setAssignedTeacher(row ? row.querySelector('.teacher-display') : null, this.value);
        });
    }
}

function addExistingEventListeners(container) {
    container.querySelectorAll('.subject-select-existing').forEach(select => {
        // displaySchedules() re-runs this after the subject/teacher payload
        // arrives, so the binding has to be idempotent: a second listener would
        // POST the same subject/teacher pair twice.
        if (select.dataset.changeBound === 'true') {
            return;
        }

        select.dataset.changeBound = 'true';

        select.addEventListener('change', function() {
            // Picking a subject also re-points the block at the teacher assigned
            // to that subject; updateTeacherForSubject() then persists the pair
            // through admin/schedules/update-both-fields.
            updateTeacherForSubject(this);
        });
    });
}

function loadSubjectsAndTeachers(day) {
    fetch(`<?= base_url('admin/schedules/subject-teachers/') ?>${sectionId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.subjectTeachers = data.subject_teachers;
                document.querySelectorAll(`#schedule-${day} .subject-select`).forEach(sel => {
                    loadSubjectsForSelect(sel, day);
                });
                
                // Load existing schedules dropdowns after data is available
                const container = document.getElementById(`schedule-${day}`);
                loadSubjectsForExisting(container);
                addExistingEventListeners(container);
            }
        });
}

async function removeEmptyScheduleRow(btn) {
    const row = btn.closest('tr');
    const scheduleDiv = btn.closest('.new-schedule');
    const hasData = scheduleDiv?.querySelector('.subject-select')?.value;
    
    if (hasData) {
        const proceed = await customConfirm(
            'This schedule has data. Are you sure you want to remove it?',
            'Remove Schedule?'
        );
        if (!proceed) {
            return;
        }
    }
    
    row.remove();
}

function addNewTimeSlot(day) {
    const table = document.querySelector(`#schedule-${day} table tbody`);
    const lastRow = table.querySelector('tr:last-child');
    const lastIndex = lastRow ? parseInt(lastRow.dataset.timeIndex) : -1;
    const newIndex = lastIndex + 1;
    
    const newRow = document.createElement('tr');
    newRow.dataset.day = day;
    newRow.dataset.timeIndex = newIndex;
    newRow.className = 'new-time-slot';
    newRow.innerHTML = `
        <td class="fw-bold text-center">
            <div class="time-editable" data-day="${day}" data-index="${newIndex}" onclick="editTime(this)">
                <span class="time-text" style="cursor: pointer; padding: 8px; border-radius: 4px; display: inline-block;">--:-- - --:--</span>
                <div class="time-inputs" style="display: none; gap: 8px; align-items: center;">
                    <input type="time" class="form-control start-time" value="" style="width: 140px; min-width: 140px;">
                    <span style="font-weight: bold;">-</span>
                    <input type="time" class="form-control end-time" value="" style="width: 140px; min-width: 140px;">
                    <button type="button" class="btn btn-sm btn-success save-time-btn" style="margin-left: 8px;">
                        <i class="bi bi-check"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger cancel-time-btn" style="margin-left: 4px;" onclick="this.closest('.time-editable').querySelector('.time-text').style.display='inline-block'; this.closest('.time-inputs').style.display='none';">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        </td>
        <td>
            <div class="p-3 bg-white border rounded new-schedule" data-day="${day}" data-start="" data-end="">
                <div class="row">
                    <div class="col-md-3"><label class="form-label"><i class="bi <?= !empty($isNonNumerical) ? 'bi-diagram-3' : 'bi-book' ?> me-1 text-muted"></i><?= !empty($isNonNumerical) ? 'Developmental Domain' : 'Subject' ?></label><select class="form-select subject-select"><option value=""><?= !empty($isNonNumerical) ? 'Select Domain' : 'Select Subject' ?></option></select></div>
                    <div class="col-md-3"><label class="form-label"><i class="bi bi-person me-1 text-muted"></i>Teacher</label><input type="text" class="form-control teacher-display" readonly placeholder="<?= !empty($isNonNumerical) ? 'Select Domain First' : 'Select Subject First' ?>"><select class="form-select teacher-select d-none"></select></div>
                    <div class="col-md-3"><label class="form-label"><i class="bi bi-door-open me-1 text-muted"></i>Room</label><input type="text" class="form-control room-input" placeholder="Room" style="pointer-events: auto;"></div>
                    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-danger w-100" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i> Remove</button></div>
                </div>
            </div>
        </td>
    `;
    
    table.appendChild(newRow);
    loadSubjectsForSelect(newRow.querySelector('.subject-select'), day);
}

/* ---------------------------------------------------------------------------
 * CSRF plumbing
 *
 * admin/schedules/* is covered by the global CSRF filter, so every POST from
 * this page must carry a valid token. These requests used to be sent without
 * one, so CodeIgniter answered 403 (SecurityException) before the controller
 * ever ran. That error response carries no success/error key, so the UI fell
 * back to the useless "Failed to save changes. Unknown error" message and
 * nothing was ever written to the database.
 *
 * The header name must match Config\Security::$headerName.
 * ------------------------------------------------------------------------- */
const SCHEDULE_CSRF_HEADERS = { 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' };

/**
 * POST to a schedule endpoint with the CSRF token and always resolve to a
 * {success, error} object, so a 403/500/HTML response can never be reported
 * as "Unknown error".
 */
function postSchedule(url, formData) {
    return fetch(url, {
        method: 'POST',
        body: formData,
        headers: SCHEDULE_CSRF_HEADERS
    }).then(response => response.text().then(text => {
        let data = null;
        try { data = JSON.parse(text); } catch (e) { data = null; }

        if (data && typeof data.success !== 'undefined') {
            if (!data.success && !data.error) {
                data.error = 'The server rejected the request.';
            }
            return data;
        }

        if (!response.ok) {
            return {
                success: false,
                error: 'The server blocked this request (HTTP ' + response.status +
                       '). Please reload the page and try again.'
            };
        }

        return { success: false, error: 'Unexpected response from the server.' };
    }));
}

/**
 * Flatten whatever a schedule endpoint returned into a list of messages.
 *
 * The controllers answer a failed save with `error` (one HTML-joined string)
 * and/or `conflicts` / `errors` (the structured list). Showing only
 * `errors[0]` hid the rest, so a click that failed on three blocks looked like
 * it failed for one reason.
 */
function collectMessages(data) {
    const messages = [];

    (data.conflicts || []).forEach(conflict => {
        if (!conflict) return;
        messages.push(typeof conflict === 'string' ? conflict : (conflict.message || ''));
    });

    (data.errors || []).forEach(entry => {
        if (!entry) return;
        messages.push(typeof entry === 'string' ? entry : (entry.message || ''));
    });

    if (messages.length === 0) {
        // `error` joins several conflicts with <br>, so split it back apart
        // instead of dumping the whole line into one bullet.
        (data.error || 'Unknown error').split('<br>').forEach(line => {
            if (line.trim() !== '') messages.push(line.trim());
        });
    }

    return messages.filter(message => message !== '');
}

/**
 * Paint the rows a rejected save referred to, so the failure is visible on the
 * block that caused it instead of only in the alert.
 */
function highlightConflicts(messages) {
    document.querySelectorAll('.schedule-conflict').forEach(el => el.classList.remove('schedule-conflict'));

    if (!messages || messages.length === 0) return;

    document.querySelectorAll('.new-schedule').forEach(slot => {
        const subject = slot.querySelector('.subject-select');
        const teacher = slot.querySelector('.teacher-select');
        const subjectText = subject?.selectedOptions?.[0]?.textContent?.trim() || '';
        const teacherText = selectedTeacherName(teacher);
        const room = slot.querySelector('.room-input')?.value?.trim() || '';

        const mentioned = messages.some(message =>
            (subjectText !== '' && message.includes(subjectText))
            || (teacherText !== '' && message.includes(teacherText))
            || (room !== '' && message.includes(room))
        );

        if (mentioned) slot.classList.add('schedule-conflict');
    });
}

/**
 * Drop the blocks the server just wrote from the DOM.
 *
 * Called before the grid is refreshed: a day that contains a rejected block is
 * deliberately not reloaded (so the input survives), which means the blocks
 * that *were* written have to be removed by hand or the next click would post
 * them a second time and hit their own conflict.
 *
 * The key format must match saveAllSchedules(): day:start-end:itemId.
 */
function removeSavedBlocks(savedKeySet) {
    if (savedKeySet.size === 0) return;

    document.querySelectorAll('.new-schedule').forEach(slot => {
        const day = slot.dataset.day;
        const time = collectTimeSlot(slot);
        const itemId = slot.querySelector('.subject-select')?.value;

        if (!day || !time || time.invalid || !itemId) return;

        if (savedKeySet.has(`${day}:${time.start}-${time.end}:${itemId}`)) {
            slot.closest('tr')?.remove();
        }
    });
}

/**
 * Read the start/end time a new block sits in.
 *
 * The editable slot label holds 'HH:MM-HH:MM' (or '--:-- - --:--' while the
 * time is still unset). Returning a structured result instead of a bare string
 * lets the caller tell "no time set yet" from "end before start", which the old
 * `timeText.split('-')` on a dash-less label silently turned into NaN.
 *
 * @returns {{start: string, end: string}|{invalid: string}|null}
 */
function collectTimeSlot(slot) {
    const row = slot.closest('tr');
    const timeText = row?.querySelector('.time-text')?.textContent || '';
    const parts = timeText.split('-').map(part => part.trim()).filter(part => part !== '');

    if (parts.length !== 2 || parts.some(part => !/^\d{1,2}:\d{2}$/.test(part))) {
        return null;
    }

    const [start, end] = parts;

    if (end <= start) {
        return { invalid: 'the end time must be later than the start time.' };
    }

    return { start, end };
}

function saveAllSchedules() {
    const newSchedules = [];
    const roomUpdates = [];
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    const problems = [];

    // Collect new schedules
    days.forEach(day => {
        document.querySelectorAll(`#schedule-${day} .new-schedule`).forEach(slot => {
            const subjectSelect = slot.querySelector('.subject-select');
            const teacherSelect = slot.querySelector('.teacher-select');
            const roomInput = slot.querySelector('.room-input');

            const hasSubject = !!subjectSelect?.value;
            const hasTeacher = !!teacherSelect?.value;

            if (!hasSubject && !hasTeacher) {
                return;
            }

            if (!hasSubject || !hasTeacher) {
                const subjectText = subjectSelect?.selectedOptions?.[0]?.textContent?.trim() || 'this subject';
                problems.push(hasSubject
                    ? `${day}: ${subjectText} has no teacher assigned yet - assign one on the Sections page first, or remove the empty block.`
                    : `${day}: pick a subject first, or remove the empty block.`);
                return;
            }

            const time = collectTimeSlot(slot);

            if (time === null) {
                problems.push(`${day}: set a valid start and end time before saving this block.`);
                return;
            }

            if (time.invalid) {
                problems.push(`${day}: ${time.invalid}`);
                return;
            }

            // `key` identifies this block inside the click, so the reply can
            // name the exact rows the server rejected. The labels are sent so a
            // conflict caused by another block in the SAME click can be
            // described by name instead of by id (those blocks are not in the
            // database yet, so the server cannot look them up).
            newSchedules.push({
                key: `${day}:${time.start}-${time.end}:${subjectSelect.value}`,
                section_id: sectionId,
                subject_id: subjectSelect.value,
                teacher_id: teacherSelect.value,
                day_of_week: day,
                start_time: time.start + ':00',
                end_time: time.end + ':00',
                room: roomInput?.value || '',
                item_name: subjectSelect.selectedOptions?.[0]?.textContent?.trim() || '',
                section_name: sectionName,
                teacher_name: selectedTeacherName(teacherSelect)
            });
        });
        
        // Collect modified room inputs
        document.querySelectorAll(`#schedule-${day} .room-input-existing[data-modified="true"]`).forEach(input => {
            roomUpdates.push({
                schedule_id: input.dataset.scheduleId,
                room: input.value
            });
        });
    });
    
    if (problems.length > 0) {
        showAlert(problems.join('<br>'), 'error');
        return;
    }

    if (newSchedules.length === 0 && roomUpdates.length === 0) {
        showAlert('No changes to save.', 'error');
        return;
    }
    
    let savedCount = 0;
    let errors = [];
    // Keys of the blocks the server actually wrote, returned by the batch
    // endpoint. Used to keep rejected blocks on screen without leaving the
    // saved ones behind to be written twice on the next click.
    let savedKeys = [];
    
    const allPromises = [];
    
    // Save new schedules - all in ONE request. Posting them one by one (or in
    // parallel) made every block blind to the others, so two blocks added in
    // the same click could both land on the same section slot. The batch
    // endpoint validates the whole set against itself and against the stored
    // timetable, then writes it in a single transaction.
    if (newSchedules.length > 0) {
        const formData = new FormData();
        formData.append('section_id', String(sectionId));

        newSchedules.forEach((schedule, index) => {
            Object.keys(schedule).forEach(field => {
                formData.append(`schedules[${index}][${field}]`, schedule[field]);
            });
        });

        allPromises.push(
            postSchedule('<?= base_url('admin/schedules/save-batch') ?>', formData)
                .then(data => {
                    if (data.success) {
                        savedCount += Number(data.saved) || 0;
                        savedKeys = savedKeys.concat(data.saved_keys || []);
                        (data.errors || []).forEach(entry => {
                            errors.push(entry.message || 'This block was rejected.');
                        });
                    } else {
                        errors = errors.concat(collectMessages(data));
                    }
                })
        );
    }
    
    // Save room updates
    roomUpdates.forEach(update => {
        const formData = new FormData();
        formData.append('schedule_id', update.schedule_id);
        formData.append('room', update.room);
        
        allPromises.push(
            postSchedule('<?= base_url('admin/schedules/update-room') ?>', formData)
                .then(data => {
                    if (data.success) savedCount++;
                    else errors = errors.concat(collectMessages(data));
                })
        );
    });
    
    Promise.all(allPromises)
    .then(() => {
        const savedKeySet = new Set(savedKeys);
        // A block with a key the server did not echo back was rejected. Its day
        // is left untouched so the admin does not lose what they typed: the
        // rejected block stays (flagged), the accepted ones on that day are
        // dropped from the DOM instead.
        const rejectedDays = newSchedules
            .filter(schedule => !savedKeySet.has(schedule.key))
            .map(schedule => schedule.day_of_week);

        removeSavedBlocks(savedKeySet);
        days.filter(day => !rejectedDays.includes(day)).forEach(day => loadSchedules(day));

        if (savedCount > 0 || errors.length > 0) {
            const msg = errors.length > 0
                ? (savedCount > 0 ? `Saved ${savedCount} item(s).<br>` : '')
                  + `${errors.length} block(s) were rejected and left on screen to fix:<br><small>`
                  + errors.join('<br>') + '</small>'
                : `Successfully saved ${savedCount} item(s)!`;
            showAlert(msg, errors.length > 0 ? 'error' : 'success');
        }

        highlightConflicts(errors);
    })
    .catch(error => {
        showAlert('Network error: ' + error.message, 'error');
    });
}

function deleteSelectedSchedules(day) {
    const selected = Array.from(document.querySelectorAll(`#schedule-${day} .schedule-checkbox:checked`)).map(cb => cb.value);
    
    if (selected.length === 0) {
        showAlert('Please select schedules to remove', 'error');
        return;
    }
    
    showConfirmModal(`Delete ${selected.length} selected schedule(s)?`, () => {
        let completed = 0;
        let failed = 0;
        
        selected.forEach(id => {
            const finish = () => {
                if (completed + failed !== selected.length) return;
                loadSchedules(day);
                if (completed > 0) {
                    showAlert(`${completed} schedule(s) deleted successfully!`, 'success');
                }
                if (failed > 0) {
                    showAlert(`${failed} schedule(s) failed to delete`, 'error');
                }
            };

            postSchedule(`<?= base_url('admin/schedules/delete/') ?>${id}`, new FormData())
                .then(data => {
                    if (data.success) completed++;
                    else failed++;
                    finish();
                })
                .catch(() => {
                    failed++;
                    finish();
                });
        });
    });
}

function showConfirmModal(message, onConfirm) {
    const modalHtml = `
    <div class="modal fade" id="scheduleConfirmModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #6c757d; border-color: #6c757d;">Cancel</button>
                    <button type="button" class="btn btn-danger" id="scheduleConfirmBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>`;
    
    const existing = document.getElementById('scheduleConfirmModal');
    if (existing) existing.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modalEl = document.getElementById('scheduleConfirmModal');
    const modal = new bootstrap.Modal(modalEl);
    
    document.getElementById('scheduleConfirmBtn').addEventListener('click', function() {
        modal.hide();
        setTimeout(() => onConfirm(), 300);
    }, { once: true });
    
    modal.show();
}

function loadSubjectsForExisting(container) {
    if (window.subjectTeachers) {
        container.querySelectorAll('.subject-select-existing').forEach(select => {
            const currentValue = select.dataset.current;
            select.innerHTML = '';
            Object.keys(window.subjectTeachers).forEach(subjectId => {
                const subject = window.subjectTeachers[subjectId];
                const selected = subjectId === currentValue ? 'selected' : '';
                select.innerHTML += `<option value="${subjectId}" ${selected}>${subject.subject_name}</option>`;
            });
        });
        
        // Show the teacher assigned to each block's own subject. The name the
        // server rendered (the block's stored teacher_id) is only a placeholder
        // until this payload arrives: the assignment is what the field must
        // display, and where the two disagree it is the block that is out of
        // date, not the assignment.
        container.querySelectorAll('.teacher-display-existing').forEach(field => {
            setAssignedTeacher(field, field.dataset.currentSubject);
        });
    }
}

function updateTeacherForSubject(subjectSelect) {
    const scheduleId = subjectSelect.dataset.scheduleId;
    const subjectId = subjectSelect.value;
    const row = subjectSelect.closest('.row');
    const teacherField = row ? row.querySelector('.teacher-display-existing') : null;

    const entry = window.subjectTeachers ? window.subjectTeachers[subjectId] : null;
    const assignedId = entry && entry.assigned_teacher_id ? String(entry.assigned_teacher_id) : '';
    const previousSubject = subjectSelect.dataset.current || '';

    if (assignedId === '') {
        // Fixed field: there is no teacher to write and teacher_id is NOT NULL,
        // so leave the block untouched instead of replacing it with a
        // teacherless row.
        if (teacherField) setAssignedTeacher(teacherField, subjectId);
        subjectSelect.value = previousSubject;
        showAlert('This subject has no teacher assigned in this section yet. Assign one on the Sections page first.', 'error');
        return;
    }

    if (teacherField) setAssignedTeacher(teacherField, subjectId);

    // The pair is only committed once the server accepts it; if it rejects
    // (teacher busy, subject already on that day, ...) the block is put back so
    // the grid never shows a schedule the database refuses.
    updateExistingScheduleFields(
        scheduleId,
        subjectId,
        assignedId,
        subjectSelect,
        () => {
            subjectSelect.value = previousSubject;
            if (teacherField) setAssignedTeacher(teacherField, previousSubject);
        }
    );
}

function updateExistingScheduleFields(scheduleId, subjectId, teacherId, selectElement, revert) {
    const formData = new FormData();
    formData.append('schedule_id', scheduleId);
    formData.append('subject_id', subjectId);
    formData.append('teacher_id', teacherId);

    selectElement.style.backgroundColor = '#fff3cd';
    selectElement.disabled = true;

    postSchedule('<?= base_url('admin/schedules/update-both-fields') ?>', formData)
    .then(data => {
        if (data.success) {
            selectElement.dataset.current = subjectId;
            selectElement.style.backgroundColor = '#d1edff';
            setTimeout(() => {
                selectElement.style.backgroundColor = '';
            }, 1000);
        } else {
            selectElement.style.backgroundColor = '#f8d7da';
            if (typeof revert === 'function') {
                revert();
            }
            // A blank alert is worse than a plain one: every rejection reason
            // the server sends is listed.
            showAlert('<strong>This change was not saved.</strong><br>' + collectMessages(data).join('<br>'), 'error');
            setTimeout(() => {
                selectElement.style.backgroundColor = '';
            }, 2000);
        }
    })
    .finally(() => {
        selectElement.disabled = false;
    });
}

function updateExistingRoom(input) {
    const scheduleId = input.dataset.scheduleId;
    const room = input.value;
    const originalValue = input.defaultValue;
    
    if (room === originalValue) {
        return; // No change
    }
    
    const formData = new FormData();
    formData.append('schedule_id', scheduleId);
    formData.append('room', room);
    
    // Visual feedback
    input.style.backgroundColor = '#fff3cd';
    input.disabled = true;
    
    postSchedule('<?= base_url('admin/schedules/update-room') ?>', formData)
    .then(data => {
        if (data.success) {
            input.style.backgroundColor = '#d1edff';
            input.defaultValue = room; // Update the default value
            
            // Show brief success message
            const tempMsg = document.createElement('small');
            tempMsg.className = 'text-success';
            tempMsg.textContent = '✓ Saved';
            tempMsg.style.position = 'absolute';
            tempMsg.style.right = '5px';
            tempMsg.style.top = '50%';
            tempMsg.style.transform = 'translateY(-50%)';
            input.parentElement.style.position = 'relative';
            input.parentElement.appendChild(tempMsg);
            
            setTimeout(() => {
                input.style.backgroundColor = '';
                if (tempMsg.parentElement) tempMsg.remove();
            }, 1500);
        } else {
            input.style.backgroundColor = '#f8d7da';
            input.value = originalValue; // Revert to original value
            showAlert('<strong>Room not saved.</strong><br>' + collectMessages(data).join('<br>'), 'error');
            setTimeout(() => {
                input.style.backgroundColor = '';
            }, 2000);
        }
    })
    .catch(error => {
        input.style.backgroundColor = '#f8d7da';
        input.value = originalValue;
        setTimeout(() => {
            input.style.backgroundColor = '';
        }, 2000);
    })
    .finally(() => {
        input.disabled = false;
    });
}

function showAlert(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    const iconClass = type === 'success' ? 'bi-check-circle' : type === 'error' ? 'bi-x-circle' : 'bi-info-circle';
    
    const modalHtml = `
    <div class="modal fade" id="scheduleAlertModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <i class="bi ${iconClass} fs-1 text-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'}"></i>
                    <div class="mt-3 mb-0">${message}</div>
                    <button type="button" class="btn btn-primary mt-3" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>`;
    
    const existing = document.getElementById('scheduleAlertModal');
    if (existing) existing.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('scheduleAlertModal'));
    modal.show();
}

function editTime(element) {
    const timeText = element.querySelector('.time-text');
    const timeInputs = element.querySelector('.time-inputs');
    const startInput = timeInputs.querySelector('.start-time');
    const endInput = timeInputs.querySelector('.end-time');
    const day = element.dataset.day;
    const index = parseInt(element.dataset.index);
    const row = element.closest('tr');
    
    timeText.style.display = 'none';
    timeInputs.style.display = 'flex';
    
    let isEditing = true;
    let isSaving = false;
    
    const saveTime = async () => {
        if (!isEditing || isSaving) return;
        
        isSaving = true;
        
        const newStart = startInput.value;
        const newEnd = endInput.value;
        
        if (!newStart || !newEnd) {
            alert('Enter both a start and an end time.');
            isSaving = false;
            return;
        }

        if (newEnd <= newStart) {
            alert('The end time must be later than the start time.');
            isSaving = false;
            return;
        }

        const newTimeSlot = newStart + '-' + newEnd;

        // Only saved blocks (subject-select-existing) live in the database; a
        // brand new block is written by "Save Schedules" and just needs the new
        // label. The old version rewrote the label for BOTH, so moving a saved
        // block left the grid and the database disagreeing until a reload.
        const savedBlocks = Array.from(row.querySelectorAll('td:last-child .subject-select-existing'));

        if (savedBlocks.length === 0) {
            // Nothing is stored yet, so there is no old slot to read or move: a
            // row added with "Add Time Slot" still shows the '--:-- - --:--'
            // placeholder here. Writing the label is the whole job. Reading the
            // old slot BEFORE this check made the branch unreachable, so every
            // new row died on "This time slot could not be read" and the only
            // advice was a reload, which threw the new row away.
            isEditing = false;
            timeText.textContent = newTimeSlot;
            timeText.style.display = 'inline-block';
            timeInputs.style.display = 'none';
            return;
        }

        // Stored blocks must MOVE server-side, so the slot they currently
        // occupy has to come from the label. It is only unreadable when the
        // markup changed under us; a placeholder row never reaches this point.
        const oldSlot = collectTimeSlot(row.querySelector('.new-schedule') || row);

        if (oldSlot === null) {
            alert('This time slot could not be read. Reload the page and try again.');
            isSaving = false;
            return;
        }

        if (oldSlot.invalid) {
            // The editor stays open so the two inputs can be corrected.
            alert('This time slot cannot be moved: ' + oldSlot.invalid);
            isSaving = false;
            return;
        }

        const proceed = await customConfirm(
            'Move this whole time slot from ' + oldSlot.start + '-' + oldSlot.end + ' to ' + newTimeSlot + '?',
            'Change Time Slot?'
        );

        if (!proceed) {
            isSaving = false;
            timeText.style.display = 'inline-block';
            timeInputs.style.display = 'none';
            return;
        }

        const formData = new FormData();
        formData.append('section_id', sectionId);
        formData.append('day_of_week', day);
        formData.append('old_start_time', oldSlot.start);
        formData.append('old_end_time', oldSlot.end);
        formData.append('start_time', newStart);
        formData.append('end_time', newEnd);

        const data = await postSchedule('<?= base_url('admin/schedules/update-time') ?>', formData);

        if (data.success) {
            isEditing = false;
            timeText.textContent = newTimeSlot;
            timeText.style.display = 'inline-block';
            timeInputs.style.display = 'none';
            loadSchedules(day);
            return;
        }

        // Rejected: the slot stays where it was. Reloading re-reads the stored
        // times, so the grid never shows a move the server refused.
        isSaving = false;
        showAlert('<strong>This time slot was not moved.</strong><br>' + collectMessages(data).join('<br>'), 'error');
        timeText.style.display = 'inline-block';
        timeInputs.style.display = 'none';
        loadSchedules(day);
    };
    
    const cancel = () => {
        isEditing = false;
        timeText.style.display = 'inline-block';
        timeInputs.style.display = 'none';
    };
    
    timeInputs.addEventListener('click', (e) => e.stopPropagation());
    startInput.addEventListener('click', (e) => e.stopPropagation());
    endInput.addEventListener('click', (e) => e.stopPropagation());
    
    startInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            endInput.focus();
        } else if (e.key === 'Escape') {
            cancel();
        }
    });
    
    endInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveTime();
        } else if (e.key === 'Escape') {
            cancel();
        }
    });
    
    const saveBtn = timeInputs.querySelector('.save-time-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            saveTime();
        });
    }
    
    const cancelBtn = timeInputs.querySelector('.cancel-time-btn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            cancel();
        });
    }
    
    setTimeout(() => startInput.focus(), 50);
}
</script>

<?= $this->endSection() ?>
