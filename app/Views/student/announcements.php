<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * Student announcements list: filter pills, search and pagination.
 *
 * Every value arrives from the controller with a fallback, so the view still
 * renders if it is included without them. $linkFor is the one place that builds
 * a link: it carries the search term across a filter or page change, so
 * paginating after searching does not silently drop the search.
 */
$filter      = $filter ?? 'all';
$search      = $search ?? '';
$pillCounts  = $pillCounts ?? ['all' => 0, 'unread' => 0, 'read' => 0];
$page        = $page ?? 1;
$perPage     = $perPage ?? 8;
$total       = $total ?? 0;
$totalPages  = $totalPages ?? 1;
$from        = $from ?? 0;
$to          = $to ?? 0;

$baseParams = [];
if ($search !== '') { $baseParams['q'] = $search; }

$linkFor = static function (string $f, int $p) use ($baseParams): string {
  return base_url('student/announcements') . '?' . http_build_query(array_merge($baseParams, ['filter' => $f, 'page' => $p]));
};

$kindMeta = static function (string $target): array {
  $t = strtolower((string) $target);
  if (str_starts_with($t, 'grade_')) return ['label' => 'Grade ' . substr($target, 6), 'icon' => 'bi-mortarboard', 'badge' => 'bg-info'];
  if (str_starts_with($t, 'section_')) return ['label' => 'Section', 'icon' => 'bi-people', 'badge' => 'bg-info'];
  if ($t === 'all') return ['label' => 'School-wide', 'icon' => 'bi-megaphone', 'badge' => 'bg-primary'];
  if ($t === 'student') return ['label' => 'Students', 'icon' => 'bi-backpack', 'badge' => 'bg-success'];
  return ['label' => ucfirst($target), 'icon' => 'bi-bell', 'badge' => 'bg-secondary'];
};

$pills = [
  'all' => ['All', 'bi-collection'],
  'unread' => ['Unread', 'bi-envelope-exclamation'],
  'read' => ['Read', 'bi-check2-all'],
];
?>

<style>
.student-announce-filters .btn { border-radius: 999px; font-size: 0.82rem; padding: 0.3rem 0.8rem; }
.student-announce-search { max-width: 320px; }
.student-announce-search .form-control { border-radius: 999px 0 0 999px; font-size: 0.85rem; }
.student-announce-search .btn { border-radius: 0 999px 999px 0; }
.announcement-item { border: 1px solid #e9eef5; border-radius: 12px; transition: box-shadow 0.15s ease, transform 0.15s ease, background-color 0.15s ease; }
.announcement-item:hover { background-color: #f8fafc; box-shadow: 0 4px 14px rgba(15, 42, 87, 0.08); transform: translateY(-1px); }
.announcement-item:last-child { margin-bottom: 0 !important; }
.announcement-kind { width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.announcement-kind--new { background: rgba(13, 110, 253, 0.12); color: #0d6efd; }
.announcement-kind--read { background: #f1f5f9; color: #64748b; }
.unread-announcement { background-color: #eef5ff; border-color: rgba(13, 110, 253, 0.35); }
.sender-chip { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.76rem; color: #475569; }
.sender-chip i { color: #94a3b8; }
.sent-chip { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.76rem; color: #64748b; white-space: nowrap; }
.student-announce-pager .page-link { border-radius: 8px; font-size: 0.82rem; }
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
  <div>
    <h1 class="h4 fw-bold text-primary mb-1"><i class="bi bi-megaphone me-2"></i>Announcements</h1>
    <p class="text-muted small mb-0">School updates for you &mdash; newest first. <?= (int) ($pillCounts['unread'] ?? 0) ?> unread.</p>
  </div>
  <form method="get" action="<?= base_url('student/announcements') ?>" class="student-announce-search w-100" role="search">
    <input type="hidden" name="filter" value="<?= esc($filter) ?>">
    <div class="input-group input-group-sm">
      <input type="search" name="q" class="form-control" placeholder="Search title or message&hellip;" value="<?= esc($search) ?>" aria-label="Search announcements">
      <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
    </div>
  </form>
</div>

<?= $this->include('partials/mascot_announce_head') ?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3 student-announce-filters">
  <?php foreach ($pills as $pillKey => $pill): ?>
    <a href="<?= esc($linkFor($pillKey, 1)) ?>"
       class="btn btn-sm <?= $filter === $pillKey ? 'btn-primary' : 'btn-outline-secondary' ?>">
      <i class="bi <?= esc($pill[1]) ?> me-1"></i><?= esc($pill[0]) ?>
      <span class="badge bg-light text-dark ms-1"><?= (int) ($pillCounts[$pillKey] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!empty($announcements)): ?>
  <div class="d-flex flex-column gap-2">
    <?php foreach ($announcements as $announcement): ?>
      <?php
        $isUnread = empty($announcement['is_read']);
        $kind     = $kindMeta((string) ($announcement['target'] ?? 'all'));
      ?>
      <a href="<?= base_url('student/announcements/view/' . $announcement['id']) ?>"
         class="announcement-item p-3 text-decoration-none text-dark d-block <?= $isUnread ? 'unread-announcement' : '' ?>">
        <div class="d-flex gap-3">
          <span class="announcement-kind <?= $isUnread ? 'announcement-kind--new' : 'announcement-kind--read' ?>" aria-hidden="true">
            <i class="bi <?= esc($kind['icon']) ?>"></i>
          </span>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
              <h6 class="mb-0 text-truncate <?= $isUnread ? 'fw-bold' : '' ?>"><?= esc($announcement['title']) ?></h6>
              <span class="sent-chip">
                <i class="bi bi-clock"></i><?= date('M j, Y', strtotime((string) $announcement['created_at'])) ?>
              </span>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <span class="badge <?= esc($kind['badge']) ?>"><?= esc($kind['label']) ?></span>
              <?php if ($isUnread): ?>
                <?php if (mascot_exists('sticker-new')): ?>
                  <?= mascot_sticker_for('new', ['class' => 'mascot-chip mascot-chip--sm', 'alt' => 'New']) ?>
                <?php else: ?>
                  <span class="badge bg-success">New</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <p class="text-muted small mb-1">
              <?= strip_tags(substr((string) $announcement['body'], 0, 200)) ?><?= strlen(strip_tags((string) $announcement['body'])) > 200 ? '...' : '' ?>
            </p>
            <?php if (!empty($announcement['sender_name'])): ?>
              <span class="sender-chip">
                <i class="bi bi-person"></i><?= esc($announcement['sender_name']) ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="mt-3 student-announce-pager" aria-label="Announcements pages">
      <ul class="pagination pagination-sm justify-content-center mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= esc($linkFor($filter, max(1, $page - 1))) ?>" aria-label="Previous"><i class="bi bi-chevron-left"></i></a>
        </li>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= esc($linkFor($filter, $p)) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= esc($linkFor($filter, min($totalPages, $page + 1))) ?>" aria-label="Next"><i class="bi bi-chevron-right"></i></a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
<?php else: ?>
  <div class="text-center py-4">
    <i class="bi bi-megaphone fs-1 text-muted mb-3"></i>
    <h5 class="text-muted"><?= $search !== '' ? 'No Matches' : 'No Announcements' ?></h5>
    <p class="text-muted mb-0">
      <?= $search !== '' ? 'Nothing matched that search.' : 'No announcements have been posted yet.' ?>
    </p>
  </div>
<?php endif; ?>

<?= $this->endSection() ?> 