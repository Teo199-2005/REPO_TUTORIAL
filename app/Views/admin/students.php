<?php if (!isset($this)) { /* placeholder to ensure file exists */ } ?>
<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>
<style><?= view('partials/password_requirements_style') ?></style>
<?php
  // Export PDF carries every active filter through the query string, so the
  // printed list matches what is on screen (all matching rows, not just this page).
  $studentFilterValues = [
    'search'       => trim((string) ($search ?? '')),
    'grade'        => (string) ($gradeLevel ?? ''),
    'section'      => (string) ($section ?? ''),
    'assignment'   => (string) ($assignment ?? ''),
    'status'       => (string) ($status ?? 'all'),
    'gender'       => (string) ($gender ?? ''),
    'student_type' => (string) ($studentType ?? ''),
    'religion'     => (string) ($religion ?? ''),
  ];

  $studentFilterNames = ['search', 'grade', 'section', 'assignment', 'status', 'gender', 'student_type', 'religion'];

  $studentActive = admin_filter_count_active($studentFilterValues, $studentFilterNames);

  $pdfParams = admin_filter_query($studentFilterValues, $studentFilterNames, ['', 'all']);
  $pdfUrl    = base_url('admin/students/export-pdf')
    . ($pdfParams !== [] ? '?' . http_build_query($pdfParams) : '');

  $studentHeaderActions = '<a class="btn btn-outline-secondary" href="' . base_url('admin/dashboard') . '">'
    . '<i class="bi bi-arrow-left"></i> Back</a>'
    . '<a class="btn btn-outline-primary" href="' . esc($pdfUrl) . '" target="_blank" rel="noopener">'
    . '<i class="bi bi-file-earmark-pdf"></i> Export PDF</a>'
    . '<a class="btn btn-primary admin-btn-primary" href="' . base_url('admin/students/enroll') . '">'
    . '<i class="bi bi-plus-circle"></i> Enroll student</a>';

  echo view('admin/partials/page_header', ['pageHeader' => [
    'icon'     => 'bi-people-fill',
    'title'    => 'Manage Students',
    'subtitle' => 'Enrolled learners, their profile and their records',
    'actions'  => $studentHeaderActions,
  ]]);
?>

<?php
  $studentGradeOptions = [admin_filter_option('', 'All grade levels')];
  foreach (grade_level_options() as $g) {
    $studentGradeOptions[] = admin_filter_option((string) $g, grade_level_label($g));
  }

  $studentSectionOptions = [admin_filter_option('', 'All sections')];
  foreach (($allSections ?? []) as $sec) {
    $studentSectionOptions[] = admin_filter_option(
      (string) $sec['id'],
      $sec['section_name'] . ' (' . grade_level_label((int) $sec['grade_level']) . ')'
    );
  }

  $studentStatusChoiceList = [];
  foreach (($statusOptions ?? ['all']) as $opt) {
    $studentStatusChoiceList[] = admin_filter_option(
      (string) $opt,
      $opt === 'all' ? 'All statuses' : ucfirst((string) $opt)
    );
  }

  $studentTypeChoiceList = [admin_filter_option('', 'All types')];
  foreach (($studentTypeOptions ?? []) as $opt) {
    $studentTypeChoiceList[] = admin_filter_option((string) $opt, ucfirst((string) $opt));
  }

  $studentReligionChoiceList = [admin_filter_option('', 'All religions')];
  foreach (($religionOptions ?? []) as $opt) {
    $studentReligionChoiceList[] = admin_filter_option((string) $opt, (string) $opt);
  }

  $advancedNames = ['status', 'gender', 'student_type', 'religion'];
  $advancedCount = admin_filter_count_active($studentFilterValues, $advancedNames);

  // The emergency-contacts sheet is a page action, not a filter, so it sits
  // beside the filter bar rather than inside the form.
  echo view('admin/partials/filter_bar', ['filterBar' => [
    'action'      => base_url('admin/students'),
    'id'          => 'studentFilter',
    'label'       => 'Filter students',
    'resetUrl'    => base_url('admin/students'),
    'activeCount' => $advancedCount['total'],
    'totalCount'  => $studentActive['total'],
    'primary'     => [
      [
        'name' => 'search', 'label' => 'Search', 'icon' => 'bi-search', 'type' => 'search',
        'value'       => admin_filter_value($studentFilterValues, 'search'),
        'placeholder' => 'Name or LRN',
      ],
      [
        'name' => 'grade', 'label' => 'Grade level', 'icon' => 'bi-mortarboard',
        'value'   => admin_filter_value($studentFilterValues, 'grade'),
        'options' => $studentGradeOptions,
      ],
      [
        'name' => 'section', 'label' => 'Section', 'icon' => 'bi-people',
        'value'   => admin_filter_value($studentFilterValues, 'section'),
        'options' => $studentSectionOptions,
      ],
      [
        'name' => 'assignment', 'label' => 'Section assignment', 'icon' => 'bi-diagram-2',
        'value'   => admin_filter_value($studentFilterValues, 'assignment'),
        'options' => [
          admin_filter_option('', 'All students'),
          admin_filter_option('assigned', 'Has a section'),
          admin_filter_option('unassigned', 'No section yet'),
        ],
      ],
    ],
    'advanced'    => [
      [
        'name' => 'status', 'label' => 'Status', 'icon' => 'bi-activity',
        'value'   => admin_filter_value($studentFilterValues, 'status', 'all'),
        'options' => $studentStatusChoiceList,
      ],
      [
        'name' => 'student_type', 'label' => 'Student type', 'icon' => 'bi-person-badge',
        'value'   => admin_filter_value($studentFilterValues, 'student_type'),
        'options' => $studentTypeChoiceList,
      ],
      [
        'name' => 'gender', 'label' => 'Sex', 'icon' => 'bi-gender-ambiguous',
        'value'   => admin_filter_value($studentFilterValues, 'gender'),
        'options' => [
          admin_filter_option('', 'All'),
          admin_filter_option('Male', 'Male'),
          admin_filter_option('Female', 'Female'),
        ],
      ],
      [
        'name' => 'religion', 'label' => 'Religion', 'icon' => 'bi-book',
        'value'   => admin_filter_value($studentFilterValues, 'religion'),
        'options' => $studentReligionChoiceList,
      ],
    ],
  ]]);
?>
<button type="button" class="btn btn-outline-secondary mb-3" onclick="showEmergencyContacts()">
  <i class="bi bi-person-lines-fill" aria-hidden="true"></i> Emergency contacts
</button>

<div class="card">
  <div class="card-body p-0">
    <?php if (!empty($students)): ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 admin-table">
          <thead>
            <tr>
              <th><i class="bi bi-hash me-1 text-muted"></i>LRN</th>
              <th><i class="bi bi-person me-1 text-muted"></i>Name</th>
              <th><i class="bi bi-mortarboard me-1 text-muted"></i>Grade</th>
              <th><i class="bi bi-people me-1 text-muted"></i>Section</th>
              <th><i class="bi bi-activity me-1 text-muted"></i>Status</th>
              <th class="text-end"><i class="bi bi-gear me-1 text-muted"></i>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $st): ?>
              <tr>
                <td><?= esc($st['lrn'] ?? '—') ?></td>
                <td><?= esc($st['first_name'].' '.$st['last_name']) ?></td>
                <td>
                  <?= esc(grade_level_label((int) ($st['grade_level'] ?? 0))) ?>
                </td>
                <td>
                  <?php if (empty($st['section_name'])): ?>
                    <span class="text-danger">
                      <i class="bi bi-exclamation-triangle me-1"></i>
                      Not assigned
                    </span>
                  <?php else: ?>
                    <?php 
                      // Remove "Grade X - " prefix if it exists
                      $sectionName = $st['section_name'];
                      $sectionName = preg_replace('/^Grade \d+ - /', '', $sectionName);
                      echo esc($sectionName);
                    ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                  $stStatus = (string) ($st['enrollment_status'] ?? 'enrolled');
                  $stStatusClass = match ($stStatus) {
                    'enrolled' => 'bg-success',
                    'approved' => 'bg-info text-dark',
                    'graduated' => 'bg-primary',
                    'dropped' => 'bg-danger',
                    default => 'bg-secondary',
                  };
                  ?>
                  <span class="badge <?= $stStatusClass ?>"><?= esc(ucfirst(str_replace('_', ' ', $stStatus))) ?></span>
                </td>
                <td class="text-end">
                  <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-outline-primary" onclick="viewStudent(<?= $st['id'] ?>)" title="View Details">
                      <i class="bi bi-eye"></i>
                    </button>
                    <a href="<?= base_url('admin/students/edit/' . $st['id']) ?>" class="btn btn-sm btn-outline-warning" title="Edit Student">
                      <i class="bi bi-pencil"></i>
                    </a>
<button class="btn btn-sm btn-danger" title="Delete Student" onclick="showDeleteConfirmation(<?= $st['id'] ?>, '<?= esc($st['first_name'] . ' ' . $st['last_name'], 'js') ?>')">
  <i class="bi bi-trash"></i>
</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <?= view('admin/partials/empty_state', ['emptyState' => [
        'icon'   => 'bi-inbox',
        'title'  => $studentActive['total'] > 0
          ? 'No students match these filters'
          : 'No enrolled students yet',
        'hint'   => $studentActive['total'] > 0
          ? 'Try clearing a filter, or widen the grade level and section.'
          : 'Enrolled students will appear here once they are added.',
        'action' => $studentActive['total'] > 0
          ? '<a class="btn btn-outline-secondary" href="' . base_url('admin/students') . '">'
            . '<i class="bi bi-arrow-counterclockwise"></i> Reset filters</a>'
          : '<a class="btn btn-primary admin-btn-primary" href="' . base_url('admin/students/enroll') . '">'
            . '<i class="bi bi-plus-circle"></i> Enroll student</a>',
      ]]) ?>
    <?php endif; ?>
  </div>
  
  <div class="card-footer">
    <div class="d-flex justify-content-between align-items-center">
      <div class="text-muted small">
        <?php if (isset($totalStudents) && $totalStudents > 0): ?>
          Showing <?= ($currentPage - 1) * $perPage + 1 ?> to <?= min($currentPage * $perPage, $totalStudents) ?> of <?= $totalStudents ?> students
        <?php else: ?>
          No students found
        <?php endif; ?>
      </div>
      <?php if (isset($totalPages) && $totalPages > 1): ?>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php if ($currentPage > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>">
                  <i class="bi bi-chevron-left"></i> Previous
                </a>
              </li>
            <?php endif; ?>
            
            <?php 
            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);
            for ($i = $startPage; $i <= $endPage; $i++): 
            ?>
              <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            
            <?php if ($currentPage < $totalPages): ?>
              <li class="page-item">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>">
                  Next <i class="bi bi-chevron-right"></i>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Custom Student Details Modal -->
<div id="customStudentModal" class="custom-modal-overlay" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="customStudentModalLabel">
  <div class="custom-modal-container">
    <div class="custom-modal-header">
      <h3 class="custom-modal-title" id="customStudentModalLabel">Student Details</h3>
      <button type="button" class="custom-modal-close" onclick="closeStudentModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="custom-modal-body">
      <div id="customStudentDetails">
        <div class="loading-spinner">
          <div class="spinner"></div>
          <p>Loading student details...</p>
        </div>
      </div>
    </div>
    <div class="custom-modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeStudentModal()">Close</button>
    </div>
  </div>
</div>

<!-- Document Viewer Modal -->
<div id="documentViewerModal" class="document-viewer-modal" style="display: none;">
  <div class="document-viewer-overlay" onclick="closeDocumentModal()"></div>
  <div class="document-viewer-content">
    <div class="document-viewer-header">
      <h5 id="documentViewerTitle">Document Viewer</h5>
      <button type="button" class="document-viewer-close" onclick="closeDocumentModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="document-viewer-body">
      <img id="documentViewerImage" src="" alt="Document" class="document-viewer-image">
    </div>
  </div>
</div>

<!-- Edit Student Modal -->
<div id="editStudentModal" class="custom-modal-overlay" style="display: none;">
  <div class="custom-modal-container">
    <div class="custom-modal-header">
      <h3 class="custom-modal-title">Edit Student</h3>
      <button type="button" class="custom-modal-close" onclick="closeEditStudentModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="custom-modal-body">
      <form id="editStudentForm">
        <div class="row">
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">LRN</label>
              <input type="text" class="form-control" name="lrn" id="editLrn" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Student Type</label>
              <select class="form-select" name="student_type" id="editStudentType">
                <option value="">Select Type</option>
                <option value="New Student">New Student</option>
                <option value="Transferee">Transferee</option>
                <option value="Old Student">Old Student</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" id="editEmail" required>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">First Name</label>
              <input type="text" class="form-control" name="first_name" id="editFirstName" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Last Name</label>
              <input type="text" class="form-control" name="last_name" id="editLastName" required>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Grade Level</label>
              <select class="form-select" name="grade_level" id="editGradeLevel" required>
                <?php foreach (grade_level_options() as $g): ?>
                  <option value="<?= $g ?>"><?= esc(grade_level_label($g)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Gender</label>
              <select class="form-select" name="gender" id="editGender" required>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="enrollment_status" id="editStatus" required>
                <option value="enrolled">Enrolled</option>
                <option value="suspended">Suspended</option>
                <option value="graduated">Graduated</option>
                <option value="transferred">Transferred</option>
              </select>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Contact Number</label>
          <input type="text" class="form-control" name="contact_number" id="editContactNumber" maxlength="11" inputmode="numeric" pattern="^09[0-9]{9}" autocomplete="tel-national" placeholder="09XXXXXXXXX">
        </div>
        <div class="mb-3">
          <label class="form-label">Address</label>
          <input type="hidden" name="address" id="editAddress" value="">
          <div data-loc-group="address" data-loc-field="editAddress" data-loc-label="Address"></div>
        </div>
      </form>
    </div>
    <div class="custom-modal-footer">
      <button type="button" class="btn btn-primary" onclick="saveStudent()">Save Changes</button>
    </div>
  </div>
</div>

<style>
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
  animation: fadeIn 0.3s ease-out;
}

.custom-modal-container {
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.10), 0 24px 56px -8px rgba(15, 23, 42, 0.16);
  max-width: 900px;
  width: 90%;
  max-height: 90vh;
  overflow: hidden;
  position: relative;
  animation: slideIn 0.3s ease-out;
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

.custom-modal-header h3 {
  color: #ffffff !important;
  margin: 0;
}

.custom-modal-header h3.custom-modal-title {
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
}

.custom-modal-footer {
  background: #f1f5f9;
  padding: 1.5rem 2rem;
  border-top: 2px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

/* Loading Spinner */
.loading-spinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  color: #6b7280;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e5e7eb;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

/* Animations */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-50px) scale(0.9);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Student Details Styling */
.student-info-section {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  border: var(--hairline);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.student-info-title {
  color: #1e40af;
  font-size: 1.1rem;
  font-weight: 700;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #e2e8f0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.student-info-table {
  width: 100%;
  border-collapse: collapse;
}

.student-info-table td {
  padding: 0.75rem 0;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: top;
}

.student-info-table td:first-child {
  font-weight: 700;
  color: #374151;
  width: 35%;
}

.student-info-table td:last-child {
  color: #6b7280;
}

.student-info-table tr:last-child td {
  border-bottom: none;
}

/* Document Viewer Modal */
.document-viewer-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.9);
  z-index: 100000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.document-viewer-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.document-viewer-content {
  background: white;
  border-radius: 12px;
  max-width: 90vw;
  max-height: 90vh;
  overflow: hidden;
  position: relative;
}

.document-viewer-header {
  background: #1e40af;
  color: white;
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.document-viewer-header h5 {
  margin: 0;
  color: white;
}

.document-viewer-close {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.document-viewer-body {
  padding: 1rem;
  text-align: center;
}

.document-viewer-image {
  max-width: 100%;
  max-height: 70vh;
  object-fit: contain;
}

/* Emergency Contacts Modal */
.emergency-contact-avatar {
  font-size: 1.75rem;
  color: #3b82f6;
  display: inline-flex;
  align-items: center;
}

#emergencyContactsPagination .page-link {
  cursor: pointer;
  user-select: none;
}

/* Responsive Design */
@media (max-width: 768px) {
  .custom-modal-container {
    width: 95%;
    margin: 1rem;
  }

  .custom-modal-header,
  .custom-modal-body,
  .custom-modal-footer {
    padding: 1rem;
  }

  .custom-modal-title {
    font-size: 1.2rem;
  }
}
</style>

<script>
// Store student data for quick access
const studentsData = <?= json_encode($students) ?>;

// Filter form helpers. The visible filter bar auto-submits through
// public/js/admin-filter-bar.js, so only URL-driven shortcuts live here.
function clearFilters() {
  window.location.href = '<?= base_url('admin/students') ?>';
}

function filterByGrade(grade) {
  const url = new URL(window.location);
  if (grade) {
    url.searchParams.set('grade', grade);
  } else {
    url.searchParams.delete('grade');
  }
  url.searchParams.delete('page'); // Reset to first page
  window.location.href = url.toString();
}

function filterBySection(section) {
  const url = new URL(window.location);
  if (section) {
    url.searchParams.set('section', section);
  } else {
    url.searchParams.delete('section');
  }
  url.searchParams.delete('page'); // Reset to first page
  window.location.href = url.toString();
}

// View student details in full page
function viewStudent(studentId) {
  window.location.href = `<?= base_url('admin/students/view/') ?>${studentId}`;
}

// Open custom modal
function openStudentModal(studentId) {
  const modal = document.getElementById('customStudentModal');
  const detailsContainer = document.getElementById('customStudentDetails');
  const footer = document.querySelector('.modern-footer');

  // Hide footer and show modal
  if (footer) footer.style.display = 'none';
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden'; // Prevent background scrolling

  // Show loading state
  detailsContainer.innerHTML = `
    <div class="loading-spinner">
      <div class="spinner"></div>
      <p>Loading student details...</p>
    </div>
  `;

  // Simulate loading delay and then show student data
  setTimeout(() => {
    loadStudentDetails(studentId);
  }, 500);
}

// Close custom modal
function closeStudentModal() {
  const modal = document.getElementById('customStudentModal');
  const footer = document.querySelector('.modern-footer');
  
  modal.style.display = 'none';
  if (footer) footer.style.display = 'block';
  document.body.style.overflow = ''; // Restore scrolling
}

// Load and display student details
function loadStudentDetails(studentId) {
  const detailsContainer = document.getElementById('customStudentDetails');

  // Fetch student details with documents from server
  fetch(`<?= base_url('admin/students/details/') ?>${studentId}`)
    .then(response => response.text())
    .then(html => {
      detailsContainer.innerHTML = html;
    })
    .catch(error => {
      console.error('Error loading student details:', error);

      // Fallback to local data if server request fails
      const student = studentsData.find(s => s.id == studentId);

      if (!student) {
        detailsContainer.innerHTML = `
          <div class="student-info-section">
            <div class="alert alert-danger">
              <i class="bi bi-exclamation-triangle"></i>
              Student not found.
            </div>
          </div>
        `;
        return;
      }

      // Show basic student info without documents
      loadBasicStudentDetails(student, detailsContainer);
    });
}

// Fallback function for basic student details
function loadBasicStudentDetails(student, detailsContainer) {

  const detailsHtml = `
    <div class="row">
      <div class="col-md-6">
        <div class="student-info-section">
          <div class="student-info-title">
            <i class="bi bi-person-circle"></i>
            Personal Information
          </div>
          <table class="student-info-table">
            <tr><td>LRN:</td><td>${student.lrn || 'N/A'}</td></tr>
            <tr><td>Full Name:</td><td>${student.first_name} ${student.middle_name || ''} ${student.last_name} ${student.suffix || ''}</td></tr>
            <tr><td>Gender:</td><td>${student.gender || 'N/A'}</td></tr>
            <tr><td>Date of Birth:</td><td>${student.date_of_birth ? new Date(student.date_of_birth).toLocaleDateString() : 'N/A'}</td></tr>
            <tr><td>Place of Birth:</td><td>${student.place_of_birth || 'N/A'}</td></tr>
            <tr><td>Nationality:</td><td>${student.nationality || 'N/A'}</td></tr>
            <tr><td>Religion:</td><td>${student.religion || 'N/A'}</td></tr>
          </table>
        </div>
      </div>
      <div class="col-md-6">
        <div class="student-info-section">
          <div class="student-info-title">
            <i class="bi bi-telephone"></i>
            Contact Information
          </div>
          <table class="student-info-table">
            <tr><td>Email:</td><td>${student.email || 'N/A'}</td></tr>
            <tr><td>Contact Number:</td><td>${student.contact_number || 'N/A'}</td></tr>
            <tr><td>Address:</td><td>${student.address || 'N/A'}</td></tr>
          </table>
        </div>

        <div class="student-info-section">
          <div class="student-info-title">
            <i class="bi bi-shield-exclamation"></i>
            Emergency Contact
          </div>
          <table class="student-info-table">
            <tr><td>Name:</td><td>${student.emergency_contact_name || 'N/A'}</td></tr>
            <tr><td>Number:</td><td>${student.emergency_contact_number || 'N/A'}</td></tr>
            <tr><td>Relationship:</td><td>${student.emergency_contact_relationship || 'N/A'}</td></tr>
          </table>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-12">
        <div class="student-info-section">
          <div class="student-info-title">
            <i class="bi bi-mortarboard"></i>
            Academic Information
          </div>
          <table class="student-info-table">
            <tr><td>Grade Level:</td><td>${formatGradeLevel(student.grade_level)}</td></tr>
            <tr><td>Section:</td><td>${student.section_name || 'Not assigned'}</td></tr>
            <tr><td>School Year:</td><td>${student.school_year || 'N/A'}</td></tr>
            <tr><td>Status:</td><td><span class="badge bg-success">Enrolled</span></td></tr>
            <tr><td>Enrollment Date:</td><td>${student.created_at ? new Date(student.created_at).toLocaleDateString() : 'N/A'}</td></tr>
          </table>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-12">
        <div class="student-info-section">
          <div class="student-info-title">
            <i class="bi bi-file-earmark-text"></i>
            Required Documents
          </div>
          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            Documents are not available in fallback mode. Please refresh the page to load documents.
          </div>
        </div>
      </div>
    </div>
  `;

  detailsContainer.innerHTML = detailsHtml;
}

// Simple image viewer functions (global scope)
function showImageModal(imageUrl, title) {
  // Create modal if it doesn't exist
  let modal = document.getElementById('simpleImageModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'simpleImageModal';
    modal.innerHTML = `
      <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10000; display: flex; align-items: center; justify-content: center;" onclick="closeSimpleModal()">
        <div style="background: white; border-radius: 12px; max-width: 90vw; max-height: 90vh; overflow: hidden; position: relative;" onclick="event.stopPropagation()">
          <div style="background: #1e40af; color: white; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <h5 style="margin: 0; color: white;" id="simpleModalTitle">Document Viewer</h5>
            <button onclick="closeSimpleModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; cursor: pointer;">×</button>
          </div>
          <div style="padding: 1rem; text-align: center;">
            <img id="simpleModalImage" src="" alt="Document" style="max-width: 100%; max-height: 70vh; object-fit: contain;">
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  }

  // Set image and title
  const img = modal.querySelector('#simpleModalImage');
  const titleEl = modal.querySelector('#simpleModalTitle');

  if (img) img.src = imageUrl;
  if (titleEl) titleEl.textContent = title;

  // Show modal
  modal.style.display = 'block';
  document.body.style.overflow = 'hidden';
}

function closeSimpleModal() {
  const modal = document.getElementById('simpleImageModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
}



// Document viewer functions (global scope) - keeping for compatibility
function openDocumentModal(imageUrl, title) {
  console.log('openDocumentModal called, redirecting to showImageModal');
  showImageModal(imageUrl, title);
}

function closeDocumentModal() {
  console.log('Closing document modal');

  const modal = document.getElementById('documentViewerModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = '';
    console.log('Document modal closed successfully');
  } else {
    console.error('Document viewer modal not found for closing');
  }
}

// Event listeners for the custom modal
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('customStudentModal');

  // Delegated archive confirmation with explicit confirmation step
  document.addEventListener('click', function(e) {
    const archiveBtn = e.target.closest('button[data-archive-id]');
    if (!archiveBtn) return;
    e.preventDefault();
    e.stopPropagation();
    const studentId = archiveBtn.getAttribute('data-archive-id');
    const studentName = archiveBtn.getAttribute('data-archive-name') || 'this student';
    if (typeof showArchiveConfirmation === 'function') {
      showArchiveConfirmation(studentId, studentName);
    } else if (typeof showConfirmModal === 'function') {
      showConfirmModal({
        title: 'Confirm Archive',
        message: 'Are you sure you want to archive "' + studentName + '"? This can be reversed later.',
        confirmText: 'Yes, archive',
        cancelText: 'Cancel',
        onConfirm: function () {
          if (typeof confirmArchiveStudent === 'function') {
            confirmArchiveStudent(studentId);
          }
        }
      });
    } else {
      alert('Archive functionality requires confirmation. Please enable modal support.');
    }
  });

  // Event delegation for clickable documents
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('clickable-document') || e.target.closest('.clickable-document')) {
      e.preventDefault();
      e.stopPropagation();

      const element = e.target.classList.contains('clickable-document') ? e.target : e.target.closest('.clickable-document');
      const imageUrl = element.getAttribute('data-image-url');
      const imageTitle = element.getAttribute('data-image-title');

      console.log('Document clicked via event delegation:', imageUrl, imageTitle);

      if (imageUrl && imageTitle) {
        openDocumentModal(imageUrl, imageTitle);
      }
    }
  });

  // Close modal when clicking outside the modal container
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      closeStudentModal();
    }
  });

  // Close modal with Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      if (modal.style.display === 'flex') {
        closeStudentModal();
      }

      // Also close document viewer modal
      const docModal = document.getElementById('documentViewerModal');
      if (docModal && docModal.style.display === 'flex') {
        closeDocumentModal();
      }
    }
  });

  // Prevent modal content clicks from closing the modal
  const modalContainer = modal.querySelector('.custom-modal-container');
  if (modalContainer) {
    modalContainer.addEventListener('click', function(e) {
      e.stopPropagation();
    });
  }
});

// Emergency Contacts Modal Functions
const emergencyContactsState = {
  search: '',
  page: 1,
  perPage: 25
};

function showEmergencyContacts() {
  const modal = document.getElementById('emergencyContactsModal');
  const contactsList = document.getElementById('emergencyContactsList');
  const footerEl = document.getElementById('emergencyContactsFooter');
  const footer = document.querySelector('.modern-footer');
  
  if (footer) footer.style.display = 'none';
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  // Reset search + pagination state each time the modal opens
  emergencyContactsState.search = '';
  emergencyContactsState.page = 1;
  const searchInput = document.getElementById('emergencyContactSearch');
  if (searchInput) searchInput.value = '';
  if (footerEl) footerEl.style.display = 'none';
  
  // Show loading state
  contactsList.innerHTML = `
    <div class="loading-spinner">
      <div class="spinner"></div>
      <p>Loading emergency contacts...</p>
    </div>
  `;
  
  // Load emergency contacts data
  setTimeout(() => {
    renderEmergencyContacts();
  }, 500);
}

function closeEmergencyContactsModal() {
  const modal = document.getElementById('emergencyContactsModal');
  const footer = document.querySelector('.modern-footer');
  
  modal.style.display = 'none';
  if (footer) footer.style.display = 'block';
  document.body.style.overflow = '';
}

function getFilteredEmergencyContacts() {
  const term = emergencyContactsState.search;
  if (!term) return studentsData;
  
  return studentsData.filter(student => {
    const lrn = String(student.lrn || '').toLowerCase();
    const name = `${student.first_name || ''} ${student.last_name || ''}`.toLowerCase();
    const contact = `${student.emergency_contact_name || ''} ${student.emergency_contact_number || ''} ${student.emergency_contact_relationship || ''}`.toLowerCase();
    
    return lrn.includes(term) || name.includes(term) || contact.includes(term);
  });
}

function renderEmergencyContacts() {
  const contactsList = document.getElementById('emergencyContactsList');
  const filtered = getFilteredEmergencyContacts();
  const total = filtered.length;
  const totalPages = Math.max(1, Math.ceil(total / emergencyContactsState.perPage));
  
  if (emergencyContactsState.page > totalPages) {
    emergencyContactsState.page = totalPages;
  }
  
  const start = (emergencyContactsState.page - 1) * emergencyContactsState.perPage;
  const pageItems = filtered.slice(start, start + emergencyContactsState.perPage);
  
  if (total === 0) {
    contactsList.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-search me-1"></i> No students found matching your search.</div>';
    updateEmergencyPaginationFooter(0, 1, 0, 0);
    return;
  }
  
  let contactsHtml = '<div class="table-responsive">';
  contactsHtml += '<table class="table table-hover align-middle mb-2 admin-table" id="emergencyContactsTable">';
  contactsHtml += '<thead><tr><th><i class="bi bi-person me-1"></i>Student</th><th><i class="bi bi-telephone me-1"></i>Emergency Contact</th></tr></thead>';
  contactsHtml += '<tbody>';
  
  pageItems.forEach(student => {
    contactsHtml += `
      <tr>
        <td>
          <div class="d-flex align-items-center">
            <span class="emergency-contact-avatar me-2"><i class="bi bi-person-circle"></i></span>
            <div>
              <div class="fw-semibold">${student.first_name} ${student.last_name}</div>
              <small class="text-muted">${student.lrn || 'N/A'} · ${formatGradeLevel(student.grade_level)}</small>
            </div>
          </div>
        </td>
        <td>
          <div><i class="bi bi-person-badge text-primary me-1"></i><span class="fw-semibold">Name:</span> ${student.emergency_contact_name || 'N/A'}</div>
          <div><i class="bi bi-telephone-fill text-success me-1"></i><span class="fw-semibold">Number:</span> ${student.emergency_contact_number || 'N/A'}</div>
          <div><i class="bi bi-people-fill text-warning me-1"></i><span class="fw-semibold">Relationship:</span> ${student.emergency_contact_relationship || 'N/A'}</div>
        </td>
      </tr>
    `;
  });
  
  contactsHtml += '</tbody></table></div>';
  contactsList.innerHTML = contactsHtml;
  
  updateEmergencyPaginationFooter(total, totalPages, start, start + pageItems.length);
}

function updateEmergencyPaginationFooter(total, totalPages, from, to) {
  const footerEl = document.getElementById('emergencyContactsFooter');
  const countEl = document.getElementById('emergencyContactsCount');
  const navEl = document.getElementById('emergencyContactsPagination');
  
  if (!footerEl || !countEl || !navEl) return;
  
  footerEl.style.display = 'flex';
  
  if (total === 0) {
    countEl.innerHTML = 'Showing 0 of 0 rows';
    navEl.innerHTML = '';
    return;
  }
  
  countEl.innerHTML = `Showing ${from + 1}-${to} of ${total} row${total === 1 ? '' : 's'}`;
  
  const current = emergencyContactsState.page;
  let pagesHtml = `<li class="page-item ${current <= 1 ? 'disabled' : ''}">
    <a class="page-link" onclick="${current > 1 ? `goToEmergencyPage(${current - 1})` : 'return false;'}" aria-label="Previous">
      <i class="bi bi-chevron-left"></i>
    </a>
  </li>`;
  
  const startPage = Math.max(1, current - 2);
  const endPage = Math.min(totalPages, current + 2);
  for (let i = startPage; i <= endPage; i++) {
    pagesHtml += `<li class="page-item ${i === current ? 'active' : ''}">
      <a class="page-link" onclick="goToEmergencyPage(${i})">${i}</a>
    </li>`;
  }
  
  pagesHtml += `<li class="page-item ${current >= totalPages ? 'disabled' : ''}">
    <a class="page-link" onclick="${current < totalPages ? `goToEmergencyPage(${current + 1})` : 'return false;'}" aria-label="Next">
      <i class="bi bi-chevron-right"></i>
    </a>
  </li>`;
  
  navEl.innerHTML = `<ul class="pagination pagination-sm mb-0">${pagesHtml}</ul>`;
}

function goToEmergencyPage(page) {
  emergencyContactsState.page = page;
  renderEmergencyContacts();
}

function changeEmergencyPerPage(value) {
  emergencyContactsState.perPage = parseInt(value, 10) || 25;
  emergencyContactsState.page = 1;
  renderEmergencyContacts();
}

function filterEmergencyContacts() {
  const searchInput = document.getElementById('emergencyContactSearch');
  emergencyContactsState.search = (searchInput ? searchInput.value : '').toLowerCase().trim();
  emergencyContactsState.page = 1;
  renderEmergencyContacts();
}

function clearEmergencyContactSearch() {
  const searchInput = document.getElementById('emergencyContactSearch');
  if (searchInput) searchInput.value = '';
  emergencyContactsState.search = '';
  emergencyContactsState.page = 1;
  renderEmergencyContacts();
}

// Edit Student - redirect to edit page
function editStudent(studentId) {
  window.location.href = `<?= base_url('admin/students/edit/') ?>${studentId}`;
}

// Enroll Student Modal Functions
function buildEnrollStudentModal() {
  const existing = document.getElementById('enrollStudentModal');
  if (existing) existing.remove();

  const html = `
  <div class="modal fade" id="enrollStudentModal" tabindex="-1">
    <div class="modal-dialog modal-xl"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Enroll New Student</h5>
        <div class="d-flex align-items-center">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <button type="button" class="btn btn-info btn-sm position-absolute" style="top: 15px; right: 50px; z-index: 1000;" onclick="fillDemoData()">Demo Fill</button>
      </div>
      <form id="enrollStudentForm" method="post" action="<?= base_url('admin/students/store') ?>" enctype="multipart/form-data">
        <?= str_replace(["\n","\r"], '', csrf_field()) ?>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
          <h6 class="text-primary mb-3">Account Information</h6>
          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">LRN</label>
                <input type="text" class="form-control" name="lrn" placeholder="Auto-generated if empty">
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" class="form-control" name="email" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label" for="enrollPassword">Password *</label>
                <div style="position: relative;">
                  <input type="password" class="form-control" name="password" id="enrollPassword"
                         minlength="<?= password_policy_min_length() ?>" pattern="(?=.*\d).{<?= password_policy_min_length() ?>,}"
                         autocomplete="new-password" required data-password-indicator>
                  <button type="button" id="toggleEnrollPassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer; color: #6c757d;">
                    <i class="fas fa-eye" id="enrollEyeIcon"></i>
                  </button>
                </div>
                <div class="alert alert-info d-flex align-items-center gap-2 py-2 px-3 mt-2" role="note" style="font-size: .8rem;">
                  <i class="bi bi-key-fill" aria-hidden="true"></i>
                  <span><strong>Input your desired password.</strong> This is the password you will use to log in.</span>
                </div>
                <?= view('partials/password_requirements', ['compact' => true]) ?>
              </div>
            </div>
          </div>
          <hr>
          <h6 class="text-primary mb-3">Personal Information</h6>
          <div class="row">
            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">First Name *</label>
                <input type="text" class="form-control" name="first_name" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Middle Name</label>
                <input type="text" class="form-control" name="middle_name" minlength="2">
              </div>
            </div>
            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Last Name *</label>
                <input type="text" class="form-control" name="last_name" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Suffix</label>
                <select class="form-select" name="suffix">
                  <option value="">None</option>
                  <option value="Jr.">Jr.</option>
                  <option value="Sr.">Sr.</option>
                  <option value="II">II</option>
                  <option value="III">III</option>
                  <option value="IV">IV</option>
                  <option value="V">V</option>
                </select>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Gender *</label>
                <select class="form-select" name="gender" required>
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Date of Birth *</label>
                <input type="date" class="form-control" name="date_of_birth" required>
              </div>
            </div>
            <div class="col-md-12">
              <div class="mb-3">
                <label class="form-label">Place of Birth</label>
                <input type="hidden" name="place_of_birth" id="modal_place_of_birth" value="">
                <div data-loc-group="place_of_birth" data-loc-field="modal_place_of_birth" data-loc-label="Place of Birth"></div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Nationality</label>
                <input type="text" class="form-control" name="nationality" value="Filipino">
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Religion</label>
                <input type="text" class="form-control" name="religion">
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Contact Number</label>
                <input type="text" class="form-control" name="contact_number" maxlength="11" inputmode="numeric" pattern="^09[0-9]{9}" autocomplete="tel-national" placeholder="09XXXXXXXXX">
              </div>
            </div>
          </div>
          <hr>
          <h6 class="text-primary mb-3">Academic Information</h6>
          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Grade Level *</label>
                <select class="form-select" name="grade_level" required>
                  <option value="">Select Grade</option>
                  <?php foreach (grade_level_options() as $g): ?>
                    <option value="<?= $g ?>"><?= esc(grade_level_label($g)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Student Type *</label>
                <select class="form-select" name="student_type" required>
                  <option value="">Select Type</option>
                  <option value="New Student">New Student</option>
                  <option value="Transferee">Transferee</option>
                  <option value="Old Student">Old Student</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Section</label>
                <select class="form-select" name="section_id">
                  <option value="">No Section Assigned</option>
                </select>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Address</label>
            <input type="hidden" name="address" id="modal_address" value="">
            <div data-loc-group="address" data-loc-field="modal_address" data-loc-label="Address"></div>
          </div>
          <hr>
          <h6 class="text-primary mb-3">Required Documents</h6>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Birth Certificate *</label>
                <input type="file" class="form-control" name="birth_certificate" accept=".pdf,.jpg,.jpeg,.png" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Report Card (Form 138) *</label>
                <input type="file" class="form-control" name="report_card" accept=".pdf,.jpg,.jpeg,.png" required>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Good Moral Certificate *</label>
                <input type="file" class="form-control" name="good_moral" accept=".pdf,.jpg,.jpeg,.png" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">2x2 Photo *</label>
                <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png" required>
              </div>
            </div>
          </div>
          <hr>
          <h6 class="text-primary mb-3">Emergency Contact</h6>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Emergency Contact Name</label>
                <input type="text" class="form-control" name="emergency_contact_name">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Emergency Contact Number</label>
                <input type="text" class="form-control" name="emergency_contact_number" maxlength="11" inputmode="numeric" pattern="^09[0-9]{9}" autocomplete="tel-national" placeholder="09XXXXXXXXX">
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="enrollRelationship">Relationship</label>
            <select class="form-select" name="emergency_contact_relationship" id="enrollRelationship" onchange="toggleEnrollRelationshipOther()">
              <option value="">Select Relationship</option>
              <?php foreach (emergency_contact_relationship_options() as $relOption): ?>
                <option value="<?= esc($relOption) ?>"><?= esc($relOption) ?></option>
              <?php endforeach; ?>
            </select>
            <div id="enrollRelationshipOtherWrap" style="display: none; margin-top: .35rem;">
              <label class="form-label" for="enrollRelationshipOther">Specify Relationship</label>
              <input type="text" class="form-control" name="emergency_contact_relationship_other" id="enrollRelationshipOther"
                     maxlength="50" placeholder="e.g. Godparent, Friend" autocomplete="off">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #374151 !important; border-color: #374151 !important;">Cancel</button>
          <button type="submit" class="btn btn-primary">Enroll Student</button>
        </div>
      </form>
    </div></div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  // The modal is injected after page load, so re-run the shared contact-number
  // normaliser over its inputs (inline handlers never fire for injected HTML).
  if (window.PhoneInput) {
    window.PhoneInput.enhanceAll(document.getElementById('enrollStudentModal'));
  }
  return document.getElementById('enrollStudentModal');
}

function openEnrollStudentModal() {
  const modalEl = buildEnrollStudentModal();
  const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true, focus: true });
  modal.show();
}

// Handle enrollment form submission
document.addEventListener('submit', function(e) {
  if (e.target.id === 'enrollStudentForm') {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    fetch(e.target.action, {
      method: 'POST',
      body: formData
    })
    .then(response => {
      if (!response.ok) {
        return response.text().then(text => {
          throw new Error(`HTTP ${response.status}: ${text}`);
        });
      }
      return response.text();
    })
    .then(text => {
      // Check if it's a successful redirect (HTML response)
      if (text.includes('<!doctype html>') || text.includes('<html')) {
        bootstrap.Modal.getInstance(document.getElementById('enrollStudentModal')).hide();
        alert('Student enrolled successfully!');
        location.reload();
        return;
      }
      
      try {
        const data = JSON.parse(text);
        if (data.success) {
          bootstrap.Modal.getInstance(document.getElementById('enrollStudentModal')).hide();
          alert('Student enrolled successfully!');
          location.reload();
        } else {
          alert('Error: ' + (data.error || data.message || 'Failed to enroll student'));
        }
      } catch (e) {
        alert('Error: Unable to process server response');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Network Error: ' + error.message);
    });
  }
});

// Password toggle functionality for enrollment modal
document.addEventListener('click', function(e) {
  if (e.target.id === 'toggleEnrollPassword' || e.target.id === 'enrollEyeIcon') {
    const passwordInput = document.querySelector('#enrollStudentModal [name="password"]');
    const eyeIcon = document.getElementById('enrollEyeIcon');
    
    if (passwordInput && eyeIcon) {
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.className = 'fas fa-eye-slash';
      } else {
        passwordInput.type = 'password';
        eyeIcon.className = 'fas fa-eye';
      }
    }
  }
});

// Demo fill function for enrollment modal
function fillDemoData() {
  const form = document.getElementById('enrollStudentForm');
  if (!form) return;
  
  // Demo data arrays for randomization
  const firstNames = ['Juan', 'Maria', 'Jose', 'Ana', 'Carlos', 'Sofia', 'Miguel', 'Isabella', 'Luis', 'Carmen', 'Pedro', 'Lucia', 'Antonio', 'Elena', 'Francisco'];
  const middleNames = ['Santos', 'Cruz', 'Reyes', 'Garcia', 'Lopez', 'Martinez', 'Gonzalez', 'Rodriguez', 'Fernandez', 'Morales', 'Jimenez', 'Herrera', 'Medina', 'Castro', 'Ortiz'];
  const lastNames = ['Dela Cruz', 'Santos', 'Garcia', 'Reyes', 'Lopez', 'Martinez', 'Gonzalez', 'Rodriguez', 'Fernandez', 'Morales', 'Jimenez', 'Herrera', 'Medina', 'Castro', 'Ortiz'];
  const suffixes = ['', '', '', 'Jr.', 'Sr.', 'III', ''];
  const genders = ['Male', 'Female'];
  const gradeLevels = <?= json_encode(array_map('strval', grade_level_options())) ?>;
  const studentTypes = ['New Student', 'Transferee', 'Old Student'];
  const barangays = ['Poblacion', 'Tawala', 'Bolod', 'Danao', 'Tangnan', 'Libaong', 'Lourdes'];
  const religions = ['Catholic', 'Protestant', 'Iglesia ni Cristo', 'Baptist', 'Methodist', 'Born Again', 'Seventh-day Adventist'];
  const relationships = ['Mother', 'Father', 'Guardian', 'Aunt', 'Uncle', 'Grandmother', 'Grandfather'];
  const emailDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
  
  const getRandom = (arr) => arr[Math.floor(Math.random() * arr.length)];
  // Barangay text is generated per call (after getRandom exists) so every
  // demo entry differs.
  const getRandomBrgyFill = () => 'Purok ' + (Math.floor(Math.random() * 10) + 1) + ', Barangay ' + getRandom(barangays);
  const getRandomDemoLocation = () => {
    if (window.PSGC_DATA && window.LocSelect) {
      const provinces = Object.keys(window.PSGC_DATA);
      const province = getRandom(provinces);
      const cities = window.PSGC_DATA[province] || [];
      return {
        province: province,
        city: cities.length ? getRandom(cities) : '',
        barangay: getRandomBrgyFill()
      };
    }
    return { province: '', city: '', barangay: getRandomBrgyFill() };
  };
  const getRandomBirthDate = () => {
    const today = new Date();
    const age = Math.floor(Math.random() * 7) + 6;
    const birthYear = today.getFullYear() - age;
    const birthMonth = Math.floor(Math.random() * 12) + 1;
    const birthDay = Math.floor(Math.random() * 28) + 1;
    return `${birthYear}-${birthMonth.toString().padStart(2, '0')}-${birthDay.toString().padStart(2, '0')}`;
  };
  const getRandomPhone = () => {
    const prefixes = ['0917', '0918', '0919', '0920', '0921', '0922', '0923', '0924', '0925', '0926', '0927', '0928', '0929'];
    return getRandom(prefixes) + Math.floor(Math.random() * 10000000).toString().padStart(7, '0');
  };
  
  // Generate random data
  const firstName = getRandom(firstNames);
  const middleName = getRandom(middleNames);
  const lastName = getRandom(lastNames);
  const suffix = getRandom(suffixes);
  const gender = getRandom(genders);
  const gradeLevel = getRandom(gradeLevels);
  const studentType = getRandom(studentTypes);
  const birthDate = getRandomBirthDate();
  const birthPlace = getRandomDemoLocation();
  const religion = getRandom(religions);
  const contactNumber = getRandomPhone();
  const emergencyContactNumber = getRandomPhone();
  const relationship = getRandom(relationships);
  const randomLRN = '999' + Date.now().toString().slice(-9);
  const emailUsername = (firstName + lastName).toLowerCase().replace(/\s+/g, '');
  const email = emailUsername + Math.floor(Math.random() * 999) + '@' + getRandom(emailDomains);
  const addressPlace = getRandomDemoLocation();
  const emergencyContactName = getRandom(firstNames) + ' ' + getRandom(lastNames);
  
  // Fill form fields
  form.querySelector('[name="lrn"]').value = randomLRN;
  form.querySelector('[name="email"]').value = email;
  form.querySelector('[name="password"]').value = 'Demo123!';
  form.querySelector('[name="first_name"]').value = firstName;
  form.querySelector('[name="middle_name"]').value = middleName;
  form.querySelector('[name="last_name"]').value = lastName;
  form.querySelector('[name="suffix"]').value = suffix;
  form.querySelector('[name="gender"]').value = gender;
  form.querySelector('[name="date_of_birth"]').value = birthDate;
  if (window.LocSelect) {
    window.LocSelect.set('modal_place_of_birth', birthPlace);
    window.LocSelect.set('modal_address', addressPlace);
  }
  form.querySelector('[name="nationality"]').value = 'Filipino';
  form.querySelector('[name="religion"]').value = religion;
  form.querySelector('[name="contact_number"]').value = contactNumber;
  form.querySelector('[name="grade_level"]').value = gradeLevel;
  form.querySelector('[name="student_type"]').value = studentType;
  if (window.LocSelect) {
    window.LocSelect.refresh(form);
  }
  form.querySelector('[name="emergency_contact_name"]').value = emergencyContactName;
  form.querySelector('[name="emergency_contact_number"]').value = emergencyContactNumber;
  form.querySelector('[name="emergency_contact_relationship"]').value = relationship;
  // Keep the "Other" companion box in step with the value just assigned.
  toggleEnrollRelationshipOther();
}

// Archive student function
function archiveStudent(studentId, studentName) {
  showArchiveConfirmation(studentId, studentName);
}

// Restore student function
function restoreStudent(studentId, studentName) {
  showRestoreConfirmation(studentId, studentName);
}

// The enroll-modal relationship selector reveals its free-text box for "Other".
// While hidden that box is disabled so no stale value is ever submitted.
function toggleEnrollRelationshipOther() {
  const select = document.getElementById('enrollRelationship');
  const wrapper = document.getElementById('enrollRelationshipOtherWrap');
  const input = document.getElementById('enrollRelationshipOther');

  if (!select || !wrapper || !input) {
    return;
  }

  const isOther = select.value === <?= json_encode(emergency_contact_relationship_other_option()) ?>;

  wrapper.style.display = isOther ? 'block' : 'none';
  input.disabled = !isOther;
}

function getDashboardModalPortal() {
  return document.getElementById('dashboard-modal-portal') || document.body;
}

function createDashboardModal(id, title, bodyHtml, confirmButtonId, confirmButtonClass, confirmButtonText) {
  const existing = document.getElementById(id);
  if (existing) existing.remove();

  const modalHtml = `
  <div class="modal fade" id="${id}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">${title}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          ${bodyHtml}
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: darkgray; border-color: darkgray;">Cancel</button>
          <button type="button" class="btn ${confirmButtonClass}" id="${confirmButtonId}">${confirmButtonText}</button>
        </div>
      </div>
    </div>
  </div>`;

  getDashboardModalPortal().insertAdjacentHTML('beforeend', modalHtml);
  return document.getElementById(id);
}

function showArchiveConfirmation(studentId, studentName) {
  const modalEl = createDashboardModal(
    'archiveConfirmModal',
    'Confirm Action',
    `
      <div class="alert alert-warning border-0">
        <i class="bi bi-question-circle fs-2 mb-2 d-block text-center"></i>
        <p class="text-center mb-0">Are you sure you want to archive student "${studentName}"? They will be moved to archived students.</p>
      </div>
    `,
    'confirmArchiveBtn',
    'btn-primary',
    'Confirm'
  );

  const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true, focus: true });
  const confirmBtn = modalEl.querySelector('#confirmArchiveBtn');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', function () {
      confirmArchiveStudent(studentId);
    }, { once: true });
  }
  modal.show();
}

function confirmArchiveStudent(studentId) {
  const modal = bootstrap.Modal.getInstance(document.getElementById('archiveConfirmModal'));
  if (modal) modal.hide();
  
  fetch(`<?= base_url('admin/students/archive') ?>/${studentId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showArchiveAlert(data.message);
      setTimeout(() => location.reload(), 1500);
    } else {
      showArchiveAlert('Error: ' + data.error, 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showArchiveAlert('Failed to archive student', 'error');
  });
}

function showDeleteConfirmation(studentId, studentName) {
  const modalEl = createDashboardModal(
    'deleteConfirmModal',
    'Confirm Permanent Delete',
    `
      <div class="alert alert-danger border-0">
        <i class="bi bi-exclamation-triangle fs-2 mb-2 d-block text-center"></i>
        <p class="text-center mb-0">Are you sure you want to PERMANENTLY DELETE student "${studentName}"? This cannot be undone.</p>
      </div>
    `,
    'confirmDeleteBtn',
    'btn-danger',
    'Yes, Delete'
  );

  const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true, focus: true });
  const confirmBtn = modalEl.querySelector('#confirmDeleteBtn');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', function () {
      confirmDeleteStudent(studentId);
    }, { once: true });
  }
  modal.show();
}

function confirmDeleteStudent(studentId) {
  const modal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
  if (modal) modal.hide();

  const csrfName = '<?= csrf_token() ?>';
  const csrfHash = '<?= csrf_hash() ?>';

  fetch(`<?= base_url('admin/students/delete-permanently') ?>/${studentId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfHash
    },
    body: JSON.stringify({ [csrfName]: csrfHash })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showArchiveAlert('Student permanently deleted.');
      setTimeout(() => location.reload(), 1200);
    } else {
      showArchiveAlert('Error: ' + (data.error || 'Failed to delete student'), 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showArchiveAlert('Failed to delete student', 'error');
  });
}

function showRestoreConfirmation(studentId, studentName) {
  const modalEl = createDashboardModal(
    'restoreConfirmModal',
    'Confirm Restore',
    `
      <div class="alert alert-success border-0">
        <i class="bi bi-arrow-counterclockwise fs-2 mb-2 d-block text-center"></i>
        <p class="text-center mb-0">Are you sure you want to restore student "${studentName}" to active status?</p>
      </div>
    `,
    'confirmRestoreBtn',
    'btn-success',
    'Restore'
  );

  const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true, focus: true });
  const confirmBtn = modalEl.querySelector('#confirmRestoreBtn');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', function () {
      confirmRestoreStudent(studentId);
    }, { once: true });
  }
  modal.show();
}

function confirmRestoreStudent(studentId) {
  const modal = bootstrap.Modal.getInstance(document.getElementById('restoreConfirmModal'));
  if (modal) modal.hide();
  
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
      showArchiveAlert(data.message);
      setTimeout(() => location.reload(), 1500);
    } else {
      showArchiveAlert('Error: ' + (data.error || 'Failed to restore student'), 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showArchiveAlert('Failed to restore student', 'error');
  });
}

function showArchiveAlert(message, type = 'success') {
  const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
  const iconClass = type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle';
  
  const modalEl = createDashboardModal(
    'archiveAlertModal',
    'Alert',
    `
      <div class="${alertClass} border-0 mb-3">
        <i class="${iconClass} fs-2 mb-2"></i>
        <p class="mb-0">${message}</p>
      </div>
    `,
    'archiveAlertOkBtn',
    'btn-primary',
    'OK'
  );

  const okBtn = modalEl.querySelector('#archiveAlertOkBtn');
  if (okBtn) {
    okBtn.setAttribute('data-bs-dismiss', 'modal');
  }

  const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true, focus: true });
  modal.show();
}

</script>

<?= $this->endSection() ?>

<?= $this->section('portal_overlays') ?>
<!-- Emergency Contacts Modal — rendered OUTSIDE .main-content (via the layout's portal_overlays
     section) so it stacks above the fixed sidebar (z-index 1030) and sticky top bar (z-index 1045).
     Inside .main-content the modal is trapped in its z-index: 1 stacking context. -->
<div id="emergencyContactsModal" class="custom-modal-overlay" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="emergencyContactsModalLabel">
  <div class="custom-modal-container">
    <div class="custom-modal-header">
      <h3 class="custom-modal-title"><i class="bi bi-person-lines-fill me-2"></i>Emergency Contacts</h3>
      <button type="button" class="custom-modal-close" onclick="closeEmergencyContactsModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="custom-modal-body">
      <div class="mb-3">
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="text" id="emergencyContactSearch" class="form-control" placeholder="Search by LRN or student name..." onkeydown="if (event.key === 'Enter') { event.preventDefault(); filterEmergencyContacts(); }">
          <button type="button" class="btn btn-primary" onclick="filterEmergencyContacts()">
            <i class="bi bi-search me-1"></i>Search
          </button>
          <button type="button" class="btn btn-outline-secondary" onclick="clearEmergencyContactSearch()">
            <i class="bi bi-x-circle me-1"></i>Clear
          </button>
        </div>
      </div>
      <div id="emergencyContactsList">
        <div class="loading-spinner">
          <div class="spinner"></div>
          <p>Loading emergency contacts...</p>
        </div>
      </div>
      <div id="emergencyContactsFooter" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2" style="display: none;">
        <div class="d-flex align-items-center gap-2">
          <label class="small text-muted mb-0" for="emergencyContactsPerPage">Rows</label>
          <select id="emergencyContactsPerPage" class="form-select form-select-sm" style="width: auto;" onchange="changeEmergencyPerPage(this.value)" aria-label="Rows per page">
            <option value="10">10</option>
            <option value="25" selected>25</option>
            <option value="50">50</option>
          </select>
          <span class="text-muted small" id="emergencyContactsCount"></span>
        </div>
        <nav id="emergencyContactsPagination" aria-label="Emergency contacts pages"></nav>
      </div>
    </div>
  </div>
</div>

<script>
// Emergency modal: close on Escape (mirrors the Student Details modal behavior)
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    const emergencyModal = document.getElementById('emergencyContactsModal');
    if (emergencyModal && emergencyModal.style.display === 'flex') {
      closeEmergencyContactsModal();
    }
  }
});

// Emergency modal: close when clicking the dark backdrop (mirrors the Student Details modal behavior)
document.addEventListener('click', function (e) {
  const emergencyModal = document.getElementById('emergencyContactsModal');
  if (emergencyModal && e.target === emergencyModal) {
    closeEmergencyContactsModal();
  }
});
</script>
<?= $this->endSection() ?>

