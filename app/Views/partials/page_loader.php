<?php

/**
 * Shared full-page loading overlay.
 *
 * Extracted from the landing page's #landingPageLoader so every loading screen
 * in the app is the identical branded component - the same dual-ring dial,
 * glass logo shell, school name and typography. Rendered by:
 *   - app/Views/landing.php                    (initial page load)
 *   - app/Views/partials/login_captcha_modal  (while the login is verified)
 *
 * Exposes a small imperative API so callers do not re-implement the show/hide
 * behaviour or the scroll lock:
 *
 *   PageLoader.show('<id>');   // display + lock page scroll
 *   PageLoader.hide('<id>');   // fade out, then remove from the DOM
 *
 * @var string      $loaderId       DOM id for this instance.
 * @var string|null $loaderStatus   Optional status line (e.g. "Signing you in...").
 * @var bool        $loaderHidden   Start hidden (for overlays shown on demand).
 */

$loaderId     = $loaderId ?? 'pageLoader';
$loaderStatus = $loaderStatus ?? null;
$loaderHidden = $loaderHidden ?? false;
?>
<style>
/* Branded page loader - identical to the landing page's original design. */
.page-loader {
  position: fixed;
  inset: 0;
  /* Above Bootstrap's modal (1055) and backdrop (1050). */
  z-index: 2147483000;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: clamp(1.25rem, 4vw, 2rem);
  padding: 1.5rem;
  background:
    radial-gradient(ellipse 120% 80% at 50% 20%, rgba(59, 130, 246, 0.35) 0%, transparent 55%),
    radial-gradient(ellipse 90% 70% at 80% 100%, rgba(251, 191, 36, 0.18) 0%, transparent 45%),
    linear-gradient(155deg, #0b1220 0%, #132447 38%, #1e3a8a 72%, #172554 100%);
  transition:
    opacity 0.55s cubic-bezier(0.4, 0, 0.2, 1),
    visibility 0.55s,
    transform 0.55s cubic-bezier(0.4, 0, 0.2, 1);
}
.page-loader[hidden] { display: none; }
.page-loader.is-done {
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transform: scale(1.03);
}
/* The dial.
   The track and the glow live here rather than on the rings themselves: the
   rings are masked down to an annulus, and a box-shadow on a masked element is
   masked away with it. */
.page-loader .page-loader__dial {
  position: relative;
  width: min(92vw, 360px);
  height: min(92vw, 360px);
  max-width: 360px;
  max-height: 360px;
  filter: drop-shadow(0 24px 48px rgba(0, 0, 0, 0.35));
}

/* A faint static track, so the sweep reads as a dial filling up rather than as
   an arc floating in space. */
.page-loader .page-loader__dial::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: radial-gradient(circle, transparent 0 71%, rgba(255, 255, 255, 0.07) 71.5% 100%);
}
.page-loader .page-loader__ring {
  position: absolute;
  border-radius: 50%;
  inset: 0;
  box-sizing: border-box;
  /* Painted rather than bordered: a conic gradient gives a trail that fades out
     along its whole length. A border with two coloured sides has a hard edge that
     swings past the eye once per revolution and reads as a stutter. */
  -webkit-mask: radial-gradient(circle, transparent 0 71%, #000 71.5%);
  mask: radial-gradient(circle, transparent 0 71%, #000 71.5%);
  will-change: transform;
}
.page-loader .page-loader__ring--outer {
  background: conic-gradient(from 0deg,
    rgba(251, 191, 36, 0) 0deg,
    rgba(251, 191, 36, 0.06) 100deg,
    rgba(245, 158, 11, 0.45) 250deg,
    #fbbf24 320deg,
    #f59e0b 360deg);
  /* linear, and deliberately so. The old curve (cubic-bezier(.6,.05,.35,1))
     eased each revolution, so the ring accelerated and then decelerated once per
     turn and visibly stumbled. Constant angular velocity is what reads as a
     smooth spin; easing only suits a one-shot transition. */
  animation: pageLoaderSpin 1.5s linear infinite;
}
.page-loader .page-loader__ring--mid {
  inset: 26px;
  background: conic-gradient(from 0deg,
    rgba(96, 165, 250, 0) 0deg,
    rgba(59, 130, 246, 0.35) 200deg,
    rgba(147, 197, 253, 0.9) 340deg,
    rgba(59, 130, 246, 0.2) 360deg);
  -webkit-mask: radial-gradient(circle, transparent 0 62%, #000 63%);
  mask: radial-gradient(circle, transparent 0 62%, #000 63%);
  /* Slower and counter-rotating, so the two never lock into a repeating pattern
     the eye can lock onto. */
  animation: pageLoaderSpin 2.4s linear infinite reverse;
}
.page-loader .page-loader__logo-shell {
  position: absolute;
  inset: clamp(52px, 16vw, 68px);
  border-radius: 50%;
  background: linear-gradient(165deg, rgba(255, 255, 255, 0.22) 0%, rgba(255, 255, 255, 0.06) 100%);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  box-shadow:
    0 24px 56px rgba(0, 0, 0, 0.4),
    inset 0 1px 0 rgba(255, 255, 255, 0.35),
    inset 0 -1px 0 rgba(0, 0, 0, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.18);
  /* A slow breath, out of step with both rings. The seal itself must not animate:
     a scaling or spinning logo reads as a glitch rather than as life. */
  animation: pageLoaderBreathe 3.6s cubic-bezier(0.45, 0, 0.55, 1) infinite;
}
@keyframes pageLoaderBreathe {
  0%, 100% { transform: scale(1); }
  50%      { transform: scale(1.045); }
}
.page-loader .page-loader__logo {
  width: 72%;
  height: 72%;
  max-width: 160px;
  max-height: 160px;
  object-fit: contain;
  border-radius: 50%;
  filter: drop-shadow(0 8px 24px rgba(0, 0, 0, 0.35));
}
/* Times New Roman bold throughout, matching the school wordmark in the navbar.
   The token comes from app.css, which both layouts load, with a literal stack as
   the fallback so the partial still renders correctly on its own. */
.page-loader .page-loader__label,
.page-loader .page-loader__sub,
.page-loader .page-loader__status {
  font-family: var(--font-serif);
  font-weight: var(--font-weight-bold, 700);
}
.page-loader .page-loader__label {
  margin: 0;
  font-size: clamp(1.05rem, 3.2vw, 1.3rem);
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.92);
  text-align: center;
  text-shadow: 0 2px 12px rgba(0, 0, 0, 0.45);
}
.page-loader .page-loader__sub {
  margin: -0.75rem 0 0;
  font-size: clamp(0.78rem, 2.1vw, 0.9rem);
  color: rgba(251, 191, 36, 0.95);
  letter-spacing: 0.14em;
  text-transform: uppercase;
}
.page-loader .page-loader__status {
  margin: -0.5rem 0 0;
  font-size: clamp(0.85rem, 2.3vw, 1rem);
  color: rgba(255, 255, 255, 0.92);
  letter-spacing: 0.02em;
  text-align: center;
}
@keyframes pageLoaderSpin {
  to { transform: rotate(360deg); }
}
/* Reduced motion: the spin stays, because a loading indicator that does not move
   stops reading as "working", but everything slows right down and the breathing
   shell - a continuous scale, the one movement here that is not a rotation - is
   dropped entirely. The two rings are set to the same slow speed and the same
   direction so they never beat against each other. */
@media (prefers-reduced-motion: reduce) {
  .page-loader .page-loader__ring--outer,
  .page-loader .page-loader__ring--mid {
    animation-duration: 3.2s;
    animation-direction: normal;
  }
  .page-loader .page-loader__logo-shell { animation: none; }
  .page-loader.is-done { transition-duration: 0.2s; }
}
/* The page is scroll-locked while any loader instance is showing. */
html.page-loader-active body { overflow: hidden; }
</style>

<div id="<?= esc($loaderId) ?>" class="page-loader" role="progressbar" aria-busy="true" aria-valuetext="Loading"<?= $loaderHidden ? ' hidden' : '' ?>>
  <div class="page-loader__dial">
    <div class="page-loader__ring page-loader__ring--outer" aria-hidden="true"></div>
    <div class="page-loader__ring page-loader__ring--mid" aria-hidden="true"></div>
    <div class="page-loader__logo-shell">
      <img src="<?= asset_url('LPHS2.png') ?>" alt="Cauayan South Central School"
           class="page-loader__logo" width="160" height="160" decoding="async">
    </div>
  </div>
  <p class="page-loader__label">Cauayan South Central School</p>
  <p class="page-loader__sub">School Management System</p>
  <?php if ($loaderStatus !== null && $loaderStatus !== ''): ?>
  <p class="page-loader__status" data-loader-status><?= esc($loaderStatus) ?></p>
  <?php endif; ?>
</div>

<script>
/* Imperative show/hide API for the branded page loader. Every loading screen
   in the app goes through this, so the behaviour (and the scroll lock) is
   defined in exactly one place. */
(function () {
  var active = 0;

  function lock(on) {
    active += on ? 1 : -1;
    if (active < 0) { active = 0; }
    document.documentElement.classList.toggle('page-loader-active', active > 0);
  }

  window.PageLoader = window.PageLoader || {};

  window.PageLoader.show = function (id, status) {
    var el = document.getElementById(id);
    if (! el) { return; }
    if (status) {
      var s = el.querySelector('[data-loader-status]');
      if (s) { s.textContent = status; }
    }
    el.hidden = false;
    el.classList.remove('is-done');
    el.setAttribute('aria-busy', 'true');
    lock(true);
  };

  window.PageLoader.hide = function (id) {
    var el = document.getElementById(id);
    if (! el) { return; }
    el.classList.add('is-done');       // opacity 0 + pointer-events: none
    el.setAttribute('aria-busy', 'false');
    lock(false);
    // Keep the node so show() can bring the same instance back (the login
    // screen reuses it when an attempt times out). It is fully inert while
    // faded: visibility:hidden, pointer-events:none, then display:none.
    window.setTimeout(function () { el.hidden = true; }, 600);
  };
})();
</script>
