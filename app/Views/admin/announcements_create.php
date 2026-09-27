<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style>
.blue-divider {
  height: 1px;
  background: var(--hairline-strong);

.form-floating textarea {
  min-height: 120px;
}

.preview-card {
  background: #f8f9fa;
  border: 1px dashed #dee2e6;
  border-radius: 8px;
}
</style>

<!-- Header Section -->
<div class="dashboard-header mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 fw-bold text-primary mb-1">Create Announcement</h1>
      <p class="text-muted mb-0 small">Create and publish announcements to your community</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= base_url('admin/announcements') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>Back to List
      </a>
    </div>
  </div>
  
  <!-- Blue Divider -->
  <div class="blue-divider"></div>
</div>

<?php if ($errors = session('errors')): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="row">
  <!-- Form Section -->
  <div class="col-lg-8">
    <div class="card bg-white border-0 shadow-sm rounded-3">
      <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">
          <i class="bi bi-plus-circle me-2"></i>New Announcement
        </h5>
      </div>
      <div class="card-body p-4">
        <form method="post" action="<?= base_url('admin/announcements/store') ?>" id="announcementForm">
          <?= csrf_field() ?>
          
          <div class="row g-3">
            <div class="col-12">
              <div class="form-floating">
                <input type="text" class="form-control" id="title" name="title" 
                       placeholder="Enter announcement title" required 
                       value="<?= old('title') ?>" maxlength="255">
                <label for="title">Announcement Title *</label>
              </div>
              <div class="form-text">
                <span id="titleCounter">0/255</span> characters
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="target_roles" name="target_roles" required>
                  <option value="">Select Target Audience</option>
                  <option value="all" <?= old('target_roles') === 'all' ? 'selected' : '' ?>>All Users</option>
                  <option value="admin" <?= old('target_roles') === 'admin' ? 'selected' : '' ?>>Administrators</option>
                  <option value="teacher" <?= old('target_roles') === 'teacher' ? 'selected' : '' ?>>All Teachers</option>
                  <option value="student" <?= old('target_roles') === 'student' ? 'selected' : '' ?>>All Students</option>
                  <option value="specific_grade" <?= old('target_roles') === 'specific_grade' ? 'selected' : '' ?>>Grade Level</option>
                  <option value="specific_section" <?= old('target_roles') === 'specific_section' ? 'selected' : '' ?>>Section</option>
                </select>
                <label for="target_roles">Target Audience *</label>
              </div>
            </div>
            
            <!-- Grade Level Selection (shown when specific_grade or specific_section is selected) -->
            <div class="col-md-6" id="gradeLevelContainer" style="display: none;">
              <div class="form-floating">
                <select class="form-select" id="grade_level" name="grade_level">
                  <option value="">Select Grade Level</option>
                  <?php foreach (grade_level_options() as $g): ?>
                    <option value="<?= $g ?>"><?= esc(grade_level_label($g)) ?></option>
                  <?php endforeach; ?>
                </select>
                <label for="grade_level">Grade Level</label>
              </div>
            </div>
            
            <!-- Section Selection (shown when specific_section is selected) -->
            <div class="col-md-6" id="sectionContainer" style="display: none;">
              <div class="form-floating">
                <select class="form-select" id="section_id" name="section_id">
                  <option value="">Select Section</option>
                </select>
                <label for="section_id">Section</label>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="form-floating">
                <select class="form-select" id="priority" name="priority">
                  <option value="normal">Normal</option>
                  <option value="high">High Priority</option>
                  <option value="urgent">Urgent</option>
                </select>
                <label for="priority">Priority Level</label>
              </div>
            </div>
            
            <div class="col-12">
              <div class="form-floating">
                <textarea class="form-control" id="body" name="body" 
                          placeholder="Enter announcement content" required 
                          style="min-height: 150px;"><?= old('body') ?></textarea>
                <label for="body">Announcement Content *</label>
              </div>
              <div class="form-text">
                Use clear and concise language. This will be visible to your selected audience.
              </div>
            </div>
          </div>
          
          <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-check-circle me-2"></i>Publish Announcement
            </button>
            <button type="button" class="btn btn-outline-info" onclick="showPreview()">
              <i class="bi bi-eye me-2"></i>Preview
            </button>
            <a href="<?= base_url('admin/announcements') ?>" class="btn btn-outline-secondary">
              <i class="bi bi-x me-2"></i>Cancel
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Preview Section -->
  <div class="col-lg-4">
    <div class="card bg-white border-0 shadow-sm rounded-3">
      <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0">
          <i class="bi bi-eye me-2"></i>Live Preview
        </h5>
      </div>
      <div class="card-body">
        <div id="previewContent" class="preview-card p-3">
          <div class="text-muted text-center">
            <i class="bi bi-eye-slash display-6"></i>
            <p class="mt-2 mb-0">Start typing to see preview</p>
          </div>
        </div>
        
        <div class="mt-3">
          <h6 class="fw-semibold">Tips for Great Announcements:</h6>
          <ul class="small text-muted">
            <li>Keep titles clear and descriptive</li>
            <li>Use simple, easy-to-understand language</li>
            <li>Include important dates and deadlines</li>
            <li>Choose the right target audience</li>
            <li>Proofread before publishing</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Character counter for title
document.getElementById('title').addEventListener('input', function() {
  const length = this.value.length;
  const counter = document.getElementById('titleCounter');
  counter.textContent = `${length}/255`;
  counter.className = length > 240 ? 'text-warning' : length > 250 ? 'text-danger' : 'text-muted';
  
  updatePreview();
});

// Update preview on content change
document.getElementById('body').addEventListener('input', updatePreview);
document.getElementById('target_roles').addEventListener('change', function() {
  updatePreview();
  handleTargetRoleChange();
});
document.getElementById('grade_level').addEventListener('change', function() {
  loadSections();
  updatePreview();
});
document.getElementById('section_id').addEventListener('change', updatePreview);

// Handle target role change to show/hide grade and section dropdowns
function handleTargetRoleChange() {
  const targetRole = document.getElementById('target_roles').value;
  const gradeLevelContainer = document.getElementById('gradeLevelContainer');
  const sectionContainer = document.getElementById('sectionContainer');
  const gradeLevel = document.getElementById('grade_level');
  const sectionId = document.getElementById('section_id');
  
  // Reset selections
  gradeLevel.value = '';
  sectionId.value = '';
  sectionId.innerHTML = '<option value="">Select Section</option>';
  
  // Show/hide containers based on selection
  if (targetRole === 'specific_grade') {
    gradeLevelContainer.style.display = 'block';
    sectionContainer.style.display = 'none';
    gradeLevel.required = true;
    sectionId.required = false;
  } else if (targetRole === 'specific_section') {
    gradeLevelContainer.style.display = 'block';
    sectionContainer.style.display = 'block';
    gradeLevel.required = true;
    sectionId.required = true;
  } else {
    gradeLevelContainer.style.display = 'none';
    sectionContainer.style.display = 'none';
    gradeLevel.required = false;
    sectionId.required = false;
  }
}

// Load sections based on selected grade level
function loadSections() {
  const gradeLevel = document.getElementById('grade_level').value;
  const sectionSelect = document.getElementById('section_id');
  
  if (!gradeLevel) {
    sectionSelect.innerHTML = '<option value="">Select Section</option>';
    return;
  }
  
  // Show loading state
  sectionSelect.innerHTML = '<option value="">Loading sections...</option>';
  
  // Fetch sections for the selected grade level
  fetch(`<?= base_url('admin/announcements/get-sections') ?>?grade_level=${gradeLevel}`)
    .then(response => response.json())
    .then(data => {
      if (data.success && data.sections) {
        let options = '<option value="">Select Section</option>';
        data.sections.forEach(section => {
          options += `<option value="${section.id}">${section.section_name} (${formatGradeLevel(section.grade_level)})</option>`;
        });
        sectionSelect.innerHTML = options;
      } else {
        sectionSelect.innerHTML = '<option value="">No sections available</option>';
      }
    })
    .catch(error => {
      console.error('Error loading sections:', error);
      sectionSelect.innerHTML = '<option value="">Error loading sections</option>';
    });
}

function updatePreview() {
  const title = document.getElementById('title').value;
  const body = document.getElementById('body').value;
  const target = document.getElementById('target_roles').value;
  const gradeLevel = document.getElementById('grade_level').value;
  const sectionId = document.getElementById('section_id').value;
  const sectionText = sectionId ? document.getElementById('section_id').options[document.getElementById('section_id').selectedIndex].text : '';
  const previewContent = document.getElementById('previewContent');
  
  if (!title && !body) {
    previewContent.innerHTML = `
      <div class="text-muted text-center">
        <i class="bi bi-eye-slash display-6"></i>
        <p class="mt-2 mb-0">Start typing to see preview</p>
      </div>
    `;
    return;
  }
  
  // Build target badge text
  let targetText = target;
  if (target === 'specific_grade' && gradeLevel) {
    targetText = `${formatGradeLevel(gradeLevel)}`;
  } else if (target === 'specific_section' && sectionText) {
    targetText = sectionText;
  }
  
  const targetBadge = targetText ? `<span class="badge bg-secondary mb-2">${targetText}</span>` : '';
  const titleHtml = title ? `<h5 class="fw-bold text-primary">${escapeHtml(title)}</h5>` : '';
  const bodyHtml = body ? `<p class="mb-0">${escapeHtml(body).replace(/\n/g, '<br>')}</p>` : '';
  
  previewContent.innerHTML = `
    ${targetBadge}
    ${titleHtml}
    ${bodyHtml}
    <small class="text-muted d-block mt-2">
      <i class="bi bi-calendar me-1"></i>
      ${new Date().toLocaleDateString()}
    </small>
  `;
}

function showPreview() {
  updatePreview();
  document.getElementById('previewContent').scrollIntoView({ behavior: 'smooth' });
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Form validation
document.getElementById('announcementForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const title = document.getElementById('title').value.trim();
  const body = document.getElementById('body').value.trim();
  const target = document.getElementById('target_roles').value;
  const gradeLevel = document.getElementById('grade_level').value;
  const sectionId = document.getElementById('section_id').value;
  
  if (!title || !body || !target) {
    await customAlert('Please fill in all required fields.', 'Validation Error');
    return false;
  }
  
  if (target === 'specific_grade' && !gradeLevel) {
    await customAlert('Please select a grade level.', 'Validation Error');
    return false;
  }
  
  if (target === 'specific_section' && (!gradeLevel || !sectionId)) {
    await customAlert('Please select both grade level and section.', 'Validation Error');
    return false;
  }
  
  if (title.length > 255) {
    await customAlert('Title must be 255 characters or less.', 'Validation Error');
    return false;
  }
  
  // Build confirmation message
  let targetText = target;
  if (target === 'specific_grade' && gradeLevel) {
    targetText = `${formatGradeLevel(gradeLevel)} students`;
  } else if (target === 'specific_section' && sectionId) {
    const sectionText = document.getElementById('section_id').options[document.getElementById('section_id').selectedIndex].text;
    targetText = sectionText;
  }
  
  // Confirm before publishing
  const confirmed = await customConfirm(`Are you sure you want to publish this announcement to ${targetText}?`, 'Confirm Publication');
  if (confirmed) {
    this.submit();
  }
});

// Initialize on page load
handleTargetRoleChange();

// Initialize preview
updatePreview();
</script>

<?= $this->endSection() ?>
