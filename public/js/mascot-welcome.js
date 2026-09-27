/**
 * Tappy — first-login welcome modal.
 *
 * A short, branded, four-slide greeting shown once per role. It replaced the
 * "Take a tour" button in the top bar, which meant two things had to move here:
 * deciding *when* a first-time user is greeted, and handing over to the tour
 * afterwards.
 *
 * The once-per-role flag
 * ----------------------
 * mascot-tour.js already had to solve "once per role, per browser", so this
 * reuses its vocabulary rather than inventing a second one: localStorage keyed by
 * role for the permanent decision, sessionStorage mirror for the per-visit one.
 * "Do not show this again" is the difference between the two — Skip is for now,
 * the checkbox is forever.
 *
 * The handover
 * ------------
 * Setting data-mascot-welcome-dismissed="1" is the signal mascot-tour.js is
 * polling for. Without it the tour would either open behind this modal or be
 * marked as seen before the user ever saw it.
 */
(function () {
  'use strict';

  var KEY_PREFIX = 'mascot.welcome.v1';

  function storageGet(store, key) {
    try { return window[store].getItem(key); } catch (e) { return null; }
  }

  function storageSet(store, key, value) {
    try { window[store].setItem(key, value); } catch (e) { /* private mode */ }
  }

  function seenKey(role) { return KEY_PREFIX + '.seen.' + role; }
  function neverKey(role) { return KEY_PREFIX + '.never.' + role; }

  function start() {
    var root = document.querySelector('[data-mascot-welcome]');
    if (!root) return;

    var role = root.getAttribute('data-welcome-role') || 'staff';
    var dialog = root.querySelector('.mascot-welcome__dialog');
    var slides = root.querySelectorAll('[data-welcome-slide]');
    var dotsHost = root.querySelector('[data-welcome-dots]');
    var nextBtn = root.querySelector('[data-welcome-next]');
    var prevBtn = root.querySelector('[data-welcome-prev]');
    var skipBtn = root.querySelector('[data-welcome-skip]');
    var neverBox = root.querySelector('[data-welcome-never]');
    var opener = document.activeElement;

    var index = 0;

    function alreadyGreeted() {
      return storageGet('localStorage', seenKey(role)) === '1'
        || storageGet('sessionStorage', seenKey(role)) === '1'
        || storageGet('localStorage', neverKey(role)) === '1';
    }

    function renderDots() {
      if (!dotsHost) return;

      var html = '';
      for (var i = 0; i < slides.length; i++) {
        html += '<span class="mascot-welcome__dot' + (i === index ? ' is-current' : '') + '"></span>';
      }
      dotsHost.innerHTML = html;
    }

    function render() {
      for (var i = 0; i < slides.length; i++) {
        var on = i === index;
        slides[i].hidden = !on;
        slides[i].classList.toggle('is-active', on);
      }

      if (prevBtn) prevBtn.hidden = index === 0;
      if (nextBtn) {
        nextBtn.textContent = index === slides.length - 1 ? 'Start exploring' : 'Next';
      }

      renderDots();

      // Move focus to the dialog so the keyboard and a screen reader land inside
      // the greeting rather than behind it.
      if (dialog) {
        dialog.setAttribute('tabindex', '-1');
        try { dialog.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
      }
    }

    function close(rememberForever) {
      root.hidden = true;
      document.removeEventListener('keydown', onKey, true);

      if (rememberForever) {
        storageSet('localStorage', neverKey(role), '1');
      }

      // This is the signal mascot-tour.js is waiting for.
      root.setAttribute('data-mascot-welcome-dismissed', '1');

      if (opener && opener.focus) {
        try { opener.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
      }
    }

    function onKey(e) {
      if (e.key === 'Escape') { close(!!(neverBox && neverBox.checked)); return; }
      if (e.key !== 'Tab') return;

      // Trap focus inside the dialog while it is open.
      if (!dialog) return;
      var focusables = dialog.querySelectorAll('button:not([hidden]):not([disabled]), input:not([disabled])');
      if (!focusables.length) return;

      var first = focusables[0];
      var last = focusables[focusables.length - 1];

      if (e.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        if (index >= slides.length - 1) {
          close(!!(neverBox && neverBox.checked));
          return;
        }
        index += 1;
        render();
      });
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        if (index <= 0) return;
        index -= 1;
        render();
      });
    }

    if (skipBtn) {
      skipBtn.addEventListener('click', function () {
        close(!!(neverBox && neverBox.checked));
      });
    }

    // Already greeted: do not show it, but still tell the tour to carry on, or the
    // automatic tour would wait for a dismissal that is never coming.
    if (alreadyGreeted()) {
      root.setAttribute('data-mascot-welcome-dismissed', '1');
      return;
    }

    storageSet('sessionStorage', seenKey(role), '1');
    if (neverBox && storageGet('localStorage', neverKey(role)) === '1') {
      neverBox.checked = true;
    }

    root.hidden = false;
    document.addEventListener('keydown', onKey, true);
    render();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
