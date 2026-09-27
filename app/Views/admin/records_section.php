<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="mb-4">
    <a href="<?= base_url('admin/records?year=' . $schoolYear) ?>" class="btn btn-sm mb-3" style="background-color: #495057; color: white;">
        <i class="bi bi-arrow-left"></i> Back to Records
    </a>
    <h1 class="h3 mb-2"><?= esc(grade_level_label((int) $gradeLevel)) ?> - <?= esc($sectionName) ?></h1>
    <p class="text-muted">School Year: <?= $schoolYear ?></p>
</div>

<?php if (empty($students)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <h5 class="mt-3">No Students</h5>
            <p class="text-muted">No students are currently enrolled in this section for School Year <?= $schoolYear ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($students as $student): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="student-card">
                            <div class="fw-bold"><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></div>
                            <small class="text-muted"><?= esc($student['lrn']) ?></small>
                            <?php $isDevelopmental = in_array($student['grading_type'] ?? ($gradingType ?? 'numerical'), ['non_numerical', 'custom']); ?>
                            <?php if ($isDevelopmental): ?>
                                <a href="<?= base_url('admin/records/sned-report-card/' . $student['id'] . '?year=' . $schoolYear) ?>" class="btn btn-success btn-sm mt-2 w-100">
                                    <i class="bi bi-file-earmark-text"></i> View Report Card (Developmental)
                                </a>
                            <?php else: ?>
                                <a href="<?= base_url('admin/records/view/' . $student['id'] . '?year=' . $schoolYear) ?>" class="btn btn-primary btn-sm mt-2 w-100">
                                    <i class="bi bi-file-earmark-text"></i> View Report Card
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.student-card { padding: 15px; border-radius: 8px; background: #f8f9fa; border: 1px solid #dee2e6; }
</style>

<?= $this->endSection() ?>
