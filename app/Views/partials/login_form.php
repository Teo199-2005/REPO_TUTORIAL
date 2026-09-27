<?php

/**
 * Login form: identifier, password, remember-me.
 *
 * The arithmetic CAPTCHA is deliberately NOT part of this markup. It is
 * presented in a modal (partials/login_captcha_modal.php) that opens when the
 * visitor presses "ACCESS SYSTEM" - see the include at the bottom of this
 * file, which renders that modal inside the <form> so its answer is submitted
 * along with the credentials.
 *
 * @var string      $captchaQuestion  Passed through to the CAPTCHA modal.
 * @var string|null $formIdPrefix     Unique DOM id prefix for the CAPTCHA modal.
 * @var bool        $registrationEnabled
 */

$formIdPrefix = $formIdPrefix ?? 'login';
$uid = static fn (string $name): string => $formIdPrefix . $name;

$idIdentifier = $uid('Identifier');
$idPassword   = $uid('Password');
$idCaptcha    = $uid('Captcha');
$idQuestion   = $uid('Question');

// The controller passes the question; the modal receives it the same way. When
// it is somehow absent, fall back to the current session challenge so the UI
// still renders (the server check remains authoritative either way).
if (empty($captchaQuestion)) {
    $captchaQuestion = function_exists('arithmetic_captcha_question')
        ? (arithmetic_captcha_question() ?? '? + ? = ?')
        : '? + ? = ?';
}
?>
<style><?= view('partials/login_form_style') ?></style>

<form method="post" action="<?= base_url('login') ?>" data-login-form>
  <?= csrf_field() ?>

  <div class="custom-input-group">
    <i class="bi bi-person input-icon"></i>
    <input type="text" class="custom-field" id="<?= esc($idIdentifier) ?>" name="identifier"
           placeholder="Email, LRN, or PRC License"
           value="<?= old('identifier') ?: ($_COOKIE['remembered_identifier'] ?? '') ?>" required>
  </div>

  <div class="custom-input-group password-input-wrapper">
    <i class="bi bi-lock input-icon"></i>
    <input type="password" class="custom-field" id="<?= esc($idPassword) ?>" name="password"
           placeholder="Password" value="<?= $_COOKIE['remembered_password'] ?? '' ?>" required>
    <button type="button" class="btn position-absolute password-toggle-btn" data-password-toggle
            aria-label="Show password">
      <i class="bi bi-eye" data-password-toggle-icon></i>
    </button>
  </div>

  <div class="remember-section">
    <div class="form-check">
      <input type="checkbox" class="form-check-input" id="<?= esc($uid('Remember')) ?>" name="remember" value="1"
             <?= isset($_COOKIE['remembered_identifier']) && $_COOKIE['remembered_identifier'] ? 'checked' : '' ?>>
      <label class="form-check-label" for="<?= esc($uid('Remember')) ?>">Remember me</label>
    </div>
    <div class="forgot-password-section">
      <a href="<?= base_url('forgot-password') ?>" class="forgot-link">Forgot Password?</a>
    </div>
  </div>
  <button type="submit" class="login-btn">ACCESS SYSTEM</button>

  <?php
  // The arithmetic CAPTCHA is presented in a MODAL, not inline: it opens when
  // the visitor presses "ACCESS SYSTEM", so this form stays uncluttered.
  //
  // The modal is rendered OUTSIDE this form (at the end of the page) because
  // the login card uses `backdrop-filter`, which creates a containing block
  // for position:fixed elements - a modal nested inside would be trapped in
  // the card's stacking context and render behind it. The visitor's answer is
  // therefore typed in the modal and copied into this hidden field, which is
  // what actually gets submitted.
  ?>
  <input type="hidden" name="captcha_answer" data-captcha-value
         id="<?= esc($uid('CaptchaValue')) ?>" value="">
</form>

<?php if ($registrationEnabled ?? true): ?>
<div class="register-section">
  <p class="register-text">New student? <a href="<?= base_url('register') ?>" class="register-link">Create Account</a></p>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Scoped to this form instance so the page and the modal never fight over
    // the same elements.
    var form = document.querySelector('[data-login-form]');
    if (! form) { return; }

    // Password visibility toggle.
    var toggle = form.querySelector('[data-password-toggle]');
    var pass   = form.querySelector('input[name="password"]');
    var icon   = form.querySelector('[data-password-toggle-icon]');
    if (toggle && pass && icon) {
        toggle.addEventListener('click', function () {
            var shown = pass.getAttribute('type') === 'text';
            pass.setAttribute('type', shown ? 'password' : 'text');
            icon.classList.toggle('bi-eye', shown);
            icon.classList.toggle('bi-eye-slash', ! shown);
            toggle.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');
        });
    }
});
</script>

