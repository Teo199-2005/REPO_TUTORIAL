<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= session()->getFlashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">SNED - Special Needs Education</h1>
        <small class="text-muted">School Year: <strong><?= esc($schoolYear) ?></strong></small>
    </div>
    <a href="<?= base_url('teacher/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
    </a>
</div>

<?php if (empty($sections)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
            <h5 class="text-muted">No Non-Numerical Sections Found</h5>
            <p class="text-muted mb-0">There are no active non-numerical sections with enrolled students.</p>
            <p class="text-muted">Contact the administrator to create non-numerical sections.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($sections as $sec): ?>
    <?php 
        $categoryModel = new \App\Models\SnedCategoryModel();
        $categories = $categoryModel->getCategoriesWithFieldCounts($sec['id']);
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-people me-2"></i><?= esc($sec['section_name']) ?>
                <small class="ms-2 badge bg-light text-primary"><?= ($sec['grading_type'] ?? 'numerical') === 'non_numerical' ? 'Non-Numerical' : 'Numerical' ?></small>
            </h5>
            <span class="badge bg-light text-primary"><?= count($sec['students']) ?> Student(s)</span>
        </div>
        <div class="card-body">
            <!-- Students List -->
            <div class="mb-4">
                <h6 class="fw-bold mb-2"><i class="bi bi-person-lines-fill me-1"></i> Enrolled Students</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>LRN</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sec['students'] as $idx => $student): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><strong><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></strong></td>
                                <td><?= esc($student['lrn'] ?? 'N/A') ?></td>
                                <td class="text-center">
                                    <a href="<?= base_url("teacher/sned/report-card/{$student['id']}") ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="View SNED Report Card">
                                        <i class="bi bi-file-earmark-text"></i> Report Card
                                    </a>
                                    <a href="<?= base_url("teacher/sned/learner-development-report-pdf/{$student['id']}") ?>" class="btn btn-sm btn-outline-success" target="_blank" title="View Learner Development Report (values, conduct and character development)">
                                        <i class="bi bi-person-heart"></i> Learner Development Report
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Categories (Developmental Domains) -->
            <h6 class="fw-bold mb-3"><i class="bi bi-diagram-3 me-1"></i> Developmental Domains</h6>
            <?php if (empty($categories)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No developmental domains have been added yet. 
                    Contact the administrator to add SNED categories from Settings.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($categories as $category): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 border">
                            <div class="card-body">
                                <h6 class="card-title fw-bold mb-1">
                                    <i class="bi bi-bookmark-check me-1 text-primary"></i><?= esc($category['name']) ?>
                                </h6>
                                <small class="text-muted d-block mb-3"><?= $category['field_count'] ?> field(s)</small>
                                <div class="d-grid gap-2">
                                    <a href="<?= base_url("teacher/sned/grades/{$sec['id']}/{$category['id']}") ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil-square me-1"></i> Enter Grades
                                    </a>
                                    <a href="<?= base_url("teacher/sned/categories/{$sec['id']}") ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-gear me-1"></i> Manage Fields
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?= $this->endSection() ?>
