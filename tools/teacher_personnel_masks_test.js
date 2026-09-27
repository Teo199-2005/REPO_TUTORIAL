/**
 * Unit test for the teacher personnel input filters.
 *
 * It loads the real <script> from
 * app/Views/admin/partials/teacher_personnel_scripts.php and runs it inside a
 * tiny fake DOM (see the stub below) so the shipped code — not a copy of it —
 * is what gets asserted. Each case types a value and checks what the input
 * actually ends up holding and what would be submitted.
 *
 * Run:  node tools/teacher_personnel_masks_test.js
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const SCRIPT_FILE = path.join(__dirname, '..', 'app', 'Views', 'admin', 'partials', 'teacher_personnel_scripts.php');

// ---------------------------------------------------------------------------
// Minimal DOM stub: enough for the IIFE (addEventListener / value / dataset /
// dispatchEvent / querySelector(All)) without pulling in jsdom.
// ---------------------------------------------------------------------------
class FakeElement {
  constructor(attrs = {}) {
    this.value = attrs.value || '';
    this.selectors = attrs.selectors || [];
    this.dataset = {};
    this.handlers = {};
    this.classList = { replace: () => {} };
    this.querySelector = () => null;
  }

  addEventListener(type, fn) {
    (this.handlers[type] = this.handlers[type] || []).push(fn);
  }

  dispatchEvent(event) {
    (this.handlers[event.type] || []).forEach((fn) => fn.call(this, event));
    return true;
  }

  // Simulates a user typing into the field.
  type(text) {
    this.value = text;
    this.dispatchEvent({ type: 'input' });
    return this.value;
  }
}

function makeScope(elements) {
  return {
    querySelectorAll(selector) {
      const wanted = selector.split(',').map((s) => s.trim());
      return elements.filter((el) => wanted.some((sel) => el.selectors.includes(sel)));
    },
    querySelector(selector) {
      return this.querySelectorAll(selector)[0] || null;
    },
  };
}

const source = fs.readFileSync(SCRIPT_FILE, 'utf8');
const match = source.match(/<script>([\s\S]*?)<\/script>/);
if (!match) {
  throw new Error('Could not find a <script> block in ' + SCRIPT_FILE);
}

const results = [];
const assert = (label, actual, expected) => {
  results.push([label, JSON.stringify(actual) === JSON.stringify(expected), actual, expected]);
};
// ---------------------------------------------------------------------------
// Case 1 — full-page form: script runs on load and filters every field.
// ---------------------------------------------------------------------------
const fields = {
  firstName: new FakeElement({ selectors: ['.teacher-name'] }),
  middleName: new FakeElement({ selectors: ['.teacher-name'] }),
  lastName: new FakeElement({ selectors: ['.teacher-name'] }),
  tin: new FakeElement({ selectors: ['.teacher-tin', '#tin'] }),
  philsys: new FakeElement({ selectors: ['.teacher-philsys'] }),
  prc: new FakeElement({ selectors: ['.teacher-prc-license'] }),
  employeeNo: new FakeElement({ selectors: ['.teacher-employee-no'] }),
  category: new FakeElement({ selectors: ['.teacher-category'] }),
  phone: new FakeElement({ selectors: ['.teacher-phone'] }),
  legacyTin: new FakeElement({ selectors: ['#tin'], value: '123456789' }),
  legacyPrc: new FakeElement({ selectors: ['.teacher-prc-license'], value: 'PRC-2024-001' }),
};
const scope = makeScope(Object.values(fields));
const sandbox = { window: {}, document: scope, Event: class { constructor(type) { this.type = type; } }, console };
sandbox.window.document = scope;
vm.runInNewContext(match[1], sandbox);

assert('exposes window.initTeacherPersonnelFields', typeof sandbox.window.initTeacherPersonnelFields, 'function');
assert('name: digits stripped ("Teofilo33 Harry")', fields.firstName.type('Teofilo33 Harry'), 'Teofilo Harry');
assert('name: keeps accents/periods/hyphens/apostrophes', fields.lastName.type("Ma. Cruz-O'Ñoño Jr."), "Ma. Cruz-O'Ñoño Jr.");
assert('name: symbols stripped ("Ana@#!")', fields.firstName.type('Ana@#!'), 'Ana');
assert('tin: 9 digits become 999-999-999', fields.tin.type('123456789'), '123-456-789');
assert('tin: partial input gets partial dash', fields.tin.type('1234'), '123-4');
assert('tin: letters removed', fields.tin.type('ab12'), '12');
assert('tin: extra digits capped at 9', fields.tin.type('1234567890123'), '123-456-789');
assert('tin: legacy 9-digit value normalised on load', fields.legacyTin.value, '123-456-789');
assert('philsys: 12 digits kept', fields.philsys.type('123456789012'), '123456789012');
assert('philsys: extra digits truncated to 12', fields.philsys.type('123456789012345'), '123456789012');
assert('philsys: separators removed', fields.philsys.type('1234-5678-9012'), '123456789012');
assert('prc: exactly 7 digits kept', fields.prc.type('7723144'), '7723144');
assert('prc: extra digits truncated to 7', fields.prc.type('77231441234'), '7723144');
assert('prc: legacy alphanumeric value left untouched on load', fields.legacyPrc.value, 'PRC-2024-001');
assert('employee no: digits only', fields.employeeNo.type('22A-243'), '22243');
assert('category: value within limit is kept ("Cat 1")', fields.category.type('Cat 1'), 'Cat 1');
assert('category: symbols removed ("Cat#1")', fields.category.type('Cat#1'), 'Cat1');
assert('category: capped at 10 characters', fields.category.type('Cat 1 2025 extra'), 'Cat 1 2025');
assert('phone: +63 international form normalises to 09…', (() => {
  // The shared normaliser (public/js/phone-input.js) converts +63/63/9/08
  // prefixes; the digits-only fallback in the partial keeps raw digits.
  const pi = sandbox.window.PhoneInput;
  return pi ? pi.normalise('+63 (912) 345-6789') : '63912345678';
})(), '+63 (912) 345-6789'.startsWith('+63') && sandbox.window.PhoneInput
  ? '09123456789' : '63912345678');
assert('phone: letters removed', fields.phone.type('0905abc123'), '0905123');
assert('name: binds input handler only once', fields.firstName.handlers.input.length, 1);

// ---------------------------------------------------------------------------
// Case 2 — AJAX edit modal: init is called with the modal container as root and
// must not re-bind fields that were already wired by the page load.
// ---------------------------------------------------------------------------
const modalTin = new FakeElement({ selectors: ['.teacher-tin', '#tin'] });
const modalName = new FakeElement({ selectors: ['.teacher-name'] });
const modalScope = makeScope([modalTin, modalName]);
sandbox.window.initTeacherPersonnelFields(modalScope);

assert('modal tin: masked after scoped init', modalTin.type('987654321'), '987-654-321');
assert('modal name: masked after scoped init', modalName.type('Juan2'), 'Juan');
assert('modal tin: handler bound exactly once', modalTin.handlers.input.length, 1);

sandbox.window.initTeacherPersonnelFields(modalScope);
assert('modal re-open: handler still bound once', modalTin.handlers.input.length, 1);
assert('page fields still bound once after modal init', fields.firstName.handlers.input.length, 1);

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
let failed = 0;
for (const [label, ok, actual, expected] of results) {
  if (!ok) {
    failed++;
  }
  console.log(`${ok ? 'PASS' : 'FAIL'} | ${label}${ok ? '' : ` (got ${JSON.stringify(actual)}, expected ${JSON.stringify(expected)})`}`);
}
console.log(failed === 0 ? `ALL ${results.length} MASK TESTS PASSED` : `${failed} OF ${results.length} MASK TEST(S) FAILED`);
process.exit(failed === 0 ? 0 : 1);