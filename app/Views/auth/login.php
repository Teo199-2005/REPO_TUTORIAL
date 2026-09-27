<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<style>
.login-container {
  min-height: calc(100vh - 80px);
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem 4rem;
  position: relative;
  margin: -2rem -15px 0 -15px;
}

.login-container::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="%233b82f6" stroke-width="0.5" opacity="0.2"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
  opacity: 0.9;
}

.login-container::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: url('<?= asset_url('LPHS2.png') ?>');
  background-repeat: repeat;
  background-size: 110px 110px;
  background-position: center center;
  opacity: 0.095;
  filter: grayscale(0.7) saturate(0.8);
  pointer-events: none;
}

/* Tappy stands BESIDE the sign-in card rather than inside it. He used to live in
   .login-header, on the dark blue, where he stretched the header and looked like
   a sticker on the card. z-index lifts him above the container's watermark
   pseudo-element, which is a positioned sibling with no z-index of its own. */
.login-mascot {
  position: absolute;
  top: 50%;
  right: calc(50% + 265px);
  transform: translateY(-50%);
  width: 27rem;
  max-width: 42vw;
  z-index: 2;
}

@media (max-width: 1199.98px) {
  /* Not enough side room: drop him under the card, still speaking. */
  .login-mascot {
    position: static;
    transform: none;
    width: auto;
    max-width: 440px;
    margin: 1.5rem auto 0;
  }
}

.login-card {
  background: rgba(30, 64, 175, 0.95);
  backdrop-filter: blur(25px);
  border: 1px solid rgba(255, 255, 255, 0.22);
  border-radius: var(--radius-xl);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12), 0 24px 56px -8px rgba(15, 23, 42, 0.18);
  width: 100%;
  max-width: 440px;
  overflow: hidden;
  position: relative;
  z-index: 1;
}

.login-header {
  text-align: center;
  padding: 2.5rem 2.5rem 1.75rem;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(147, 197, 253, 0.05) 100%);
  border-bottom: 1px solid rgba(59, 130, 246, 0.15);
}

.login-logo {
  width: 64px; height: 64px; object-fit: contain; border-radius: 50%;
  margin: 0 auto 0.875rem; display: block;
  background: rgba(255, 255, 255, 0.95);
  border: 2px solid rgba(255, 255, 255, 0.7);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.3);
}

.login-title {
  font-family: 'Times New Roman', Times, 'Liberation Serif', 'DejaVu Serif', serif;
  font-size: 1.85rem; font-weight: 700; margin-bottom: 0.5rem;
  letter-spacing: 0; line-height: 1.2;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ffffff 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  background-clip: text; color: transparent;
}

.login-subtitle {
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.9rem; font-weight: 400; margin: 0;
}

.login-form { padding: 1.75rem 2.25rem 2.25rem; }

/* Form controls, the arithmetic CAPTCHA, the submit button and the alert
   styling now live in partials/login_form_style.php, which is shared with
   the login modal so the two entry points can never drift apart. */
@media (max-width: 991.98px) {
  .login-header { padding: 2.25rem 2rem 1.5rem; }
  .login-title { font-size: 1.6rem; }
  .login-logo { width: 60px; height: 60px; }
  .login-form { padding: 1.5rem 2rem 1.75rem; }
  .register-section { margin: 0 -2rem -1.75rem; padding-left: 2rem; padding-right: 2rem; padding-bottom: 1.5rem; }
}

@media (max-width: 767.98px) {
  .login-container { padding: 1.5rem 0.75rem 3rem; margin: -2rem -15px 0 -15px; }
  .login-card { max-width: 400px; border-radius: 14px; }
  .login-header { padding: 2rem 1.5rem 1.25rem; }
  .login-logo { width: 56px; height: 56px; margin-bottom: 0.75rem; }
  .login-title { font-size: 1.4rem; }
  .login-subtitle { font-size: 0.8rem; }
  .login-form { padding: 1.25rem 1.5rem 1.5rem; }
  .form-control, .custom-field,
  input[type="text"], input[type="password"], input[type="email"],
  input[type="tel"], input[type="date"], input[type="number"],
  select.form-select { font-size: 16px; height: 40px; padding: 0.45rem 0.75rem; }
  .remember-section { flex-direction: column; gap: 0.5rem; align-items: flex-start; margin-bottom: 1rem; }
  .login-btn { padding: 0.55rem 1rem; font-size: 0.85rem; min-height: 40px; margin-bottom: 0.875rem; }
  .register-section { padding-top: 0.875rem; margin: 0 -1.5rem -1.5rem; padding-left: 1.5rem; padding-right: 1.5rem; padding-bottom: 1.25rem; }
  .register-text { font-size: 0.78rem; }
  .alert { padding: 0.45rem 0.7rem; font-size: 0.78rem; }
}

@media (max-width: 480px) {
  .login-container { padding: 1rem 0.5rem 2rem; }
  .login-card { border-radius: 12px; max-width: 100%; }
  .login-header { padding: 1.5rem 1rem 1rem; }
  .login-logo { width: 48px; height: 48px; margin-bottom: 0.5rem; }
  .login-title { font-size: 1.15rem; }
  .login-subtitle { font-size: 0.8125rem; }
  .login-form { padding: 1rem 1rem 1.25rem; }
  .form-control, .custom-field,
  input[type="text"], input[type="password"], input[type="email"],
  select.form-select { height: 38px; padding: 0.4rem 0.6rem; }
  .custom-input-group .custom-field { padding-left: 2rem !important; }
  .custom-input-group .input-icon { left: 10px; font-size: 0.85rem; }
  .password-input-wrapper .custom-field { padding-right: 36px !important; }
  .password-toggle-btn { width: 30px !important; height: 30px !important; right: 3px !important; }
  .login-btn { height: 38px; font-size: 0.8rem; padding: 0.45rem 0.75rem; margin-bottom: 0.75rem; }
  .remember-section { margin-bottom: 0.875rem; }
  .register-section { margin: 0 -1rem -1.25rem; padding-left: 1rem; padding-right: 1rem; padding-bottom: 1.25rem; }
}

@media (max-width: 359px) {
  .login-header { padding: 1rem 0.75rem 0.75rem; }
  .login-logo { width: 42px; height: 42px; }
  .login-title { font-size: 1.05rem; }
  .login-form { padding: 0.75rem 0.75rem 1rem; }
}

@media (max-height: 500px) and (orientation: landscape) {
  .login-container { min-height: auto; padding: 1rem 0.5rem; }
  .login-header { padding: 1rem 1rem 0.75rem; }
  .login-logo { width: 40px; height: 40px; margin-bottom: 0.25rem; }
  .login-title { font-size: 1.05rem; margin-bottom: 0.25rem; }
  .login-subtitle { font-size: 0.8125rem; }
  .login-form { padding: 0.75rem 1rem 1rem; }
  .form-control, .custom-field { height: 36px; }
  .remember-section { margin-bottom: 0.5rem; }
  .login-btn { height: 36px; font-size: 0.8rem; margin-bottom: 0.5rem; }
}
</style>

<div class="login-container">
  <div class="login-card">
    <div class="login-header">
      <img src="<?= asset_url('LPHS2.png') ?>" alt="Cauayan South Central School Logo" class="login-logo">
      <h1 class="login-title">Cauayan South Central School</h1>
      <p class="login-subtitle">School Management System</p>
      <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.78rem; margin: 0.4rem 0 0 0;">Version 2.1.0 | Secure Portal</p>
    </div>

    <div class="login-form">
      <?php $flashError = session()->getFlashdata('error'); ?>
      <?php if ($flashError !== null && $flashError !== ''): ?>
        <div class="alert alert-danger" id="errorAlert">
          <?php if (strpos((string) $flashError, 'not allowed') !== false): ?>
            Your login session expired or the page was open too long. Please press &quot;ACCESS SYSTEM&quot; again.
          <?php else: ?>
            <?= $flashError ?>
          <?php endif; ?>
          <?php if (session()->getFlashdata('locked_until')): ?>
            <div id="countdown" style="font-weight: bold; margin-top: 0.5rem;"></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success">
          <?= session()->getFlashdata('success') ?>
        </div>
      <?php endif; ?>

      <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger">
          <ul class="mb-0 ps-3">
            <?php foreach (session()->getFlashdata('errors') as $error): ?>
              <li><?= esc($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?= view('partials/login_form', [
          'captchaQuestion' => $captchaQuestion ?? null,
          'formIdPrefix' => 'pageLogin',
          'registrationEnabled' => $registrationEnabled ?? true,
      ]) ?>
    </div>
  </div>

  <?php
  // Tappy stands BESIDE the sign-in card, not inside it. Inside the card he sat
  // on the dark blue header, made the header taller, and read as decoration
  // stuck to the card. Out here he has a bubble with something to say, and he
  // cannot push the form around because the card is already laid out.
  ?>
  <div class="login-mascot">
    <?= mascot_say([
        'title' => "Hi, I'm Tappy",
        'text'  => 'Your guide around Tap n Track. Sign in, and I will show you around.',
        'pose'  => 'hero',
        'size'  => 'var(--mascot-hero)',
        'align' => 'left',
    ]) ?>
  </div>
</div>

<?php
// Rendered here, OUTSIDE .login-container/.login-card: the card's
// backdrop-filter creates a containing block for position:fixed, so a modal
// nested inside it would be trapped behind the card and block clicks.
$captchaModalQuestion = $captchaQuestion ?? null;
if ($captchaModalQuestion === null) {
    $captchaModalQuestion = function_exists('arithmetic_captcha_question')
        ? arithmetic_captcha_question()
        : null;
}
?>
<?= view('partials/login_captcha_modal', [
    'captchaQuestion' => $captchaModalQuestion,
    'formIdPrefix' => 'pageLogin',
]) ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var lockEl = document.getElementById('countdown');
    // Keep the form's CSRF token fresh: while the user waits out a lockout
    // countdown (or the page is restored from the back/forward cache), the
    // token embedded at render time can go stale — the submit would then be
    // rejected with a 403 "The action you requested is not allowed".
    var tokenRefreshed = false;
    function refreshCsrfToken() {
        fetch('<?= base_url('login/csrf-token') ?>', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.token || !data.name) { return; }
                var input = document.querySelector('input[name="' + data.name + '"]');
                if (input) { input.value = data.token; }
            })
            .catch(function () {});
    }
    if (lockEl) {
        var lockUntil = new Date('<?= session()->getFlashdata('locked_until') ?? '0' ?>').getTime();
        var ea = document.getElementById('errorAlert');
        setInterval(function() {
            var d = lockUntil - new Date().getTime();
            if (d < 0) {
                lockEl.innerHTML = ''; if (ea) ea.style.display = 'none';
                if (!tokenRefreshed) { tokenRefreshed = true; refreshCsrfToken(); }
                return;
            }
            var m = Math.floor(d / 60000), s = Math.floor((d % 60000) / 1000);
            lockEl.innerHTML = '<i class="bi bi-clock"></i> Time remaining: ' + m + 'm ' + s + 's';
        }, 1000);
    }

    // A back/forward-cache restore also carries a stale token.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { refreshCsrfToken(); }
    });
});
</script>

<?= $this->endSection() ?>
