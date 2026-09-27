<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/page_header', ['pageHeader' => [
  'icon'     => 'bi-shield-lock',
  'title'    => 'Password reset requests',
  'subtitle' => 'Manage password reset requests from teachers and students',
]]) ?>

<!-- Filters -->
<!-- Filters — shared admin standard -->
<?php
  $prFilterValues = [
    'status'   => $filter_status ?? '',
    'q'        => $filter_q ?? '',
    'per_page' => (string) ($per_page ?? 15),
  ];

  $prActive = admin_filter_count_active(
    $prFilterValues,
    ['status', 'q', 'per_page'],
    ['', '15'] // "15 rows" is the default, so it is not a filter
  );

  echo view('admin/partials/filter_bar', ['filterBar' => [
    'action'      => base_url('admin/password-resets'),
    'id'          => 'passwordResetFilter',
    'label'       => 'Filter password reset requests',
    'resetUrl'    => base_url('admin/password-resets'),
    'activeCount' => $prActive['total'],
    'totalCount'  => $prActive['total'],
    'primary'     => [
      [
        'name' => 'q', 'label' => 'Search', 'icon' => 'bi-search', 'type' => 'search',
        'value'       => admin_filter_value($prFilterValues, 'q'),
        'placeholder' => 'Email, student name, or LRN',
      ],
      [
        'name' => 'status', 'label' => 'Status', 'icon' => 'bi-check2-circle',
        'value'   => admin_filter_value($prFilterValues, 'status'),
        'options' => [
          admin_filter_option('', 'All statuses'),
          admin_filter_option('pending', 'Pending'),
          admin_filter_option('approved', 'Approved'),
          admin_filter_option('rejected', 'Rejected'),
          admin_filter_option('used', 'Used'),
          admin_filter_option('expired', 'Expired'),
        ],
      ],
      [
        'name' => 'per_page', 'label' => 'Rows per page', 'icon' => 'bi-list-ul',
        'value'   => admin_filter_value($prFilterValues, 'per_page', '15'),
        'options' => [
          admin_filter_option('15', '15'),
          admin_filter_option('25', '25'),
          admin_filter_option('50', '50'),
        ],
      ],
    ],
    // Only three controls on this page, so all of them stay visible and the
    // "More filters" toggle is not rendered.
    'advanced' => [],
  ]]);
?>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i><?= session()->getFlashdata('success') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?= session()->getFlashdata('error') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="card bg-white border-0 shadow-sm rounded-3">
  <div class="card-body p-0">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
      <div>
        <input type="checkbox" id="selectAll" class="form-check-input me-2" onchange="toggleSelectAll()">
        <label for="selectAll" class="form-check-label">Select All</label>
      </div>
      <button class="btn btn-danger btn-sm" id="bulkDeleteBtn" onclick="bulkDelete()" disabled>
        <i class="bi bi-trash me-1"></i>Delete Selected (<span id="selectedCount">0</span>)
      </button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 admin-table">
        <thead class="table-light">
          <tr>
            <th class="border-0 fw-medium" style="width: 40px;"></th>
            <th class="border-0 fw-medium"><i class="bi bi-person me-1 text-muted"></i>User</th>
            <th class="border-0 fw-medium"><i class="bi bi-hash me-1 text-muted"></i>Identifier</th>
            <th class="border-0 fw-medium"><i class="bi bi-calendar3 me-1 text-muted"></i>Requested</th>
            <th class="border-0 fw-medium"><i class="bi bi-activity me-1 text-muted"></i>Status</th>
            <th class="border-0 fw-medium text-center"><i class="bi bi-gear me-1 text-muted"></i>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($requests)): ?>
            <?php foreach ($requests as $reset): ?>
              <tr>
                <td class="py-3">
                  <input type="checkbox" class="form-check-input reset-checkbox" value="<?= $reset['id'] ?>" onchange="updateSelectedCount()">
                </td>
                <td class="py-3">
                  <div class="fw-medium"><?= esc($reset['user_email'] ?? $reset['email']) ?></div>
                  <?php if (!empty($reset['first_name']) && !empty($reset['last_name'])): ?>
                    <small class="text-muted d-block"><?= esc($reset['first_name'] . ' ' . $reset['last_name']) ?></small>
                  <?php endif; ?>
                </td>
                <td class="py-3">
                  <span class="badge bg-light text-dark"><?= esc($reset['student_id'] ?? $reset['lrn'] ?? 'N/A') ?></span>
                </td>
                <td class="py-3">
                  <?php 
                    $date = new DateTime($reset['created_at']);
                    $date->setTimezone(new DateTimeZone('Asia/Manila'));
                  ?>
                  <small class="text-muted"><?= $date->format('M j, Y g:i A') ?></small>
                </td>
                <td class="py-3">
                  <?php 
                    $statusClass = match($reset['status']) {
                      'pending' => 'bg-warning',
                      'approved' => 'bg-success',
                      'rejected' => 'bg-danger',
                      'used' => 'bg-info',
                      'expired' => 'bg-secondary',
                      default => 'bg-secondary'
                    };
                  ?>
                  <span class="badge <?= $statusClass ?>"><?= ucfirst($reset['status']) ?></span>
                </td>
                <td class="py-3 text-center">
                  <?php if ($reset['status'] === 'pending'): ?>
                    <button class="btn btn-success btn-sm" onclick="approveReset(<?= $reset['id'] ?>)">
                      <i class="bi bi-check-circle me-1"></i>Approve
                    </button>
                    <button class="btn btn-danger btn-sm ms-1" onclick="rejectReset(<?= $reset['id'] ?>)">
                      <i class="bi bi-x-circle me-1"></i>Reject
                    </button>
                  <?php elseif ($reset['status'] === 'approved'): ?>
                    <a href="<?= base_url('admin/password-resets/change/' . $reset['id']) ?>" class="btn btn-primary btn-sm">
                      <i class="bi bi-key me-1"></i>Change Password
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No password reset requests match the current filters.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="text-muted small">
      <?php if (($total ?? 0) > 0): ?>
        Showing <?= (int) $showing_from ?>–<?= (int) $showing_to ?> of <?= (int) $total ?> requests
      <?php else: ?>
        No requests found
      <?php endif; ?>
    </div>
    <?php if (($total_pages ?? 1) > 1): ?>
      <nav aria-label="Password reset requests pagination">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?= ($current_page ?? 1) <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => ($current_page ?? 1) - 1])) ?>"><i class="bi bi-chevron-left"></i></a>
          </li>
          <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <li class="page-item <?= $p === (int) $current_page ? 'active' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= ($current_page ?? 1) >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => ($current_page ?? 1) + 1])) ?>"><i class="bi bi-chevron-right"></i></a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<style>
.custom-modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}
.custom-modal-content {
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 500px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}
.custom-modal-header {
  padding: 20px;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.custom-modal-body {
  padding: 20px;
  background: #fef9e7;
  text-align: center;
}
.custom-modal-footer {
  padding: 15px 20px;
  display: flex;
  justify-content: center;
  gap: 10px;
  border-top: 1px solid #dee2e6;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  let currentResetId = null;
  let csrfHash = '<?= csrf_hash() ?>';

  function csrfHeaders() {
    return {
      'X-Requested-With': 'XMLHttpRequest'
    };
  }

  function csrfBody() {
    return new URLSearchParams({ '<?= csrf_token() ?>': csrfHash });
  }

  function applyCsrfRefresh(data) {
    if (data && typeof data.csrf_hash === 'string' && data.csrf_hash !== '') {
      csrfHash = data.csrf_hash;
    }
  }

  window.toggleSelectAll = function() {
  const selectAll = document.getElementById('selectAll');
  const checkboxes = document.querySelectorAll('.reset-checkbox');
  checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSelectedCount();
  };

  window.updateSelectedCount = function() {
  const checkboxes = document.querySelectorAll('.reset-checkbox:checked');
  const count = checkboxes.length;
  document.getElementById('selectedCount').textContent = count;
  document.getElementById('bulkDeleteBtn').disabled = count === 0;
  
  const selectAll = document.getElementById('selectAll');
  const allCheckboxes = document.querySelectorAll('.reset-checkbox');
    selectAll.checked = allCheckboxes.length > 0 && count === allCheckboxes.length;
  };

  window.bulkDelete = function() {
  const checkboxes = document.querySelectorAll('.reset-checkbox:checked');
  const ids = Array.from(checkboxes).map(cb => cb.value);
  
  if (ids.length === 0) {
    alert('Please select at least one request to delete');
    return;
  }
  
  createModal('Confirm Bulk Delete', `Delete ${ids.length} password reset request(s)? This will only remove the request records, not undo any password changes.`, 'Confirm', () => {
    (async () => {
      let successCount = 0;
      for (const id of ids) {
        try {
          const response = await fetch(`<?= base_url('admin/password-resets/delete/') ?>${id}`, {
            method: 'POST',
            headers: csrfHeaders(),
            body: csrfBody()
          });
          const data = await response.json();
          applyCsrfRefresh(data);
          if (data.success) {
            successCount++;
          }
        } catch (error) {
          console.error('Error deleting request ' + id + ':', error);
        }
      }
      if (successCount > 0) {
        location.reload();
      } else {
        alert('Failed to delete requests');
      }
    })();
  });
  };

  window.createModal = function(title, message, confirmText, onConfirm) {
  const modalHtml = `
    <div class="custom-modal-backdrop" id="customModal">
      <div class="custom-modal-content">
        <div class="custom-modal-header">
          <h5 class="mb-0">${title}</h5>
          <button type="button" class="btn-close" onclick="closeCustomModal()"></button>
        </div>
        <div class="custom-modal-body">
          <p class="mb-0">${message}</p>
        </div>
        <div class="custom-modal-footer">
          <button type="button" class="btn btn-dark" onclick="closeCustomModal()">Cancel</button>
          <button type="button" class="btn btn-warning" onclick="confirmCustomModal()">${confirmText}</button>
        </div>
      </div>
    </div>
  `;
  document.body.insertAdjacentHTML('beforeend', modalHtml);
    window.customModalCallback = onConfirm;
  };

  window.closeCustomModal = function() {
  const modal = document.getElementById('customModal');
  if (modal) modal.remove();
    window.customModalCallback = null;
  };

  window.confirmCustomModal = function() {
  if (window.customModalCallback) {
    window.customModalCallback();
  }
    closeCustomModal();
  };

  window.approveReset = function(resetId) {
  currentResetId = resetId;
  createModal('Confirm Action', 'Approve this password reset request?', 'Confirm', () => {
    fetch(`<?= base_url('admin/password-resets/approve/') ?>${currentResetId}`, {
    method: 'POST',
    headers: csrfHeaders(),
    body: csrfBody()
    })
    .then(response => response.json())
    .then(data => {
      applyCsrfRefresh(data);
      if (data.success) {
        if (data.redirect) {
          window.location.href = data.redirect;
        } else {
          location.reload();
        }
      } else {
        alert('Error: ' + (data.error || 'Failed to approve request'));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while approving the request');
    });
  });
  };

  window.rejectReset = function(resetId) {
  currentResetId = resetId;
  createModal('Confirm Action', 'Reject this password reset request?', 'Confirm', () => {
    fetch(`<?= base_url('admin/password-resets/reject/') ?>${currentResetId}`, {
    method: 'POST',
    headers: csrfHeaders(),
    body: csrfBody()
    })
    .then(response => response.json())
    .then(data => {
      applyCsrfRefresh(data);
      if (data.success) {
        location.reload();
      } else {
        alert('Error: ' + (data.error || 'Failed to reject request'));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while rejecting the request');
    });
  });
  };

  window.deleteReset = function(resetId) {
  currentResetId = resetId;
  createModal('Confirm Action', 'Delete this password reset request? This will only remove the request record, not undo any password changes.', 'Confirm', () => {
    fetch(`<?= base_url('admin/password-resets/delete/') ?>${currentResetId}`, {
    method: 'POST',
    headers: csrfHeaders(),
    body: csrfBody()
    })
    .then(response => response.json())
    .then(data => {
      applyCsrfRefresh(data);
      if (data.success) {
        location.reload();
      } else {
        alert('Error: ' + (data.error || 'Failed to delete request'));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while deleting the request');
    });
  });
  };
});
</script>

<?= $this->endSection() ?>