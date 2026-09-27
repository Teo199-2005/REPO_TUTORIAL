<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3">My Sections</h1>
  <a href="<?= base_url('teacher/dashboard') ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
</div>

<?php if (!empty($sections)): ?>
  <!-- Summary Statistics -->
  <div class="card mb-4">
    <div class="card-body">
      <div class="row text-center">
        <div class="col-4">
          <div class="d-flex align-items-center justify-content-center">
            <div class="text-primary me-3">
              <i class="bi bi-building fs-2"></i>
            </div>
            <div>
              <h4 class="mb-0"><?= count($sections) ?></h4>
              <small class="text-muted">Assigned Sections</small>
            </div>
          </div>
        </div>
        <div class="col-4">
          <div class="d-flex align-items-center justify-content-center">
            <div class="text-success me-3">
              <i class="bi bi-people fs-2"></i>
            </div>
            <div>
              <h4 class="mb-0"><?= array_sum(array_column($sections, 'current_enrollment')) ?></h4>
              <small class="text-muted">Total Students</small>
            </div>
          </div>
        </div>
        <div class="col-4">
          <div class="d-flex align-items-center justify-content-center">
            <div class="text-info me-3">
              <i class="bi bi-mortarboard fs-2"></i>
            </div>
            <div>
              <h4 class="mb-0"><?= array_sum(array_column($sections, 'max_capacity')) ?></h4>
              <small class="text-muted">Total Capacity</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sections Table -->
  <?php
  // Group sections by grade level
  $sectionsByGrade = [];
  foreach ($sections as $section) {
    $sectionsByGrade[$section['grade_level']][] = $section;
  }
  ksort($sectionsByGrade);
  ?>

  <?php foreach ($sectionsByGrade as $gradeLevel => $gradeSections): ?>
    <div class="card mb-4">
      <div class="card-header">
        <h6 class="card-title mb-0 fw-semibold">
          <i class="bi bi-mortarboard"></i> <?= esc(grade_level_label((int) $gradeLevel)) ?> Sections
          <span class="badge bg-secondary ms-2"><?= count($gradeSections) ?> section<?= count($gradeSections) > 1 ? 's' : '' ?></span>
        </h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-hover mb-0">
            <thead>
              <tr>
                <th>Section Name</th>
                <th>Enrollment</th>
                <th>Capacity</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($gradeSections as $section): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= esc($section['section_name']) ?></div>
                    <small class="text-muted">School Year: <?= esc($section['school_year']) ?></small>
                  </td>
                  <td>
                    <div class="d-flex align-items-center">
                      <span class="me-2"><?= $section['current_enrollment'] ?>/<?= $section['max_capacity'] ?></span>
                      <?php if ($section['current_enrollment'] >= $section['max_capacity']): ?>
                        <span class="badge bg-danger ms-1">FULL</span>
                      <?php endif; ?>
                      <div class="progress ms-2" style="width: 60px; height: 8px;">
                        <?php
                        $percentage = $section['max_capacity'] > 0 ? ($section['current_enrollment'] / $section['max_capacity']) * 100 : 0;
                        $progressClass = $percentage >= 100 ? 'bg-danger' : ($percentage >= 90 ? 'bg-warning' : ($percentage >= 70 ? 'bg-info' : 'bg-success'));
                        ?>
                        <div class="progress-bar <?= $progressClass ?>" style="width: <?= min(100, $percentage) ?>%"></div>
                      </div>
                    </div>
                  </td>
                  <td><?= $section['max_capacity'] ?> students</td>
                  <td>
                    <span class="badge bg-<?= $section['is_active'] ? 'success' : 'secondary' ?>">
                      <?= $section['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm" role="group">
                      <button class="btn btn-outline-primary" onclick="viewSectionStudents(<?= $section['id'] ?>)" title="View Students">
                        <i class="bi bi-people"></i>
                      </button>
                      <?php if ($section['current_enrollment'] >= $section['max_capacity']): ?>
                        <button class="btn btn-outline-secondary" disabled title="Section is full">
                          <i class="bi bi-person-x"></i>
                        </button>
                      <?php else: ?>
                        <button class="btn btn-outline-info" onclick="assignStudents(<?= $section['id'] ?>, '<?= esc($section['section_name']) ?>', <?= $section['grade_level'] ?>)" title="Assign Students">
                          <i class="bi bi-person-plus-fill"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

<?php else: ?>
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="bi bi-building fs-1 text-muted mb-3"></i>
      <h5 class="text-muted">No Sections Assigned</h5>
      <p class="text-muted">You are not currently assigned as an adviser to any sections. Please contact the administrator if you believe this is an error.</p>
    </div>
  </div>
<?php endif; ?>

<script>
// Store sections data for quick access
const sectionsData = <?= json_encode($sections) ?>;

function viewSectionStudents(sectionId) {
  const section = sectionsData.find(s => s.id == sectionId);

  if (!section) {
    alert('Section not found.');
    return;
  }

  // Build and show modal dynamically
  const modalEl = buildSectionStudentsModal(section.section_name);
  const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true, focus: true });

  // Show loading
  const listEl = modalEl.querySelector('#sectionStudentsList');
  listEl.innerHTML = `
    <div class="text-center">
      <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
      <p class="mt-2">Loading students...</p>
    </div>
  `;

  modal.show();

  // Fetch students via AJAX
  fetch(`<?= base_url('teacher/sections/students/') ?>${sectionId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displaySectionStudents(data.students);
      } else {
        document.getElementById('sectionStudentsList').innerHTML = `
          <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> ${data.error || 'Failed to load students'}
          </div>
        `;
      }
    })
    .catch(error => {
      document.getElementById('sectionStudentsList').innerHTML = `
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle"></i> Error loading students: ${error.message}
        </div>
      `;
    });
}

function buildSectionStudentsModal(sectionName) {
  const existing = document.getElementById('sectionStudentsModal');
  if (existing) existing.remove();

  const html = `
  <div class="modal fade" id="sectionStudentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-people me-2"></i>${sectionName} Students</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="sectionStudentsList"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div></div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  return document.getElementById('sectionStudentsModal');
}

function displaySectionStudents(students) {
  if (students.length === 0) {
    document.getElementById('sectionStudentsList').innerHTML = `
      <div class="text-center py-4">
        <i class="bi bi-people fs-1 text-muted mb-3"></i>
        <h6 class="text-muted">No students enrolled</h6>
        <p class="text-muted">This section doesn't have any students yet.</p>
      </div>
    `;
    return;
  }

  let studentsHtml = `
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>LRN</th>
            <th>Name</th>
            <th>Status</th>
            <th>Enrolled Date</th>
          </tr>
        </thead>
        <tbody>
  `;

  students.forEach(student => {
    studentsHtml += `
      <tr>
        <td>${student.lrn || 'N/A'}</td>
        <td>${student.first_name} ${student.last_name}</td>
        <td><span class="badge bg-success">Enrolled</span></td>
        <td>${student.created_at ? new Date(student.created_at).toLocaleDateString() : 'N/A'}</td>
      </tr>
    `;
  });

  studentsHtml += `
        </tbody>
      </table>
    </div>
    <div class="mt-3">
      <small class="text-muted">Total: ${students.length} students</small>
    </div>
  `;

  document.getElementById('sectionStudentsList').innerHTML = studentsHtml;
}

function assignStudents(sectionId, sectionName, gradeLevel) {
  // Find the section data to get the correct grade level
  const section = sectionsData.find(s => s.id == sectionId);
  const actualGradeLevel = section ? section.grade_level : gradeLevel;
  
  currentGradeLevel = actualGradeLevel;
  currentSectionId = sectionId;
  
  const modalEl = buildAssignStudentsModal({ sectionId, sectionName, gradeLevel: actualGradeLevel });
  const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true, focus: true });
  
  modal.show();
  loadStudentsPage(1, '');
}

let currentSearchTerm = '';

function loadStudentsPage(page = 1, search = '') {
  if (search !== undefined) {
    currentSearchTerm = search;
  }
  
  const listEl = document.getElementById('unassignedStudentsList');
  
  listEl.innerHTML = `
    <div class="text-center">
      <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
      <p class="mt-2">Loading unassigned students...</p>
    </div>
  `;
  
  const params = new URLSearchParams({
    page: page,
    ...(currentSearchTerm && { search: currentSearchTerm })
  });
  
  fetch(`<?= base_url('teacher/sections/unassigned-students/') ?>${currentGradeLevel}?${params}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayUnassignedStudents(data.students, currentSectionId, data.pagination);
      } else {
        listEl.innerHTML = `
          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> ${data.message || 'No unassigned students found for this grade level'}
          </div>
        `;
      }
    })
    .catch(error => {
      listEl.innerHTML = `
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle"></i> Error loading students: ${error.message}
        </div>
      `;
    });
}

function searchStudents() {
  const searchTerm = document.getElementById('studentSearch').value;
  loadStudentsPage(1, searchTerm);
}

function handleSearchKeyPress(event) {
  if (event.key === 'Enter') {
    event.preventDefault();
    searchStudents();
  }
}

function buildAssignStudentsModal({ sectionId, sectionName, gradeLevel }) {
  const existing = document.getElementById('assignStudentsModal');
  if (existing) existing.remove();
  
  const html = `
  <div class="modal fade" id="assignStudentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Assign Students to ${sectionName}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <div class="p-3 bg-light rounded">
            <h6 class="mb-1">${sectionName}</h6>
            <small class="text-muted">${formatGradeLevel(gradeLevel)} &bull; Unassigned students only</small>
          </div>
        </div>
        <div id="unassignedStudentsList"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="assignSelectedStudents(${sectionId})">
          <i class="bi bi-person-check"></i> Assign Selected
        </button>
      </div>
    </div></div>
  </div>`;
  
  document.body.insertAdjacentHTML('beforeend', html);
  return document.getElementById('assignStudentsModal');
}

let currentGradeLevel = null;
let currentSectionId = null;

function displayUnassignedStudents(students, sectionId, pagination = null) {
  const listEl = document.getElementById('unassignedStudentsList');
  
  if (students.length === 0) {
    listEl.innerHTML = `
      <div class="text-center py-4">
        <i class="bi bi-people fs-1 text-muted mb-3"></i>
        <h6 class="text-muted">No unassigned students</h6>
        <p class="text-muted">All students in this grade level are already assigned to sections.</p>
      </div>
    `;
    return;
  }
  
  let studentsHtml = `
    <div class="mb-3">
      <div class="row align-items-center">
        <div class="col-md-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="selectAllStudents" onchange="toggleAllStudents()">
            <label class="form-check-label fw-semibold" for="selectAllStudents">
              Select All (${pagination ? pagination.totalStudents : students.length} students)
            </label>
          </div>
        </div>
        <div class="col-md-5">
          <input type="text" class="form-control" id="studentSearch" placeholder="Search by name or LRN..." onkeypress="handleSearchKeyPress(event)">
        </div>
        <div class="col-md-1">
          <button type="button" class="btn btn-outline-primary" onclick="searchStudents()" title="Search">
            <i class="bi bi-search"></i>
          </button>
        </div>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th width="50">Select</th>
            <th>LRN</th>
            <th>Name</th>
            <th>Grade</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
  `;
  
  students.forEach(student => {
    studentsHtml += `
      <tr>
        <td>
          <div class="form-check">
            <input class="form-check-input student-checkbox" type="checkbox" value="${student.id}" id="student_${student.id}">
          </div>
        </td>
        <td>${student.lrn || 'N/A'}</td>
        <td>${student.first_name} ${student.last_name}</td>
        <td>${formatGradeLevel(student.grade_level)}</td>
        <td><span class="badge bg-warning">Unassigned</span></td>
      </tr>
    `;
  });
  
  studentsHtml += `
        </tbody>
      </table>
    </div>
  `;
  
  // Add pagination if provided
  if (pagination && pagination.totalPages > 1) {
    studentsHtml += `
      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">
          Showing ${((pagination.currentPage - 1) * pagination.perPage) + 1} to ${Math.min(pagination.currentPage * pagination.perPage, pagination.totalStudents)} of ${pagination.totalStudents} students
        </div>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            ${pagination.currentPage > 1 ? `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); loadStudentsPage(${pagination.currentPage - 1})">Previous</a></li>` : ''}
            ${Array.from({length: Math.min(5, pagination.totalPages)}, (_, i) => {
              const page = Math.max(1, pagination.currentPage - 2) + i;
              if (page <= pagination.totalPages) {
                return `<li class="page-item ${page === pagination.currentPage ? 'active' : ''}"><a class="page-link" href="#" onclick="event.preventDefault(); loadStudentsPage(${page})">${page}</a></li>`;
              }
              return '';
            }).join('')}
            ${pagination.currentPage < pagination.totalPages ? `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); loadStudentsPage(${pagination.currentPage + 1})">Next</a></li>` : ''}
          </ul>
        </nav>
      </div>
    `;
  }
  
  listEl.innerHTML = studentsHtml;
}

function toggleAllStudents() {
  const selectAll = document.getElementById('selectAllStudents');
  const checkboxes = document.querySelectorAll('.student-checkbox');
  
  checkboxes.forEach(checkbox => {
    checkbox.checked = selectAll.checked;
  });
}

function assignSelectedStudents(sectionId) {
  const selectedStudents = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
  
  if (selectedStudents.length === 0) {
    alert('Please select at least one student to assign.');
    return;
  }
  
  if (!confirm(`Are you sure you want to assign ${selectedStudents.length} student(s) to this section?`)) {
    return;
  }
  
  // Send assignment request
  fetch(`<?= base_url('teacher/sections/assign-students/') ?>${sectionId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
    },
    body: JSON.stringify({ student_ids: selectedStudents })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      bootstrap.Modal.getInstance(document.getElementById('assignStudentsModal')).hide();
      alert(data.message || 'Students assigned successfully!');
      setTimeout(() => location.reload(), 1000);
    } else {
      alert(data.error || 'Failed to assign students');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Network error occurred. Please try again.');
  });
}
</script>

<?= $this->endSection() ?>