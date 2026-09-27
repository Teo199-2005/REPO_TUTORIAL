<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-0">Manage Schedule</h1>
        <?php if ($teacher): ?>
            <p class="text-muted mb-0"><?= esc($teacher['first_name'] . ' ' . $teacher['last_name']) ?></p>
        <?php endif; ?>
    </div>
    <a href="<?= base_url('teacher/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<?php /* A timetable is the one teacher page where a picture of a calendar says more
         than a bubble pinned in the corner can, so this page earns the
         portal-schedules artwork and a full line of its own rather than relying on
         the dock alone.

         The two are independent on purpose. The poster is decorative and returns
         '' when the file is absent; mascot_say() carries the meaning and falls back
         to plain readable text. Either one can go missing without taking the other
         with it. */ ?>
<?php
$schedulePoster = mascot_poster_banner('portal-schedules', ['alt' => '']);
?>
<div class="schedule-intro<?= $schedulePoster === '' ? ' schedule-intro--solo' : '' ?>">
    <?php if ($schedulePoster !== ''): ?>
        <div class="schedule-intro__art"><?= $schedulePoster ?></div>
    <?php endif; ?>
    <div class="schedule-intro__say">
        <?= mascot_say([
            'pose'  => 'laptop',
            'title' => 'Your teaching schedule',
            'text'  => 'Set the week here and the portal keeps it for you. Tap a cell to change what you teach in that slot, then save. Anything the office changes appears here too.',
            'align' => 'left',
            'size'  => 150,
        ]) ?>
    </div>
</div>

<?php if ($error = session('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= esc($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success = session('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= esc($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Weekly Schedule</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= base_url('teacher/schedule/save') ?>">
            <?= csrf_field() ?>
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 120px;">Time</th>
                            <th>Monday</th>
                            <th>Tuesday</th>
                            <th>Wednesday</th>
                            <th>Thursday</th>
                            <th>Friday</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $timeSlots = [
                            '07:00-08:00', '08:00-09:00', '09:00-10:00', '10:00-11:00', '11:00-12:00',
                            '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00', '16:00-17:00'
                        ];
                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                        ?>
                        
                        <?php foreach ($timeSlots as $timeSlot): ?>
                            <tr>
                                <td class="fw-bold text-center align-middle"><?= $timeSlot ?></td>
                                <?php foreach ($days as $day): ?>
                                    <td style="padding: 8px;">
                                        <?php 
                                        $currentSchedule = $schedules[$day][$timeSlot] ?? null;
                                        ?>
                                        
                                        <div class="mb-2">
                                            <label class="form-label small">Subject</label>
                                            <select name="schedule[<?= $day ?>][<?= $timeSlot ?>][subject_id]" class="form-select form-select-sm">
                                                <option value="">Select Subject</option>
                                                <?php foreach ($subjects as $subject): ?>
                                                    <option value="<?= $subject['id'] ?>" 
                                                            <?= ($currentSchedule && $currentSchedule['subject_id'] == $subject['id']) ? 'selected' : '' ?>>
                                                        <?= esc($subject['subject_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label class="form-label small">Section</label>
                                            <select name="schedule[<?= $day ?>][<?= $timeSlot ?>][section_id]" class="form-select form-select-sm">
                                                <option value="">Select Section</option>
                                                <?php foreach ($sections as $section): ?>
                                                    <option value="<?= $section['id'] ?>" 
                                                            <?= ($currentSchedule && $currentSchedule['section_id'] == $section['id']) ? 'selected' : '' ?>>
                                                        <?= esc($section['section_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="form-label small">Room</label>
                                            <input type="text" name="schedule[<?= $day ?>][<?= $timeSlot ?>][room]" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="Room"
                                                   value="<?= $currentSchedule ? esc($currentSchedule['room']) : '' ?>">
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Save Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>