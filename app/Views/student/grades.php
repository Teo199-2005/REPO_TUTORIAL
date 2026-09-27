<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>

<style>
/* Force white text on "Not recorded" badges */
.badge.bg-secondary.text-white {
  color: #ffffff !important;
  background-color: #6c757d !important;
}

/* Fix modal z-index issues */
.modal-backdrop {
  z-index: 1050 !important;
}

#enrollmentConfirmModal {
  z-index: 1055 !important;
}

#enrollmentConfirmModal .modal-dialog {
  z-index: 1056 !important;
}

#enrollmentConfirmModal .modal-content {
  z-index: 1057 !important;
  pointer-events: auto !important;
}
</style>

<!-- Compact Header Section with Blue Divider -->
<div class="dashboard-header mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 fw-bold text-primary mb-1">My Academic Grades</h1>
      <p class="text-muted mb-0 small">Track your academic progress and performance</p>
    </div>
    <div class="d-flex gap-2">
      <?php if (($student['can_view_report_card'] ?? 1) == 1): ?>
        <a href="<?= base_url('student/report-card') ?>" class="btn btn-success btn-sm">
          <i class="bi bi-file-earmark-pdf me-2"></i>View Report Card
        </a>
      <?php else: ?>
        <button class="btn btn-secondary btn-sm" disabled title="Report card access disabled by teacher">
          <i class="bi bi-file-earmark-pdf me-2"></i>View Report Card
        </button>
      <?php endif; ?>
      <?php if (!empty($isNonNumerical) && ($student['can_view_report_card'] ?? 1) == 1): ?>
        <a href="<?= base_url('student/learner-development-report') ?>" class="btn btn-outline-success btn-sm" target="_blank" title="Values, conduct and character development (three terms)">
          <i class="bi bi-person-heart me-2"></i>Learner Development Report
        </a>
      <?php endif; ?>
      <a href="<?= base_url('student/dashboard') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
      </a>
    </div>
  </div>

  <!-- Blue Divider Line -->
  <div class="blue-divider"></div>
</div>

<!-- Compact Stats Cards -->
<?php if (!empty($isNonNumerical)): ?>
<div class="d-flex gap-3 mb-4">
  <div class="stats-card bg-white border-0 shadow-sm rounded-3 flex-fill">
    <div class="card-body text-center p-3">
      <div class="stats-icon bg-primary bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
        <i class="bi bi-list-check text-white fs-5"></i>
      </div>
      <h4 class="stats-number text-primary mb-1 small"><?= (int) $domainSummary['assessed'] ?><?= (int) $domainSummary['totalIndicators'] > 0 ? ' / ' . (int) $domainSummary['totalIndicators'] : '' ?></h4>
      <p class="stats-label text-muted fw-medium mb-0 small">Indicators Assessed</p>
    </div>
  </div>

  <div class="stats-card bg-white border-0 shadow-sm rounded-3 flex-fill">
    <div class="card-body text-center p-3">
      <div class="stats-icon bg-success bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
        <i class="bi bi-graph-up-arrow text-white fs-5"></i>
      </div>
      <h4 class="stats-number text-success mb-1 small"><?= number_format($domainSummary['masteryRate'], 1) ?>%</h4>
      <p class="stats-label text-muted fw-medium mb-0 small">Mastery (P + AP)</p>
    </div>
  </div>

  <div class="stats-card bg-white border-0 shadow-sm rounded-3 flex-fill">
    <div class="card-body text-center p-3">
      <div class="stats-icon bg-info bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
        <i class="bi bi-calendar-check text-white fs-5"></i>
      </div>
      <h4 class="stats-number text-info mb-1 small"><?= (int) $domainSummary['quartersDone'] ?> / 4</h4>
      <p class="stats-label text-muted fw-medium mb-0 small">Quarters Recorded</p>
    </div>
  </div>

  <div class="stats-card bg-white border-0 shadow-sm rounded-3 flex-fill">
    <div class="card-body text-center p-3">
      <div class="stats-icon bg-warning bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
        <i class="bi bi-award-fill text-white fs-5"></i>
      </div>
      <h4 class="stats-number text-warning mb-1 small"><?= (int) $domainSummary['symbols']['P'] ?></h4>
      <p class="stats-label text-muted fw-medium mb-0 small">Proficient (P)</p>
    </div>
  </div>
</div>
<?php
  // Tappy celebrates a good term and encourages a struggling one. The band
  // follows the DepEd grading scale already used for the pass mark below.
  $gwaValue = is_numeric($gwa) ? (float) $gwa : null;
  $gwaBand  = $gwaValue === null ? 'none' : ($gwaValue >= 90 ? 'outstanding' : ($gwaValue >= 85 ? 'veryGood' : ($gwaValue >= 75 ? 'good' : 'needsHelp')));
  $gwaArt = [
      'outstanding' => ['medal', 'Outstanding!', 'An excellent result. Keep it up — that is a wonderful term.', 'top'],
      'veryGood'    => ['celebrate', 'Very good work', 'That is a strong result. A little more effort and you are at the top.', 'award'],
      'good'        => ['thumbs-up', 'You passed', 'Good job. Keep studying and you will climb higher next term.', 'done'],
      'needsHelp'   => ['thinking', 'Let us work on this', 'This term was below the passing mark. Please talk to your adviser about how to improve.', 'warn'],
      'none'        => ['reading', 'No grades yet', 'Grades will appear here as soon as your teachers enter them.', 'tip'],
  ][$gwaBand];
?>
<?php if ($gwaBand !== 'none'): ?>
  <div class="mascot-achievement">
    <?= mascot_img(['name' => $gwaArt[0], 'alt' => '', 'size' => 86]) ?>
    <div>
      <?php /* The character reacts; the sticker names the result. Between them the
                 band is readable before a single word is. */ ?>
      <div class="mascot-achievement__title">
        <?= mascot_sticker_for($gwaArt[3], ['class' => 'mascot-chip mascot-chip--sm']) ?>
        <?= esc($gwaArt[1]) ?>
      </div>
      <div class="mascot-achievement__text"><?= esc($gwaArt[2]) ?></div>
    </div>
  </div>
<?php endif; ?>

<?php else: ?>
<div class="d-flex gap-3 mb-4">
  <div class="stats-card bg-white border-0 shadow-sm rounded-3 flex-fill">
    <div class="card-body text-center p-3">
      <div class="stats-icon bg-success bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
        <i class="bi bi-trophy-fill text-white fs-5"></i>
      </div>
      <h4 class="stats-number text-success mb-1 small"><?= $gwa !== null ? number_format($gwa, 2) : 'N/A' ?></h4>
      <p class="stats-label text-muted fw-medium mb-0 small">General Weighted Average</p>
    </div>
  </div>

  <?php for ($t = 1; $t <= 3; $t++): ?>
    <div class="stats-card bg-white border-0 shadow-sm rounded-3 flex-fill">
      <div class="card-body text-center p-3">
        <div class="stats-icon bg-primary bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
          <i class="bi bi-calendar-check text-white fs-5"></i>
        </div>
        <h4 class="stats-number text-primary mb-1 small">
          <?= isset($allTermGrades[$t]) && $allTermGrades[$t] !== null
              ? number_format($allTermGrades[$t], 1)
              : '--' ?>
        </h4>
        <p class="stats-label text-muted fw-medium mb-0 small">Term <?= $t ?></p>
      </div>
    </div>
  <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Blue Divider -->
<div class="blue-divider mb-4"></div>

<!-- Next School Year / Promotion Status -->
<?php $appStatus = $promotion['application']['status'] ?? null; ?>
<?php if ($promotion['isGraduating']): ?>
  <?php if ((int) $promotion['gradeLevel'] === 6): ?>
    <div class="card border-0 shadow-sm rounded-3 mb-4" style="background: linear-gradient(135deg, #d1e7dd, #eef8f2);">
      <div class="card-body p-4 text-center">
        <i class="bi bi-award-fill text-success fs-1"></i>
        <h4 class="fw-bold text-success mt-2 mb-1">Congratulations, <?= esc($student['first_name']) ?>!</h4>
        <p class="text-muted mb-0 small">You have successfully completed Grade 6 &mdash; the highest grade level offered at CSCS. You are eligible to graduate! 🎓</p>
      </div>
    </div>
  <?php else: ?>
    <div class="card border-0 shadow-sm rounded-3 mb-4">
      <div class="card-body p-4 text-center">
        <i class="bi bi-info-circle text-info fs-1"></i>
        <h4 class="fw-bold text-info mt-2 mb-1">You have completed the highest level offered</h4>
        <p class="text-muted mb-0 small">Your adviser will coordinate with your family regarding your next steps. No further enrollment application is needed.</p>
      </div>
    </div>
  <?php endif; ?>
<?php elseif ($appStatus === 'pending'): ?>
  <div class="alert alert-info d-flex align-items-center gap-3 shadow-sm rounded-3 mb-4 py-3">
    <i class="bi bi-hourglass-split fs-3"></i>
    <div>
      <strong>Application Pending</strong>
      <div class="small">Your application to enroll in <?= esc($promotion['nextGradeLabel']) ?> for S.Y. <?= esc($promotion['nextSchoolYear']) ?> is awaiting approval from the school admin. Once approved, you will be unassigned from your current section and placed in a new one when classes open.</div>
    </div>
  </div>
<?php elseif ($appStatus === 'approved'): ?>
  <div class="alert alert-success d-flex align-items-center gap-3 shadow-sm rounded-3 mb-4 py-3">
    <i class="bi bi-check-circle-fill fs-3"></i>
    <div>
      <strong>Approved!</strong>
      <div class="small">Your enrollment to <?= esc($promotion['nextGradeLabel']) ?> for S.Y. <?= esc($promotion['nextSchoolYear']) ?> has been approved. You will be assigned to a section when the new school year starts.</div>
    </div>
  </div>
<?php elseif ($appStatus === 'rejected'): ?>
  <div class="alert alert-warning d-flex align-items-center gap-3 shadow-sm rounded-3 mb-4 py-3">
    <i class="bi bi-exclamation-triangle-fill fs-3"></i>
    <div class="flex-grow-1">
      <strong>Application Not Approved</strong>
      <div class="small">Your application for S.Y. <?= esc($promotion['nextSchoolYear']) ?> was not approved. Please come and talk to your adviser to discuss your options.</div>
    </div>
    <?php if ($promotion['eligibleToApply']): ?>
      <button type="button" class="btn btn-outline-success btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#enrollNextYearModal">
        <i class="bi bi-arrow-clockwise me-1"></i>Apply Again
      </button>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php if ($promotion['eligibleToApply'] && $appStatus !== 'approved'): ?>
  <div class="card border-0 shadow-sm rounded-3 mb-4" style="background: linear-gradient(135deg, #d1e7dd, #eef8f2);">
    <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h4 class="fw-bold text-success mb-1"><i class="bi bi-trophy-fill me-2"></i><?= $promotion['isNonNumerical'] ? 'You completed all quarters!' : 'You passed all terms!' ?></h4>
        <p class="text-muted mb-0 small">
          <?php if ($gwa !== null): ?>
            General Weighted Average: <strong><?= number_format((float) $gwa, 2) ?></strong> &mdash;
          <?php else: ?>
            Your progress was completed with developmental symbols &mdash;
          <?php endif; ?>
          You are eligible to enroll in
          <strong><?= esc($promotion['nextGradeLabel']) ?></strong> for S.Y. <strong><?= esc($promotion['nextSchoolYear']) ?></strong>.
          Submit your application below; the school admin will review and approve it.
        </p>
      </div>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#enrollNextYearModal">
        <i class="bi bi-mortarboard-fill me-2"></i><?= $appStatus === 'rejected' ? 'Re-apply to ' : 'Enroll to ' ?><?= esc($promotion['nextGradeLabel']) ?>
      </button>
    </div>
  </div>
<?php endif; ?>
<?php if (empty($appStatus) && !$promotion['eligibleToApply'] && $promotion['failed']): ?>
  <div class="alert alert-warning d-flex align-items-center gap-3 shadow-sm rounded-3 mb-4 py-3">
    <i class="bi bi-exclamation-triangle-fill fs-3"></i>
    <div>
      <strong>Academic Attention Needed</strong>
      <div class="small">Your General Weighted Average is <strong><?= number_format((float) $gwa, 2) ?></strong>, which is below the passing mark of 75. Please come and talk to your adviser to discuss your academic standing and your options for the next school year.</div>
    </div>
  </div>
<?php elseif (empty($appStatus) && !$promotion['eligibleToApply'] && $promotion['isNonNumerical'] && !$promotion['termsCompleted']): ?>
  <div class="alert alert-info d-flex align-items-center gap-3 shadow-sm rounded-3 mb-4 py-3">
    <i class="bi bi-info-circle fs-3"></i>
    <div>
      <strong>Progress Recorded by Symbols</strong>
      <div class="small">Your section uses developmental symbols (Proficient / Approaching Proficiency / Developing / Beginning) instead of numeric grades. Once your adviser completes your quarterly records (<?= (int) $promotion['snedQuartersDone'] ?> of <?= (int) $promotion['snedQuartersTotal'] ?> quarters recorded), you may apply for promotion. Your adviser and the school admin will review your progress.</div>
    </div>
  </div>
<?php endif; ?>

<!-- Developmental Progress (non-numerical sections) -->
<?php if (!empty($isNonNumerical)): ?>
<div class="card bg-white border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-transparent border-0 p-3">
    <h4 class="card-title mb-0 small">
      <i class="bi bi-list-check me-2 dash-icon-inline"></i>
      Developmental Progress &mdash; All Quarters
    </h4>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th class="border-0 fw-medium small">Domain</th>
            <th class="border-0 fw-medium small">Performance Indicator</th>
            <th class="border-0 fw-medium small text-center">Q1</th>
            <th class="border-0 fw-medium small text-center">Q2</th>
            <th class="border-0 fw-medium small text-center">Q3</th>
            <th class="border-0 fw-medium small text-center">Q4</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($domainProgress)): ?>
            <?php foreach ($domainProgress as $domain): ?>
              <?php foreach ($domain['fields'] as $fieldIndex => $field): ?>
                <tr>
                  <?php if ($fieldIndex === 0): ?>
                  <td class="py-2 fw-medium text-dark small align-middle" rowspan="<?= count($domain['fields']) ?>"><?= esc($domain['name']) ?></td>
                  <?php endif; ?>
                  <td class="py-2 small"><?= esc($field['name']) ?></td>
                  <?php for ($q = 1; $q <= 4; $q++):
                      $sym = $field['symbols'][$q] ?? null;
                      $symBadge = ['P' => 'success', 'AP' => 'primary', 'D' => 'warning', 'B' => 'danger', 'NO/NA' => 'secondary'][$sym] ?? null;
                  ?>
                  <td class="py-2 text-center">
                    <?php if ($symBadge): ?>
                      <span class="badge bg-<?= $symBadge ?> small" style="color: white !important;"><?= esc($sym) ?></span>
                    <?php else: ?>
                      <span class="text-muted small">--</span>
                    <?php endif; ?>
                  </td>
                  <?php endfor; ?>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                <small>No developmental domains configured for this section yet.</small>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-light border-0 p-3">
    <div class="row align-items-center">
      <div class="col">
        <strong class="text-primary small">Quarters recorded:</strong>
        <span class="ms-2 fw-bold"><?= (int) $domainSummary['quartersDone'] ?> of 4</span>
        <span class="mx-2 text-muted">&middot;</span>
        <strong class="text-primary small">Indicators assessed:</strong>
        <span class="ms-2 fw-bold"><?= (int) $domainSummary['assessed'] ?><?= (int) $domainSummary['totalIndicators'] > 0 ? ' of ' . (int) $domainSummary['totalIndicators'] : '' ?></span>
      </div>
      <div class="col-auto">
        <span class="badge bg-primary small" style="color: white !important;">
          Mastery (P+AP): <?= number_format($domainSummary['masteryRate'], 1) ?>%
        </span>
      </div>
    </div>
    <div class="small text-muted mt-2">
      Symbols:
      <span class="badge bg-success small" style="color: white !important;">P</span> Proficient
      <span class="badge bg-primary small" style="color: white !important;">AP</span> Approaching Proficiency
      <span class="badge bg-warning small" style="color: white !important;">D</span> Developing
      <span class="badge bg-danger small" style="color: white !important;">B</span> Beginning
      <span class="badge bg-secondary small" style="color: white !important;">NO/NA</span> Not Observed/Not Applicable
    </div>
  </div>
</div>
<?php else: ?>
<!-- Grades Table -->
<div class="card bg-white border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-transparent border-0 p-3">
    <h4 class="card-title mb-0 small">
      <i class="bi bi-list-check me-2 dash-icon-inline"></i>
      Term <?= esc($term) ?> Grades
    </h4>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th class="border-0 fw-medium small">Subject</th>
            <th class="border-0 fw-medium small">Code</th>
            <th class="border-0 fw-medium small text-center">Grade</th>
            <th class="border-0 fw-medium small">Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($grades)): ?>
            <?php foreach ($grades as $row): ?>
              <tr>
                <td class="py-2">
                  <div class="fw-medium text-dark small"><?= esc($row['subject']['subject_name']) ?></div>
                </td>
                <td class="py-2">
                  <span class="badge bg-light text-dark small"><?= esc($row['subject']['subject_code']) ?></span>
                </td>
                <td class="py-2 text-center">
                  <?php if ($row['grade'] && $row['grade']['grade'] !== null): ?>
                    <?php
                      $grade = $row['grade']['grade'];
                      $badgeClass = $grade >= 90 ? 'success' : ($grade >= 85 ? 'info' : ($grade >= 75 ? 'warning' : 'danger'));
                    ?>
                    <span class="badge bg-<?= $badgeClass ?> small" style="color: white !important;">
                      <?= number_format($grade, 2) ?>
                    </span>
                  <?php else: ?>
                    <span class="badge bg-secondary small" style="color: white !important;">Not recorded</span>
                  <?php endif; ?>
                </td>
                <td class="py-2">
                  <?php if ($row['grade'] && isset($row['grade']['remarks'])): ?>
                    <span class="text-muted small"><?= esc($row['grade']['remarks']) ?></span>
                  <?php else: ?>
                    <span class="text-muted small">--</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" class="text-center py-4 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                <small>No grades recorded for this term yet.</small>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Term Summary -->
  <div class="card-footer bg-light border-0 p-3">
    <div class="row align-items-center">
      <div class="col">
        <strong class="text-primary small">Term <?= esc($term) ?> Average:</strong>
        <span class="ms-2 fw-bold">
          <?= $termAverage !== null ? number_format($termAverage, 2) : 'N/A' ?>
        </span>
      </div>
      <div class="col-auto">
        <?php if ($termAverage !== null): ?>
          <?php
            $avgBadgeClass = $termAverage >= 90 ? 'success' : ($termAverage >= 85 ? 'info' : ($termAverage >= 75 ? 'warning' : 'danger'));
            $avgMessage = $termAverage >= 90 ? 'Excellent' : ($termAverage >= 85 ? 'Very Good' : ($termAverage >= 75 ? 'Good' : 'Needs Improvement'));
          ?>
          <span class="badge bg-<?= $avgBadgeClass ?> small" style="color: white !important;">
            <?= $avgMessage ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>







<?php if ($promotion['eligibleToApply']): ?>
<!-- Enroll to Next Grade Confirmation Modal -->
<div class="modal fade" id="enrollNextYearModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-3">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-mortarboard-fill text-success me-2"></i>Enroll to <?= esc($promotion['nextGradeLabel']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Submit your application to enroll in <strong><?= esc($promotion['nextGradeLabel']) ?></strong> for S.Y. <strong><?= esc($promotion['nextSchoolYear']) ?></strong>?</p>
        <p class="mb-0 text-muted small">The school admin will review your application. Once approved, you will be moved to <?= esc($promotion['nextGradeLabel']) ?> and unassigned from your current section until a new section is assigned.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="applyNextYearBtn">
          <i class="bi bi-send me-2"></i>Submit Application
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const applyBtn = document.getElementById('applyNextYearBtn');
  if (!applyBtn) return;

  applyBtn.addEventListener('click', async function () {
    const original = applyBtn.innerHTML;
    applyBtn.disabled = true;
    applyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

    try {
      const res = await fetch('<?= base_url('student/apply-next-year') ?>', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
        }
      });
      const data = await res.json();

      const modalEl = document.getElementById('enrollNextYearModal');
      if (modalEl && window.bootstrap) {
        bootstrap.Modal.getInstance(modalEl)?.hide();
      }

      if (data.success) {
        showToast('success', data.message || 'Application submitted!');
        setTimeout(() => window.location.reload(), 1800);
      } else if (res.status === 409 || /already have a .* application/i.test(data.error || '')) {
        showToast('warning', data.error || 'You already have a pending application.');
      } else {
        showToast('danger', data.error || 'Failed to submit application.');
      }
    } catch (e) {
      showToast('danger', 'An error occurred while submitting your application. Please try again.');
    } finally {
      applyBtn.disabled = false;
      applyBtn.innerHTML = original;
    }
  });

  /**
   * Toast notification (top-right, auto-dismiss) - replaces native alert()
   * dialogs for application feedback.
   */
  function showToast(type, message) {
    let container = document.getElementById('studentToastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'studentToastContainer';
      container.className = 'toast-container position-fixed top-0 end-0 p-3';
      container.style.zIndex = '20000';
      document.body.appendChild(container);
    }

    const styles = {
      success: { bg: 'bg-success', icon: 'bi-check-circle-fill' },
      warning: { bg: 'bg-warning text-dark', icon: 'bi-exclamation-triangle-fill' },
      danger:  { bg: 'bg-danger', icon: 'bi-exclamation-circle-fill' },
      info:    { bg: 'bg-primary', icon: 'bi-info-circle-fill' }
    };
    const s = styles[type] || styles.info;

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white ${s.bg} border-0 shadow`;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">
          <i class="bi ${s.icon} me-2"></i>${String(message).replace(/\n/g, '<br>')}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>`;

    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: type === 'danger' ? 8000 : 5000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove(), { once: true });
  }
});
</script>
<?php endif; ?>

<?= $this->endSection() ?>