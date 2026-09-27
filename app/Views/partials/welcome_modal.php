<?php

/**
 * First-login welcome modal.
 *
 * Shown once per role, on the first dashboard page of the first visit, and it
 * carries the school branding: the school logo and the school name in the
 * header. The backdrop is the app's plain modal scrim, nothing more.
 *
 * Rendered as a direct child of <body> by dashboard_layout.php, at the same
 * z-index rung as the tour (1090 - above the dock at 1050, above the tour at
 * 1080) so it is unambiguously the first thing on screen.
 *
 * Degradation is the important part:
 *   - the whole partial returns nothing when the admin has switched it off, or
 *     when there is no artwork to put in it;
 *   - it ships hidden and is only shown by mascot-welcome.js, which also owns the
 *     once-per-role flag. A modal that cannot be dismissed is worse than no modal.
 *
 * @var string|null $welcomeRole Force a role instead of detecting it (tests, previews)
 */
$welcomeRole = $welcomeRole ?? mascot_role_key();
$welcome     = mascot_welcome_copy($welcomeRole);

// Nothing to show: switched off, or no artwork for the greeting.
if (! $welcome['enabled'] || ! mascot_exists('hero')) {
    return;
}

$schoolName = 'Cauayan South Central School';

try {
    $configured = setting('School.name');
    if (is_string($configured) && trim($configured) !== '') {
        $schoolName = trim($configured);
    }
} catch (\Throwable $e) {
    // No settings service on this request: the hard-coded name is fine.
}

// No 'size' key on purpose. mascot_img turns a numeric size into an inline
// --mascot-size, which outranks the stylesheet; the dialog owns its own scale in
// mascot-welcome.css so the hero, the speech pose and the list icons stay in step.
$bullets = '';
foreach ($welcome['bullets'] as $bullet) {
    $bullets .= '<li class="mascot-welcome__bullet">'
        . mascot_img(['name' => 'thumbs-up', 'loading' => 'lazy', 'alt' => ''])
        . '<span>' . esc($bullet) . '</span>'
        . '</li>';
}

$posterHtml = $welcome['poster'] !== ''
    ? '<img class="mascot-welcome__poster" src="' . esc($welcome['poster']) . '" alt="" decoding="async">'
    : '';
?>
<div
  class="mascot-welcome"
  data-mascot-welcome
  data-welcome-role="<?= esc($welcome['role']) ?>"
  hidden
>
  <div class="mascot-welcome__backdrop" aria-hidden="true"></div>

  <div
    class="mascot-welcome__dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="mascot-welcome-title"
  >
    <header class="mascot-welcome__head">
      <img class="mascot-welcome__logo" src="<?= asset_url('LPHS2.png') ?>" alt="" aria-hidden="true">
      <div>
        <p class="mascot-welcome__school"><?= esc($schoolName) ?></p>
        <p class="mascot-welcome__eyebrow">CSCS Tap n Track</p>
      </div>
    </header>

    <div class="mascot-welcome__body" data-welcome-track>

      <section class="mascot-welcome__slide is-active" data-welcome-slide>
        <div class="mascot-welcome__art">
          <?= mascot_img([
              'name'    => 'hero',
              'size'    => 'var(--mascot-welcome-art)',
              'loading' => 'eager',
              'alt'     => 'Tappy, the Tap n Track school guide, waving hello',
          ]) ?>
        </div>
        <h2 class="mascot-welcome__title" id="mascot-welcome-title"><?= esc($welcome['title']) ?></h2>
        <p class="mascot-welcome__text"><?= esc($welcome['body']) ?></p>
      </section>

      <section class="mascot-welcome__slide" data-welcome-slide hidden>
        <h2 class="mascot-welcome__title">What you can do here</h2>
        <?php /* The one place a full-width poster earns its keep: a dialog that
                 opens once, on first login, where there is no competing task on
                 the page. The same artwork on the login form was pure clutter -
                 it crowded the one field people came to fill in. */ ?>
        <div class="mascot-welcome__poster">
          <?= mascot_poster_banner('support', ['alt' => '', 'loading' => 'eager']) ?>
        </div>
        <ul class="mascot-welcome__list"><?= $bullets ?></ul>
      </section>

      <section class="mascot-welcome__slide" data-welcome-slide hidden>
        <h2 class="mascot-welcome__title">A word from Tappy</h2>
        <div class="mascot-welcome__say">
          <?= mascot_img(['name' => 'bust', 'loading' => 'lazy', 'alt' => '']) ?>
          <p class="mascot-welcome__dialogue" data-mascot-type><?= esc($welcome['dialogue']) ?></p>
        </div>
        <?= $posterHtml ?>
      </section>

      <section class="mascot-welcome__slide mascot-welcome__slide--final" data-welcome-slide hidden>
        <div class="mascot-welcome__art">
          <?= mascot_img([
              'name'    => 'celebrate',
              'size'    => 'var(--mascot-welcome-art)',
              'class'   => 'mascot--sparkle',
              'loading' => 'eager',
              'alt'     => '',
          ]) ?>
        </div>
        <h2 class="mascot-welcome__title">
          <?= mascot_sticker_for('wow', ['class' => 'mascot-chip mascot-chip--lg']) ?>
          You are all set
        </h2>
        <p class="mascot-welcome__text">
          Tappy stays in the corner of every page. Click him whenever you want a
          reminder, and ask him to show you around again at any time.
        </p>
      </section>

    </div>

    <footer class="mascot-welcome__foot">
      <label class="mascot-welcome__skip-again">
        <input type="checkbox" data-welcome-never>
        <span>Do not show this again</span>
      </label>

      <div class="mascot-welcome__controls">
        <button type="button" class="btn btn-sm btn-link" data-welcome-skip>Skip</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-welcome-prev hidden>Back</button>
        <button type="button" class="btn btn-sm btn-primary" data-welcome-next>Next</button>
      </div>
    </footer>

    <div class="mascot-welcome__dots" data-welcome-dots role="presentation"></div>
  </div>
</div>
