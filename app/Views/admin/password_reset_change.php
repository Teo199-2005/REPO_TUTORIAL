<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style><?= view('partials/password_requirements_style') ?></style>

<div class="dashboard-header mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 fw-bold text-primary mb-1">Change Password</h1>
      <p class="text-muted mb-0 small">Set new password for user: <?= esc(str_replace('mailto:', '', $reset['email'])) ?></p>
    </div>
    <a href="<?= base_url('admin/password-resets') ?>" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back to Requests
    </a>
  </div>
  <div class="blue-divider"></div>
</div>

<div class="row justify-content-center">
  <div class="col-md-6">
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
      <div class="card-body p-4">
        <div class="text-center mb-4">
          <i class="bi bi-key-fill dash-icon-inline fs-1 mb-3"></i>
          <h5 class="fw-bold">Set New Password</h5>
          <p class="text-muted small">Enter a new password for this user</p>
        </div>

        <form id="changePasswordForm" method="post" action="<?= base_url('admin/password-resets/change-password') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="reset_id" value="<?= $reset['id'] ?>">
          
          <div class="mb-3">
            <label for="newPassword" class="form-label fw-medium">New Password</label>
            <div class="input-group">
              <input type="password" class="form-control" id="newPassword" name="new_password"
                     minlength="<?= password_policy_min_length() ?>" pattern="(?=.*\d).{<?= password_policy_min_length() ?>,}"
                     autocomplete="new-password" required data-password-indicator>
              <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <?= view('partials/password_requirements') ?>
          </div>
          
          <div class="mb-4">
            <label for="confirmPassword" class="form-label fw-medium">Confirm New Password</label>
            <div class="input-group">
              <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
              <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-key me-1"></i>Change Password
            </button>
            <a href="<?= base_url('admin/password-resets') ?>" class="btn btn-outline-secondary">
              Cancel
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// Show/hide password functionality
document.getElementById('toggleNewPassword').addEventListener('click', function() {
  const passwordInput = document.getElementById('newPassword');
  const icon = this.querySelector('i');
  
  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    passwordInput.type = 'password';
    icon.className = 'bi bi-eye';
  }
});

document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
  const passwordInput = document.getElementById('confirmPassword');
  const icon = this.querySelector('i');
  
  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    passwordInput.type = 'password';
    icon.className = 'bi bi-eye';
  }
});

// Form validation
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const newPassword = document.getElementById('newPassword').value;
  const confirmPassword = document.getElementById('confirmPassword').value;
  
  if (newPassword !== confirmPassword) {
    alert('Passwords do not match');
    return;
  }
  
  if (newPassword.length < 6) {
    alert('Password must be at least 6 characters long');
    return;
  }
  
  // Submit the form
  this.submit();
});
</script>

<?= $this->endSection() ?>