<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style>
/* Admin-style Analytics Styling */
.analytics-page.compact {
  font-size: 14px;
}

.overview-card {
  background: var(--surface-tint);
  border: var(--hairline);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
}

.stat-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.stat-chip {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 400;
  white-space: nowrap;
}

.bg-primary-soft { background: rgba(30, 64, 175, 0.1); color: #1e40af; }
.bg-blue-soft { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.bg-amber-soft { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.bg-slate-soft { background: rgba(100, 116, 139, 0.1); color: #64748b; }
.bg-gray-soft { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
.bg-cyan-soft { background: rgba(14, 165, 233, 0.1); color: #0ea5e9; }
.bg-indigo-soft { background: rgba(99, 102, 241, 0.1); color: #6366f1; }

.analytics-layout {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1rem;
  margin-top: 1rem;
}

.charts-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.chart-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.chart-container {
  position: relative;
  height: 120px;
}

.chart-canvas {
  max-height: 120px;
}

.metric-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid #f1f5f9;
}

.metric-row:last-child {
  border-bottom: none;
}

.analytics-header {
  margin-bottom: 1rem;
}

@media (max-width: 768px) {
  .analytics-layout {
    grid-template-columns: 1fr;
  }
  .charts-grid {
    grid-template-columns: 1fr;
  }
  .stat-chips {
    justify-content: center;
  }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3 analytics-header">
  <div>
    <h1 class="h5 mb-0">Class Analytics</h1>
    <small class="text-muted">
      <?php if ((int) ($analyticsSectionCount ?? 0) > 1): ?>
        <?= (int) $analyticsSectionCount ?> sections (advisory + scheduled) · School year <?= esc($schoolYear ?? '') ?> · T<?= esc((string) ($currentTerm ?? '')) ?>
      <?php elseif (isset($teacherSection) && $teacherSection): ?>
        <?= esc(grade_level_label((int) $teacherSection['grade_level'])) ?> · <?= esc($teacherSection['section_name']) ?> · <?= esc($schoolYear ?? '') ?> · T<?= esc((string) ($currentTerm ?? '')) ?>
      <?php else: ?>
        Your classes · <?= esc($schoolYear ?? '') ?> · T<?= esc((string) ($currentTerm ?? '')) ?>
      <?php endif; ?>
    </small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= base_url('teacher/analytics/export-pdf') ?>" class="btn btn-sm btn-primary" target="_blank">
      <i class="bi bi-file-earmark-pdf"></i> Export PDF Report
    </a>
    <button class="btn btn-sm btn-success" onclick="sendToAdmin()">
      <i class="bi bi-send"></i> Send to Admin
    </button>
    <a href="<?= base_url('teacher/dashboard') ?>" class="btn btn-sm btn-outline-secondary">Back</a>
  </div>
</div>

<?php
  $totalStudents = (int)($analytics['totalStudents'] ?? 0);
  $classAverage = (float)($analytics['classAverage'] ?? 0);
  $attendanceRate = (float)($analytics['attendanceRate'] ?? 0);
  $improvementRate = (float)($analytics['improvementRate'] ?? 0);
?>

<?php if (isset($error)): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-2"></i><?= esc($error) ?>
</div>
<?php elseif ($totalStudents === 0): ?>
<div class="card">
  <div class="card-body text-center py-5">
    <i class="bi bi-graph-up fs-1 text-muted mb-3"></i>
    <h5 class="text-muted">No Analytics Data Available</h5>
    <p class="text-muted mb-0">
      No enrolled students were found in sections where you are adviser or on your teaching schedule for the current school year.<br>
      Analytics will appear once scheduling and enrollments are set.
    </p>
    <div class="mt-3">
      <a href="<?= base_url('teacher/dashboard') ?>" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
      </a>
    </div>
  </div>
</div>
<?php else: ?>

<?php if (! empty($isDomainMode)): ?>
<?php
  // ---- Non-numerical section: developmental-domain (symbol) analytics ----
  $da = $domainAnalytics ?? [];
  $symbols = $da['symbols'] ?? ['P' => 0, 'AP' => 0, 'D' => 0, 'B' => 0, 'NO' => 0];
  $sectionLabel = isset($teacherSection) && $teacherSection
    ? esc(grade_level_label((int) $teacherSection['grade_level'])) . ' · ' . esc($teacherSection['section_name'])
    : 'Section';
?>
<div class="analytics-page compact">
  <div class="card overview-card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
      <div class="d-flex align-items-center gap-2">
        <h6 class="mb-0">Class overview</h6>
        <small class="text-muted"><?= $sectionLabel ?> · Developmental Domains (symbols, not grades)</small>
      </div>
      <div class="stat-chips">
        <span class="stat-chip bg-primary-soft">Students <strong><?= count($da['students'] ?? []) ?></strong></span>
        <span class="stat-chip bg-blue-soft">Completion <strong><?= number_format($da['completionRate'] ?? 0, 1) ?>%</strong></span>
        <span class="stat-chip bg-green-soft">Mastery (P+AP) <strong><?= number_format($da['masteryRate'] ?? 0, 1) ?>%</strong></span>
        <span class="stat-chip bg-amber-soft">Attendance <strong><?= number_format($attendanceRate, 1) ?>%</strong></span>
      </div>
    </div>
  </div>

  <div class="analytics-layout">
    <div class="analytics-cell">
      <div class="charts-grid">
        <div class="card chart-card">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <strong class="small">Symbol distribution</strong>
            <small class="text-muted d-none d-md-inline"><?= (int) ($da['assessed'] ?? 0) ?> of <?= (int) ($da['totalIndicators'] ?? 0) ?> indicators assessed</small>
          </div>
          <div class="card-body py-2">
            <div style="height: 190px; position: relative;">
              <canvas id="symbolDistributionChart"></canvas>
            </div>
            <div class="row text-center mt-2">
              <div class="col"><div class="h6 mb-0 text-success"><?= $symbols['P'] ?></div><small class="text-muted">P</small></div>
              <div class="col"><div class="h6 mb-0 text-primary"><?= $symbols['AP'] ?></div><small class="text-muted">AP</small></div>
              <div class="col"><div class="h6 mb-0 text-warning"><?= $symbols['D'] ?></div><small class="text-muted">D</small></div>
              <div class="col"><div class="h6 mb-0 text-danger"><?= $symbols['B'] ?></div><small class="text-muted">B</small></div>
              <div class="col"><div class="h6 mb-0 text-secondary"><?= $symbols['NO'] ?></div><small class="text-muted">NO/NA</small></div>
            </div>
            <small class="text-muted d-block mt-2">Mastery = P + AP among rated indicators (NO/NA excluded).</small>
          </div>
        </div>

        <div class="card chart-card">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <strong class="small">Assessment coverage per quarter</strong>
            <small class="text-muted d-none d-md-inline">All quarters · <?= esc($schoolYear ?? '') ?></small>
          </div>
          <div class="card-body py-2">
            <?php $coverage = $da['quarterCoverage'] ?? [1 => 0, 2 => 0, 3 => 0, 4 => 0]; $maxQ = max(1, max($coverage)); ?>
            <?php foreach ($coverage as $q => $count): ?>
              <div class="metric-row">
                <span>Quarter <?= (int) $q ?></span>
                <span style="flex:1; margin: 0 10px;">
                  <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-info" style="width: <?= (int) round(($count / $maxQ) * 100) ?>%;"></div>
                  </div>
                </span>
                <strong><?= (int) $count ?></strong>
              </div>
            <?php endforeach; ?>
            <small class="text-muted d-block mt-2">Assessed indicators per quarter (symbols are stored per quarter, independent of the admin term).</small>
          </div>
        </div>
      </div>
    </div>

    <div class="analytics-cell">
      <div class="card mb-3">
        <div class="card-header py-2"><strong class="small">Developmental Domains</strong></div>
        <div class="card-body py-2">
          <?php if (! empty($da['domains'])): ?>
            <?php foreach ($da['domains'] as $domain): ?>
              <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="small"><?= esc($domain['name']) ?></span>
                  <small class="text-muted"><?= (int) $domain['assessed'] ?>/<?= (int) $domain['total'] ?> · <?= number_format($domain['mastery'], 1) ?>% mastery</small>
                </div>
                <div class="progress" style="height: 8px;">
                  <div class="progress-bar bg-success" style="width: <?= (int) round($domain['total'] > 0 ? ($domain['assessed'] / $domain['total']) * 100 : 0) ?>%;"></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="metric-row mb-0"><span class="text-muted small">No developmental domains configured</span><strong>-</strong></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header py-2"><strong class="small"><?= isset($teacherSection) ? esc($teacherSection['section_name']) : 'Section' ?> Students</strong></div>
        <div class="card-body py-2">
          <?php if (! empty($da['students'])): ?>
            <?php foreach ($da['students'] as $student): ?>
              <div class="metric-row">
                <span><?= esc($student['name']) ?></span>
                <strong class="small">
                  <?= (int) $student['assessed'] ?> assessed
                  <?php if ($student['assessed'] > 0): ?>
                    · <span class="text-success">P <?= (int) $student['P'] ?></span>
                    · <span class="text-primary">AP <?= (int) $student['AP'] ?></span>
                    · <span class="text-warning">D <?= (int) $student['D'] ?></span>
                    · <span class="text-danger">B <?= (int) $student['B'] ?></span>
                  <?php else: ?>
                    · <span class="text-muted">not yet assessed</span>
                  <?php endif; ?>
                </strong>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="metric-row mb-0"><span class="text-muted small">No students enrolled</span><strong>-</strong></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header py-2"><strong class="small">Section Metrics</strong></div>
        <div class="card-body py-2">
          <div class="metric-row"><span>Completion Rate</span><strong><?= number_format($da['completionRate'] ?? 0, 1) ?>%</strong></div>
          <div class="metric-row"><span>Mastery Rate (P+AP)</span><strong><?= number_format($da['masteryRate'] ?? 0, 1) ?>%</strong></div>
          <div class="metric-row"><span>Attendance Rate</span><strong><?= number_format($attendanceRate, 1) ?>%</strong></div>
          <div class="metric-row mb-0"><span>Students</span><strong><?= count($da['students'] ?? []) ?></strong></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php else: ?>

<div class="analytics-page compact">
  <div class="card overview-card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
      <div class="d-flex align-items-center gap-2">
        <h6 class="mb-0">Class overview</h6>
        <small class="text-muted">
          <?php if ((int) ($analyticsSectionCount ?? 0) > 1): ?>
            <?= (int) $analyticsSectionCount ?> sections · <?= (int) ($analytics['studentsGradedForDistribution'] ?? 0) ?> with grades this term
          <?php else: ?>
            <?= isset($teacherSection) && $teacherSection ? esc($teacherSection['section_name']) . ' · ' : '' ?>snapshot
          <?php endif; ?>
        </small>
      </div>
      <div class="stat-chips">
        <span class="stat-chip bg-primary-soft">Total <strong><?= $totalStudents ?></strong></span>
        <span class="stat-chip bg-blue-soft">Average <strong><?= $totalStudents > 0 ? number_format($classAverage, 1) . '%' : 'N/A' ?></strong></span>
        <span class="stat-chip bg-amber-soft">Attendance <strong><?= $totalStudents > 0 ? number_format($attendanceRate, 1) . '%' : 'N/A' ?></strong></span>
        <span class="stat-chip bg-cyan-soft">Improvement <strong><?= $totalStudents > 0 ? '+' . number_format($improvementRate, 1) . '%' : 'N/A' ?></strong></span>
      </div>
    </div>
  </div>

  <div class="analytics-layout">
    <!-- Left: 2x2 charts grid (compact) -->
    <div class="analytics-cell">
      <div class="charts-grid">
        <div class="card chart-card">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <strong class="small">Grade distribution</strong>
            <small class="text-muted d-none d-md-inline" title="Per-student average this term across listed subjects"><?= (int) ($analytics['studentsGradedForDistribution'] ?? 0) ?> with grades</small>
          </div>
          <div class="card-body py-2">
            <p class="small text-muted mb-2">Buckets count students by their <strong>average</strong> this term (subjects in scope).</p>
            <div class="row mb-2">
              <div class="col-4">
                <div class="text-center">
                  <div class="h6 mb-0 text-success"><?= ($analytics['gradeDistribution']['excellent'] ?? 0) + ($analytics['gradeDistribution']['very_good'] ?? 0) ?></div>
                  <small class="text-muted">Excellent</small>
                </div>
              </div>
              <div class="col-4">
                <div class="text-center">
                  <div class="h6 mb-0 text-warning"><?= ($analytics['gradeDistribution']['good'] ?? 0) + ($analytics['gradeDistribution']['fair'] ?? 0) ?></div>
                  <small class="text-muted">Good</small>
                </div>
              </div>
              <div class="col-4">
                <div class="text-center">
                  <div class="h6 mb-0 text-danger"><?= ($analytics['gradeDistribution']['passing'] ?? 0) + ($analytics['gradeDistribution']['failing'] ?? 0) ?></div>
                  <small class="text-muted">Needs Work</small>
                </div>
              </div>
            </div>
            <div class="chart-container">
              <?php if ($totalStudents > 0): ?>
                <canvas id="gradeDistributionChart" class="chart-canvas"></canvas>
              <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100">
                  <small class="text-muted">No students enrolled</small>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="card chart-card">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <strong class="small">Term trends (class mean)</strong>
            <small class="text-muted d-none d-md-inline">All terms · <?= esc($schoolYear ?? '') ?></small>
          </div>
          <div class="card-body py-2">
            <div class="row mb-2">
              <div class="col-6">
                <div class="text-center">
                  <div class="h6 mb-0 text-primary"><?= number_format($classAverage, 1) ?>%</div>
                  <small class="text-muted">Current</small>
                </div>
              </div>
              <div class="col-6">
                <div class="text-center">
                  <div class="h6 mb-0 text-success">+<?= number_format($improvementRate, 1) ?>%</div>
                  <small class="text-muted">Growth</small>
                </div>
              </div>
            </div>
            <div class="chart-container">
              <?php if ($totalStudents > 0): ?>
                <canvas id="termTrendsChart" class="chart-canvas"></canvas>
              <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100">
                  <small class="text-muted">No students enrolled</small>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="card chart-card">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <strong class="small"><?= isset($teacherSection) ? esc($teacherSection['section_name']) : 'Section' ?> Performance Trends</strong>
            <small class="text-muted d-none d-md-inline"><?= isset($teacherSection) ? esc(grade_level_label((int) $teacherSection['grade_level'])) : 'N/A' ?></small>
          </div>
          <div class="card-body py-2">
            <div class="row mb-2">
              <div class="col-6">
                <div class="text-center">
                  <div class="h6 mb-0 text-primary"><?= number_format($classAverage, 1) ?>%</div>
                  <small class="text-muted">Current</small>
                </div>
              </div>
              <div class="col-6">
                <div class="text-center">
                  <div class="h6 mb-0 text-success">+<?= number_format($improvementRate, 1) ?>%</div>
                  <small class="text-muted">Growth</small>
                </div>
              </div>
            </div>
            <div class="chart-container">
              <?php if ($totalStudents > 0): ?>
                <canvas id="performanceChart" class="chart-canvas"></canvas>
              <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100">
                  <small class="text-muted">No students enrolled</small>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="card chart-card">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <strong class="small"><?= isset($teacherSection) ? esc($teacherSection['section_name']) : 'Section' ?> Attendance</strong>
            <small class="text-muted d-none d-md-inline">October 2025</small>
          </div>
          <div class="card-body py-2">
            <?php if (isset($analytics['attendanceStats'])): ?>
              <div class="row mb-2">
                <div class="col-6">
                  <div class="text-center">
                    <div class="h6 mb-0 text-success"><?= $analytics['attendanceStats']['present'] ?? 0 ?></div>
                    <small class="text-muted">Present</small>
                  </div>
                </div>
                <div class="col-6">
                  <div class="text-center">
                    <div class="h6 mb-0 text-danger"><?= $analytics['attendanceStats']['absent'] ?? 0 ?></div>
                    <small class="text-muted">Absent</small>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <div class="text-center py-3">
                <small class="text-muted">No attendance data</small>
              </div>
            <?php endif; ?>
            <div class="chart-container">
              <?php if ($totalStudents > 0): ?>
                <canvas id="attendanceChart" class="chart-canvas"></canvas>
              <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100">
                  <small class="text-muted">No attendance data available</small>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Right: compact widgets stacked -->
    <div class="analytics-cell">
      <div class="card mb-3">
        <div class="card-header py-2"><strong class="small"><?= isset($teacherSection) ? esc($teacherSection['section_name']) : 'Section' ?> Metrics</strong></div>
        <div class="card-body py-2">
          <div class="metric-row"><span>Section Average</span><strong><?= $totalStudents > 0 ? number_format($classAverage, 1) . '%' : 'N/A' ?></strong></div>
          <div class="metric-row"><span>Attendance Rate</span><strong><?= $totalStudents > 0 ? number_format($attendanceRate, 1) . '%' : 'N/A' ?></strong></div>
          <div class="metric-row"><span>Term Growth</span><strong><?= $totalStudents > 0 ? '+' . number_format($improvementRate, 1) . '%' : 'N/A' ?></strong></div>
          <div class="metric-row mb-0"><span><?= isset($teacherSection) ? esc($teacherSection['section_name']) : 'Section' ?> Students</span><strong><?= $totalStudents ?></strong></div>
        </div>
      </div>



      <div class="card mb-3">
        <div class="card-header py-2"><strong class="small"><?= isset($teacherSection) ? esc($teacherSection['section_name']) : 'Section' ?> Subjects</strong></div>
        <div class="card-body py-2">
          <?php if (!empty($analytics['subjectAverages'])): ?>
            <?php foreach (array_slice($analytics['subjectAverages'], 0, 4) as $subject): ?>
              <div class="metric-row">
                <span><?= esc(substr($subject['subject'], 0, 15)) ?><?= strlen($subject['subject']) > 15 ? '...' : '' ?></span>
                <strong><?= number_format($subject['average'], 1) ?>%</strong>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="metric-row mb-0">
              <span class="text-muted small">No subject data available</span>
              <strong>-</strong>
            </div>
          <?php endif; ?>
          <small class="text-muted d-block mt-1 small">SY: <?= $schoolYear ?? get_current_school_year() ?></small>
        </div>
      </div>

      <div class="card">
        <div class="card-header py-2"><strong class="small"><?= isset($teacherSection) ? esc($teacherSection['section_name']) : 'Section' ?> Attendance Details</strong></div>
        <div class="card-body py-2">
          <?php if (isset($analytics['attendanceStats'])): ?>
            <div class="metric-row">
              <span class="text-success">Present</span>
              <strong><?= $analytics['attendanceStats']['present'] ?? 0 ?></strong>
            </div>
            <div class="metric-row">
              <span class="text-danger">Absent</span>
              <strong><?= $analytics['attendanceStats']['absent'] ?? 0 ?></strong>
            </div>
            <div class="metric-row">
              <span class="text-warning">Late</span>
              <strong><?= $analytics['attendanceStats']['late'] ?? 0 ?></strong>
            </div>
            <div class="metric-row">
              <span class="text-info">Excused</span>
              <strong><?= $analytics['attendanceStats']['excused'] ?? 0 ?></strong>
            </div>
            <div class="metric-row">
              <span>Attendance Rate</span>
              <strong><?= number_format($analytics['attendanceStats']['attendanceRate'] ?? 0, 1) ?>%</strong>
            </div>
            <small class="text-muted d-block">Rate counts present and late as attended.</small>
          <?php else: ?>
            <div class="metric-row mb-0">
              <span class="text-muted small">No attendance data</span>
              <strong>-</strong>
            </div>
          <?php endif; ?>
          <small class="text-muted d-block mt-1 small">Current Month</small>
        </div>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<?php endif; ?>



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
// Chart data from PHP
const gradeDistribution = <?= json_encode($analytics['gradeDistribution'] ?? []) ?>;
const termTrends = <?= json_encode($analytics['termTrends'] ?? []) ?>;
const subjectAverages = <?= json_encode($analytics['subjectAverages'] ?? []) ?>;
const attendanceStats = <?= json_encode($analytics['attendanceStats'] ?? []) ?>;
const analytics = <?= json_encode($analytics ?? []) ?>;
const schoolYear = '<?= $schoolYear ?? get_current_school_year() ?>';
const currentTerm = '<?= $currentTerm ?? '1' ?>';
const IS_DOMAIN_MODE = <?= ! empty($isDomainMode) ? 'true' : 'false' ?>;
const domainSymbols = <?= json_encode($da['symbols'] ?? []) ?>;

// Symbol Distribution Doughnut Chart (non-numerical sections)
function initSymbolDistributionChart() {
  const el = document.getElementById('symbolDistributionChart');
  if (!el) return;
  const ctx = el.getContext('2d');

  const data = [
    domainSymbols.P || 0,
    domainSymbols.AP || 0,
    domainSymbols.D || 0,
    domainSymbols.B || 0,
    domainSymbols.NO || 0
  ];

  const totalAssessed = data.reduce((a, b) => a + b, 0);
  if (totalAssessed === 0) {
    ctx.font = '14px Times New Roman, Times, serif';
    ctx.fillStyle = '#6b7280';
    ctx.textAlign = 'center';
    ctx.fillText('No symbols entered yet', ctx.canvas.width / 2, ctx.canvas.height / 2);
    return;
  }

  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['P — Proficient', 'AP — Approaching', 'D — Developing', 'B — Beginning', 'NO/NA'],
      datasets: [{
        data: data,
        backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#9ca3af'],
        borderWidth: 3,
        borderColor: '#ffffff',
        hoverBorderWidth: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: function(context) {
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
              return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
            }
          }
        }
      },
      cutout: '60%'
    }
  });
}

// Grade Distribution Doughnut Chart
function initGradeDistributionChart() {
  const ctx = document.getElementById('gradeDistributionChart').getContext('2d');
  
  const data = [
    gradeDistribution.excellent || 0,
    gradeDistribution.very_good || 0,
    gradeDistribution.good || 0,
    gradeDistribution.fair || 0,
    gradeDistribution.passing || 0,
    gradeDistribution.failing || 0
  ];
  
  const totalGrades = data.reduce((a, b) => a + b, 0);
  
  // If no grades, show a placeholder
  if (totalGrades === 0) {
    ctx.font = '14px Times New Roman, Times, serif';
    ctx.fillStyle = '#6b7280';
    ctx.textAlign = 'center';
    ctx.fillText('No grades entered yet', ctx.canvas.width / 2, ctx.canvas.height / 2);
    return;
  }

  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Excellent (90-100)', 'Very Good (85-89)', 'Good (80-84)', 'Fair (75-79)', 'Passing (70-74)', 'Failing (<70)'],
      datasets: [{
        data: data,
        backgroundColor: [
          '#10b981', // Excellent - Green
          '#22c55e', // Very Good - Light Green
          '#3b82f6', // Good - Blue
          '#f59e0b', // Fair - Yellow
          '#f97316', // Passing - Orange
          '#ef4444'  // Failing - Red
        ],
        borderWidth: 3,
        borderColor: '#ffffff',
        hoverBorderWidth: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            usePointStyle: true,
            padding: 15,
            font: {
              size: 11
            }
          }
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
              return context.label + ': ' + context.parsed + ' students (' + percentage + '%)';
            }
          }
        }
      },
      cutout: '60%'
    }
  });
}

// Term Trends Line Chart
function initTermTrendsChart() {
  const ctx = document.getElementById('termTrendsChart').getContext('2d');

  const hasData = termTrends.some(t => t.average > 0);

  if (!hasData) {
    ctx.font = '14px Times New Roman, Times, serif';
    ctx.fillStyle = '#6b7280';
    ctx.textAlign = 'center';
    ctx.fillText('No grade trends yet', ctx.canvas.width / 2, ctx.canvas.height / 2);
    return;
  }

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: termTrends.map(t => t.term),
      datasets: [{
        label: 'Class Average',
        data: termTrends.map(t => t.average),
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#3b82f6',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 3,
        pointRadius: 6,
        pointHoverRadius: 8
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          grid: {
            color: 'rgba(0,0,0,0.1)'
          },
          ticks: {
            callback: function(value) {
              return value + '%';
            }
          }
        },
        x: {
          grid: {
            color: 'rgba(0,0,0,0.1)'
          }
        }
      },
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              return 'Average: ' + context.parsed.y.toFixed(1) + '%';
            }
          }
        }
      }
    }
  });
}

// Export Analytics Function
function exportAnalytics() {
  // Create a simple text report
  let report = 'CLASS ANALYTICS REPORT\n';
  report += '======================\n\n';
  report += 'School Year: ' + (schoolYear || '<?= get_current_school_year() ?>') + '\n';
  report += 'Term: ' + (currentTerm || '1') + '\n';
  report += 'Generated: ' + new Date().toLocaleDateString() + '\n\n';

  report += 'SUMMARY STATISTICS:\n';
  report += '- Total Students: ' + (analytics.totalStudents || 0) + '\n';
  report += '- Class Average: ' + (analytics.classAverage || 0).toFixed(1) + '%\n';
  report += '- Attendance Rate: ' + (analytics.attendanceRate || 0).toFixed(1) + '%\n\n';

  report += 'GRADE DISTRIBUTION:\n';
  report += '- Excellent (90-100): ' + (gradeDistribution.excellent || 0) + ' students\n';
  report += '- Very Good (85-89): ' + (gradeDistribution.very_good || 0) + ' students\n';
  report += '- Good (80-84): ' + (gradeDistribution.good || 0) + ' students\n';
  report += '- Fair (75-79): ' + (gradeDistribution.fair || 0) + ' students\n';
  report += '- Passing (70-74): ' + (gradeDistribution.passing || 0) + ' students\n';
  report += '- Failing (<70): ' + (gradeDistribution.failing || 0) + ' students\n\n';

  if (subjectAverages.length > 0) {
    report += 'SUBJECT AVERAGES:\n';
    subjectAverages.forEach(subject => {
      report += '- ' + subject.subject + ': ' + subject.average.toFixed(1) + '%\n';
    });
  }

  // Download as text file
  const blob = new Blob([report], { type: 'text/plain' });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'class_analytics_report.txt';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
}

// Attendance Chart
function initAttendanceChart() {
  const ctx = document.getElementById('attendanceChart').getContext('2d');

  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Present', 'Absent', 'Late', 'Excused'],
      datasets: [{
        data: [
          attendanceStats.present || 0,
          attendanceStats.absent || 0,
          attendanceStats.late || 0,
          attendanceStats.excused || 0
        ],
        backgroundColor: [
          '#10b981', // Present - Green
          '#ef4444', // Absent - Red
          '#f59e0b', // Late - Yellow
          '#3b82f6'  // Excused - Blue
        ],
        borderWidth: 2,
        borderColor: '#ffffff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            usePointStyle: true,
            padding: 10,
            font: {
              size: 10
            }
          }
        }
      },
      cutout: '50%'
    }
  });
}

// Performance Trends Chart (Area Chart)
function initPerformanceChart() {
  const ctx = document.getElementById('performanceChart').getContext('2d');
  
  const termData = termTrends.map(t => t.average);
  const termLabels = termTrends.map(t => t.term);

  const hasData = termData.some(avg => avg > 0);
  
  // If no data, show placeholder
  if (!hasData) {
    ctx.font = '14px Times New Roman, Times, serif';
    ctx.fillStyle = '#6b7280';
    ctx.textAlign = 'center';
    ctx.fillText('No performance data yet', ctx.canvas.width / 2, ctx.canvas.height / 2);
    return;
  }
  
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: termLabels,
      datasets: [{
        label: 'Class Performance',
        data: termData,
        borderColor: '#8b5cf6',
        backgroundColor: 'rgba(139, 92, 246, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#8b5cf6',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          grid: {
            color: 'rgba(0,0,0,0.1)'
          },
          ticks: {
            callback: function(value) {
              return value + '%';
            }
          }
        },
        x: {
          grid: {
            display: false
          }
        }
      },
      plugins: {
        legend: {
          display: false
        }
      }
    }
  });
}



// Send analytics report to admin
function sendToAdmin() {
  if (confirm('Send this analytics report to the admin? This will create an announcement with your report data.')) {
    fetch('<?= base_url('teacher/analytics/send-to-admin') ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        '<?= config('Security')->headerName ?>': '<?= csrf_hash() ?>'
      },
      body: JSON.stringify({
        '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('Analytics report sent to admin successfully!');
      } else {
        alert('Error: ' + (data.error || 'Failed to send report'));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Failed to send report to admin');
    });
  }
}

// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  const totalStudents = <?= $totalStudents ?>;
  
  if (totalStudents > 0) {
    if (IS_DOMAIN_MODE) {
      initSymbolDistributionChart();
    } else {
      initGradeDistributionChart();
      initTermTrendsChart();
    }
    initAttendanceChart();
    initPerformanceChart();
  }
});
</script>

<?= $this->endSection() ?>



