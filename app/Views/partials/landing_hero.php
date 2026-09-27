<?php
helper('landing');
$heroSlides = $heroSlides ?? landing_hero_slides_for_view();
$stripText  = $stripText ?? landing_announcement_strip_text();
$showStrip  = $stripText !== '';
// Built from $stripText so a caller that overrides the text still gets its URLs
// linkified. Safe to echo raw: landing_announcement_strip_linkify() escapes the
// text itself, so esc() here would turn our own <a> tags back into visible text.
$stripHtml  = landing_announcement_strip_linkify($stripText);
$slideCount = count($heroSlides);
$lifelines  = landing_lifelines();
?>
<section class="hero hero-slideshow" aria-label="Cauayan South Central School" data-slide-count="<?= (int) $slideCount ?>">
  <?php if ($showStrip): ?>
    <div class="landing-announcement-strip" role="marquee" aria-label="School announcement">
      <div class="landing-announcement-strip__viewport">
        <div class="landing-announcement-strip__track">
          <?php for ($copy = 0; $copy < 2; $copy++): ?>
            <div class="landing-announcement-strip__group"<?= $copy === 1 ? ' aria-hidden="true"' : '' ?>>
              <?php for ($repeat = 0; $repeat < 4; $repeat++): ?>
                <span class="landing-announcement-strip__item">
                  <i class="bi bi-megaphone-fill landing-announcement-strip__icon" aria-hidden="true"></i>
                  <span class="landing-announcement-strip__text"><?= $stripHtml ?></span>
                </span>
                <span class="landing-announcement-strip__sep" aria-hidden="true">◆</span>
              <?php endfor; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="hero-slideshow__stage">
    <?php foreach ($heroSlides as $index => $slide): ?>
      <div class="hero-slide<?= $index === 0 ? ' is-active' : '' ?>" data-slide-index="<?= (int) $index ?>">
        <img
          class="hero-banner-img"
          src="<?= esc($slide['url']) ?>"
          alt="<?= esc($slide['alt']) ?>"
          width="1983"
          height="793"
          <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
          decoding="async"
          style="--hero-object-position: <?= esc($slide['position'] ?? '50% 50%') ?>; object-position: <?= esc($slide['position'] ?? '50% 50%') ?>; transform: scale(<?= esc(number_format((float) ($slide['scale'] ?? 1), 2, '.', '')) ?>);"
        >
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($slideCount > 1): ?>
    <!-- Dot controls removed in favor of arrow nav -->
  <?php endif; ?>
  
  <!-- Minimal prev/next arrows (left/right) centered vertically -->
  <button class="hero-nav hero-prev" type="button" aria-label="Previous slide">
    <i class="bi bi-chevron-left" aria-hidden="true"></i>
  </button>
  <button class="hero-nav hero-next" type="button" aria-label="Next slide">
    <i class="bi bi-chevron-right" aria-hidden="true"></i>
  </button>

  <div class="hero-caption">
    <h1 class="sr-only">Cauayan South Central School – CSCS Tap n Track</h1>
  </div>
  
  <!-- Lifelines: Water / Communication / Electricity -->
  <div class="hero-lifelines" role="region" aria-label="School lifelines status">
    <div class="lifeline-card">
      <div class="lifeline-icon"><i class="bi bi-droplet-fill" aria-hidden="true"></i></div>
      <div class="lifeline-body">
        <div class="lifeline-title">Water</div>
  <div class="lifeline-status"><?= esc($lifelines['water'] ?? 'FUNCTIONAL') ?></div>
      </div>
    </div>

    <div class="lifeline-card">
      <div class="lifeline-icon"><i class="bi bi-telephone-fill" aria-hidden="true"></i></div>
      <div class="lifeline-body">
        <div class="lifeline-title">Communication</div>
  <div class="lifeline-status"><?= esc($lifelines['communication'] ?? 'FUNCTIONAL') ?></div>
      </div>
    </div>

    <div class="lifeline-card">
      <div class="lifeline-icon"><i class="bi bi-lightbulb-fill" aria-hidden="true"></i></div>
      <div class="lifeline-body">
        <div class="lifeline-title">Electricity</div>
  <div class="lifeline-status"><?= esc($lifelines['electricity'] ?? 'FUNCTIONAL') ?></div>
      </div>
    </div>
  </div>
</section>
