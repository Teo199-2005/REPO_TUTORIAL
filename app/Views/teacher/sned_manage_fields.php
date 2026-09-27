<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Manage Fields</h1>
        <small class="text-muted">Category: <strong><?= esc($category['name']) ?></strong></small>
    </div>
    <a href="javascript:history.back()" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= session()->getFlashdata('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= session()->getFlashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Existing Fields -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Fields / Indicators</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($category['fields'])): ?>
                <div class="text-center py-4">
                    <i class="bi bi-inboxes fs-1 text-muted mb-2"></i>
                    <p class="text-muted mb-0">No fields added yet. Add your first field below.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Field Name</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($category['fields'] as $index => $field): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <span id="field-name-<?= $field['id'] ?>"><?= esc($field['field_name']) ?></span>
                                    <form id="edit-form-<?= $field['id'] ?>" method="post" action="<?= base_url('teacher/sned/fields/edit') ?>" style="display: none;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="field_id" value="<?= $field['id'] ?>">
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="field_name" class="form-control" value="<?= esc($field['field_name']) ?>" required>
                                            <button type="submit" class="btn btn-success">Save</button>
                                            <button type="button" class="btn btn-secondary" onclick="cancelEdit(<?= $field['id'] ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editField(<?= $field['id'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= base_url("teacher/sned/fields/delete/{$field['id']}") ?>" style="display: inline;" class="sned-field-deactivate-form">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
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
    
    <div class="col-lg-4">
        <!-- Add New Field -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Add New Field</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= base_url('teacher/sned/fields/add') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label">Field Name</label>
                        <input type="text" name="field_name" class="form-control" placeholder="e.g., Gross Motor Skills" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i> Add Field
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Category Info -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h6 class="mb-2"><i class="bi bi-info-circle me-1"></i> About <?= esc($category['name']) ?></h6>
                <p class="text-muted small mb-0"><?= esc($category['description'] ?? 'No description available.') ?></p>
            </div>
        </div>
    </div>
</div>

<script>
function editField(fieldId) {
    document.getElementById('field-name-' + fieldId).style.display = 'none';
    document.getElementById('edit-form-' + fieldId).style.display = 'block';
}

function cancelEdit(fieldId) {
    document.getElementById('field-name-' + fieldId).style.display = 'block';
    document.getElementById('edit-form-' + fieldId).style.display = 'none';
}

// Styled confirmation modal instead of the native confirm() dialog.
document.querySelectorAll('form.sned-field-deactivate-form').forEach(function (form) {
    form.addEventListener('submit', async function (e) {
        if (form.dataset.confirmed === '1') { // real submit after modal approval
            form.dataset.confirmed = '';
            return;
        }
        e.preventDefault();
        const ok = await customConfirm('Deactivate this field?\nExisting grades will be preserved.', 'Deactivate Field');
        if (ok) {
            form.dataset.confirmed = '1';
            form.submit(); // bypasses the submit event, so no loop
        }
    });
});
</script>

<?= $this->endSection() ?>