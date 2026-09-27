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

.profile-header h3,
.profile-header p {
  color: #ffffff !important;
}

/* Beat dashboard.css `.main-content .page-content h3` (!important + higher
   specificity) which turns the name dark navy on the blue header. */
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

/* Compact profile layout — reduce top/bottom spacing on fields */
.profile-card .p-4 {
  padding: 1rem !important;
}
.profile-header {
  padding: 1.2rem 1rem !important;
}
.profile-avatar {
  width: 72px !important;
  height: 72px !important;
  font-size: 1.8rem !important;
  margin-bottom: 0.6rem !important;
}
body.dashboard-app .main-content .page-content .profile-card .profile-header h3.profile-name {
  font-size: 1.1rem !important;
}
body.dashboard-app .main-content .page-content .profile-card .profile-header p.profile-email {
  font-size: 0.8rem !important;
}
.form-section {
  padding: 0.85rem 0.9rem !important;
  margin-bottom: 0.85rem !important;
}
.form-section .compact-h {
  font-size: 0.92rem !important;
  margin-bottom: 0.55rem !important;
  font-weight: 700;
  color: #007bff;
}
.form-section .compact-h i {
  font-size: 0.9rem !important;
}
.form-section .compact-label {
  font-size: 0.76rem !important;
  margin-bottom: 0.2rem !important;
}
.form-section .compact-input {
  padding: 0.35rem 0.6rem !important;
  font-size: 0.83rem !important;
  border-radius: 0.45rem !important;
  min-height: 0 !important;
  line-height: 1.35 !important;
}
.form-section .compact-hint {
  font-size: 0.7rem !important;
  margin-top: 0.2rem !important;
  line-height: 1.45 !important;
}
.form-section .row.g-2 {
  --bs-gutter-y: 0.5rem !important;
  --bs-gutter-x: 0.5rem !important;
}
.form-section img {
  max-height: 90px;
}

/* Header chips (matching teacher/admin profile) */
.profile-chips {
  display: flex; flex-wrap: wrap; justify-content: center; gap: 0.4rem;
  margin-top: 0.7rem;
}
.profile-chip {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: #ffffff;
  border-radius: 999px;
  font-size: 0.7rem;
  padding: 0.2rem 0.65rem;
  display: inline-flex; align-items: center; gap: 0.3rem;
}
body.dashboard-app .main-content .page-content .profile-card .profile-header .profile-chip {
  color: #ffffff !important;
}
</style>

<!-- Header Section -->
<div class="dashboard-header mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 fw-bold text-primary mb-1">My Profile</h1>
      <p class="text-muted mb-0 small">Manage your personal information and account settings</p>
    </div>
  </div>
  
  <!-- Blue Divider -->
  <div class="blue-divider"></div>
</div>

<?php if ($success = session('success')): ?>
  <div class="alert alert-success"><?= esc($success) ?></div>
<?php endif; ?>
<?php if ($error = session('error')): ?>
  <div class="alert alert-danger"><?= esc($error) ?></div>
<?php endif; ?>
<?php if ($errors = session('errors')): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?>
        <li><?= esc($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php
helper('nutrition');
$nutritionIncomplete = ! \App\Models\StudentModel::isNutritionProfileComplete($student);
$profileReqs = student_profile_requirements($student);
$profileComplete = student_profile_complete($student);
?>
<?php if ($info = session('info')): ?>
  <div class="alert alert-info border-0 shadow-sm d-flex align-items-start gap-3 mb-4" role="alert">
    <i class="bi bi-info-circle-fill fs-4 flex-shrink-0"></i>
    <div><?= esc($info) ?></div>
  </div>
<?php endif; ?>
<?= view('partials/profile_unlock_card', ['student' => $student, 'context' => 'profile']) ?>

<div class="row">
  <!-- Profile Information -->
  <div class="col-lg-8">
    <div class="profile-card">
      <div class="profile-header">
        <div class="profile-avatar">
          <?php if (!empty($student['photo_path'])): ?>
            <img src="<?= base_url('files/' . $student['photo_path']) ?>" alt="Profile photo"
                 style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
          <?php else: ?>
            <?= strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)) ?>
          <?php endif; ?>
        </div>
        <h3 class="mb-1 profile-name"><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></h3>
        <p class="mb-0 profile-email"><?= esc($student['email']) ?></p>
        <div class="profile-chips">
          <span class="profile-chip"><i class="bi bi-person-vcard"></i>LRN: <?= esc($student['lrn'] ?? '—') ?></span>
          <span class="profile-chip"><i class="bi bi-mortarboard"></i><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0))) ?></span>
          <?php if (!empty($student['section_name'])): ?>
            <span class="profile-chip"><i class="bi bi-people"></i><?= esc($student['section_name']) ?></span>
          <?php endif; ?>
          <span class="profile-chip"><i class="bi bi-check-circle"></i><?= esc(ucfirst($student['enrollment_status'] ?? '')) ?></span>
        </div>
      </div>
      
      <div class="card-body p-4">
        <form action="<?= base_url('student/profile/update') ?>" method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          
          <div class="form-section py-2">
            <h5 class="compact-h"><i class="bi bi-person-bounding-box me-2"></i>Profile Picture</h5>
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <div id="profile-photo-thumb" class="spc-thumb spc-thumb-circle" style="background-image:url('<?= !empty($student['photo_path']) ? base_url('files/' . $student['photo_path']) : '' ?>');">
                <?php if (empty($student['photo_path'])): ?><i class="bi bi-person"></i><?php endif; ?>
              </div>
              <div class="flex-grow-1" style="min-width:220px;">
                <label for="photo" class="form-label compact-label mb-1">Upload your profile picture</label>
                <input type="file" class="form-control compact-input" id="photo" name="photo" accept="image/png,image/jpeg,image/webp">
                <div class="form-text compact-hint">JPG, PNG or WebP, up to 2MB. Drag and zoom to crop - the circle shows exactly how it will look on your profile.<?= $profileReqs['photo'] ? '' : ' <span class="text-danger fw-bold">Required to unlock your portal.</span>' ?></div>
              </div>
            </div>
          </div>

          <div class="form-section py-2">
            <h5 class="compact-h"><i class="bi bi-person-badge me-2"></i>2x2 ID Picture</h5>
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <div id="id-photo-thumb" class="spc-thumb spc-thumb-square" style="background-image:url('<?= !empty($student['id_photo_path']) ? base_url('files/' . $student['id_photo_path']) : '' ?>');">
                <?php if (empty($student['id_photo_path'])): ?><i class="bi bi-person"></i><?php endif; ?>
              </div>
              <div class="flex-grow-1" style="min-width:220px;">
                <label for="id_photo" class="form-label compact-label mb-1">Upload your 2x2 ID picture</label>
                <input type="file" class="form-control compact-input" id="id_photo" name="id_photo" accept="image/png,image/jpeg,image/webp">
                <div class="form-text compact-hint">2x2 square photo for your digital <a href="<?= base_url('student/id-cards') ?>">ID Card</a>. Drag and zoom to crop - the square preview shows the final result.<?= $profileReqs['id_photo'] ? '' : ' <span class="text-danger fw-bold">Required to unlock your portal.</span>' ?></div>
              </div>
            </div>
          </div>

          <div class="form-section py-2">
            <h5 class="compact-h"><i class="bi bi-person me-2"></i>Personal Information</h5>
            <div class="row g-2">
              <div class="col-md-4">
                <label for="first_name" class="form-label compact-label">First Name</label>
                <input type="text" class="form-control compact-input" id="first_name" name="first_name" 
                       value="<?= esc($student['first_name']) ?>" required>
              </div>
              <div class="col-md-4">
                <label for="middle_name" class="form-label compact-label">Middle Name</label>
                <input type="text" class="form-control compact-input" id="middle_name" name="middle_name" minlength="2" 
                       value="<?= esc($student['middle_name'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label for="last_name" class="form-label compact-label">Last Name</label>
                <input type="text" class="form-control compact-input" id="last_name" name="last_name" 
                       value="<?= esc($student['last_name']) ?>" required>
              </div>
            </div>
          </div>

          <div class="form-section py-2">
            <h5 class="compact-h"><i class="bi bi-envelope me-2"></i>Contact Information</h5>
            <div class="row g-2">
              <div class="col-md-6">
                <label for="email" class="form-label compact-label">Email Address</label>
                <input type="email" class="form-control compact-input" id="email" name="email" 
                       value="<?= esc($student['email']) ?>" required>
              </div>
              <div class="col-md-6">
                <label for="phone" class="form-label compact-label">Phone Number</label>
                <input type="text" class="form-control compact-input" id="phone" name="phone" maxlength="11" inputmode="numeric" pattern="^09[0-9]{9}" autocomplete="tel-national" placeholder="09XXXXXXXXX"
                       value="<?= esc(old('phone', $student['contact_number'] ?? $student['phone'] ?? '')) ?>">
              </div>
            </div>
            <div class="mt-2">
              <label for="address" class="form-label compact-label">Address</label>
              <input type="hidden" id="address" name="address" maxlength="500" value="<?= esc($student['address'] ?? '') ?>">
              <div data-loc-group="address" data-loc-field="address" data-loc-label="Address"></div>
            </div>
          </div>

          <div class="form-section py-2">
            <h5 class="compact-h"><i class="bi bi-heart-pulse me-2"></i>Health / nutrition (required)</h5>
            <p class="text-muted compact-hint mb-2">Used for school wellness records. BMI is estimated from your height and weight using WHO growth references for your age and sex where applicable.</p>
            <div class="row g-2">
              <div class="col-md-4">
                <label for="height_cm" class="form-label compact-label">Height (cm) <?= $nutritionIncomplete ? '<span class="text-danger">*</span>' : '' ?></label>
                <input type="number" step="0.1" min="80" max="250" class="form-control compact-input" id="height_cm" name="height_cm"
                       value="<?= esc(old('height_cm', $student['height_cm'] ?? '')) ?>" placeholder="e.g. 165">
              </div>
              <div class="col-md-4">
                <label for="weight_kg" class="form-label compact-label">Weight (kg) <?= $nutritionIncomplete ? '<span class="text-danger">*</span>' : '' ?></label>
                <input type="number" step="0.1" min="15" max="200" class="form-control compact-input" id="weight_kg" name="weight_kg"
                       value="<?= esc(old('weight_kg', $student['weight_kg'] ?? '')) ?>" placeholder="e.g. 52">
              </div>
              <div class="col-md-4">
                <label for="ethnicity" class="form-label compact-label">Ethnicity <?= $nutritionIncomplete ? '<span class="text-danger">*</span>' : '' ?></label>
                <select class="form-select compact-input" id="ethnicity" name="ethnicity">
                  <option value="">— Select —</option>
                  <?php
                  $ethVal = old('ethnicity', $student['ethnicity'] ?? '');
                  foreach (student_ethnicity_options() as $val => $label): ?>
                    <option value="<?= esc($val) ?>" <?= (string) $ethVal === (string) $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <span class="text-muted compact-hint">Age (from school record):</span>
                <?php
                $ageY = '';
                if (! empty($student['date_of_birth'])) {
                    try {
                        $dob = new \DateTimeImmutable($student['date_of_birth']);
                        $ageY = (string) $dob->diff(new \DateTimeImmutable('today'))->y;
                    } catch (\Throwable) {
                        $ageY = '—';
                    }
                } else {
                    $ageY = '—';
                }
                ?>
                <strong><?= esc($ageY !== '' ? $ageY . ' years' : '—') ?></strong>
              </div>
              <div class="col-md-6">
                <span class="text-muted compact-hint">Screening category:</span>
                <?php if (! empty($student['nutrition_status'])): ?>
                  <span class="badge bg-secondary"><?= esc(\App\Libraries\StudentNutritionClassifier::statusLabel($student['nutrition_status'])) ?></span>
                  <?php if (! empty($student['bmi'])): ?>
                    <span class="small text-muted ms-1">BMI <?= esc((string) $student['bmi']) ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">— complete all three fields above</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check-circle me-2"></i>Update Profile
            </button>
            <a href="<?= base_url('student/dashboard') ?>" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Change Password -->
  <div class="col-lg-4">
    <div class="profile-card">
      <div class="card-header bg-warning text-dark">
        <h5 class="card-title mb-0">
          <i class="bi bi-shield-lock me-2"></i>Change Password
        </h5>
      </div>
      <div class="card-body">
        <form id="changePasswordForm" action="<?= base_url('student/profile/change-password') ?>" method="post">
          <?= csrf_field() ?>
          
          <div class="mb-3">
            <label for="current_password" class="form-label">Current Password</label>
            <div style="position: relative;">
              <input type="password" class="form-control" id="current_password" name="current_password" required>
              <button type="button" id="toggleCurrentPassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer; color: #6c757d;">
                <i class="bi bi-eye" id="currentEyeIcon"></i>
              </button>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <div style="position: relative;">
              <input type="password" class="form-control" id="new_password" name="new_password" 
                     minlength="<?= password_policy_min_length() ?>" pattern="(?=.*\d).{<?= password_policy_min_length() ?>,}"
                     autocomplete="new-password" required data-password-indicator>
              <button type="button" id="toggleNewPassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer; color: #6c757d;">
                <i class="bi bi-eye" id="newEyeIcon"></i>
              </button>
            </div>
            <?= view('partials/password_requirements') ?>
          </div>
          
          <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <div style="position: relative;">
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
              <button type="button" id="toggleConfirmPassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer; color: #6c757d;">
                <i class="bi bi-eye" id="confirmEyeIcon"></i>
              </button>
            </div>
          </div>
          
          <button type="button" class="btn btn-warning w-100" onclick="confirmPasswordChange()">
            <i class="bi bi-key me-2"></i>Change Password
          </button>
        </form>
      </div>
    </div>

    <!-- Profile Stats -->
    <div class="profile-card mt-3">
      <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0">
          <i class="bi bi-graph-up me-2"></i>Student Info
        </h5>
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted">LRN</span>
          <span class="fw-bold"><?= esc($student['lrn']) ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted">Grade Level</span>
          <span class="fw-bold"><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0))) ?></span>
        </div>
        <?php if (!empty($student['section_name'])): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted">Section</span>
          <span class="fw-bold"><?= esc($student['section_name']) ?></span>
        </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center">
          <span class="text-muted">Status</span>
          <span class="badge bg-primary"><?= ucfirst(esc($student['enrollment_status'])) ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmPasswordModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Confirm Password Change</h5>
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

<style>
#confirmPasswordModal {
  z-index: 99999 !important;
}
#confirmPasswordModal ~ .modal-backdrop {
  z-index: 99998 !important;
}
</style>

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
    document.body.appendChild(modalEl);
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  } else {
    form.reportValidity();
  }
}

function submitPasswordChange() {
  document.getElementById('changePasswordForm').submit();
}

// Password toggle functionality
document.getElementById('toggleCurrentPassword').addEventListener('click', function() {
  const passwordInput = document.getElementById('current_password');
  const eyeIcon = document.getElementById('currentEyeIcon');
  
  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    eyeIcon.className = 'bi bi-eye-slash';
  } else {
    passwordInput.type = 'password';
    eyeIcon.className = 'bi bi-eye';
  }
});

document.getElementById('toggleNewPassword').addEventListener('click', function() {
  const passwordInput = document.getElementById('new_password');
  const eyeIcon = document.getElementById('newEyeIcon');
  
  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    eyeIcon.className = 'bi bi-eye-slash';
  } else {
    passwordInput.type = 'password';
    eyeIcon.className = 'bi bi-eye';
  }
});

document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
  const passwordInput = document.getElementById('confirm_password');
  const eyeIcon = document.getElementById('confirmEyeIcon');
  
  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    eyeIcon.className = 'bi bi-eye-slash';
  } else {
    passwordInput.type = 'password';
    eyeIcon.className = 'bi bi-eye';
  }
});
</script>


<style>
  /* Student photo crop previews (profile = circle, ID = 2x2 square) */
  .spc-thumb {
    width: 72px;
    height: 72px;
    border: var(--hairline);
    background-color: #eef4ff;
    background-size: cover;
    background-position: center;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #0d6efd;
    flex-shrink: 0;
  }
  .spc-thumb-circle { border-radius: 50%; }
  .spc-thumb-square { border-radius: 0; }

  /* Crop modal: one big full-width stage (the old side preview box — the
     little blue shape — is gone; the result shows in the page thumb). */
  .spc-modal__subtitle {
    font-size: 0.78rem;
    font-weight: 400;
    color: #6c757d;
    margin-top: 0.15rem;
  }
  .spc-stage-wrap {
    position: relative;
    display: block;
    width: 100%;
    min-height: 320px;
    height: min(58vh, 460px);
    padding: 14px;
    border: 1px dashed #ced4da;
    border-radius: 12px;
    background-color: #f8f9fa;
    background-image:
      linear-gradient(45deg, #eceff2 25%, transparent 25%),
      linear-gradient(-45deg, #eceff2 25%, transparent 25%),
      linear-gradient(45deg, transparent 75%, #eceff2 75%),
      linear-gradient(-45deg, transparent 75%, #eceff2 75%);
    background-size: 18px 18px;
    background-position: 0 0, 0 9px, 9px -9px, -9px 0;
    overflow: hidden;
  }
  .spc-stage-wrap > img { max-width: 100%; }
  .spc-hints {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem 0.6rem;
    margin-top: 0.75rem;
  }
  .spc-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    color: #495057;
    background: #f1f3f5;
    border: 1px solid #e4e7eb;
    border-radius: 999px;
    padding: 0.24rem 0.65rem;
  }
  .spc-hint i { color: #0d6efd; font-size: 0.82rem; }
  @media (max-width: 575.98px) {
    .spc-stage-wrap { height: min(50vh, 340px); min-height: 230px; padding: 10px; }
  }
</style>

<!-- Crop modal: profile picture (circle) -->
<div class="modal fade" id="profileCropModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title"><i class="bi bi-scissors me-2"></i>Crop your profile picture</h5>
          <div class="spc-modal__subtitle">Final result: circular avatar shown on your profile.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="spc-stage-wrap"><img id="profileCropStage" alt="Profile photo crop stage"></div>
        <div class="spc-hints">
          <span class="spc-hint"><i class="bi bi-arrows-move" aria-hidden="true"></i>Drag the image to reposition</span>
          <span class="spc-hint"><i class="bi bi-zoom-in" aria-hidden="true"></i>Scroll or pinch to zoom</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="profileCropCancelBtn">Choose another</button>
        <button type="button" class="btn btn-primary" id="profileCropApplyBtn"><i class="bi bi-check-lg me-1"></i>Apply crop</button>
      </div>
    </div>
  </div>
</div>

<!-- Crop modal: 2x2 ID picture (square) -->
<div class="modal fade" id="idCropModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title"><i class="bi bi-scissors me-2"></i>Crop your 2x2 ID picture</h5>
          <div class="spc-modal__subtitle">Final result: square photo printed on your 2x2 ID card.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="spc-stage-wrap"><img id="idCropStage" alt="ID photo crop stage"></div>
        <div class="spc-hints">
          <span class="spc-hint"><i class="bi bi-arrows-move" aria-hidden="true"></i>Drag the image to reposition</span>
          <span class="spc-hint"><i class="bi bi-zoom-in" aria-hidden="true"></i>Scroll or pinch to zoom</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="idCropCancelBtn">Choose another</button>
        <button type="button" class="btn btn-primary" id="idCropApplyBtn"><i class="bi bi-check-lg me-1"></i>Apply crop</button>
      </div>
    </div>
  </div>
</div>

<script>
// Wire both file inputs to the crop/drag modal with live shaped previews.
document.addEventListener('DOMContentLoaded', function () {
  if (!window.StudentPhotoCrop) { return; }
  StudentPhotoCrop.attach({
    input: document.getElementById('photo'),
    pagePreview: document.getElementById('profile-photo-thumb'),
    shape: 'circle',
    modal: document.getElementById('profileCropModal'),
    stage: document.getElementById('profileCropStage'),
    applyBtn: document.getElementById('profileCropApplyBtn'),
    cancelBtn: document.getElementById('profileCropCancelBtn')
  });
  StudentPhotoCrop.attach({
    input: document.getElementById('id_photo'),
    pagePreview: document.getElementById('id-photo-thumb'),
    shape: 'square',
    modal: document.getElementById('idCropModal'),
    stage: document.getElementById('idCropStage'),
    applyBtn: document.getElementById('idCropApplyBtn'),
    cancelBtn: document.getElementById('idCropCancelBtn')
  });
});
</script><?= $this->endSection() ?>