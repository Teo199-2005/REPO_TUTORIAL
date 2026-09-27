<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">My Students</h1>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-primary" id="totalStudentsCount"><?= count($advisoryStudents) ?> Students</span>
        <button class="btn btn-danger btn-sm" onclick="removeSelectedStudents()" id="removeSelectedBtn" disabled>
            <i class="bi bi-person-dash"></i> Remove Selected (<span id="selectedCountRemove">0</span>)
        </button>
        <button class="btn btn-primary btn-sm" onclick="allowSelectedView()" id="allowBtn" disabled>
            <i class="bi bi-eye"></i> Enable Viewing (<span id="allowCount">0</span>)
        </button>
        <button class="btn btn-warning btn-sm" onclick="blockSelectedView()" id="blockBtn" disabled>
            <i class="bi bi-eye-slash"></i> Disable Viewing (<span id="blockCount">0</span>)
        </button>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-3" id="studentTabs" role="tablist">
    <?php if ($advisorySection): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="advisory-tab" data-bs-toggle="tab" data-bs-target="#advisory" type="button" role="tab">
            <i class="bi bi-star-fill"></i> Advisory Class - <?= esc($advisorySection['section_name']) ?>
        </button>
    </li>
    <?php endif; ?>
    <?php foreach ($subjectSections as $index => $sectionData): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= !$advisorySection && $index === 0 ? 'active' : '' ?>" id="section-<?= $sectionData['section']['id'] ?>-tab" data-bs-toggle="tab" data-bs-target="#section-<?= $sectionData['section']['id'] ?>" type="button" role="tab">
            <?= esc($sectionData['section']['section_name']) ?> - <?= esc(grade_level_label((int) $sectionData['section']['grade_level'])) ?>
        </button>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Tabs Content -->
<div class="tab-content" id="studentTabsContent">

    <!-- Advisory Class Tab -->
    <?php if ($advisorySection): ?>
    <div class="tab-pane fade show active" id="advisory" role="tabpanel">
        <?php if (!empty($advisoryStudents)): ?>
            <?php 
            $maleStudents = array_filter($advisoryStudents, function($student) {
                return strtolower($student['gender']) === 'male';
            });
            $femaleStudents = array_filter($advisoryStudents, function($student) {
                return strtolower($student['gender']) === 'female';
            });
            ?>
            <!-- Male Students Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person me-2"></i>Male Students (<?= count($maleStudents) ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($maleStudents)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" class="selectAllMale" onchange="toggleSelectAll(this, 'male')"></th>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Grade Level</th>
                                        <th>Section</th>
                                        <th>Status</th>
                                        <th>View Access</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maleStudents as $student): ?>
                                        <tr>
                                            <td><input type="checkbox" class="student-checkbox male-checkbox" value="<?= $student['id'] ?>" data-name="<?= esc($student['first_name'] . ' ' . $student['last_name']) ?>" onchange="updateSelectedCount()"></td>
                                            <td><strong><?= esc($student['lrn']) ?></strong></td>
                                            <td><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                            <td><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0))) ?></td>
                                            <td><?= esc($student['section_name'] ?? 'No Section') ?></td>
                                            <td>
                                                <span class="badge bg-success">Enrolled</span>
                                            </td>
                                            <td>
                                                <span class="badge <?= ($student['can_view_report_card'] ?? 0) ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= ($student['can_view_report_card'] ?? 0) ? 'Enabled' : 'Disabled' ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="<?= base_url('teacher/report-card/' . $student['id']) ?>" class="btn btn-primary" target="_blank" title="View Report Card" aria-label="View report card for <?= esc($student['first_name'] . ' ' . $student['last_name']) ?> (opens in new tab)">
                                                        <i class="bi bi-file-earmark-text"></i> <span class="d-none d-md-inline">Report Card</span>
                                                    </a>
                                                    <?php if (is_sned_grade((int) ($student['grade_level'] ?? 0))): ?>
                                                    <a href="<?= base_url('teacher/sned/report-card/' . $student['id']) ?>" class="btn btn-primary" target="_blank" title="View SNED Report Card" aria-label="View SNED report card for <?= esc($student['first_name'] . ' ' . $student['last_name']) ?> (opens in new tab)" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                                        <i class="bi bi-universal-access"></i> <span class="d-none d-md-inline">SNED</span>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-person text-muted fs-1"></i>
                            <p class="text-muted mb-0">No male students in this class</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Female Students Section -->
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-dress me-2"></i>Female Students (<?= count($femaleStudents) ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($femaleStudents)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" class="selectAllFemale" onchange="toggleSelectAll(this, 'female')"></th>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Grade Level</th>
                                        <th>Section</th>
                                        <th>Status</th>
                                        <th>View Access</th>
                                        <th class="text-center" style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($femaleStudents as $student): ?>
                                        <tr>
                                            <td><input type="checkbox" class="student-checkbox female-checkbox" value="<?= $student['id'] ?>" data-name="<?= esc($student['first_name'] . ' ' . $student['last_name']) ?>" onchange="updateSelectedCount()"></td>
                                            <td><strong><?= esc($student['lrn']) ?></strong></td>
                                            <td><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                            <td><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0))) ?></td>
                                            <td><?= esc($student['section_name'] ?? 'No Section') ?></td>
                                            <td>
                                                <span class="badge bg-success">Enrolled</span>
                                            </td>
                                            <td>
                                                <span class="badge <?= ($student['can_view_report_card'] ?? 0) ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= ($student['can_view_report_card'] ?? 0) ? 'Enabled' : 'Disabled' ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="<?= base_url('teacher/report-card/' . $student['id']) ?>" class="btn btn-primary" target="_blank" title="View Report Card" aria-label="View report card for <?= esc($student['first_name'] . ' ' . $student['last_name']) ?> (opens in new tab)">
                                                        <i class="bi bi-file-earmark-text"></i> <span class="d-none d-md-inline">Report Card</span>
                                                    </a>
                                                    <?php if (is_sned_grade((int) ($student['grade_level'] ?? 0))): ?>
                                                    <a href="<?= base_url('teacher/sned/report-card/' . $student['id']) ?>" class="btn btn-primary" target="_blank" title="View SNED Report Card" aria-label="View SNED report card for <?= esc($student['first_name'] . ' ' . $student['last_name']) ?> (opens in new tab)" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                                        <i class="bi bi-universal-access"></i> <span class="d-none d-md-inline">SNED</span>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-person-dress text-muted fs-1"></i>
                            <p class="text-muted mb-0">No female students in this class</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-people fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No Students Assigned</h5>
                    <p class="text-muted mb-0">You are not currently assigned as an adviser to any section.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Subject Sections Tabs -->
    <?php foreach ($subjectSections as $index => $sectionData): ?>
    <div class="tab-pane fade <?= !$advisorySection && $index === 0 ? 'show active' : '' ?>" id="section-<?= $sectionData['section']['id'] ?>" role="tabpanel">
        <div class="alert alert-info mb-3">
            <i class="bi bi-book"></i> <strong>Subjects:</strong> <?= esc(implode(', ', $sectionData['section']['subjects'])) ?>
        </div>
        
        <?php if (!empty($sectionData['students'])): ?>
            <?php 
            $maleStudents = array_filter($sectionData['students'], function($student) {
                return strtolower($student['gender']) === 'male';
            });
            $femaleStudents = array_filter($sectionData['students'], function($student) {
                return strtolower($student['gender']) === 'female';
            });
            ?>
            
            <!-- Male Students -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person me-2"></i>Male Students (<?= count($maleStudents) ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($maleStudents)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Grade Level</th>
                                        <th>Section</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maleStudents as $student): ?>
                                        <tr>
                                            <td><strong><?= esc($student['lrn']) ?></strong></td>
                                            <td><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                            <td><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0))) ?></td>
                                            <td><?= esc($student['section_name'] ?? 'No Section') ?></td>
                                            <td><span class="badge bg-success">Enrolled</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-person text-muted fs-1"></i>
                            <p class="text-muted mb-0">No male students in this class</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Female Students -->
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-dress me-2"></i>Female Students (<?= count($femaleStudents) ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($femaleStudents)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Grade Level</th>
                                        <th>Section</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($femaleStudents as $student): ?>
                                        <tr>
                                            <td><strong><?= esc($student['lrn']) ?></strong></td>
                                            <td><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                            <td><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0))) ?></td>
                                            <td><?= esc($student['section_name'] ?? 'No Section') ?></td>
                                            <td><span class="badge bg-success">Enrolled</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-person-dress text-muted fs-1"></i>
                            <p class="text-muted mb-0">No female students in this class</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-people fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No Students Assigned</h5>
                    <p class="text-muted mb-0">No students enrolled in this section.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($advisorySection) && empty($subjectSections)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-people fs-1 text-muted mb-3"></i>
        <h5 class="text-muted">No Classes Assigned</h5>
        <p class="text-muted mb-0">You are not currently assigned as an adviser or subject teacher to any section.</p>
    </div>
</div>
<?php endif; ?>

<script>
function toggleSelectAll(checkbox, gender) {
    const activeTab = document.querySelector('.tab-pane.active');
    const checkboxes = activeTab.querySelectorAll(`.${gender}-checkbox`);
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelectedCount();
}

// Update total student count when switching tabs
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function() {
            const activeTab = document.querySelector('.tab-pane.active');
            const studentCount = activeTab.querySelectorAll('.student-checkbox').length;
            document.getElementById('totalStudentsCount').textContent = studentCount + ' Students';
            // Reset selections when switching tabs
            document.querySelectorAll('.student-checkbox:checked').forEach(cb => cb.checked = false);
            document.querySelectorAll('.selectAllMale, .selectAllFemale').forEach(cb => cb.checked = false);
            updateSelectedCount();
        });
    });
});

function updateSelectedCount() {
    const activeTab = document.querySelector('.tab-pane.active');
    const selected = activeTab ? activeTab.querySelectorAll('.student-checkbox:checked') : [];
    const count = selected.length;
    document.getElementById('selectedCountRemove').textContent = count;
    document.getElementById('allowCount').textContent = count;
    document.getElementById('blockCount').textContent = count;
    document.getElementById('removeSelectedBtn').disabled = count === 0;
    document.getElementById('allowBtn').disabled = count === 0;
    document.getElementById('blockBtn').disabled = count === 0;
}

function removeSelectedStudents() {
    const selected = Array.from(document.querySelectorAll('.student-checkbox:checked'));
    const studentIds = selected.map(cb => cb.value);
    const count = selected.length;
    const btn = document.getElementById('removeSelectedBtn');
    const originalText = btn.innerHTML;
    
    showConfirmModal(`Are you sure you want to remove ${count} selected student(s) from your section?`, () => {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Removing...';
        
        fetch('<?= base_url('teacher/remove-student') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
            },
            body: JSON.stringify({ student_ids: studentIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(`${count} student(s) removed from your section.`);
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(data.error || 'Failed to remove students. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred. Please try again.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });
}

function allowSelectedView() {
    const selected = Array.from(document.querySelectorAll('.student-checkbox:checked'));
    const studentIds = selected.map(cb => cb.value);
    const count = selected.length;
    const btn = document.getElementById('allowBtn');
    const originalText = btn.innerHTML;
    
    showConfirmModal(`Allow ${count} selected student(s) to view their report cards?`, () => {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Updating...';
        bulkToggleAccess(studentIds, 1, btn, originalText);
    });
}

function blockSelectedView() {
    const selected = Array.from(document.querySelectorAll('.student-checkbox:checked'));
    const studentIds = selected.map(cb => cb.value);
    const count = selected.length;
    const btn = document.getElementById('blockBtn');
    const originalText = btn.innerHTML;
    
    showConfirmModal(`Block ${count} selected student(s) from viewing their report cards?`, () => {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Updating...';
        bulkToggleAccess(studentIds, 0, btn, originalText);
    });
}

function showConfirmModal(message, onConfirm) {
    const modalHtml = `
    <div class="modal fade" id="confirmModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal" style="background-color: #6c757d !important; border-color: #6c757d !important; color: white !important;">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>`;
    
    const existing = document.getElementById('confirmModal');
    if (existing) existing.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    
    document.getElementById('confirmBtn').addEventListener('click', function() {
        modal.hide();
        setTimeout(() => onConfirm(), 300);
    }, { once: true });
    
    modal.show();
}

function showAlert(message) {
    const modalHtml = `
    <div class="modal fade" id="alertModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-info-circle text-info me-2"></i>Alert</h5>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal" style="background-color: #6c757d !important; border-color: #6c757d !important; color: white !important;">OK</button>
                </div>
            </div>
        </div>
    </div>`;
    
    const existing = document.getElementById('alertModal');
    if (existing) existing.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('alertModal'));
    modal.show();
}

function bulkToggleAccess(studentIds, canView, btn, originalText) {
    const total = studentIds.length;
    const hasButton = !!btn;
    let completed = 0;
    let failed = 0;
    let firstError = '';

    const finish = () => {
        if (hasButton) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
        if (failed === 0) {
            showAlert(`Updated ${total} student(s) successfully!`);
            setTimeout(() => location.reload(), 1000);
            return;
        }
        // The change did NOT happen for every student - say so and refresh
        // so the page gets a fresh CSRF token and the list reflects the DB.
        showAlert(
            (firstError || `Some updates failed (${failed} of ${total}).`)
            + ' The page has been refreshed - please try again.'
        );
        setTimeout(() => location.reload(), 2500);
    };

    studentIds.forEach(id => {
        fetch('<?= base_url('teacher/toggle-report-card-access') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
            },
            body: JSON.stringify({ student_id: id, can_view: canView })
        })
        .then(async response => {
            let data = null;
            try { data = await response.json(); } catch (e) { /* non-JSON (e.g. security error page) */ }
            completed++;
            if (!(response.ok && data && data.success)) {
                failed++;
                if (!firstError) {
                    firstError = (data && (data.error || data.message))
                        || (response.status === 403 ? 'Your session expired.' : `Update failed (HTTP ${response.status}).`);
                }
            }
            if (completed === total) finish();
        })
        .catch(error => {
            console.error('Error:', error);
            completed++;
            failed++;
            if (!firstError) firstError = 'Could not reach the server.';
            if (completed === total) finish();
        });
    });
}
</script>

<?= $this->endSection() ?> 
