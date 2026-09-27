<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">
                        <i class="bi bi-person-plus text-warning"></i>
                        Pending Teacher Registrations
                        <span class="badge bg-warning text-dark ms-2"><?= count($pendingTeachers) ?></span>
                    </h5>
                    <div>
                        <a href="<?= base_url('admin/teachers') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to Teachers
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <i class="bi bi-info-circle"></i>
                        These accounts were submitted through the teacher registration link. Approving activates the
                        login account and gives the teacher access to the Teacher Portal.
                    </p>

                    <?php if (empty($pendingTeachers)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                            <h5 class="mt-2">No Pending Teacher Registrations</h5>
                            <p class="text-muted mb-0">All teacher sign-ups have been reviewed.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle admin-table" data-js-paged="1">
                                <thead class="table-light">
                                    <tr>
                                        <th>Teacher</th>
                                        <th>Email</th>
                                        <th>Position</th>
                                        <th>Teaching Area</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php foreach ($pendingTeachers as $teacher): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center me-2"
                                                         style="width: 38px; height: 38px; font-weight: 700; font-size: 0.85rem;">
                                                        <?= strtoupper(substr($teacher['first_name'] ?? 'T', 0, 1) . substr($teacher['last_name'] ?? '', 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold">
                                                            <?= esc(trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['middle_name'] ?? '') . ' ' . ($teacher['last_name'] ?? '') . ' ' . ($teacher['suffix'] ?? ''))) ?>
                                                        </div>
                                                        <div class="text-muted small">Employee No.: <?= esc($teacher['employee_id'] ?? 'Not assigned') ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= esc($teacher['email'] ?? '—') ?></td>
                                            <td><?= esc($teacher['position'] ?? '—') ?></td>
                                            <td><?= esc($teacher['department'] ?? '—') ?></td>
                                            <td>
                                                <span class="text-muted small">
                                                    <?= ! empty($teacher['created_at']) ? esc(date('M j, Y g:i A', strtotime($teacher['created_at']))) : '—' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-success btn-sm approve-teacher" data-id="<?= (int) $teacher['id'] ?>" data-name="<?= esc(trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? ''))) ?>">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm reject-teacher" data-id="<?= (int) $teacher['id'] ?>" data-name="<?= esc(trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? ''))) ?>">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
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
document.addEventListener('DOMContentLoaded', function () {
    const csrfName = '<?= csrf_token() ?>';
    const csrfHash = '<?= csrf_hash() ?>';

    function getPortal() {
        return document.getElementById('dashboard-modal-portal') || document.body;
    }

    function buildConfirmModal(id, titleClass, title, bodyHtml, confirmLabel) {
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
              <div class="modal-body">${bodyHtml}</div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn ${titleClass.includes('bg-success') ? 'btn-success' : 'btn-danger'}" data-action="confirm">${confirmLabel}</button>
              </div>
            </div>
          </div>`;

        getPortal().appendChild(modalDiv);

        modalDiv.addEventListener('hidden.bs.modal', function () {
            modalDiv.remove();
            document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
                backdrop.remove();
            });
        }, { once: true });

        return modalDiv;
    }

    function post(url) {
        const body = new FormData();
        body.append(csrfName, csrfHash);
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(response => {
            return response.json().catch(function () {
                throw new Error('Server returned an unexpected response.');
            });
        });
    }

    function openConfirmModal(button, action) {
        const isApprove = action === 'approve';
        const teacherName = button.dataset.name || 'this teacher registration';

        const modalDiv = buildConfirmModal(
            isApprove ? 'approveTeacherModal' : 'rejectTeacherModal',
            isApprove ? 'bg-success text-white' : 'bg-danger text-white',
            isApprove ? 'Confirm Approval' : 'Reject Registration',
            isApprove
                ? `<p>Are you sure you want to APPROVE <strong>${teacherName}</strong>?</p>
                   <p class="mb-2"><strong>This will:</strong></p>
                   <ul class="mb-0">
                     <li>Activate their login account</li>
                     <li>Give them access to the Teacher Portal immediately</li>
                   </ul>`
                : `<p>Are you sure you want to REJECT <strong>${teacherName}</strong>?</p>
                   <div class="alert alert-warning mb-0">
                     <i class="bi bi-exclamation-triangle"></i> The account will stay inactive.
                   </div>`,
            isApprove ? 'Confirm Approval' : 'Confirm Rejection'
        );

        const modal = new bootstrap.Modal(modalDiv, { backdrop: 'static', keyboard: true, focus: true });
        modal.show();

        const confirmBtn = modalDiv.querySelector('[data-action="confirm"]');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                confirmBtn.disabled = true;
                const label = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

                const instance = bootstrap.Modal.getInstance(modalDiv);
                if (instance) instance.hide();

                post(`<?= base_url('admin/teachers') ?>/${action}/` + button.dataset.id)
                    .then(function (data) {
                        if (data && data.success) {
                            showToast('success', (data.message) || (isApprove ? 'Registration approved.' : 'Registration rejected.'));
                            setTimeout(function () { window.location.reload(); }, 1800);
                        } else {
                            confirmBtn.disabled = false;
                            confirmBtn.innerHTML = label;
                            console.error('Teacher ' + action + ' failed:', data);
                            showToast('danger', (data && (data.error || data.message)) || (isApprove ? 'Unable to approve this registration.' : 'Unable to reject this registration.'));
                        }
                    })
                    .catch(function (error) {
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = label;
                        console.error('Teacher ' + action + ' network error:', error);
                        showToast('danger', 'Unable to reach the server. Please check your connection and try again.');
                    });
            }, { once: true });
        }
    }

    document.querySelectorAll('.approve-teacher').forEach(function (button) {
        button.addEventListener('click', function () {
            openConfirmModal(button, 'approve');
        });
    });

    document.querySelectorAll('.reject-teacher').forEach(function (button) {
        button.addEventListener('click', function () {
            openConfirmModal(button, 'reject');
        });
    });

    /**
     * Toast notifications (top-right, stacked, auto-dismiss) — same pattern as
     * the Pending Students page, used for all approve/reject feedback instead
     * of native alert() dialogs.
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
});
</script>
<?= $this->endSection() ?>