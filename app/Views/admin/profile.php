<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style>
<?= view('partials/password_requirements_style') ?>

.blue-divider {
  height: 1px;
  background: var(--hairline-strong);

.profile-card {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.profile-header {
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: white !important;
  padding: 2rem;
  text-align: center;
}

.profile-avatar {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
  font-size: 2.5rem;
  font-weight: bold;
}

.form-section {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.form-section h5 {
  color: #007bff;
  margin-bottom: 1rem;
  font-weight: 700;
}

/* Keep name/email white on the blue header — beats dashboard.css
   `.main-content .page-content h3` (!important + higher specificity). */
body.dashboard-app .main-content .page-content .profile-card .profile-header h3.profile-name {
  color: #ffffff !important;
  border-bottom: none !important;
  margin-bottom: 0.25rem !important;
  padding-bottom: 0 !important;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}
body.dashboard-app .main-content .page-content .profile-card .profile-header p.profile-email {
  color: #ffffff !important;
  opacity: 0.92;
}

/* Header chips (matching teacher profile) */
.profile-chips {
  display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem;
  margin-top: 1rem;
}
.profile-chip {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: #ffffff;
  border-radius: 999px;
  font-size: 0.8125rem;
  padding: 0.25rem 0.75rem;
  display: inline-flex; align-items: center; gap: 0.35rem;
}
body.dashboard-app .main-content .page-content .profile-card .profile-header .profile-chip {
  color: #ffffff !important;
}
</style>

<div class="dashboard-header mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 fw-bold text-primary mb-1">My Profile</h1>
      <p class="text-muted mb-0 small">Manage your personal information and account settings</p>
    </div>
  </div>
  <div class="blue-divider"></div>
</div>

<?php if ($success = session('success')): ?>
  <div class="alert alert-success"><?= esc($success) ?></div>
<?php endif; ?>
<?php if ($error = session('error')): ?>
  <div class="alert alert-danger"><?= esc($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-8">
    <div class="profile-card">
      <div class="profile-header">
        <div class="profile-avatar">
          <?= strtoupper(substr($admin['first_name'] ?? 'A', 0, 1) . substr($admin['last_name'] ?? 'D', 0, 1)) ?>
        </div>
        <h3 class="mb-1 profile-name" style="color: white !important;"><?= esc($admin['first_name'] . ' ' . $admin['last_name']) ?></h3>
        <p class="mb-0 profile-email" style="color: white; opacity: 0.9;"><?= esc($admin['email']) ?></p>
        <div class="profile-chips">
          <span class="profile-chip"><i class="bi bi-shield-lock"></i>Administrator</span>
          <span class="profile-chip"><i class="bi bi-person-circle"></i>User ID: <?= esc($admin['id']) ?></span>
          <span class="profile-chip"><i class="bi bi-check-circle"></i>Active</span>
        </div>
      </div>
      
      <div class="card-body p-4">
        <form action="<?= base_url('admin/profile/update') ?>" method="post">
          <?= csrf_field() ?>
          
          <div class="form-section">
            <h5><i class="bi bi-person me-2"></i>Personal Information</h5>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" class="form-control" id="first_name" name="first_name" 
                       value="<?= esc($admin['first_name']) ?>" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" class="form-control" id="last_name" name="last_name" 
                       value="<?= esc($admin['last_name']) ?>" required>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h5><i class="bi bi-envelope me-2"></i>Contact Information</h5>
            <div class="mb-3">
              <label for="email" class="form-label">Email Address</label>
              <input type="email" class="form-control" id="email" name="email" 
                     value="<?= esc($admin['email']) ?>" required>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check-circle me-2"></i>Update Profile
            </button>
            <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="profile-card">
      <div class="card-header bg-warning text-dark">
        <h5 class="card-title mb-0">
          <i class="bi bi-shield-lock me-2"></i>Change Password
        </h5>
      </div>
      <div class="card-body">
        <form id="changePasswordForm" action="<?= base_url('admin/profile/change-password') ?>" method="post">
          <?= csrf_field() ?>
          
          <div class="mb-3">
            <label for="current_password" class="form-label">Current Password</label>
            <div class="input-group">
              <input type="password" class="form-control" id="current_password" name="current_password" required>
              <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                <i class="bi bi-eye" id="current_password_icon"></i>
              </button>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <div class="input-group">
              <input type="password" class="form-control" id="new_password" name="new_password"
                     minlength="<?= password_policy_min_length() ?>" pattern="(?=.*\d).{<?= password_policy_min_length() ?>,}"
                     autocomplete="new-password" required data-password-indicator>
              <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                <i class="bi bi-eye" id="new_password_icon"></i>
              </button>
            </div>
            <?= view('partials/password_requirements') ?>
          </div>
          
          <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <div class="input-group">
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
              <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                <i class="bi bi-eye" id="confirm_password_icon"></i>
              </button>
            </div>
          </div>
          
          <button type="button" class="btn btn-warning w-100" onclick="confirmPasswordChange()">
            <i class="bi bi-key me-2"></i>Change Password
          </button>
        </form>
      </div>
    </div>

    <div class="profile-card mt-3">
      <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0">
          <i class="bi bi-shield-check me-2"></i>Account Info
        </h5>
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted">Role</span>
          <span class="badge bg-primary">Administrator</span>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <span class="text-muted">Status</span>
          <span class="badge bg-success">Active</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmPasswordModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Password Change</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to change your password?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal" style="background-color: #495057; color: white;">Cancel</button>
        <button type="button" class="btn btn-warning" onclick="submitPasswordChange()">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script>
function confirmPasswordChange() {
  const form = document.getElementById('changePasswordForm');
  if (form.checkValidity()) {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    if (newPass !== confirmPass) {
      alert('New password and confirm password do not match!');
      return;
    }
    const modalEl = document.getElementById('confirmPasswordModal');
    const portal = document.getElementById('dashboard-modal-portal') || document.body;
    if (modalEl && modalEl.parentElement !== portal) {
      portal.appendChild(modalEl);
    }
    const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true, focus: true });
    modal.show();
  } else {
    form.reportValidity();
  }
}

function submitPasswordChange() {
  document.getElementById('changePasswordForm').submit();
}

function togglePassword(fieldId) {
    const passwordField = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        passwordField.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>

<?= $this->endSection() ?>
