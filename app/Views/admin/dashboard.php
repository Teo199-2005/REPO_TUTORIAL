<?= $this->extend('dashboard_layout') ?>

<?= $this->section('portal_overlays') ?>
<style><?= view('partials/password_requirements_style') ?></style>
<!-- Outside .main-content so backdrop stacks above fixed sidebar + sticky top bar -->
<div id="createAdminModal" class="custom-modal-overlay create-admin-modal-overlay" style="display: none;">
  <div class="create-admin-shell" role="dialog" aria-modal="true" aria-labelledby="createAdminModalTitle">
    <div class="create-admin-shell-header">
      <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
        <span class="create-admin-modal-header-icon" aria-hidden="true"><i class="bi bi-person-plus"></i></span>
        <div class="min-w-0">
          <h3 id="createAdminModalTitle" class="create-admin-shell-title mb-0">Create admin account</h3>
          <span class="create-admin-shell-subtitle">Add a master admin or restricted admin staff member</span>
        </div>
      </div>
      <button type="button" class="btn btn-sm rounded-circle create-admin-modal-close" onclick="closeCreateAdminModal()" aria-label="Close">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="create-admin-shell-body">
      <form id="createAdminForm">
        <p class="create-admin-section-title"><i class="bi bi-shield-check me-2"></i>Account type</p>
        <div class="row g-2 mb-4 create-admin-type-row">
          <div class="col-sm-6">
            <label class="create-admin-type-card" for="accountTypeMaster">
              <input class="staff-page-cb-sr" type="radio" name="account_type" id="accountTypeMaster" value="master" checked onchange="toggleStaffPagePickers()">
              <span class="create-admin-type-card-inner">
                <i class="bi bi-shield-lock create-admin-type-icon" aria-hidden="true"></i>
                <span class="create-admin-type-title">Master admin</span>
                <span class="create-admin-type-desc">Full access to all admin areas</span>
              </span>
            </label>
          </div>
          <div class="col-sm-6">
            <label class="create-admin-type-card" for="accountTypeStaff">
              <input class="staff-page-cb-sr" type="radio" name="account_type" id="accountTypeStaff" value="staff" onchange="toggleStaffPagePickers()">
              <span class="create-admin-type-card-inner">
                <i class="bi bi-person-badge create-admin-type-icon" aria-hidden="true"></i>
                <span class="create-admin-type-title">Admin staff</span>
                <span class="create-admin-type-desc">Limited to selected pages only</span>
              </span>
            </label>
          </div>
        </div>

        <p class="create-admin-section-title"><i class="bi bi-person-vcard me-2"></i>Account details</p>
        <div class="mb-3">
          <label for="adminEmail" class="form-label small fw-semibold text-secondary">Email</label>
          <div class="input-group">
            <span class="input-group-text create-admin-input-icon"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="adminEmail" name="email" placeholder="name@school.edu" required autocomplete="email">
          </div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label for="adminFirstName" class="form-label small fw-semibold text-secondary">First name</label>
            <div class="input-group">
              <span class="input-group-text create-admin-input-icon"><i class="bi bi-person"></i></span>
              <input type="text" class="form-control" id="adminFirstName" name="first_name" placeholder="Given name" required autocomplete="given-name">
            </div>
          </div>
          <div class="col-md-6">
            <label for="adminLastName" class="form-label small fw-semibold text-secondary">Last name</label>
            <div class="input-group">
              <span class="input-group-text create-admin-input-icon"><i class="bi bi-person"></i></span>
              <input type="text" class="form-control" id="adminLastName" name="last_name" placeholder="Family name" required autocomplete="family-name">
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label for="adminPassword" class="form-label small fw-semibold text-secondary">Password</label>
          <div class="input-group">
            <span class="input-group-text create-admin-input-icon"><i class="bi bi-key"></i></span>
            <input type="password" class="form-control" id="adminPassword" name="password"
                   placeholder="<?= esc(password_policy_summary()) ?>"
                   minlength="<?= password_policy_min_length() ?>" pattern="(?=.*\d).{<?= password_policy_min_length() ?>,}"
                   required autocomplete="new-password" data-password-indicator>
            <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Show password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <?= view('partials/password_requirements', ['compact' => true]) ?>
        </div>
        <div class="mb-3">
          <label for="adminConfirmPassword" class="form-label small fw-semibold text-secondary">Confirm password</label>
          <div class="input-group">
            <span class="input-group-text create-admin-input-icon"><i class="bi bi-key-fill"></i></span>
            <input type="password" class="form-control" id="adminConfirmPassword" name="confirm_password" placeholder="Re-enter password" required autocomplete="new-password">
          </div>
        </div>

        <div id="staffPagesSection" class="create-admin-pages-wrap" style="display: none;">
          <p class="create-admin-section-title mb-2"><i class="bi bi-ui-checks-grid me-2"></i>Pages admin staff can open</p>
          <p class="small text-muted mb-3">Choose at least one area. Staff will not see links or URLs for anything unchecked.</p>
          <div class="create-admin-page-grid">
            <?php foreach (admin_valid_page_keys() as $key): ?>
              <label class="create-admin-page-tile" for="staffpage_<?= esc($key) ?>">
                <input class="staff-page-cb" type="checkbox" name="pages[]" value="<?= esc($key) ?>" id="staffpage_<?= esc($key) ?>">
                <span class="create-admin-page-tile-inner">
                  <i class="bi <?= esc(admin_page_icon($key)) ?> create-admin-page-icon" aria-hidden="true"></i>
                  <span class="create-admin-page-label"><?= esc(admin_page_label($key)) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </form>
    </div>
    <div class="create-admin-shell-footer">
      <button type="button" class="btn btn-outline-secondary px-4" onclick="closeCreateAdminModal()"><i class="bi bi-x-lg me-2"></i>Cancel</button>
      <button type="button" class="btn btn-primary px-4" onclick="submitCreateAdminForm()"><i class="bi bi-check2-circle me-2"></i>Create account</button>
    </div>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<script>
function toggleStaffPagePickers() {
  const staff = document.getElementById('accountTypeStaff');
  const section = document.getElementById('staffPagesSection');
  if (!staff || !section) return;
  section.style.display = staff.checked ? 'block' : 'none';
}

function openCreateAdminModal() {
  const modal = document.getElementById('createAdminModal');
  const footer = document.querySelector('.modern-footer');
  
  if (footer) footer.style.display = 'none';
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  const master = document.getElementById('accountTypeMaster');
  if (master) master.checked = true;
  toggleStaffPagePickers();
  document.querySelectorAll('.staff-page-cb').forEach(cb => { cb.checked = false; });
}

function closeCreateAdminModal() {
  const modal = document.getElementById('createAdminModal');
  const footer = document.querySelector('.modern-footer');
  
  modal.style.display = 'none';
  if (footer) footer.style.display = 'block';
  document.body.style.overflow = '';
  
  document.getElementById('createAdminForm').reset();
}

function submitCreateAdminForm() {
  const form = document.getElementById('createAdminForm');
  const password = document.getElementById('adminPassword').value;
  const confirmPassword = document.getElementById('adminConfirmPassword').value;
  const staffRadio = document.getElementById('accountTypeStaff');
  
  if (password !== confirmPassword) {
    showToast('danger', 'Passwords do not match!');
    return;
  }
  if (staffRadio && staffRadio.checked) {
    const any = form.querySelectorAll('.staff-page-cb:checked').length > 0;
    if (!any) {
      showToast('warning', 'Select at least one page for admin staff.');
      return;
    }
  }
  
  const formData = new FormData(form);
  const btn = document.querySelector('.create-admin-shell-footer .btn-primary');
  const originalContent = btn.innerHTML;
  
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Creating...';
  
  fetch('<?= base_url('admin/dashboard/createAdmin') ?>', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast('success', 'Admin account created successfully!');
      setTimeout(() => {
        closeCreateAdminModal();
        location.reload();
      }, 1500);
    } else {
      showToast('danger', 'Error: ' + data.message);
      btn.disabled = false;
      btn.innerHTML = originalContent;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('danger', 'An error occurred while creating the admin account.');
    btn.disabled = false;
    btn.innerHTML = originalContent;
  });
}

document.addEventListener('DOMContentLoaded', function() {
  const togglePassword = document.getElementById('togglePassword');
  if (togglePassword) {
    togglePassword.addEventListener('click', function() {
      const password = document.getElementById('adminPassword');
      const confirmPassword = document.getElementById('adminConfirmPassword');
      const icon = this.querySelector('i');
      
      if (password.type === 'password') {
        password.type = 'text';
        confirmPassword.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
      } else {
        password.type = 'password';
        confirmPassword.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
      }
    });
  }
});
</script>

<div class="admin-dashboard-home">
<div class="dashboard-header admin-dash-header mb-3">
  <div class="d-flex align-items-center gap-3">
    <div class="dash-page-icon" aria-hidden="true"><i class="bi bi-speedometer2"></i></div>
    <div>
      <h2 class="mb-0" style="font-size:1.25rem;">Admin Dashboard</h2>
      <small class="text-muted">Overview and quick actions</small>
    </div>
  </div>
  <div class="admin-dash-actions d-flex flex-wrap gap-2">
    <div class="btn-group flex-wrap admin-dash-actions-nav">
  <a href="<?= base_url('admin/students') ?>" class="btn btn-sm btn-outline-primary compact-md-hide-text"><i class="bi bi-people-fill me-2"></i>Students</a>
  <a href="<?= base_url('admin/teachers') ?>" class="btn btn-sm btn-outline-primary compact-md-hide-text"><i class="bi bi-person-video3 me-2"></i>Teachers</a>
  <a href="<?= base_url('admin/sections') ?>" class="btn btn-sm btn-outline-primary compact-md-hide-text"><i class="bi bi-grid-3x3-gap me-2"></i>Subjects & Sections</a>
  <a href="<?= base_url('announcements/admin') ?>" class="btn btn-sm btn-outline-primary compact-md-hide-text"><i class="bi bi-megaphone me-2"></i>Announcements</a>
  <a href="<?= base_url('admin/students/pending') ?>" class="btn btn-sm btn-outline-primary compact-md-hide-text"><i class="bi bi-clock-history me-2"></i>Pending Applications</a>
  <a href="<?= base_url('admin/analytics') ?>" class="btn btn-sm btn-outline-primary compact-md-hide-text"><i class="bi bi-graph-up me-2"></i>Analytics</a>
    </div>
    
    <?php if (function_exists('is_master_admin') && is_master_admin()): ?>
    <button type="button" class="btn btn-sm btn-warning" onclick="openCreateAdminModal()" id="createAdminBtn">
      <i class="bi bi-person-plus me-1"></i>Create admin
    </button>
    <?php endif; ?>
    
    <button type="button" class="btn btn-sm <?= ($registrationEnabled ?? true) ? 'btn-success' : 'btn-danger' ?>" onclick="toggleEnrollment()" id="enrollmentToggleBtn">
      <i class="bi <?= ($registrationEnabled ?? true) ? 'bi-unlock' : 'bi-lock' ?> me-1"></i><?= ($registrationEnabled ?? true) ? 'Registration Open' : 'Registration Closed' ?>
    </button>
    
    <button type="button" class="btn btn-sm <?= ($gradingEnabled ?? true) ? 'btn-success' : 'btn-danger' ?>" onclick="toggleGrading()" id="gradingToggleBtn">
      <i class="bi <?= ($gradingEnabled ?? true) ? 'bi-pencil-square' : 'bi-lock-fill' ?> me-1"></i><?= ($gradingEnabled ?? true) ? 'Grading Open' : 'Grading Closed' ?>
    </button>
  </div>
</div>

<?php /* Removed feature tiles grid as requested */ ?>

<!-- Executive summary: Quick stats KPI cards -->
<?php
  $pendingCount    = (int)($pending_enrollments ?? 0);
  $pendingSeverity = $pendingCount === 0 ? 'green' : ($pendingCount <= 10 ? 'yellow' : 'red');
  $regEnabled      = (bool)($registrationEnabled ?? true);
  $gradingOn       = (bool)($gradingEnabled ?? true);
  $teacherTotal    = (int)($total_teachers ?? 0);
  $summaryUpdated  = ($enrollmentLastUpdated ?? null)
    ? esc(date('M j, Y g:i A', strtotime($enrollmentLastUpdated)))
    : '—';
?>
<div class="admin-dash-summary-head">
  <h6 class="card-title mb-0 fw-semibold"><i class="bi bi-grid-1x2 me-1"></i> Quick stats</h6>
  <small class="admin-dash-updated"><i class="bi bi-clock-history"></i> Snapshot · Updated <?= $summaryUpdated ?></small>
</div>
<div class="admin-dash-summary-grid">
  <!-- 1 · Pending Applications -->
  <a href="<?= base_url('admin/students/pending') ?>" class="admin-dash-sum-card admin-dash-sum-tappable">
    <div class="admin-dash-sum-icon admin-dash-sum-icon--<?= $pendingSeverity ?>" aria-hidden="true"><i class="bi bi-inbox"></i></div>
    <div class="admin-dash-sum-body">
      <span class="admin-dash-sum-label">Pending Applications
        <i class="bi bi-info-circle" title="Enrollment applications waiting for review. Click this card to open the review page."></i>
      </span>
      <span class="admin-dash-sum-value admin-dash-sum-value--<?= $pendingSeverity ?>" data-counter="<?= $pendingCount ?>"><?= $pendingCount ?></span>
      <span class="admin-dash-sum-helper">
        <?= $pendingCount === 0 ? 'No applications awaiting review' : ($pendingCount <= 10 ? 'Applications awaiting your review' : 'High volume — review soon') ?>
      </span>
    </div>
  </a>

  <!-- 2 · Enrollment Status -->
  <div class="admin-dash-sum-card">
    <div class="admin-dash-sum-icon admin-dash-sum-icon--<?= $regEnabled ? 'green' : 'red' ?>" aria-hidden="true">
      <i class="bi <?= $regEnabled ? 'bi-door-open' : 'bi-door-closed' ?>"></i>
    </div>
    <div class="admin-dash-sum-body">
      <span class="admin-dash-sum-label">Enrollment Status
        <i class="bi bi-info-circle" title="Controls whether new student registrations are accepted. Toggle from the header buttons above."></i>
      </span>
      <span class="admin-dash-sum-badge admin-dash-sum-badge--<?= $regEnabled ? 'open' : 'closed' ?>">
        <span class="admin-dash-stat-dot"></span><?= $regEnabled ? 'Open' : 'Closed' ?>
      </span>
      <span class="admin-dash-sum-helper">Enrollment period · SY <?= esc($schoolYear ?? get_current_school_year()) ?></span>
    </div>
  </div>

  <!-- 3 · Grade Encoding -->
  <div class="admin-dash-sum-card">
    <div class="admin-dash-sum-icon admin-dash-sum-icon--<?= $gradingOn ? 'cyan' : 'slate' ?>" aria-hidden="true"><i class="bi bi-pencil-square"></i></div>
    <div class="admin-dash-sum-body">
      <span class="admin-dash-sum-label">Grade Encoding
        <i class="bi bi-info-circle" title="Controls whether teachers can enter grades. Toggle from the header buttons above."></i>
      </span>
      <span class="admin-dash-sum-badge admin-dash-sum-badge--<?= $gradingOn ? 'open' : 'closed' ?>">
        <span class="admin-dash-stat-dot"></span><?= $gradingOn ? 'Enabled' : 'Disabled' ?>
      </span>
      <span class="admin-dash-sum-helper">
        <?= $gradingOn ? "Available to {$teacherTotal} teacher" . ($teacherTotal === 1 ? '' : 's') : "Paused for {$teacherTotal} teacher" . ($teacherTotal === 1 ? '' : 's') ?>
      </span>
    </div>
  </div>

  <!-- 4 · Current School Year -->
  <div class="admin-dash-sum-card admin-dash-sum-card--accent">
    <div class="admin-dash-sum-icon admin-dash-sum-icon--blue" aria-hidden="true"><i class="bi bi-calendar-check"></i></div>
    <div class="admin-dash-sum-body">
      <span class="admin-dash-sum-label">Current School Year
        <i class="bi bi-info-circle" title="The active academic period used across enrollment and grading."></i>
      </span>
      <span class="admin-dash-sum-value admin-dash-sum-value--sy">SY <?= esc($schoolYear ?? get_current_school_year()) ?></span>
      <span class="admin-dash-sum-helper">
        Term <?= esc((string)($currentTerm ?? get_current_term())) ?>
        <span class="admin-dash-sum-dots" title="Terms in the school year; filled dot = current term">
          <span class="<?= (int)($currentTerm ?? 1) >= 1 ? 'active' : '' ?>"></span>
          <span class="<?= (int)($currentTerm ?? 1) >= 2 ? 'active' : '' ?>"></span>
          <span class="<?= (int)($currentTerm ?? 1) >= 3 ? 'active' : '' ?>"></span>
        </span>
        · <span class="admin-dash-sum-status-on">Active</span>
      </span>
    </div>
  </div>
</div>

<!-- Top Row: 2 Charts -->
<div class="admin-dash-grid admin-dash-grid--top">
  <div class="admin-dash-widget admin-dash-widget--enrollment">
    <div class="card h-100 admin-dash-enroll-card">
      <div class="card-header py-2">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
          <div>
            <h6 class="card-title mb-0 fw-semibold"><i class="bi bi-people me-1"></i>Enrolled Students</h6>
            <small class="text-muted">Kindergarten – Grade 6 Enrollment Distribution</small>
          </div>
          <div class="text-end">
            <div class="chart-controls admin-dash-year-picker">
              <i class="bi bi-calendar3" aria-hidden="true"></i>
              <select id="yearFilter" class="form-select form-select-sm" aria-label="School year" onchange="updateEnrollmentChart()">
                <?php foreach ($availableYears as $year): ?>
                  <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <small class="admin-dash-updated d-block mt-1">
              <i class="bi bi-clock-history"></i> Updated <?= ($enrollmentLastUpdated ?? null) ? esc(date('M j, Y g:i A', strtotime($enrollmentLastUpdated))) : '—' ?>
            </small>
          </div>
        </div>
      </div>
      <div class="card-body py-2">
        <?php
          // Legend data: nonzero grades only; color order matches the JS doughnut palette
          $enrollPalette = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#a5b4fc', '#7c3aed', '#64748b'];
          $enrollLegendItems = [];
          $enrollColorIndex = 0;
          foreach ($enrollmentByGrade as $grade => $count) {
            if ($count <= 0) {
              continue;
            }
            $enrollLegendItems[] = [
              'label' => grade_level_label((int) $grade),
              'count' => $count,
              'pct'   => $enrollmentTotal > 0 ? round(($count / $enrollmentTotal) * 100, 1) : 0,
              'color' => $enrollPalette[$enrollColorIndex % count($enrollPalette)],
            ];
            $enrollColorIndex++;
          }
          usort($enrollLegendItems, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        ?>

        <!-- KPI row -->
        <div class="admin-dash-kpi-row">
          <div class="admin-dash-kpi">
            <span class="admin-dash-kpi-num" id="totalEnrollment"><?= (int) $enrollmentTotal ?></span>
            <span class="admin-dash-total-label">Total Students</span>
          </div>
          <div class="admin-dash-kpi">
            <span class="admin-dash-kpi-num"><?= (int) $enrollmentActiveGrades ?></span>
            <span class="admin-dash-total-label">Active Grade Levels</span>
          </div>
          <div class="admin-dash-kpi">
            <span class="admin-dash-kpi-num admin-dash-kpi-num--sm"><?= $enrollmentTopGrade ? esc($enrollmentTopGrade['label']) : '—' ?></span>
            <span class="admin-dash-total-label">Largest Grade<?= $enrollmentTopGrade ? ' · ' . (int) $enrollmentTopGrade['count'] : '' ?></span>
          </div>
          <div class="admin-dash-kpi">
            <?php if ($enrollmentGrowth === null): ?>
              <span class="admin-dash-kpi-num admin-dash-kpi-num--muted">—</span>
              <span class="admin-dash-total-label">No prior year</span>
            <?php else: ?>
              <span class="admin-dash-kpi-num <?= $enrollmentGrowth >= 0 ? 'text-success' : 'text-danger' ?>">
                <i class="bi bi-arrow-<?= $enrollmentGrowth >= 0 ? 'up' : 'down' ?>-right"></i><?= abs($enrollmentGrowth) ?>%
              </span>
              <span class="admin-dash-total-label">Enrollment Growth</span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ((int) $enrollmentTotal <= 0): ?>
          <!-- Empty state -->
          <div class="admin-dash-enroll-empty text-center py-4">
            <i class="bi bi-people" aria-hidden="true"></i>
            <p class="mb-2">No enrollment data available for the selected school year.</p>
            <a href="<?= base_url('admin/students/create') ?>" class="btn btn-sm btn-primary">
              <i class="bi bi-person-plus me-1"></i>Add Student
            </a>
          </div>
        <?php else: ?>
          <!-- Doughnut + legend (chart left, breakdown right on desktop) -->
          <div class="admin-dash-enroll-split">
            <div class="chart-container admin-dash-chart">
              <canvas id="enrollmentChart"></canvas>
            </div>
            <div class="admin-dash-enroll-legend-wrap">
              <button class="btn btn-sm admin-dash-legend-toggle d-md-none w-100 mb-1" type="button" data-bs-toggle="collapse" data-bs-target="#enrollLegend" aria-expanded="false" aria-controls="enrollLegend">
                <i class="bi bi-pie-chart me-1"></i>Legend <i class="bi bi-chevron-down"></i>
              </button>
              <div class="collapse d-md-block" id="enrollLegend">
                <div class="admin-dash-enroll-legend">
                  <?php foreach ($enrollLegendItems as $item): ?>
                    <div class="admin-dash-legend-badge">
                      <span class="admin-dash-legend-dot" style="background: <?= $item['color'] ?>;"></span>
                      <span class="admin-dash-legend-name"><?= esc($item['label']) ?></span>
                      <span class="admin-dash-legend-meta"><?= (int) $item['count'] ?> · <?= $item['pct'] ?>%</span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ((int) $enrollmentTotal > 0): ?>
          <!-- Insights -->
          <div class="admin-dash-enroll-insights">
            <div class="admin-dash-insight">
              <small>Highest</small>
              <strong><?= $enrollmentTopGrade ? esc($enrollmentTopGrade['label']) : '—' ?></strong>
            </div>
            <div class="admin-dash-insight">
              <small>Lowest</small>
              <strong><?= $enrollmentLowGrade ? esc($enrollmentLowGrade['label']) : '—' ?></strong>
            </div>
            <div class="admin-dash-insight">
              <small>Total Enrollment</small>
              <strong><?= (int) $enrollmentTotal ?></strong>
            </div>
            <div class="admin-dash-insight">
              <small>Distribution</small>
              <strong><?= $enrollmentTopGrade ? esc($enrollmentTopGrade['label']) . ' leads · ' . round(($enrollmentTopGrade['count'] / max(1, $enrollmentTotal)) * 100) . '%' : '—' ?></strong>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Quick stats relocated to the executive summary row above -->
</div>

<!-- Bottom Row -->
<div class="admin-dash-grid admin-dash-grid--bottom">
  <div class="admin-dash-widget">
    <div class="card h-100 admin-dash-card">
      <div class="card-header py-2">
        <div class="d-flex justify-content-between align-items-center gap-2">
          <h6 class="card-title mb-0 fw-semibold"><i class="bi bi-clipboard2-check me-1"></i>Recent Enrollment Applications</h6>
          <a href="<?= base_url('admin/students/pending') ?>" class="admin-dash-stat-link">
            View all <i class="bi bi-arrow-right-short"></i>
          </a>
        </div>
      </div>
      <div class="card-body py-2">
        <?php if (!empty($recentEnrollments)): ?>
          <div class="admin-dash-app-list">
            <?php foreach (array_slice($recentEnrollments, 0, 5) as $enrollment): ?>
              <?php
                $statusMeta = match($enrollment['enrollment_status']) {
                  'enrolled' => ['icon' => 'bi-check-circle-fill',   'class' => 'is-enrolled'],
                  'pending'  => ['icon' => 'bi-hourglass-split',     'class' => 'is-pending'],
                  'rejected' => ['icon' => 'bi-x-circle-fill',       'class' => 'is-rejected'],
                  'approved' => ['icon' => 'bi-patch-check-fill',    'class' => 'is-approved'],
                  default    => ['icon' => 'bi-question-circle-fill','class' => 'is-default'],
                };
                $firstName = trim((string)($enrollment['first_name'] ?? ''));
                $lastName  = trim((string)($enrollment['last_name'] ?? ''));
                $initials  = mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1);
                $initials  = ($initials !== '') ? strtoupper($initials) : '';
              ?>
              <div class="admin-dash-app-item <?= $statusMeta['class'] ?>">
                <span class="admin-dash-avatar" aria-hidden="true">
                  <?= $initials !== '' ? esc($initials) : '<i class="bi bi-person-fill"></i>' ?>
                </span>
                <span class="admin-dash-app-meta">
                  <span class="admin-dash-app-name"><?= esc($lastName . ', ' . $firstName) ?></span>
                  <span class="admin-dash-app-grade">
                    <i class="bi bi-mortarboard"></i><?= esc(grade_level_label((int) ($enrollment['grade_level'] ?? 0))) ?>
                  </span>
                </span>
                <span class="admin-dash-app-status">
                  <i class="bi <?= $statusMeta['icon'] ?>"></i><?= ucfirst($enrollment['enrollment_status']) ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-4">
            <i class="bi bi-person-check text-muted fs-1 mb-2"></i>
            <p class="text-muted mb-2">No recent enrollment applications.</p>
            <p class="small text-muted mb-3">New student registrations will appear here for review.</p>
            <a href="<?= base_url('admin/students/pending') ?>" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-clock-history me-1"></i> View Pending Applications
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="admin-dash-widget">
    <div class="card h-100 admin-dash-card">
      <div class="card-header py-2">
        <div class="d-flex justify-content-between align-items-center gap-2">
          <h6 class="card-title mb-0 fw-semibold"><i class="bi bi-bar-chart-line me-1"></i>Enrollment by Grade Level</h6>
          <small class="admin-dash-card-subtitle">Kindergarten – Grade 6</small>
        </div>
      </div>
      <div class="card-body py-2">
        <div class="chart-container admin-dash-chart admin-dash-chart--md">
          <canvas id="gradeChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="admin-dash-widget">
    <div class="card h-100 admin-dash-card">
      <div class="card-header py-2">
        <div class="d-flex justify-content-between align-items-center gap-2">
          <h6 class="card-title mb-0 fw-semibold"><i class="bi bi-megaphone me-1"></i>Recent Announcements</h6>
          <a href="<?= base_url('admin/announcements') ?>" class="admin-dash-stat-link">
            Manage <i class="bi bi-gear"></i>
          </a>
        </div>
      </div>
      <div class="card-body py-2">
        <?php if (!empty($recentAnnouncements)): ?>
          <div class="admin-dash-ann-list">
            <?php foreach (array_slice($recentAnnouncements, 0, 3) as $announcement): ?>
              <div class="admin-dash-ann-item">
                <span class="admin-dash-ann-icon" aria-hidden="true"><i class="bi bi-megaphone-fill"></i></span>
                <div class="admin-dash-ann-meta">
                  <div class="admin-dash-ann-top">
                    <h6 class="admin-dash-ann-title"><?= esc($announcement['title']) ?></h6>
                    <span class="admin-dash-ann-date">
                      <i class="bi bi-calendar3"></i><?= $announcement['created_at'] ? date('M j, Y', strtotime($announcement['created_at'])) : 'N/A' ?>
                    </span>
                  </div>
                  <p class="admin-dash-ann-body"><?= esc(strip_tags($announcement['body'])) ?></p>
                  <span class="admin-dash-ann-target">
                    <i class="bi bi-bullseye"></i>Target: <?= esc(ucwords(str_replace('_', ' ', (string)($announcement['target_roles'] ?? '')))) ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-4">
            <i class="bi bi-megaphone text-muted fs-1 mb-2"></i>
            <p class="text-muted mb-2">No recent announcements.</p>
            <p class="small text-muted mb-3">Create announcements to communicate with students, teachers, and parents.</p>
            <a href="<?= base_url('admin/announcements') ?>" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-plus-circle me-1"></i> Create Announcement
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if (! empty($adminStaffList) && function_exists('is_master_admin') && is_master_admin()): ?>
<div class="card border-0 shadow-sm mt-4">
  <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>Admin staff</h6>
    <div class="d-flex align-items-center gap-2">
      <small class="text-muted d-none d-md-inline">Page access for restricted admin accounts</small>
      <button type="button" id="staffDeleteBtn" class="btn btn-sm btn-outline-danger" disabled
              onclick="openStaffDeleteModal()">
        <i class="bi bi-trash me-1"></i>Delete selected
        <span id="staffDeleteCount" class="badge bg-danger ms-1 d-none">0</span>
      </button>
    </div>
  </div>
  <div class="card-body py-2">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0" data-no-enhance="1">
        <thead><tr>
          <th style="width: 36px;"><input type="checkbox" class="form-check-input" id="staffSelectAll" aria-label="Select all admin staff"></th>
          <th>Email</th><th>Name</th><th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($adminStaffList as $row): ?>
            <tr>
              <td><input type="checkbox" class="form-check-input staff-row-cb" value="<?= (int) ($row['id'] ?? 0) ?>" aria-label="Select <?= esc($row['email'] ?? 'account') ?>"></td>
              <td><?= esc($row['email'] ?? '') ?></td>
              <td><?= esc(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
              <td class="text-end">
                <a href="<?= base_url('admin/dashboard/staff-permissions/' . (int) ($row['id'] ?? 0)) ?>" class="btn btn-sm btn-outline-primary">Edit pages</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Confirm deletion of selected admin staff -->
<div class="modal fade" id="staffDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-3">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-trash me-2"></i>Delete Admin Staff Accounts</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to <strong>DELETE</strong> <span id="staffDeleteModalCount">0</span> selected admin staff account(s)?</p>
        <ul class="mb-2 small text-muted">
          <li>Their login accounts will be deactivated immediately</li>
          <li>Their page access settings will be cleared</li>
        </ul>
        <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle"></i> This action cannot be undone from this page.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="staffDeleteConfirmBtn"><i class="bi bi-trash me-2"></i>Delete</button>
      </div>
    </div>
  </div>
</div>
<script>
// ---- Admin staff checkbox selection & bulk deletion ----
const STAFF_CSRF_NAME = '<?= csrf_token() ?>';
const STAFF_CSRF_HASH = '<?= csrf_hash() ?>';

function staffSelectedIds() {
  return Array.from(document.querySelectorAll('.staff-row-cb:checked'))
    .map(cb => parseInt(cb.value, 10))
    .filter(v => !isNaN(v) && v > 0);
}

function staffUpdateBulkButtons() {
  const ids = staffSelectedIds();
  const btn = document.getElementById('staffDeleteBtn');
  const count = document.getElementById('staffDeleteCount');
  if (btn) btn.disabled = ids.length === 0;
  if (count) {
    count.textContent = ids.length;
    count.classList.toggle('d-none', ids.length === 0);
  }
  const selectAll = document.getElementById('staffSelectAll');
  const boxes = document.querySelectorAll('.staff-row-cb');
  if (selectAll && boxes.length > 0) {
    const checked = Array.from(boxes).filter(cb => cb.checked).length;
    selectAll.checked = checked === boxes.length;
    selectAll.indeterminate = checked > 0 && checked < boxes.length;
  }
}

function openStaffDeleteModal() {
  const ids = staffSelectedIds();
  if (ids.length === 0) {
    showToast('warning', 'Select at least one admin staff account first.');
    return;
  }
  const counter = document.getElementById('staffDeleteModalCount');
  if (counter) counter.textContent = ids.length;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('staffDeleteModal')).show();
}

function staffPost(url, payload) {
  return fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': STAFF_CSRF_HASH
    },
    body: JSON.stringify({ ...(payload || {}), [STAFF_CSRF_NAME]: STAFF_CSRF_HASH })
  }).then(response => response.json().catch(() => ({ success: false, error: 'Server returned an unexpected response.' })));
}

document.addEventListener('DOMContentLoaded', function () {
  const selectAll = document.getElementById('staffSelectAll');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.staff-row-cb').forEach(cb => { cb.checked = selectAll.checked; });
      staffUpdateBulkButtons();
    });
  }
  document.querySelectorAll('.staff-row-cb').forEach(cb => {
    cb.addEventListener('change', staffUpdateBulkButtons);
  });

  const confirmBtn = document.getElementById('staffDeleteConfirmBtn');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', function () {
      const ids = staffSelectedIds();
      if (ids.length === 0) {
        bootstrap.Modal.getInstance(document.getElementById('staffDeleteModal'))?.hide();
        return;
      }

      confirmBtn.disabled = true;
      confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';

      staffPost('<?= base_url('admin/dashboard/staff-delete-batch') ?>', { ids: ids })
        .then(data => {
          bootstrap.Modal.getInstance(document.getElementById('staffDeleteModal'))?.hide();
          if (data && data.success) {
            showToast('success', data.message || 'Admin staff account(s) deleted.');
            setTimeout(() => location.reload(), 1800);
          } else {
            showToast('danger', (data && (data.error || data.message)) || 'Deletion failed.');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="bi bi-trash me-2"></i>Delete';
          }
        })
        .catch(error => {
          console.error('Staff deletion error:', error);
          bootstrap.Modal.getInstance(document.getElementById('staffDeleteModal'))?.hide();
          showToast('danger', 'Unable to reach the server. Please check your connection and try again.');
          confirmBtn.disabled = false;
          confirmBtn.innerHTML = '<i class="bi bi-trash me-2"></i>Delete';
        });
    });
  }

  staffUpdateBulkButtons();
});
</script>
<?php endif; ?>

</div>

<style>
/* Clean modal styling */
.modal-xl {
  max-width: 1200px;
}

.modal-body {
  max-height: 70vh;
  overflow-y: auto;
}

.announcement-item {
  border: 1px solid #dee2e6;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1rem;
  background: #f8f9fa;
  transition: all 0.2s ease;
}

.announcement-item:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  transform: translateY(-1px);
}

.announcement-title {
  font-weight: 700;
  color: #495057;
  margin-bottom: 0.5rem;
}

.announcement-body {
  color: #6c757d;
  margin-bottom: 0.75rem;
  line-height: 1.5;
}

.announcement-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.875rem;
  color: #6c757d;
}

.target-badge {
  background: #e9ecef;
  color: #495057;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.8125rem;
  font-weight: 400;
}

/* Custom Modal Styles */
.custom-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
}

.custom-modal-container {
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.10), 0 24px 56px -8px rgba(15, 23, 42, 0.16);
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow: hidden;
  position: relative;
  z-index: 100001;
  pointer-events: auto;
}

.custom-modal-header {
  background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
  color: #ffffff !important;
  padding: 1.5rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.custom-modal-header * {
  color: #ffffff !important;
}

.custom-modal-title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: #ffffff !important;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.custom-modal-title, .custom-modal-title * {
  color: #ffffff !important;
}

.custom-modal-close {
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.3);
  color: #ffffff !important;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 1.2rem;
}

.custom-modal-close:hover {
  background: rgba(255, 255, 255, 0.3);
  border-color: rgba(255, 255, 255, 0.5);
  color: #ffffff !important;
  transform: scale(1.1);
}

.custom-modal-close i {
  color: #ffffff !important;
}

.custom-modal-body {
  padding: 2rem;
  max-height: 60vh;
  overflow-y: auto;
  background: #f8fafc;
  position: relative;
  z-index: 100001;
}

.custom-modal-body input,
.custom-modal-body select,
.custom-modal-body textarea,
.custom-modal-body button {
  position: relative;
  z-index: 100002;
  pointer-events: auto;
}

.custom-modal-footer {
  background: #f1f5f9;
  padding: 1.5rem 2rem;
  border-top: 2px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}
</style>

<!-- Chart.js for enrollment charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2" defer></script>
<script>
let enrollmentChartInstance;

// Chart.js draws on a canvas, so no CSS cascade can reach it: the platform
// typeface has to be handed to Chart.js explicitly, otherwise axis ticks,
// legends and centre labels fall back to Chart.js' own default stack.
// Chart.js is loaded with `defer` above, so it is parsed after this inline
// block; DOMContentLoaded fires once the deferred bundle has run.
window.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') return;
  if (Chart.defaults.font) {
    Chart.defaults.font.family = "'Times New Roman', Times, 'Liberation Serif', 'DejaVu Serif', serif";
    Chart.defaults.font.weight = 400;
  } else if (Chart.defaults.global) {
    Chart.defaults.global.defaultFontFamily = "'Times New Roman', Times, 'Liberation Serif', 'DejaVu Serif', serif";
    Chart.defaults.global.defaultFontWeight = 'normal';
  }
});

// Enrollment data by grade level (Kinder – Grade 6)
const enrollmentByGrade = <?= json_encode($enrollmentChartValues ?? []) ?>;
const gradeChartLabels = <?= json_encode($enrollmentChartLabels ?? []) ?>;
const enrollCenterGrade = <?= json_encode(($enrollmentActiveGrades ?? 0) === 1 && !empty($enrollmentTopGrade) ? $enrollmentTopGrade['label'] : null) ?>;
const enrollCenterSY = 'SY <?= esc($schoolYear ?? get_current_school_year()) ?>';

// Must match the palette used for the PHP legend badges (nonzero grades, in order)
const ENROLL_PALETTE = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#a5b4fc', '#7c3aed', '#64748b'];

function initializeCharts() {
  const canvas = document.getElementById('enrollmentChart');

  // Include every grade level: slices with students get palette colors,
  // empty ones get a light gray so the doughnut always looks complete.
  const items = [];
  (enrollmentByGrade || []).forEach((value, index) => {
    items.push({
      label: gradeChartLabels[index] || `Grade ${index}`,
      value: value,
      active: value > 0
    });
  });
  const total = items.reduce((sum, item) => sum + item.value, 0);
  const activeItems = items.filter((item) => item.active);
  const singleGrade = activeItems.length === 1;

  const totalEl = document.getElementById('totalEnrollment');
  if (totalEl) totalEl.textContent = total;

  // Empty state is rendered server-side — nothing to draw
  if (!canvas || activeItems.length === 0) return;

  // Assign colors: active slices follow the legend palette, inactive ones gray
  let colorIndex = 0;
  items.forEach((item) => {
    item.color = item.active
      ? ENROLL_PALETTE[colorIndex++ % ENROLL_PALETTE.length]
      : '#e5eaf0';
  });

  // Center text: count, "Total Students", and the grade name (single-grade) or SY
  const centerLabel = singleGrade ? activeItems[0].label : enrollCenterSY;
  const centerText = {
    id: 'enrollCenterText',
    afterDraw(chart) {
      const { ctx, chartArea } = chart;
      const meta = chart.getDatasetMeta(0);
      if (!meta || !meta.data.length) return;
      const x = (chartArea.left + chartArea.right) / 2;
      const y = (chartArea.top + chartArea.bottom) / 2;
      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = '#0f172a';
      ctx.font = '700 20px Times New Roman, Times, serif';
      ctx.fillText(String(total), x, y - 10);
      ctx.fillStyle = '#94a3b8';
      ctx.font = '700 8.5px Times New Roman, Times, serif';
      ctx.fillText('TOTAL STUDENTS', x, y + 6);
      if (centerLabel) {
        ctx.fillStyle = singleGrade ? '#1d4ed8' : '#64748b';
        ctx.font = '700 9.5px Times New Roman, Times, serif';
        ctx.fillText(centerLabel, x, y + 19);
      }
      ctx.restore();
    }
  };

  const buildChart = () => {
    if (window.ChartDataLabels) {
      Chart.register(ChartDataLabels);
      Chart.defaults.set('plugins.datalabels', { display: false });
    }

    enrollmentChartInstance = new Chart(canvas, {
      type: 'doughnut',
      plugins: [centerText],
      data: {
        labels: items.map((item) => item.label),
        datasets: [{
          data: items.map((item) => item.value),
          backgroundColor: items.map((item) => item.color),
          borderColor: '#ffffff',
          borderWidth: 2,
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        // Bigger hole when data is sparse so a lone slice reads as a ring, not a blob
        cutout: singleGrade ? '72%' : '62%',
        animation: { animateRotate: true, animateScale: true, duration: 800, easing: 'easeOutQuart' },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const pct = total > 0 ? Math.round((ctx.raw / total) * 100) : 0;
                const state = ctx.raw === 0 ? ' — no students yet' : '';
                return ` ${ctx.label}: ${ctx.raw} student${ctx.raw === 1 ? '' : 's'} (${pct}%)${state}`;
              }
            }
          },
          datalabels: {
            // Percentage labels only on slices that actually have students
            display: (ctx) => {
              const value = ctx.dataset.data[ctx.dataIndex];
              return value > 0 && total > 0 && (value / total) >= 0.08;
            },
            formatter: (value) => total > 0 ? Math.round((value / total) * 100) + '%' : '',
            color: '#ffffff',
            textShadowBlur: 4,
            textShadowColor: 'rgba(15, 23, 42, 0.35)',
            font: { weight: '700', size: 10 }
          }
        }
      }
    });
  };

  if (document.readyState === 'complete' || window.Chart) {
    buildChart();
  } else {
    window.addEventListener('load', buildChart);
  }
}

// Initialize Grade Level Chart
function initializeGradeChart() {
  const gradeCtx = document.getElementById('gradeChart').getContext('2d');
  const colorPrimary = '#3b82f6';
  const colorHeading = '#0f172a';
  
  const gradient = gradeCtx.createLinearGradient(0, 0, 0, 300);
  gradient.addColorStop(0, 'rgba(59, 130, 246, 0.9)');
  gradient.addColorStop(1, 'rgba(59, 130, 246, 0.2)');

  new Chart(gradeCtx, {
    type: 'bar',
    data: {
      labels: gradeChartLabels,
      datasets: [{
        label: 'Enrolled Students',
        data: enrollmentByGrade,
        backgroundColor: gradient,
        borderColor: colorPrimary,
        borderWidth: 1,
        borderRadius: 8,
        maxBarThickness: 40,
        categoryPercentage: 0.6,
        barPercentage: 0.7,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: { stepSize: 1, color: colorHeading },
          grid: { color: 'rgba(15,23,42,0.06)' }
        },
        x: {
          ticks: { color: colorHeading },
          grid: { display: false }
        }
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${ctx.raw} students`
          }
        }
      }
    }
  });
}

// Update enrollment chart when year filter changes
function updateEnrollmentChart() {
  const selectedYear = document.getElementById('yearFilter').value;
  const url = new URL(window.location);
  url.searchParams.set('year', selectedYear);
  
  // Show loading state
  const btn = document.getElementById('yearFilter');
  const originalText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...';
  
  setTimeout(() => {
    window.location.href = url.toString();
  }, 300);
}

// Animated counters for the Quick stats summary cards
function animateSummaryCounters() {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('[data-counter]').forEach((el) => {
    const target = parseInt(el.dataset.counter, 10) || 0;
    if (reduceMotion || target === 0) {
      el.textContent = target;
      return;
    }
    const duration = 700;
    const start = performance.now();
    const step = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(target * eased);
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  });
}

// Initialize all charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  initializeCharts();
  initializeGradeChart();
  animateSummaryCounters();
  
  const yearFilter = document.getElementById('yearFilter');
  if (yearFilter) {
    yearFilter.value = '<?= $selectedYear ?>';
  }
});



function formatDateTime(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function toggleEnrollment() {
  const btn = document.getElementById('enrollmentToggleBtn');
  const originalContent = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Updating...';
  
  fetch('<?= base_url('admin/dashboard/toggleEnrollment') ?>', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast('success', 'Registration ' + (data.enabled ? 'opened' : 'closed') + ' successfully');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast('danger', 'Failed to toggle registration: ' + data.message);
      btn.disabled = false;
      btn.innerHTML = originalContent;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('danger', 'An error occurred while toggling registration.');
    btn.disabled = false;
    btn.innerHTML = originalContent;
  });
}

function toggleGrading() {
  const btn = document.getElementById('gradingToggleBtn');
  const originalContent = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Updating...';
  
  fetch('<?= base_url('admin/dashboard/toggleGrading') ?>', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast('success', 'Grading ' + (data.enabled ? 'enabled' : 'disabled') + ' successfully');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast('danger', 'Failed to toggle grading: ' + data.message);
      btn.disabled = false;
      btn.innerHTML = originalContent;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('danger', 'An error occurred while toggling grading.');
    btn.disabled = false;
    btn.innerHTML = originalContent;
  });
}

function showToast(type, message) {
  const toastId = 'toast-' + Date.now();
  const toastHtml = `
    <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true" style="z-index: 100002; min-width: 300px;">
      <div class="d-flex">
        <div class="toast-body">
          ${message}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  `;
  
  document.body.insertAdjacentHTML('beforeend', toastHtml);
  const toastElement = document.getElementById(toastId);
  const toast = new bootstrap.Toast(toastElement, { delay: 3000, autohide: true });
  toast.show();
  
  toastElement.addEventListener('hidden.bs.toast', () => {
    toastElement.remove();
  });
}

// Set the selected year in the filter on page load
document.addEventListener('DOMContentLoaded', function() {
  const yearFilter = document.getElementById('yearFilter');
  if (yearFilter) {
    yearFilter.value = '<?= $selectedYear ?>';
  }
});

</script>


<?= $this->endSection() ?>