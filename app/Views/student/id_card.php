<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style>
.blue-divider {
  height: 1px;
  background: var(--hairline-strong);

.sid-note {
  background: #eef4ff;
  border: 1px solid #cfe0ff;
  border-radius: 12px;
  padding: 1rem 1.25rem;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

<?= view('partials/id_card_style') ?>

/* Printing from the dashboard: drop the chrome so the sheet carries only the
   two card faces. The shared @media print rules in partials/id_card_style.php
   demote the flip card to a plain side-by-side row, so no JavaScript is
   involved and both faces print from this page too. */
@media print {
  .app-sidebar-wrapper,
  .dashboard-topbar,
  .app-sidebar-backdrop,
  .dashboard-header,
  .no-print { display: none !important; }

  .main-content,
  .page-content {
    display: block !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  .idc-stage { gap: 0; }
}
</style>

<!-- Header Section -->
<div class="dashboard-header mb-4 no-print">
  <h1 class="h3 fw-bold text-primary mb-1">My ID Card</h1>
  <p class="text-muted mb-0 small">Your digital school identification card</p>

  <!-- Blue Divider -->
  <div class="blue-divider"></div>
</div>

<?php if ($success = session('success')): ?>
  <div class="alert alert-success no-print"><?= esc($success) ?></div>
<?php endif; ?>
<?php if ($error = session('error')): ?>
  <div class="alert alert-danger no-print"><?= esc($error) ?></div>
<?php endif; ?>

<!-- When to use this card -->
<div class="sid-note mb-4 no-print">
  <i class="bi bi-info-circle-fill text-primary fs-4 flex-shrink-0"></i>
  <div class="small">
    <strong>Forgot your physical ID at home?</strong> No problem — you can show or print this digital ID card instead.
    Present it at the school gate or to any school staff for identity verification while your physical ID isn't with you.
    The card flips over to show the emergency contact details and the school principal's signature.
  </div>
</div>

<?php if (empty($student['photo'])): ?>
  <div class="alert alert-warning no-print d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div class="small">
      Your ID card has no photo yet. Upload one on your
      <a href="<?= base_url('student/profile') ?>">Profile</a> page and it will appear here automatically.
    </div>
  </div>
<?php endif; ?>

<div class="idc-stage">
  <div class="idc-flip" id="idc-flip" role="button" tabindex="0" aria-pressed="false" aria-label="Show the back of this ID card">
    <div class="idc-flip__inner">
      <?= view('partials/id_card_front', ['student' => $student]) ?>
      <?= view('partials/id_card_back', ['student' => $student]) ?>
    </div>
  </div>
  <p class="idc-hint no-print" id="idc-hint">Showing the FRONT &mdash; click the card to flip it over.</p>
</div>

<div class="mt-4 no-print">
  <button type="button" class="btn btn-primary" onclick="window.print()">
    <i class="bi bi-printer me-2"></i>Print ID Card (Front &amp; Back)
  </button>
  <a href="<?= base_url('student/profile') ?>" class="btn btn-outline-secondary">
    <i class="bi bi-person-gear me-2"></i>Update Photo
  </a>
</div>

<?= view('partials/id_card_flip_script') ?>

<?= $this->endSection() ?>
