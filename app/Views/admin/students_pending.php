<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history text-warning"></i> 
                        New Student Applications
                        <span class="badge bg-warning text-dark ms-2"><?= count($pendingStudents) ?></span>
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small d-none" id="bulkSelectedCount"></span>
                        <button type="button" class="btn btn-success btn-sm" id="bulkApproveBtn"
                                onclick="bulkApproveSelected()" disabled title="Select at least 2 applications to enable">
                            <i class="bi bi-check-lg"></i> Approve Selected
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="bulkRejectBtn"
                                onclick="bulkRejectSelected()" disabled title="Select at least 2 applications to enable">
                            <i class="bi bi-x-lg"></i> Reject Selected
                        </button>
                        <a href="<?= base_url('admin/students/pending/history') ?>" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-clock-history"></i> History
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingStudents)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                            <h5 class="mt-2">No New Student Applications</h5>
                            <p class="text-muted mb-0">All new student applications have been processed.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 36px;" class="text-center">
                                            <input type="checkbox" class="form-check-input" id="selectAllPending" title="Select all applications" aria-label="Select all applications">
                                        </th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Grade Level</th>
                                        <th>Applied Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingStudents as $student): ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input pending-select" value="<?= $student['id'] ?>" aria-label="Select application">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                        <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= esc(trim($student['first_name'] . ' ' . (!empty($student['middle_name']) ? strtoupper(substr($student['middle_name'], 0, 1)) . '. ' : '') . $student['last_name'] . (!empty($student['suffix']) ? ' ' . $student['suffix'] : ''))) ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= esc($student['email'] ?? '') ?></td>
                                            <td>
                                                <span class="badge bg-info"><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0))) ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                $createdDate = new DateTime($student['created_at']);
                                                $createdDate->setTimezone(new DateTimeZone('Asia/Manila'));
                                                ?>
                                                <?= $createdDate->format('M j, Y') ?>
                                                <br><small class="text-muted"><?= $createdDate->format('g:i A') ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            onclick="approveStudent(<?= $student['id'] ?>, '<?= esc($student['first_name'] . ' ' . $student['last_name']) ?>')">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            onclick="rejectStudent(<?= $student['id'] ?>, '<?= esc($student['first_name'] . ' ' . $student['last_name']) ?>')">
                                                        <i class="bi bi-x-lg"></i> Reject
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info btn-sm" 
                                                            onclick="viewStudentDetails(<?= $student['id'] ?>)">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================ NEXT SCHOOL YEAR APPLICATIONS ============================ -->
<!-- Rendered on this same page so the admin reviews both queues from one screen. -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card mt-3" id="next-year-applications">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-up-circle text-success"></i>
                        Next School Year Applications
                        <span class="badge bg-warning text-dark ms-2"><?= count($nextYearPending ?? []) ?> pending</span>
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small d-none" id="nextYearSelectedCount"></span>
                        <button type="button" class="btn btn-success btn-sm" id="bulkPromoteBtn"
                                onclick="bulkPromoteSelected()" disabled title="Select at least 2 applications to enable">
                            <i class="bi bi-mortarboard-fill"></i> Promote Selected
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="bulkRejectNextYearBtn"
                                onclick="bulkRejectNextYearSelected()" disabled title="Select at least 2 applications to enable">
                            <i class="bi bi-x-lg"></i> Reject Selected
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Students who passed all terms applied from the student portal to enroll in the next grade level.
                        <strong>Promote</strong> moves them up a grade level, clears their section assignment (they get a new
                        section for the next school year) and notifies them by portal notification.
                        Grade 6 students graduate instead, so they never appear here.
                    </p>

                    <?php if (empty($nextYearPending)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                            <h6 class="mt-2">No Pending Next Year Applications</h6>
                            <p class="text-muted small mb-0">There are no next school year applications waiting for review.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 36px;" class="text-center">
                                            <input type="checkbox" class="form-check-input" id="nextYearSelectAll"
                                                   title="Select all next year applications" aria-label="Select all next year applications">
                                        </th>
                                        <th>Name</th>
                                        <th>LRN</th>
                                        <th>Current</th>
                                        <th>Next</th>
                                        <th>GWA</th>
                                        <th>Applied</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nextYearPending as $app): ?>
                                        <?php
                                        $nyName = trim(
                                            $app['first_name'] . ' ' .
                                            (!empty($app['middle_name']) ? strtoupper(substr($app['middle_name'], 0, 1)) . '. ' : '') .
                                            $app['last_name']
                                        );
                                        $nyNextLabel = grade_level_label((int) ($app['next_grade_level'] ?? 0));
                                        $nyGwa = (float) ($app['gwa'] ?? 0);
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input next-year-select"
                                                       value="<?= (int) $app['id'] ?>"
                                                       data-student-id="<?= (int) $app['student_id'] ?>"
                                                       data-next-grade="<?= (int) ($app['next_grade_level'] ?? 0) ?>"
                                                       data-next-label="<?= esc($nyNextLabel, 'attr') ?>"
                                                       data-name="<?= esc($nyName, 'attr') ?>"
                                                       aria-label="Select application">
                                            </td>
                                            <td>
                                                <strong><?= esc($nyName) ?></strong>
                                                <?php if (!empty($app['section_name'])): ?>
                                                    <div class="text-muted small"><?= esc($app['section_name']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small"><?= esc($app['lrn'] ?? '--') ?></td>
                                            <td><span class="badge bg-info"><?= esc(grade_level_label((int) ($app['current_grade_level'] ?? 0))) ?></span></td>
                                            <td><span class="badge bg-success"><?= esc($nyNextLabel) ?></span></td>
                                            <td class="small">
                                                <?php if ($nyGwa > 0): ?>
                                                    <strong><?= esc(number_format($nyGwa, 2)) ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted" title="Non-numerical (developmental symbols) assessment">Symbols-based</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small">
                                                <?= esc(date('M j, Y', strtotime($app['applied_at']))) ?>
                                                <br><small class="text-muted"><?= esc(date('g:i A', strtotime($app['applied_at']))) ?></small>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-success btn-sm"
                                                            data-student-id="<?= (int) $app['student_id'] ?>"
                                                            data-next-grade="<?= (int) ($app['next_grade_level'] ?? 0) ?>"
                                                            data-next-label="<?= esc($nyNextLabel, 'attr') ?>"
                                                            data-name="<?= esc($nyName, 'attr') ?>"
                                                            onclick="nextYearPromoteOne(this)">
                                                        <i class="bi bi-arrow-up-circle"></i> Promote
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                            data-application-id="<?= (int) $app['id'] ?>"
                                                            data-name="<?= esc($nyName, 'attr') ?>"
                                                            onclick="nextYearRejectOne(this)">
                                                        <i class="bi bi-x-lg"></i> Reject
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
const PENDING_CSRF_NAME = '<?= csrf_token() ?>';
const PENDING_CSRF_HASH = '<?= csrf_hash() ?>';

function pendingFetch(url, data) {
    const body = {};
    body[PENDING_CSRF_NAME] = PENDING_CSRF_HASH;
    if (data) Object.assign(body, data);
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': PENDING_CSRF_HASH
        },
        body: JSON.stringify(body)
    });
}

function getDashboardModalPortal() {
    return document.getElementById('dashboard-modal-portal') || document.body;
}

function createPendingModal(id, titleClass, title, bodyHtml, confirmButtonId, confirmButtonClass, confirmButtonText) {
    const existing = document.getElementById(id);
    if (existing) existing.remove();

    const modalDiv = document.createElement('div');
    modalDiv.className = 'modal fade';
    modalDiv.id = id;
    modalDiv.setAttribute('tabindex', '-1');
    modalDiv.setAttribute('aria-hidden', 'true');

    modalDiv.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header ${titleClass}">
            <h5 class="modal-title">${title}</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            ${bodyHtml}
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn ${confirmButtonClass}" id="${confirmButtonId}">${confirmButtonText}</button>
          </div>
        </div>
      </div>`;

    getDashboardModalPortal().appendChild(modalDiv);

    modalDiv.addEventListener('hidden.bs.modal', function () {
        modalDiv.remove();
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.remove();
        });
    }, { once: true });

    return modalDiv;
}

function approveStudent(studentId, studentName) {
    const modalDiv = createPendingModal(
        'approveModal',
        'bg-success text-white',
        'Confirm Approval',
        `
            <p>Are you sure you want to APPROVE <strong>${studentName}</strong>'s application?</p>
            <p class="mb-2"><strong>This will:</strong></p>
            <ul class="mb-0">
              <li>Activate their account</li>
              <li>Allow them to access the student portal</li>
            </ul>
        `,
        'confirmApproveBtn',
        'btn-success',
        'Confirm'
    );

    const modal = new bootstrap.Modal(modalDiv, { backdrop: 'static', keyboard: true, focus: true });
    modal.show();

    const confirmBtn = document.getElementById('confirmApproveBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            confirmApproval(studentId);
        }, { once: true });
    }
}

function confirmApproval(studentId) {
    const modalEl = document.getElementById('approveModal');
    if (!modalEl) return;
    
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    // Show loading state
    showAlert('info', 'Processing approval...');
    
    fetch(`<?= base_url('admin/students/approve/') ?>${studentId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': PENDING_CSRF_HASH
        },
        body: JSON.stringify({ [PENDING_CSRF_NAME]: PENDING_CSRF_HASH })
    })
    .then(response => {
        const status = response.status;
        return response.text().then(text => {
            let data;
            try { data = JSON.parse(text); }
            catch (e) { data = { error: 'Server returned status ' + status + '. ' + String(text).substring(0, 200) }; }
            return { status: status, data: data };
        });
    })
    .then(result => {
        // Remove loading alert
        dismissAlerts();
        if (result.data && result.data.success) {
            showAlert('success', result.data.message);
            setTimeout(() => location.reload(), 2500);
        } else {
            console.error('Approve failed:', result.status, result.data);
            showAlert('error', (result.data && (result.data.error || result.data.message)) || 'Approval failed (HTTP ' + result.status + '). Details in browser console (F12).');
        }
    })
    .catch(error => {
        dismissAlerts();
        showAlert('error', 'Network error occurred. Please check your connection and try again.');
    });
}

function rejectStudent(studentId, studentName) {
    const modalDiv = createPendingModal(
        'rejectStudentModal',
        'bg-danger text-white',
        'Reject Application',
        `
            <p>Are you sure you want to REJECT <strong>${studentName}</strong>'s application?</p>
            <div class="alert alert-warning mb-0">
              <i class="bi bi-exclamation-triangle"></i> This action cannot be undone.
            </div>
        `,
        'confirmRejectStudentBtn',
        'btn-danger',
        'Confirm Rejection'
    );

    const modal = new bootstrap.Modal(modalDiv, { backdrop: 'static', keyboard: true, focus: true });
    modal.show();

    const confirmBtn = document.getElementById('confirmRejectStudentBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            confirmRejectStudent(studentId);
        }, { once: true });
    }
}

function confirmRejectStudent(studentId) {
    const modalEl = document.getElementById('rejectStudentModal');
    if (!modalEl) return;
    
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    // Show loading state
    showAlert('info', 'Processing rejection...');
    
    fetch(`<?= base_url('admin/students/reject/') ?>${studentId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': PENDING_CSRF_HASH
        },
        body: JSON.stringify({ [PENDING_CSRF_NAME]: PENDING_CSRF_HASH })
    })
    .then(response => {
        const status = response.status;
        return response.text().then(text => {
            let data;
            try { data = JSON.parse(text); }
            catch (e) { data = { error: 'Server returned status ' + status + '. ' + String(text).substring(0, 200) }; }
            return { status: status, data: data };
        });
    })
    .then(result => {
        dismissAlerts();
        if (result.data && result.data.success) {
            showAlert('success', result.data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            console.error('Reject failed:', result.status, result.data);
            showAlert('error', (result.data && (result.data.error || result.data.message)) || 'Rejection failed (HTTP ' + result.status + '). Details in browser console (F12).');
        }
    })
    .catch(error => {
        dismissAlerts();
        showAlert('error', 'Network error occurred. Please check your connection and try again.');
    });
}

function viewStudentDetails(studentId) {
    window.location.href = `<?= base_url('admin/students/view/') ?>${studentId}`;
}

function dismissAlerts() {
    const container = document.querySelector('.container-fluid');
    if (container) {
        container.querySelectorAll('.alert').forEach(a => a.remove());
    }
}

function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : (type === 'info' ? 'alert-info' : 'alert-danger');
    const formattedMessage = message.replace(/\n/g, '<br>');
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${formattedMessage}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertAdjacentHTML('afterbegin', alertHtml);
    
    // Auto-dismiss after 8 seconds for success messages with credentials
    const dismissTime = message.includes('Login Credentials') ? 8000 : 5000;
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, dismissTime);
}

// ---- Bulk selection & bulk approve/reject logic ----
const BULK_MIN_SELECTION = 2;

function getPendingCheckboxes() {
    return Array.from(document.querySelectorAll('.pending-select'));
}

function getSelectedPendingIds() {
    return getPendingCheckboxes()
        .filter(cb => cb.checked)
        .map(cb => parseInt(cb.value, 10))
        .filter(v => !isNaN(v));
}

function updateBulkButtons() {
    const selected = getSelectedPendingIds();
    const count = selected.length;
    const canBulk = count >= BULK_MIN_SELECTION;

    const approveBtn = document.getElementById('bulkApproveBtn');
    const rejectBtn = document.getElementById('bulkRejectBtn');
    if (approveBtn) approveBtn.disabled = !canBulk;
    if (rejectBtn) rejectBtn.disabled = !canBulk;

    const countLabel = document.getElementById('bulkSelectedCount');
    if (countLabel) {
        if (count > 0) {
            countLabel.textContent = count + ' selected' + (canBulk ? '' : ' (select at least ' + BULK_MIN_SELECTION + ' to enable bulk actions)');
            countLabel.classList.remove('d-none');
        } else {
            countLabel.classList.add('d-none');
        }
    }

    // Keep the select-all checkbox in sync (checked / indeterminate / unchecked)
    const boxes = getPendingCheckboxes();
    const selectAll = document.getElementById('selectAllPending');
    if (selectAll && boxes.length > 0) {
        const checkedCount = boxes.filter(cb => cb.checked).length;
        selectAll.checked = checkedCount === boxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
    }
}

function bulkApproveSelected() {
    const ids = getSelectedPendingIds();
    if (ids.length < BULK_MIN_SELECTION) {
        showAlert('error', 'Please select at least ' + BULK_MIN_SELECTION + ' applications first.');
        return;
    }
    bulkProcessApplications('approve', ids);
}

function bulkRejectSelected() {
    const ids = getSelectedPendingIds();
    if (ids.length < BULK_MIN_SELECTION) {
        showAlert('error', 'Please select at least ' + BULK_MIN_SELECTION + ' applications first.');
        return;
    }
    bulkProcessApplications('reject', ids);
}

function bulkProcessApplications(action, ids) {
    const isApprove = action === 'approve';
    const modalDiv = createPendingModal(
        'bulkActionModal',
        isApprove ? 'bg-success text-white' : 'bg-danger text-white',
        isApprove ? 'Bulk Approve Applications' : 'Bulk Reject Applications',
        `
            <p>Are you sure you want to ${isApprove ? 'APPROVE' : 'REJECT'} <strong>${ids.length}</strong> selected application(s)?</p>
            ${isApprove ? `
            <p class="mb-2"><strong>This will:</strong></p>
            <ul class="mb-0">
              <li>Activate each selected student's account</li>
            </ul>` : `
            <div class="alert alert-warning mb-0">
              <i class="bi bi-exclamation-triangle"></i> This action cannot be undone.
            </div>`}
        `,
        'confirmBulkActionBtn',
        isApprove ? 'btn-success' : 'btn-danger',
        isApprove ? 'Approve All' : 'Reject All'
    );

    const modal = new bootstrap.Modal(modalDiv, { backdrop: 'static', keyboard: true, focus: true });
    modal.show();

    const confirmBtn = document.getElementById('confirmBulkActionBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            const modalEl = document.getElementById('bulkActionModal');
            const instance = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
            if (instance) instance.hide();

            showAlert('info', 'Processing ' + ids.length + ' application(s)...');

            const url = isApprove
                ? `<?= base_url('admin/students/bulkApprove') ?>`
                : `<?= base_url('admin/students/bulkReject') ?>`;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': PENDING_CSRF_HASH
                },
                body: JSON.stringify({ student_ids: ids, [PENDING_CSRF_NAME]: PENDING_CSRF_HASH })
            })
            .then(response => {
                const status = response.status;
                return response.text().then(text => {
                    let data;
                    try { data = JSON.parse(text); }
                    catch (e) { data = { error: 'Server returned status ' + status + '. ' + String(text).substring(0, 200) }; }
                    return { status: status, data: data };
                });
            })
            .then(result => {
                dismissAlerts();
                if (result.data && result.data.success) {
                    showAlert('success', result.data.message);
                    setTimeout(() => location.reload(), 2500);
                } else {
                    console.error('Bulk action failed:', result.status, result.data);
                    showAlert('error', (result.data && (result.data.error || result.data.message)) || 'Bulk action failed (HTTP ' + result.status + '). Details in browser console (F12).');
                }
            })
            .catch(error => {
                dismissAlerts();
                showAlert('error', 'Network error occurred. Please check your connection and try again.');
            });
        }, { once: true });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('selectAllPending');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            const checked = this.checked;
            getPendingCheckboxes().forEach(cb => { cb.checked = checked; });
            updateBulkButtons();
        });
    }

    // Delegated: fires for each row checkbox
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('pending-select')) {
            updateBulkButtons();
        }
    });

    // Initial state
    updateBulkButtons();
});

// ======================================================================
// NEXT SCHOOL YEAR APPLICATIONS — selection, modals, bulk promote/reject
// ======================================================================

function getNextYearCheckboxes() {
    return Array.from(document.querySelectorAll('.next-year-select'));
}

function getSelectedNextYearRows() {
    return getNextYearCheckboxes().filter(cb => cb.checked);
}

function nextYearUpdateBulkButtons() {
    const rows = getSelectedNextYearRows();
    const count = rows.length;
    const canBulk = count >= BULK_MIN_SELECTION;

    const promoteBtn = document.getElementById('bulkPromoteBtn');
    const rejectBtn = document.getElementById('bulkRejectNextYearBtn');
    if (promoteBtn) promoteBtn.disabled = !canBulk;
    if (rejectBtn) rejectBtn.disabled = !canBulk;

    const countLabel = document.getElementById('nextYearSelectedCount');
    if (countLabel) {
        if (count > 0) {
            countLabel.textContent = count + ' selected' + (canBulk ? '' : ' (select at least ' + BULK_MIN_SELECTION + ' to enable bulk actions)');
            countLabel.classList.remove('d-none');
        } else {
            countLabel.classList.add('d-none');
        }
    }

    const boxes = getNextYearCheckboxes();
    const selectAll = document.getElementById('nextYearSelectAll');
    if (selectAll && boxes.length > 0) {
        const checkedCount = boxes.filter(cb => cb.checked).length;
        selectAll.checked = checkedCount === boxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
    }
}

/**
 * Single PROMOTE — proper Bootstrap confirmation modal (never a native
 * confirm() dialog), then POSTs to Admin\Students::promote with the
 * student id carried on the row button.
 */
function nextYearPromoteOne(btn) {
    const studentId = parseInt(btn.dataset.studentId, 10);
    const nextLabel = btn.dataset.nextLabel || 'the next grade level';
    const name = btn.dataset.name || 'this student';
    if (isNaN(studentId)) return;

    const modalDiv = createPendingModal(
        'nextYearPromoteModal',
        'bg-success text-white',
        'Promote Student',
        `
            <p>Promote <strong>${name}</strong> to <strong>${nextLabel}</strong> for the next school year?</p>
            <p class="mb-2"><strong>This will:</strong></p>
            <ul class="mb-0">
              <li>Move them up to ${nextLabel}</li>
              <li>Clear their current section assignment (a new section is assigned for the next school year)</li>
              <li>Send them a portal notification</li>
            </ul>
        `,
        'confirmNextYearPromoteBtn',
        'btn-success',
        'Promote Student'
    );

    const modal = new bootstrap.Modal(modalDiv, { backdrop: 'static', keyboard: true, focus: true });
    modal.show();

    document.getElementById('confirmNextYearPromoteBtn')?.addEventListener('click', function () {
        const modalEl = document.getElementById('nextYearPromoteModal');
        bootstrap.Modal.getInstance(modalEl)?.hide();
        nextYearPost(
            `<?= base_url('admin/students/promote/') ?>${studentId}`,
            { next_grade_level: parseInt(btn.dataset.nextGrade, 10) },
            `Promoting ${name}...`
        );
    }, { once: true });
}

/**
 * Single REJECT — proper Bootstrap confirmation modal, then POSTs to
 * Admin\Students::rejectApplication with the application id.
 */
function nextYearRejectOne(btn) {
    const applicationId = parseInt(btn.dataset.applicationId, 10);
    const name = btn.dataset.name || 'this application';
    if (isNaN(applicationId)) return;

    const modalDiv = createPendingModal(
        'nextYearRejectModal',
        'bg-danger text-white',
        'Reject Application',
        `
            <p>Reject <strong>${name}</strong>'s application to enroll in the next grade level?</p>
            <p class="small text-muted mb-2">They will be notified by portal notification and advised to talk to their adviser. They may apply again if still eligible.</p>
            <div class="alert alert-warning mb-0">
              <i class="bi bi-exclamation-triangle"></i> This action cannot be undone.
            </div>
        `,
        'confirmNextYearRejectBtn',
        'btn-danger',
        'Reject Application'
    );

    const modal = new bootstrap.Modal(modalDiv, { backdrop: 'static', keyboard: true, focus: true });
    modal.show();

    document.getElementById('confirmNextYearRejectBtn')?.addEventListener('click', function () {
        const modalEl = document.getElementById('nextYearRejectModal');
        bootstrap.Modal.getInstance(modalEl)?.hide();
        nextYearPost(
            `<?= base_url('admin/students/next-year-applications/reject/') ?>${applicationId}`,
            {},
            `Rejecting application...`
        );
    }, { once: true });
}

function bulkPromoteSelected() {
    const rows = getSelectedNextYearRows();
    if (rows.length < BULK_MIN_SELECTION) {
        showToast('warning', 'Please select at least ' + BULK_MIN_SELECTION + ' applications first.');
        return;
    }

    const listHtml = rows.slice(0, 5).map(cb => `<li>${cb.dataset.name || 'Student'} &rarr; ${cb.dataset.nextLabel || ''}</li>`).join('');
    const more = rows.length > 5 ? `<li>and ${rows.length - 5} more...</li>` : '';

    const modalDiv = createPendingModal(
        'nextYearBulkPromoteModal',
        'bg-success text-white',
        'Bulk Promote Students',
        `
            <p>Promote <strong>${rows.length}</strong> student(s) to their next grade level for the next school year?</p>
            <ul class="small mb-2">${listHtml}${more}</ul>
            <p class="mb-2"><strong>This will:</strong></p>
            <ul class="mb-0">
              <li>Move each student up a grade level</li>
              <li>Clear each student's section assignment</li>
              <li>Notify each student</li>
            </ul>
        `,
        'confirmNextYearBulkPromoteBtn',
        'btn-success',
        'Promote All'
    );

    const modal = new bootstrap.Modal(modalDiv, { backdrop: 'static', keyboard: true, focus: true });
    modal.show();

    document.getElementById('confirmNextYearBulkPromoteBtn')?.addEventListener('click', function () {
        const ids = rows.map(cb => parseInt(cb.value, 10)).filter(v => !isNaN(v));
        const modalEl = document.getElementById('nextYearBulkPromoteModal');
        bootstrap.Modal.getInstance(modalEl)?.hide();
        nextYearPost(
            `<?= base_url('admin/students/next-year-applications/bulk-approve') ?>`,
            { ids: ids },
            `Promoting ${ids.length} student(s)...`
        );
    }, { once: true });
}

function bulkRejectNextYearSelected() {
    const rows = getSelectedNextYearRows();
    if (rows.length < BULK_MIN_SELECTION) {
        showToast('warning', 'Please select at least ' + BULK_MIN_SELECTION + ' applications first.');
        return;
    }

    const modalDiv = createPendingModal(
        'nextYearBulkRejectModal',
        'bg-danger text-white',
        'Bulk Reject Applications',
        `
            <p>Reject <strong>${rows.length}</strong> selected application(s)?</p>
            <p class="small text-muted mb-2">Each student will be notified and advised to talk to their adviser.</p>
            <div class="alert alert-warning mb-0">
              <i class="bi bi-exclamation-triangle"></i> This action cannot be undone.
            </div>
        `,
        'confirmNextYearBulkRejectBtn',
        'btn-danger',
        'Reject All'
    );

    const modal = new bootstrap.Modal(modalDiv, { backdrop: 'static', keyboard: true, focus: true });
    modal.show();

    document.getElementById('confirmNextYearBulkRejectBtn')?.addEventListener('click', function () {
        const ids = rows.map(cb => parseInt(cb.value, 10)).filter(v => !isNaN(v));
        const modalEl = document.getElementById('nextYearBulkRejectModal');
        bootstrap.Modal.getInstance(modalEl)?.hide();
        nextYearPost(
            `<?= base_url('admin/students/next-year-applications/bulk-reject') ?>`,
            { ids: ids },
            `Rejecting ${ids.length} application(s)...`
        );
    }, { once: true });
}

/**
 * Shared fetch for every next-year action. Bulk endpoints expect
 * { ids: [application ids] }; single endpoints post an empty JSON body with
 * the id in the URL. Results surface as toast notifications.
 */
function nextYearPost(url, payload, loadingMessage) {
    showToast('info', loadingMessage || 'Processing...');

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': PENDING_CSRF_HASH
        },
        body: JSON.stringify({ ...(payload || {}), [PENDING_CSRF_NAME]: PENDING_CSRF_HASH })
    })
    .then(response => {
        const status = response.status;
        return response.text().then(text => {
            let data;
            try { data = JSON.parse(text); }
            catch (e) { data = { error: 'Server returned status ' + status + '. ' + String(text).substring(0, 200) }; }
            return { status: status, data: data };
        });
    })
    .then(result => {
        if (result.data && result.data.success) {
            showToast('success', result.data.message || 'Done.');
            setTimeout(() => location.reload(), 1800);
        } else {
            console.error('Next-year action failed:', result.status, result.data);
            showToast('danger', (result.data && (result.data.error || result.data.message)) || 'Action failed (HTTP ' + result.status + ').');
        }
    })
    .catch(error => {
        console.error('Next-year action error:', error);
        showToast('danger', 'Network error occurred. Please check your connection and try again.');
    });
}

/**
 * Toast notifications (top-right, stacked, auto-dismiss) — used for all
 * next-year application feedback instead of native alert() dialogs.
 */
function showToast(type, message) {
    let container = document.getElementById('pendingToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'pendingToastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '20000';
        document.body.appendChild(container);
    }

    const styles = {
        success: { bg: 'bg-success',   icon: 'bi-check-circle-fill' },
        danger:  { bg: 'bg-danger',    icon: 'bi-exclamation-circle-fill' },
        warning: { bg: 'bg-warning text-dark', icon: 'bi-exclamation-triangle-fill' },
        info:    { bg: 'bg-primary',   icon: 'bi-info-circle-fill' }
    };
    const s = styles[type] || styles.info;

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white ${s.bg} border-0 shadow`;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${s.icon} me-2"></i>${String(message).replace(/\n/g, '<br>')}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>`;

    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: type === 'danger' ? 8000 : 4500 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove(), { once: true });
}

document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('nextYearSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            const checked = this.checked;
            getNextYearCheckboxes().forEach(cb => { cb.checked = checked; });
            nextYearUpdateBulkButtons();
        });
    }

    // Delegated: also covers rows re-rendered after a partial reload
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('next-year-select')) {
            nextYearUpdateBulkButtons();
        }
    });

    nextYearUpdateBulkButtons();
});

</script>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 16px;
    font-weight: bold;
}

.btn-group .btn {
    border-radius: 0;
}

.btn-group .btn:first-child {
    border-top-left-radius: 0.375rem;
    border-bottom-left-radius: 0.375rem;
}

.btn-group .btn:last-child {
    border-top-right-radius: 0.375rem;
    border-bottom-right-radius: 0.375rem;
}
</style>

<?= $this->endSection() ?>
