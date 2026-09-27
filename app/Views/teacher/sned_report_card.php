<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-0 fw-bold">SNED Progress Report Card</h1>
        <small class="text-muted">
            Student: <strong><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></strong> | 
            LRN: <strong><?= esc($student['lrn'] ?? 'N/A') ?></strong> |
            SY <?= esc($schoolYear) ?>
        </small>
    </div>
    <div>
        <a href="<?= isset($pdfUrl) && $pdfUrl ? esc($pdfUrl) : base_url("teacher/sned/report-card-pdf/{$student['id']}") ?>" class="btn btn-sm btn-danger" target="_blank">
            <i class="bi bi-filetype-pdf me-1"></i> Export PDF
        </a>
        <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary ms-1">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<!-- Grading Legend -->
<div class="card border mb-3">
    <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center">
        <span class="fw-bold small me-3">Guide for Rating:</span>
        <?php foreach ($gradeSymbols as $gs): ?>
            <span class="badge rounded-pill me-1 bg-secondary" style="font-size: 0.8em;"><?= esc($gs['symbol']) ?></span>
            <small class="text-muted me-3"><?= esc($gs['label']) ?></small>
        <?php endforeach; ?>
    </div>
</div>

<?php foreach ($categories as $category): ?>
<div class="card border mb-2">
    <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold small"><?= esc($category['name']) ?></h6>
        <small class="text-muted"><?= count($category['fields']) ?> indicators</small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($category['fields'])): ?>
        <div class="text-center py-3">
            <p class="text-muted mb-0 small">No indicators defined for this domain.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 32px;">#</th>
                        <th>Performance indicators</th>
                        <?php foreach ($quarters as $q): ?>
                        <th class="text-center" style="width: 55px;"><?= $q ?></th>
                        <?php endforeach; ?>
                        <th class="text-center" style="width: 90px;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($category['fields'] as $index => $field): ?>
                    <tr>
                        <td class="text-center"><?= $index + 1 ?></td>
                        <td class="small"><?= esc($field['field_name']) ?></td>
                        <?php foreach ($quarters as $q): ?>
                        <td class="text-center">
                            <?php
                            $symbol = $allGrades[$field['id']][$q]['grade_symbol'] ?? '';
                            ?>
                            <?php if ($symbol): ?>
                                <span class="badge rounded-pill bg-secondary"><?= esc($symbol) ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="text-center small text-muted">
                            <?= $allGrades[$field['id']][1]['remarks'] ?? ($allGrades[$field['id']][2]['remarks'] ?? ($allGrades[$field['id']][3]['remarks'] ?? ($allGrades[$field['id']][4]['remarks'] ?? '-'))) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<div class="card border mt-2">
    <div class="card-body py-2 text-center">
        <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            This report card uses symbol-based grading. Quarters 1-4 are evaluated independently.
        </small>
    </div>
</div>

<?= $this->endSection() ?>