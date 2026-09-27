<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="mb-4">
    <h1 class="h3 mb-2">System Settings</h1>
    <p class="text-muted">Configure system-wide settings</p>
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

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Academic Year Configuration</h5>
    </div>
    <div class="card-body p-4">
        <form method="post" action="<?= base_url('admin/settings/update-school-year') ?>">
            <?= csrf_field() ?>
            
            <div class="row g-4">
                <div class="col-12">
                    <div class="setting-box h-100">
                        <label class="form-label fw-bold text-primary">
                            <i class="bi bi-calendar3 me-2"></i>School Year
                        </label>
                        <?php
                        // Selector instead of free text: exactly two choices,
                        // both derived from today's date — the running school
                        // year and the one before it (e.g. in 2026:
                        // 2026-2027 and 2025-2026).
                        $schoolYearChoices  = school_year_choices();
                        $selectedSchoolYear = in_array((string) $currentSchoolYear, $schoolYearChoices, true)
                            ? (string) $currentSchoolYear
                            : $schoolYearChoices[0];
                        ?>
                        <select name="school_year" class="form-select form-select-lg" id="schoolYearInput" required>
                            <?php foreach ($schoolYearChoices as $syYear): ?>
                                <option value="<?= esc($syYear) ?>"<?= $syYear === $selectedSchoolYear ? ' selected' : '' ?>><?= esc($syYear) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>Only the two most recent school years are available.
                        </small>
                    </div>
                </div>
                
                <div class="col-12">
                    <div class="setting-box h-100">
                        <label class="form-label fw-bold text-primary">
                            <i class="bi bi-calendar-check me-2"></i>Current Term
                        </label>
                        <select name="term" class="form-select form-select-lg" required>
                            <option value="1" <?= $currentTerm == 1 ? 'selected' : '' ?>>Term 1</option>
                            <option value="2" <?= $currentTerm == 2 ? 'selected' : '' ?>>Term 2</option>
                            <option value="3" <?= $currentTerm == 3 ? 'selected' : '' ?>>Term 3</option>
                        </select>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>Kindergarten through Grade 6
                        </small>
                    </div>
                </div>

                <div class="col-12">
                    <div class="alert alert-light border h-100 mb-0">
                        <h6 class="mb-2"><i class="bi bi-exclamation-triangle text-warning me-2"></i>System Impact</h6>
                        <small class="d-block mb-2 text-muted">These settings will affect the following areas:</small>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2">
                            <div class="col"><small><i class="bi bi-check-circle text-success me-1"></i>Student enrollments &amp; registrations</small></div>
                            <div class="col"><small><i class="bi bi-check-circle text-success me-1"></i>Section assignments &amp; class lists</small></div>
                            <div class="col"><small><i class="bi bi-check-circle text-success me-1"></i>Grade entry &amp; report cards</small></div>
                            <div class="col"><small><i class="bi bi-check-circle text-success me-1"></i>Academic reports &amp; transcripts</small></div>
                            <div class="col"><small><i class="bi bi-check-circle text-success me-1"></i>Teacher &amp; admin dashboards</small></div>
                            <div class="col"><small><i class="bi bi-check-circle text-success me-1"></i>Analytics &amp; statistics</small></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-4" id="saveBtn">
                    <i class="bi bi-check-circle me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- School Principal / School Head (system-wide) -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>School Principal (School Head)</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted">
            These details are used everywhere the school head appears &mdash; update them once and the whole
            system follows. Principals change every few years, so no file edits are needed.
        </p>
        <div class="alert alert-light border mb-4">
            <h6 class="mb-2"><i class="bi bi-diagram-3 text-warning me-2"></i>Where this is used</h6>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2">
                <div class="col"><small><i class="bi bi-check-circle text-success me-1"></i>Student ID cards (back face)</small></div>
                <div class="col"><small><i class="bi bi-check-circle text-success me-1"></i>Report cards &amp; progress reports (footer signature)</small></div>
                <div class="col"><small><i class="bi bi-check-circle text-success me-1"></i>Public About page (photo, name &amp; rank)</small></div>
                <div class="col"><small><i class="bi bi-check-circle text-success me-1"></i>Public site footer</small></div>
                <div class="col"><small><i class="bi bi-check-circle text-success me-1"></i>Automated emails (enrollment, rejection, promotion)</small></div>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" action="<?= base_url('admin/settings/update-principal') ?>">
            <?= csrf_field() ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="schoolPrincipalName">Principal Name</label>
                    <input type="text" class="form-control" id="schoolPrincipalName" name="school_principal_name"
                           value="<?= esc($principalName) ?>" maxlength="120" required>
                    <small class="text-muted">Printed on report cards, the About page and the site footer.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="schoolPrincipalRank">Rank / Position</label>
                    <input type="text" class="form-control" id="schoolPrincipalRank" name="school_principal_rank"
                           value="<?= esc($principalRank) ?>" maxlength="60" placeholder="Principal IV">
                    <small class="text-muted">Shown under the name, e.g. "Principal IV".</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="schoolPrincipalPhoto">Principal Photo</label>
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-center mb-3">
                            <img src="<?= esc($principalPhotoUrl) ?>" alt="<?= esc($principalName) ?>"
                                 id="principalPhotoPreview"
                                 class="img-fluid rounded border"
                                 style="max-height: 260px; max-width: 210px; object-fit: cover;">
                        </div>
                        <input type="file" class="form-control" id="schoolPrincipalPhoto" name="school_principal_photo"
                               accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
                        <small class="text-muted d-block mt-2">
                            JPG/PNG/WEBP, max 5MB. A portrait photo (4:5) works best. Leave empty to keep the current photo.
                        </small>
                        <?php if (! empty($hasCustomPrincipalPhoto)): ?>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" value="1"
                                       id="removePrincipalPhoto" name="remove_principal_photo">
                                <label class="form-check-label small" for="removePrincipalPhoto">
                                    Remove uploaded photo (restore the default picture)
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-warning text-dark">
                    <i class="bi bi-check-circle me-2"></i>Save School Principal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Subject Management Section (Numerical Levels: Kindergarten to Grade 6 + Custom) --><div class="card border-0 shadow-sm mt-4">    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">        <h5 class="mb-0">Subject Management <span class="badge bg-light text-dark ms-2">Numerical</span></h5>        <small class="text-white-50">Kindergarten to Grade 6 students are graded with numeric grades (0&ndash;100) per subject. Click a subject below to edit it.</small>    </div>    <div class="card-body p-4">        <?php        $gradeOptions = grade_level_options();        $nonSnedGrades = array_filter($gradeOptions, fn($g) => $g !== 7);        ?>        <?php foreach ($nonSnedGrades as $grade): ?>        <div class="mb-4">            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">                <h6 class="fw-bold text-success mb-0">                    <i class="bi bi-book me-2"></i><?= esc(grade_level_label((int) $grade)) ?> Subjects                    <span class="badge bg-primary ms-2 align-middle">Numerical</span>                </h6>                <button class="btn btn-sm btn-outline-success" onclick="addGradeItem(<?= $grade ?>)">                    <i class="bi bi-plus-circle me-1"></i>Add Subject                </button>            </div>            <div id="grade<?= $grade ?>Subjects" class="mb-3"></div>        </div>        <?php if ($grade < max($nonSnedGrades)): ?><hr><?php endif; ?>        <?php endforeach; ?>    </div></div><!-- Developmental Domains Section (Non-Numerical Sections) --><div class="card border-0 shadow-sm mt-4">    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">        <h5 class="mb-0">Developmental Domains <span class="badge bg-warning text-dark ms-2">Non-Numerical Sections</span></h5>        <button class="btn btn-light btn-sm" onclick="addSnedCategory()">            <i class="bi bi-plus-circle me-1"></i>Add Developmental Domain        </button>    </div>    <div class="card-body p-4">        <div class="alert alert-info mb-4">            <i class="bi bi-info-circle me-2"></i>            This is the <strong>master list of developmental domains</strong> used by every section graded            <strong>non-numerically</strong> (such as SNED). Create the section as <em>Non-Numerical</em> on the            <a href="<?= base_url('admin/sections') ?>">Sections</a> page and it will automatically use the domains            defined here. Each domain contains performance indicators &mdash; click <strong>Indicators</strong> to manage them, or click a
            domain card to edit its name and description.        </div>        <div id="snedCategoriesContainer" class="row g-3"></div>        <div id="snedEmptyState" class="text-center py-4 d-none">            <i class="bi bi-diagram-3 fs-1 text-muted d-block mb-3"></i>            <h6 class="text-muted">No developmental domains added yet</h6>            <p class="text-muted small mb-0">Click <strong>Add Developmental Domain</strong> to create the first one. Every non-numerical section will use it automatically.</p>        </div>        <div id="snedPager" class="d-flex justify-content-between align-items-center mt-4 d-none">            <button type="button" class="btn btn-sm btn-outline-primary" id="snedPrevBtn" onclick="snedChangePage(-1)">                <i class="bi bi-arrow-left me-1"></i>Previous            </button>            <span class="text-muted small" id="snedPageInfo"></span>            <button type="button" class="btn btn-sm btn-outline-primary" id="snedNextBtn" onclick="snedChangePage(1)">                Next<i class="bi bi-arrow-right ms-1"></i>            </button>        </div>    </div></div>
<!-- Featured Posters Section -->
<form method="post" enctype="multipart/form-data" action="<?= base_url('admin/settings/update-featured-posters') ?>">
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Featured Posters</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-4">Upload a poster image to be shown on teacher and student dashboards.</p>

        <table style="width:100%;border-collapse:separate;border-spacing:1rem;">
            <tr>
                <td style="width:50%;vertical-align:top;padding-right:0.5rem;">
                    <div class="border rounded-3 p-3 h-100">
                        <h6 class="fw-bold text-info mb-3"><i class="bi bi-person-video3 me-2"></i>Teacher Dashboard Poster</h6>
                        <?php if (! empty($featuredPosterTeacherUrl)): ?>
                            <div class="mb-3">
                                <img src="<?= esc($featuredPosterTeacherUrl ?? '') ?>" alt="Teacher Poster" class="img-fluid rounded-3 border" style="max-height: 220px; width: 100%; object-fit: cover;">
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border mb-3">No poster uploaded yet.</div>
                        <?php endif; ?>
                        <input type="file" name="featured_poster_teacher" class="form-control poster-file-input" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-preview-target="teacher">
                        <small class="text-muted d-block mt-2">JPG/PNG/WEBP, max 5MB.</small>
                    </div>
                </td>
                <td style="width:50%;vertical-align:top;padding-left:0.5rem;">
                    <div class="border rounded-3 p-3 h-100">
                        <h6 class="fw-bold text-info mb-3"><i class="bi bi-mortarboard-fill me-2"></i>Student Dashboard Poster</h6>
                        <?php if (! empty($featuredPosterStudentUrl)): ?>
                            <div class="mb-3">
                                <img src="<?= esc($featuredPosterStudentUrl ?? '') ?>" alt="Student Poster" class="img-fluid rounded-3 border" style="max-height: 220px; width: 100%; object-fit: cover;">
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border mb-3">No poster uploaded yet.</div>
                        <?php endif; ?>
                        <input type="file" name="featured_poster_student" class="form-control poster-file-input" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" data-preview-target="student">
                        <small class="text-muted d-block mt-2">JPG/PNG/WEBP, max 5MB.</small>
                    </div>
                </td>
            </tr>
        </table>

            <div class="mt-4">
                <button type="submit" class="btn btn-info text-white">
                    <i class="bi bi-image me-2"></i>Save Featured Posters
                </button>
            </div>
        </form>
    </div>
</div>

<?php
/**
 * First-login welcome modal content.
 *
 * Everything here is optional. An empty box falls back to the built-in default in
 * mascot_welcome_copy(), so an admin can rewrite just the dialogue line and leave
 * the rest alone. The "Show this modal" switch is the only global kill switch.
 */
$welcomeRoles = [
    'admin'   => 'Admin',
    'teacher' => 'Teacher',
    'student' => 'Student',
    'parent'  => 'Parent',
];
?>
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-stars me-2"></i>Welcome Modal (first login)</h5>
        <p class="text-muted mb-4">
            Tappy's greeting, shown once per role the first time that role signs in.
            Leave a box empty to use the built-in default.
        </p>

        <form method="post" action="<?= base_url('admin/settings/update-welcome-modal') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-check form-switch mb-4">
                <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    name="welcome_enabled"
                    value="1"
                    id="welcomeEnabled"
                    <?= ! empty($welcomeEnabled) ? 'checked' : '' ?>
                >
                <label class="form-check-label" for="welcomeEnabled">
                    Show the welcome modal on first login
                </label>
            </div>

            <?php foreach ($welcomeRoles as $welcomeRoleKey => $welcomeRoleLabel): ?>
                <?php
                $welcomePosterVar = 'welcomePoster_' . $welcomeRoleKey;
                $welcomePosterUrl = ${$welcomePosterVar} ?? '';
                ?>
                <div class="border rounded-3 p-3 mb-3">
                    <h6 class="fw-bold text-info mb-3"><?= esc($welcomeRoleLabel) ?></h6>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Title</label>
                            <input
                                type="text"
                                class="form-control"
                                name="welcome_title_<?= esc($welcomeRoleKey) ?>"
                                value="<?= esc(${'welcomeTitle_' . $welcomeRoleKey} ?? '') ?>"
                                maxlength="200"
                                placeholder="This is the <?= esc(strtolower($welcomeRoleLabel)) ?> dashboard"
                            >
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tappy's dialogue line</label>
                            <input
                                type="text"
                                class="form-control"
                                name="welcome_dialogue_<?= esc($welcomeRoleKey) ?>"
                                value="<?= esc(${'welcomeDialogue_' . $welcomeRoleKey} ?? '') ?>"
                                maxlength="300"
                                placeholder="Tap &quot;Show me around&quot; and I will point out the parts you use most."
                            >
                        </div>
                        <div class="col-12">
                            <label class="form-label">Body text</label>
                            <textarea
                                class="form-control"
                                name="welcome_body_<?= esc($welcomeRoleKey) ?>"
                                rows="2"
                                maxlength="500"
                                placeholder="Everything you need is on this page and in the menu on the left."
                            ><?= esc(${'welcomeBody_' . $welcomeRoleKey} ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Poster (optional)</label>
                            <?php if ($welcomePosterUrl !== ''): ?>
                                <div class="mb-2">
                                    <img
                                        src="<?= esc($welcomePosterUrl) ?>"
                                        alt="<?= esc($welcomeRoleLabel) ?> welcome poster"
                                        class="img-fluid rounded-3 border"
                                        style="max-height: 180px; width: 100%; object-fit: cover;"
                                    >
                                </div>
                            <?php endif; ?>
                            <input
                                type="file"
                                name="welcome_poster_<?= esc($welcomeRoleKey) ?>"
                                class="form-control"
                                accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            >
                            <small class="text-muted d-block mt-2">
                                JPG/PNG/WEBP, max 5MB. Shown under the dialogue line.
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-info text-white">
                <i class="bi bi-stars me-2"></i>Save Welcome Modal
            </button>
        </form>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.poster-file-input').forEach(function(input) {
        input.addEventListener('change', function() {
            const target = input.getAttribute('data-preview-target');
            const file = input.files && input.files[0];
            if (!target || !file) {
                return;
            }
            const col = input.closest('.border.rounded-3');
            if (!col) {
                return;
            }
            let wrap = col.querySelector('.poster-preview-live');
            if (!wrap) {
                const empty = col.querySelector('.alert');
                if (empty) {
                    empty.remove();
                }
                const oldImg = col.querySelector('img');
                if (oldImg) {
                    oldImg.remove();
                }
                wrap = document.createElement('div');
                wrap.className = 'mb-3 poster-preview-live';
                col.insertBefore(wrap, input);
            }
            wrap.innerHTML = '';
            const img = document.createElement('img');
            img.alt = 'Preview';
            img.className = 'img-fluid rounded-3 border poster-preview-img';
            img.style.cssText = 'max-height:220px;width:100%;object-fit:cover';
            img.src = URL.createObjectURL(file);
            wrap.appendChild(img);
        });
    });

    // The School Year control is a fixed <select> with exactly two options
    // (current + previous school year), so the free-text auto-formatting and
    // YYYY-YYYY blur check that used to live here no longer apply.

    const allGrades = <?= json_encode(grade_level_options()) ?>;    // Kindergarten through Grade 6 (plus custom) are always numerical: load    // their subjects. Developmental domains live in their own dedicated block.    allGrades.filter(g => g !== 7).forEach(function(grade) {        loadGradeSubjects(grade);    });});


// Subjects loaded per grade, keyed by grade level. The Edit modal reads the
// current code/name from here instead of stuffing them into inline onclick
// attributes, which would break on apostrophes in subject names.
window.settingsGradeSubjects = window.settingsGradeSubjects || {};

function loadGradeSubjects(grade) {
    window.settingsGradeSubjects = window.settingsGradeSubjects || {};
    console.log('Loading subjects for grade:', grade);
    fetch(`<?= base_url('admin/settings/get-grade-subjects/') ?>${grade}`)
        .then(r => r.json())
        .then(data => {
            console.log(`${formatGradeLevel(grade)} subjects response:`, data);
            const container = document.getElementById(`grade${grade}Subjects`);
            if (data.success && data.subjects && data.subjects.length > 0) {
                window.settingsGradeSubjects[grade] = data.subjects;
                // Clicking a subject (or its pencil button) opens the Edit Subject
                // modal; the X keeps deleting. stopPropagation keeps the nested
                // buttons from also triggering the badge click.
                container.innerHTML = data.subjects.map(s =>
                    `<span class="badge bg-primary text-white me-2 mb-2 p-2 settings-subject-badge"
                           role="button" tabindex="0"
                           title="Click to edit this subject"
                           onclick="editSubjectFromSettings(${s.id}, ${grade})"
                           onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); editSubjectFromSettings(${s.id}, ${grade}); }">
                        <strong>${escSnedHtml(s.subject_code)}</strong> - ${escSnedHtml(s.subject_name)}
                        <button type="button" class="btn btn-sm btn-light text-primary ms-2 py-0 px-1" style="font-size: 0.85rem;" title="Edit subject"
                                onclick="event.stopPropagation(); editSubjectFromSettings(${s.id}, ${grade})"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn-close btn-close-white btn-sm ms-1" style="font-size: 0.7rem;" title="Delete subject"
                                onclick="event.stopPropagation(); deleteSubjectFromSettings(${s.id}, ${grade})"></button>
                    </span>`
                ).join('');
            } else {
                window.settingsGradeSubjects[grade] = [];
                container.innerHTML = '<p class="text-muted mb-0">No subjects added yet</p>';
            }
        })
        .catch(error => {
            console.error(`Error loading grade ${grade} subjects:`, error);
            document.getElementById(`grade${grade}Subjects`).innerHTML = '<p class="text-danger mb-0">Error loading subjects</p>';
        });
}

function addSubjectToGrade(grade) {
    const existing = document.getElementById('addSubjectSettingsModal');
    if (existing) existing.remove();
    
    const modalHtml = `
    <div class="modal fade" id="addSubjectSettingsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill me-2"></i>Add Subject - ${formatGradeLevel(grade)}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Subject Code</label>
                        <input type="text" id="subjectCodeInput" class="form-control form-control-lg border-2" placeholder="e.g., MATH${grade}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Subject Name</label>
                        <input type="text" id="subjectNameInput" class="form-control form-control-lg border-2" placeholder="e.g., Mathematics ${grade}" required>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #495057; border-color: #495057;"><i class="bi bi-x-circle me-2"></i>Cancel</button>
                    <button type="button" class="btn btn-success" onclick="saveSubjectFromSettings(${grade})"><i class="bi bi-check-circle me-2"></i>Add Subject</button>
                </div>
            </div>
        </div>
    </div>`;
    
    const portal = document.getElementById('dashboard-modal-portal') || document.body;
    portal.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('addSubjectSettingsModal'), { backdrop: true, keyboard: true, focus: true });
    modal.show();
    
    setTimeout(() => document.getElementById('subjectCodeInput').focus(), 300);
}

function saveSubjectFromSettings(grade) {
    const code = document.getElementById('subjectCodeInput').value.trim();
    const name = document.getElementById('subjectNameInput').value.trim();
    
    if (!code || !name) {
        showToast('Please fill in both fields', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('subject_code', code);
    formData.append('subject_name', name);
    formData.append('grade_level', grade);
    formData.append('is_active', true);
    formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    
    fetch('<?= base_url('admin/subjects/add') ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('addSubjectSettingsModal')).hide();
                loadGradeSubjects(grade);
                showSuccessToast('Subject added successfully!');
            } else {
                showToast(data.error || 'Failed to add subject', 'danger');
            }
        })
        .catch(error => {
            console.error('Add subject failed:', error);
            showToast('Failed to add subject. Please check your connection and try again.', 'danger');
        });
}

// Every non-SNED level is numerical, so adding an item means adding a subject.function addGradeItem(grade) {    addSubjectToGrade(grade);}


function showToast(message, type = 'success') {
    const alertClass = type === 'success' ? 'alert-success' : type === 'danger' ? 'alert-danger' : type === 'warning' ? 'alert-warning' : 'alert-info';
    const iconClass = type === 'success' ? 'bi-check-circle' : type === 'danger' ? 'bi-exclamation-triangle' : type === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle';
    
    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        <i class="bi ${iconClass} me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

function showSuccessToast(message) {
    showToast(message, 'success');
}


const SNED_PAGE_SIZE = 9; // 3 columns x 3 rows per pagelet snedAllCategories = [];let snedCurrentPage = 1;function loadSnedCategories() {    fetch('<?= base_url('admin/sned/categories') ?>?shared=1')        .then(r => r.json())        .then(data => {            snedAllCategories = (data.success && data.categories) ? data.categories : [];            snedCurrentPage = 1;            renderSnedGrid();        })        .catch(error => {            console.error('Error loading developmental domains:', error);            document.getElementById('snedCategoriesContainer').innerHTML = '';            showToast('Error loading developmental domains', 'danger');        });}function renderSnedGrid() {    const container = document.getElementById('snedCategoriesContainer');    const emptyState = document.getElementById('snedEmptyState');    const pager = document.getElementById('snedPager');    if (!container) return;    const total = snedAllCategories.length;    if (total === 0) {        container.innerHTML = '';        if (emptyState) emptyState.classList.remove('d-none');        if (pager) pager.classList.add('d-none');        return;    }    if (emptyState) emptyState.classList.add('d-none');    const totalPages = Math.max(1, Math.ceil(total / SNED_PAGE_SIZE));    if (snedCurrentPage > totalPages) snedCurrentPage = totalPages;    if (snedCurrentPage < 1) snedCurrentPage = 1;    const start = (snedCurrentPage - 1) * SNED_PAGE_SIZE;    const pageItems = snedAllCategories.slice(start, start + SNED_PAGE_SIZE);    container.innerHTML = pageItems.map(c => {        // Domain names are injected into onclick attributes, so they must be
        // escaped for BOTH layers: backslash/apostrophe for the JS string
        // literal inside the attribute, then & < > " for the HTML attribute
        // itself. (& first, so the entities added below stay intact.)
        const safeName = String(c.name || '')
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');        const scopeBadge = (c.grade_level === null || c.grade_level === undefined)            ? '<span class="badge bg-success ms-1" title="Used by every non-numerical section">All non-numerical</span>'            : '<span class="badge bg-secondary ms-1" title="Visible to SNED sections">SNED</span>';        return `        <div class="col-lg-4 col-md-6">            <div class="card h-100 border shadow-sm settings-domain-card"
                 role="button" tabindex="0"
                 title="Click to edit this domain"
                 onclick="editSnedCategory(${c.id})"
                 onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); editSnedCategory(${c.id}); }">                <div class="card-body d-flex flex-column">                    <div class="d-flex justify-content-between align-items-start mb-2 gap-2">                        <h6 class="fw-bold mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i>${escSnedHtml(c.name)}</h6>                        ${scopeBadge}                    </div>                    <p class="text-muted small mb-3 flex-grow-1">${c.description ? escSnedHtml(c.description) : 'No description'}</p>                    <div class="d-flex gap-2">                        <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="event.stopPropagation(); manageSnedFields(${c.id}, '${safeName}')">                            <i class="bi bi-gear me-1"></i>Indicators (${c.field_count || 0})                        </button>                        <button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation(); editSnedCategory(${c.id})" title="Edit domain name and description">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); deleteSnedCategory(${c.id}, '${safeName}')" title="Remove domain">                            <i class="bi bi-trash"></i>                        </button>                    </div>                </div>            </div>        </div>`;    }).join('');    if (pager) {        if (totalPages > 1) {            pager.classList.remove('d-none');            document.getElementById('snedPageInfo').textContent = 'Showing ' + (start + 1) + '-' + Math.min(start + SNED_PAGE_SIZE, total) + ' of ' + total + ' domains';            document.getElementById('snedPrevBtn').disabled = snedCurrentPage <= 1;            document.getElementById('snedNextBtn').disabled = snedCurrentPage >= totalPages;        } else {            pager.classList.add('d-none');        }    }}function snedChangePage(delta) {    snedCurrentPage += delta;    renderSnedGrid();    const el = document.getElementById('snedCategoriesContainer');    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });}
function addSnedCategory() {
    const existing = document.getElementById('addSnedCategoryModal');
    if (existing) existing.remove();
    
    const modalHtml = `
    <div class="modal fade" id="addSnedCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-diagram-3 me-2"></i>Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" style="color: #333;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="color: #333;">Category Name</label>
                        <input type="text" id="snedCategoryName" class="form-control form-control-lg border-2" placeholder="e.g., Gross Motor Skills" style="color: #333;" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="color: #333;">Description</label>
                        <textarea id="snedCategoryDesc" class="form-control border-2" rows="3" placeholder="Brief description of this category" style="color: #333;"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #495057; border-color: #495057;"><i class="bi bi-x-circle me-2"></i>Cancel</button>
                    <button type="button" class="btn btn-success" onclick="saveSnedCategory()"><i class="bi bi-check-circle me-2"></i>Add Category</button>
                </div>
            </div>
        </div>
    </div>`;
    
    const portal = document.getElementById('dashboard-modal-portal') || document.body;
    portal.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('addSnedCategoryModal'), { backdrop: true, keyboard: true, focus: true });
    modal.show();
    setTimeout(() => document.getElementById('snedCategoryName').focus(), 300);
}
// Click a subject badge (or its pencil button) to rename that subject.
// Reads the current values from window.settingsGradeSubjects instead of inline
// onclick attributes so names containing apostrophes cannot break the markup.
function editSubjectFromSettings(id, grade) {
    const subjects = (window.settingsGradeSubjects && window.settingsGradeSubjects[grade]) || [];
    const subject = subjects.find(s => Number(s.id) === Number(id));

    if (!subject) {
        showToast('Could not load that subject. Refresh the page and try again.', 'danger');
        return;
    }

    const existing = document.getElementById('editSubjectSettingsModal');
    if (existing) {
        // Dispose BEFORE removing so Bootstrap also clears its backdrop/body lock.
        const inst = bootstrap.Modal.getInstance(existing);
        if (inst) inst.dispose();
        existing.remove();
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    const modalHtml = `
    <div class="modal fade" id="editSubjectSettingsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Subject - ${formatGradeLevel(grade)}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Subject Code</label>
                        <input type="text" id="editSubjectCodeInput" class="form-control form-control-lg border-2" value="${escSnedHtml(subject.subject_code)}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Subject Name</label>
                        <input type="text" id="editSubjectNameInput" class="form-control form-control-lg border-2" value="${escSnedHtml(subject.subject_name)}" required>
                    </div>
                    <div class="alert alert-light border mb-0">
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Renaming updates this subject everywhere it is offered, including section class lists and report cards.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #495057; border-color: #495057;"><i class="bi bi-x-circle me-2"></i>Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveSubjectFromSettingsEdit(${id}, ${grade})"><i class="bi bi-save me-2"></i>Save Changes</button>
                </div>
            </div>
        </div>
    </div>`;

    const portal = document.getElementById('dashboard-modal-portal') || document.body;
    portal.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('editSubjectSettingsModal'), { backdrop: true, keyboard: true, focus: true });
    modal.show();

    setTimeout(() => document.getElementById('editSubjectCodeInput').focus(), 300);
}

function saveSubjectFromSettingsEdit(id, grade) {
    const code = (document.getElementById('editSubjectCodeInput').value || '').trim();
    const name = (document.getElementById('editSubjectNameInput').value || '').trim();

    if (!code || !name) {
        showToast('Please fill in both fields', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('subject_code', code);
    formData.append('subject_name', name);
    // is_active is intentionally NOT posted: it is per-section
    // (section_subjects.is_active) and editing a subject must not flip it.
    formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch('<?= base_url('admin/subjects/edit/') ?>' + id, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const el = document.getElementById('editSubjectSettingsModal');
                if (el) bootstrap.Modal.getInstance(el).hide();
                loadGradeSubjects(grade);
                showSuccessToast('Subject updated successfully!');
            } else {
                showToast(data.error || 'Failed to update subject', 'danger');
            }
        })
        .catch(error => {
            console.error('Edit subject failed:', error);
            showToast('Failed to update subject. Please check your connection and try again.', 'danger');
        });
}

function saveSnedCategory() {
    const name = document.getElementById('snedCategoryName').value.trim();
    const desc = document.getElementById('snedCategoryDesc').value.trim();
    
    if (!name) {
        showToast('Please enter a domain name', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('name', name);
    formData.append('description', desc);
    // No grade_level on purpose: a domain created here is a shared domain
    // (section_id IS NULL, grade_level NULL) so that EVERY non-numerical
    // section uses it automatically — matching the copy in this block.
    formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    
    fetch('<?= base_url('admin/sned/categories/add') ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('addSnedCategoryModal')).hide();
                loadSnedCategories();


                showSuccessToast('Category added successfully!');
            } else {
                showToast(data.error || 'Failed to add category', 'danger');
            }
        })
        .catch(error => {
            console.error('Add category failed:', error);
            showToast('Failed to add category. Please check your connection and try again.', 'danger');
        });
}

// Click a domain card (or its pencil button) to rename the domain and rewrite
// its description. The category is looked up in snedAllCategories so the values
// never have to travel through an inline onclick attribute.
function editSnedCategory(categoryId) {
    const category = snedAllCategories.find(c => Number(c.id) === Number(categoryId));
    if (!category) {
        showToast('Could not load that domain. Refresh the page and try again.', 'danger');
        return;
    }

    const existing = document.getElementById('editSnedCategoryModal');
    if (existing) {
        // Dispose BEFORE removing so Bootstrap also clears its backdrop/body lock.
        const inst = bootstrap.Modal.getInstance(existing);
        if (inst) inst.dispose();
        existing.remove();
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    const indicatorCount = Number(category.field_count || 0);

    const modalHtml = `
    <div class="modal fade" id="editSnedCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Developmental Domain</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" style="color: #333;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="color: #333;">Domain Name</label>
                        <input type="text" id="editSnedCategoryName" class="form-control form-control-lg border-2" maxlength="255" value="${escSnedHtml(category.name)}" style="color: #333;" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="color: #333;">Description</label>
                        <textarea id="editSnedCategoryDesc" class="form-control border-2" rows="4" placeholder="Brief description of this domain" style="color: #333;">${escSnedHtml(category.description || '')}</textarea>
                        <small class="text-muted">Shown on the domain card and to teachers when they encode grades. Leave it empty to show "No description".</small>
                    </div>
                    <div class="alert alert-light border mb-0">
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>The ${indicatorCount} performance indicator(s) and every grade already recorded stay attached &mdash; only the name and description change.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #495057; border-color: #495057;"><i class="bi bi-x-circle me-2"></i>Cancel</button>
                    <button type="button" class="btn btn-success" onclick="saveSnedCategoryEdit(${category.id})"><i class="bi bi-check-circle me-2"></i>Save Changes</button>
                </div>
            </div>
        </div>
    </div>`;

    const portal = document.getElementById('dashboard-modal-portal') || document.body;
    portal.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('editSnedCategoryModal'), { backdrop: true, keyboard: true, focus: true });
    modal.show();

    setTimeout(() => document.getElementById('editSnedCategoryName').focus(), 300);
}

function saveSnedCategoryEdit(categoryId) {
    const nameEl = document.getElementById('editSnedCategoryName');
    const descEl = document.getElementById('editSnedCategoryDesc');
    const name = (nameEl.value || '').trim();
    const desc = (descEl.value || '').trim();

    if (!name) {
        showToast('Please enter a domain name', 'warning');
        nameEl.focus();
        return;
    }

    const formData = new FormData();
    formData.append('category_id', categoryId);
    formData.append('name', name);
    formData.append('description', desc);
    formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch('<?= base_url('admin/sned/categories/edit') ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const el = document.getElementById('editSnedCategoryModal');
                if (el) bootstrap.Modal.getInstance(el).hide();
                loadSnedCategories();
                showSuccessToast('Domain updated successfully!');
            } else {
                showToast(data.error || 'Failed to update domain', 'danger');
            }
        })
        .catch(error => {
            console.error('Edit domain failed:', error);
            showToast('Failed to update domain. Please check your connection and try again.', 'danger');
        });
}


function snedFieldRowHtml(f, i, categoryId) {
    return `<tr>
                        <td>${i+1}</td>
                        <td>
                            <span id="sned-field-name-${f.id}">${escSnedHtml(f.field_name)}</span>
                            <div class="input-group input-group-sm d-none" id="sned-field-edit-${f.id}">
                                <input type="text" class="form-control" id="sned-field-input-${f.id}"
                                       value="${escSnedHtml(f.field_name)}" maxlength="255"
                                       onkeydown="if (event.key === 'Enter') { event.preventDefault(); saveSnedField(${f.id}, ${categoryId}); } else if (event.key === 'Escape') { cancelSnedFieldEdit(${f.id}); }">
                                <button class="btn btn-success" type="button" title="Save" onclick="saveSnedField(${f.id}, ${categoryId})"><i class="bi bi-check-lg"></i></button>
                                <button class="btn btn-secondary" type="button" title="Cancel" onclick="cancelSnedFieldEdit(${f.id})"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" title="Edit" onclick="editSnedField(${f.id})"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-danger" title="Deactivate" onclick="deleteSnedField(${f.id}, ${categoryId})"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>`;
}

// Refresh ONLY the table inside the open modal — never rebuild the modal
// itself. Rebuilding it while it is open leaves Bootstrap's backdrop orphaned
// (dark, unclickable screen) because the modal element is removed without
// Bootstrap being told to hide it.
function renderSnedFieldsTable(categoryId) {
    const tbody = document.getElementById('snedFieldsTableBody');
    if (!tbody) return;

    fetch('<?= base_url('admin/sned/fields/') ?>' + categoryId)
        .then(r => r.json())
        .then(data => {
            const fields = data.success && data.fields ? data.fields : [];
            tbody.innerHTML = fields.length > 0
                ? fields.map((f, i) => snedFieldRowHtml(f, i, categoryId)).join('')
                : '<tr><td colspan="3" class="text-center text-muted py-3">No fields yet</td></tr>';
        })
        .catch(() => showToast('Failed to refresh indicators', 'danger'));
}

function manageSnedFields(categoryId, categoryName) {
    const existing = document.getElementById('manageSnedFieldsModal');
    if (existing) {
        // Dispose the old instance BEFORE removing its element so Bootstrap
        // also removes its backdrop and the body lock — otherwise the screen
        // stays dark and unclickable.
        const inst = bootstrap.Modal.getInstance(existing);
        if (inst) inst.dispose();
        existing.remove();
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }
    
    // Fetch fields
    fetch('<?= base_url('admin/sned/fields/') ?>' + categoryId)
        .then(r => r.json())
        .then(data => {
            const fields = data.success && data.fields ? data.fields : [];
            const fieldsHtml = fields.length > 0 ? 
                fields.map((f, i) => 
                    `<tr>
                        <td>${i+1}</td>
                        <td>
                            <span id="sned-field-name-${f.id}">${escSnedHtml(f.field_name)}</span>
                            <div class="input-group input-group-sm d-none" id="sned-field-edit-${f.id}">
                                <input type="text" class="form-control" id="sned-field-input-${f.id}"
                                       value="${escSnedHtml(f.field_name)}" maxlength="255"
                                       onkeydown="if (event.key === 'Enter') { event.preventDefault(); saveSnedField(${f.id}, ${categoryId}); } else if (event.key === 'Escape') { cancelSnedFieldEdit(${f.id}); }">
                                <button class="btn btn-success" type="button" title="Save" onclick="saveSnedField(${f.id}, ${categoryId})"><i class="bi bi-check-lg"></i></button>
                                <button class="btn btn-secondary" type="button" title="Cancel" onclick="cancelSnedFieldEdit(${f.id})"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" title="Edit" onclick="editSnedField(${f.id})"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-danger" title="Deactivate" onclick="deleteSnedField(${f.id}, ${categoryId})"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>`
                ).join('') :
                '<tr><td colspan="3" class="text-center text-muted py-3">No fields yet</td></tr>';
            
            const modalHtml = `
            <div class="modal fade" id="manageSnedFieldsModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold"><i class="bi bi-gear me-2"></i>${categoryName} - Fields</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4" style="color: #333;">
                            <div class="row">
                                <div class="col-lg-7">
                                    <h6 class="fw-bold mb-3" style="color: #333;"><i class="bi bi-list-check me-2"></i>Existing Fields</h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead class="table-light">
                                                <tr><th>#</th><th>Field Name</th><th style="width:110px;">Actions</th></tr>
                                            </thead>
                                            <tbody id="snedFieldsTableBody">${fieldsHtml}</tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <h6 class="fw-bold mb-3" style="color: #333;"><i class="bi bi-plus-circle me-2"></i>Add Field</h6>
                                    <div class="mb-3">
                                        <label class="form-label" style="color: #333;">Field Name</label>
                                        <input type="text" id="newFieldName" class="form-control" placeholder="e.g., Can walk independently" style="color: #333;">
                                    </div>
                                    <button class="btn btn-primary w-100" onclick="addSnedField(${categoryId})">
                                        <i class="bi bi-plus-circle me-1"></i> Add Field
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
            
            const portal = document.getElementById('dashboard-modal-portal') || document.body;
            portal.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('manageSnedFieldsModal'), { backdrop: true, keyboard: true, focus: true });
            modal.show();
        });
}

function addSnedField(categoryId) {
    const name = document.getElementById('newFieldName').value.trim();
    if (!name) {
        showToast('Please enter an indicator name', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('category_id', categoryId);
    formData.append('field_name', name);
    formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    
    fetch('<?= base_url('admin/sned/fields/add') ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('newFieldName').value = '';
                showSuccessToast('Indicator added!');
                // Refresh only the table — the modal stays open and no
                // backdrop is orphaned.
                renderSnedFieldsTable(categoryId);
            } else {
                showToast(data.error || 'Failed to add indicator', 'danger');
            }
        });
}

// Escape user-provided field names before injecting them into the modal HTML.
function escSnedHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
}

function editSnedField(fieldId) {
    document.getElementById('sned-field-name-' + fieldId).classList.add('d-none');
    const editor = document.getElementById('sned-field-edit-' + fieldId);
    editor.classList.remove('d-none');
    const input = document.getElementById('sned-field-input-' + fieldId);
    input.focus();
    input.select();
}

function cancelSnedFieldEdit(fieldId) {
    const editor = document.getElementById('sned-field-edit-' + fieldId);
    document.getElementById('sned-field-input-' + fieldId).value =
        document.getElementById('sned-field-name-' + fieldId).textContent;
    editor.classList.add('d-none');
    document.getElementById('sned-field-name-' + fieldId).classList.remove('d-none');
}

function saveSnedField(fieldId, categoryId) {
    const input = document.getElementById('sned-field-input-' + fieldId);
    const name = input.value.trim();
    if (!name) {
        showToast('Indicator name is required', 'danger');
        input.focus();
        return;
    }

    const formData = new FormData();
    formData.append('field_id', fieldId);
    formData.append('field_name', name);
    formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch('<?= base_url('admin/sned/fields/edit') ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('sned-field-name-' + fieldId).textContent = name;
                cancelSnedFieldEdit(fieldId);
                showSuccessToast('Indicator updated!');
            } else {
                showToast(data.error || 'Failed to update indicator', 'danger');
            }
        })
        .catch(() => showToast('Failed to update indicator', 'danger'));
}

async function deleteSnedField(fieldId, categoryId) {
    const ok = await customConfirm('Deactivate this indicator?\nExisting grades will be preserved.', 'Deactivate Indicator');
    if (!ok) return;
    
    fetch('<?= base_url('admin/sned/fields/delete/') ?>' + fieldId, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showSuccessToast('Indicator deactivated');
            renderSnedFieldsTable(categoryId);
        } else {
            showToast(data.error || 'Failed to delete', 'danger');
        }
    });
}

async function deleteSnedCategory(categoryId, categoryName, refreshCallback) {
    const ok = await customConfirm('Deactivate the domain "' + categoryName + '"?\nAll associated fields and grades will be deactivated.', 'Deactivate Domain');
    if (!ok) return;
    
    fetch('<?= base_url('admin/sned/categories/delete/') ?>' + categoryId, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showSuccessToast('Category deleted');
            if (typeof refreshCallback === 'function') {
                refreshCallback();
            } else {
                loadSnedCategories();
            }
        } else {
            showToast(data.error || 'Failed to delete', 'danger');
        }
    })
    .catch(error => {
        console.error('Delete category failed:', error);
        showToast('Failed to delete. Please try again.', 'danger');
    });
}

// Override loadGradeSubjects for SNED to load categories instead
const originalDomContentLoaded = document.addEventListener('DOMContentLoaded', function() {
    // Load SNED categories
    loadSnedCategories();
}, { once: true });

function deleteSubjectFromSettings(id, grade) {
    fetch(`<?= base_url('admin/subjects/delete/') ?>${id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Subject deleted successfully!', 'success');
            loadGradeSubjects(grade);
        } else {
            showToast(data.error || 'Failed to delete', 'danger');
        }
    });
}

/* Principal photo: show the chosen image immediately so the office can
   confirm the right portrait before saving. */
document.getElementById('schoolPrincipalPhoto')?.addEventListener('change', function() {
    const file = this.files && this.files[0];
    const preview = document.getElementById('principalPhotoPreview');
    if (!file || !preview) {
        return;
    }
    const removeBox = document.getElementById('removePrincipalPhoto');
    if (removeBox) {
        removeBox.checked = false;
    }
    preview.src = URL.createObjectURL(file);
});
</script>

<style>
.setting-box {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    height: 100%;
}

/* Subject badges and domain cards are clickable: clicking one opens its edit
   modal, so they need a pointer cursor and a visible hover/focus state. */
.settings-subject-badge {
    cursor: pointer;
    transition: box-shadow .15s ease-in-out, transform .15s ease-in-out;
}
.settings-subject-badge:hover,
.settings-subject-badge:focus-visible {
    box-shadow: 0 0 0 .2rem rgba(255, 255, 255, .5);
    transform: translateY(-1px);
    outline: none;
}
.settings-domain-card {
    cursor: pointer;
    transition: box-shadow .15s ease-in-out, transform .15s ease-in-out;
}
.settings-domain-card:hover,
.settings-domain-card:focus-visible {
    box-shadow: 0 .5rem 1rem rgba(13, 110, 253, .25) !important;
    transform: translateY(-2px);
    outline: none;
}
.settings-domain-card:focus-visible {
    border-color: #0d6efd !important;
}
</style>

<?= $this->endSection() ?>
