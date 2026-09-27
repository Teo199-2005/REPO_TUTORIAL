<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style>
.blue-divider {
  height: 1px;
  background: var(--hairline-strong);

.form-floating textarea {
  min-height: 120px;
}

.preview-card {
  background: #f8f9fa;
  border: 1px dashed #dee2e6;
  border-radius: 8px;
}

.original-content {
  background: #e3f2fd;
  padding: 1rem;
  border-radius: 4px;
}
</style>

<!-- Header Section -->
<div class="dashboard-header mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 fw-bold text-primary mb-1">Edit Announcement</h1>
      <p class="text-muted mb-0 small">Modify and update announcement content</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= base_url('admin/announcements/show/' . $announcement['id']) ?>" class="btn btn-outline-info btn-sm">
        <i class="bi bi-eye me-2"></i>View
      </a>
      <a href="<?= base_url('admin/announcements') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>Back to List
      </a>
    </div>
  </div>
  
  <!-- Blue Divider -->
  <div class="blue-divider"></div>
</div>

<?php if ($errors = session('errors')): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Original Content Reference -->
<div class="card bg-white border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">
      <i class="bi bi-info-circle me-2"></i>Original Announcement
    </h5>
    <button type="button" class="btn btn-sm btn-light" onclick="toggleEditMode()">
      <i class="bi bi-pencil me-1"></i><span id="editBtnText">Edit</span>
    </button>
  </div>
  <div class="card-body">
    <div class="original-content">
        <div id="viewMode">
            <h6 class="fw-bold text-primary"><?= esc($announcement['title']) ?></h6>
            <div class="mb-2"><?= esc($announcement['body']) ?></div>
        <div class="d-flex gap-2">
          <span class="badge bg-secondary"><?= esc($announcement['target_roles']) ?></span>
          <span class="badge bg-success">Published</span>
        </div>
      </div>
      <div id="editMode" style="display: none;">
        <div contenteditable="true" id="editableContent" class="border rounded p-2 mb-2" style="min-height: 200px;"><?= esc($announcement['body']) ?></div>
        <button type="button" class="btn btn-sm btn-success" onclick="applyChanges()">
          <i class="bi bi-check me-1"></i>Apply Changes
        </button>
      </div>
    </div>
  </div>
</div>



<script>
function toggleEditMode() {
  const viewMode = document.getElementById('viewMode');
  const editMode = document.getElementById('editMode');
  const btnText = document.getElementById('editBtnText');
  
  if (editMode.style.display === 'none') {
    viewMode.style.display = 'none';
    editMode.style.display = 'block';
    btnText.textContent = 'Cancel';
  } else {
    viewMode.style.display = 'block';
    editMode.style.display = 'none';
    btnText.textContent = 'Edit';
  }
}

function applyChanges() {
  const editableContent = document.getElementById('editableContent');
  const newBody = editableContent.innerHTML;
  const announcementId = <?= $announcement['id'] ?>;
  
  fetch('<?= base_url('admin/announcements/updateContent/') ?>' + announcementId, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: 'body=' + encodeURIComponent(newBody)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      document.getElementById('viewMode').querySelector('.mb-2').innerHTML = newBody;
      alert('Changes saved successfully!');
      toggleEditMode();
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    alert('Failed to save changes.');
  });
}
</script>

<?= $this->endSection() ?>
