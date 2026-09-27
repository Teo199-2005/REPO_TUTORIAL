<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>
<?php $sentAt = $announcement['sent_at'] ?? ($announcement['published_at'] ?? ($announcement['created_at'] ?? null)); ?>

<style>
.student-announce-detail-meta .meta-chip { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.82rem; color: #475569; background: #f8fafc; border: 1px solid #e9eef5; border-radius: 999px; padding: 0.25rem 0.7rem; }
.student-announce-detail-meta .meta-chip i { color: #64748b; }
.announcement-content { line-height: 1.7; font-size: 0.95rem; color: #1e293b; }
.announcement-content p { margin-bottom: 1rem; }
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-2 mb-3">
  <div>
    <h1 class="h4 fw-bold text-primary mb-2"><?= esc($announcement['title']) ?></h1>
    <div class="d-flex flex-wrap align-items-center gap-2 student-announce-detail-meta">
      <span class="meta-chip"><i class="bi bi-person-circle"></i><?= esc($announcement['sender_name'] ?? 'School Administration') ?></span>
      <span class="meta-chip"><i class="bi bi-send"></i>Sent <?= $sentAt ? esc(date('F j, Y \a\t g:i A', strtotime($sentAt))) : '—' ?></span>
    </div>
  </div>
  <a href="<?= base_url('student/announcements') ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0">
    <i class="bi bi-arrow-left me-1"></i>Back to Announcements
  </a>
</div>

<div class="card border-0 shadow-sm rounded-3">
  <div class="card-body p-3 p-md-4">
    <div class="announcement-content">
      <?= $announcement['body'] ?>
    </div>
  </div>
</div>

<?= $this->endSection() ?>