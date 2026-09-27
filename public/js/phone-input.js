/**
 * Shared Philippine mobile number input helper.
 *
 * Every contact-number field in the app stores plain 09XXXXXXXXX: the prefix
 * "09" is inserted automatically and only 9 more digits can be typed, so the
 * value is always exactly 11 digits. The same normalising rules live on the
 * server in app/Helpers/phone_helper.php (phone_normalize()).
 *
 * Fields are picked up automatically by name (contact_number,
 * emergency_contact_number, phone) or explicitly with data-phone="1":
 *
 *   <input type="text" class="form-control" name="contact_number"
 *          maxlength="11" inputmode="numeric" placeholder="09XXXXXXXXX">
 *
 * window.PhoneInput.enhanceAll(root) must be re-run for markup injected later
 * (modals built with innerHTML), because inline <script> blocks never execute
 * there.
 */
(function () {
  'use strict';

  var PREFIX = '09';
  var MAX_DIGITS = 11;
  var SELECTOR = 'input[data-phone], input[name="contact_number"], '
    + 'input[name="emergency_contact_number"], input[name="phone"]';

  // Any user typed/pasted value -> canonical 09XXXXXXXXX.
  //  +639171234567 / 639171234567 -> 09171234567
  //  9171234567                   -> 09171234567
  //  0917-123-4567 / 0917 123 4567-> 09171234567
  //  ""                           -> "" (lets required/optional checks work)
  function normalise(value) {
    var digits = String(value == null ? '' : value).replace(/\D/g, '');

    if (digits === '') {
      return '';
    }

    // Country code first, then a leading 9 typed without its 0.
    if (digits.indexOf('63') === 0 && digits.length >= 12) {
      digits = '0' + digits.slice(2);
    }
    if (digits.charAt(0) === '9') {
      digits = '0' + digits;
    }

    // Whatever is left is the subscriber part; the field always starts with 09.
    var rest;
    if (digits.indexOf(PREFIX) === 0) {
      rest = digits.slice(2);
    } else if (digits.charAt(0) === '0') {
      rest = digits.slice(1);
    } else {
      rest = digits;
    }

    return PREFIX + rest.slice(0, MAX_DIGITS - PREFIX.length);
  }

  function isValid(value) {
    return /^09\d{9}$/.test(normalise(value));
  }

  function enhance(input) {
    if (!input || !input.addEventListener || input.dataset.phoneEnhanced === '1') {
      return;
    }
    input.dataset.phoneEnhanced = '1';

    input.setAttribute('maxlength', String(MAX_DIGITS));
    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('autocomplete', 'tel-national');
    if (!input.getAttribute('placeholder')) {
      input.setAttribute('placeholder', '09XXXXXXXXX');
    }

    // Auto-start: an empty field already shows the 09 prefix.
    input.addEventListener('focus', function () {
      if (this.value === '') {
        this.value = PREFIX;
        if (typeof this.setSelectionRange === 'function') {
          var end = this.value.length;
          try {
            this.setSelectionRange(end, end);
          } catch (err) {}
        }
      }
    });

    input.addEventListener('input', function () {
      this.value = normalise(this.value);
    });

    // Leaving the field with only the untouched prefix clears it so the
    // browser's required/pattern checks and the server both see "empty".
    input.addEventListener('blur', function () {
      var value = normalise(this.value);
      this.value = value === PREFIX ? '' : value;
    });

    // Legacy values (0917-123-4567, +63 917 …, 9171234567) load as plain digits.
    if (input.value) {
      input.value = normalise(input.value);
    }
  }

  function enhanceAll(root) {
    var scope = root && typeof root.querySelectorAll === 'function' ? root : document;

    scope.querySelectorAll(SELECTOR).forEach(enhance);

    // The scope itself can be the input (e.g. an injected single field).
    if (scope.nodeType === 1 && scope.matches && scope.matches(SELECTOR)) {
      enhance(scope);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    enhanceAll(document);
  });

  window.PhoneInput = {
    PREFIX: PREFIX,
    MAX_DIGITS: MAX_DIGITS,
    normalise: normalise,
    isValid: isValid,
    enhance: enhance,
    enhanceAll: enhanceAll
  };
})();
