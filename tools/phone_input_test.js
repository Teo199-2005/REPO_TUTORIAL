/**
 * Unit test for the shared Philippine mobile input helper (public/js/phone-input.js).
 *
 * Loads the real script in a minimal fake DOM and asserts normalise()/isValid()
 * for every accepted and rejected format.
 *
 * Run:  node tools/phone_input_test.js
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const SCRIPT_FILE = path.join(__dirname, '..', 'public', 'js', 'phone-input.js');

const sandbox = {
  window: {},
  document: {
    addEventListener() {},
    querySelectorAll: () => [],
    matches: () => false,
    nodeType: 9,
  },
  console,
};
sandbox.window.document = sandbox.document;
vm.runInNewContext(fs.readFileSync(SCRIPT_FILE, 'utf8'), sandbox);

const P = sandbox.window.PhoneInput;
const results = [];
const assert = (label, actual, expected) => {
  results.push([label, JSON.stringify(actual) === JSON.stringify(expected), actual, expected]);
};

// API surface
assert('exposes window.PhoneInput', typeof P, 'object');
assert('normalise is a function', typeof P.normalise, 'function');
assert('isValid is a function', typeof P.isValid, 'function');
assert('enhanceAll is a function', typeof P.enhanceAll, 'function');
assert('PREFIX is 09', P.PREFIX, '09');
assert('MAX_DIGITS is 11', P.MAX_DIGITS, 11);

// normalise: every accepted entry format -> 09XXXXXXXXX
assert('normalise: +639171234567', P.normalise('+639171234567'), '09171234567');
assert('normalise: 639171234567', P.normalise('639171234567'), '09171234567');
assert('normalise: 9171234567 (bare 9)', P.normalise('9171234567'), '09171234567');
assert('normalise: 0917-123-4567 (dashed)', P.normalise('0917-123-4567'), '09171234567');
assert('normalise: 0917 123 4567 (spaced)', P.normalise('0917 123 4567'), '09171234567');
assert('normalise: 0917.123.4567 (dotted)', P.normalise('0917.123.4567'), '09171234567');
assert('normalise: (0917) 123-4567', P.normalise('(0917) 123-4567'), '09171234567');
assert('normalise: canonical is idempotent', P.normalise('09171234567'), '09171234567');
assert('normalise: 08… treated as subscriber digits', P.normalise('08123456789'), '09812345678');
assert('normalise: too long truncated to 11', P.normalise('091234567890123'), '09123456789');
assert('normalise: +63 truncated', P.normalise('+639171234567890'), '09171234567');
assert('normalise: empty -> empty', P.normalise(''), '');
assert('normalise: null -> empty', P.normalise(null), '');
assert('normalise: non-digit junk only -> empty', P.normalise('abc'), '');
assert('normalise: short partial gets 09 prefix', P.normalise('12345'), '0912345');
assert('normalise: partial 09… kept (incomplete)', P.normalise('091712345'), '091712345');

// isValid: exactly 09 + 9 digits after normalisation
assert('isValid: canonical', P.isValid('09171234567'), true);
assert('isValid: dashed legacy', P.isValid('0917-123-4567'), true);
assert('isValid: +63 legacy', P.isValid('+639171234567'), true);
assert('isValid: 63 legacy', P.isValid('639171234567'), true);
assert('isValid: bare 9', P.isValid('9171234567'), true);
assert('isValid: partial rejected', P.isValid('091712345'), false);
assert('isValid: empty rejected', P.isValid(''), false);
assert('isValid: null rejected', P.isValid(null), false);
// isValid() tests the *normalised* value, mirroring the server flow
// (phone_normalize_request() runs before the /^09\d{9}$/ rule): 08… subscriber
// digits are re-prefixed to 09… and over-long input is truncated to 11.
assert('isValid: 08… re-prefixed to 09… is accepted', P.isValid('08171234567'), true);
assert('isValid: 10 digits rejected', P.isValid('0917123456'), false);
assert('isValid: 12 digits truncated then accepted', P.isValid('091712345678'), true);
assert('isValid: letters rejected', P.isValid('0917abc4567'), false);

// Parity with the PHP helper (app/Helpers/phone_helper.php phone_normalize).
const parity = [
  ['+639171234567', '09171234567'],
  ['639171234567', '09171234567'],
  ['9171234567', '09171234567'],
  ['0917-123-4567', '09171234567'],
  ['', ''],
  ['08123456789', '09812345678'],
  ['091234567890123', '09123456789'],
];
for (const [input, expected] of parity) {
  assert(`parity with PHP for ${JSON.stringify(input)}`, P.normalise(input), expected);
}

let failed = 0;
for (const [label, ok, actual, expected] of results) {
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'} | ${label}${ok ? '' : ` (got ${JSON.stringify(actual)}, expected ${JSON.stringify(expected)})`}`);
}
console.log(failed === 0 ? `ALL ${results.length} PHONE TESTS PASSED` : `${failed} OF ${results.length} PHONE TEST(S) FAILED`);
process.exit(failed === 0 ? 0 : 1);
