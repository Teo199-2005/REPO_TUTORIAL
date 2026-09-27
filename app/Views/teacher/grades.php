<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Enter Grades</h1>
    <span class="badge bg-info">Term <?= (int) $currentTerm ?> - SY <?= get_current_school_year() ?></span>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= session()->getFlashdata('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= session()->getFlashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Tabs Navigation -->
<?php if ($isAdvisory || !empty($subjectSections)): ?>
<ul class="nav nav-tabs mb-3" id="gradesTabs" role="tablist">
    <?php if ($isAdvisory): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="advisory-tab" data-bs-toggle="tab" data-bs-target="#advisory" type="button" role="tab">
            <i class="bi bi-star-fill"></i> Advisory Class
        </button>
    </li>
    <?php endif; ?>
    <?php foreach ($subjectSections as $index => $sectionData): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= !$isAdvisory && $index === 0 ? 'active' : '' ?>" id="section-<?= $sectionData['section']['id'] ?>-tab" data-bs-toggle="tab" data-bs-target="#section-<?= $sectionData['section']['id'] ?>" type="button" role="tab">
            <?= esc($sectionData['section']['section_name']) ?> - <?= esc(grade_level_label((int) $sectionData['section']['grade_level'])) ?>
        </button>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Tabs Content -->
<div class="tab-content" id="gradesTabsContent">
    <!-- Advisory Class Tab -->
    <?php if ($isAdvisory): ?>
    <div class="tab-pane fade show active" id="advisory" role="tabpanel">
        <?php if (! empty($isNonNumericalSection)): ?>
            <?php
                // Inline developmental-domain grading: the page switches its
                // content based on the section's grading type — no portal redirect.
                $snedFieldCount = 0;
                foreach ($snedDomains as $sd) {
                    $snedFieldCount += count($sd['fields'] ?? []);
                }
            ?>
            <div class="card mt-4">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-diagram-3 text-primary me-2"></i>Developmental Domains — <?= esc($snedSection['section_name'] ?? 'Advisory Class') ?></h6>
                        <small class="text-muted">
                            <?= esc(grade_level_label((int) ($students[0]['grade_level'] ?? 0))) ?> &bull; Non-Numerical &bull; assessed with symbols, not numeric grades
                        </small>
                    </div>
                    <a href="<?= base_url('teacher/sned') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Full SNED Portal
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($snedDomains) || $snedFieldCount === 0): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clipboard-x fs-1 text-muted mb-2"></i>
                            <p class="text-muted mb-1">No developmental domains have been assigned to this section yet.</p>
                            <p class="text-muted small mb-0">Ask the school admin to tick this section's domains under <strong>Sections &rarr; Domains</strong>.</p>
                        </div>
                    <?php else: ?>
                        <!-- Rating guide -->
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <span class="fw-bold small me-2">Rating Guide:</span>
                            <?php foreach ($gradingSymbols as $gs): ?>
                                <span class="badge rounded-pill bg-secondary"><?= esc($gs['symbol']) ?></span>
                                <small class="text-muted me-3"><?= esc($gs['label']) ?></small>
                            <?php endforeach; ?>
                        </div>

                        <!-- Quarter tabs -->
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <?php foreach ($snedQuarters as $q): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?= $q === $snedActiveQuarter ? 'active' : '' ?>"
                                            data-bs-toggle="tab" data-bs-target="#snedQ<?= $q ?>" type="button" role="tab">
                                        Quarter <?= $q ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="tab-content">
                        <?php foreach ($snedQuarters as $q): ?>
                            <div class="tab-pane fade <?= $q === $snedActiveQuarter ? 'show active' : '' ?>" id="snedQ<?= $q ?>" role="tabpanel">
                                <?php // ---- Vertical list layout: one standing row per indicator, paginated ---- ?>
                                <?php if (count($students) > 1): ?>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                    <span class="fw-bold small"><i class="bi bi-person me-1"></i>Student:</span>
                                    <?php foreach ($students as $sIdx => $student): ?>
                                        <button type="button"
                                                class="btn btn-sm <?= $sIdx === 0 ? 'btn-primary' : 'btn-outline-primary' ?> sned-student-pill"
                                                data-student-target="<?= (int) $student['id'] ?>"
                                                onclick="snedSwitchStudent(this)">
                                            <?= esc(trim($student['first_name'] . ' ' . $student['last_name'])) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <?php foreach ($students as $sIdx => $student): ?>
                                <div class="sned-student-panel" data-panel-student="<?= (int) $student['id'] ?>" style="<?= $sIdx === 0 ? '' : 'display:none;' ?>">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                                        <span class="fw-bold"><i class="bi bi-person-circle me-1 text-primary"></i><?= esc(trim($student['first_name'] . ' ' . $student['last_name'])) ?></span>
                                        <span class="badge bg-light text-dark">LRN: <?= esc($student['lrn'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:50px;">#</th>
                                                    <th style="width:190px;">Domain</th>
                                                    <th>Indicator</th>
                                                    <th style="width:200px;">Rating</th>
                                                    <th style="width:250px;">Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $rowNo = 0; foreach ($snedDomains as $sd): if (empty($sd['fields'])) { continue; } foreach (($sd['fields'] ?? []) as $sf): $rowNo++;
                                                    $existing = $snedGradesByQuarter[$q][$student['id']][$sf['id']] ?? null;
                                                    $currentSymbol = $existing['grade_symbol'] ?? '';
                                                    $currentRemarks = $existing['remarks'] ?? '';
                                                ?>
                                                <tr class="sned-field-row">
                                                    <td class="text-muted"><?= $rowNo ?></td>
                                                    <td><span class="badge bg-info bg-opacity-10 text-info border border-info-subtle fw-normal"><?= esc($sd['name']) ?></span></td>
                                                    <td class="small"><?= esc($sf['field_name']) ?></td>
                                                    <td>
                                                        <select class="form-select form-select-sm sned-inline-symbol"
                                                                data-student="<?= (int) $student['id'] ?>"
                                                                data-field="<?= (int) $sf['id'] ?>"
                                                                data-quarter="<?= (int) $q ?>"
                                                                onchange="snedInlineSaveGrade(this)">
                                                            <option value="">--</option>
                                                            <?php foreach ($gradingSymbols as $gs): ?>
                                                                <option value="<?= esc($gs['symbol']) ?>" <?= $currentSymbol === $gs['symbol'] ? 'selected' : '' ?>>
                                                                    <?= esc($gs['symbol']) ?> - <?= esc($gs['label']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm sned-inline-remarks"
                                                               placeholder="Remarks"
                                                               data-student="<?= (int) $student['id'] ?>"
                                                               data-field="<?= (int) $sf['id'] ?>"
                                                               data-quarter="<?= (int) $q ?>"
                                                               value="<?= esc($currentRemarks) ?>"
                                                               onchange="snedInlineSaveRemarks(this)">
                                                    </td>
                                                </tr>
                                                <?php endforeach; endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <nav data-pager class="mt-2"></nav>
                                </div>
                                <?php endforeach; ?>
                                <small class="text-muted d-block mt-2">
                                    <i class="bi bi-info-circle me-1"></i>Saves instantly — symbol and remarks are stored per quarter, independent of the admin term setting.
                                </small>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
        <?= view('teacher/grades_table', [
            'students' => $students,
            'subjects' => $subjects,
            'studentGrades' => $studentGrades,
            'currentTerm' => $currentTerm,
            'gradingEnabled' => $gradingEnabled,
            'isAdvisory' => true,
            'sectionId' => $teacher['id'],
            'sectionGradingType' => $sectionGradingType ?? 'numerical',
            'gradingSymbols' => $gradingSymbols ?? []
        ]) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Subject Sections Tabs -->
    <?php foreach ($subjectSections as $index => $sectionData): ?>
    <div class="tab-pane fade <?= !$isAdvisory && $index === 0 ? 'show active' : '' ?>" id="section-<?= $sectionData['section']['id'] ?>" role="tabpanel">
        <div class="alert alert-info mb-3">
            <i class="bi bi-info-circle"></i> <strong>Note:</strong> You are teaching <strong><?= esc(implode(', ', array_column($sectionData['section']['subjects'], 'name'))) ?></strong> in this section.
        </div>
        <?= view('teacher/grades_table', [
            'students' => $sectionData['data']['students'],
            'subjects' => $sectionData['section']['subjects'],
            'studentGrades' => $sectionData['data']['studentGrades'],
            'currentTerm' => $currentTerm,
            'gradingEnabled' => $gradingEnabled,
            'isAdvisory' => false,
            'sectionId' => $sectionData['section']['id']
        ]) ?>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-clipboard-data fs-1 text-muted mb-3"></i>
        <h5 class="text-muted">No Classes Assigned</h5>
        <p class="text-muted mb-0">You are not assigned as an adviser or subject teacher to any section.</p>
    </div>
</div>
<?php endif; ?>

<script>
// Inline developmental-domain grading — posts to the same save endpoint the
// SNED portal uses, so both pages share one data store.
const SNED_INLINE_CSRF = '<?= csrf_token() ?>:<?= csrf_hash() ?>';

function snedInlinePost(studentId, fieldId, quarter, gradeSymbol, remarks, el) {
    const [tokenName, tokenHash] = SNED_INLINE_CSRF.split(':');
    const formData = new FormData();
    formData.append(tokenName, tokenHash);
    formData.append('student_id', studentId);
    formData.append('field_id', fieldId);
    formData.append('quarter', quarter);
    formData.append('grade_symbol', gradeSymbol || '');
    formData.append('remarks', remarks || '');

    fetch('<?= base_url('teacher/sned/grades/save') ?>', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': tokenHash }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            el.classList.add('is-valid');
            setTimeout(() => el.classList.remove('is-valid'), 2000);
        } else {
            el.classList.add('is-invalid');
            setTimeout(() => el.classList.remove('is-invalid'), 3000);
            console.error('SNED save failed:', data.message || data);
            alert('Could not save: ' + (data.message || 'please try again.'));
        }
    })
    .catch(error => {
        console.error('SNED save error:', error);
        alert('Network error while saving. Please try again.');
    });
}

function snedInlineSaveGrade(select) {
    snedInlinePost(select.dataset.student, select.dataset.field, select.dataset.quarter, select.value, '', select);
}

function snedInlineSaveRemarks(input) {
    snedInlinePost(input.dataset.student, input.dataset.field, input.dataset.quarter, '', input.value, input);
}

// ---- Vertical list layout: pagination + student switching -----------------
const SNED_PAGE_SIZE = 10;

function snedApplyPager(panel) {
    if (!panel) return;
    const rows = panel.querySelectorAll('.sned-field-row');
    const total = rows.length;
    const pages = Math.max(1, Math.ceil(total / SNED_PAGE_SIZE));
    let cur = parseInt(panel.dataset.page || '1', 10);
    cur = Math.min(Math.max(1, cur), pages);
    panel.dataset.page = cur;

    rows.forEach((row, i) => {
        row.style.display = (i >= (cur - 1) * SNED_PAGE_SIZE && i < cur * SNED_PAGE_SIZE) ? '' : 'none';
    });

    const pager = panel.querySelector('[data-pager]');
    if (!pager) return;
    if (total === 0) { pager.innerHTML = ''; return; }

    let html = '<ul class="pagination pagination-sm mb-1">';
    html += `<li class="page-item ${cur === 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="snedGotoPage(this, ${cur - 1}); return false;">&laquo;</a></li>`;
    for (let p = 1; p <= pages; p++) {
        html += `<li class="page-item ${p === cur ? 'active' : ''}"><a class="page-link" href="#" onclick="snedGotoPage(this, ${p}); return false;">${p}</a></li>`;
    }
    html += `<li class="page-item ${cur === pages ? 'disabled' : ''}"><a class="page-link" href="#" onclick="snedGotoPage(this, ${cur + 1}); return false;">&raquo;</a></li>`;
    html += '</ul>';
    html += `<div class="small text-muted">Showing ${(cur - 1) * SNED_PAGE_SIZE + 1}&ndash;${Math.min(cur * SNED_PAGE_SIZE, total)} of ${total} indicators</div>`;
    pager.innerHTML = html;
}

function snedGotoPage(link, page) {
    const panel = link.closest('.sned-student-panel');
    if (!panel) return;
    panel.dataset.page = page;
    snedApplyPager(panel);
}

function snedSwitchStudent(btn) {
    const pane = btn.closest('.tab-pane');
    if (!pane) return;
    pane.querySelectorAll('.sned-student-pill').forEach(b => {
        b.classList.toggle('btn-primary', b === btn);
        b.classList.toggle('btn-outline-primary', b !== btn);
    });
    pane.querySelectorAll('.sned-student-panel').forEach(p => {
        const active = p.dataset.panelStudent === btn.dataset.studentTarget;
        p.style.display = active ? '' : 'none';
        if (active) {
            p.dataset.page = 1; // reset to first page on student switch
            snedApplyPager(p);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sned-student-panel').forEach(p => {
        if (p.style.display !== 'none') snedApplyPager(p);
    });
});
</script>

<?= $this->endSection() ?>
