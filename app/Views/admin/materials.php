<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?php helper('materials'); ?>

<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="h3 mb-2">School Materials</h1>
        <p class="text-muted mb-0">Upload handbooks, rules, forms, and learning resources. Materials marked for the website appear in the public <strong>Materials</strong> menu for visitors, students, and parents.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
        <i class="bi bi-cloud-upload me-2"></i>Upload material
    </button>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= esc(session()->getFlashdata('success')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= esc(session()->getFlashdata('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0"><i class="bi bi-folder2-open me-2 text-primary"></i>Uploaded materials</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($materials)): ?>
            <div class="text-center py-5 px-3">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <p class="text-muted mt-3 mb-0">No materials yet. Upload a PDF handbook, school rules, or other documents for the community.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 admin-table" data-js-paged="1">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>File</th>
                            <th>Website</th>
                            <th>Uploaded</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $material): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($material->title) ?></div>
                                    <?php if (! empty($material->description)): ?>
                                        <small class="text-muted"><?= esc($material->description) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-info text-white"><?= esc(material_category_label($material->category)) ?></span></td>
                                <td>
                                    <span class="badge bg-secondary"><?= esc(strtoupper((string) $material->file_type)) ?></span>
                                    <small class="text-muted d-block"><?= esc(material_format_size($material->file_size)) ?></small>
                                </td>
                                <td>
                                    <?php if ((int) ($material->show_on_website ?? 0) === 1): ?>
                                        <span class="badge bg-success">Visible</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= esc(date('M j, Y', strtotime((string) $material->created_at))) ?></small></td>
                                <td class="text-end text-nowrap">
                                    <?php if (material_can_preview_inline($material->file_type)): ?>
                                        <a href="<?= esc(material_preview_url((int) $material->id)) ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" title="Preview">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= base_url('admin/materials/download/' . (int) $material->id) ?>" class="btn btn-sm btn-outline-secondary" title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editMaterialModal<?= (int) $material->id ?>" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteMaterialModal<?= (int) $material->id ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload modal -->
<div class="modal fade" id="uploadMaterialModal" tabindex="-1" aria-labelledby="uploadMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="<?= base_url('admin/materials/upload') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadMaterialModalLabel"><i class="bi bi-cloud-upload me-2"></i>Upload school material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="upload_title">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="upload_title" name="title" required maxlength="255" placeholder="e.g. Student Handbook SY 2025–2026">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="upload_description">Description</label>
                        <textarea class="form-control" id="upload_description" name="description" rows="2" maxlength="1000" placeholder="Short summary for visitors (optional)"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="upload_category">Category</label>
                            <select
                                class="form-select material-category-select"
                                id="upload_category"
                                name="category"
                                data-custom-target="#upload_custom_category_group"
                            >
                                <?php foreach ($categories as $key => $label): ?>
                                    <?php if ($key === 'other'): ?>
                                        <option value="other"><?= esc('Custom category') ?></option>
                                    <?php else: ?>
                                        <option value="<?= esc($key) ?>"><?= esc($label) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 d-none" id="upload_custom_category_group">
                        <label class="form-label fw-semibold" for="upload_custom_category">
                            Custom category <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control material-custom-category-input"
                            id="upload_custom_category"
                            name="custom_category"
                            maxlength="100"
                            placeholder="e.g. Parent Letters, Scholarship Info"
                        >
                        <small class="text-muted">Shown on the website exactly as typed.</small>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold" for="upload_file">File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="upload_file" name="material" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.zip">
                        <small class="text-muted">PDF, Office documents, images, or ZIP — max 15MB.</small>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_on_website" value="1" id="upload_show_web" checked>
                        <label class="form-check-label" for="upload_show_web">Show on public website (Materials menu)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary text-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($materials as $material): ?>
<?php
    $materialCategory = (string) ($material->category ?? '');
    $isCustomCategory = $materialCategory !== '' && ! isset($categories[$materialCategory]);
?>
<div class="modal fade" id="editMaterialModal<?= (int) $material->id ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= base_url('admin/materials/update/' . (int) $material->id) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Edit material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title</label>
                        <input type="text" class="form-control" name="title" value="<?= esc($material->title) ?>" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" name="description" rows="2" maxlength="1000"><?= esc($material->description ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <select
                            class="form-select material-category-select"
                            name="category"
                            data-custom-target="#edit_custom_category_<?= (int) $material->id ?>"
                        >
                            <?php foreach ($categories as $key => $label): ?>
                                <?php if ($key === 'other'): ?>
                                    <option
                                        value="other"
                                        <?= $isCustomCategory || $materialCategory === 'other' ? ' selected' : '' ?>
                                    >
                                        <?= esc('Custom category') ?>
                                    </option>
                                <?php else: ?>
                                    <option
                                        value="<?= esc($key) ?>"
                                        <?= $materialCategory === $key ? ' selected' : '' ?>
                                    >
                                        <?= esc($label) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div
                        class="mb-3<?= $isCustomCategory ? '' : ' d-none' ?>"
                        id="edit_custom_category_<?= (int) $material->id ?>"
                    >
                        <label class="form-label fw-semibold" for="edit_custom_category_<?= (int) $material->id ?>">
                            Custom category <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control material-custom-category-input"
                            id="edit_custom_category_<?= (int) $material->id ?>_input"
                            name="custom_category"
                            maxlength="100"
                            value="<?= $isCustomCategory ? esc($materialCategory) : '' ?>"
                            placeholder="e.g. Parent Letters, Scholarship Info"
                        >
                        <small class="text-muted">Shown on the website exactly as typed.</small>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_on_website" value="1" id="show_web_<?= (int) $material->id ?>"<?= (int) ($material->show_on_website ?? 0) === 1 ? ' checked' : '' ?>>
                        <label class="form-check-label" for="show_web_<?= (int) $material->id ?>">Show on public website</label>
                    </div>
                    <p class="small text-muted mb-0 mt-2">File: <?= esc($material->file_name) ?> — upload a new file by deleting and re-uploading.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary text-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php foreach ($materials as $material): ?>
<div class="modal fade" id="deleteMaterialModal<?= (int) $material->id ?>" tabindex="-1" aria-labelledby="deleteMaterialModalLabel<?= (int) $material->id ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-danger">
                <h5 class="modal-title" id="deleteMaterialModalLabel<?= (int) $material->id ?>"><i class="bi bi-exclamation-triangle me-2"></i>Delete material?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Delete <strong>&ldquo;<?= esc($material->title) ?>&rdquo;</strong> permanently?</p>
                <p class="text-muted small mb-0">This action cannot be undone. The uploaded file will be removed and the material will no longer appear for teachers or on the public website.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary text-dark" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?= base_url('admin/materials/delete/' . (int) $material->id) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<script>
(function () {
  function syncCategory(select) {
    if (!select) return;
    var targetSelector = select.getAttribute('data-custom-target');
    if (!targetSelector) return;
    var group = document.querySelector(targetSelector);
    if (!group) return;
    var isOther = select.value === 'other';
    group.classList.toggle('d-none', !isOther);
    var input = group.querySelector('.material-custom-category-input');
    if (input) {
      input.required = isOther;
      if (!isOther) {
        input.value = '';
      }
    }
  }

  document.querySelectorAll('.material-category-select').forEach(function (select) {
    select.addEventListener('change', function () {
      syncCategory(select);
    });
    syncCategory(select);
  });
})();
</script>

<?= $this->endSection() ?>
