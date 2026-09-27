<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style>
.blue-divider {
  height: 1px;
  background: var(--hairline-strong);

.announcement-content {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1.5rem;
}

.meta-info {
  background: #ffffff;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  padding: 1rem;
}
</style>

<!-- Header Section -->
<div class="dashboard-header mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 fw-bold text-primary mb-1">View Announcement</h1>
      <p class="text-muted mb-0 small">Announcement details and information</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= base_url('admin/announcements/edit/' . $announcement['id']) ?>" class="btn btn-warning btn-sm">
        <i class="bi bi-pencil me-2"></i>Edit
      </a>

      <a href="<?= base_url('admin/announcements') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>Back to List
      </a>
    </div>
  </div>
  
  <!-- Blue Divider -->
  <div class="blue-divider"></div>
</div>

<?php if ($success = session('success')): ?>
  <div class="alert alert-success"><?= esc($success) ?></div>
<?php endif; ?>
<?php if ($error = session('error')): ?>
  <div class="alert alert-danger"><?= esc($error) ?></div>
<?php endif; ?>

<div class="row">
  <!-- Main Content -->
  <div class="col-lg-8">
    <div class="card bg-white border-0 shadow-sm rounded-3">
      <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">
          <i class="bi bi-megaphone me-2"></i>Announcement Content
        </h5>
      </div>
      <div class="card-body p-4">
        <!-- Title -->
        <h2 class="text-primary mb-3"><?= esc($announcement['title']) ?></h2>
        
        <?php if (strpos($announcement['title'], 'Analytics Report') !== false): ?>
          <!-- Hide any error messages that might appear above the content -->
          <style>
            .alert-danger, .text-danger { display: none !important; }
          </style>
        <?php endif; ?>
        
        <!-- Status Badge -->
        <div class="mb-3">
          <span class="badge bg-success fs-6">
            <i class="bi bi-check-circle me-1"></i>Published
          </span>
          
          <span class="badge bg-secondary fs-6 ms-2">
            <i class="bi bi-people me-1"></i><?= esc($announcement['target_roles']) ?>
          </span>
        </div>
        
        <!-- Content -->
        <div class="announcement-content">
          <div class="fs-5 lh-base">
            <?php if (strpos($announcement['title'], 'Analytics Report') !== false): ?>
              <!-- Render HTML content for analytics reports -->
              <?php 
              $content = $announcement['body'];
              // Remove various error messages and alerts
              $content = str_replace('Teacher record not found', '', $content);
              $content = preg_replace('/Teacher record not found/i', '', $content);
              $content = preg_replace('/<div[^>]*alert[^>]*>.*?<\/div>/s', '', $content);
              $content = preg_replace('/<p[^>]*text-danger[^>]*>.*?<\/p>/s', '', $content);
              $content = preg_replace('/<span[^>]*text-danger[^>]*>.*?<\/span>/s', '', $content);
              $content = trim($content);
              echo $content;
              ?>
            <?php else: ?>
              <!-- Escape content for regular announcements -->
              <?= nl2br(esc($announcement['body'])) ?>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Actions -->
        <div class="mt-4 d-flex gap-2">
          <?php if (strpos($announcement['title'], 'Analytics Report') !== false): ?>
            <a href="<?= base_url('admin/announcements/download-pdf/' . $announcement['id']) ?>" class="btn btn-success">
              <i class="bi bi-file-earmark-pdf me-2"></i>Export PDF
            </a>
          <?php endif; ?>
          <a href="<?= base_url('admin/announcements/edit/' . $announcement['id']) ?>" class="btn btn-warning">
            <i class="bi bi-pencil me-2"></i>Edit Announcement
          </a>
          <button onclick="duplicateAnnouncement()" class="btn btn-outline-info">
            <i class="bi bi-files me-2"></i>Duplicate
          </button>
          <button onclick="deleteAnnouncement(<?= $announcement['id'] ?>, '<?= esc($announcement['title']) ?>')" 
                  class="btn btn-outline-danger">
            <i class="bi bi-trash me-2"></i>Delete
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Sidebar Info -->
  <div class="col-lg-4">
    <div class="card bg-white border-0 shadow-sm rounded-3">
      <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0">
          <i class="bi bi-info-circle me-2"></i>Announcement Details
        </h5>
      </div>
      <div class="card-body">
        <div class="meta-info mb-3">
          <h6 class="fw-semibold text-primary mb-2">Publication Info</h6>
          <div class="row g-2">
            <div class="col-6">
              <small class="text-muted d-block">Status</small>
              <span class="fw-medium">Published</span>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">Target</small>
              <span class="fw-medium"><?= esc($announcement['target_roles']) ?></span>
            </div>
            <div class="col-12 mt-2">
              <small class="text-muted d-block">Published</small>
              <span class="fw-medium"><?= date('M j, Y g:i A', strtotime($announcement['published_at'])) ?></span>
            </div>
          </div>
        </div>
        
        <div class="meta-info mb-3">
          <h6 class="fw-semibold text-primary mb-2">Creation Info</h6>
          <div class="row g-2">
            <div class="col-12">
              <small class="text-muted d-block">Created</small>
              <span class="fw-medium"><?= date('M j, Y g:i A', strtotime($announcement['created_at'])) ?></span>
            </div>
            <?php if ($announcement['updated_at'] && $announcement['updated_at'] !== $announcement['created_at']): ?>
              <div class="col-12">
                <small class="text-muted d-block">Last Updated</small>
                <span class="fw-medium"><?= date('M j, Y g:i A', strtotime($announcement['updated_at'])) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="meta-info">
          <h6 class="fw-semibold text-primary mb-2">Content Stats</h6>
          <div class="row g-2">
            <div class="col-6">
              <small class="text-muted d-block">Title Length</small>
              <span class="fw-medium"><?= strlen($announcement['title']) ?> chars</span>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">Content Length</small>
              <span class="fw-medium"><?= strlen($announcement['body']) ?> chars</span>
            </div>
            <div class="col-12 mt-2">
              <small class="text-muted d-block">Word Count</small>
              <span class="fw-medium"><?= str_word_count($announcement['body']) ?> words</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card bg-white border-0 shadow-sm rounded-3 mt-3">
      <div class="card-header bg-secondary text-white">
        <h5 class="card-title mb-0">
          <i class="bi bi-lightning me-2"></i>Quick Actions
        </h5>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <?php if (strpos($announcement['title'], 'Analytics Report') !== false): ?>
            <a href="<?= base_url('admin/announcements/download-pdf/' . $announcement['id']) ?>" class="btn btn-success">
              <i class="bi bi-file-earmark-pdf me-2"></i>Export PDF
            </a>
          <?php endif; ?>
          <a href="<?= base_url('admin/announcements/edit/' . $announcement['id']) ?>"
             class="btn btn-outline-warning">
            <i class="bi bi-pencil me-2"></i>Edit Content
          </a>
          <button onclick="duplicateAnnouncement()" class="btn btn-outline-info">
            <i class="bi bi-files me-2"></i>Create Copy
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>


// Delete announcement
function deleteAnnouncement(id, title) {
  if (confirm(`Are you sure you want to delete the announcement "${title}"? This action cannot be undone.`)) {
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= base_url('admin/announcements/delete/') ?>' + id;
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '<?= csrf_token() ?>';
    csrfInput.value = '<?= csrf_hash() ?>';
    form.appendChild(csrfInput);
    
    document.body.appendChild(form);
    form.submit();
  }
}

// Duplicate announcement
function duplicateAnnouncement() {
  const title = '<?= esc($announcement['title']) ?>';
  const body = `<?= esc($announcement['body']) ?>`;
  const target = '<?= esc($announcement['target_roles']) ?>';
  
  // Create URL with pre-filled data
  const params = new URLSearchParams({
    title: 'Copy of ' + title,
    body: body,
    target_roles: target
  });
  
  window.location.href = '<?= base_url('admin/announcements/create') ?>?' + params.toString();
}
</script>

<?= $this->endSection() ?>
