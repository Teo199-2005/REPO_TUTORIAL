<?php
/**
 * Profile-completion (portal unlock) card.
 *
 * Shared by student/profile.php (where a locked student now lands) and
 * student/dashboard.php, so the wording and progress can never drift apart.
 * Every unmet requirement shows the reason it is unmet plus a deep link to
 * the exact field that fixes it.
 *
 * @var array|null $student Students-table row
 * @var string     $context 'profile' (on-page, no CTA) | 'dashboard' (CTA button)
 */
helper('asset');
helper('student_profile');

$unlock        = student_profile_unlock_checklist($student ?? null);
$brand         = profile_unlock_brand();
$isProfilePage = ($context ?? 'dashboard') === 'profile';
?>
<?php if (! $unlock['complete']): ?>
<section class="profile-unlock-card" aria-labelledby="profileUnlockTitle">
  <header class="profile-unlock-brand">
    <img src="<?= esc($brand['logo']) ?>" alt="<?= esc($brand['school']) ?> logo" width="34" height="34" class="profile-unlock-brand__logo">
    <span class="profile-unlock-brand__text">
      <span class="profile-unlock-brand__school"><?= esc($brand['school']) ?></span>
      <span class="profile-unlock-brand__product"><?= esc($brand['product']) ?> &middot; <?= esc($brand['portal']) ?></span>
    </span>
    <span class="profile-unlock-brand__badge"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Portal locked</span>
  </header>

  <div class="profile-unlock-body">
    <div class="profile-unlock-head">
      <h2 id="profileUnlockTitle" class="profile-unlock-title">Complete your profile to unlock your portal</h2>
      <p class="profile-unlock-sub"><?= $isProfilePage
          ? 'The rest of the student sidebar stays locked until all three items below are on file. Each item tells you what is still missing.'
          : 'The other sidebar pages stay locked until you provide:' ?></p>
      <div class="profile-unlock-progress" role="img" aria-label="<?= (int) $unlock['done'] ?> of <?= (int) $unlock['total'] ?> requirements complete">
        <span class="profile-unlock-progress__bar" style="width: <?= (int) $unlock['percent'] ?>%"></span>
      </div>
      <span class="profile-unlock-count"><?= (int) $unlock['done'] ?> of <?= (int) $unlock['total'] ?> done</span>
    </div>

    <ul class="profile-unlock-list">
      <?php foreach ($unlock['items'] as $item): ?>
        <li class="profile-unlock-item<?= $item['met'] ? ' is-met' : ' is-missing' ?>">
          <i class="bi <?= $item['met'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> profile-unlock-item__icon" aria-hidden="true"></i>
          <span class="profile-unlock-item__body">
            <span class="profile-unlock-item__label"><?= esc($item['label']) ?></span>
            <span class="profile-unlock-item__why"><?= esc($item['why']) ?></span>
          </span>
          <?php if ($item['met']): ?>
            <span class="profile-unlock-item__state"><i class="bi bi-check2" aria-hidden="true"></i>Done</span>
          <?php else: ?>
            <a class="profile-unlock-item__action" href="<?= esc($item['link']) ?>"><?= esc($item['action']) ?><i class="bi bi-arrow-right-short" aria-hidden="true"></i></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if (! $isProfilePage): ?>
      <a href="<?= esc($brand['profile_url']) ?>" class="btn btn-warning btn-sm profile-unlock-cta">
        <i class="bi bi-person-gear me-1" aria-hidden="true"></i>Complete my profile
      </a>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
