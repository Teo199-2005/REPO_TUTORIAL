<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="h3">Announcements & Notifications</h1>
    <p class="text-muted mb-0">View announcements & notifications from admin and post to students</p>
  </div>
  <a href="<?= base_url('teacher/dashboard') ?>" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
  </a>
</div>

<?php if ($errors = session('errors')): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>
<?php if ($success = session('success')): ?>
  <div class="alert alert-success"><?= esc($success) ?></div>
<?php endif; ?>

<!-- Grade Recommendations Section -->
<div class="card mb-4 border-warning">
  <div class="card-header bg-warning text-dark">
    <h5 class="card-title mb-0">
      <i class="bi bi-clipboard-check me-2"></i>Grade Recommendations from Subject Teachers
      <?php if (isset($unreadGradeRecs) && $unreadGradeRecs > 0): ?>
        <span class="badge bg-danger ms-2"><?= $unreadGradeRecs ?> New</span>
      <?php endif; ?>
    </h5>
  </div>
  <div class="card-body">
    <?php if (!empty($gradeRecommendations)): ?>
      <div class="list-group">
        <?php foreach ($gradeRecommendations as $rec): ?>
          <div class="list-group-item <?= !$rec['is_read'] ? 'list-group-item-warning' : '' ?>">
            <div class="d-flex justify-content-between align-items-start">
              <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <?php if (!$rec['is_read']): ?>
                    <?php if (mascot_exists('sticker-new')): ?>
                      <?= mascot_sticker_for('new', ['class' => 'mascot-chip mascot-chip--sm', 'alt' => 'New']) ?>
                    <?php else: ?>
                      <span class="badge bg-danger">New</span>
                    <?php endif; ?>
                  <?php endif; ?>
                  <h6 class="mb-0 <?= !$rec['is_read'] ? 'fw-bold' : '' ?>"><?= esc($rec['title']) ?></h6>
                </div>
                <p class="mb-2 text-muted"><?= esc($rec['message']) ?></p>
                <small class="text-muted">
                  <i class="bi bi-clock me-1"></i><?= date('M j, Y g:i A', strtotime($rec['created_at'])) ?>
                </small>
              </div>
              <div>
                <?php if (!$rec['is_read']): ?>
                  <button class="btn btn-sm btn-outline-primary mark-grade-rec-read" data-id="<?= $rec['id'] ?>">
                    <i class="bi bi-check2"></i> Mark as Read
                  </button>
                <?php else: ?>
                  <span class="badge bg-success"><i class="bi bi-check-circle"></i> Read</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-center py-4">
        <i class="bi bi-clipboard-check fs-1 text-muted mb-3"></i>
        <h6 class="text-muted">No Grade Recommendations</h6>
        <p class="text-muted mb-0 small">Subject teachers' grade recommendations will appear here.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Post New Announcement -->
<div class="card mb-4">
  <div class="card-header">
    <h5 class="card-title mb-0">
      <i class="bi bi-plus-circle me-2"></i>Post New Announcement
    </h5>
  </div>
  <div class="card-body">
    <form method="post" action="<?= base_url('teacher/announcements') ?>">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Body</label>
        <textarea name="body" rows="4" class="form-control" required></textarea>
      </div>
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-send me-2"></i>Post to Students
      </button>
    </form>
  </div>
</div>

<!-- Announcements List -->
<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">
      <i class="bi bi-megaphone me-2"></i>Recent Announcements
    </h5>
  </div>
  <div class="card-body">
    <?php if (!empty($announcements)): ?>
      <?php foreach ($announcements as $announcement): ?>
        <a href="<?= base_url('teacher/announcements/view/' . $announcement['id']) ?>" class="announcement-item border-bottom pb-3 mb-3 text-decoration-none text-dark d-block <?= !$announcement['is_read'] ? 'unread-announcement' : '' ?>">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="d-flex align-items-center gap-2">
              <?php if (!$announcement['is_read']): ?>
                <?php if (mascot_exists('sticker-new')): ?>
                  <?= mascot_sticker_for('new', ['class' => 'mascot-chip mascot-chip--sm', 'alt' => 'New']) ?>
                <?php else: ?>
                  <span class="badge bg-danger">New</span>
                <?php endif; ?>
              <?php endif; ?>
              <h6 class="mb-1 <?= !$announcement['is_read'] ? 'fw-bold' : '' ?>"><?= esc($announcement['title']) ?></h6>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-<?= $announcement['target_roles'] === 'admin' ? 'primary' : ($announcement['target_roles'] === 'all' ? 'success' : 'info') ?>">
                <?= ucfirst(esc($announcement['target_roles'])) ?>
              </span>
              <small class="text-muted">
                <?= date('M j, Y', strtotime($announcement['created_at'])) ?>
              </small>
            </div>
          </div>
          <p class="text-muted mb-0">
            <?= strip_tags(substr($announcement['body'], 0, 200)) ?><?= strlen(strip_tags($announcement['body'])) > 200 ? '...' : '' ?>
          </p>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="text-center py-4">
        <i class="bi bi-megaphone fs-1 text-muted mb-3"></i>
        <h5 class="text-muted">No Announcements</h5>
        <p class="text-muted mb-0">No announcements have been posted yet.</p>
      </div>
    <?php endif; ?>
  </div>
</div>



<style>
.announcement-item:last-child {
  border-bottom: none !important;
  margin-bottom: 0 !important;
  padding-bottom: 0 !important;
}
.announcement-item:hover {
  background-color: #f8f9fa;
  border-radius: 8px;
  padding: 12px;
  margin: -12px;
  margin-bottom: 12px;
}
.unread-announcement {
  background-color: #e7f3ff;
  padding-left: 12px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mark grade recommendation as read
    document.querySelectorAll('.mark-grade-rec-read').forEach(button => {
        button.addEventListener('click', function() {
            const notificationId = this.dataset.id;
            
            fetch('<?= base_url('teacher/announcements/mark-grade-rec-read') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `notification_id=${notificationId}&<?= csrf_token() ?>=<?= csrf_hash() ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to mark as read');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        });
    });
});
</script>



<?= $this->endSection() ?>



