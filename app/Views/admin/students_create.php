<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style><?= view('partials/password_requirements_style') ?>

/* Relationship selector + its "Other" companion box (same behaviour as the
   registration form): the box only appears for "Other". */
.religion-other { display: none; margin-top: .35rem; }
.religion-other.is-visible { display: block; }
.religion-other .form-label { margin-bottom: .15rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3">Add New Student</h1>
  <a href="<?= base_url('admin/students') ?>" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left"></i> Back to Students
  </a>
</div>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger">
    <?= session()->getFlashdata('error') ?>
  </div>
<?php endif; ?>

<?php if (isset($validation) && $validation->getErrors()): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($validation->getErrors() as $error): ?>
        <li><?= esc($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">Student Information</h5>
  </div>
  <div class="card-body">
    <form method="post" action="<?= base_url('admin/students/store') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      
      <!-- Account Information -->
      <div class="row mb-4">
        <div class="col-12">
          <h6 class="text-primary border-bottom pb-2 mb-3">Account Information</h6>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="lrn" class="form-label">LRN (Learner Reference Number)</label>
            <input type="text" class="form-control" id="lrn" name="lrn" 
                   value="<?= old('lrn') ?>" placeholder="Auto-generated if empty">
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="email" name="email" 
                   value="<?= old('email') ?>" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
            <div class="position-relative">
              <input type="password" class="form-control" id="password" name="password"
                     minlength="<?= password_policy_min_length() ?>" pattern="(?=.*\d).{<?= password_policy_min_length() ?>,}"
                     autocomplete="new-password" required data-password-indicator>
              <button type="button" class="btn position-absolute" id="togglePassword" 
                      style="right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; color: #6b7280; z-index: 10;">
                <i class="bi bi-eye" id="toggleIcon"></i>
              </button>
            </div>
            <div class="alert alert-info d-flex align-items-center gap-2 py-2 px-3 mt-2" role="note" style="font-size: .8rem;">
              <i class="bi bi-key-fill" aria-hidden="true"></i>
              <span><strong>Input your desired password.</strong> This is the password you will use to log in.</span>
            </div>
            <?= view('partials/password_requirements') ?>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
            <div class="position-relative">
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
              <button type="button" class="btn position-absolute" id="toggleConfirmPassword" 
                      style="right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; color: #6b7280; z-index: 10;">
                <i class="bi bi-eye" id="toggleConfirmIcon"></i>
              </button>
            </div>
            <div class="form-text">Re-enter the same password to confirm</div>
          </div>
        </div>
      </div>

      <!-- Personal Information -->
      <div class="row mb-4">
        <div class="col-12">
          <h6 class="text-primary border-bottom pb-2 mb-3">Personal Information</h6>
        </div>
        <div class="col-md-3">
          <div class="mb-3">
            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="first_name" name="first_name" maxlength="50" oninput="this.value = this.value.replace(/[^\p{L}\p{M}\s.'\-]/gu, '')"
                   value="<?= old('first_name') ?>" required>
          </div>
        </div>
        <div class="col-md-3">
          <div class="mb-3">
            <?php
            // Students without a middle name tick the box; the field is then
            // cleared and left unrequired. A supplied middle name must be at
            // least two characters.
            $noMiddleName = old(NO_MIDDLE_NAME_POST_KEY) === no_middle_name_rule();
            $middleNameValue = $noMiddleName ? '' : old('middle_name');
            ?>
            <label for="middle_name" class="form-label">Middle Name</label>
            <input type="text" class="form-control" id="middle_name" name="middle_name" maxlength="100" minlength="2" oninput="this.value = this.value.replace(/[^\p{L}\p{M}\s.'\-]/gu, '')"
                   value="<?= esc($middleNameValue) ?>" <?= $noMiddleName ? 'disabled' : 'required' ?>>
            <label class="form-check-label d-flex align-items-center gap-1 mt-1" for="no_middle_name" style="font-size: .75rem; cursor: pointer;">
              <input class="form-check-input mt-0" type="checkbox" name="<?= NO_MIDDLE_NAME_POST_KEY ?>" id="no_middle_name" value="<?= no_middle_name_rule() ?>" onchange="toggleNoMiddleName()" <?= $noMiddleName ? 'checked' : '' ?>>
              <span>No middle name</span>
            </label>
          </div>
        </div>
        <div class="col-md-3">
          <div class="mb-3">
            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="last_name" name="last_name" maxlength="50" oninput="this.value = this.value.replace(/[^\p{L}\p{M}\s.'\-]/gu, '')"
                   value="<?= old('last_name') ?>" required>
          </div>
        </div>
        <div class="col-md-3">
          <div class="mb-3">
            <label for="suffix" class="form-label">Suffix</label>
            <select class="form-select" id="suffix" name="suffix">
              <option value="">None</option>
              <option value="Jr." <?= old('suffix') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
              <option value="Sr." <?= old('suffix') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
              <option value="II" <?= old('suffix') === 'II' ? 'selected' : '' ?>>II</option>
              <option value="III" <?= old('suffix') === 'III' ? 'selected' : '' ?>>III</option>
              <option value="IV" <?= old('suffix') === 'IV' ? 'selected' : '' ?>>IV</option>
              <option value="V" <?= old('suffix') === 'V' ? 'selected' : '' ?>>V</option>
            </select>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
            <select class="form-select" id="gender" name="gender" required>
              <option value="">Select Gender</option>
              <option value="Male" <?= old('gender') === 'Male' ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= old('gender') === 'Female' ? 'selected' : '' ?>>Female</option>
            </select>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                   value="<?= old('date_of_birth') ?>" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="nationality" class="form-label">Nationality</label>
            <input type="text" class="form-control" id="nationality" name="nationality" 
                   value="<?= old('nationality', 'Filipino') ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="religion" class="form-label">Religion</label>
            <input type="text" class="form-control" id="religion" name="religion" 
                   value="<?= old('religion') ?>">
          </div>
        </div>
        <div class="col-md-12">
          <div class="mb-3">
            <label for="place_of_birth" class="form-label">Place of Birth</label>
            <input type="hidden" id="place_of_birth" name="place_of_birth"
                   value="<?= old('place_of_birth') ?>">
            <div data-loc-group="place_of_birth" data-loc-field="place_of_birth" data-loc-label="Place of Birth"></div>
          </div>
        </div>
      </div>

      <!-- Academic Information -->
      <div class="row mb-4">
        <div class="col-12">
          <h6 class="text-primary border-bottom pb-2 mb-3">Academic Information</h6>
        </div>
        <div class="col-md-4">
          <div class="mb-3">
            <label for="grade_level" class="form-label">Grade Level <span class="text-danger">*</span></label>
            <select class="form-select" id="grade_level" name="grade_level" required>
              <option value="">Select Grade Level</option>
              <?php foreach (grade_level_options() as $g): ?>
                <option value="<?= $g ?>" <?= old('grade_level') === (string) $g ? 'selected' : '' ?>><?= esc(grade_level_label($g)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-md-4">
          <div class="mb-3">
            <label for="student_type" class="form-label">Student Type <span class="text-danger">*</span></label>
            <select class="form-select" id="student_type" name="student_type" required>
              <option value="">Select Type</option>
              <option value="New Student" <?= old('student_type') === 'New Student' ? 'selected' : '' ?>>New Student</option>
              <option value="Transferee" <?= old('student_type') === 'Transferee' ? 'selected' : '' ?>>Transferee</option>
              <option value="Old Student" <?= old('student_type') === 'Old Student' ? 'selected' : '' ?>>Old Student</option>
            </select>
          </div>
        </div>
        <div class="col-md-4">
          <div class="mb-3">
            <label for="section_id" class="form-label">Section</label>
            <select class="form-select" id="section_id" name="section_id">
              <option value="">No Section Assigned</option>
              <?php foreach ($sections as $section): ?>
                <option value="<?= $section['id'] ?>" <?= old('section_id') == $section['id'] ? 'selected' : '' ?>>
                  <?= esc($section['section_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Contact Information -->
      <div class="row mb-4">
        <div class="col-12">
          <h6 class="text-primary border-bottom pb-2 mb-3">Contact Information</h6>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="contact_number" class="form-label">Contact Number</label>
            <input type="text" class="form-control" id="contact_number" name="contact_number" maxlength="11" inputmode="numeric" pattern="^09[0-9]{9}" autocomplete="tel-national" placeholder="09XXXXXXXXX"
                   value="<?= old('contact_number') ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="photo" class="form-label">2x2 Photo</label>
            <input type="file" class="form-control" id="photo" name="photo" accept=".jpg,.jpeg,.png">
            <div class="form-text">Upload student's 2x2 photo (JPG, PNG)</div>
          </div>
        </div>
        <div class="col-md-12">
          <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <input type="hidden" id="address" name="address" value="<?= old('address') ?>">
            <div data-loc-group="address" data-loc-field="address" data-loc-label="Address"></div>
          </div>
        </div>
      </div>

      <!-- Emergency Contact -->
      <div class="row mb-4">
        <div class="col-12">
          <h6 class="text-primary border-bottom pb-2 mb-3">Emergency Contact</h6>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="emergency_contact_name" class="form-label">Contact Name</label>
            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                   value="<?= old('emergency_contact_name') ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="emergency_contact_number" class="form-label">Contact Number</label>
            <input type="text" class="form-control" id="emergency_contact_number" name="emergency_contact_number" maxlength="11" inputmode="numeric" pattern="^09[0-9]{9}" autocomplete="tel-national" placeholder="09XXXXXXXXX"
                   value="<?= old('emergency_contact_number') ?>">
          </div>
        </div>
        <?php
        // A relationship outside the listed choices reveals a free-text box, so
        // "Other" is never stored as a literal value.
        $relOptions = emergency_contact_relationship_options();
        $relOld     = old('emergency_contact_relationship', '', false);
        $relValue   = is_string($relOld) ? trim($relOld) : '';
        $relIsOther = emergency_contact_relationship_is_other($relValue);
        ?>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="emergency_contact_relationship" class="form-label">Relationship</label>
            <select class="form-select" name="emergency_contact_relationship" id="emergency_contact_relationship" onchange="toggleRelationshipOther()">
              <option value="">Select Relationship</option>
              <?php foreach ($relOptions as $relOption): ?>
                <option value="<?= esc($relOption) ?>" <?= $relValue === $relOption ? 'selected' : '' ?>><?= esc($relOption) ?></option>
              <?php endforeach; ?>
              <option value="<?= esc(emergency_contact_relationship_other_option()) ?>" <?= $relIsOther ? 'selected' : '' ?>>Other (please specify)</option>
            </select>
            <div class="religion-other<?= $relIsOther ? ' is-visible' : '' ?>" id="relationshipOtherWrap">
              <label class="form-label" for="emergency_contact_relationship_other">Specify Relationship *</label>
              <input type="text" class="form-control" name="emergency_contact_relationship_other" id="emergency_contact_relationship_other"
                     value="<?= $relIsOther ? esc($relValue) : '' ?>"
                     data-field-label="Specify Relationship"
                     maxlength="50" placeholder="e.g. Godparent, Friend" autocomplete="off"
                     <?= $relIsOther ? 'required' : 'disabled' ?>>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="<?= base_url('admin/students') ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-circle"></i> Create Student
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// "No middle name": when ticked the field is cleared, disabled (so no stale
// value is ever submitted) and unrequired. When unticked it is required and
// must be at least two characters.
function toggleNoMiddleName() {
    const checkbox = document.getElementById('no_middle_name');
    const field = document.getElementById('middle_name');

    if (!checkbox || !field) {
        return;
    }

    const noMiddleName = checkbox.checked;

    if (noMiddleName) {
        field.value = '';
    }

    field.disabled = noMiddleName;
    field.required = !noMiddleName;
    field.style.opacity = noMiddleName ? '0.5' : '';
}

// The relationship selector only reveals its free-text box for "Other". While
// hidden that box is disabled (so no stale value is ever submitted) and drops
// `required`, matching the registration form.
function toggleRelationshipOther() {
    const select = document.getElementById('emergency_contact_relationship');
    const wrapper = document.getElementById('relationshipOtherWrap');
    const input = document.getElementById('emergency_contact_relationship_other');

    if (!select || !wrapper || !input) {
        return;
    }

    const isOther = select.value === <?= json_encode(emergency_contact_relationship_other_option()) ?>;

    wrapper.classList.toggle('is-visible', isOther);
    input.required = isOther;
    input.disabled = !isOther;
}

document.addEventListener('DOMContentLoaded', function() {
    // Restore the box state for a redisplayed form.
    toggleNoMiddleName();
    toggleRelationshipOther();

    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const toggleConfirmIcon = document.getElementById('toggleConfirmIcon');
    
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            if (type === 'text') {
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        });
    }

    if (toggleConfirmPassword) {
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);

            if (type === 'text') {
                toggleConfirmIcon.classList.remove('bi-eye');
                toggleConfirmIcon.classList.add('bi-eye-slash');
            } else {
                toggleConfirmIcon.classList.remove('bi-eye-slash');
                toggleConfirmIcon.classList.add('bi-eye');
            }
        });
    }
});
</script>

<?= $this->endSection() ?>
