// TEMPORARY: renders loadGradeSubjects()'s badge markup with DOM/fetch stubs to
// prove every subject badge is clickable, keeps its delete X, and escapes values.
const fs = require('fs');

const src = fs.readFileSync('app/Views/admin/settings.php', 'utf8');
const start = src.indexOf('function loadGradeSubjects(grade) {');
const end = src.indexOf('function addSubjectToGrade(grade) {');
if (start < 0 || end < 0) { console.error('FAIL: could not locate loadGradeSubjects'); process.exit(1); }

const fnSource = src.slice(start, end).replace(/<\?=.*?\?>/g, 'PHP');

const SUBJECTS = [
  { id: 11, subject_code: 'ENG1', subject_name: 'English 1' },
  { id: 12, subject_code: 'FIL1', subject_name: "Filipino <1> & \"Wika\"" },
];

const container = { innerHTML: '' };
const document = {
  getElementById: (id) => (id === 'grade1Subjects' ? container : null),
};
const window = {};
const formatGradeLevel = () => 'Grade 1';
const escSnedHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (c) =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const fetch = () => Promise.resolve({ json: () => Promise.resolve({ success: true, subjects: SUBJECTS }) });

const loadGradeSubjects = new Function('document', 'fetch', 'escSnedHtml', 'formatGradeLevel', 'window',
  fnSource + '\nreturn loadGradeSubjects;')(document, fetch, escSnedHtml, formatGradeLevel, window);

loadGradeSubjects(1);

setTimeout(() => {
  const html = container.innerHTML;
  const checks = [
    ['one badge per subject', (html.match(/settings-subject-badge/g) || []).length === 2],
    ['badge is clickable (onclick -> editSubjectFromSettings)', (html.match(/onclick="editSubjectFromSettings\(\d+, 1\)"/g) || []).length === 2],
    ['badge exposes role=button + tabindex', (html.match(/role="button" tabindex="0"/g) || []).length === 2],
    ['pencil edit button per badge (with stopPropagation)', (html.match(/event\.stopPropagation\(\); editSubjectFromSettings\(\d+, 1\)/g) || []).length === 2],
    ['delete X kept (with stopPropagation)', (html.match(/event\.stopPropagation\(\); deleteSubjectFromSettings\(\d+, 1\)/g) || []).length === 2],
    ['subjects cached for the edit modal', Array.isArray(window.settingsGradeSubjects[1]) && window.settingsGradeSubjects[1].length === 2],
    ['codes/names escaped in badge text', html.includes('Filipino &lt;1&gt; &amp; &quot;Wika&quot;') && !html.includes('<1>')],
    ['no undefined leaking into markup', !html.includes('undefined')],
    ['keyboard handler present (Enter/Space)', (html.match(/onkeydown="if \(event\.key === 'Enter'/g) || []).length === 2],
  ];

  let failed = 0;
  for (const [label, ok] of checks) {
    console.log((ok ? '  OK   ' : '  FAIL ') + label);
    if (!ok) failed++;
  }
  console.log(failed === 0 ? '\nALL SUBJECT BADGE CHECKS PASSED' : `\n${failed} CHECK(S) FAILED`);
  process.exit(failed === 0 ? 0 : 1);
}, 30);
