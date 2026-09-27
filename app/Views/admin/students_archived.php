<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3">Archived Students</h1>
  <div>
    <a href="<?= base_url('admin/students') ?>" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> Back to Students
    </a>
  </div>
</div>

<div class="alert alert-info">
  <i class="bi bi-info-circle"></i>
  <strong>Archived Students:</strong> These students have been archived and are no longer active in the system. 
  You can restore them to active status or permanently delete them from the system.
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (!empty($archivedStudents)): ?>
      <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
        <div>
          <input type="checkbox" id="selectAll" class="form-check-input me-2">
          <label for="selectAll" class="form-check-label">Select All</label>
          <span id="selectedCount" class="ms-2 text-muted">(0 selected)</span>
        </div>
        <div>
          <button id="bulkRestoreBtn" type="button" class="btn btn-success btn-sm me-2" style="display: none;">
            <i class="bi bi-arrow-clockwise"></i> Restore Selected
          </button>
          <button id="bulkDeleteBtn" type="button" class="btn btn-danger btn-sm" style="display: none;">
            <i class="bi bi-trash"></i> Delete Selected
          </button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 admin-table" data-js-paged="1">
          <thead>
            <tr>
              <th style="width: 40px;"></th>
              <th>LRN</th>
              <th>Name</th>
              <th>Grade</th>
              <th>Archived Date</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($archivedStudents as $st): ?>
              <tr>
                <td><input type="checkbox" class="form-check-input student-checkbox" data-student-id="<?= $st['id'] ?>" data-student-name="<?= esc($st['first_name'].' '.$st['last_name']) ?>"></td>
                <td><?= esc($st['lrn'] ?? '—') ?></td>
                <td><?= esc($st['first_name'].' '.$st['last_name']) ?></td>
                <td><?= esc(grade_level_label((int) ($st['grade_level'] ?? 0))) ?></td>
                <td><?= date('M j, Y g:i A', strtotime($st['deleted_at'])) ?></td>
                <td class="text-end">
                  <div class="btn-group" role="group">
                    <!-- confirmation-gate-2026-06-28 -->
                    <a href="<?= base_url('admin/students/view-archived/' . $st['id']) ?>" class="btn btn-sm btn-info" title="View Details">
                      <i class="bi bi-eye"></i> View
                    </a>
                    <button type="button" class="btn btn-sm btn-success" title="Restore Student" id="restoreBtn-<?= $st['id'] ?>" onclick="return confirm('Are you sure you want to restore <?= esc($st['first_name'] . ' ' . $st['last_name'], 'js') ?>?') && restoreStudent(<?= $st['id'] ?>, '<?= esc($st['first_name'] . ' ' . $st['last_name'], 'js') ?>')">
                      <i class="bi bi-arrow-clockwise"></i> Restore
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" title="Delete Permanently" id="deleteBtn-<?= $st['id'] ?>" onclick="return confirm('PERMANENT DELETE: <?= esc($st['first_name'] . ' ' . $st['last_name'], 'js') ?> cannot be undone. Continue?') && confirm('Final warning: all records will be permanently deleted.') && deleteStudentPermanently(<?= $st['id'] ?>, '<?= esc($st['first_name'] . ' ' . $st['last_name'], 'js') ?>')">
                      <i class="bi bi-trash"></i> Delete
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="p-4 text-center text-muted">
        <i class="bi bi-archive" style="font-size: 3rem; opacity: 0.3;"></i>
        <h5 class="mt-3">No Archived Students</h5>
        <p>There are currently no archived students in the system.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const selectAllCheckbox = document.getElementById('selectAll');
  const studentCheckboxes = document.querySelectorAll('.student-checkbox');
  const bulkRestoreBtn = document.getElementById('bulkRestoreBtn');
  const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
  const selectedCount = document.getElementById('selectedCount');

  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
      studentCheckboxes.forEach(checkbox => checkbox.checked = this.checked);
      updateBulkActions();
    });
  }

  studentCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
      updateBulkActions();
      if (selectAllCheckbox) {
        selectAllCheckbox.checked = Array.from(studentCheckboxes).every(cb => cb.checked);
      }
    });
  });

  function updateBulkActions() {
    const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
    if (selectedCount) selectedCount.textContent = `(${checkedCount} selected)`;
    if (bulkRestoreBtn) bulkRestoreBtn.style.display = checkedCount > 0 ? 'inline-block' : 'none';
    if (bulkDeleteBtn) bulkDeleteBtn.style.display = checkedCount > 0 ? 'inline-block' : 'none';
  }
});

function bulkRestoreStudents() {
  const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
  const studentIds = Array.from(checkedBoxes).map(cb => cb.dataset.studentId);
  const studentNames = Array.from(checkedBoxes).map(cb => cb.dataset.studentName);

  if (studentIds.length === 0) return alert('Please select at least one student.');

  fetch(`<?= base_url('admin/students/bulkRestore') ?>`, {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
    body: JSON.stringify({ student_ids: studentIds })
  })
  .then(response => response.json())
  .then(data => {
    alert(data.success ? data.message : 'Error: ' + data.error);
    if (data.success) location.reload();
  })
  .catch(() => alert('Failed to restore students'));
}

function bulkDeleteStudents() {
  const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
  const studentIds = Array.from(checkedBoxes).map(cb => cb.dataset.studentId);
  const studentNames = Array.from(checkedBoxes).map(cb => cb.dataset.studentName);

  if (studentIds.length === 0) return alert('Please select at least one student.');

  fetch(`<?= base_url('admin/students/bulkDeletePermanently') ?>`, {
    method: 'DELETE',
    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
    body: JSON.stringify({ student_ids: studentIds })
  })
  .then(response => response.json())
  .then(data => {
    alert(data.success ? data.message : 'Error: ' + data.error);
    if (data.success) location.reload();
  })
  .catch(() => alert('Failed to delete students'));
}

function restoreStudent(studentId, studentName) {
  if (typeof studentName === 'undefined' || studentName === null) studentName = '';
  fetch(`<?= base_url('admin/students/restore') ?>/${studentId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert(data.message);
      location.reload();
    } else {
      alert('Error: ' + (data.error || 'Failed to restore student'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to restore student');
  });
}

// Delete student permanently function
function deleteStudentPermanently(studentId, studentName) {
  if (typeof studentName === 'undefined' || studentName === null) studentName = '';
  fetch(`<?= base_url('admin/students/delete-permanently') ?>/${studentId}`, {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert(data.message);
      location.reload();
    } else {
      alert('Error: ' + (data.error || 'Failed to delete student permanently'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to delete student permanently');
  });
}
</script>

<?= $this->endSection() ?>