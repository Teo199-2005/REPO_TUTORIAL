<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?php helper('grade_level'); ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-diagram-3 me-2"></i>Developmental Domains</h1>
        <small class="text-muted">
            Section: <strong><?= esc($section['section_name']) ?></strong>
            &bull; <?= esc(grade_level_display_name($section)) ?>
            &bull; <?= ($section['grading_type'] ?? '') === 'custom' ? 'Custom' : 'Non-Numerical' ?>
            <?php if (!empty($adviser)): ?>
                &bull; Adviser: <?= esc(trim($adviser['first_name'] . ' ' . ($adviser['middle_name'] ?? '') . ' ' . $adviser['last_name'])) ?>
            <?php else: ?>
                &bull; No Adviser
            <?php endif; ?>
        </small>
    </div>
    <a href="<?= base_url('admin/sections') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Sections
    </a>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= session()->getFlashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="alert alert-info border-0">
    <i class="bi bi-info-circle me-2"></i>
    <strong><?= esc($section['section_name']) ?></strong> is a non-numerical section: it does not use subjects.
    Student progress is assessed through developmental domains (categories) with performance indicators (fields).
    Teachers enter SNED grades from their portal; domains and fields are managed here and in
    <a href="<?= base_url('admin/settings') ?>">Settings</a>.
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i>Domains for this section
            <span class="badge bg-primary ms-1"><?= count($sectionCategories) ?></span>
        </h5>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSectionDomain(<?= (int) $section['id'] ?>)">
            <i class="bi bi-plus-circle me-1"></i>Add Domain
        </button>
    </div>
    <div class="card-body p-4">
        <?php if (empty($sectionCategories)): ?>
            <div class="text-center py-4">
                <i class="bi bi-diagram-3 fs-1 text-muted mb-3 d-block"></i>
                <h6 class="text-muted">No domains defined for this section yet</h6>
                <p class="text-muted mb-3">Add a domain above, or use one of the shared domains below as a template.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($sectionCategories as $category): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 border shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle p-3 me-3 d-flex align-items-center justify-content-center"
                                         style="width: 56px; height: 56px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); flex-shrink: 0;">
                                        <i class="bi bi-diagram-3 text-white fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1"><?= esc($category['name']) ?></h5>
                                        <small class="text-muted"><?= (int) ($category['field_count'] ?? 0) ?> Field(s)</small>
                                    </div>
                                </div>
                                <small class="text-muted d-block mb-3"><?= esc($category['description'] ?? 'No description') ?></small>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                            onclick="manageSectionDomainFields(<?= (int) $category['id'] ?>, '<?= esc($category['name'], 'js') ?>')">
                                        <i class="bi bi-gear me-1"></i> Manage Fields
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            onclick="editSectionDomain(<?= (int) $category['id'] ?>)">
                                        <i class="bi bi-pencil me-1"></i> Edit Domain
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                            onclick="deleteSectionDomain(<?= (int) $category['id'] ?>, '<?= esc($category['name'], 'js') ?>')">
                                        <i class="bi bi-trash me-1"></i> Deactivate Domain
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-globe me-2 text-success"></i>Shared Domains (all non-numerical sections)
            <span class="badge bg-secondary ms-1"><?= count($globalCategories) ?></span>
        </h5>
        <a href="<?= base_url('admin/settings') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-gear me-1"></i> Manage in Settings
        </a>
    </div>
    <div class="card-body p-4">
        <?php if (empty($globalCategories)): ?>
            <p class="text-muted mb-0">No shared domains added yet. SNED uses developmental domains with performance
                indicators instead of traditional subjects &mdash; add them from
                <a href="<?= base_url('admin/settings') ?>">Settings</a>.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($globalCategories as $category): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 border shadow-sm settings-domain-card"
                             role="button" tabindex="0"
                             title="Click to edit this shared domain"
                             onclick="editSectionDomain(<?= (int) $category['id'] ?>)"
                             onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); editSectionDomain(<?= (int) $category['id'] ?>); }">
                            <div class="card-body d-flex flex-column">
                                <h6 class="mb-1"><i class="bi bi-diagram-3 me-2 text-success"></i><?= esc($category['name']) ?></h6>
                                <small class="text-muted d-block mb-2"><?= (int) ($category['field_count'] ?? 0) ?> Field(s)</small>
                                <small class="text-muted flex-grow-1"><?= esc($category['description'] ?? 'No description') ?></small>
                                <button type="button" class="btn btn-outline-secondary btn-sm mt-3"
                                        onclick="event.stopPropagation(); editSectionDomain(<?= (int) $category['id'] ?>)">
                                    <i class="bi bi-pencil me-1"></i> Edit Domain
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const SNED_CSRF_NAME = '<?= csrf_token() ?>';
const SNED_CSRF_HASH = '<?= csrf_hash() ?>';

function snedPost(url, fields) {
  const body = new FormData();
  Object.entries(fields).forEach(([k, v]) => body.append(k, v));
  body.append(SNED_CSRF_NAME, SNED_CSRF_HASH);
  return fetch(url, { method: 'POST', body }).then(r => r.json());
}

function showSnedToast(message, type = 'success') {
  const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
  const toast = document.createElement('div');
  toast.className = `alert ${alertClass} position-fixed shadow`;
  toast.style.cssText = 'top: 80px; right: 20px; z-index: 2000; min-width: 280px;';
  toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}`;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 2500);
}

function reloadSnedPage() {
  setTimeout(() => window.location.reload(), 800);
}

// Every domain shown on this page (section-specific + shared), so the edit modal
// can look up the current name/description without inline JS arguments.
const SECTION_DOMAINS = <?= json_encode(array_map(static function ($c) {
    return [
        'id'          => (int) $c['id'],
        'name'        => (string) $c['name'],
        'description' => (string) ($c['description'] ?? ''),
        'field_count' => (int) ($c['field_count'] ?? 0),
    ];
}, array_merge($sectionCategories ?? [], $globalCategories ?? []))) ?>;

// Edit a domain's name and description. Works for both the section-specific
// domains above and the shared domains listed further down the page.
function editSectionDomain(categoryId) {
  const category = SECTION_DOMAINS.find(c => Number(c.id) === Number(categoryId));
  if (!category) {
    showSnedToast('Could not load that domain. Refresh the page and try again.', 'danger');
    return;
  }

  const existing = document.getElementById('editSectionDomainModal');
  if (existing) {
    // Dispose BEFORE removing so Bootstrap also clears its backdrop/body lock.
    const inst = bootstrap.Modal.getInstance(existing);
    if (inst) inst.dispose();
    existing.remove();
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
  }

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, ch => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]
  ));

  const html = `
  <div class="modal fade" id="editSectionDomainModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Developmental Domain</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="mb-3">
          <label class="form-label fw-semibold">Domain Name</label>
          <input type="text" class="form-control form-control-lg border-2" id="editSectionDomainName" maxlength="255" value="${escapeHtml(category.name)}" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Description</label>
          <textarea class="form-control border-2" id="editSectionDomainDesc" rows="4" placeholder="Brief description of this domain">${escapeHtml(category.description)}</textarea>
          <small class="text-muted">Shown on the domain card and to teachers when they encode grades. Leave it empty to show "No description".</small>
        </div>
        <div class="alert alert-light border mb-0">
          <small class="text-muted"><i class="bi bi-info-circle me-1"></i>The ${category.field_count} performance indicator(s) and every grade already recorded stay attached &mdash; only the name and description change.</small>
        </div>
      </div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="editSectionDomainSaveBtn"><i class="bi bi-check-circle me-2"></i>Save Changes</button>
      </div>
    </div></div>
  </div>`;

  document.body.insertAdjacentHTML('beforeend', html);
  const modal = new bootstrap.Modal(document.getElementById('editSectionDomainModal'), { backdrop: true, keyboard: true, focus: true });
  modal.show();
  setTimeout(() => document.getElementById('editSectionDomainName').focus(), 300);

  document.getElementById('editSectionDomainSaveBtn').addEventListener('click', function () {
    const nameEl = document.getElementById('editSectionDomainName');
    const name = nameEl.value.trim();
    const description = document.getElementById('editSectionDomainDesc').value.trim();

    if (!name) {
      showSnedToast('Please enter a domain name', 'danger');
      nameEl.focus();
      return;
    }

    this.disabled = true;
    snedPost('<?= base_url('admin/sned/categories/edit') ?>', {
      category_id: categoryId,
      name: name,
      description: description
    }).then(data => {
      this.disabled = false;
      if (data.success) {
        modal.hide();
        showSnedToast('Domain updated');
        reloadSnedPage();
      } else {
        showSnedToast(data.error || 'Failed to update domain', 'danger');
      }
    }).catch(() => {
      this.disabled = false;
      showSnedToast('An error occurred. Please try again.', 'danger');
    });
  });
}
</script>
<!-- SCRIPT-PART-2 -->

<script>
function addSectionDomain(sectionId) {
  const existing = document.getElementById('addSectionDomainModal');
  if (existing) existing.remove();

  const html = `
  <div class="modal fade" id="addSectionDomainModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Domain</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Domain Name</label>
          <input type="text" class="form-control" id="sectionDomainName" placeholder="e.g., Cognitive Domain" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description <span class="text-muted">(optional)</span></label>
          <textarea class="form-control" id="sectionDomainDesc" rows="3" placeholder="Brief description of this domain"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="sectionDomainSaveBtn">Add Domain</button>
      </div>
    </div></div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  const modal = new bootstrap.Modal(document.getElementById('addSectionDomainModal'), { backdrop: true, keyboard: true, focus: true });
  modal.show();

  document.getElementById('sectionDomainSaveBtn').addEventListener('click', function () {
    const name = document.getElementById('sectionDomainName').value.trim();
    const desc = document.getElementById('sectionDomainDesc').value.trim();
    if (!name) { alert('Please enter a domain name'); return; }
    const btn = this;
    btn.disabled = true;
    snedPost('<?= base_url('admin/sned/categories/add') ?>', {
      name: name, description: desc, section_id: sectionId
    }).then(data => {
      if (data.success) {
        bootstrap.Modal.getInstance(document.getElementById('addSectionDomainModal')).hide();
        showSnedToast('Domain added successfully!');
        reloadSnedPage();
      } else {
        btn.disabled = false;
        alert(data.error || 'Failed to add domain');
      }
    }).catch(() => { btn.disabled = false; alert('An error occurred. Please try again.'); });
  });
}

function deleteSectionDomain(categoryId, categoryName) {
  const existing = document.getElementById('deleteSectionDomainModal');
  if (existing) existing.remove();

  const html = `
  <div class="modal fade" id="deleteSectionDomainModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-question-circle me-2"></i>Confirm Action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning border-0 mb-0">
          <p class="text-center mb-0">Deactivate domain <strong>${categoryName}</strong>?
            Existing grades will be preserved.</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="deleteSectionDomainBtn">Deactivate</button>
      </div>
    </div></div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  const modal = new bootstrap.Modal(document.getElementById('deleteSectionDomainModal'), { backdrop: 'static', keyboard: true, focus: true });
  modal.show();

  document.getElementById('deleteSectionDomainBtn').addEventListener('click', function () {
    this.disabled = true;
    snedPost('<?= base_url('admin/sned/categories/delete/') ?>' + categoryId, {})
      .then(data => {
        if (data.success) {
          bootstrap.Modal.getInstance(document.getElementById('deleteSectionDomainModal')).hide();
          showSnedToast('Domain deactivated');
          reloadSnedPage();
        } else {
          this.disabled = false;
          alert(data.error || 'Failed to deactivate domain');
        }
      })
      .catch(() => { this.disabled = false; alert('An error occurred. Please try again.'); });
  });
}
</script>

<script>
function manageSectionDomainFields(categoryId, categoryName) {
  const existing = document.getElementById('sectionDomainFieldsModal');
  if (existing) existing.remove();

  const html = `
  <div class="modal fade" id="sectionDomainFieldsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-list-check me-2"></i>${categoryName} &mdash; Fields</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="sectionDomainFieldsList" class="text-center">
          <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
          <p class="mt-2 mb-0">Loading fields...</p>
        </div>
        <div class="input-group mt-3">
          <input type="text" class="form-control" id="sectionDomainFieldName" placeholder="New indicator / field name">
          <button type="button" class="btn btn-success" id="sectionDomainFieldAddBtn"><i class="bi bi-plus-circle me-1"></i>Add</button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div></div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  const modal = new bootstrap.Modal(document.getElementById('sectionDomainFieldsModal'), { backdrop: true, keyboard: true, focus: true });
  modal.show();

  const renderFields = (fields) => {
    const listEl = document.getElementById('sectionDomainFieldsList');
    if (!listEl) return;
    if (!fields.length) {
      listEl.className = 'text-center text-muted py-3';
      listEl.innerHTML = '<i class="bi bi-inbox fs-3 d-block mb-2"></i>No fields yet. Add the first indicator below.';
      return;
    }
    listEl.className = '';
    listEl.innerHTML = `
      <table class="table table-sm table-hover mb-0">
        <thead><tr><th style="width: 40px;">#</th><th>Field / Indicator</th><th style="width: 70px;"></th></tr></thead>
        <tbody>
          ${fields.map((f, i) => `
            <tr>
              <td>${i + 1}</td>
              <td>${f.field_name}</td>
              <td>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-field-id="${f.id}">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>`).join('')}
        </tbody>
      </table>`;
    listEl.querySelectorAll('button[data-field-id]').forEach(btn => {
      btn.addEventListener('click', function () {
        snedPost('<?= base_url('admin/sned/fields/delete/') ?>' + this.dataset.fieldId, {})
          .then(data => {
            if (data.success) { loadFields(); showSnedToast('Field deactivated'); }
            else { alert(data.error || 'Failed to remove field'); }
          });
      });
    });
  };

  const loadFields = () => {
    fetch('<?= base_url('admin/sned/fields/') ?>' + categoryId)
      .then(r => r.json())
      .then(data => renderFields(data.success && data.fields ? data.fields : []))
      .catch(() => {
        const el = document.getElementById('sectionDomainFieldsList');
        if (el) el.innerHTML = '<div class="alert alert-danger mb-0">Error loading fields</div>';
      });
  };
  loadFields();

  document.getElementById('sectionDomainFieldAddBtn').addEventListener('click', function () {
    const input = document.getElementById('sectionDomainFieldName');
    const fieldName = input.value.trim();
    if (!fieldName) { alert('Please enter a field name'); return; }
    this.disabled = true;
    snedPost('<?= base_url('admin/sned/fields/add') ?>', {
      category_id: categoryId, field_name: fieldName
    }).then(data => {
      this.disabled = false;
      if (data.success) {
        input.value = '';
        loadFields();
        showSnedToast('Field added');
      } else {
        alert(data.error || 'Failed to add field');
      }
    }).catch(() => { this.disabled = false; alert('An error occurred. Please try again.'); });
  });
}
</script>

<style>
/* Domain cards on this page are clickable: clicking one opens its edit modal,
   so they need a pointer cursor and a visible hover/focus state. */
.settings-domain-card {
    cursor: pointer;
    transition: box-shadow .15s ease-in-out, transform .15s ease-in-out;
}
.settings-domain-card:hover,
.settings-domain-card:focus-visible {
    box-shadow: 0 .5rem 1rem rgba(13, 110, 253, .25);
    transform: translateY(-2px);
    outline: none;
    border-color: #0d6efd !important;
}
</style>

<?= $this->endSection() ?>

