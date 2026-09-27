<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-collection me-2 text-primary"></i>Learning Materials</h1>
        <p class="text-muted mb-0" style="max-width: 640px;">
            Browse and download learning materials, handbooks, forms, and resources shared by the school administration for your classes.
        </p>
    </div>
    <div>
        <span class="badge bg-light text-dark border"><i class="bi bi-eye me-1"></i> View-only access</span>
        <a href="<?= base_url('teacher/dashboard') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back
        </a>
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

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">School Materials</h5>
                <span class="badge bg-primary-subtle text-primary"><?= count($materials) ?> available</span>
            </div>
            <div class="card-body">
                <?php if (empty($materials)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-folder2-open display-1 text-muted"></i>
                        <h5 class="mt-3 text-muted">No materials available yet</h5>
                        <p class="text-muted">Materials shared by the school administration will appear here for you to view and download.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Shared By</th>
                                    <th>File Type</th>
                                    <th>Size</th>
                                    <th>Uploaded</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $material): ?>
                                    <?php
                                    $ext = strtolower((string) ($material->file_type ?? ''));
                                    $icon = 'text';
                                    if ($ext === 'pdf') $icon = 'pdf';
                                    elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = 'image';
                                    elseif (in_array($ext, ['zip', 'rar'])) $icon = 'zip';
                                    elseif (in_array($ext, ['doc', 'docx'])) $icon = 'word';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-<?= $icon ?> me-2 dash-icon-inline"></i>
                                                <div>
                                                    <strong><?= esc($material->title) ?></strong>
                                                    <?php if (!empty($material->description)): ?>
                                                        <br><small class="text-muted"><?= esc($material->description) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= ($material->category ?? '') === 'transcript' ? 'success' : (($material->category ?? '') === 'certificate' ? 'warning' : 'info') ?>">
                                                <?= ucfirst((string) $material->category) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (($material->uploaded_by_type ?? '') === 'admin'): ?>
                                                <span class="badge bg-info text-dark"><i class="bi bi-shield-check me-1"></i>Administration</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= strtoupper($ext) ?></span></td>
                                        <td><?= ($material->file_size ?? 0) >= 1048576 ? number_format($material->file_size / 1048576, 2) . ' MB' : number_format(($material->file_size ?? 0) / 1024, 1) . ' KB' ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('M j, Y', strtotime($material->created_at)) ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= base_url('teacher/materials/download/' . $material->id) ?>"
                                               class="btn btn-sm btn-outline-primary" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>