<?php

/**
 * Arithmetic CAPTCHA presented as a MODAL on the login page.
 *
 * The login form itself stays clean: the visitor enters their credentials and
 * presses "ACCESS SYSTEM", which opens this modal. They solve the problem here
 * and confirm, at which point the whole form (including this field) submits.
 *
 * Rendered INSIDE the login <form> by partials/login_form.php so the answer is
 * submitted with the credentials without any hidden-field juggling.
 *
 * SECURITY: only the question is ever sent to the browser. The expected answer
 * lives in the server session and is verified by Auth::attempt() through
 * arithmetic_captcha_check(), which consumes it on every attempt. The modal is
 * purely a presentation layer - bypassing or disabling it does not bypass the
 * server-side check, because the POST simply arrives without captcha_answer and
 * is rejected.
 *
 * @var string|null $captchaQuestion
 * @var string      $formIdPrefix
 */

$formIdPrefix = $formIdPrefix ?? 'login';
$modalId      = $formIdPrefix . 'CaptchaModal';

if (empty($captchaQuestion)) {
    $captchaQuestion = function_exists('arithmetic_captcha_question')
        ? (arithmetic_captcha_question() ?? '? + ? = ?')
        : '? + ? = ?';
}

// A failed attempt redirects back here with an error alert already rendered
// by the page. The modal detects that in the DOM (rather than reading the
// flashdata itself, which the page has already consumed) and re-opens.
?>
<div class="modal fade" id="<?= esc($modalId) ?>" tabindex="-1"
     aria-labelledby="<?= esc($modalId) ?>Label" aria-hidden="true" data-captcha-modal>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content login-captcha-modal">
      <!-- School watermark, mirroring the login card's tiled logo backdrop. -->
      <div class="login-captcha-watermark" aria-hidden="true"></div>

      <div class="modal-header login-captcha-header">
        <img src="<?= asset_url('LPHS2.png') ?>" alt="Cauayan South Central School Logo" class="login-captcha-logo">
        <div>
          <h5 class="login-captcha-school">Cauayan South Central School</h5>
          <p class="login-captcha-subtitle">School Management System &middot; Secure Portal</p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body login-captcha-body">
        <div class="login-captcha-titlebar">
          <i class="bi bi-shield-lock" aria-hidden="true"></i>
          <span id="<?= esc($modalId) ?>Label">Security Check</span>
        </div>
        <p class="login-captcha-prompt">Solve the arithmetic problem to continue.</p>

        <!-- Server verdict shown inside the dialog (see showError in the script). -->
        <div class="login-captcha-error" data-captcha-error role="alert" hidden></div>

        <div class="captcha-section" data-captcha-section>
          <div class="captcha-row">
            <span class="captcha-question" data-captcha-question aria-live="polite"><?= esc($captchaQuestion) ?></span>
            <button type="button" class="captcha-refresh-btn" data-captcha-refresh
                    title="Refresh CAPTCHA" aria-label="Refresh CAPTCHA">
              <i class="bi bi-arrow-clockwise"></i>
            </button>
          </div>
          <!-- No `required` and no `name` here. It is not a form control: the
               answer is copied into the login form's hidden captcha_answer
               field on submit. `required` is also deliberately omitted so HTML5
               validation cannot block the login form's submit event.
               Auth::attempt() still enforces `required|numeric` server-side,
               which is the real control. -->
          <label class="captcha-label" for="<?= esc($formIdPrefix) ?>CaptchaAnswer">Your answer</label>
          <input type="text" class="custom-field captcha-field" id="<?= esc($formIdPrefix) ?>CaptchaAnswer"
                 data-captcha-input inputmode="numeric" autocomplete="off"
                 maxlength="3" placeholder="Type the number">
        </div>

        <button type="button" class="login-btn login-captcha-confirm" data-captcha-confirm>
          CONTINUE
        </button>
        <button type="button" class="login-captcha-cancel" data-bs-dismiss="modal">Cancel</button>
        <p class="login-captcha-footnote">Version 2.1.0 &middot; Protected by Cauayan South Central School</p>
      </div>
    </div>
  </div>
</div>

<?php
// Same branded loader the landing page uses (partials/page_loader.php), so both
// loading screens are visually identical. Rendered hidden and shown on demand.
?>
<?= view('partials/page_loader', [
    'loaderId' => 'loginPageLoader',
    'loaderStatus' => 'Signing you in...',
    'loaderHidden' => true,
]) ?>

<style>
/* Match the login card's visual language, with school branding. */
.login-captcha-modal {
  background: rgba(30, 64, 175, 0.97);
  border: 2px solid rgba(251, 191, 36, 0.6);
  border-radius: 16px; overflow: hidden; color: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.10), 0 24px 56px -8px rgba(15, 23, 42, 0.16), 0 0 0 4px rgba(251, 191, 36, 0.15);
  /* Must stay above the login page's own stacked layers. */
  position: relative; z-index: 1;
}
/* Tiled school-logo watermark, as on the login card. */
.login-captcha-watermark {
  position: absolute; inset: 0;
  background-image: url('<?= asset_url('LPHS2.png') ?>');
  background-repeat: repeat;
  background-size: 110px 110px;
  background-position: center center;
  opacity: 0.07;
  filter: grayscale(0.7) saturate(0.8);
  pointer-events: none;
  z-index: 0;
}
.login-captcha-header,
.login-captcha-body { position: relative; z-index: 1; }

.login-captcha-header {
  display: flex; align-items: center; gap: 0.75rem;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(147, 197, 253, 0.05) 100%);
  border-bottom: 1px solid rgba(59, 130, 246, 0.15); position: relative;
  padding: 1rem 1.25rem;
}
.login-captcha-logo {
  width: 46px; height: 46px; object-fit: contain; border-radius: 50%;
  background: rgba(255, 255, 255, 0.95); border: 2px solid rgba(255, 255, 255, 0.7);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.3); flex-shrink: 0;
}
.login-captcha-school {
  font-family: 'Times New Roman', Times, 'Liberation Serif', 'DejaVu Serif', serif;
  font-size: 1rem; font-weight: 700; margin: 0; line-height: 1.25;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 60%, #ffffff 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  background-clip: text; color: transparent;
}
.login-captcha-subtitle {
  color: rgba(255, 255, 255, 0.9); font-size: 0.8125rem; margin: 0; font-weight: 400;
}
.login-captcha-body { padding: 1.1rem 1.5rem 1.25rem; }
.login-captcha-titlebar {
  display: flex; align-items: center; gap: 0.5rem;
  color: #fbbf24; font-size: 0.8rem; font-weight: 700;
  letter-spacing: 0.08em; text-transform: uppercase;
  padding-bottom: 0.5rem; margin-bottom: 0.75rem;
  border-bottom: 1px solid rgba(59, 130, 246, 0.2);
}
.login-captcha-prompt {
  color: rgba(255, 255, 255, 0.95); font-size: 0.85rem; font-weight: 400;
  margin: 0 0 0.75rem;
}
.login-captcha-body .captcha-section { margin-bottom: 1rem; }
.login-captcha-body .captcha-label { margin: 0 0 0.4rem; }
/* Server verdict, mirrored from the page alert into the dialog. */
.login-captcha-error {
  display: flex; align-items: flex-start; gap: 0.45rem;
  background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;
  border-radius: 8px; padding: 0.5rem 0.7rem; margin: 0 0 0.85rem;
  font-size: 0.8rem; font-weight: 400; line-height: 1.35;
}
.login-captcha-error[hidden] { display: none; }
.login-captcha-error::before { content: '\26A0'; font-size: 0.9rem; line-height: 1.2; }
.login-captcha-confirm { margin-bottom: 0.5rem; }
.login-captcha-confirm.is-verifying { opacity: 0.75; cursor: progress; }
.login-captcha-cancel {
  display: block; width: 100%; background: none; border: none;
  color: rgba(255, 255, 255, 0.8); font-size: 0.82rem; font-weight: 400;
  padding: 0.4rem; cursor: pointer; transition: color 0.2s ease;
}
.login-captcha-cancel:hover { color: #fbbf24; }
.login-captcha-footnote {
  color: rgba(255, 255, 255, 0.55); font-size: 0.8125rem; text-align: center;
  margin: 0.75rem 0 0; padding-top: 0.6rem;
  border-top: 1px solid rgba(59, 130, 246, 0.15);
}
@media (max-width: 575.98px) {
  .login-captcha-body { padding: 1rem 1.1rem 1.1rem; }
  .login-captcha-logo { width: 38px; height: 38px; }
  .login-captcha-school { font-size: 0.9rem; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.querySelector('[data-captcha-modal]');
    var form    = document.querySelector('[data-login-form]');
    if (! modalEl || ! form) { return; }

    // Use Bootstrap when its bundle loaded, but fall back to a minimal
    // show/hide so a blocked CDN can never lock the visitor out of signing in.
    var hasBootstrap = (typeof bootstrap !== 'undefined') && bootstrap.Modal;
    var modal = hasBootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;

    var showModal = function () {
        if (modal) { modal.show(); return; }
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
        if (! document.querySelector('[data-captcha-fallback-backdrop]')) {
            var b = document.createElement('div');
            b.className = 'modal-backdrop fade show';
            b.setAttribute('data-captcha-fallback-backdrop', '');
            document.body.appendChild(b);
        }
    };

    var hideModal = function () {
        if (modal) { modal.hide(); return; }
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        var b = document.querySelector('[data-captcha-fallback-backdrop]');
        if (b) { b.parentNode.removeChild(b); }
    };

    // The fallback path needs the backdrop removed on the close/cancel controls.
    if (! hasBootstrap) {
        Array.prototype.forEach.call(
            modalEl.querySelectorAll('[data-bs-dismiss="modal"]'),
            function (btn) { btn.addEventListener('click', hideModal); }
        );
    }
    var cq      = modalEl.querySelector('[data-captcha-question]');
    var ci      = modalEl.querySelector('[data-captcha-input]');
    var cr      = modalEl.querySelector('[data-captcha-refresh]');
    var cs      = modalEl.querySelector('[data-captcha-section]');
    var confirm = modalEl.querySelector('[data-captcha-confirm]');
    // The modal lives outside the <form> (so it is not trapped by the login
    // card's backdrop-filter stacking context); the answer is copied into this
    // hidden field, which is what actually gets submitted.
    var hidden  = form.querySelector('[data-captcha-value]');

    // The page renders the server's verdict in an alert that sits BEHIND this
    // dialog, so it is mirrored into the modal where the visitor is looking.
    var pageError = document.querySelector('#errorAlert, .login-form .alert-danger');
    var errBox    = modalEl.querySelector('[data-captcha-error]');
    var loadTimer = null;
    var LOADER_ID = 'loginPageLoader';

    // The same branded loader the landing page uses, driven through the shared
    // PageLoader API defined in partials/page_loader.php. showLoading() reports
    // whether the overlay actually took over, so the caller can fall back to
    // visible in-modal feedback instead of a dead click with no screen.
    var loaderAvailable = function () {
        return !!(window.PageLoader && window.PageLoader.show
                  && document.getElementById(LOADER_ID));
    };
    var showLoading = function (message) {
        if (!loaderAvailable()) { return false; }
        window.PageLoader.show(LOADER_ID, message);
        return true;
    };
    var hideLoading = function () {
        if (window.PageLoader && window.PageLoader.hide) {
            window.PageLoader.hide(LOADER_ID);
        }
    };

    var showError = function (message) {
        if (! errBox || ! message) { return; }
        errBox.textContent = message;
        errBox.hidden = false;
    };
    var clearError = function () {
        if (! errBox) { return; }
        errBox.textContent = '';
        errBox.hidden = true;
    };

    // 1. Pressing "ACCESS SYSTEM" opens the CAPTCHA modal instead of posting
    //    straight away, so the form itself stays free of extra fields.
    //
    //    `submitting` lets the CONTINUE button's own submit through. Without
    //    it, requestSubmit() re-fires this very event, the handler intercepts
    //    it again, and the login POST never happens.
    var submitting = false;

    form.addEventListener('submit', function (e) {
        if (submitting) {
            submitting = false;   // the confirmed attempt: let it post
            return;
        }
        e.preventDefault();
        // Clear any previous answer so a stale value cannot be submitted.
        if (ci) { ci.value = ''; }
        if (hidden) { hidden.value = ''; }
        if (cs) { cs.classList.remove('has-error'); }
        clearError();
        showModal();
        if (ci) { setTimeout(function () { ci.focus(); }, 200); }
    });

    // 2. Digits only - a convenience for the visitor. The authoritative check
    //    is still arithmetic_captcha_check() on the server.
    if (ci) {
        ci.addEventListener('input', function () {
            var cleaned = ci.value.replace(/[^0-9]/g, '');
            if (cleaned !== ci.value) { ci.value = cleaned; }
            if (cs) { cs.classList.remove('has-error'); }
            clearError();
        });
    }

    // 3. Refresh asks Auth::captchaRefresh for a new question, which also
    //    invalidates the previous one server-side. Only the question is
    //    returned - the answer never leaves the server.
    if (cr && cq && ci) {
        var captchaLoading = false;
        cr.addEventListener('click', function () {
            if (captchaLoading) { return; }   // ignore double-clicks
            captchaLoading = true;
            cr.disabled = true;
            cr.classList.add('is-spinning');

            fetch('<?= base_url('login/captcha') ?>', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (data && data.question) {
                        cq.textContent = data.question;
                        ci.value = '';
                    }
                })
                .catch(function () { /* keep the current question on failure */ })
                .then(function () {
                    captchaLoading = false;
                    cr.disabled = false;
                    cr.classList.remove('is-spinning');
                    ci.focus();
                });
        });
    }

    // 4. Confirming copies the answer into the login form's hidden field and
    //    submits. The modal stays open showing "VERIFYING..." so the visitor
    //    gets clear feedback:
    //      - correct answer -> the browser navigates to their dashboard and
    //        the modal disappears with the page;
    //      - wrong answer   -> the page comes back with a fresh question and
    //        this modal re-opens showing the error.
    if (confirm && ci) {
        confirm.addEventListener('click', function () {
            if (ci.value === '') {
                if (cs) { cs.classList.add('has-error'); }
                ci.focus();
                return;
            }
            if (hidden) { hidden.value = ci.value; }
            clearError();

            confirm.disabled = true;
            confirm.dataset.originalLabel = confirm.textContent;
            confirm.textContent = 'VERIFYING...';
            confirm.classList.add('is-verifying');

            // Hand off to the full-screen loading screen: the CAPTCHA modal is
            // no longer needed, and the page is about to navigate away. If no
            // loader is available, keep the dialog open on "VERIFYING..." rather
            // than hiding it and leaving the visitor staring at nothing.
            if (showLoading('Signing you in...')) {
                hideModal();
            }

            // Safety net: if the request never completes (network drop, server
            // hang) the visitor must not be stranded on the loading screen.
            // Fetch a fresh question - the submitted one is already spent - and
            // bring them back to the CAPTCHA.
            if (loadTimer) { clearTimeout(loadTimer); }
            loadTimer = setTimeout(function () {
                hideLoading();
                confirm.disabled = false;
                confirm.textContent = confirm.dataset.originalLabel || 'CONTINUE';
                confirm.classList.remove('is-verifying');
                fetch('<?= base_url('login/captcha') ?>', { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) { if (data && data.question) { cq.textContent = data.question; } })
                    .catch(function () {});
                showError('The request took too long. Please try again.');
                showModal();
            }, 20000);

            submitting = true;
            form.requestSubmit ? form.requestSubmit() : form.submit();
        });
    }

    // 5. Surface the server's verdict INSIDE the modal. The page renders the
    //    error in an alert behind the dialog, so copy it in here where the
    //    visitor is actually looking.
    if (pageError) {
        showError(pageError.textContent.replace(/\s+/g, ' ').trim());
        showModal();
    }
});
</script>

