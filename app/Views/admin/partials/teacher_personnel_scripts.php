<script>
/**
 * Teacher personnel form input filters.
 *
 * These filters mirror the *server-side* rules in
 * app/Helpers/teacher_form_helper.php (teacher_store_validation_rules) so the
 * browser never lets the user type something the server would reject:
 *
 *   first/last/middle name : Unicode letters, marks, spaces, . ' - only
 *   license_number         : exactly 7 digits
 *   tin                    : 9 digits, displayed as 999-999-999 (legacy
 *                            9-digit values are normalised on load)
 *   philsys_number         : exactly 12 digits
 *   government_employee_no : digits only (max 20)
 *   personnel_category     : letters, digits and spaces (max 10)
 *   contact_number         : 09 + 9 digits, delegated to window.PhoneInput
 *                            (public/js/phone-input.js) with a digits-only
 *                            fallback when that helper is not loaded
 *
 * window.initTeacherPersonnelFields(root) is exposed so pages that inject the
 * edit form through AJAX (see admin/teachers.php) can re-run the filters —
 * <script> tags inside HTML assigned with innerHTML never execute.
 */
(function () {
  // Server rule: regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]
  var NAME_DISALLOWED = /[^\p{L}\p{M}\s.'\u2019-]/gu;

  // Never bind the same input twice (init can run on page load *and* every time
  // an edit modal is opened).
  function firstBinding(el, flag) {
    var key = 'tpf' + flag;
    if (el.dataset[key] === '1') {
      return false;
    }
    el.dataset[key] = '1';
    return true;
  }

  function restrict(el, pattern, maxLen, formatter) {
    el.addEventListener('input', function () {
      var value = String(this.value).replace(pattern, '');
      if (maxLen) {
        value = value.slice(0, maxLen);
      }
      if (formatter) {
        value = formatter(value);
      }
      this.value = value;
    });
  }

  function tinFormatter(value) {
    var d = value.replace(/\D/g, '').slice(0, 9);
    if (d.length <= 3) {
      return d;
    }
    if (d.length <= 6) {
      return d.slice(0, 3) + '-' + d.slice(3);
    }
    return d.slice(0, 3) + '-' + d.slice(3, 6) + '-' + d.slice(6);
  }

    // `normalise()` replays the input filter over any value the server already
    // rendered. It is only safe for fields where the normalised value is exactly
    // equivalent to the stored one — i.e. the TIN, where the server accepts both
    // "123456789" and "123-456-789", so adding the dashes cannot alter data.
    // It is deliberately NOT applied to PRC License Number: legacy identifiers
    // such as "PRC-2024-001" (used by the demo teacher logins) are alphanumeric,
    // and stripping the letters on page load would silently rewrite the record.
    function normalise(el) {
      if (el.value) {
        el.dispatchEvent(new Event('input'));
      }
    }

  // Religion selector: reveal the "Other" free-text box only when needed.
  // While hidden the box is disabled (a stale value is never submitted) and
  // drops `required`; the server resolves the choice into the typed text.
  function syncReligionOther(select) {
    var field = select.closest('.religion-field');
    if (!field) return;
    var wrapper = field.querySelector('.religion-other');
    var input = field.querySelector('input[name="religion_other"]');
    if (!wrapper || !input) return;
    var isOther = select.value === 'Other';
    wrapper.classList.toggle('is-visible', isOther);
    field.classList.toggle('is-other', isOther);
    input.required = isOther;
    input.disabled = !isOther;
  }

  window.initTeacherPersonnelFields = function (root) {
    var scope = (root && typeof root.querySelectorAll === 'function') ? root : document;

    // The Address control is built by the shared location selector. Pages that
    // inject this form through AJAX (admin/teachers.php's edit modal) never run
    // the partial's own <script>, so initialise the selector here too —
    // otherwise the Province/City/Municipality/Barangay boxes stay empty.
    // On full page loads the selector is not defined yet at parse time; its own
    // DOMContentLoaded init builds the controls instead.
    if (window.LocSelect && typeof window.LocSelect.init === 'function') {
      window.LocSelect.init(scope);
    }

    scope.querySelectorAll('.teacher-name').forEach(function (el) {
      if (!firstBinding(el, 'Name')) return;
      restrict(el, NAME_DISALLOWED);
    });

    scope.querySelectorAll('.teacher-religion').forEach(function (el) {
      if (!firstBinding(el, 'Religion')) return;
      el.addEventListener('change', function () {
        syncReligionOther(el);
      });
      syncReligionOther(el);
    });

    scope.querySelectorAll('.teacher-tin, #tin').forEach(function (el) {
      if (!firstBinding(el, 'Tin')) return;
      restrict(el, /\D/g, null, tinFormatter);
      normalise(el);
    });

    scope.querySelectorAll('.teacher-philsys').forEach(function (el) {
      if (!firstBinding(el, 'Philsys')) return;
      restrict(el, /\D/g, 12);
    });

    scope.querySelectorAll('.teacher-prc-license').forEach(function (el) {
      if (!firstBinding(el, 'Prc')) return;
      restrict(el, /\D/g, 7);
    });

    scope.querySelectorAll('.teacher-employee-no').forEach(function (el) {
      if (!firstBinding(el, 'Employee')) return;
      restrict(el, /\D/g, 20);
    });

    scope.querySelectorAll('.teacher-category').forEach(function (el) {
      if (!firstBinding(el, 'Category')) return;
      restrict(el, /[^A-Za-z0-9\s]/g, 10);
    });

    scope.querySelectorAll('.teacher-phone').forEach(function (el) {
      if (!firstBinding(el, 'Phone')) return;
      el.addEventListener('input', function () {
        // Normalise at event time: phone-input.js loads after this partial on
        // full-page forms, so prefer the shared helper whenever it exists.
        if (window.PhoneInput) {
          this.value = window.PhoneInput.normalise(this.value);
          return;
        }
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
      });
      if (window.PhoneInput && el.value) {
        el.value = window.PhoneInput.normalise(el.value);
      }
    });

    var toggle = scope.querySelector ? scope.querySelector('#togglePassword') : null;
    if (toggle && firstBinding(toggle, 'PasswordToggle')) {
      toggle.addEventListener('click', function () {
        var passwordField = document.getElementById('password');
        var toggleIcon = document.getElementById('toggleIcon');
        if (!passwordField) return;
        if (passwordField.type === 'password') {
          passwordField.type = 'text';
          if (toggleIcon) toggleIcon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
          passwordField.type = 'password';
          if (toggleIcon) toggleIcon.classList.replace('bi-eye-slash', 'bi-eye');
        }
      });
    }
  };

  // Full-page forms (create / edit pages) render this partial server-side.
  window.initTeacherPersonnelFields(document);
})();
</script>
