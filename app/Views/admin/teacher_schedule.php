<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Manage Schedule</h1>
        <h2 class="teacher-name"><?= esc($teacher['first_name'] . ' ' . $teacher['last_name']) ?></h2>
    </div>
    <div class="d-flex gap-2">
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-sm text-muted border-0 active" id="gridViewBtn" onclick="switchView('grid')" style="background: none; color: #495057 !important; opacity: 1;">
                <i class="bi bi-grid-3x3-gap"></i>
            </button>
            <button type="button" class="btn btn-sm text-muted border-0" id="listViewBtn" onclick="switchView('list')" style="background: none; color: #495057 !important; opacity: 0.5;">
                <i class="bi bi-list-ul"></i>
            </button>
        </div>
        <a href="<?= base_url('admin/teachers') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Teachers
        </a>
    </div>
</div>

<?php
// Overlaps the save validation would now refuse: legacy rows, or rows written
// through another page. They are listed here because a teacher cannot be in
// two places at once, and because such a pair now blocks every new block that
// would touch those times.
$conflicts = $conflicts ?? [];
?>
<?php if (!empty($conflicts)): ?>
    <div class="alert alert-danger py-3 px-3 mb-4">
        <h6 class="alert-heading mb-2">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <?= count($conflicts) ?> existing conflict<?= count($conflicts) === 1 ? '' : 's' ?> in this teacher's schedule
        </h6>
        <ul class="mb-2 ps-3 small">
            <?php foreach ($conflicts as $conflict): ?>
                <li><?= esc($conflict['message']) ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="small mb-0">
            Saving is refused while a block would collide with one of these, so fix them on the section
            pages first. Conflicts that mention another school year are usually stale rows from a previous
            year &mdash; open that section's schedule and press <strong>Remove Selected</strong> on the
            block you no longer need.
        </div>
    </div>
<?php endif; ?>

<style>
.teacher-name {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2563eb;
    margin: 8px 0;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

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

.time-inputs {
    background: #f8f9fa;
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    min-width: 200px;
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

.schedule-cell {
    min-height: 80px;
    padding: 8px;
}

.form-select-sm, .form-control-sm {
    font-size: 16px;
    padding: 8px 12px;
}

.schedule-cell:has(.section-select option:checked:not([value=""])) {
    background-color: #e8f5e9;
}

tr:has(.schedule-cell .section-select option:checked:not([value=""])) {
    background-color: #f1f8f4;
}
</style>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Weekly Schedule</h5>
    </div>
    <div class="card-body">
        <?php 
        // Get all unique time slots from existing schedules
        $existingTimeSlots = [];
        foreach ($schedules as $schedule) {
            $timeKey = date('H:i', strtotime($schedule['start_time'])) . '-' . date('H:i', strtotime($schedule['end_time']));
            $existingTimeSlots[$timeKey] = true;
        }
        
        // Default time slots - continuous schedule
        $defaultTimeSlots = [
            '07:00-08:00', '08:00-09:00', '09:00-10:00', '10:00-11:00',
            '11:00-12:00', '12:00-13:00', '13:00-14:00', '14:00-15:00',
            '15:00-16:00', '16:00-17:00'
        ];
        
        // Merge existing time slots with defaults and sort
        $allTimeSlots = array_unique(array_merge($defaultTimeSlots, array_keys($existingTimeSlots)));
        usort($allTimeSlots, function($a, $b) {
            return strcmp($a, $b);
        });
        $timeSlots = $allTimeSlots;
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        
        // Organize existing schedules by day and time
        $scheduleGrid = [];
        foreach ($schedules as $schedule) {
            $timeKey = date('H:i', strtotime($schedule['start_time'])) . '-' . date('H:i', strtotime($schedule['end_time']));
            $scheduleGrid[$schedule['day_of_week']][$timeKey] = $schedule;
        }
        ?>
        
        <!-- List View -->
        <div id="listView" style="display: none;">
            <form id="scheduleFormList">
                <?php foreach ($timeSlots as $index => $timeSlot): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?= $timeSlot ?></h6>
                            <div class="time-slot-controls">
                                <button type="button" class="time-edit-btn" onclick="adjustTimeList(<?= $index ?>, 15)" title="+15 minutes">
                                    <i class="bi bi-chevron-up"></i>
                                </button>
                                <button type="button" class="time-edit-btn" onclick="adjustTimeList(<?= $index ?>, -15)" title="-15 minutes">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day): ?>
                            <div class="col-md">
                                <h6 class="text-muted mb-2"><?= $day ?></h6>
                                <?php 
                                $existingSchedule = $scheduleGrid[$day][$timeSlot] ?? null;
                                $startTime = explode('-', $timeSlot)[0];
                                $endTime = explode('-', $timeSlot)[1];
                                ?>
                                <div class="schedule-cell-list" data-day="<?= $day ?>" data-start="<?= $startTime ?>" data-end="<?= $endTime ?>">
                                    <label class="form-label small">Section</label>
                                    <select class="form-select form-select-sm mb-2 section-select" name="section" 
                                            onchange="updateSubjectOptions(this, '<?= $day ?>', '<?= $timeSlot ?>')">
                                        <option value="">Select Section</option>
                                        <?php 
                                        $displayedSections = [];
                                        foreach ($sections as $section): 
                                            $sectionName = $section['section_name'];
                                            $sectionName = preg_replace('/^Grade \d+ - /', '', $sectionName);
                                            $displayText = $sectionName . ' (' . grade_level_label((int) $section['grade_level']) . ')';
                                            if (in_array($displayText, $displayedSections)) continue;
                                            $displayedSections[] = $displayText;
                                        ?>
                                            <option value="<?= $section['id'] ?>" data-grade="<?= $section['grade_level'] ?>" 
                                                    <?= $existingSchedule && $existingSchedule['section_id'] == $section['id'] ? 'selected' : '' ?>>
                                                <?= esc($displayText) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <label class="form-label small">Subject</label>
                                    <select class="form-select form-select-sm mb-2 subject-select" name="subject" 
                                            data-day="<?= $day ?>" data-time="<?= $timeSlot ?>" 
                                            onchange="refreshSubjectDropdownsForDay('<?= $day ?>')" disabled>
                                        <option value="">Select Section First</option>
                                    </select>
                                    
                                    <label class="form-label small">Room</label>
                                    <input type="text" class="form-control form-control-sm room-input" name="room" 
                                           placeholder="Room" value="<?= esc($existingSchedule['room'] ?? '') ?>">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle me-2"></i>Save Schedule
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Grid View -->
        <div id="gridView">
        <form id="scheduleForm">
            <?php 
            $dayColors = [
                'Monday' => '#F4D03F',
                'Tuesday' => '#34495E',
                'Wednesday' => '#F4D03F',
                'Thursday' => '#34495E',
                'Friday' => '#F4D03F'
            ];
            $dayTextColors = [
                'Monday' => '#1a1a1a',
                'Tuesday' => '#ffffff',
                'Wednesday' => '#1a1a1a',
                'Thursday' => '#ffffff',
                'Friday' => '#1a1a1a'
            ];
            ?>
            <?php foreach ($days as $dayIndex => $day): ?>
            <div class="card mb-3" style="border: var(--hairline); box-shadow: var(--shadow-md);">
                <div class="card-header" style="cursor: pointer; background: var(--surface-tint); border-bottom: var(--hairline);" onclick="toggleDay('<?= $day ?>')">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0" style="color: var(--color-heading); font-weight: 700;"><i class="bi bi-calendar-day me-2"></i><?= $day ?></h5>
                        <i class="bi bi-chevron-down" style="color: var(--color-text-muted);" id="icon-<?= $day ?>"></i>
                    </div>
                </div>
                <div class="card-body" id="day-<?= $day ?>" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="20%" style="font-size: 16px;">Time</th>
                                    <th width="80%" style="font-size: 16px;">Schedule</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timeSlots as $index => $timeSlot): ?>
                                <tr data-day="<?= $day ?>" data-time-index="<?= $index ?>">
                                    <td class="fw-bold text-center" style="font-size: 16px;">
                                        <div class="time-editable" data-day="<?= $day ?>" data-index="<?= $index ?>" onclick="editTime(this)">
                                            <span class="time-text" style="cursor: pointer; padding: 8px; border-radius: 4px; display: inline-block;"><?= $timeSlot ?></span>
                                            <div class="time-inputs" style="display: none; gap: 8px; align-items: center;">
                                                <input type="time" class="form-control start-time" value="<?= explode('-', $timeSlot)[0] ?>" style="width: 140px; min-width: 140px;">
                                                <span style="font-weight: bold;">-</span>
                                                <input type="time" class="form-control end-time" value="<?= explode('-', $timeSlot)[1] ?>" style="width: 140px; min-width: 140px;">
                                                <button type="button" class="btn btn-sm btn-success save-time-btn" style="margin-left: 8px;">
                                                    <i class="bi bi-check"></i> Save
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $existingSchedule = $scheduleGrid[$day][$timeSlot] ?? null;
                                        $startTime = explode('-', $timeSlot)[0];
                                        $endTime = explode('-', $timeSlot)[1];
                                        ?>
                                        <div class="schedule-cell" data-day="<?= $day ?>" data-start="<?= $startTime ?>" data-end="<?= $endTime ?>">
                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <label class="form-label" style="font-size: 16px; font-weight: 700;">Section</label>
                                                    <select class="form-select form-select-sm section-select" name="section" 
                                                            onchange="updateSubjectOptions(this, '<?= $day ?>', '<?= $timeSlot ?>'); checkForSuggestions(this)">
                                                        <option value="">Select Section</option>
                                                        <?php 
                                                        $displayedSections = [];
                                                        foreach ($sections as $section): 
                                                            $sectionName = $section['section_name'];
                                                            $sectionName = preg_replace('/^Grade \d+ - /', '', $sectionName);
                                                            $displayText = $sectionName . ' (' . grade_level_label((int) $section['grade_level']) . ')';
                                                            
                                                            if (in_array($displayText, $displayedSections)) continue;
                                                            $displayedSections[] = $displayText;
                                                        ?>
                                                            <option value="<?= $section['id'] ?>" data-grade="<?= $section['grade_level'] ?>" 
                                                                    <?= $existingSchedule && $existingSchedule['section_id'] == $section['id'] ? 'selected' : '' ?>>
                                                                <?= esc($displayText) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label" style="font-size: 16px; font-weight: 700;">Subject</label>
                                                    <select class="form-select form-select-sm subject-select" name="subject" 
                                                            data-day="<?= $day ?>" data-time="<?= $timeSlot ?>" 
                                                            onchange="showSmartSuggestions(this); refreshSubjectDropdownsForDay('<?= $day ?>')" disabled>
                                                        <option value="">Select Section First</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label" style="font-size: 16px; font-weight: 700;">Room</label>
                                                    <input type="text" class="form-control form-control-sm room-input" name="room" 
                                                           placeholder="Room" value="<?= esc($existingSchedule['room'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-circle me-2"></i>Save Schedule
                </button>
            </div>
        </form>
        </div>
    </div>
</div>

<script>
const defaultTimeSlots = <?= json_encode($timeSlots) ?>;
let timeSlotsPerDay = {
    'Monday': [...defaultTimeSlots],
    'Tuesday': [...defaultTimeSlots],
    'Wednesday': [...defaultTimeSlots],
    'Thursday': [...defaultTimeSlots],
    'Friday': [...defaultTimeSlots]
};
let hasExistingSchedules = <?= json_encode(!empty($schedules)) ?>;
const subjectsByGrade = <?= json_encode($subjectsByGrade ?? []) ?>;
const assignedCombinations = <?= json_encode($assignedCombinations ?? []) ?>;
const allSubjects = <?= json_encode($subjects ?? []) ?>;
const existingSchedules = <?= json_encode($schedules ?? []) ?>;
console.log('Existing schedules:', existingSchedules);
console.log('Assigned combinations:', assignedCombinations);

function editTime(element) {
    const timeText = element.querySelector('.time-text');
    const timeInputs = element.querySelector('.time-inputs');
    const startInput = timeInputs.querySelector('.start-time');
    const endInput = timeInputs.querySelector('.end-time');
    const day = element.dataset.day;
    const index = parseInt(element.dataset.index);
    const row = element.closest('tr');
    const cell = row.querySelector('.schedule-cell');
    
    // Initialize inputs with current cell data, not PHP template values
    startInput.value = cell.dataset.start;
    endInput.value = cell.dataset.end;
    
    timeText.style.display = 'none';
    timeInputs.style.display = 'flex';
    
    let isEditing = true;
    let isSaving = false;
    
    const saveTime = async () => {
        if (!isEditing || isSaving) return;
        
        isSaving = true;
        
        const newStart = startInput.value;
        const newEnd = endInput.value;
        
        if (!newStart || !newEnd || newEnd <= newStart) {
            alert('Invalid time range');
            isSaving = false;
            return;
        }
        
        // Check if this time slot has existing schedule data
        const oldTimeSlot = timeText.textContent;
        const hasSchedule = cell.querySelector('.section-select').value || cell.querySelector('.subject-select').value;
        
        if (hasSchedule && oldTimeSlot !== (newStart + '-' + newEnd)) {
            const proceed = await customConfirm(
                'Changing this time slot will clear the schedule data for this time.',
                'Change Time Slot?'
            );
            if (!proceed) {
                isSaving = false;
                return;
            }
            // Clear the schedule data for this cell
            cell.querySelector('.section-select').value = '';
            const subjectSelect = cell.querySelector('.subject-select');
            subjectSelect.innerHTML = '<option value="">Select Section First</option>';
            subjectSelect.disabled = true;
            cell.querySelector('.room-input').value = '';
        }
        
        isEditing = false;
        
        console.log('Saving time:', newStart, '-', newEnd, 'for day:', day, 'index:', index);
        
        if (newStart && newEnd) {
            const newTimeSlot = newStart + '-' + newEnd;
            const dayRows = document.querySelectorAll(`tr[data-day="${day}"]`);
            
            // Calculate slot duration
            const newStartDate = new Date('1970-01-01 ' + newStart);
            const newEndDate = new Date('1970-01-01 ' + newEnd);
            const slotDuration = (newEndDate - newStartDate) / 60000;
            
            // Update current slot ONCE
            cell.dataset.start = newStart;
            cell.dataset.end = newEnd;
            timeText.textContent = newTimeSlot;
            timeSlotsPerDay[day][index] = newTimeSlot;
            
            console.log('Updated cell data:', cell.dataset.start, cell.dataset.end);
            
            // Auto-adjust all slots to be continuous
            let currentTime = new Date('1970-01-01 ' + newStart);
            
            // Adjust slots above (backwards) only if not the first slot
            for (let i = parseInt(index) - 1; i >= 0; i--) {
                const row = dayRows[i];
                const otherTimeEditable = row.querySelector('.time-editable');
                const otherTimeText = otherTimeEditable.querySelector('.time-text');
                const otherCell = row.querySelector('.schedule-cell');
                
                const endTime = new Date(currentTime);
                currentTime.setMinutes(currentTime.getMinutes() - slotDuration);
                
                const otherNewStart = currentTime.toTimeString().substring(0, 5);
                const otherNewEnd = endTime.toTimeString().substring(0, 5);
                
                otherCell.dataset.start = otherNewStart;
                otherCell.dataset.end = otherNewEnd;
                otherTimeText.textContent = otherNewStart + '-' + otherNewEnd;
                timeSlotsPerDay[day][i] = otherNewStart + '-' + otherNewEnd;
            }
            
            // Reset for slots below
            currentTime = new Date('1970-01-01 ' + newEnd);
            
            // Adjust slots below (forwards)
            for (let i = parseInt(index) + 1; i < dayRows.length; i++) {
                const row = dayRows[i];
                const otherTimeEditable = row.querySelector('.time-editable');
                const otherTimeText = otherTimeEditable.querySelector('.time-text');
                const otherCell = row.querySelector('.schedule-cell');
                
                const startTime = new Date(currentTime);
                currentTime.setMinutes(currentTime.getMinutes() + slotDuration);
                
                const otherNewStart = startTime.toTimeString().substring(0, 5);
                const otherNewEnd = currentTime.toTimeString().substring(0, 5);
                
                otherCell.dataset.start = otherNewStart;
                otherCell.dataset.end = otherNewEnd;
                otherTimeText.textContent = otherNewStart + '-' + otherNewEnd;
                timeSlotsPerDay[day][i] = otherNewStart + '-' + otherNewEnd;
            }
        }
        
        timeText.style.display = 'inline-block';
        timeInputs.style.display = 'none';
    };
    
    const cancel = () => {
        isEditing = false;
        timeText.style.display = 'inline-block';
        timeInputs.style.display = 'none';
    };
    
    timeInputs.addEventListener('click', (e) => e.stopPropagation());
    
    setTimeout(() => {
        document.addEventListener('click', function closeOnClickOutside(e) {
            if (!timeInputs.contains(e.target) && !timeText.contains(e.target)) {
                if (isEditing) {
                    saveTime();
                }
                document.removeEventListener('click', closeOnClickOutside);
            }
        });
    }, 150);
    
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
        saveBtn.onclick = (e) => {
            e.stopPropagation();
            e.preventDefault();
            saveTime();
        };
    }
    
    setTimeout(() => startInput.focus(), 50);
}

function toggleDay(day) {
    const allDays = document.querySelectorAll('[id^="day-"]');
    const currentContent = document.getElementById('day-' + day);
    const currentIcon = document.getElementById('icon-' + day);
    
    allDays.forEach(el => {
        if (el !== currentContent) {
            el.style.display = 'none';
        }
    });
    
    document.querySelectorAll('[id^="icon-"]').forEach(icon => {
        if (icon !== currentIcon) {
            icon.className = 'bi bi-chevron-down';
        }
    });
    
    if (currentContent.style.display === 'none' || currentContent.style.display === '') {
        currentContent.style.display = 'block';
        currentIcon.className = 'bi bi-chevron-up';
    } else {
        currentContent.style.display = 'none';
        currentIcon.className = 'bi bi-chevron-down';
    }
}

function switchView(viewType) {
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');
    
    if (viewType === 'grid') {
        gridView.style.display = 'block';
        listView.style.display = 'none';
        gridBtn.style.opacity = '1';
        listBtn.style.opacity = '0.5';
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
    } else {
        gridView.style.display = 'none';
        listView.style.display = 'block';
        gridBtn.style.opacity = '0.5';
        listBtn.style.opacity = '1';
        gridBtn.classList.remove('active');
        listBtn.classList.add('active');
    }
}

function adjustTimeList(index, amount) {
    const timeHeaders = document.querySelectorAll('#listView .card-header h6');
    const timeHeader = timeHeaders[index];
    const currentTime = timeHeader.textContent;
    const [startTime, endTime] = currentTime.split('-');
    
    const newStart = addMinutes(startTime, amount);
    const newEnd = addMinutes(endTime, amount);
    
    if (newEnd <= newStart) return; // Prevent invalid slots
    
    const newTimeSlot = newStart + '-' + newEnd;
    timeSlots[index] = newTimeSlot;
    timeHeader.textContent = newTimeSlot;
    
    // Update all schedule cells for this time slot
    const card = timeHeader.closest('.card');
    const cells = card.querySelectorAll('.schedule-cell-list');
    cells.forEach(cell => {
        cell.dataset.start = newStart;
        cell.dataset.end = newEnd;
    });
}

function updateSubjectOptions(sectionSelect, day, timeSlot) {
    const sectionId = sectionSelect.value;
    
    const cell = sectionSelect.closest('.schedule-cell, .schedule-cell-list');
    const subjectSelect = cell.querySelector('.subject-select');
    
    if (!sectionId || !assignedCombinations[sectionId]) {
        subjectSelect.innerHTML = '<option value="">Select Section First</option>';
        subjectSelect.disabled = true;
        return;
    }
    
    // Get current subject selection
    const currentSubjectId = subjectSelect.value;
    
    // Get all subjects already scheduled on this day (excluding current cell)
    const scheduledSubjectsOnDay = new Set();
    document.querySelectorAll('.schedule-cell, .schedule-cell-list').forEach(otherCell => {
        if (otherCell !== cell && otherCell.dataset.day === day) {
            const otherSubjectSelect = otherCell.querySelector('.subject-select');
            if (otherSubjectSelect && otherSubjectSelect.value) {
                scheduledSubjectsOnDay.add(parseInt(otherSubjectSelect.value));
            }
        }
    });
    
    // Clear and populate subject options with only assigned subjects
    subjectSelect.innerHTML = '<option value="">Select Subject</option>';
    
    const assignedSubjectIds = assignedCombinations[sectionId];
    allSubjects.forEach(subject => {
        if (assignedSubjectIds.includes(subject.id)) {
            // Skip subjects already scheduled on this day (except current selection)
            if (scheduledSubjectsOnDay.has(subject.id) && currentSubjectId != subject.id) {
                return;
            }
            
            const option = document.createElement('option');
            option.value = subject.id;
            option.textContent = subject.subject_name;
            
            if (currentSubjectId == subject.id) {
                option.selected = true;
            }
            subjectSelect.appendChild(option);
        }
    });
    
    subjectSelect.disabled = false;
    
    // Always fetch room suggestions when section is selected
    if (sectionSelect.value) {
        fetchScheduleSuggestions(sectionSelect.value, subjectSelect.value || '', day, cell);
    }
    
    // Refresh all subject dropdowns on the same day
    setTimeout(() => refreshSubjectDropdownsForDay(day), 50);
}

function refreshSubjectDropdownsForDay(day) {
    // First, collect all scheduled subjects for this day
    const scheduledSubjectsOnDay = new Map();
    document.querySelectorAll('.schedule-cell, .schedule-cell-list').forEach(cell => {
        if (cell.dataset.day === day) {
            const subjectSelect = cell.querySelector('.subject-select');
            if (subjectSelect && subjectSelect.value && subjectSelect.value !== '') {
                const subjectId = parseInt(subjectSelect.value);
                scheduledSubjectsOnDay.set(subjectId, cell);
            }
        }
    });
    
    console.log(`Refreshing ${day} - Scheduled subjects:`, Array.from(scheduledSubjectsOnDay.keys()));
    
    // Now update all dropdowns for this day
    document.querySelectorAll('.schedule-cell, .schedule-cell-list').forEach(cell => {
        if (cell.dataset.day === day) {
            const sectionSelect = cell.querySelector('.section-select');
            const subjectSelect = cell.querySelector('.subject-select');
            
            // Only refresh if section is selected and subject dropdown is enabled
            if (sectionSelect && sectionSelect.value && subjectSelect && !subjectSelect.disabled) {
                const sectionId = sectionSelect.value;
                
                if (!assignedCombinations[sectionId]) return;
                
                // Save current selection
                const savedValue = subjectSelect.value;
                
                // Rebuild options
                subjectSelect.innerHTML = '<option value="">Select Subject</option>';
                const assignedSubjectIds = assignedCombinations[sectionId];
                
                let addedCount = 0;
                let skippedCount = 0;
                
                allSubjects.forEach(subject => {
                    if (assignedSubjectIds.includes(subject.id)) {
                        // Skip subjects already scheduled on this day by OTHER cells
                        if (scheduledSubjectsOnDay.has(subject.id)) {
                            const cellWithSubject = scheduledSubjectsOnDay.get(subject.id);
                            if (cellWithSubject !== cell) {
                                skippedCount++;
                                return; // Skip this subject
                            }
                        }
                        
                        const option = document.createElement('option');
                        option.value = subject.id;
                        option.textContent = subject.subject_name;
                        subjectSelect.appendChild(option);
                        addedCount++;
                    }
                });
                
                console.log(`Cell ${cell.dataset.start}: Added ${addedCount} subjects, Skipped ${skippedCount}`);
                
                // Restore selection if it still exists in options
                if (savedValue) {
                    subjectSelect.value = savedValue;
                }
            }
        }
    });
}



// Initialize existing schedules
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing existing schedules...');
    
    // Process all schedule cells to set existing data
    document.querySelectorAll('.schedule-cell, .schedule-cell-list').forEach(cell => {
        const day = cell.dataset.day;
        const startTime = cell.dataset.start;
        const endTime = cell.dataset.end;
        const timeSlot = startTime + '-' + endTime;
        
        // Find existing schedule for this cell
        const existingSchedule = existingSchedules.find(schedule => {
            const scheduleTimeSlot = schedule.start_time.substring(0,5) + '-' + schedule.end_time.substring(0,5);
            return schedule.day_of_week === day && scheduleTimeSlot === timeSlot;
        });
        
        if (existingSchedule) {
            console.log('Found existing schedule for', day, timeSlot, existingSchedule);
            
            const sectionSelect = cell.querySelector('.section-select');
            const subjectSelect = cell.querySelector('.subject-select');
            
            // Set section first
            if (sectionSelect && existingSchedule.section_id) {
                sectionSelect.value = existingSchedule.section_id;
                
                // Update subject options based on selected section
                updateSubjectOptions(sectionSelect, day, timeSlot);
                
                // Set subject after options are populated (without triggering refresh)
                setTimeout(() => {
                    if (subjectSelect && existingSchedule.subject_id) {
                        subjectSelect.value = existingSchedule.subject_id;
                        console.log('Set subject to:', existingSchedule.subject_id, 'for', day, timeSlot);
                        // Fetch room suggestions after setting subject
                        fetchScheduleSuggestions(existingSchedule.section_id, existingSchedule.subject_id, day, cell);
                    }
                }, 100);
            }
        }
    });
    
    // Refresh all days after initialization to filter out already-selected subjects
    setTimeout(() => {
        ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'].forEach(day => {
            refreshSubjectDropdownsForDay(day);
        });
    }, 300);
});

document.getElementById('scheduleForm').addEventListener('submit', function(e) {
    console.log('Grid form submitted');
    saveSchedule(e, '.schedule-cell');
});

document.getElementById('scheduleFormList').addEventListener('submit', function(e) {
    console.log('List form submitted');
    saveSchedule(e, '.schedule-cell-list');
});

async function saveSchedule(e, cellSelector) {
    e.preventDefault();
    console.log('saveSchedule called with selector:', cellSelector);
    
    const schedules = [];
    const cells = document.querySelectorAll(cellSelector);
    console.log('Found cells:', cells.length);
    const errors = [];
    
    cells.forEach(cell => {
        const sectionSelect = cell.querySelector('.section-select');
        const subjectSelect = cell.querySelector('.subject-select');
        const roomInput = cell.querySelector('.room-input');
        
        // Validation: if section is selected, subject is required
        if (sectionSelect.value) {
            if (!subjectSelect.value) {
                errors.push(`${cell.dataset.day} ${cell.dataset.start}-${cell.dataset.end}: Subject is required when section is selected`);
            }
        }
        
        if (subjectSelect.value && sectionSelect.value) {
            // Convert time format for database
            const startTime = cell.dataset.start + ':00';
            const endTime = cell.dataset.end + ':00';
            
            schedules.push({
                subject_id: subjectSelect.value,
                section_id: sectionSelect.value,
                day_of_week: cell.dataset.day,
                start_time: startTime,
                end_time: endTime,
                room: roomInput.value || ''
            });
        }
    });
    
    if (errors.length > 0) {
        showValidationModal(errors);
        return;
    }

    // Local pre-check: every block in this grid belongs to the same teacher, so
    // two overlapping blocks anywhere in the submitted set would put the
    // teacher in two places at once (and, when they share a section, double-book
    // the section too). The previous version only compared identical
    // room+time pairs, so overlapping-but-not-identical blocks slipped through
    // to the server. The server re-checks this against the stored timetable -
    // this pass only saves a round trip.
    const conflicts = [];
    const minutes = value => {
        const [hour, minute] = value.substring(0, 5).split(':').map(Number);
        return hour * 60 + minute;
    };
    const overlaps = (a, b) => minutes(a.start_time) < minutes(b.end_time)
        && minutes(b.start_time) < minutes(a.end_time);

    for (let i = 0; i < schedules.length; i++) {
        for (let j = i + 1; j < schedules.length; j++) {
            const first = schedules[i];
            const second = schedules[j];

            if (first.day_of_week !== second.day_of_week || !overlaps(first, second)) continue;

            const when = `${first.day_of_week} ${first.start_time.substring(0, 5)}-${first.end_time.substring(0, 5)}`;
            const also = `${second.start_time.substring(0, 5)}-${second.end_time.substring(0, 5)}`;

            if (first.section_id === second.section_id) {
                conflicts.push(`Section/Self conflict: two blocks in the same section on ${first.day_of_week} overlap (${when} and ${also}).`);
            } else {
                conflicts.push(`Teacher conflict: you are scheduled in two sections at the same time on ${first.day_of_week} (${when} and ${also}).`);
            }

            if (first.room && second.room
                && first.room.toLowerCase() === second.room.toLowerCase()) {
                conflicts.push(`Room conflict: "${first.room}" is used twice at overlapping times on ${first.day_of_week} (${when} and ${also}).`);
            }
        }
    }

    if (conflicts.length > 0) {
        // No "save anyway" escape hatch: an overlapping grid is exactly what the
        // server now refuses, so offering to bypass it only produced a rejected
        // save and a vague error.
        showValidationModal(
            conflicts,
            'Schedule Conflict',
            'These blocks overlap, and a teacher cannot be in two places at once. Fix them before saving:'
        );
        return;
    }
    
    console.log('Saving schedules:', schedules);
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    submitBtn.disabled = true;
    
    console.log('Sending schedule data:', schedules);
    
    fetch('<?= base_url('admin/teachers/schedule/save/' . $teacher['id']) ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ schedules: schedules })
    })
    .then(response => response.json().catch(() => null).then(data => {
        // The conflict report is the payload of a 409, so a non-ok status is
        // expected here; falling through to the catch() below would have thrown
        // the reasons away and shown "Network response was not ok: 409".
        return { ok: response.ok, status: response.status, data };
    }))
    .then(({ data }) => {
        console.log('Server response:', data); // Debug log
        if (data && data.success) {
            showNotification(data.message || 'Schedule saved successfully!', 'success');
            // Reload page after 2 seconds to show saved data
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            // Every rejected block is listed, not just the first: the server
            // returns them all in `conflicts`.
            const messages = [];
            (data?.conflicts || []).forEach(conflict => {
                const message = typeof conflict === 'string' ? conflict : (conflict.message || '');
                if (message) messages.push(message);
            });

            if (messages.length === 0 && data?.error) {
                messages.push(data.error);
            }

            console.error('Save failed:', data);
            showValidationModal(
                messages.length > 0 ? messages : ['The schedule was not saved.'],
                'Schedule Not Saved',
                'The server rejected this timetable. Nothing was changed:'
            );
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showNotification('Failed to save schedule: ' + error.message, 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}
function showNotification(message, type) {
    // Remove existing notifications
    const existing = document.querySelector('.schedule-notification');
    if (existing) existing.remove();
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `schedule-notification alert alert-${type === 'success' ? 'success' : 'danger'}`;
    notification.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
        ${message}
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1050;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border: none;
        border-radius: 8px;
        animation: slideIn 0.3s ease-out;
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .suggestion-badge {
        display: inline-block;
        padding: 2px 8px;
        margin: 2px;
        font-size: 0.8125rem;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .suggestion-badge:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .room-suggestions {
        font-size: 0.8125rem;
    }
    .suggestion-modal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        z-index: 1060;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        font-size: 16px;
    }
    .suggestion-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1055;
    }
`;
document.head.appendChild(style);

// Smart suggestion functions
function validateSubjectSelection(subjectSelect) {
    // Validation is now handled by filtering options in updateSubjectOptions
    // This function is kept for potential future use
    return true;
}

function showSmartSuggestions(subjectSelect) {
    const cell = subjectSelect.closest('.schedule-cell');
    const sectionSelect = cell.querySelector('.section-select');
    const day = cell.dataset.day;
    
    // Always fetch suggestions when subject changes
    if (sectionSelect.value) {
        fetchScheduleSuggestions(sectionSelect.value, subjectSelect.value || '', day, cell);
    }
}

function checkForSuggestions(sectionSelect) {
    const cell = sectionSelect.closest('.schedule-cell');
    const subjectSelect = cell.querySelector('.subject-select');
    
    if (subjectSelect.value) {
        showSmartSuggestions(subjectSelect);
    }
}

function fetchScheduleSuggestions(sectionId, subjectId, day, cell) {
    if (!sectionId) return;
    
    fetch('<?= base_url('admin/teachers/schedule/suggestions/' . $teacher['id']) ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            section_id: sectionId,
            subject_id: subjectId || '',
            day: day
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.suggestions) {
            displaySuggestions(data, cell);
        } else {
            // Show default rooms as fallback
            displaySuggestions({suggestions: []}, cell);
        }
    })
    .catch(error => {
        console.error('Error fetching suggestions:', error);
        // Show default rooms on error
        displaySuggestions({suggestions: []}, cell);
    });
}

function displaySuggestions(data, cell) {
    const roomSuggestionsDiv = cell.querySelector('.room-suggestions');
    if (!roomSuggestionsDiv) return;
    
    const currentDay = cell.dataset.day;
    const currentStart = cell.dataset.start;
    const currentEnd = cell.dataset.end;
    
    // Find suggestions for current time slot
    const matchingSuggestions = data.suggestions.filter(s => 
        s.day === currentDay && s.start_time === currentStart && s.end_time === currentEnd
    );
    
    let rooms = [];
    if (matchingSuggestions.length > 0 && matchingSuggestions[0].available_rooms && matchingSuggestions[0].available_rooms.length > 0) {
        rooms = matchingSuggestions[0].available_rooms;
    } else {
        // Fallback to default rooms
        rooms = ['Room 101', 'Room 102', 'Room 103', 'Room 104', 'Room 105'];
    }
    
    roomSuggestionsDiv.innerHTML = `
        <small class="text-muted">Available: </small>
        ${rooms.slice(0, 3).map(room => 
            `<span class="suggestion-badge bg-success text-white" onclick="selectRoom(this, '${room}')">
                ${room}
            </span>`
        ).join('')}
    `;
    roomSuggestionsDiv.style.display = 'block';
}

function selectRoom(badge, room) {
    const cell = badge.closest('.schedule-cell');
    const roomInput = cell.querySelector('.room-input');
    if (roomInput) {
        roomInput.value = room;
        roomInput.focus();
        
        // Highlight effect
        roomInput.style.background = '#d4edda';
        setTimeout(() => {
            roomInput.style.background = '';
        }, 1000);
    }
}

function suggestRoom(btn) {
    const cell = btn.closest('.schedule-cell');
    const sectionSelect = cell.querySelector('.section-select');
    const subjectSelect = cell.querySelector('.subject-select');
    const day = cell.dataset.day;
    
    if (!sectionSelect.value || !subjectSelect.value) {
        showNotification('Please select section and subject first', 'error');
        return;
    }
    
    // Show modal with all suggestions
    showSuggestionsModal(sectionSelect.value, subjectSelect.value, day, cell);
}

function showSuggestionsModal(sectionId, subjectId, day, cell) {
    fetch('<?= base_url('admin/teachers/schedule/suggestions/' . $teacher['id']) ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            section_id: sectionId,
            subject_id: subjectId,
            day: day
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displaySuggestionsModal(data, cell);
        }
    })
    .catch(error => console.error('Error:', error));
}

function displaySuggestionsModal(data, cell) {
    const overlay = document.createElement('div');
    overlay.className = 'suggestion-overlay';
    
    const modal = document.createElement('div');
    modal.className = 'suggestion-modal';
    
    const currentDay = cell.dataset.day;
    const suggestions = data.suggestions.filter(s => s.day === currentDay);
    
    modal.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-lightbulb-fill text-warning"></i> Smart Recommendations</h5>
            <button class="btn-close" onclick="closeSuggestionsModal()"></button>
        </div>
        <div class="mb-3">
            <strong>Subject:</strong> ${data.subject.subject_name}<br>
            <strong>Section:</strong> ${data.section.section_name}<br>
            <strong>Day:</strong> ${currentDay}
        </div>
        <h6 class="mb-2">Available Time Slots:</h6>
        <div class="list-group">
            ${suggestions.length > 0 ? suggestions.map(s => `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong style="font-size: 16px;">${s.start_time} - ${s.end_time}</strong>
                            <div class="mt-1">
                                <span class="text-muted" style="font-size: 14px;">Available Rooms:</span>
                                ${s.available_rooms.map(room => 
                                    `<span class="badge bg-success ms-1">${room}</span>`
                                ).join('')}
                            </div>
                        </div>
                        <button class="btn btn-sm btn-primary" 
                                onclick="applySuggestion('${s.start_time}', '${s.end_time}', '${s.available_rooms[0]}', '${currentDay}')">
                            Apply
                        </button>
                    </div>
                </div>
            `).join('') : '<div class="alert alert-info">No available time slots for this day</div>'}
        </div>
        ${data.available_teachers && data.available_teachers.length > 0 ? `
            <h6 class="mt-3 mb-2">Other Available Teachers:</h6>
            <div class="list-group">
                ${data.available_teachers.slice(0, 3).map(t => `
                    <div class="list-group-item">
                        <i class="bi bi-person"></i> ${t.first_name} ${t.last_name}
                        <small class="text-muted d-block">${t.specialization || 'General'}</small>
                    </div>
                `).join('')}
            </div>
        ` : ''}
    `;
    
    document.body.appendChild(overlay);
    document.body.appendChild(modal);
    
    overlay.onclick = closeSuggestionsModal;
}

function closeSuggestionsModal() {
    document.querySelectorAll('.suggestion-overlay, .suggestion-modal').forEach(el => el.remove());
}

function showValidationModal(errors, title = 'Incomplete Schedule', intro = 'Please complete the following:') {
    const overlay = document.createElement('div');
    overlay.className = 'suggestion-overlay';
    
    const modal = document.createElement('div');
    modal.className = 'suggestion-modal';
    modal.style.maxWidth = '500px';
    
    modal.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-exclamation-triangle text-warning"></i> ${title}</h5>
            <button class="btn-close" onclick="closeValidationModal()"></button>
        </div>
        <p class="mb-3">${intro}</p>
        <div class="list-group mb-3">
            ${errors.map(error => `
                <div class="list-group-item list-group-item-warning">
                    <i class="bi bi-arrow-right-circle"></i> ${error}
                </div>
            `).join('')}
        </div>
        <div class="text-end">
            <button class="btn btn-primary" onclick="closeValidationModal()">
                <i class="bi bi-check-lg"></i> OK
            </button>
        </div>
    `;
    
    document.body.appendChild(overlay);
    document.body.appendChild(modal);
    
    overlay.onclick = closeValidationModal;
}

function closeValidationModal() {
    document.querySelectorAll('.suggestion-overlay, .suggestion-modal').forEach(el => el.remove());
}

function applySuggestion(startTime, endTime, room, day) {
    // Find the row with matching day and update it
    const rows = document.querySelectorAll(`tr[data-day="${day}"]`);
    
    // Find empty row or ask user which row to update
    let targetRow = null;
    for (const row of rows) {
        const cell = row.querySelector('.schedule-cell');
        const subjectSelect = cell.querySelector('.subject-select');
        if (!subjectSelect.value) {
            targetRow = row;
            break;
        }
    }
    
    if (targetRow) {
        const cell = targetRow.querySelector('.schedule-cell');
        cell.dataset.start = startTime;
        cell.dataset.end = endTime;
        
        const roomInput = cell.querySelector('.room-input');
        if (roomInput) roomInput.value = room;
        
        // Update time display
        const timeText = targetRow.querySelector('.time-text');
        if (timeText) timeText.textContent = `${startTime}-${endTime}`;
        
        showNotification('Suggestion applied! Please select section and subject.', 'success');
    } else {
        showNotification('All time slots are filled. Please clear a slot first.', 'error');
    }
    
    closeSuggestionsModal();
}

</script>

<?= $this->endSection() ?>