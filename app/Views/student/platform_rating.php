<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?php
$currentRating     = (int) (old('rating') ?: ($rating['rating'] ?? 0));
$hasPrior          = ! empty($rating);
$ratingThankYou    = ! empty($ratingThankYou);
$thankYouStars     = (int) ($ratingThankYouStars ?? 0);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h4 mb-0"><i class="bi bi-stars me-2 text-warning"></i>Rate the platform</h1>
  <a href="<?= base_url('student/dashboard') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Dashboard
  </a>
</div>

<?php if ($err = session('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show"><?= esc($err) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>
<?php if (! empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= esc(is_array($e) ? implode(' ', $e) : $e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <?php if ($ratingThankYou): ?>
      <div class="text-center py-4 py-md-5" id="platformRatingThankYouState">
        <div class="platform-rating-thankyou-icon mb-3" aria-hidden="true">
          <i class="bi bi-check-circle-fill text-success"></i>
        </div>
        <h2 class="h5 fw-bold mb-2">Thank you for your feedback!</h2>
        <p class="text-muted mb-3">Your rating for <?= esc($school_year ?? '') ?> · <?= esc($term_label ?? '') ?> has been saved.</p>
        <?php if ($thankYouStars >= 1 && $thankYouStars <= 5): ?>
          <div class="text-warning fs-4 mb-4" aria-label="<?= (int) $thankYouStars ?> out of 5 stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class="bi <?= $i <= $thankYouStars ? 'bi-star-fill' : 'bi-star' ?> me-1"></i>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
        <a href="<?= base_url('student/dashboard') ?>" class="btn btn-primary px-4">
          <i class="bi bi-house-door me-2"></i>Back to dashboard
        </a>
      </div>
    <?php else: ?>
      <p class="text-muted mb-2">Your feedback helps the school improve CSCS Tap n Track. One rating per term is required before logout.</p>
      <p class="small text-secondary mb-4">
        <i class="bi bi-calendar3 me-1"></i><?= esc($school_year ?? '') ?> · <?= esc($term_label ?? '') ?>
      </p>

      <?php if ($hasPrior): ?>
        <p class="small text-secondary mb-3">
          <i class="bi bi-clock-history me-1"></i>
          Last submitted: <?= esc(date('M j, Y g:i A', strtotime((string) $rating['updated_at']))) ?>
        </p>
      <?php endif; ?>

      <form method="post" action="<?= base_url('student/platform-rating/save') ?>" id="platformRatingForm">
        <?= csrf_field() ?>
        <input type="hidden" name="rating" id="ratingValue" value="<?= $currentRating > 0 ? esc($currentRating) : '' ?>" required>

        <label class="form-label fw-semibold">Overall satisfaction (1–5 stars)</label>
        <div class="platform-star-picker mb-2" role="group" aria-label="Star rating">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <button type="button" class="btn btn-link platform-star-btn p-1 fs-2 lh-1 text-decoration-none <?= $i <= $currentRating ? 'text-warning' : 'text-secondary' ?>"
                    data-value="<?= $i ?>" aria-label="<?= $i ?> stars">
              <i class="bi <?= $i <= $currentRating ? 'bi-star-fill' : 'bi-star' ?>"></i>
            </button>
          <?php endfor; ?>
        </div>
        <p class="small text-muted mb-4" id="ratingHint"><?= $currentRating > 0 ? esc($currentRating) . ' / 5 selected' : 'Tap a star to choose.' ?></p>

        <div class="mb-4">
          <label for="comment" class="form-label fw-semibold">Comments <span class="text-muted fw-normal">(optional, max 500 characters)</span></label>
          <textarea class="form-control" name="comment" id="comment" rows="4" maxlength="500"
                    placeholder="What works well? What could be better?"><?= esc(old('comment', $rating['comment'] ?? '')) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-send-fill me-2"></i><?= $hasPrior ? 'Update rating' : 'Submit rating' ?>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($ratingThankYou): ?>
  <?= view('partials/platform_rating_thankyou_modal', [
      'showOnLoad'     => true,
      'submittedStars' => $thankYouStars,
      'dashboardUrl'   => base_url('student/dashboard'),
      'primaryLabel'   => 'Back to dashboard',
  ]) ?>
<?php else: ?>
<script>
(function () {
  const hidden = document.getElementById('ratingValue');
  const hint = document.getElementById('ratingHint');
  const buttons = document.querySelectorAll('.platform-star-btn');
  function paint(val) {
    buttons.forEach(function (btn) {
      const v = parseInt(btn.getAttribute('data-value'), 10);
      const icon = btn.querySelector('i');
      btn.classList.toggle('text-warning', v <= val);
      btn.classList.toggle('text-secondary', v > val);
      icon.className = 'bi ' + (v <= val ? 'bi-star-fill' : 'bi-star');
    });
    hint.textContent = val > 0 ? val + ' / 5 selected' : 'Tap a star to choose.';
    hint.classList.remove('text-danger');
  }
  buttons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const v = parseInt(btn.getAttribute('data-value'), 10);
      hidden.value = String(v);
      paint(v);
    });
  });
  document.getElementById('platformRatingForm').addEventListener('submit', function (e) {
    if (!hidden.value || parseInt(hidden.value, 10) < 1) {
      e.preventDefault();
      hint.textContent = 'Please select a star rating.';
      hint.classList.add('text-danger');
    }
  });
  <?php if ($currentRating > 0): ?>paint(<?= (int) $currentRating ?>);<?php endif; ?>
})();
</script>
<?php endif; ?>

<?= $this->endSection() ?>
