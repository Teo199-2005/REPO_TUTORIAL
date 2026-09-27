<?php
helper(['school_year', 'platform_rating']);
$logoutRatingSchoolYear = get_current_school_year();
$logoutRatingTerm = get_current_term();
$forceShow = (bool) session()->getFlashdata('platform_rating_required');
?>
<div id="platformRatingLogoutOverlay" class="platform-rating-logout-overlay" style="display: none;" aria-hidden="true">
  <div class="platform-rating-logout-dialog" role="dialog" aria-modal="true" aria-labelledby="platformRatingLogoutTitle">
    <div class="platform-rating-logout-header">
      <i class="bi bi-stars text-warning fs-3"></i>
      <div>
        <h3 id="platformRatingLogoutTitle" class="h5 mb-1">Rate CSCS Tap n Track before you leave</h3>
        <p class="text-muted small mb-0">
          Required feedback for <strong><?= esc($logoutRatingSchoolYear) ?></strong> · <?= esc(platform_rating_term_label($logoutRatingTerm)) ?>
        </p>
      </div>
    </div>
    <div class="platform-rating-logout-body">
      <p class="mb-3">Please share how the platform is working for you this term. You can log out after you submit your rating.</p>

      <input type="hidden" id="logoutRatingValue" value="">

      <label class="form-label fw-semibold">Overall satisfaction (1–5 stars) <span class="text-danger">*</span></label>
      <div class="platform-star-picker mb-2" role="group" aria-label="Star rating">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <button type="button" class="btn btn-link platform-star-btn-logout p-1 fs-2 lh-1 text-decoration-none text-secondary"
                  data-value="<?= $i ?>" aria-label="<?= $i ?> stars">
            <i class="bi bi-star"></i>
          </button>
        <?php endfor; ?>
      </div>
      <p class="small text-muted mb-3" id="logoutRatingHint">Tap a star to choose.</p>

      <div class="mb-0">
        <label for="logoutRatingComment" class="form-label fw-semibold">Comments <span class="text-muted fw-normal">(optional)</span></label>
        <textarea class="form-control" id="logoutRatingComment" rows="3" maxlength="500" placeholder="What works well? What could be better?"></textarea>
      </div>
      <p class="small text-danger mt-2 mb-0" id="logoutRatingError" style="display: none;"></p>
    </div>
    <div class="platform-rating-logout-footer">
      <button type="button" class="btn btn-primary px-4" id="logoutRatingSubmitBtn" disabled>
        <i class="bi bi-send-fill me-2"></i>Submit feedback &amp; log out
      </button>
    </div>
  </div>
</div>

<style>
.platform-rating-logout-overlay {
  position: fixed;
  inset: 0;
  z-index: 10050;
  background: rgba(15, 23, 42, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.platform-rating-logout-dialog {
  background: #fff;
  border-radius: 16px;
  max-width: 480px;
  width: 100%;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.10), 0 24px 56px -8px rgba(15, 23, 42, 0.16);
  overflow: hidden;
}
.platform-rating-logout-header {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  background: linear-gradient(135deg, #eff6ff 0%, #fff 100%);
}
.platform-rating-logout-body {
  padding: 1.25rem 1.5rem;
}
.platform-rating-logout-footer {
  padding: 1rem 1.5rem 1.25rem;
  border-top: 1px solid #e2e8f0;
  text-align: right;
}
</style>

<script>
(function () {
  const overlay = document.getElementById('platformRatingLogoutOverlay');
  if (!overlay) return;

  const hidden = document.getElementById('logoutRatingValue');
  const hint = document.getElementById('logoutRatingHint');
  const errorEl = document.getElementById('logoutRatingError');
  const submitBtn = document.getElementById('logoutRatingSubmitBtn');
  const commentEl = document.getElementById('logoutRatingComment');
  const buttons = overlay.querySelectorAll('.platform-star-btn-logout');
  const statusUrl = <?= json_encode(base_url('api/platform-rating/status')) ?>;
  const submitUrl = <?= json_encode(base_url('api/platform-rating/submit')) ?>;
  const logoutUrl = <?= json_encode(base_url('logout')) ?>;
  const csrfName = <?= json_encode(csrf_token()) ?>;
  const csrfHash = <?= json_encode(csrf_hash()) ?>;
  const forceShowOnLoad = <?= $forceShow ? 'true' : 'false' ?>;

  function paintStars(val) {
    buttons.forEach(function (btn) {
      const v = parseInt(btn.getAttribute('data-value'), 10);
      const icon = btn.querySelector('i');
      btn.classList.toggle('text-warning', v <= val);
      btn.classList.toggle('text-secondary', v > val);
      icon.className = 'bi ' + (v <= val ? 'bi-star-fill' : 'bi-star');
    });
    hint.textContent = val > 0 ? val + ' / 5 selected' : 'Tap a star to choose.';
    hint.classList.remove('text-danger');
    submitBtn.disabled = val < 1;
  }

  buttons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const v = parseInt(btn.getAttribute('data-value'), 10);
      hidden.value = String(v);
      paintStars(v);
    });
  });

  function showModal() {
    hidden.value = '';
    commentEl.value = '';
    errorEl.style.display = 'none';
    paintStars(0);
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function hideModal() {
    overlay.style.display = 'none';
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function showThankYouThenLogout(stars) {
    const dialog = overlay.querySelector('.platform-rating-logout-dialog');
    if (!dialog) {
      hideModal();
      window.location.href = logoutUrl;
      return;
    }

    let starsHtml = '';
    const n = parseInt(stars, 10) || 0;
    for (let i = 1; i <= 5; i++) {
      starsHtml += '<i class="bi ' + (i <= n ? 'bi-star-fill' : 'bi-star') + ' me-1"></i>';
    }

    dialog.innerHTML =
      '<div class="text-center px-4 py-5">' +
        '<div class="platform-rating-thankyou-icon mb-3" aria-hidden="true">' +
          '<i class="bi bi-check-circle-fill text-success" style="font-size: 3.75rem;"></i>' +
        '</div>' +
        '<h3 class="h5 fw-bold mb-2">Thank you!</h3>' +
        '<p class="text-muted mb-3">Your feedback has been saved. Logging you out…</p>' +
        (n >= 1 ? '<div class="text-warning fs-4 mb-4">' + starsHtml + '</div>' : '') +
        '<div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>' +
      '</div>';

    window.setTimeout(function () {
      hideModal();
      window.location.href = logoutUrl;
    }, 2600);
  }

  function checkRatingRequired(callback) {
    fetch(statusUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          callback(false);
          return;
        }
        callback(data.required === true && data.submitted !== true);
      })
      .catch(function () { callback(false); });
  }

  window.platformRatingLogout = {
    promptIfNeeded: function () {
      checkRatingRequired(function (needsRating) {
        if (needsRating) {
          showModal();
        }
      });
    }
  };

  function interceptLogoutClick(e) {
    const link = e.target.closest('a[href*="logout"]');
    if (!link) return;
    e.preventDefault();
    checkRatingRequired(function (needsRating) {
      if (needsRating) {
        showModal();
      } else {
        window.location.href = logoutUrl;
      }
    });
  }

  document.addEventListener('click', interceptLogoutClick, true);

  submitBtn.addEventListener('click', function () {
    const rating = parseInt(hidden.value, 10);
    if (rating < 1 || rating > 5) {
      hint.textContent = 'Please select a star rating.';
      hint.classList.add('text-danger');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    errorEl.style.display = 'none';

    const body = new FormData();
    body.append('rating', String(rating));
    body.append('comment', commentEl.value);
    body.append(csrfName, csrfHash);

    fetch(submitUrl, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          showThankYouThenLogout(rating);
        } else {
          errorEl.textContent = data.error || 'Could not save rating. Please try again.';
          errorEl.style.display = 'block';
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Submit feedback &amp; log out';
        }
      })
      .catch(function () {
        errorEl.textContent = 'Network error. Please try again.';
        errorEl.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Submit feedback &amp; log out';
      });
  });

  if (forceShowOnLoad) {
    document.addEventListener('DOMContentLoaded', function () {
      window.platformRatingLogout.promptIfNeeded();
    });
  }
})();
</script>
