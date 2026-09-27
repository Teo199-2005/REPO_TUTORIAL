// TEMPORARY: renders renderSnedGrid()'s card markup with DOM stubs to prove the
// domain cards are clickable, carry an edit button, and stay valid HTML.
const fs = require('fs');

const src = fs.readFileSync('app/Views/admin/settings.php', 'utf8');
const start = src.indexOf('function renderSnedGrid() {');
const end = src.indexOf('function snedChangePage(');
if (start < 0 || end < 0) { console.error('FAIL: could not locate renderSnedGrid'); process.exit(1); }

const fnSource = src.slice(start, end).replace(/<\?=.*?\?>/g, 'PHP');

const container = { innerHTML: '', empty: false, classList: { add() { this.empty = true; }, remove() {} } };
const pager = { classList: { add() {}, remove() {} } };

const document = {
  getElementById(id) {
    if (id === 'snedCategoriesContainer') return container;
    if (id === 'snedEmptyState') return null;
    if (id === 'snedPager') return pager;
    return null;
  },
};

const escSnedHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (c) =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

let snedAllCategories = [
  { id: 3, name: 'Psychomotor Domain', description: 'Gross & fine motor skills', field_count: 5 },
  { id: 4, name: 'Cognitive Domain', description: null, field_count: 0 },
  { id: 5, name: "Teacher's <Special> Domain", description: 'Quotes "inside" & stuff', field_count: 2 },
];
let snedCurrentPage = 1;
const SNED_PAGE_SIZE = 9;

const renderSnedGrid = new Function('document', 'escSnedHtml', 'SNED_PAGE_SIZE', 'snedAllCategories', 'snedCurrentPage',
  fnSource + '\nreturn renderSnedGrid;');

renderSnedGrid(document, escSnedHtml, SNED_PAGE_SIZE, snedAllCategories, snedCurrentPage)();

const html = container.innerHTML;
const checks = [
  ['renders one card per domain', (html.match(/settings-domain-card/g) || []).length === 3],
  ['cards are clickable (onclick -> editSnedCategory)', (html.match(/onclick="editSnedCategory\(\d+\)"/g) || []).length === 3],
  ['cards expose role=button + tabindex', (html.match(/role="button" tabindex="0"/g) || []).length === 3],
  ['pencil edit button present per card', (html.match(/bi bi-pencil/g) || []).length === 3],
  ['edit button calls editSnedCategory with stopPropagation', (html.match(/event\.stopPropagation\(\); editSnedCategory\(\d+\)/g) || []).length === 3],
  ['Indicators button keeps stopPropagation', (html.match(/event\.stopPropagation\(\); manageSnedFields/g) || []).length === 3],
  ['delete button keeps stopPropagation', (html.match(/event\.stopPropagation\(\); deleteSnedCategory/g) || []).length === 3],
  ['description falls back to "No description"', html.includes('No description')],
  ['apostrophes escaped in indicators call', html.includes("Teacher\\'s &lt;Special&gt; Domain")],
  ['quotes/ampersands escaped in description', html.includes('Quotes &quot;inside&quot; &amp; stuff')],
  ['no raw unescaped script breaker', !html.includes('<Special>')],
  ['no undefined leaking into markup', !html.includes('undefined')],
  ['balanced div tags per card', (html.match(/<div/g) || []).length === (html.match(/<\/div>/g) || []).length],
];

let failed = 0;
for (const [label, ok] of checks) {
  console.log((ok ? '  OK   ' : '  FAIL ') + label);
  if (!ok) failed++;
}
console.log(failed === 0 ? '\nALL CARD CHECKS PASSED' : `\n${failed} CHECK(S) FAILED`);
process.exit(failed === 0 ? 0 : 1);
