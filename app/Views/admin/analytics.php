<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>
<!-- Prevent caching of analytics data -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<?php
    helper('school_year');
    $currentCalendarYear = (int) date('Y');
    $schoolYear = get_current_school_year();
    $yearParts = explode('-', (string) $schoolYear);
    $syEndYear = (count($yearParts) === 2 && is_numeric($yearParts[1]))
        ? (int) $yearParts[1]
        : $currentCalendarYear;
    $maxYear = max($currentCalendarYear, $syEndYear);
    $availableYears = $availableYears ?? [];
    if (empty($availableYears)) {
        for ($y = $maxYear - 2; $y <= $maxYear; $y++) {
            $availableYears[] = $y;
        }
    }
    $selectedYear = (int) ($selectedYear ?? $currentCalendarYear);
    if (! in_array($selectedYear, $availableYears, true)) {
        $selectedYear = $currentCalendarYear;
    }
    ?>
<?php
  // Header actions: year filter + period selector + export, kept together so
  // the controls that change the report sit in one predictable place.
  ob_start();
  ?>
  <form method="get" class="admin-filter-form analytics-year-filter" aria-label="Analytics year filter">
    <input type="hidden" name="period" value="<?= esc($period ?? 'all') ?>">
    <i class="bi bi-calendar3 text-muted" aria-hidden="true"></i>
    <span class="analytics-year-filter__label">Filter by year:</span>
    <select name="year" class="analytics-year-filter__select" aria-label="Year">
      <?php foreach ($availableYears as $yr): ?>
        <option value="<?= (int) $yr ?>" <?= ((int) $selectedYear === (int) $yr) ? 'selected' : '' ?>><?= (int) $yr ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <div class="filter-buttons analytics-filter" role="group" aria-label="Analytics period filter">
    <?php
      $analyticsYear = (int) ($selectedYear ?? date('Y'));
      $periods = [
          'week'  => ['bi-calendar-week', 'Week'],
          'month' => ['bi-calendar-month', 'Month'],
          'term'  => ['bi-journal-bookmark', 'Term ' . ($currentTerm ?? get_current_term())],
          'all'   => ['bi-infinity', 'All'],
      ];
    ?>
    <?php foreach ($periods as $key => [$icon, $text]): ?>
      <a href="?period=<?= esc($key) ?>&year=<?= $analyticsYear ?>"
         class="btn btn-sm <?= ($period ?? 'all') === $key ? 'btn-primary' : 'btn-outline-primary' ?>">
        <i class="bi <?= esc($icon) ?> me-1" aria-hidden="true"></i><?= esc((string) $text) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <a href="<?= base_url('admin/analytics/export-pdf') ?>?period=<?= esc($period ?? 'all') ?>&year=<?= (int) ($selectedYear ?? date('Y')) ?>"
     class="btn btn-sm btn-primary admin-btn-primary" target="_blank">
    <i class="bi bi-file-earmark-pdf"></i> Export PDF report
  </a>
  <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i> Back
  </a>
  <?php
  $analyticsHeaderActions = (string) ob_get_clean();

  echo view('admin/partials/page_header', ['pageHeader' => [
    'icon'     => 'bi-graph-up-arrow',
    'title'    => 'Analytics Dashboard',
    'subtitle' => 'Enrollment, faculty, and performance insights for the whole school',
    'actions'  => $analyticsHeaderActions,
  ]]);
?>

<?php
  $maleCount = (int)($genderDistribution['male'] ?? 0);
  $femaleCount = (int)($genderDistribution['female'] ?? 0);
  $enrolledTotal = (int)($statusDistribution['enrolled'] ?? 0);
  $pendingTotal = (int)($statusDistribution['pending'] ?? 0);
  $approvedTotal = (int)($statusDistribution['approved'] ?? 0);
  $rejectedTotal = (int)($statusDistribution['rejected'] ?? 0);
  $totalStudents = $enrolledTotal + $pendingTotal + $approvedTotal + $rejectedTotal;
  $periodDescription = match ($period ?? 'all') {
    'week'  => 'Enrollments recorded in the last 7 days',
    'month' => 'Enrollments recorded in the last 30 days',
    'term'  => 'Enrollments for school year ' . ($schoolYear ?? get_current_school_year()),
    default => 'Enrollments for year ' . ((int) ($selectedYear ?? date('Y'))) . ' (SY starting ' . ((int) ($selectedYear ?? date('Y'))) . ' + records created in ' . ((int) ($selectedYear ?? date('Y'))) . ')',
  };
?>

<div class="analytics-page compact">
  <div class="card overview-card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-clipboard2-data text-primary fs-5" aria-hidden="true"></i>
        <div>
          <h6 class="mb-0">Overview</h6>
          <small class="text-muted"><?= esc($periodDescription) ?></small>
        </div>
      </div>
      <div class="stat-chips">
        <span class="stat-chip bg-primary-soft"><i class="bi bi-people me-1"></i>Total <strong><?= $totalStudents ?></strong></span>
        <span class="stat-chip bg-blue-soft"><i class="bi bi-check2-circle me-1"></i>Enrolled <strong><?= $enrolledTotal ?></strong></span>
        <span class="stat-chip bg-amber-soft"><i class="bi bi-hourglass-split me-1"></i>Pending <strong><?= $pendingTotal ?></strong></span>
        <span class="stat-chip bg-slate-soft"><i class="bi bi-patch-check me-1"></i>Approved <strong><?= $approvedTotal ?></strong></span>
        <span class="stat-chip bg-gray-soft"><i class="bi bi-x-circle me-1"></i>Rejected <strong><?= $rejectedTotal ?></strong></span>
        <span class="stat-chip bg-cyan-soft"><i class="bi bi-gender-male me-1"></i>Male <strong><?= $maleCount ?></strong></span>
        <span class="stat-chip bg-indigo-soft"><i class="bi bi-gender-female me-1"></i>Female <strong><?= $femaleCount ?></strong></span>
      </div>
    </div>
  </div>

  <div class="analytics-layout">
    <!-- Left: 2x2 charts grid (compact) -->
    <div class="analytics-cell">
      <div class="charts-grid">

        <div class="card chart-card">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <strong class="small"><i class="bi bi-person-video3 me-1"></i>Teachers Overview</strong>
            <small class="text-muted d-none d-md-inline">Active faculty & who advises a section</small>
          </div>
          <div class="card-body py-2">
            <small class="text-muted d-block mb-2">Shows how many teachers are currently active and how many of them are assigned as section advisers.</small>
            <div class="row mb-2">
              <div class="col-6">
                <div class="text-center">
                  <div class="h5 mb-0 text-primary"><?= $teacherStats['active'] ?? 0 ?></div>
                  <small class="text-muted">Active Teachers</small>
                </div>
              </div>
              <div class="col-6">
                <div class="text-center">
                  <div class="h5 mb-0 text-warning"><?= $teacherStats['with_adviser'] ?? 0 ?></div>
                  <small class="text-muted">With Sections</small>
                </div>
              </div>
            </div>
            <div class="chart-container pie-chart" style="height: 50px;">
              <canvas id="teacherChart" class="chart-canvas"></canvas>
            </div>
          </div>
        </div>
        <div class="card chart-card">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <strong class="small"><i class="bi bi-grid-3x3-gap me-1"></i>Grade Level Distribution</strong>
            <small class="text-muted d-none d-md-inline">Enrolled students per grade level</small>
          </div>
          <div class="card-body py-2">
            <small class="text-muted d-block mb-2">Breaks down the enrolled population by grade level — shows how many students are in each grade and which grade has the highest enrollment.</small>
            <div class="row mb-2">
              <div class="col-6">
                <div class="text-center">
                  <div class="h5 mb-0 text-primary"><?= max($gradeDistribution ?? [0]) ?></div>
                  <small class="text-muted">Highest Grade</small>
                </div>
              </div>
              <div class="col-6">
                <div class="text-center">
                  <div class="h5 mb-0 text-info"><?= count(array_filter($gradeDistribution ?? [])) ?></div>
                  <small class="text-muted">Active Grades</small>
                </div>
              </div>
            </div>
            <div class="chart-container bar-chart" style="height: 50px;">
              <canvas id="gradeChart" class="chart-canvas"></canvas>
            </div>
          </div>
        </div>
        <div class="card chart-card">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <strong class="small"><i class="bi bi-gender-ambiguous me-1"></i>Gender Distribution</strong>
            <small class="text-muted d-none d-md-inline">Male vs female (enrolled only)</small>
          </div>
          <div class="card-body py-2">
            <small class="text-muted d-block mb-2">Compares the number of male and female enrolled students so you can see the gender balance across the current enrollment.</small>
            <div class="row mb-2">
              <div class="col-6">
                <div class="text-center">
                  <div class="h5 mb-0 text-primary"><?= $maleCount ?></div>
                  <small class="text-muted">Male</small>
                </div>
              </div>
              <div class="col-6">
                <div class="text-center">
                  <div class="h5 mb-0 text-pink"><?= $femaleCount ?></div>
                  <small class="text-muted">Female</small>
                </div>
              </div>
            </div>
            <div class="chart-container pie-chart" style="height: 50px;">
              <canvas id="genderChart" class="chart-canvas"></canvas>
            </div>
          </div>
        </div>

        <div class="card chart-card">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <strong class="small"><i class="bi bi-clipboard2-check me-1"></i>Enrollment Status</strong>
            <small class="text-muted d-none d-md-inline">Where applications stand</small>
          </div>
          <div class="card-body py-2">
            <small class="text-muted d-block mb-2">Tracks the status of all student applications — how many have been enrolled, are still pending review, approved, or rejected.</small>
            <div class="row mb-2">
              <div class="col-6">
                <div class="text-center">
                  <div class="h5 mb-0 text-success"><?= $enrolledTotal ?></div>
                  <small class="text-muted">Enrolled</small>
                </div>
              </div>
              <div class="col-6">
                <div class="text-center">
                  <div class="h5 mb-0 text-warning"><?= $pendingTotal ?></div>
                  <small class="text-muted">Pending</small>
                </div>
              </div>
            </div>
            <div class="chart-container pie-chart" style="height: 50px;">
              <canvas id="statusChart" class="chart-canvas"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right: compact widgets stacked -->
    <div class="analytics-cell">
      <div class="card mb-3">
        <div class="card-header py-2">
          <div class="d-flex justify-content-between align-items-center">
            <strong class="small"><i class="bi bi-speedometer2 me-1"></i>Key Metrics</strong>
            <small class="text-muted d-none d-md-inline">Enrollment funnel at a glance</small>
          </div>
        </div>
        <div class="card-body py-2">
          <div class="metric-row"><span><i class="bi bi-check2-all me-1 text-success"></i>Completion</span><strong><?= esc(($metrics['completionRate'] ?? 0) . '%') ?></strong></div>
          <div class="metric-row"><span><i class="bi bi-hourglass-split me-1 text-warning"></i>Pending</span><strong><?= esc(($metrics['pendingRate'] ?? 0) . '%') ?></strong></div>
          <div class="metric-row"><span><i class="bi bi-patch-check me-1 text-info"></i>Approval</span><strong><?= esc(($metrics['approvalRate'] ?? 0) . '%') ?></strong></div>
          <div class="metric-row mb-0"><span><i class="bi bi-gender-ambiguous me-1 text-secondary"></i>Gender Gap</span><strong><?= esc($metrics['genderBalance'] ?? 0) ?></strong></div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
          <strong class="small"><i class="bi bi-clock-history me-1"></i>Recent Enrolled</strong>
          <small class="text-muted d-none d-md-inline">Newest 5 enrollees</small>
        </div>
        <div class="card-body p-0">
          <?php if (!empty($recentEnrolled)): ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($recentEnrolled as $s): ?>
                <li class="list-group-item py-2 d-flex justify-content-between align-items-center">
                  <span class="small text-truncate" style="max-width: 170px;">
                    <i class="bi bi-person-circle text-muted me-1"></i><?= esc(($s['last_name'] ?? '') . ', ' . ($s['first_name'] ?? '')) ?>
                  </span>
                  <small class="text-muted">G<?= esc($s['grade_level'] ?? '-') ?></small>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-muted m-2 small">No recent records.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header py-2">
          <div class="d-flex justify-content-between align-items-center">
            <strong class="small"><i class="bi bi-award me-1"></i>Average Grade (T<?= esc((string) ($currentTerm ?? get_current_term())) ?>)</strong>
            <small class="text-muted d-none d-md-inline">Mean per grade level</small>
          </div>
        </div>
        <div class="card-body py-2">
          <?php foreach (grade_level_options() as $g): ?>
          <div class="metric-row<?= $g === grade_level_max() ? ' mb-0' : '' ?>"><span><?= esc(grade_level_label($g)) ?></span><strong><?= esc($gradeAverages[$g] ?? 0) ?></strong></div>
          <?php endforeach; ?>
          <small class="text-muted d-block mt-1 small">SY: <?= esc($schoolYear ?? get_current_school_year()) ?></small>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart.js draws on a canvas, so no CSS cascade can reach it: the platform
// typeface has to be handed to Chart.js explicitly, otherwise axis ticks,
// legends and data labels fall back to Chart.js' own default stack.
if (typeof Chart !== 'undefined') {
  if (Chart.defaults.font) {
    Chart.defaults.font.family = "'Times New Roman', Times, 'Liberation Serif', 'DejaVu Serif', serif";
    Chart.defaults.font.weight = 400;
  } else if (Chart.defaults.global) {
    Chart.defaults.global.defaultFontFamily = "'Times New Roman', Times, 'Liberation Serif', 'DejaVu Serif', serif";
    Chart.defaults.global.defaultFontWeight = 'normal';
  }
}
</script>
<script>
const css = getComputedStyle(document.documentElement);
const colorPrimary = css.getPropertyValue('--color-primary').trim() || '#1e40af';
const colorPrimaryLight = css.getPropertyValue('--color-primary-light').trim() || '#3b82f6';
const colorHeading = css.getPropertyValue('--color-heading').trim() || '#0f172a';

const genderData = <?= json_encode($genderDistribution ?? []) ?>;
const statusData = <?= json_encode($statusDistribution ?? []) ?>;
const teacherData = <?= json_encode($teacherStats ?? []) ?>;

document.addEventListener('DOMContentLoaded', function () {
const chartDefaults = { responsive: true, maintainAspectRatio: false };

// Grade Bar (modern blue palette)
const gradeEl = document.getElementById('gradeChart');
if (gradeEl) new Chart(gradeEl, { type: 'bar', data: { labels: <?= json_encode(grade_level_chart_labels()) ?>, datasets: [{ data: <?= json_encode(array_map(static fn (int $g): int => (int) ($gradeDistribution[$g] ?? $gradeDistribution[(string) $g] ?? 0), grade_level_options())) ?>, backgroundColor: ['#7c3aed', colorPrimary, colorPrimaryLight, '#60a5fa', '#93c5fd', '#bfdbfe', '#dbeafe'], borderRadius: 6 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, grid: { color: 'rgba(15,23,42,0.06)' }, ticks: { stepSize: 1, color: colorHeading, maxTicksLimit: 4 } }, x: { grid: { display: false }, ticks: { color: colorHeading } } }, plugins: { legend: { display: false } } } });

// Teacher Doughnut (orange shades)
const teacherEl = document.getElementById('teacherChart');
if (teacherEl) new Chart(teacherEl, { type: 'doughnut', data: { labels: ['With Sections','Available'], datasets: [{ data: [teacherData.with_adviser ?? 0, teacherData.without_adviser ?? 0], backgroundColor: ['#f59e0b', '#fbbf24'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { color: colorHeading, boxWidth: 10 } } } } });

// Gender Doughnut (blue shades)
const genderEl = document.getElementById('genderChart');
if (genderEl) new Chart(genderEl, { type: 'doughnut', data: { labels: ['Male','Female'], datasets: [{ data: [genderData.male ?? 0, genderData.female ?? 0], backgroundColor: [colorPrimary, colorPrimaryLight], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { color: colorHeading, boxWidth: 10 } } } } });



// Enrollment Status (doughnut)
const statusEl = document.getElementById('statusChart');
if (statusEl) new Chart(statusEl, { type: 'doughnut', data: { labels: ['Enrolled','Pending','Approved','Rejected'], datasets: [{ data: [statusData.enrolled ?? 0, statusData.pending ?? 0, statusData.approved ?? 0, statusData.rejected ?? 0], backgroundColor: [colorPrimary, colorPrimaryLight, '#60a5fa', '#94a3b8'], borderWidth: 0 }] },   options: { ...chartDefaults, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { color: colorHeading, boxWidth: 10 } } } } });

});
</script>
<?= $this->endSection() ?> 
