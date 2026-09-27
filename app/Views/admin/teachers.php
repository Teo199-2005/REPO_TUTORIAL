<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<?php
  $teacherFilterValues = [
    'search'     => trim((string) ($search ?? '')),
    'assignment' => (string) ($assignment ?? ''),
    'status'     => trim((string) ($status ?? '')),
    'position'   => trim((string) ($position ?? '')),
    'department' => trim((string) ($department ?? '')),
    'grade'      => trim((string) ($grade ?? '')),
    'gender'     => trim((string) ($gender ?? '')),
    'religion'   => trim((string) ($religion ?? '')),
    'sort_by'    => (string) ($sortBy ?? 'name'),
    'sort_order' => (string) ($sortOrder ?? 'asc'),
  ];

  $teacherFilterNames = [
    'search', 'assignment', 'status', 'position', 'department',
    'grade', 'gender', 'religion', 'sort_by', 'sort_order',
  ];

  // Sort defaults are not "filters", so they are excluded from the counts and
  // from the reset list; the user changes them deliberately, not to narrow.
  $teacherNarrowNames = ['search', 'assignment', 'status', 'position', 'department', 'grade', 'gender', 'religion'];
  $teacherActive      = admin_filter_count_active($teacherFilterValues, $teacherNarrowNames, ['', 'all', 'name', 'asc']);

  // Export PDF carries every active filter through the query string, so the
  // printed list matches what is on screen (all matching rows).
  $pdfParams = admin_filter_query($teacherFilterValues, $teacherFilterNames, ['', 'all', 'name', 'asc']);
  $pdfUrl    = base_url('admin/teachers/export-pdf')
    . ($pdfParams !== [] ? '?' . http_build_query($pdfParams) : '');

  $teacherHeaderActions = '<a class="btn btn-outline-secondary" href="' . base_url('admin/dashboard') . '">'
    . '<i class="bi bi-arrow-left"></i> Back</a>'
    . '<a class="btn btn-outline-primary" href="' . esc($pdfUrl) . '" target="_blank" rel="noopener">'
    . '<i class="bi bi-file-earmark-pdf"></i> Export PDF</a>'
    . '<a class="btn btn-outline-warning" href="' . base_url('admin/teachers/pending') . '">'
    . '<i class="bi bi-person-plus"></i> Pending registrations'
    . (! empty($pendingRegistrationCount)
        ? ' <span class="badge bg-dark">' . (int) $pendingRegistrationCount . '</span>'
        : '')
    . '</a>'
    . '<a class="btn btn-primary admin-btn-primary" href="' . base_url('admin/teachers/create') . '">'
    . '<i class="bi bi-plus-circle"></i> Add new teacher</a>'
    // Global personnel-editing switch keeps its own id because the inline
    // script below drives it.
    . '<button type="button" id="personnelEditGlobalBtn" onclick="toggleGlobalPersonnelEdit()"'
    . ' class="btn ' . ($personnelEditGlobal ? 'btn-outline-success' : 'btn-outline-secondary') . '"'
    . ' title="Turn personnel record editing on or off for every teacher at once">'
    . '<i class="bi ' . ($personnelEditGlobal ? 'bi-unlock-fill' : 'bi-lock-fill') . '"></i> Personnel editing: '
    . ($personnelEditGlobal ? 'on' : 'off') . '</button>';

  echo view('admin/partials/page_header', ['pageHeader' => [
    'icon'     => 'bi-person-video3',
    'title'    => 'Manage Teachers',
    'subtitle' => 'Teaching staff, their assignments and their personnel records',
    'actions'  => $teacherHeaderActions,
  ]]);
?>

<div class="alert alert-info d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
  <div>
    <strong><i class="bi bi-link-45deg"></i> Teacher registration link</strong>
    <div class="small text-muted">
      Share this link with teachers so they can register themselves. New sign-ups appear under
      <em>Pending Registrations</em> for your approval before they can sign in.
    </div>
  </div>
  <div class="input-group input-group-sm teacher-registration-link-group">
    <input type="text" class="form-control" id="teacherRegistrationLink" value="<?= base_url('teacher/register') ?>" readonly onfocus="this.select()">
    <button class="btn btn-outline-primary" type="button" id="copyTeacherRegistrationLink">
      <i class="bi bi-clipboard"></i> Copy Link
    </button>
  </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?= session()->getFlashdata('success') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <?= session()->getFlashdata('error') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Search Form -->
<?php
  $teacherStatusChoiceList = [admin_filter_option('', 'All employment statuses')];
  foreach (($statusOptions ?? []) as $opt) {
    $teacherStatusChoiceList[] = admin_filter_option((string) $opt, ucfirst(str_replace('_', ' ', (string) $opt)));
  }

  $teacherPositionChoiceList = [admin_filter_option('', 'All positions')];
  foreach (($positionOptions ?? []) as $opt) {
    $teacherPositionChoiceList[] = admin_filter_option((string) $opt, (string) $opt);
  }

  $teacherDepartmentChoiceList = [admin_filter_option('', 'All departments')];
  foreach (($departmentOptions ?? []) as $opt) {
    $teacherDepartmentChoiceList[] = admin_filter_option((string) $opt, (string) $opt);
  }

  $teacherGradeChoiceList = [admin_filter_option('', 'All grade levels')];
  foreach (grade_level_options() as $g) {
    $teacherGradeChoiceList[] = admin_filter_option((string) $g, grade_level_label($g));
  }

  $teacherReligionChoiceList = [admin_filter_option('', 'All religions')];
  foreach (($religionOptions ?? []) as $opt) {
    $teacherReligionChoiceList[] = admin_filter_option((string) $opt, (string) $opt);
  }

  echo view('admin/partials/filter_bar', ['filterBar' => [
    'action'      => base_url('admin/teachers'),
    'id'          => 'teacherFilter',
    'label'       => 'Filter teachers',
    'resetUrl'    => base_url('admin/teachers'),
    'activeCount' => admin_filter_count_active(
      $teacherFilterValues,
      ['status', 'position', 'department', 'grade', 'gender', 'religion']
    )['total'],
    'totalCount'  => $teacherActive['total'],
    'primary'     => [
      [
        'name' => 'search', 'label' => 'Search', 'icon' => 'bi-search', 'type' => 'search',
        'value'       => admin_filter_value($teacherFilterValues, 'search'),
        'placeholder' => 'Name, Employee No., TIN, PRC or email',
      ],
      [
        'name' => 'assignment', 'label' => 'Advisory class', 'icon' => 'bi-diagram-2',
        'value'   => admin_filter_value($teacherFilterValues, 'assignment'),
        'options' => [
          admin_filter_option('', 'All teachers'),
          admin_filter_option('assigned', 'Has an advisory class'),
          admin_filter_option('unassigned', 'No advisory class'),
        ],
      ],
      [
        'name' => 'status', 'label' => 'Employment status', 'icon' => 'bi-activity',
        'value'   => admin_filter_value($teacherFilterValues, 'status'),
        'options' => $teacherStatusChoiceList,
      ],
      [
        'name' => 'sort_by', 'label' => 'Sort by', 'icon' => 'bi-sort-down',
        'value'   => admin_filter_value($teacherFilterValues, 'sort_by', 'name'),
        'options' => [
          admin_filter_option('name', 'Name'),
          admin_filter_option('age', 'Age'),
        ],
      ],
    ],
    'advanced'    => [
      [
        'name' => 'position', 'label' => 'Position', 'icon' => 'bi-briefcase',
        'value'   => admin_filter_value($teacherFilterValues, 'position'),
        'options' => $teacherPositionChoiceList,
      ],
      [
        'name' => 'department', 'label' => 'Department', 'icon' => 'bi-diagram-3',
        'value'   => admin_filter_value($teacherFilterValues, 'department'),
        'options' => $teacherDepartmentChoiceList,
      ],
      [
        'name' => 'grade', 'label' => 'Grade level', 'icon' => 'bi-mortarboard',
        'value'   => admin_filter_value($teacherFilterValues, 'grade'),
        'options' => $teacherGradeChoiceList,
      ],
      [
        'name' => 'gender', 'label' => 'Sex', 'icon' => 'bi-gender-ambiguous',
        'value'   => admin_filter_value($teacherFilterValues, 'gender'),
        'options' => [
          admin_filter_option('', 'All'),
          admin_filter_option('Male', 'Male'),
          admin_filter_option('Female', 'Female'),
        ],
      ],
      [
        'name' => 'religion', 'label' => 'Religion', 'icon' => 'bi-book',
        'value'   => admin_filter_value($teacherFilterValues, 'religion'),
        'options' => $teacherReligionChoiceList,
      ],
      [
        'name' => 'sort_order', 'label' => 'Order', 'icon' => 'bi-arrow-down-up',
        'value'   => admin_filter_value($teacherFilterValues, 'sort_order', 'asc'),
        'options' => [
          admin_filter_option('asc', 'Ascending (A to Z)'),
          admin_filter_option('desc', 'Descending (Z to A)'),
        ],
      ],
    ],
  ]]);
?>

<div class="card">
  <div class="card-body p-0">
    <?php if (!empty($teachers)): ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 admin-table" data-js-paged="1">
          <thead>
            <tr>
              <th><i class="bi bi-hash me-1 text-muted"></i>Emp. No.</th>
              <th><i class="bi bi-person me-1 text-muted"></i>Name</th>
              <th><i class="bi bi-calendar3 me-1 text-muted"></i>Age</th>
              <th><i class="bi bi-envelope me-1 text-muted"></i>Email</th>
              <th><i class="bi bi-diagram-3 me-1 text-muted"></i>Department</th>
              <th><i class="bi bi-briefcase me-1 text-muted"></i>Position</th>
              <th><i class="bi bi-activity me-1 text-muted"></i>Status</th>
              <th class="text-end"><i class="bi bi-gear me-1 text-muted"></i>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($teachers as $teacher): ?>
              <tr>
                <td><?= esc($teacher['government_employee_no'] ?? $teacher['employee_id'] ?? '—') ?></td>
                <td><?= esc($teacher['first_name'] . ' ' . $teacher['last_name']) ?></td>
                <td><?= esc((string) ($teacher['age'] ?? '—')) ?></td>
                <td><?= esc($teacher['email']) ?></td>
                <td><?= esc($teacher['department'] ?? '—') ?></td>
                <td><?= esc($teacher['position'] ?? '—') ?></td>
                <td>
                  <?php
                  $statusClass = match($teacher['employment_status'] ?? 'active') {
                    'active' => 'bg-success',
                    'inactive' => 'bg-danger',
                    'on_leave' => 'bg-warning',
                    default => 'bg-secondary'
                  };
                  ?>
                  <span class="badge <?= $statusClass ?>">
                    <?= ucfirst(str_replace('_', ' ', $teacher['employment_status'] ?? 'active')) ?>
                  </span>
                </td>
                <td class="text-end">
                  <div class="btn-group" role="group">
                    <?php if (!empty($teacher['section_name'])): ?>
                      <button class="btn btn-sm btn-outline-success" onclick="viewAdvisoryClass(<?= $teacher['id'] ?>, '<?= esc($teacher['section_name']) ?>', <?= $teacher['grade_level'] ?>)" title="Advisory Class">
                        <i class="bi bi-people"></i>
                      </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewTeacher(<?= $teacher['id'] ?>)" title="View Details">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-warning" onclick="openEditTeacherModal(<?= $teacher['id'] ?>)" title="Edit Teacher">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTeacher(<?= $teacher['id'] ?>, '<?= esc($teacher['first_name'] . ' ' . $teacher['last_name']) ?>')" title="Delete Teacher">
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
        'icon'   => 'bi-person-x',
        'title'  => $teacherActive['total'] > 0
          ? 'No teachers match these filters'
          : 'No teachers yet',
        'hint'   => $teacherActive['total'] > 0
          ? 'Try clearing a filter, or widening the position and department.'
          : 'Add the first teacher to start assigning advisory classes.',
        'action' => $teacherActive['total'] > 0
          ? '<a class="btn btn-outline-secondary" href="' . base_url('admin/teachers') . '">'
            . '<i class="bi bi-arrow-counterclockwise"></i> Reset filters</a>'
          : '<a class="btn btn-primary admin-btn-primary" href="' . base_url('admin/teachers/create') . '">'
            . '<i class="bi bi-plus-circle"></i> Add first teacher</a>',
      ]]) ?>
    <?php endif; ?>
  </div>
</div>





<style>
/* Teacher sections cell styling */
.teacher-sections-cell {
  max-width: 300px;
}

.section-badge {
  display: inline-block !important;
  margin-right: 5px;
  margin-bottom: 3px;
  padding: 3px 8px;
  font-size: 0.8125rem;
  white-space: nowrap;
}

/* Teacher modal uses Bootstrap defaults - no custom styles needed */

/* Teacher Info Styles */
.teacher-info-section {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  border: var(--hairline);
}

.teacher-info-title {
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

.teacher-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.teacher-info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.teacher-info-label {
  font-size: 0.875rem;
  font-weight: 700;
  color: #6b7280;
  text-transform: uppercase;
}

.teacher-info-value {
  font-size: 1rem;
  color: #111827;
  font-weight: 400;
}

.status-badge {
  display: inline-flex;
  padding: 0.375rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 700;
  text-transform: uppercase;
}

.status-active {
  background-color: #d1fae5;
  color: #065f46;
}
</style>

<script>
// Store teacher data for fallback
const teachersData = <?= json_encode($teachers ?? []) ?>;

// Copy the public teacher registration link to the clipboard
(function () {
  const copyBtn = document.getElementById('copyTeacherRegistrationLink');
  const linkInput = document.getElementById('teacherRegistrationLink');

  if (!copyBtn || !linkInput) {
    return;
  }

  copyBtn.addEventListener('click', function () {
    const link = linkInput.value;

    const markCopied = function () {
      const original = copyBtn.innerHTML;
      copyBtn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
      copyBtn.classList.remove('btn-outline-primary');
      copyBtn.classList.add('btn-success');

      setTimeout(function () {
        copyBtn.innerHTML = original;
        copyBtn.classList.remove('btn-success');
        copyBtn.classList.add('btn-outline-primary');
      }, 2000);
    };

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(link).then(markCopied).catch(function () {
        linkInput.select();
        document.execCommand('copy');
        markCopied();
      });
      return;
    }

    linkInput.select();
    document.execCommand('copy');
    markCopied();
  });
})();

// View teacher details in full page
function viewTeacher(teacherId) {
  window.location.href = `<?= base_url('admin/teachers/view/') ?>${teacherId}`;
}

function buildTeacherDetailsModal(teacherId) {
  const existing = document.getElementById('customTeacherModal');
  if (existing) existing.remove();

  const html = `
  <div class="modal fade" id="customTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Teacher Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="customTeacherDetails">
          <div class="text-center">
            <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
            <p class="mt-2">Loading teacher details...</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
      </div>
    </div></div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  return document.getElementById('customTeacherModal');
}

function openTeacherModal(teacherId) {
  const modalEl = buildTeacherDetailsModal(teacherId);
  const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true, focus: true });
  modal.show();
  
  // Load teacher data
  fetch(`<?= base_url('admin/teachers') ?>/details/${teacherId}`)
    .then(response => response.text())
    .then(html => {
      document.getElementById('customTeacherDetails').innerHTML = html;
    })
    .catch(error => {
      console.error('Error:', error);
      // Fallback to local teacher data
      const teacher = teachersData.find(t => t.id == teacherId);
      if (teacher) {
        showBasicTeacherDetails(teacher, document.getElementById('customTeacherDetails'));
      } else {
        document.getElementById('customTeacherDetails').innerHTML = '<div class="alert alert-danger">Failed to load teacher data</div>';
      }
    });
}

// Fallback function to show basic teacher details
function showBasicTeacherDetails(teacher, container) {
  const html = `
    <div class="teacher-info-section">
      <div class="teacher-info-title">
        <i class="bi bi-person"></i>
        Personal Information
      </div>
      <div class="teacher-info-grid">
        <div class="teacher-info-item">
          <div class="teacher-info-label">Full Name</div>
          <div class="teacher-info-value">${teacher.first_name} ${teacher.last_name}</div>
        </div>
        <div class="teacher-info-item">
          <div class="teacher-info-label">PRC License Number</div>
          <div class="teacher-info-value">${teacher.license_number || 'N/A'}</div>
        </div>
        <div class="teacher-info-item">
          <div class="teacher-info-label">Email</div>
          <div class="teacher-info-value">${teacher.email || 'N/A'}</div>
        </div>
        <div class="teacher-info-item">
          <div class="teacher-info-label">Department</div>
          <div class="teacher-info-value">${teacher.department || 'N/A'}</div>
        </div>
        <div class="teacher-info-item">
          <div class="teacher-info-label">Position</div>
          <div class="teacher-info-value">${teacher.position || 'N/A'}</div>
        </div>
        <div class="teacher-info-item">
          <div class="teacher-info-label">Status</div>
          <div class="teacher-info-value">
            <span class="status-badge status-${teacher.employment_status || 'active'}">
              ${(teacher.employment_status || 'active').replace('_', ' ').toUpperCase()}
            </span>
          </div>
        </div>
      </div>
    </div>
  `;
  container.innerHTML = html;
}

function buildEditTeacherModal(teacherId) {
  const existing = document.getElementById('editTeacherModal');
  if (existing) existing.remove();

  const html = `
  <div class="modal fade" id="editTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-xl"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Teacher</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="editTeacherForm" method="post" action="<?= base_url('admin/teachers/update') ?>/${teacherId}">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="POST">
        <div class="modal-body">
          <div id="editTeacherContent">
            <div class="text-center">
              <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
              <p class="mt-2">Loading teacher details...</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Update Teacher</button>
        </div>
      </form>
    </div></div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  return document.getElementById('editTeacherModal');
}

function openEditTeacherModal(teacherId) {
  if (!teacherId || teacherId <= 0) {
    alert('Invalid teacher ID');
    return;
  }
  
  const modalEl = buildEditTeacherModal(teacherId);
  const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true, focus: true });
  modal.show();
  
  // Load teacher data
  fetch(`<?= base_url('admin/teachers/edit-form') ?>/${teacherId}`, {
    method: 'GET',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'text/html,application/json'
    }
  })
    .then(response => {
      if (!response.ok) {
        if (response.status === 404) {
          throw new Error('Teacher not found');
        } else if (response.status === 403) {
          throw new Error('Access denied');
        } else {
          throw new Error(`Server error: ${response.status}`);
        }
      }
      return response.text();
    })
    .then(html => {
      if (html.trim().startsWith('{')) {
        // Response is JSON (error)
        const errorData = JSON.parse(html);
        throw new Error(errorData.error || 'Failed to load teacher data');
      }
      const content = document.getElementById('editTeacherContent');
      content.innerHTML = html;
      // The personnel partial ships its own <script>, but <script> tags inside
      // HTML assigned with innerHTML never execute — re-run the shared input
      // filters (TIN/PhilSys/PRC masks, name/phone restrictions) explicitly.
      if (typeof window.initTeacherPersonnelFields === 'function') {
        window.initTeacherPersonnelFields(content);
      }
      // Inline <script> execution aside, PhoneInput binds on DOMContentLoaded
      // only — re-enhance the injected contact-number field explicitly.
      if (window.PhoneInput) {
        window.PhoneInput.enhanceAll(content);
      }
    })
    .catch(error => {
      console.error('Error loading teacher data:', error);
      const errorMessage = error.message || 'Failed to load teacher data';
      document.getElementById('editTeacherContent').innerHTML = 
        `<div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle me-2"></i>
          ${errorMessage}
          <br><small class="mt-2 d-block">Please try again or contact support if the problem persists.</small>
        </div>`;
    });
}



// Handle edit form submission
document.addEventListener('submit', function(e) {
  if (e.target.id === 'editTeacherForm') {
    e.preventDefault();
    
    // Remove existing error messages
    const existingErrors = document.querySelectorAll('#editTeacherContent .alert-danger, #editTeacherContent .alert-success');
    existingErrors.forEach(error => error.remove());
    
    // Basic client-side validation
    const form = e.target;
    const firstName = form.querySelector('#first_name')?.value?.trim();
    const lastName = form.querySelector('#last_name')?.value?.trim();
    const email = form.querySelector('#email')?.value?.trim();
    const gender = form.querySelector('#gender')?.value;
    const birthMonth = form.querySelector('#birth_month')?.value;
    const birthDay = form.querySelector('#birth_day')?.value;
    const birthYear = form.querySelector('#birth_year')?.value;
    const employmentStatus = form.querySelector('#employment_status')?.value;
    const tin = form.querySelector('#tin')?.value?.trim();
    const philsys = form.querySelector('#philsys_number')?.value?.trim();
    const license = form.querySelector('#license_number')?.value?.trim();
    const employeeNo = form.querySelector('#government_employee_no')?.value?.trim();
    
    const errors = [];
    if (!firstName) errors.push('First name is required');
    else if (!/^[\p{L}\p{M}\s.'-]+$/u.test(firstName)) errors.push('First name may only contain letters, spaces, periods, apostrophes and hyphens');
    if (!lastName) errors.push('Last name is required');
    else if (!/^[\p{L}\p{M}\s.'-]+$/u.test(lastName)) errors.push('Last name may only contain letters, spaces, periods, apostrophes and hyphens');
    if (!email) errors.push('Email is required');
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Please enter a valid email address');
    if (!gender) errors.push('Sex is required');
    if (!birthMonth || !birthDay || !birthYear) errors.push('Complete date of birth (month, day, year) is required');
    if (!employmentStatus) errors.push('Employment status is required');
    // These checks mirror app/Helpers/teacher_form_helper.php exactly so the two
    // layers never disagree (e.g. legacy 9-digit TINs are accepted by the server
    // and normalised on save, so they must not be blocked here).
    if (tin && !/^\d{3}-?\d{3}-?\d{3}$/.test(tin)) errors.push('TIN must be in format 999-999-999');
    if (license && !/^\d{7}$/.test(license)) errors.push('PRC License Number must be exactly 7 digits');
    if (philsys && !/^\d{12}$/.test(philsys)) errors.push('PhilSys number must be exactly 12 digits');
    if (employeeNo && !/^\d{1,20}$/.test(employeeNo)) errors.push('Employee No. must contain digits only');
    
    if (errors.length > 0) {
      const errorHtml = `<div class="alert alert-danger"><ul class="mb-0">${errors.map(error => `<li>${error}</li>`).join('')}</ul></div>`;
      document.getElementById('editTeacherContent').insertAdjacentHTML('afterbegin', errorHtml);
      return;
    }
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
    submitBtn.disabled = true;
    
    const formData = new FormData(e.target);
    
    fetch(e.target.action, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    .then(response => {
      console.log('Response status:', response.status);

      if (!response.ok) {
        return response.text().then(text => {
          // The server sends structured JSON for validation failures (HTTP 422)
          // and auth/CSRF failures. Pass it to the renderer below so the user
          // sees the real field errors instead of a fake "network error".
          let payload = null;
          try { payload = JSON.parse(text); } catch (err) { /* not JSON */ }
          if (payload && typeof payload === 'object' && payload.success === false) {
            return payload;
          }
          console.error('Error response body:', text);
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        });
      }
      
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        return response.json();
      } else {
        return response.text().then(text => {
          console.error('Non-JSON response:', text);
          throw new Error('Server returned non-JSON response');
        });
      }
    })
    .then(data => {
      if (data.success) {
        // Show success message briefly
        const successHtml = '<div class="alert alert-success">Teacher updated successfully!</div>';
        document.getElementById('editTeacherContent').insertAdjacentHTML('afterbegin', successHtml);
        
        setTimeout(() => {
          bootstrap.Modal.getInstance(document.getElementById('editTeacherModal')).hide();
          location.reload();
        }, 1000);
      } else {
        // Show validation errors
        const prettyField = (key) => String(key).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        const prettyMsg = (msg) => String(msg).replace(/the ([a-z_]+) field/gi, (m, f) => 'The ' + prettyField(f) + ' field');
        let errorHtml = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><strong>Please fix the following:</strong><ul class="mb-0 mt-1">';
        if (data.errors && typeof data.errors === 'object') {
          for (let field in data.errors) {
            if (Array.isArray(data.errors[field])) {
              data.errors[field].forEach(error => {
                errorHtml += `<li>${prettyMsg(error)}</li>`;
              });
            } else {
              errorHtml += `<li>${prettyMsg(data.errors[field])}</li>`;
            }
          }
          errorHtml += '</ul>';
        } else if (data.error) {
          errorHtml += `</ul>${prettyMsg(data.error)}`;
        } else {
          errorHtml += '</ul>Failed to update teacher. Please check your input and try again.';
        }
        errorHtml += '</div>';
        document.getElementById('editTeacherContent').insertAdjacentHTML('afterbegin', errorHtml);
      }
    })
    .catch(error => {
      console.error('Fetch error:', error);
      let errorMessage = 'Network error occurred. Please check your connection and try again.';
      
      if (error.message.includes('HTTP 404')) {
        errorMessage = 'Update endpoint not found. Please contact support.';
      } else if (error.message.includes('HTTP 403')) {
        errorMessage = 'Access denied. Please refresh the page and try again.';
      } else if (error.message.includes('HTTP 500')) {
        errorMessage = 'Server error occurred. Please try again later.';
      } else if (error.message.includes('Failed to fetch')) {
        errorMessage = 'Connection failed. Please check your internet connection.';
      }
      
      const errorHtml = `<div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i>
        ${errorMessage}
        <br><small class="mt-2 d-block text-muted">Error details: ${error.message}</small>
      </div>`;
      document.getElementById('editTeacherContent').insertAdjacentHTML('afterbegin', errorHtml);
    })
    .finally(() => {
      // Reset button state
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    });
  }
});

// Manage teacher schedule
function manageSchedule(teacherId, teacherName) {
  window.location.href = `<?= base_url('admin/teachers/schedule/') ?>${teacherId}`;
}

// Toggle subjects display
function toggleSubjects(event, teacherId) {
  event.preventDefault();
  const subjectsList = document.getElementById('subjects-' + teacherId);
  const button = event.currentTarget;
  const icon = button.querySelector('i');
  
  if (subjectsList.style.display === 'none') {
    subjectsList.style.display = 'block';
    icon.className = 'bi bi-chevron-up';
  } else {
    subjectsList.style.display = 'none';
    icon.className = 'bi bi-chevron-down';
  }
}

// View advisory class function
function viewAdvisoryClass(teacherId, sectionName, gradeLevel) {
  const modalHtml = `
  <div class="modal fade" id="advisoryClassModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-book me-2"></i>Teacher Classes</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="text-center">
            <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
            <p class="mt-2">Loading class information...</p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal" style="background-color: #6c757d; color: white;">Close</button>
        </div>
      </div>
    </div>
  </div>`;
  
  const existing = document.getElementById('advisoryClassModal');
  if (existing) existing.remove();
  
  document.body.insertAdjacentHTML('beforeend', modalHtml);
  const modal = new bootstrap.Modal(document.getElementById('advisoryClassModal'));
  modal.show();
  
  // Fetch teacher's classes
  const teacher = teachersData.find(t => t.id == teacherId);
  if (!teacher) {
    document.querySelector('#advisoryClassModal .modal-body').innerHTML = 
      '<div class="alert alert-danger">Teacher not found</div>';
    return;
  }
  
  let html = '<div class="p-3">';
  
  // Advisory Section
  if (teacher.section_name) {
    html += `
      <div class="mb-4">
        <h6 class="fw-semibold text-dark mb-3"><i class="bi bi-star-fill me-2 dash-icon-inline"></i>Advisory Class</h6>
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">${teacher.section_name}</h5>
            <p class="card-text"><strong>Grade Level:</strong> ${teacher.grade_level}</p>
          </div>
        </div>
      </div>
    `;
  }
  
  // Subject Classes
  if (teacher.teaching_sections && teacher.teaching_sections.length > 0) {
    html += `
      <div>
        <h6 class="text-info mb-3"><i class="bi bi-book me-2"></i>Subject Classes</h6>
        <div class="row g-3">
    `;
    
    teacher.teaching_sections.forEach(section => {
      html += `
        <div class="col-md-6">
          <div class="card">
            <div class="card-body">
              <h6 class="card-title">${section.section_name}</h6>
              <p class="card-text mb-1"><strong>Grade:</strong> ${section.grade_level}</p>
              <p class="card-text mb-0"><strong>Subject:</strong> ${section.subject_name}</p>
            </div>
          </div>
        </div>
      `;
    });
    
    html += '</div></div>';
  }
  
  if (!teacher.section_name && (!teacher.teaching_sections || teacher.teaching_sections.length === 0)) {
    html += '<div class="alert alert-info">No classes assigned to this teacher</div>';
  }
  
  html += '</div>';
  
  document.querySelector('#advisoryClassModal .modal-body').innerHTML = html;
}

// Delete teacher function
function deleteTeacher(teacherId, teacherName) {
  showDeleteConfirmation(teacherId, teacherName);
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

function showDeleteConfirmation(teacherId, teacherName) {
  const modalEl = createDashboardModal(
    'deleteConfirmModal',
    'Confirm Action',
    `
      <div class="alert alert-warning border-0">
        <i class="bi bi-question-circle fs-2 mb-2 d-block text-center"></i>
        <p class="text-center mb-0">Are you sure you want to delete teacher "${teacherName}"? This action cannot be undone.</p>
      </div>
    `,
    'confirmDeleteBtn',
    'btn-primary',
    'Confirm'
  );

  const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true, focus: true });
  const confirmBtn = modalEl.querySelector('#confirmDeleteBtn');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', function () {
      confirmDeleteTeacher(teacherId);
    }, { once: true });
  }
  modal.show();
}

function toggleGlobalPersonnelEdit() {
  const enabled = <?= $personnelEditGlobal ? 'true' : 'false' ?>;
  const modalEl = createDashboardModal(
    'personnelEditGlobalModal',
    'Personnel Record Editing',
    `
      <div class="alert <?= $personnelEditGlobal ? 'alert-danger' : 'alert-success' ?> border-0">
        <i class="bi <?= $personnelEditGlobal ? 'bi-lock-fill' : 'bi-unlock-fill' ?> fs-2 mb-2 d-block text-center"></i>
        <p class="text-center mb-1">
          Are you sure you want to <strong><?= $personnelEditGlobal ? 'DISABLE' : 'ENABLE' ?></strong> personnel record
          editing for <strong>ALL teachers</strong>?
        </p>
        <p class="text-center text-muted small mb-0">
          <?= $personnelEditGlobal
              ? 'Teachers will no longer be able to edit their own Personnel Record until you enable it again.'
              : 'Every teacher will be able to edit their own Personnel Record from their profile.' ?>
        </p>
      </div>
    `,
    'confirmPersonnelEditGlobalBtn',
    enabled ? 'btn-danger' : 'btn-success',
    enabled ? 'Disable for All' : 'Enable for All'
  );

  const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true, focus: true });
  const confirmBtn = modalEl.querySelector('#confirmPersonnelEditGlobalBtn');
  confirmBtn.addEventListener('click', function () {
    const original = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    fetch(`<?= base_url('admin/teachers/toggle-personnel-edit-global') ?>`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
      }
    })
    .then(async response => {
      let data = null;
      try { data = await response.json(); } catch (e) { /* non-JSON */ }
      if (response.ok && data && data.success) {
        modal.hide();
        showDeleteAlert(data.message);
        setTimeout(() => window.location.reload(), 1200);
      } else {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = original;
        showDeleteAlert((data && data.error) || 'Unable to toggle personnel record editing.');
      }
    })
    .catch(() => {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = original;
      showDeleteAlert('Unable to reach the server. Please try again.');
    });
  }, { once: true });
  modal.show();
}

function confirmDeleteTeacher(teacherId) {
  const modal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
  if (modal) modal.hide();
  
  fetch(`<?= base_url('admin/teachers/delete') ?>/${teacherId}`, {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
    }
  })
  .then(async response => {
    let data = null;
    try { data = await response.json(); } catch (e) { /* non-JSON (e.g. security error page) */ }
    if (response.ok && data && data.success) {
      showDeleteAlert(data.message);
      setTimeout(() => location.reload(), 1500);
      return;
    }
    // The delete did NOT happen - tell the truth and refresh so the page
    // gets a fresh CSRF token and the list reflects the real database.
    const reason = (data && (data.error || data.message))
      || (response.status === 403 ? 'Your session expired. The page has been refreshed - please try again.'
        : 'Delete failed (HTTP ' + response.status + '). The page has been refreshed - please try again.');
    showDeleteAlert(reason, 'error');
    setTimeout(() => location.reload(), 2500);
  })
  .catch(error => {
    console.error('Error:', error);
    showDeleteAlert('Failed to reach the server. The page has been refreshed - please try again.', 'error');
    setTimeout(() => location.reload(), 2500);
  });
}

function showDeleteAlert(message, type = 'success') {
  const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
  const iconClass = type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle';
  
  const modalEl = createDashboardModal(
    'deleteAlertModal',
    'Alert',
    `
      <div class="${alertClass} border-0 mb-3">
        <i class="${iconClass} fs-2 mb-2"></i>
        <p class="mb-0">${message}</p>
      </div>
    `,
    'deleteAlertOkBtn',
    'btn-primary',
    'OK'
  );

  const okBtn = modalEl.querySelector('#deleteAlertOkBtn');
  if (okBtn) {
    okBtn.setAttribute('data-bs-dismiss', 'modal');
  }

  const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true, focus: true });
  modal.show();
}
</script>

<?= view('admin/partials/teacher_personnel_scripts') ?>

<?= $this->endSection() ?>
