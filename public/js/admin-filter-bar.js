/**
 * Admin filter bar — auto-submit behaviour.
 *
 * The admin standard is that filter bars have no "Apply" button: changing a
 * filter immediately reloads the list. That is only safe if we do not submit on
 * every keystroke, so:
 *
 *   - <select>            submits immediately on change
 *   - checkbox / radio    submits immediately on change
 *   - <input type=text|search|number|date>  submits 400ms after the last
 *                         keystroke, and immediately on Enter
 *
 * Forms opt in with the .admin-filter-form class (or data-admin-filter), so
 * this never touches unrelated forms elsewhere in the app.
 */
(function () {
  'use strict';

  var DEBOUNCE_MS = 400;

  function isFilterForm(form) {
    if (!form) return false;
    if (form.dataset && form.dataset.adminFilter !== undefined) return true;
    return form.classList && form.classList.contains('admin-filter-form');
  }

  /** True for free-text style inputs, which need debouncing. */
  function needsDebounce(el) {
    if (!el || el.tagName !== 'INPUT') return false;
    var type = (el.getAttribute('type') || 'text').toLowerCase();
    return ['text', 'search', 'email', 'url', 'tel', 'number', 'date', 'datetime-local'].indexOf(type) !== -1;
  }

  function submit(form) {
    if (form.dataset && form.dataset.adminSubmitting === '1') return;
    if (form.dataset) form.dataset.adminSubmitting = '1';
    form.submit();
  }

  function enhance(form) {
    if (form.dataset && form.dataset.adminFilterEnhanced === '1') return;
    if (form.dataset) form.dataset.adminFilterEnhanced = '1';

    // Drop a page= parameter so a filtered list returns to the first page.
    var page = form.querySelector('input[name="page"]');
    if (page && page.value) page.value = '';

    var timer = null;

    form.addEventListener('change', function (e) {
      if (e.target && e.target.name === 'page') return;
      if (timer) { clearTimeout(timer); timer = null; }
      submit(form);
    });

    form.addEventListener('input', function (e) {
      if (!needsDebounce(e.target)) return;
      if (timer) clearTimeout(timer);
      timer = setTimeout(function () {
        timer = null;
        submit(form);
      }, DEBOUNCE_MS);
    });

    form.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      if (e.target && e.target.tagName === 'TEXTAREA') return;
      e.preventDefault();
      if (timer) { clearTimeout(timer); timer = null; }
      submit(form);
    });

    // Keep the "More Filters" panel's open/closed choice across reloads so a
    // user who opened it does not have to open it again on every filter change.
    var toggle = form.querySelector('[data-admin-filter-toggle]');
    var panel = toggle ? document.getElementById(toggle.getAttribute('data-admin-filter-target')) : null;
    if (toggle && panel) {
      var KEY = 'adminFiltersOpen';
      try {
        if (window.sessionStorage.getItem(KEY) === '1') {
          panel.classList.add('show');
          toggle.setAttribute('aria-expanded', 'true');
        }
        toggle.addEventListener('click', function () {
          window.setTimeout(function () {
            try {
              window.sessionStorage.setItem(KEY, panel.classList.contains('show') ? '1' : '0');
            } catch (err) { /* storage unavailable — non-fatal */ }
          }, 0);
        });
      } catch (err) { /* storage blocked — non-fatal */ }
    }
  }

  function init() {
    var forms = document.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) {
      if (isFilterForm(forms[i])) enhance(forms[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
