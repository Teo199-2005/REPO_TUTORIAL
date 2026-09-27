<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<div class="teacher-dashboard-home">
<!-- Header Section -->
<div class="dashboard-header d-flex align-items-center justify-content-between mb-4">
  <div class="d-flex align-items-center gap-3">
    <div class="dash-page-icon" aria-hidden="true"><i class="bi bi-person-video3"></i></div>
    <div>
      <h2 class="mb-0" style="font-size:1.25rem;">Teacher Dashboard</h2>
      <small class="text-muted">
        <?php if (isset($selectedSection)): ?>
          <?= esc($selectedSection['name']) ?> - <?= esc(grade_level_label((int) $selectedSection['grade'])) ?> (<?= esc($selectedSection['type']) ?>)
        <?php else: ?>
          Class management and student progress
        <?php endif; ?>
      </small>
    </div>
  </div>
  <div class="btn-group flex-wrap">
    <a href="<?= base_url('teacher/grades') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pen me-2"></i>Enter Grades</a>
    <a href="<?= base_url('teacher/students') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-people me-2"></i>My Students</a>
    <a href="<?= base_url('teacher/schedule') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar-week me-2"></i>My Schedule</a>
    <a href="<?= base_url('teacher/announcements') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-megaphone me-2"></i>Announcements</a>
    <a href="<?= base_url('teacher/analytics') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-graph-up me-2"></i>Analytics</a>
    <?php if (!empty($hasSnedSection)): ?>
      <a href="<?= base_url('teacher/sned') ?>" class="btn btn-sm btn-primary" style="background: var(--color-primary); border-color: var(--color-primary);"><i class="bi bi-universal-access me-2"></i>SNED</a>
    <?php endif; ?>
  </div>
</div>

<?php
  $statStudents = (int)($totalStudents ?? 0);
  $statSubjects = (int)($totalSubjects ?? 0);
  $statSections = is_array($availableSections ?? null) ? count($availableSections) : 0;
  $statTerm = (int)($currentTerm ?? 1);
?>

<!-- Quick Stats -->
<div class="teacher-stats-grid">
  <div class="teacher-stat-col">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="dash-icon-tile dash-icon-tile--slate" aria-hidden="true">
          <i class="bi bi-people-fill"></i>
        </div>
        <div class="flex-grow-1 min-width-0">
          <div class="text-muted small">Students</div>
          <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;"><?= number_format($statStudents) ?></div>
        </div>
        <a class="btn btn-sm btn-outline-primary flex-shrink-0" href="<?= base_url('teacher/students') ?>">View</a>
      </div>
    </div>
  </div>

  <div class="teacher-stat-col">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="dash-icon-tile dash-icon-tile--emerald" aria-hidden="true">
          <i class="bi bi-journal-text"></i>
        </div>
        <div class="flex-grow-1 min-width-0">
          <div class="text-muted small">Subjects</div>
          <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;"><?= number_format($statSubjects) ?></div>
        </div>
        <a class="btn btn-sm btn-outline-success flex-shrink-0" href="<?= base_url('teacher/grades') ?>">Grades</a>
      </div>
    </div>
  </div>

  <div class="teacher-stat-col">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="dash-icon-tile dash-icon-tile--amber" aria-hidden="true">
          <i class="bi bi-grid-3x3-gap-fill"></i>
        </div>
        <div class="flex-grow-1 min-width-0">
          <div class="text-muted small">Sections</div>
          <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;"><?= number_format($statSections) ?></div>
        </div>
        <a class="btn btn-sm btn-outline-warning flex-shrink-0" href="<?= base_url('teacher/sections') ?>">Open</a>
      </div>
    </div>
  </div>

  <div class="teacher-stat-col">
    <div class="card h-100 border-0 shadow-sm">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="dash-icon-tile dash-icon-tile--violet" aria-hidden="true">
          <i class="bi bi-calendar2-week"></i>
        </div>
        <div class="flex-grow-1 min-width-0">
          <div class="text-muted small">Term</div>
          <div class="fw-bold" style="font-size:1.15rem; line-height:1.2;">T<?= esc($statTerm) ?></div>
          <div class="text-muted small"><?= esc(function_exists('get_current_school_year') ? get_current_school_year() : '') ?></div>
        </div>
        <a class="btn btn-sm btn-outline-secondary flex-shrink-0" href="<?= base_url('teacher/attendance') ?>">Attendance</a>
      </div>
    </div>
  </div>
</div>

<!-- Bottom Row -->
<div class="teacher-bottom-row">
  <!-- Recent Grades Entered -->
  <div class="teacher-bottom-col-grades">
    <div class="card h-100">
      <div class="card-header py-2">
        <h6 class="card-title mb-0 fw-semibold">Recent Grades Entered</h6>
      </div>
      <div class="card-body py-2" style="max-height: 280px; overflow-y: auto;">
        <?php if (!empty($recentGrades)): ?>
          <div class="list-group list-group-flush">
            <?php foreach (array_slice($recentGrades, 0, 5) as $grade): ?>
              <div class="list-group-item px-0 py-2 border-0 border-bottom">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="flex-grow-1">
                    <div class="fw-semibold text-dark mb-1" style="font-size: 0.85rem;">
                      <?= esc($grade['first_name'] . ' ' . $grade['last_name']) ?>
                    </div>
                    <div class="text-muted small"><?= esc($grade['subject_name']) ?></div>
                  </div>
                  <span class="badge bg-<?= (float)$grade['grade'] >= 85 ? 'success' : ((float)$grade['grade'] >= 75 ? 'warning' : 'danger') ?> ms-2 small" style="color: white !important;">
                    <?= number_format((float)$grade['grade'], 1) ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-4">
            <i class="bi bi-journal-x text-muted fs-1 mb-2"></i>
            <p class="text-muted mb-2">No grades entered yet.</p>
            <p class="small text-muted mb-3">Start by navigating to <strong>Enter Grades</strong> to input student performance data.</p>
            <a href="<?= base_url('teacher/grades') ?>" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-pen me-1"></i> Enter Grades
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Featured -->
  <div class="teacher-bottom-col-featured">
    <div class="card h-100">
      <div class="card-header py-2">
        <div class="d-flex justify-content-between align-items-center">
          <h6 class="card-title mb-0 fw-semibold">Featured</h6>
          <a href="<?= base_url('teacher/students') ?>" class="btn btn-sm btn-outline-primary px-2 py-1">
            <i class="bi bi-eye"></i> View All
          </a>
        </div>
      </div>
      <div class="card-body py-2">
        <div class="featured-poster">
          <?php if (! empty($featuredPosterTeacherUrl)): ?>
            <img
              src="<?= esc($featuredPosterTeacherUrl) ?>"
              alt="Featured poster"
              class="featured-poster__img"
              onerror="this.style.display='none';"
            >
          <?php endif; ?>
          <div style="position:absolute; left:10px; bottom:8px; color:white;">
            <div style="font-weight: 700; font-size:1.05rem; line-height:1;">Featured</div>
            <div style="font-size:0.82rem; opacity:0.95;">Teacher Dashboard</div>
            <?php if (empty($featuredPosterTeacherUrl)): ?>
              <div style="font-size:0.78rem; opacity:0.9;">(No poster uploaded yet)</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

<style>
/* Teacher Dashboard Styles */
.teacher-dashboard-home {
  width: 100%;
  max-width: 100%;
  min-width: 0;
}

.teacher-stat-col .card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.teacher-stat-col .card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.featured-poster {
  position: relative;
  width: 100%;
  min-height: 200px;
  border-radius: 8px;
  overflow: hidden;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.featured-poster__img {
  width: 100%;
  height: auto;
  display: block;
  object-fit: cover;
}

@media (max-width: 767.98px) {
  .dash-icon-tile {
    width: 40px;
    height: 40px;
    font-size: 1.25rem;
  }
}
</style>

<?= $this->endSection() ?>
