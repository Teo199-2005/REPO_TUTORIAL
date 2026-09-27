/**
 * Shared Philippine location selector (PSA PSGC 2025-2Q dataset).
 *
 * Converts a `data-loc-group` placeholder into:
 * Province selector -> City/Municipality selector -> Barangay textbox.
 * The three controls compose into a hidden input as:
 * "Barangay <barangay>, <city>, <province>".
 *
 * Expected markup:
 * <input type="hidden" name="address" id="address" value="stored value">
 * <label for="address">…</label>
 * <div data-loc-group="address" data-loc-required data-loc-field="address"></div>
 *
 * Styling lives in public/css/location-selector.css (.loc-group-row / .loc-col).
 * The generated wrapper is a plain flex row rather than Bootstrap columns: the
 * three controls keep a readable minimum width and wrap onto their own line
 * instead of being clipped inside narrow grid columns or modal bodies.
 *
 * Optional captions replace the default control labels so a form can drop its
 * own heading and still keep the meaning of the three fields:
 * <div data-loc-group="place_of_birth" data-loc-required
 *      data-loc-labels="Province of Birth|City/Municipality of Birth|Barangay of Birth"></div>
 *
 * The generated controls carry autocomplete="off": browsers otherwise treat the
 * first stray text box of a sign-up form as a username field and drop a saved
 * e-mail address into the Barangay box. E-mail looking values are never shown
 * as a barangay either.
 */
(function () {
  'use strict';

  var BARANGAY_MAX_LENGTH = 150;
  var PROVINCE_PLACEHOLDER = 'Select province';
  var CITY_WAIT_PLACEHOLDER = 'Select province first';
  var CITY_PLACEHOLDER = 'Select city/municipality';
  var BARANGAY_PLACEHOLDER = 'Enter barangay';

  function dataset() {
    return (window.PSGC_DATA && typeof window.PSGC_DATA === 'object')
      ? window.PSGC_DATA
      : {};
  }

  // "Cauayan City" ~ "City of Cauayan": lowercase, strip accents/punctuation,
  // remove leading "city of " and trailing " city".
  function normalise(value) {
    var text = String(value == null ? '' : value).toLowerCase();
    try {
      text = text.normalize('NFD').replace(/[̀-ͯ]/g, '');
    } catch (err) {}
    text = text.replace(/[^a-z0-9\s]/g, ' ').replace(/\s+/g, ' ').trim();
    text = text.replace(/^city of\s+/, '').replace(/\s+city$/, '').trim();
    return text;
  }

  function provinceNames() {
    return Object.keys(dataset()).sort(function (a, b) {
      return a.localeCompare(b);
    });
  }

  function provinceByDisplay(name) {
    var key = String(name == null ? '' : name).toLowerCase().trim();
    if (key === '') {
      return null;
    }
    var names = provinceNames();
    for (var i = 0; i < names.length; i++) {
      if (names[i].toLowerCase() === key) {
        return names[i];
      }
    }
    return null;
  }

  function cityInProvince(province, name) {
    var target = normalise(name);
    if (target === '') {
      return null;
    }
    var list = dataset()[province] || [];
    var fallback = null;
    for (var i = 0; i < list.length; i++) {
      if (list[i] === name) {
        return list[i];
      }
      if (normalise(list[i]) === target && fallback === null) {
        fallback = list[i];
      }
    }
    return fallback;
  }

  function compose(parts) {
    var chunks = [];
    if (parts.barangay !== '') {
      chunks.push(parts.barangay);
    }
    // HUCs list themselves as their own single "city" (e.g. City of Angeles
    // -> ["City of Angeles"]); skip the duplicated name.
    if (parts.city !== '' && parts.city !== parts.province) {
      chunks.push(parts.city);
    }
    if (parts.province !== '') {
      chunks.push(parts.province);
    }
    return chunks.join(', ');
  }

  // Browsers sometimes drop a saved e-mail address (or username) into the first
  // stray text box of a sign-up form. That is autofill noise, never a location,
  // so it is discarded instead of being shown and submitted as a barangay.
  function looksLikeContactNoise(text) {
    return String(text).indexOf('@') !== -1;
  }

  function parse(value) {
    var parsed = parseValue(value);
    if (looksLikeContactNoise(parsed.barangay)) {
      parsed.barangay = '';
    }
    return parsed;
  }

  // "Barangay X, City Y, Province Z" -> { province, city, barangay }.
  // Anything that does not parse is preserved in the barangay box.
  function parseValue(value) {
    var empty = { province: '', city: '', barangay: '' };
    var text = String(value == null ? '' : value).trim();
    if (text === '') {
      return empty;
    }
    var parts = text.split(',').map(function (part) {
      return part.trim();
    }).filter(function (part) {
      return part !== '';
    });
    if (parts.length === 0) {
      return empty;
    }

    for (var i = parts.length - 1; i >= Math.max(0, parts.length - 3); i--) {
      var province = provinceByDisplay(parts[i]);
      if (province === null) {
        continue;
      }
      var rest = parts.slice(0, i);
      if (rest.length === 0) {
        return { province: province, city: '', barangay: '' };
      }
      var city = cityInProvince(province, rest[rest.length - 1]);
      if (city !== null) {
        return {
          province: province,
          city: city,
          barangay: rest.slice(0, rest.length - 1).join(', ')
        };
      }
      var single = dataset()[province] || [];
      if (rest.length >= 1 && single.length === 1 && single[0] === province) {
        // HUC written without repeating itself, e.g. "Poblacion, City of Angeles".
        return { province: province, city: single[0], barangay: rest.join(', ') };
      }
      return { province: province, city: '', barangay: rest.join(', ') };
    }
    return { province: '', city: '', barangay: text };
  }

  function makeOption(select, value, label) {
    var option = document.createElement('option');
    option.value = value;
    option.textContent = label;
    select.appendChild(option);
  }

  function fillProvinces(select, current) {
    select.innerHTML = '';
    makeOption(select, '', PROVINCE_PLACEHOLDER);
    provinceNames().forEach(function (name) {
      makeOption(select, name, name);
    });
    select.value = provinceByDisplay(current) || '';
  }

  function fillCities(select, province, current) {
    select.innerHTML = '';
    var list = province ? dataset()[province] || [] : [];
    if (!province) {
      makeOption(select, '', CITY_WAIT_PLACEHOLDER);
      select.value = '';
      select.disabled = true;
      return;
    }
    makeOption(select, '', CITY_PLACEHOLDER);
    list.forEach(function (name) {
      makeOption(select, name, name);
    });
    var match = cityInProvince(province, current);
    select.value = match === null ? '' : match;
    select.disabled = false;
  }

  var DEFAULT_LABELS = {
    province: 'Province',
    city: 'City/Municipality',
    barangay: 'Barangay'
  };

  function customLabel(value, fallback) {
    var text = String(value == null ? '' : value).trim();
    return text === '' ? fallback : text;
  }

  // Optional captions, e.g.
  // data-loc-labels="Province of Birth|City/Municipality of Birth|Barangay of Birth"
  function groupLabels(group) {
    var raw = String(group.getAttribute('data-loc-labels') || '').split('|');
    return {
      province: customLabel(raw[0], DEFAULT_LABELS.province),
      city: customLabel(raw[1], DEFAULT_LABELS.city),
      barangay: customLabel(raw[2], DEFAULT_LABELS.barangay)
    };
  }

  function buildControls(group) {
    var name = group.getAttribute('data-loc-group') || 'location';
    var field = (group.getAttribute('data-loc-field') || '').trim();
    var required = group.hasAttribute('data-loc-required');
    var labels = groupLabels(group);

    var row = document.createElement('div');
    row.className = 'loc-group-row';

    var provinceCol = document.createElement('div');
    provinceCol.className = 'loc-col';
    var provinceLabel = document.createElement('label');
    provinceLabel.className = 'form-label';
    provinceLabel.textContent = labels.province + (required ? ' *' : '');
    var provinceSelect = document.createElement('select');
    provinceSelect.className = 'form-select loc-province';
    provinceSelect.setAttribute('data-loc-name', name + '_province');
    provinceSelect.setAttribute('aria-label', labels.province);
    provinceSelect.setAttribute('autocomplete', 'off');
    if (required) {
      provinceSelect.required = true;
    }
    if (field !== '') {
      provinceSelect.id = 'loc-' + field + '-province';
      provinceLabel.setAttribute('for', provinceSelect.id);
    }
    provinceCol.appendChild(provinceLabel);
    provinceCol.appendChild(provinceSelect);

    var cityCol = document.createElement('div');
    cityCol.className = 'loc-col';
    var cityLabel = document.createElement('label');
    cityLabel.className = 'form-label';
    cityLabel.textContent = labels.city + (required ? ' *' : '');
    var citySelect = document.createElement('select');
    citySelect.className = 'form-select loc-city';
    citySelect.setAttribute('data-loc-name', name + '_city');
    citySelect.setAttribute('aria-label', labels.city);
    citySelect.setAttribute('autocomplete', 'off');
    if (required) {
      citySelect.required = true;
    }
    if (field !== '') {
      citySelect.id = 'loc-' + field + '-city';
      cityLabel.setAttribute('for', citySelect.id);
    }
    cityCol.appendChild(cityLabel);
    cityCol.appendChild(citySelect);

    var barangayCol = document.createElement('div');
    barangayCol.className = 'loc-col';
    var barangayLabel = document.createElement('label');
    barangayLabel.className = 'form-label';
    barangayLabel.textContent = labels.barangay;
    var barangayInput = document.createElement('input');
    barangayInput.type = 'text';
    barangayInput.className = 'form-control loc-barangay';
    barangayInput.placeholder = BARANGAY_PLACEHOLDER;
    barangayInput.maxLength = BARANGAY_MAX_LENGTH;
    barangayInput.setAttribute('data-loc-name', name + '_barangay');
    barangayInput.setAttribute('aria-label', labels.barangay);
    barangayInput.setAttribute('autocomplete', 'off');
    if (field !== '') {
      barangayInput.id = 'loc-' + field + '-barangay';
      barangayLabel.setAttribute('for', barangayInput.id);
    }
    barangayCol.appendChild(barangayLabel);
    barangayCol.appendChild(barangayInput);

    row.appendChild(provinceCol);
    row.appendChild(cityCol);
    row.appendChild(barangayCol);
    return {
      row: row,
      provinceSelect: provinceSelect,
      citySelect: citySelect,
      barangayInput: barangayInput
    };
  }

  function hiddenField(group) {
    var name = group.getAttribute('data-loc-group');
    var field = (group.getAttribute('data-loc-field') || '').trim();
    if (field !== '') {
      var byId = document.getElementById(field);
      if (byId && byId.getAttribute('name') === name) {
        return byId;
      }
    }
    var form = group.closest('form');
    if (form) {
      var inForm = form.querySelector('input[type="hidden"][name="' + name + '"]');
      if (inForm) {
        return inForm;
      }
    }
    return document.querySelector('input[type="hidden"][name="' + name + '"]');
  }

  function currentParts(group) {
    return {
      province: group.querySelector('.loc-province').value,
      city: group.querySelector('.loc-city').value,
      barangay: group.querySelector('.loc-barangay').value.trim()
    };
  }

  function syncHidden(group) {
    var hidden = hiddenField(group);
    if (hidden) {
      hidden.value = compose(currentParts(group));
    }
  }

  function applyParsed(group, parsed) {
    var provinceSelect = group.querySelector('.loc-province');
    var citySelect = group.querySelector('.loc-city');
    var barangayInput = group.querySelector('.loc-barangay');
    fillProvinces(provinceSelect, parsed.province);
    fillCities(citySelect, provinceSelect.value, parsed.city);
    barangayInput.value = parsed.barangay;
    syncHidden(group);
  }

  function preferredLabel(group) {
    var label = group.getAttribute('data-loc-label');
    if (label && label.trim() !== '') {
      return label.trim();
    }
    return (group.getAttribute('data-loc-group') || 'location')
      .replace(/_/g, ' ')
      .replace(/\b\w/g, function (ch) { return ch.toUpperCase(); });
  }

  function markInvalid(control, invalid) {
    control.classList.toggle('is-invalid', invalid);
    if (invalid) {
      control.setAttribute('aria-invalid', 'true');
    } else {
      control.removeAttribute('aria-invalid');
    }
  }

  function initGroup(group) {
    if (!group || typeof group.getAttribute !== 'function' || !group.hasAttribute('data-loc-group')) {
      return;
    }
    if (group.dataset.locInitialised === '1') {
      return;
    }
    group.dataset.locInitialised = '1';

    var controls = buildControls(group);
    group.appendChild(controls.row);

    var hidden = hiddenField(group);
    applyParsed(group, parse(hidden ? hidden.value : ''));

    controls.provinceSelect.addEventListener('change', function () {
      fillCities(controls.citySelect, controls.provinceSelect.value, '');
      markInvalid(controls.provinceSelect, false);
      markInvalid(controls.citySelect, false);
      syncHidden(group);
    });
    controls.citySelect.addEventListener('change', function () {
      markInvalid(controls.citySelect, false);
      syncHidden(group);
    });
    controls.barangayInput.addEventListener('input', function () {
      syncHidden(group);
    });
  }

  function init(root) {
    var scope = (root && typeof root.querySelectorAll === 'function') ? root : document;
    scope.querySelectorAll('[data-loc-group]').forEach(initGroup);
  }

  function refresh(scope) {
    init(scope || document);
    var holder = (scope && typeof scope.querySelectorAll === 'function') ? scope : document;
    holder.querySelectorAll('[data-loc-group]').forEach(function (group) {
      initGroup(group);
      var hidden = hiddenField(group);
      if (hidden) {
        syncHidden(group);
      }
    });
  }

  function resolveGroup(target) {
    if (typeof target === 'string') {
      var key = target.trim();
      if (key === '') {
        return null;
      }
      // The placeholder can be referenced by its group name or by the id of the
      // hidden field that stores the composed value (the two often differ, and
      // a hidden input may even share the id with the group name).
      var group = document.querySelector('[data-loc-group="' + key + '"]') ||
        document.querySelector('[data-loc-group][data-loc-field="' + key + '"]');
      if (group) {
        return group;
      }
      var field = document.getElementById(key);
      if (!field) {
        return null;
      }
      if (field.getAttribute && field.hasAttribute && field.hasAttribute('data-loc-group')) {
        return field;
      }
      var holder = field.parentNode;
      if (holder && typeof holder.querySelector === 'function') {
        return holder.querySelector('[data-loc-group]');
      }
      return null;
    }
    if (target && target.nodeType === 1) {
      return target.closest ? (target.closest('[data-loc-group]') || target) : target;
    }
    return null;
  }

  function setGroup(target, values) {
    var group = resolveGroup(target);
    if (!group) {
      return false;
    }
    initGroup(group);
    var next = values || {};
    applyParsed(group, {
      province: String(next.province || ''),
      city: String(next.city || ''),
      barangay: String(next.barangay || '')
    });
    return true;
  }

  function getGroup(target) {
    var group = resolveGroup(target);
    if (!group || !group.querySelector('.loc-province')) {
      return { province: '', city: '', barangay: '', composed: '' };
    }
    var parts = currentParts(group);
    return {
      province: parts.province,
      city: parts.city,
      barangay: parts.barangay,
      composed: compose(parts)
    };
  }

  function groupIsValid(group) {
    if (!group.hasAttribute('data-loc-required')) {
      return true;
    }
    var provinceSelect = group.querySelector('.loc-province');
    var citySelect = group.querySelector('.loc-city');
    if (!provinceSelect || !citySelect) {
      return true;
    }
    var provinceOk = provinceSelect.value !== '';
    var cityOk = citySelect.value !== '';
    markInvalid(provinceSelect, !provinceOk);
    markInvalid(citySelect, !cityOk);
    if (!provinceOk) {
      provinceSelect.focus();
      return false;
    }
    if (!cityOk) {
      citySelect.focus();
      return false;
    }
    return true;
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || !form.querySelectorAll) {
      return;
    }
    var groups = form.querySelectorAll('[data-loc-group][data-loc-required]');
    for (var i = 0; i < groups.length; i++) {
      initGroup(groups[i]);
      syncHidden(groups[i]);
      if (!groupIsValid(groups[i])) {
        event.preventDefault();
        if (typeof event.stopImmediatePropagation === 'function') {
          event.stopImmediatePropagation();
        } else {
          event.stopPropagation();
        }
        window.alert('Please select a province and a city/municipality for ' +
          preferredLabel(groups[i]) + ' before submitting.');
        return;
      }
    }
  }, true);

  document.addEventListener('DOMContentLoaded', function () {
    init(document);
  });

  window.LocSelect = {
    init: init,
    refresh: refresh,
    set: setGroup,
    get: getGroup,
    parse: parse,
    compose: compose
  };
})();
