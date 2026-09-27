/**
 * Tappy — mascot decoration for alerts and dialogs.
 *
 * There are 186 Bootstrap alert blocks across the views, so the mascot is
 * injected into the DOM rather than written into every template. That keeps
 * the change in one place and means an alert gains branding automatically even
 * when it is produced by inline JavaScript inside a modal.
 *
 * HARD CONSTRAINT — do not touch window.confirm.
 * modal-system.js deliberately does not override it: a promise-based confirm is
 * always truthy, so `if (confirm(msg)) {...}` callers silently auto-approve.
 * That bug deleted announcements. This file must never add a confirm override.
 */
(function () {
  'use strict';

  /**
   * Where the artwork lives.
   *
   * Read from the data-mascot-base attribute the layouts render, because guessing
   * it from the URL is wrong. The old trick - take the current URL and strip the
   * last path segment - is only correct for a page one level deep. On
   * /admin/teachers it produced /admin/assets/mascot/, so the tour, every alert
   * and every dialog mascot 404ed and showed a broken image. The guess is kept
   * only as a fallback for a page that forgot the attribute.
   */
  function base() {
    var body = document.body;

    if (body && body.getAttribute) {
      var given = body.getAttribute('data-mascot-base');
      if (given) { return given; }
    }

    var a = document.createElement('a');
    a.href = window.location.href;
    return a.href.replace(/\/[^/]*$/, '') + '/assets/mascot/';
  }

  /** Which pose suits which outcome. */
  var ALERT_POSE = {
    success: 'thumbs-up',
    danger: 'worried',
    warning: 'thinking',
    info: 'bust',
    light: 'bust'
  };

  var DIALOG_POSE = {
    confirm: 'thinking',
    question: 'thinking',
    alert: 'bust',
    success: 'thumbs-up',
    error: 'worried',
    danger: 'worried',
    warning: 'thinking',
    info: 'bust'
  };

  var IMAGE_CACHE = {};

  function url(name) {
    return base() + name + '.png';
  }

  /**
   * Build a mascot <span> identical to the one mascot_img() emits in PHP.
   */
  function mascotSpan(name, size) {
    var span = document.createElement('span');
    span.className = 'mascot mascot--' + name;
    span.style.setProperty('--mascot-size', (size || 44) + 'px');

    var img = document.createElement('img');
    img.src = url(name);
    img.width = size || 44;
    img.height = size || 44;
    img.alt = '';
    img.setAttribute('aria-hidden', 'true');
    img.loading = 'lazy';
    img.decoding = 'async';

    // If the artwork has not been pasted in, hide the wrapper so it can never
    // leave an empty gap in the alert.
    img.addEventListener('error', function () {
      span.classList.add('mascot--missing');
      if (span.parentNode) span.parentNode.removeChild(span);
    });

    span.appendChild(img);
    return span;
  }

  function variantOf(el, prefix) {
    var cls = el.className || '';
    var re = new RegExp('(?:^|\\s)' + prefix + '-([a-z0-9-]+)');
    var m = cls.match(re);
    return m ? m[1] : '';
  }

  /**
   * Decorate one alert. Alerts inside a modal body are skipped: the dialog
   * already carries its own mascot, and two of them side by side looks wrong.
   */
  function decorateAlert(alert) {
    if (alert.dataset.mascotDone === '1') return;
    alert.dataset.mascotDone = '1';

    if (alert.closest('.modal-body')) return;

    var variant = variantOf(alert, 'alert');
    var pose = ALERT_POSE[variant] || 'bust';

    // A centred alert (icon above the text) has no room beside it.
    if (alert.className.indexOf('text-center') !== -1) return;

    alert.classList.add('mascot-alert');
    alert.appendChild(mascotSpan(pose, 44));
  }

  function decorateAlerts(root) {
    var scope = root || document;
    var alerts = scope.querySelectorAll(
      '.alert-success, .alert-danger, .alert-warning, .alert-info, .alert-light'
    );
    for (var i = 0; i < alerts.length; i++) decorateAlert(alerts[i]);
  }

  /**
   * Decorate a customConfirm()/customAlert() dialog. The `type` argument those
   * functions already accept decides the pose, so "are you sure?" gets the
   * thinking pose and an error gets the worried one.
   */
  function decorateDialog(container, type) {
    if (!container || container.dataset.mascotDone === '1') return;
    container.dataset.mascotDone = '1';

    var key = String(type || 'info').toLowerCase();
    var pose = DIALOG_POSE[key] || 'bust';

    var variantClass = 'mascot-dialog--' + (key === 'question' ? 'confirm' : key);
    container.classList.add('mascot-dialog', variantClass);

    var art = mascotSpan(pose, 84);
    art.classList.add('mascot-dialog-art');
    container.appendChild(art);
  }

  /**
   * Exposed so modal-system.js can hand us the dialog it just built.
   */
  window.__mascotDecorateDialog = decorateDialog;

  /**
   * Alerts arrive late in a few places (inline scripts render them into a
   * modal body, then the modal is relocated to the modal portal by
   * dashboard-modals.js), so observe rather than decorate once and hope.
   */
  function start() {
    decorateAlerts(document);
    startSay();
    startReveal();
    startDock();

    if (typeof MutationObserver === 'undefined') return;
    var pending = null;
    var observer = new MutationObserver(function () {
      if (pending) return;
      pending = window.setTimeout(function () {
        pending = null;
        decorateAlerts(document);
      }, 120);
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  /* ==========================================================================
     Floating dock — partials/mascot_dock.php

     Tappy is a guide, not a chatbot. On the first visit to a page he says
     something about that page; after that he stays quiet but stays clickable,
     because he is also the way back into the guided tour now that the
     "Take a tour" button has been removed from the top bar.

     Two rules this file must not break:
       - an *automatic* greeting hides itself after DOCK_AUTO_HIDE_MS;
       - a bubble the user opened stays until they close it, because it holds
         buttons and auto-hiding those is hostile.
     ========================================================================== */
  var DOCK_KEY_PREFIX = 'mascot.dock.v1';
  var DOCK_AUTO_HIDE_MS = 12000;
  var DOCK_HINT_AFTER_MS = 22000;
  var DOCK_HINT_TEXT = 'Need a hand?';

  function storageGet(store, key) {
    try { return window[store].getItem(key); } catch (e) { return null; }
  }

  function storageSet(store, key, value) {
    try { window[store].setItem(key, value); } catch (e) { /* private mode */ }
  }

  function prefersReducedMotion() {
    try {
      return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    } catch (e) {
      return false;
    }
  }

  /**
   * Reveal a line character by character.
   *
   * Progressive enhancement, not decoration: the full sentence is already in the
   * DOM, so with JavaScript off - or with reduced motion on - it is simply read
   * in full. Only the caret marks that typing is in progress.
   */
  function typeInto(el, text, done) {
    if (!el) { if (done) done(); return; }

    if (prefersReducedMotion()) {
      el.textContent = text;
      el.removeAttribute('data-mascot-typing');
      if (done) done();
      return;
    }

    el.setAttribute('data-mascot-typing', '1');
    el.textContent = '';

    var i = 0;
    // Reveal in chunks so a long line never takes more than about a second.
    var step = Math.max(1, Math.ceil(text.length / 90));

    var tick = function () {
      i = Math.min(text.length, i + step);
      el.textContent = text.slice(0, i);

      if (i < text.length) {
        window.setTimeout(tick, 16);
      } else {
        el.removeAttribute('data-mascot-typing');
        if (done) done();
      }
    };

    // A short pause first, so the bubble arrives and then the words follow.
    window.setTimeout(tick, 240);
  }

  /**
   * Pop a mascot in as it scrolls into view, and start the typewriter on an
   * inline speech bubble when it arrives.
   *
   * Only touches elements a view explicitly opted into, and degrades to "just
   * visible, text shown in full" when IntersectionObserver is unavailable.
   */
  function startReveal() {
    var items = document.querySelectorAll('.mascot--reveal, .mascot-say');
    if (!items.length) return;

    var enter = function (el) {
      if (el.classList.contains('mascot-say')) {
        var line = el.querySelector('[data-mascot-type]');
        if (line) typeInto(line, line.textContent || '');
      } else {
        el.classList.add('mascot--visible');
      }
    };

    if (!('IntersectionObserver' in window)) {
      for (var n = 0; n < items.length; n++) enter(items[n]);
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      for (var k = 0; k < entries.length; k++) {
        if (entries[k].isIntersecting) {
          enter(entries[k].target);
          io.unobserve(entries[k].target);
        }
      }
    }, { rootMargin: '0px 0px -6% 0px' });

    for (var i = 0; i < items.length; i++) io.observe(items[i]);
  }

  /**
   * Wire up the inline speech bubbles rendered by mascot_say().
   *
   * Scoped to [data-mascot-say] on purpose: the dock has its own copy of these
   * buttons and binds them itself, and starting the tour twice is worse than
   * starting it once.
   */
  function startSay() {
    var tours = document.querySelectorAll('[data-mascot-say] [data-mascot-tour]');
    for (var t = 0; t < tours.length; t++) {
      tours[t].addEventListener('click', function () {
        if (typeof window.__startMascotTour === 'function') {
          window.__startMascotTour();
        }
      });
    }

    var closes = document.querySelectorAll('[data-mascot-say] [data-mascot-say-close]');
    for (var c = 0; c < closes.length; c++) {
      closes[c].addEventListener('click', function () {
        var host = this.closest('[data-mascot-say]');
        if (host && host.parentNode) host.parentNode.removeChild(host);
      });
    }
  }

  function startDock() {
    var dock = document.querySelector('[data-mascot-dock]');
    if (!dock) return;

    var panel = dock.querySelector('.mascot-dock__panel');
    var trigger = dock.querySelector('[data-mascot-trigger]');
    var hint = dock.querySelector('[data-mascot-hint]');
    var art = dock.querySelector('.mascot-dock__art');
    var img = art ? art.querySelector('img') : null;
    var closes = dock.querySelectorAll('[data-mascot-dismiss]');
    var hideBtns = dock.querySelectorAll('[data-mascot-hide]');
    var tourBtns = dock.querySelectorAll('[data-mascot-tour]');

    // The restore button is a SIBLING of the dock, not a child, so it is looked up
    // on the document. It is the only way back once Tappy has been sent away.
    var restoreBtn = document.querySelector('[data-mascot-restore]');
    var titleEl = dock.querySelector('[data-mascot-title]');
    var textEl = dock.querySelector('[data-mascot-text]');

    // No artwork means no dock: a speech bubble with nobody in it is just a
    // grey box parked over the corner of the page.
    if (!panel || !img) return;

    var key = dock.getAttribute('data-dock-key') || 'default';
    var dismissedKey = DOCK_KEY_PREFIX + '.dismissed.' + key;

    /* --- Hide and restore ----------------------------------------------------
       "Hide Tappy" is a decision about the ACCOUNT, not about this browser.

       The localStorage copy is only a mirror: it is written immediately so the
       next page is right before the request completes, but it is scoped to the
       machine. Relying on it alone meant the choice followed whoever signed in
       next on a shared computer and vanished on a second device - which is the
       opposite of what someone pressing that button is asking for. The server
       value, read into data-mascot-hidden by the view, is the one that survives a
       logout and a fresh login.

       The mirror is keyed per account anyway. That is belt and braces on top of
       treating the server as authoritative below, and it matters for the window
       between logging out and logging back in: without the scope, the previous
       person's hidden flag was still sitting in localStorage under a key the next
       person would read.

       localStorage is deliberately NOT keyed by page: hiding Tappy is meant to be
       whole-account, not a per-page setting. */
    var accountScope = dock.getAttribute('data-mascot-user') || '';
    var hiddenKey = DOCK_KEY_PREFIX + '.hidden.v1' + (accountScope ? '.' + accountScope : '');
    var visibilityUrl = dock.getAttribute('data-mascot-visibility-url') || '';
    var csrfName = dock.getAttribute('data-mascot-csrf-name') || '';
    var csrfHash = dock.getAttribute('data-mascot-csrf-hash') || '';

    // Signed-in people have an account that remembers this, so the account's own
    // answer wins. Only guests - who have nothing to store it against - fall back
    // to the browser copy. Reading the mirror for a signed-in user would let one
    // account's choice override another on a shared machine, which is the bug the
    // server round trip was added to remove.
    var startsHidden = visibilityUrl
      ? dock.getAttribute('data-mascot-hidden') === '1'
      : storageGet('localStorage', hiddenKey) === '1';

    /** Show the dock and hide the restore button, or the other way round. */
    function applyHidden(hidden) {
      // artReady() consults startsHidden when the artwork finally loads. It has
      // to see the CURRENT choice, not the one this page was served with, or a
      // Tappy hidden during a slow image load springs back into view a second
      // after being dismissed.
      startsHidden = hidden;

      if (hidden) {
        hidePanel();
        dock.setAttribute('hidden', '');
      } else {
        dock.removeAttribute('hidden');
      }

      if (restoreBtn) {
        if (hidden) {
          restoreBtn.removeAttribute('hidden');
        } else {
          restoreBtn.setAttribute('hidden', '');
        }
      }
    }

    /**
     * Remember the choice locally at once, and tell the server when we can.
     *
     * The local copy lands first so the page is in the right state straight away,
     * but it is not treated as proof that the save worked. A request that comes
     * back 401/422/500 is a real failure - the account still says the old thing -
     * so the optimistic state is rolled back and the person is told, rather than
     * discovering on their next page load that Tappy came back.
     */
    function persistHidden(hidden) {
      storageSet('localStorage', hiddenKey, hidden ? '1' : '0');

      // Guests have no account to store anything against, so there is nothing to
      // post to and the local copy is the whole of it.
      if (!visibilityUrl || !csrfName) { return; }

      try {
        var body = new URLSearchParams();
        body.append('hidden', hidden ? '1' : '0');
        body.append(csrfName, csrfHash);

        window.fetch(visibilityUrl, {
          method: 'POST',
          body: body,
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
          .then(function (res) {
            // A 4xx/5xx still resolves, so the rejection handler below never sees
            // it. Checking res.ok is what turns a failed save into a visible
            // failure instead of a silent one.
            if (res && res.ok) { return null; }

            throw new Error('mascot visibility save failed: ' + (res ? res.status : 'no response'));
          })
          ['catch'](function () {
            /* The account did not take the change. Put the page back the way the
               account actually describes it, so what is on screen is true, and let
               the person know why the character is still there. */
            applyHidden(!hidden);
            storageSet('localStorage', hiddenKey, hidden ? '0' : '1');
            reportSaveFailed();
          });
      } catch (e) {
        /* fetch unavailable (very old browser): local copy only. */
      }
    }

    /**
     * Tell the person their choice did not stick.
     *
     * Silent failure here is the worst option: the page would show Tappy still
     * present with no explanation, and the natural conclusion is that the button
     * is broken.
     */
    function reportSaveFailed() {
      if (typeof window.Tappy === 'object' && window.Tappy !== null && typeof window.Tappy.onSaveFailed === 'function') {
        window.Tappy.onSaveFailed();
        return;
      }

      if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('Tappy: could not save the hide/restore choice.');
      }
    }

    // The account already says he is hidden, or this browser hid him and the
    // server has not been told yet. Either way he stays away until restored.
    var greetedKey = DOCK_KEY_PREFIX + '.greeted.' + key;
    var failed = false;
    var timer = null;
    var hintTimer = null;

    function hidePanel() {
      panel.hidden = true;
      window.clearTimeout(timer);
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
    }

    /**
     * @param {{sticky?:boolean, typed?:boolean}} opts
     *   sticky - stay open until dismissed (the user asked for it)
     *   typed  - reveal the text character by character
     */
    function showPanel(opts) {
      var options = opts || {};
      panel.hidden = false;
      if (trigger) trigger.setAttribute('aria-expanded', 'true');
      if (hint) hint.hidden = true;

      if (options.typed && textEl) {
        typeInto(textEl, textEl.textContent || '');
      }

      window.clearTimeout(timer);

      if (!options.sticky) {
        timer = window.setTimeout(hidePanel, DOCK_AUTO_HIDE_MS);
      }
    }

    /**
     * Bring Tappy back without needing a fresh page load.
     *
     * Exposed so a view can use the dock without the dock having to know anything
     * about the page it is sitting on.
     *
     * @param {{pose?:string, title?:string, text?:string, sticky?:boolean}} options
     */
    function say(options) {
      var opts = options || {};
      var pose = String(opts.pose || '').toLowerCase();

      if (typeof opts.title === 'string' && titleEl) { titleEl.textContent = opts.title; }
      if (typeof opts.text === 'string' && textEl) { textEl.textContent = opts.text; }

      if (pose && art && /^[a-z0-9-]+$/.test(pose)) {
        // Restart the pop by removing and re-adding the class, so a pose change
        // reads as movement rather than a hard cut.
        art.className = 'mascot mascot--' + pose + ' mascot-dock__art mascot--pop';
        window.setTimeout(function () {
          art.classList.remove('mascot--pop');
        }, 400);
        img.src = url(pose);
      }

      showPanel({ sticky: opts.sticky === true });
    }

    for (var c = 0; c < closes.length; c++) {
      closes[c].addEventListener('click', function () {
        hidePanel();
        storageSet('localStorage', dismissedKey, '1');
      });
    }

    // The X on the bubble means "hide Tappy", not "close this bubble": it takes
    // the character off the page and remembers that on the account. Closing the
    // bubble alone is the "Not now" action, which is why the two are separate
    // handlers rather than one shared event.
    for (var h = 0; h < hideBtns.length; h++) {
      hideBtns[h].addEventListener('click', function () {
        if (hintTimer) { window.clearTimeout(hintTimer); }
        applyHidden(true);
        persistHidden(true);
      });
    }

    if (restoreBtn) {
      restoreBtn.addEventListener('click', function () {
        applyHidden(false);
        persistHidden(false);
      });
    }

    // The tour replay that replaced the "Take a tour" button in the top bar.
    for (var t = 0; t < tourBtns.length; t++) {
      tourBtns[t].addEventListener('click', function () {
        hidePanel();
        if (typeof window.__startMascotTour === 'function') {
          window.__startMascotTour();
        }
      });
    }

    function openOnDemand() {
      window.clearTimeout(hintTimer);
      if (hint) hint.hidden = true;
      // Sticky: this one has buttons in it, so it must not time out.
      showPanel({ typed: true });
      if (trigger) trigger.focus({ preventScroll: true });
    }

    if (trigger) {
      trigger.addEventListener('click', function () {
        if (panel.hidden) {
          openOnDemand();
        } else {
          hidePanel();
        }
      });
    }

    if (hint) {
      hint.textContent = DOCK_HINT_TEXT;
      hint.addEventListener('click', openOnDemand);
    }

    function artReady() {
      if (failed) return;

      // Checked BEFORE the dock is revealed. artReady() is the one place that
      // removes `hidden`, so without this guard it would put Tappy back on screen
      // on every page load and undo both the account preference and the restore
      // button's whole reason for existing.
      if (startsHidden) {
        applyHidden(true);
        return;
      }

      dock.removeAttribute('hidden');

      // Already dismissed, or already greeted earlier in this session: Tappy
      // stays on the page but says nothing, and offers the quiet hint instead.
      if (storageGet('localStorage', dismissedKey) === '1' || storageGet('sessionStorage', greetedKey) === '1') {
        hidePanel();

        if (storageGet('localStorage', dismissedKey) !== '1' && hint) {
          hintTimer = window.setTimeout(function () {
            if (panel.hidden) hint.hidden = false;
          }, DOCK_HINT_AFTER_MS);
        }
        return;
      }

      storageSet('sessionStorage', greetedKey, '1');
      showPanel({ typed: true });
    }

    img.addEventListener('load', artReady);
    img.addEventListener('error', function () { failed = true; });

    // A cached image can finish before these listeners are attached, so the
    // ready state has to be checked rather than waited for.
    if (img.complete) {
      if (img.naturalWidth > 0) {
        artReady();
      } else {
        failed = true;
      }
    }

    if (!failed) {
      window.__mascotSay = say;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
