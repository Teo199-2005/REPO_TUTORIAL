<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/page_header', ['pageHeader' => [
  'icon'     => 'bi-stars',
  'title'    => 'Platform feedback',
  'subtitle' => 'What students and teachers said about the portal',
  'actions'  => '<a class="btn btn-outline-secondary" href="' . base_url('admin/dashboard') . '">'
    . '<i class="bi bi-arrow-left"></i> Back to dashboard</a>',
]]) ?>

<?php if (! empty($setupRequired)): ?>
  <div class="alert alert-warning border-0 shadow-sm mb-4" role="alert">
    <strong>Database setup needed.</strong> The <code>platform_ratings</code> table is missing. Run migrations on the server:
    <code class="d-block mt-2">php spark migrate</code>
  </div>
<?php elseif (empty($hasTermColumns)): ?>
  <div class="alert alert-info border-0 shadow-sm mb-4" role="alert">
    <strong>Update available.</strong> Run <code>php spark migrate</code> to add school year and term columns so ratings are tracked per term.
  </div>
<?php endif; ?>

<div style="display: flex; flex-wrap: nowrap; gap: 1rem; margin-bottom: 1rem; overflow-x: auto;">
  <div style="flex: 1 0 0; min-width: 0;">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small text-uppercase fw-semibold">Average rating</div>
        <div class="fs-5 fw-bold text-primary"><?= $avgRating !== null ? esc((string) $avgRating) . ' / 5' : '—' ?></div>
        <div class="small text-muted"><?= (int) $totalRatings ?> response<?= (int) $totalRatings === 1 ? '' : 's' ?></div>
      </div>
    </div>
  </div>
  <div style="flex: 1 0 0; min-width: 0;">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small text-uppercase fw-semibold">Students</div>
        <div class="fs-5 fw-bold"><?= (int) $ratedStudents ?> / <?= (int) $eligibleStudents ?></div>
        <div class="small text-muted"><?= $pctStudents !== null ? esc((string) $pctStudents) . '% responded' : 'No eligible students' ?></div>
      </div>
    </div>
  </div>
  <div style="flex: 1 0 0; min-width: 0;">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small text-uppercase fw-semibold">Teachers</div>
        <div class="fs-5 fw-bold"><?= (int) $ratedTeachers ?> / <?= (int) $eligibleTeachers ?></div>
        <div class="small text-muted"><?= $pctTeachers !== null ? esc((string) $pctTeachers) . '% responded' : 'No eligible teachers' ?></div>
      </div>
    </div>
  </div>
  <div style="flex: 1 0 0; min-width: 0;">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-3">
        <div class="text-muted small text-uppercase fw-semibold">By stars</div>
        <div class="small">
          <?php for ($s = 5; $s >= 1; $s--): ?>
            <div class="d-flex justify-content-between"><span><?= $s ?> ★</span><strong><?= (int) ($breakdown[$s] ?? 0) ?></strong></div>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
    <span class="fw-semibold">All responses</span>
    <form method="get" action="<?= base_url('admin/platform-ratings') ?>"
          class="admin-filter-form d-flex align-items-center gap-2">
      <label class="small text-muted mb-0" for="roleFilter">
        <i class="bi bi-people me-1" aria-hidden="true"></i>Who gave feedback
      </label>
      <select name="role" id="roleFilter" class="form-select form-select-sm" style="width: auto;">
        <option value="" <?= $roleFilter === null ? 'selected' : '' ?>>Everyone</option>
        <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Students only</option>
        <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>Teachers only</option>
      </select>
      <?php if ($roleFilter !== null && $roleFilter !== ''): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('admin/platform-ratings') ?>"
           title="Clear this filter"><i class="bi bi-arrow-counterclockwise"></i><span class="visually-hidden">Reset</span></a>
      <?php endif; ?>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 admin-table" data-js-paged="1">
      <thead>
        <tr>
          <th>Role</th>
          <th>Name</th>
          <th>Account email</th>
          <th>S.Y. / Term</th>
          <th>Rating</th>
          <th>Comment</th>
          <th>Updated</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($responses === []): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No ratings yet.</td></tr>
        <?php else: ?>
          <?php foreach ($responses as $r): ?>
            <?php
              $stars = (int) ($r['rating'] ?? 0);
              $stars = max(1, min(5, $stars));
            ?>
            <tr>
              <td><span class="badge bg-<?= $r['responder_role'] === 'teacher' ? 'info' : 'secondary' ?>"><?= esc($r['responder_role']) ?></span></td>
              <td><?= esc($r['display_name'] ?? '') ?></td>
              <td class="small"><?= esc((string) ($r['user_email'] ?? '')) ?></td>
              <td class="small text-nowrap">
                <?php if (! empty($hasTermColumns) && (string) ($r['school_year'] ?? '') !== ''): ?>
                  <?= esc((string) $r['school_year']) ?>
                  <?php if (! empty($r['term'])): ?>
                    <span class="text-muted">· Term <?= (int) $r['term'] ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class="bi <?= $i <= $stars ? 'bi-star-fill text-warning' : 'bi-star text-secondary' ?>"></i>
                <?php endfor; ?>
                <span class="small text-muted ms-1">(<?= $stars ?>)</span>
              </td>
              <td class="small" style="max-width: 280px;">
                <?php $c = trim((string) ($r['comment'] ?? '')); ?>
                <?php if ($c !== ''): ?><?= esc($c) ?><?php else: ?><span class="text-muted">—</span><?php endif; ?>
              </td>
              <td class="small text-nowrap">
                <?php
                  $updatedAt = $r['updated_at'] ?? $r['created_at'] ?? null;
                  $updatedTs = $updatedAt ? strtotime((string) $updatedAt) : false;
                ?>
                <?php if ($updatedTs): ?>
                  <?= esc(date('M j, Y g:i A', $updatedTs)) ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
