<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style>
/* Student Analytics Page — widgets follow the shared recipe in app.css; this
   block only sets the page's own layout. */
.analytics-header {
  margin-bottom: 1.5rem;
}

.stat-card {
  transition: transform var(--motion-base) var(--ease-out-soft), box-shadow var(--motion-base) var(--ease-out-soft);
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.analytics-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.chart-container {
  position: relative;
  height: 250px;
}

/* Overview panel: a soft tinted surface, not a saturated colour block. */
.overview-card {
  background: var(--surface-tint);
  color: var(--color-text);
  border-radius: var(--radius-lg);
  border: var(--hairline);
  box-shadow: var(--shadow-md);
}

.overview-card .card-body {
  padding: 2rem;
}

.filter-buttons {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.filter-buttons .btn {
  flex: 1;
  min-width: 120px;
}

/* Attendance rows read as a list of items: hairline + a status dot in the
   leading position, instead of a coloured stripe down the left edge. */
.attendance-item {
  border: var(--hairline);
  border-radius: var(--radius-md);
  background: var(--surface-1);
  box-shadow: var(--shadow-sm);
  transition: box-shadow var(--motion-base) var(--ease-out-soft), transform var(--motion-base) var(--ease-out-soft);
}

.attendance-item:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-1px);
}

.status-present  { color: var(--status-success-ink); font-weight: 700; }
.status-absent   { color: var(--status-danger-ink);  font-weight: 700; }
.status-late     { color: var(--status-warning-ink); font-weight: 700; }
.status-excused  { color: var(--status-info-ink);    font-weight: 700; }

.grade-badge {
  font-size: 1.1rem;
  padding: 0.5rem 1rem;
  border-radius: var(--radius-sm);
}

@media (max-width: 768px) {
  .analytics-layout {
    grid-template-columns: 1fr;
  }
  
  .filter-buttons {
    flex-direction: column;
  }
  
  .filter-buttons .btn {
    width: 100%;
  }
}
</style>

<div class="analytics-header">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 fw-bold text-primary mb-1">My Academic Analytics</h1>
      <p class="text-muted mb-0">Track your performance and attendance</p>
    </div>
    <div>
      <a href="<?= base_url('student/dashboard') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
      </a>
    </div>
  </div>
</div>

<?php if (isset($error)): ?>
  <div class="alert alert-danger"><?= esc($error) ?></div>
<?php endif; ?>

<!-- Overview Cards -->
<style>
.analytics-overview-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  margin-bottom: 1rem;
}
.analytics-overview-row .analytics-stat-card {
  flex: 1 1 0;
  min-width: 140px;
}
.analytics-stat-card .stat-card {
  border-radius: var(--radius-lg);
}
.analytics-stat-card .card-body {
  padding: 0.9rem 0.6rem !important;
}
.analytics-stat-card .stat-icon {
  font-size: var(--icon-lg);
  line-height: 1;
  margin-bottom: 0.45rem;
  display: inline-block;
}
.analytics-stat-card .stat-title {
  font-size: 0.875rem;
  font-weight: 700;
  margin-bottom: 0.2rem;
  color: var(--color-text-muted);
}
.analytics-stat-card .stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.1;
  margin-bottom: 0;
  font-variant-numeric: tabular-nums;
  color: var(--color-heading);
}
.analytics-stat-card .stat-sub {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  display: block;
  margin-top: 0.15rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
<?php if (!empty($isDomainMode)): ?>
<!-- Non-numerical (developmental) sections are assessed with symbols, not 0-100
     grades, so the Average / Highest / Lowest widgets are replaced by actual
     developmental progress. -->
<div class="analytics-overview-row">
  <div class="analytics-stat-card">
    <div class="card stat-card bg-primary text-white h-100">
      <div class="card-body text-center">
        <i class="bi bi-list-check stat-icon"></i>
        <h5 class="card-title stat-title">Indicators Assessed</h5>
        <p class="stat-value"><?= (int) ($domainAnalytics['assessed'] ?? 0) ?> / <?= (int) ($domainAnalytics['totalIndicators'] ?? 0) ?></p>
        <small class="stat-sub"><?= number_format((float) ($domainAnalytics['completionRate'] ?? 0), 1) ?>% completion</small>
      </div>
    </div>
  </div>

  <div class="analytics-stat-card">
    <div class="card stat-card bg-success text-white h-100">
      <div class="card-body text-center">
        <i class="bi bi-trophy stat-icon"></i>
        <h5 class="card-title stat-title">Mastery (P + AP)</h5>
        <p class="stat-value"><?= number_format((float) ($domainAnalytics['masteryRate'] ?? 0), 1) ?>%</p>
        <small class="stat-sub">Proficient + Approaching</small>
      </div>
    </div>
  </div>

  <div class="analytics-stat-card">
    <div class="card stat-card bg-warning text-white h-100">
      <div class="card-body text-center">
        <i class="bi bi-star stat-icon"></i>
        <h5 class="card-title stat-title">Proficient (P)</h5>
        <p class="stat-value"><?= (int) ($domainAnalytics['symbols']['P'] ?? 0) ?></p>
        <small class="stat-sub">AP <?= (int) ($domainAnalytics['symbols']['AP'] ?? 0) ?> &middot; D <?= (int) ($domainAnalytics['symbols']['D'] ?? 0) ?> &middot; B <?= (int) ($domainAnalytics['symbols']['B'] ?? 0) ?></small>
      </div>
    </div>
  </div>

  <div class="analytics-stat-card">
    <div class="card stat-card bg-info text-white h-100">
      <div class="card-body text-center">
        <i class="bi bi-calendar-check stat-icon"></i>
        <h5 class="card-title stat-title">Attendance</h5>
        <p class="stat-value"><?= $analytics['attendanceRate'] ?>%</p>
        <small class="stat-sub"><?= $analytics['attendanceStats']['total'] ?? 0 ?> records</small>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="analytics-overview-row">
  <div class="analytics-stat-card">
    <div class="card stat-card bg-primary text-white h-100">
      <div class="card-body text-center">
        <i class="bi bi-journal-check stat-icon"></i>
        <h5 class="card-title stat-title">My Average</h5>
        <p class="stat-value"><?= number_format($analytics['studentAverage'], 1) ?>%</p>
      </div>
    </div>
  </div>

  <div class="analytics-stat-card">
    <div class="card stat-card bg-success text-white h-100">
      <div class="card-body text-center">
        <i class="bi bi-trophy stat-icon"></i>
        <h5 class="card-title stat-title">Highest Grade</h5>
        <p class="stat-value"><?= $analytics['highestGrade']['value'] ?? 0 ?>%</p>
        <small class="stat-sub"><?= esc($analytics['highestGrade']['subject'] ?? 'N/A') ?></small>
      </div>
    </div>
  </div>

  <div class="analytics-stat-card">
    <div class="card stat-card bg-warning text-white h-100">
      <div class="card-body text-center">
        <i class="bi bi-graph-down stat-icon"></i>
        <h5 class="card-title stat-title">Lowest Grade</h5>
        <p class="stat-value"><?= $analytics['lowestGrade']['value'] ?? 0 ?>%</p>
        <small class="stat-sub"><?= esc($analytics['lowestGrade']['subject'] ?? 'N/A') ?></small>
      </div>
    </div>
  </div>

  <div class="analytics-stat-card">
    <div class="card stat-card bg-info text-white h-100">
      <div class="card-body text-center">
        <i class="bi bi-calendar-check stat-icon"></i>
        <h5 class="card-title stat-title">Attendance</h5>
        <p class="stat-value"><?= $analytics['attendanceRate'] ?>%</p>
        <small class="stat-sub"><?= $analytics['attendanceStats']['total'] ?? 0 ?> records</small>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($isDomainMode)): ?>
<!-- Developmental domain & quarter progress (symbol-based, no numeric grades) -->
<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card bg-white border-0 shadow-sm rounded-3 h-100">
      <div class="card-header bg-transparent border-0 p-3">
        <h5 class="card-title mb-0">
          <i class="bi bi-diagram-3 me-2 text-primary"></i>Developmental Domains
        </h5>
        <small class="text-muted">Assessed indicators and mastery per domain</small>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($domainAnalytics['domains'])): ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th class="border-0 fw-medium small">Domain</th>
                  <th class="border-0 fw-medium small text-center">Assessed</th>
                  <th class="border-0 fw-medium small text-center">Symbols (P/AP/D/B)</th>
                  <th class="border-0 fw-medium small text-center">Mastery</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($domainAnalytics['domains'] as $domainRow): ?>
                  <tr>
                    <td class="py-2 fw-medium small align-middle"><?= esc($domainRow['name']) ?></td>
                    <td class="py-2 text-center small align-middle">
                      <?= (int) $domainRow['assessed'] ?>/<?= (int) $domainRow['total'] ?>
                    </td>
                    <td class="py-2 text-center small align-middle text-nowrap">
                      <?php foreach (['P' => 'success', 'AP' => 'primary', 'D' => 'warning', 'B' => 'danger'] as $symbolKey => $symbolColor): ?>
                        <?php $symbolCountKey = ['P' => 'proficient', 'AP' => 'approaching', 'D' => 'developing', 'B' => 'beginning'][$symbolKey]; ?>
                        <span class="badge bg-<?= $symbolColor ?> rounded-pill me-1" style="color: white !important;"
                              title="<?= esc($symbolKey) ?>">
                          <?= (int) ($domainRow[$symbolCountKey] ?? 0) ?>
                        </span>
                      <?php endforeach; ?>
                      <?php if ((int) ($domainRow['observed'] ?? 0) > 0): ?>
                        <span class="badge bg-secondary rounded-pill" style="color: white !important;" title="NO/NA">
                          <?= (int) $domainRow['observed'] ?>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="py-2 text-center align-middle">
                      <span class="badge bg-<?= $domainRow['mastery'] >= 80 ? 'success' : ($domainRow['mastery'] >= 50 ? 'primary' : 'secondary') ?> small" style="color: white !important;">
                        <?= number_format((float) $domainRow['mastery'], 1) ?>%
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="p-4 text-center">
            <h6 class="text-muted mb-1">No developmental domains configured</h6>
            <p class="text-muted small mb-0">Your adviser will set up your section's domains.</p>
          </div>
        <?php endif; ?>
      </div>
      <div class="card-footer bg-transparent border-0 pt-0">
        <small class="text-muted">Mastery = P + AP among rated indicators (NO/NA excluded).</small>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card bg-white border-0 shadow-sm rounded-3 h-100">
      <div class="card-header bg-transparent border-0 p-3">
        <h5 class="card-title mb-0">
          <i class="bi bi-calendar3 me-2 text-info"></i>Assessment Coverage per Quarter
        </h5>
        <small class="text-muted"><?= (int) ($domainAnalytics['quartersDone'] ?? 0) ?> of 4 quarters recorded</small>
      </div>
      <div class="card-body">
        <div class="row g-2 text-center mb-3">
          <?php foreach ([1, 2, 3, 4] as $quarterNo): ?>
            <div class="col-3">
              <div class="border rounded-3 py-2">
                <div class="text-muted small">Q<?= $quarterNo ?></div>
                <div class="fw-bold"><?= (int) ($domainAnalytics['quarterCoverage'][$quarterNo] ?? 0) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="small">
          <span class="badge bg-success small" style="color: white !important;">P</span> Proficient
          <span class="badge bg-primary small" style="color: white !important;">AP</span> Approaching Proficiency
          <span class="badge bg-warning small" style="color: white !important;">D</span> Developing
          <span class="badge bg-danger small" style="color: white !important;">B</span> Beginning
          <span class="badge bg-secondary small" style="color: white !important;">NO/NA</span> Not Observed/Not Applicable
        </div>
        <small class="text-muted d-block mt-2">Symbols are recorded per quarter by your adviser, independent of the admin term.</small>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (empty($isDomainMode) && isset($analytics['improvementRate'])): ?>
<div class="alert alert-<?= $analytics['improvementRate'] >= 0 ? 'success' : 'warning' ?>">
  <i class="bi bi-arrow-<?= $analytics['improvementRate'] >= 0 ? 'up' : 'down' ?> me-2"></i>
  <strong>Performance Trend:</strong> 
  <?php if ($analytics['improvementRate'] > 0): ?>
    You've improved by <?= abs($analytics['improvementRate']) ?>% compared to the previous term. Great job!
  <?php elseif ($analytics['improvementRate'] < 0): ?>
    Your average decreased by <?= abs($analytics['improvementRate']) ?>% compared to the previous term. Keep studying!
  <?php else: ?>
    Your performance is consistent with the previous term.
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Term Trends -->
<?php if (!empty($analytics['termTrends'])): ?>
<div class="card bg-white border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-transparent border-0 p-3">
    <h5 class="card-title mb-0">
      <i class="bi bi-graph-up me-2 text-primary"></i>Term Performance Trend
    </h5>
  </div>
  <div class="card-body">
    <div class="chart-container">
      <canvas id="termTrendsChart"></canvas>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($analytics['subjectAverages'])): ?>
<!-- Subject Grades Breakdown -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card bg-white border-0 shadow-sm rounded-3 h-100">
      <div class="card-header bg-transparent border-0 p-3">
        <h5 class="card-title mb-0">
          <i class="bi bi-book me-2 text-success"></i>
          Subject Grades (Term <?= esc($selectedTerm) ?>)
        </h5>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <canvas id="subjectGradesChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-6">
    <div class="card bg-white border-0 shadow-sm rounded-3 h-100">
      <div class="card-header bg-transparent border-0 p-3">
        <h5 class="card-title mb-0">
          <i class="bi bi-pie-chart me-2 text-info"></i>
          Performance Distribution
        </h5>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <canvas id="gradeDistributionChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Detailed Subject Grades Table -->
<?php if (!empty($analytics['subjectGrades'])): ?>
<div class="card bg-white border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-transparent border-0 p-3">
    <h5 class="card-title mb-0">
      <i class="bi bi-table me-2 text-primary"></i>
      Detailed Grades Breakdown
    </h5>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th class="border-0 fw-medium">Subject</th>
            <th class="border-0 fw-medium">Grade</th>
            <th class="border-0 fw-medium">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($analytics['subjectGrades'] as $subjectGrade): ?>
            <tr>
              <td class="fw-medium"><?= esc($subjectGrade['subject']) ?></td>
              <td>
                <span class="badge bg-<?= $subjectGrade['grade'] >= 75 ? 'success' : 'danger' ?> fs-6">
                  <?= number_format($subjectGrade['grade'], 1) ?>
                </span>
              </td>
              <td>
                <?php if ($subjectGrade['grade'] >= 90): ?>
                  <span class="text-success fw-medium">Excellent</span>
                <?php elseif ($subjectGrade['grade'] >= 85): ?>
                  <span class="text-primary fw-medium">Very Good</span>
                <?php elseif ($subjectGrade['grade'] >= 80): ?>
                  <span class="text-info fw-medium">Good</span>
                <?php elseif ($subjectGrade['grade'] >= 75): ?>
                  <span class="text-warning fw-medium">Fair</span>
                <?php elseif ($subjectGrade['grade'] >= 70): ?>
                  <span class="text-secondary fw-medium">Passing</span>
                <?php else: ?>
                  <span class="text-danger fw-medium">Needs Improvement</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Attendance Records -->
<div class="card bg-white border-0 shadow-sm rounded-3" id="attendance-records">
  <div class="card-header bg-transparent border-0 p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h5 class="card-title mb-0">
        <i class="bi bi-calendar-check me-2 text-info"></i>
        Attendance Records
      </h5>
      <div class="filter-buttons">
        <a href="?attendance_filter=week&term=<?= esc($selectedTerm) ?>" 
           class="btn btn-sm btn-<?= $attendanceFilter === 'week' ? 'primary' : 'outline-primary' ?>">
          <i class="bi bi-calendar-week me-1"></i>This Week
        </a>
        <a href="?attendance_filter=month&term=<?= esc($selectedTerm) ?>" 
           class="btn btn-sm btn-<?= $attendanceFilter === 'month' ? 'primary' : 'outline-primary' ?>">
          <i class="bi bi-calendar-month me-1"></i>This Month
        </a>
        <a href="?attendance_filter=term&term=<?= esc($selectedTerm) ?>" 
           class="btn btn-sm btn-<?= $attendanceFilter === 'term' ? 'primary' : 'outline-primary' ?>">
          <i class="bi bi-journal me-1"></i>Term <?= esc($selectedTerm) ?>
        </a>
        <a href="?attendance_filter=semester" 
           class="btn btn-sm btn-<?= $attendanceFilter === 'semester' ? 'primary' : 'outline-primary' ?>">
          <i class="bi bi-journal-bookmark me-1"></i>Whole Year
        </a>
      </div>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (!empty($analytics['attendanceRecords'])): ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th class="border-0 fw-medium small">Date</th>
              <th class="border-0 fw-medium small"><?= !empty($isDomainMode) ? 'Section' : 'Subject' ?></th>
              <th class="border-0 fw-medium small">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($analytics['attendanceRecords'] as $record): ?>
              <tr class="attendance-item">
                <td class="small"><?= date('M j, Y', strtotime($record['date'])) ?></td>
                <td class="small"><?= esc($record['subject_name'] ?? (!empty($isDomainMode) ? ($section['section_name'] ?? 'General') : 'N/A')) ?></td>
                <td class="small">
                  <span class="status-<?= strtolower($record['status']) ?>">
                    <i class="bi bi-<?= $record['status'] === 'Present' ? 'check-circle' : ($record['status'] === 'Absent' ? 'x-circle' : ($record['status'] === 'Late' ? 'clock' : 'question-circle')) ?> me-1"></i>
                    <?= esc($record['status']) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (($analytics['attendancePagination']['totalPages'] ?? 1) > 1): ?>
      <div class="d-flex justify-content-between align-items-center p-3">
          <small class="text-muted">
            Showing <?= ($analytics['attendancePagination']['page'] - 1) * $analytics['attendancePagination']['perPage'] + 1 ?>
            to <?= min($analytics['attendancePagination']['page'] * $analytics['attendancePagination']['perPage'], $analytics['attendancePagination']['total']) ?>
            of <?= $analytics['attendancePagination']['total'] ?> records
          </small>
          <div class="btn-group">
            <?php if ($analytics['attendancePagination']['page'] > 1): ?>
              <a href="?attendance_filter=<?= esc($attendanceFilter) ?>&term=<?= esc($selectedTerm) ?>&attendance_page=<?= $analytics['attendancePagination']['page'] - 1 ?>#attendance-records" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-chevron-left"></i> Previous
              </a>
            <?php endif; ?>
            <?php if ($analytics['attendancePagination']['page'] < $analytics['attendancePagination']['totalPages']): ?>
              <a href="?attendance_filter=<?= esc($attendanceFilter) ?>&term=<?= esc($selectedTerm) ?>&attendance_page=<?= $analytics['attendancePagination']['page'] + 1 ?>#attendance-records" class="btn btn-sm btn-outline-primary">
                Next <i class="bi bi-chevron-right"></i>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="text-center py-5">
        <i class="bi bi-calendar-x display-1 text-muted"></i>
        <h5 class="text-muted mt-3">No attendance records found</h5>
        <p class="text-muted">Attendance records will appear here once your teachers mark them.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Chart.js Library -->
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
// Term Trends Chart
<?php if (!empty($analytics['termTrends'])): ?>
const termTrendsCtx = document.getElementById('termTrendsChart');
if (termTrendsCtx) {
  new Chart(termTrendsCtx, {
    type: 'line',
    data: {
      labels: <?= json_encode(array_column($analytics['termTrends'], 'term')) ?>,
      datasets: [{
        label: 'My Average Grade',
        data: <?= json_encode(array_column($analytics['termTrends'], 'average')) ?>,
        borderColor: '#007bff',
        backgroundColor: 'rgba(0, 123, 255, 0.1)',
        tension: 0.4,
        fill: true,
        pointRadius: 6,
        pointHoverRadius: 8
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          position: 'top'
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: {
            callback: function(value) {
              return value + '%';
            }
          }
        }
      }
    }
  });
}
<?php endif; ?>

// Subject Grades Chart
<?php if (!empty($analytics['subjectAverages'])): ?>
const subjectGradesCtx = document.getElementById('subjectGradesChart');
if (subjectGradesCtx) {
  new Chart(subjectGradesCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($analytics['subjectAverages'], 'subject')) ?>,
      datasets: [{
        label: 'My Grade',
        data: <?= json_encode(array_column($analytics['subjectAverages'], 'grade')) ?>,
        backgroundColor: [
          'rgba(40, 167, 69, 0.8)',
          'rgba(23, 162, 184, 0.8)',
          'rgba(255, 193, 7, 0.8)',
          'rgba(255, 152, 0, 0.8)',
          'rgba(153, 102, 51, 0.8)',
          'rgba(121, 85, 72, 0.8)'
        ],
        borderRadius: 8
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: {
            callback: function(value) {
              return value + '%';
            }
          }
        }
      }
    }
  });
}
<?php endif; ?>

// Grade Distribution Chart
const gradeDistCtx = document.getElementById('gradeDistributionChart');
if (gradeDistCtx) {
  const distribution = <?= json_encode($analytics['gradeDistribution']) ?>;
  const hasData = Object.values(distribution).some(val => val > 0);
  
  if (hasData) {
    new Chart(gradeDistCtx, {
      type: 'doughnut',
      data: {
        labels: ['Excellent (90-100)', 'Very Good (85-89)', 'Good (80-84)', 'Fair (75-79)', 'Passing (70-74)', 'Failing (<70)'],
        datasets: [{
          data: [
            distribution.excellent || 0,
            distribution.very_good || 0,
            distribution.good || 0,
            distribution.fair || 0,
            distribution.passing || 0,
            distribution.failing || 0
          ],
          backgroundColor: [
            'rgba(40, 167, 69, 0.8)',
            'rgba(23, 162, 184, 0.8)',
            'rgba(255, 193, 7, 0.8)',
            'rgba(255, 152, 0, 0.8)',
            'rgba(121, 85, 72, 0.8)',
            'rgba(220, 53, 69, 0.8)'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
  }
}
</script>

<?= $this->endSection() ?>