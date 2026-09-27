<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-0">Class Schedule</h1>
        <?php if ($student): ?>
            <p class="text-muted mb-0"><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></p>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('student/schedule-pdf') ?>" class="btn btn-primary"
           target="_blank" rel="noopener">
            <i class="bi bi-file-earmark-pdf"></i> Export PDF
        </a>
        <a href="<?= base_url('student/dashboard') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Weekly Class Schedule</h5>
    </div>
    <div class="card-body">
        <?php 
        // Check if there are any actual schedule entries
        $hasSchedule = false;
        if (!empty($schedules)) {
            foreach ($schedules as $daySchedules) {
                if (!empty($daySchedules)) {
                    $hasSchedule = true;
                    break;
                }
            }
        }
        ?>
        <?php if (!$hasSchedule): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x display-1 text-muted"></i>
                <h5 class="mt-3 text-muted">No schedule available</h5>
                <p class="text-muted">Your class schedule has not been set up yet.</p>
                <p class="text-muted">Please contact your adviser or the administration office.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead style="background: #34495E;">
                        <tr>
                            <th style="color: #F4D03F; font-weight: bold;">Monday</th>
                            <th style="color: #F4D03F; font-weight: bold;">Tuesday</th>
                            <th style="color: #F4D03F; font-weight: bold;">Wednesday</th>
                            <th style="color: #F4D03F; font-weight: bold;">Thursday</th>
                            <th style="color: #F4D03F; font-weight: bold;">Friday</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Get all unique time slots from actual schedules
                        $timeSlots = [];
                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                        
                        foreach ($days as $day) {
                            if (!empty($schedules[$day])) {
                                foreach (array_keys($schedules[$day]) as $timeSlot) {
                                    if (!in_array($timeSlot, $timeSlots)) {
                                        $timeSlots[] = $timeSlot;
                                    }
                                }
                            }
                        }
                        
                        // Sort time slots by start time
                        usort($timeSlots, function($a, $b) {
                            $startA = explode('-', $a)[0];
                            $startB = explode('-', $b)[0];
                            return strcmp($startA, $startB);
                        });
                        
                        // If no schedules, use default time slots
                        if (empty($timeSlots)) {
                            $timeSlots = [
                                '07:00-08:00', '08:00-09:00', '09:00-10:00', '10:00-11:00', '11:00-12:00',
                                '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00', '16:00-17:00'
                            ];
                        }
                        
                        $colorScheme = ['#F4D03F', '#5D6D7E', '#F9E79F', '#34495E', '#F7DC6F', '#566573', '#F8E6A0', '#4A5A6A', '#FAE5B8', '#3D4E5C'];
                        ?>
                        
                        <?php 
                        $lastWasMorning = true;
                        foreach ($timeSlots as $index => $timeSlot): 
                            // Check if this is the first afternoon slot (12:00 PM or later)
                            $times = explode('-', $timeSlot);
                            $startHour = (int)explode(':', $times[0])[0];
                            $isAfternoon = $startHour >= 12;
                            
                            // Add separator row when transitioning from morning to afternoon
                            if ($isAfternoon && $lastWasMorning):
                                $lastWasMorning = false;
                        ?>
                            <tr>
                                <td colspan="5" style="background: #ffffff; padding: 12px; text-align: center; border-top: 2px solid #ddd; border-bottom: 2px solid #ddd;">
                                    <span style="color: #000000; font-weight: bold; font-size: 1.1rem;">AFTERNOON SCHEDULE</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                            <tr>
                                <?php foreach ($days as $day): ?>
                                    <td style="padding: 15px; vertical-align: top; min-height: 80px;">
                                        <?php 
                                        $currentSchedule = $schedules[$day][$timeSlot] ?? null;
                                        // Convert time to 12-hour format with AM/PM
                                        $startTime = date('g:i A', strtotime($times[0]));
                                        $endTime = date('g:i A', strtotime($times[1]));
                                        $formattedTime = $startTime . ' - ' . $endTime;
                                        ?>
                                        
                                        <?php if ($currentSchedule && !empty($currentSchedule['subject_name'])): ?>
                                            <?php 
                                            $bgColor = $colorScheme[$index % 10];
                                            $isYellow = in_array($bgColor, ['#F4D03F', '#F9E79F', '#F7DC6F', '#F8E6A0', '#FAE5B8']);
                                            $textColor = '#0f172a';
                                            $borderColor = 'rgba(15, 23, 42, 0.10)';
                                            $accentColor = $isYellow ? '#34495E' : '#F4D03F';
                                            ?>
                                            <div class="schedule-item p-3 rounded shadow-sm" style="background: color-mix(in srgb, <?= $bgColor ?> 12%, #ffffff); border: 1px solid <?= $borderColor ?>; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px -2px rgba(15, 23, 42, 0.06);">
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <span class="status-dot" style="background: <?= $accentColor ?>;" aria-hidden="true"></span>
                                                    <div class="fw-bold" style="color: <?= $textColor ?>; font-size: 1rem;"><?= $formattedTime ?></div>
                                                </div>
                                                <div class="fw-bold mb-2 pb-2" style="color: <?= $textColor ?>; font-size: 1.1rem; border-bottom: 1px solid <?= $borderColor ?>;"><?= esc($currentSchedule['subject_name']) ?></div>
                                                <div class="mb-2 pb-2" style="color: <?= $textColor ?>; font-size: 1rem; border-bottom: 1px solid <?= $borderColor ?>;"><?= esc($currentSchedule['teacher_name']) ?></div>
                                                <?php if (!empty($currentSchedule['room'])): ?>
                                                    <div style="color: <?= $textColor ?>; font-size: 1rem;"><i class="bi bi-door-open"></i> <?= esc($currentSchedule['room']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>