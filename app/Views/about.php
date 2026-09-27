<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<link href="<?= asset_url('css/landing.css') ?>" rel="stylesheet" />
<link href="<?= asset_url('css/school-about.css') ?>" rel="stylesheet" />
<style>
#landing {
  font-family: 'Times New Roman', Times, serif;
  line-height: 1.6;
}
#landing .landing-section-inner {
  width: 100% !important;
  max-width: none !important;
  margin: 0 auto !important;
  padding-left: clamp(1.25rem, 6vw, 10rem) !important;
  padding-right: clamp(1.25rem, 6vw, 10rem) !important;
  box-sizing: border-box !important;
}
#landing section {
  padding: 5rem 0 !important;
}
#landing .section-light {
  background-color: #ffffff !important;
  background-image: url("https://www.transparenttextures.com/patterns/batthern.png") !important;
  background-size: auto !important;
  background-repeat: repeat !important;
}
#landing .section-dark {
  background-color: #dbeafe !important;
  background-image: url("https://www.transparenttextures.com/patterns/dotnoise-light-grey.png") !important;
  background-size: auto !important;
  background-repeat: repeat !important;
}
#landing .about-page-hero {
  position: relative;
  overflow: hidden;
  padding: clamp(4rem, 8vw, 6rem) 0 clamp(3rem, 5vw, 4.25rem) !important;
  background:
    radial-gradient(120% 120% at 15% 0%, rgba(96, 165, 250, 0.35) 0%, rgba(96, 165, 250, 0) 55%),
    radial-gradient(120% 120% at 85% 100%, rgba(251, 191, 36, 0.18) 0%, rgba(251, 191, 36, 0) 58%),
    linear-gradient(135deg, #1e3a8a 0%, #1e40af 48%, #172554 100%) !important;
  color: #fff;
  text-align: center;
}
#landing .about-page-hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-size: 38px 38px;
  opacity: 0.35;
  pointer-events: none;
}
#landing .about-page-hero .landing-section-inner {
  position: relative;
  z-index: 1;
}
#landing .about-page-hero .section-title {
  color: #fff !important;
  margin-bottom: 0.9rem;
  font-size: clamp(2rem, 5.4vw, 4rem) !important;
  letter-spacing: 0 !important;
  line-height: 1.06 !important;
  text-shadow: 0 14px 36px rgba(2, 6, 23, 0.45);
}
#landing .about-page-hero .section-subtitle {
  color: rgba(255, 255, 255, 0.96) !important;
  font-size: clamp(1.02rem, 1.9vw, 1.35rem) !important;
  line-height: 1.7;
  max-width: 56rem;
  margin: 0 auto;
}
#landing .about-page-accent {
  width: min(140px, 28vw);
  height: 4px;
  border-radius: 999px;
  margin: 1.25rem auto 0;
  background: linear-gradient(90deg, #fbbf24 0%, #f59e0b 45%, #60a5fa 100%);
  box-shadow: 0 0 20px rgba(251, 191, 36, 0.4);
}
#landing .gsap-fade-up,
#landing .gsap-fade-left,
#landing .gsap-fade-right,
#landing .gsap-scale-in {
  will-change: transform, opacity;
}
</style>

<div id="landing">
  <section class="about-page-hero" aria-label="About Cauayan South Central School">
    <div class="landing-section-inner">
      <?php /* Tappy introduces the school, with a sticker in the corner. Both hide until the artwork is pasted in. */ ?>
      <div class="mascot-hero-sticker" style="position:relative;">
        <?= mascot_sticker(['name' => 'sticker-tip', 'alt' => '', 'size' => 64, 'loading' => 'eager']) ?>
      </div>
      <h1 class="section-title">About Our School</h1>
      <p class="section-subtitle">Learn about Cauayan South Central School, our leadership, and our commitment to quality basic education.</p>
      <div class="mascot-hero-art">
        <?= mascot_img(['name' => 'reading', 'alt' => 'Tappy reading a book', 'size' => 190, 'loading' => 'eager']) ?>
      </div>
      <div class="about-page-accent gsap-scale-in" aria-hidden="true"></div>
    </div>
  </section>

  <?php /* The mascot band divider used to sit here, above the gradient rule below.
           It was the only placement left on the site and it was not earning its
           space, so it is gone. The plain gradient divider that follows carries
           the section break on its own, which is what mascot_band_divider() was
           layered over in the first place. */ ?>
  <div class="section-divider"></div>

  <?= $this->include('partials/school_about_sections') ?>

  <?= $this->include('partials/portal_overview_sections') ?>

  <div class="section-divider"></div>

  <?php
    try {
      $systemSettingModel = new \App\Models\SystemSettingModel();
      $registrationSetting = $systemSettingModel->getSetting('registration_enabled', null);
      if ($registrationSetting === null) {
        $registrationSetting = $systemSettingModel->getSetting('enrollment_enabled', 1);
      }
      $registrationEnabled = (bool) $registrationSetting;
    } catch (\Throwable $e) {
      $registrationEnabled = true;
    }
  ?>

  <section class="cta-section py-5">
    <div class="container">
      <div class="cta-content">
        <div class="mb-3">
          <span class="badge-text">CSCS Tap n Track</span>
        </div>
        <h2 class="cta-title">Access your school portal</h2>
        <p class="cta-subtitle">Sign in with your school-issued account. For new access or account issues, contact the school registrar or ICT coordinator.</p>
        <div class="cta-actions d-flex flex-wrap gap-3 justify-content-center align-items-center">
          <a href="<?= base_url('login') ?>" class="btn btn-accent btn-lg">
            <i class="bi bi-box-arrow-in-right me-2"></i>
            Sign in
          </a>
          <?php if ($registrationEnabled): ?>
          <a href="<?= base_url('register') ?>" class="btn btn-outline-light btn-lg">
            <i class="bi bi-person-plus me-2"></i>
            Student application (if opened)
          </a>
          <?php endif; ?>
        </div>
      </div>
      <div class="cta-background-pattern"></div>
    </div>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
<script>
(function () {
  if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') return;
  gsap.registerPlugin(ScrollTrigger);

  var root = document.querySelector('#landing');
  if (!root) return;

  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var scrollEase = 'power2.out';
  var revealStart = 'top 86%';

  // Hero intro sequence
  var hero = root.querySelector('.about-page-hero');
  if (hero) {
    var heroTl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    heroTl
      .fromTo(
        hero.querySelector('.section-title'),
        { autoAlpha: 0, y: 24, scale: 0.98 },
        { autoAlpha: 1, y: 0, scale: 1, duration: 0.7 },
        '+=0'
      )
      .fromTo(
        hero.querySelector('.section-subtitle'),
        { autoAlpha: 0, y: 16 },
        { autoAlpha: 1, y: 0, duration: 0.6 },
        '-=0.35'
      )
      .fromTo(
        hero.querySelector('.about-page-accent'),
        { autoAlpha: 0, scaleX: 0.6, transformOrigin: 'center center' },
        { autoAlpha: 1, scaleX: 1, duration: 0.5 },
        '-=0.35'
      );

    // Subtle parallax for hero background
    gsap.to(hero, {
      backgroundPosition: '50% 56%',
      ease: 'none',
      scrollTrigger: {
        trigger: hero,
        start: 'top top',
        end: 'bottom top',
        scrub: 0.6
      }
    });
  }

  function revealBatch(targets, fromVars, toVars) {
    var items = gsap.utils.toArray(targets);
    if (!items.length) return;
    gsap.set(items, fromVars);
    ScrollTrigger.batch(items, {
      start: revealStart,
      once: true,
      onEnter: function (batch) {
        gsap.to(batch, Object.assign({
          autoAlpha: 1,
          x: 0,
          y: 0,
          scale: 1,
          duration: 0.7,
          ease: scrollEase,
          stagger: 0.08,
          overwrite: 'auto'
        }, toVars || {}));
      }
    });
  }

  revealBatch('#landing .principal-card', { autoAlpha: 0, y: 28 }, { duration: 0.75 });
  revealBatch('#landing .vision-mission-panel', { autoAlpha: 0, y: 26 }, { duration: 0.72, stagger: 0.1 });
  revealBatch('#landing .portal-poster', { autoAlpha: 0, x: -32 }, { duration: 0.75 });
  revealBatch('#landing .portal-features-grid .feature-item', { autoAlpha: 0, y: 22, scale: 0.98 }, { duration: 0.62, stagger: 0.05 });
  revealBatch('#landing .enrollment-process-steps .process-item', { autoAlpha: 0, y: 24 }, { duration: 0.68, stagger: 0.08 });
  revealBatch('#landing .cta-section .cta-content', { autoAlpha: 0, y: 24 }, { duration: 0.75, stagger: 0 });

  // Heading polish across sections
  revealBatch('#landing .section-title', { autoAlpha: 0, y: 18 }, { duration: 0.6, stagger: 0.06 });
  revealBatch('#landing .section-subtitle', { autoAlpha: 0, y: 16 }, { duration: 0.58, stagger: 0.06 });

  // Mild depth effect while scrolling for large cards (professional, subtle)
  gsap.utils.toArray([
    '#landing .principal-card',
    '#landing .vision-mission-panel',
    '#landing .portal-poster'
  ]).forEach(function (selector) {
    var el = root.querySelector(selector);
    if (!el) return;
    gsap.to(el, {
      yPercent: -2.5,
      ease: 'none',
      scrollTrigger: {
        trigger: el,
        start: 'top bottom',
        end: 'bottom top',
        scrub: 0.8
      }
    });
  });

  ScrollTrigger.create({
    trigger: '#landing .principal-section',
    start: 'top 82%',
    once: true,
    onEnter: function () {
      var left = root.querySelector('#landing .principal-section .scroll-animate-left');
      var right = root.querySelector('#landing .principal-section .scroll-animate-right');
      if (left) gsap.fromTo(left, { autoAlpha: 0, x: -32 }, { autoAlpha: 1, x: 0, duration: 0.72, ease: scrollEase, immediateRender: false });
      if (right) gsap.fromTo(right, { autoAlpha: 0, x: 32 }, { autoAlpha: 1, x: 0, duration: 0.72, ease: scrollEase, immediateRender: false });
    }
  });

  window.addEventListener('load', function () {
    ScrollTrigger.refresh();
  });
})();
</script>

<?= $this->endSection() ?>
