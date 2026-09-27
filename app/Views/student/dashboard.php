
<?= $this->extend('dashboard_layout') ?>


<?= $this->section('content') ?>


<?php
helper('materials');
$studentDashboardMaterials = array_slice(public_website_materials(), 0, 9);
?>


<style>
/* Student stats: same card style as teacher dashboard */
/* Force white text on badges */
.badge.text-white {  color: #ffffff !important;}
.badge.bg-success.text-white {  background-color: #198754 !important;  color: #ffffff !important;}
.badge.bg-warning.text-white {  background-color: #ffc107 !important;  color: #ffffff !important;}
.badge.bg-info.text-white {  background-color: #0dcaf0 !important;  color: #ffffff !important;}
.student-widget-divider {  height: 1px;  width: 100%;  background: rgba(59, 130, 246, 0.25);  border-radius: 999px;}
/* Student dashboard hero header — single column */
.student-dash-hero {  display: flex !important;  flex-direction: column !important;  align-items: stretch !important;  gap: 0 !important;  padding: 1rem 1.15rem 0.9rem 1.25rem !important;  position: relative;  overflow: hidden;}
/* left accent bar removed per request */
body.dashboard-app .main-content .page-content .student-dash-hero h1 {  border-bottom: none !important;  margin-bottom: 0.2rem !important;  padding-bottom: 0 !important;  font-size: 1.35rem !important;  line-height: 1.25 !important;}
.student-dash-eyebrow {  display: inline-flex;  align-items: center;  gap: 0.35rem;  font-size: 0.7rem;  font-weight: 700;  letter-spacing: 0.08em;  text-transform: uppercase;  color: #2563eb;  background: rgba(37, 99, 235, 0.08);  border: 1px solid rgba(37, 99, 235, 0.18);  border-radius: 999px;  padding: 0.18rem 0.6rem;  margin-bottom: 0.45rem;  width: fit-content;}
.student-dash-eyebrow > i {  font-size: 0.8125rem;}
/* welcome and last-login lines removed per request */
.student-dash-hero .blue-divider {
  height: 1px;
  background: var(--hairline-strong);
/* Dashboard progress card equal-height CSS */
.student-dashboard-progress-col .student-progress-card {
  display: flex !important; flex-direction: column !important;
  flex: 1 1 auto; min-height: 0; overflow: visible;
}
.student-dashboard-progress-col .student-progress-card .card-body {
  flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0;
}
.student-dashboard-progress-col .student-progress-card .student-progress-inner {
  flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0;
}
.student-dashboard-progress-col .student-progress-card .mt-auto {
  margin-top: auto !important;
}
/* Health-profile toast — fixed overlay notification (top-right) */
.student-health-float {
  position: fixed;
  top: 84px;
  right: 20px;
  z-index: 1060;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  width: min(380px, calc(100vw - 40px));
  background: #fffbeb;
  border: 1px solid #fcd34d;
  border-radius: 14px;
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.18), 0 4px 10px rgba(0, 0, 0, 0.08);
  padding: 0.9rem 1rem;
  font-size: 0.88rem;
  color: #713f12;
  animation: shf-slide-in 0.45s cubic-bezier(0.21, 1.02, 0.73, 1) both;
}
@keyframes shf-slide-in {
  from { opacity: 0; transform: translateX(28px); }
  to   { opacity: 1; transform: translateX(0); }
}
.student-health-float.is-dismissed {
  animation: shf-slide-out 0.3s ease forwards;
}
@keyframes shf-slide-out {
  to { opacity: 0; transform: translateX(28px); visibility: hidden; }
}
.student-health-float > i { color: #d97706; }
.student-health-float strong { color: #92400e; }
.student-health-float-link { color: #92400e; font-weight: 700; text-decoration: underline; margin-left: 0.25rem; white-space: nowrap; }
.student-health-float-link:hover { color: #78350f; }
.student-health-float-close {
  margin-left: auto;
  flex-shrink: 0;
  background: transparent;
  border: 0;
  color: #b45309;
  font-size: 1.3rem;
  line-height: 1;
  padding: 0 0.2rem;
  cursor: pointer;
  border-radius: 6px;
}
.student-health-float-close:hover { background: rgba(180, 83, 9, 0.12); color: #78350f; }
@media (max-width: 575.98px) {
  .student-health-float { top: 74px; right: 12px; width: calc(100vw - 24px); }
}
</style>


<!-- Compact Header Section with Blue Divider -->


<div class="dashboard-header mb-4 student-dash-hero">
<span class="student-dash-eyebrow">
<i class="bi bi-mortarboard-fill"></i>Student Portal</span>
<h3 class="h3 fw-bold text-primary">Student Dashboard</h3>
<!-- Blue Divider Line -->

<div class="blue-divider"></div></div>
<?= view('partials/profile_unlock_card', ['student' => $student, 'context' => 'dashboard']) ?>

<!-- Quick Stats (same style as teacher dashboard) -->

<div class="row g-3 mb-3 student-stats-row">
<div class="col-12 col-sm-6 col-lg student-stat-col">
<div class="card h-100 border-0 shadow-sm">
<div class="card-body d-flex align-items-center gap-3 py-3">
<div class="dash-icon-tile dash-icon-tile--slate" aria-hidden="true">
<i class="bi bi-wallet2"></i>        </div>
<div class="flex-grow-1 min-width-0">
<div class="text-muted small">LRN</div>
<div class="fw-bold text-primary" style="font-size:1.15rem; line-height:1.2;">
<?= esc(!empty($student['lrn']) ? (string) $student['lrn'] : 'Pending') ?>
</div>        </div>      </div>    </div>  </div>
<div class="col-12 col-sm-6 col-lg student-stat-col">
<div class="card h-100 border-0 shadow-sm">
<div class="card-body d-flex align-items-center gap-3 py-3">
<div class="dash-icon-tile dash-icon-tile--emerald" aria-hidden="true">
<i class="bi bi-mortarboard-fill"></i>        </div>
<div class="flex-grow-1 min-width-0">
<div class="text-muted small">Current Grade Level</div>
<div class="fw-bold text-success" style="font-size:1.15rem; line-height:1.2;">
<?= esc(grade_level_label((int) ($student['grade_level'] ?? 0))) ?>
</div>        </div>      </div>    </div>  </div>
<div class="col-12 col-sm-6 col-lg student-stat-col">
<div class="card h-100 border-0 shadow-sm">
<div class="card-body d-flex align-items-center gap-3 py-3">
<div class="dash-icon-tile dash-icon-tile--cyan" aria-hidden="true">
<i class="bi bi-graph-up-arrow"></i>        </div>
<div class="flex-grow-1 min-width-0">
<div class="text-muted small">T
<?= (int) $currentTerm ?>
 Average</div>
<div class="fw-bold" style="font-size:1.15rem; line-height:1.2; color:#0aa2c0;">
<?= $termAverage !== null ? number_format($termAverage, 2) : 'N/A' ?>
</div>        </div>
<?php if ($termAverage !== null): ?>

<a class="btn btn-sm btn-outline-info flex-shrink-0" href="
<?= base_url('student/grades') ?>
">Grades</a>
<?php endif; ?>
      </div>    </div>  </div>
<div class="col-12 col-sm-6 col-lg student-stat-col">
<div class="card h-100 border-0 shadow-sm">
<div class="card-body d-flex align-items-center gap-3 py-3">
<div class="dash-icon-tile dash-icon-tile--amber" aria-hidden="true">
<i class="bi bi-person-check-fill"></i>        </div>
<div class="flex-grow-1 min-width-0">
<div class="text-muted small">Enrollment Status</div>
<div class="fw-bold" style="font-size:1.15rem; line-height:1.2; color:#b58100;">
<?= ucfirst(esc($student['enrollment_status'])) ?>
</div>        </div>      </div>    </div>  </div>
<div class="col-12 col-sm-6 col-lg student-stat-col">
<div class="card h-100 border-0 shadow-sm">
<div class="card-body d-flex align-items-center gap-3 py-3">
<div class="dash-icon-tile dash-icon-tile--violet" aria-hidden="true">
<i class="bi bi-calendar-event"></i>        </div>
<div class="flex-grow-1 min-width-0">
<div class="text-muted small">School Year</div>
<div class="fw-bold" style="font-size:1.15rem; line-height:1.2; color:#6f42c1;">
<?= get_current_school_year() ?>
</div>        </div>      </div>    </div>  </div></div>
<!-- Blue Divider -->

<div class="blue-divider mb-4"></div>
<?php  $taNumeric = $termAverage !== null ? (float) $termAverage : null;  $hasTermGrades = $taNumeric !== null;  $taBarWidth = $hasTermGrades ? min(100, max(0, $taNumeric)) : 0;  $taLabel = $hasTermGrades ? number_format($taNumeric, 2) . '%' : null;?>

<!-- Academic Progress + Featured (Featured larger) -->

<div class="row mb-4 student-dashboard-progress-row">
<div class="col-lg-5 order-2 order-lg-1 d-flex flex-column gap-3 student-dashboard-progress-col">
<!-- Widget 1: Overview — same flex height as term card on lg+ -->

<div class="card bg-white border-0 shadow-sm rounded-3 student-progress-card student-overview-card">
<div class="card-header bg-transparent border-0 pb-0 pt-3 px-3 flex-shrink-0">
<h3 class="card-title fw-semibold student-card-title"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i>          <span>Academic Progress Overview</span>        </h3>
</div>
<div class="card-body p-3 pt-2">
<div class="student-progress-inner student-overview-inner">          <div>
<div class="d-flex flex-wrap align-items-center student-overview-badges gap-2 mb-2">
<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
<i class="bi bi-calendar3 me-1"></i>School Year
<?= esc(get_current_school_year()) ?>
              </span>
<span class="badge bg-info-subtle text-primary border border-info-subtle rounded-pill">
<i class="bi bi-calendar2-week me-1"></i>Term
<?= (int) $currentTerm ?>
              </span>            </div>
<p class="text-muted student-overview-text mb-0">              Follow your standing for the selected year and term. Open Grades to see your subject-by-subject performance and remarks.            </p>
<?php $overviewGrades = $overviewGrades ?? []; ?>

<?php if (! empty($overviewGrades)): ?>

<ul class="student-overview-subjects">
<?php foreach ($overviewGrades as $ov): ?>

<?php $ovGraded = ($ov['grade'] ?? null) !== null; ?>

<li class="student-overview-subject">
<span class="student-overview-dot
<?= $ovGraded ? 'student-overview-dot--graded' : 'student-overview-dot--pending' ?>
" aria-hidden="true"></span>
<span class="student-overview-name" title="
<?= esc($ov['subject_name']) ?>
">
<?= esc($ov['subject_name']) ?>
</span>
<span class="student-overview-grade
<?= $ovGraded ? 'student-overview-grade--graded' : 'student-overview-grade--pending' ?>
">
<?= $ovGraded ? esc(number_format((float) $ov['grade'], 1)) : '--' ?>
                    </span>                  </li>
<?php endforeach; ?>

<?php if (($overviewTotalSubjects ?? 0) > count($overviewGrades)): ?>

<li class="student-overview-more">+
<?= (int) (($overviewTotalSubjects ?? 0) - count($overviewGrades)) ?>
 more subjects &middot;
<?= (int) ($overviewGradedCount ?? 0) ?>
/
<?= (int) ($overviewTotalSubjects ?? 0) ?>
 graded</li>
<?php else: ?>

<li class="student-overview-more">
<?= (int) ($overviewGradedCount ?? 0) ?>
/
<?= (int) ($overviewTotalSubjects ?? 0) ?>
 subjects graded</li>
<?php endif; ?>
              </ul>
<?php endif; ?>
          </div>
<div class="mt-auto student-progress-actions">
<div class="student-widget-divider"></div>
<div class="mt-auto d-flex flex-wrap align-items-center justify-content-between student-term-footnote overview-footnote">
<a href="
<?= base_url('student/grades') ?>
" class="btn btn-primary btn-sm">
<i class="bi bi-journal-text me-1"></i> View all grades              </a>
<div class="small text-muted student-meta-note">
<i class="bi bi-list-check" aria-hidden="true"></i>                <span>Quick breakdown per subject</span>              </div>            </div>          </div>        </div>      </div>    </div>
<!-- Widget 2: Current term -->

<div class="card bg-white border-0 shadow-sm rounded-3 student-progress-card">
<div class="card-header bg-transparent border-0 pb-0 pt-3 px-3 flex-shrink-0">
<h3 class="card-title fw-semibold student-card-title student-card-title--muted"><i class="bi bi-speedometer2" aria-hidden="true"></i>          <span>Current Term Performance</span>        </h3>
</div>
<div class="card-body p-3 pt-2">
<div class="student-progress-inner student-term-inner">
<?php if (!$hasTermGrades): ?>

<div class="h-100 d-flex flex-column">
<div class="rounded-3 border bg-light bg-opacity-50 student-term-empty d-flex flex-column flex-sm-row align-items-center">
<div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 student-term-empty-icon" style="background: rgba(108,117,125,0.15);color:#6c757d;">
<i class="bi bi-clipboard-data"></i>              </div>
<div class="flex-grow-1 text-center text-sm-start w-100">
<p class="mb-1 fw-semibold text-dark student-term-empty-title">No grades for Term
<?= (int) $currentTerm ?>
 yet</p>
<p class="text-muted student-term-empty-text">                  Teachers will post grades here once available. You can still open Grades anytime.                </p>
<div class="d-flex flex-wrap justify-content-center justify-content-sm-start align-items-center student-term-empty-badge">
<span class="badge
<?= esc($performanceMessage['class']) ?>
 d-inline-flex align-items-center gap-1">
<i class="bi
<?= esc($performanceMessage['icon'] ?? 'bi-info-circle') ?>
" aria-hidden="true"></i>                    <span>
<?= esc($performanceMessage['message']) ?>
</span>                  </span>                </div>              </div>            </div>
<div class="student-widget-divider"></div>
<div class="mt-auto d-flex flex-wrap align-items-center justify-content-between student-term-footnote">
<a href="
<?= base_url('student/grades') ?>
" class="btn btn-outline-secondary btn-sm">
<i class="bi bi-journal-text me-1"></i> Open Grades              </a>
<div class="small text-muted student-meta-note">
<i class="bi bi-arrow-repeat" aria-hidden="true"></i>                <span>Per-subject updates</span>              </div>            </div>          </div>
<?php else: ?>

<div class="h-100 d-flex flex-column">
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between student-term-top">              <div>
<div class="small text-muted mb-1">T
<?= (int) $currentTerm ?>
 average</div>
<div class="fw-bold text-primary student-term-average-big">
<?= esc(number_format($taNumeric, 2)) ?>
%</div>              </div>
<div class="performance-message flex-shrink-0 align-self-sm-center">
<span class="badge
<?= esc($performanceMessage['class']) ?>
 d-inline-flex align-items-center gap-1">
<i class="bi
<?= esc($performanceMessage['icon'] ?? 'bi-info-circle') ?>
" aria-hidden="true"></i>                  <span>
<?= esc($performanceMessage['message']) ?>
</span>                </span>              </div>            </div>
<div class="student-widget-divider"></div>
<div class="progress mb-0 student-term-progress">
<div                  class="progress-bar
<?= $taNumeric >= 75 ? 'bg-success' : ($taNumeric >= 60 ? 'bg-info' : 'bg-warning') ?>
"                  role="progressbar"                  style="width:
<?= $taBarWidth ?>
%"                  aria-valuenow="
<?= $taBarWidth ?>
"                  aria-valuemin="0"                  aria-valuemax="100"                  aria-valuetext="
<?= number_format($taNumeric, 2) ?>
 percent"                ></div>              </div>
<div class="d-flex justify-content-between text-muted student-term-scale">              <span>Keep improving</span>              <span>Great performance</span>            </div>
<div class="mt-auto text-muted student-term-foot">              Open Grades for full subject breakdown and remarks.            </div>          </div>
<?php endif; ?>
        </div>      </div>    </div>  </div>
<div class="col-lg-7 order-1 order-lg-2 d-flex">
<div class="card student-featured-card shadow-sm rounded-3 overflow-hidden flex-fill">
<div class="featured-student-banner">
<?php if (! empty($featuredPosterStudentUrl)): ?>
          <img            src="
<?= esc($featuredPosterStudentUrl) ?>
"            alt=""            role="presentation"            onerror="this.removeAttribute('src'); this.style.display='none';"          >
<?php endif; ?>

<div style="position:absolute; left:16px; bottom:16px; color:white; z-index:2;">
<div style="font-weight: 700; font-size:1.35rem; line-height:1.2;">Featured</div>
<div style="font-size:1rem; opacity:0.95;">Student Dashboard</div>
<?php if (empty($featuredPosterStudentUrl)): ?>

<div style="font-size:0.875rem; opacity:0.85;">(No poster uploaded yet)</div>
<?php endif; ?>
        </div>      </div>    </div>  </div></div>
<?= $this->endSection() ?>

<?= $this->section('portal_overlays') ?>

<?php if (! empty($nutrition_profile_incomplete)): ?>
<!-- Health-profile toast notification — fixed overlay, top-right -->
<div class="student-health-float" role="alert" aria-live="polite">
<i class="bi bi-heart-pulse fs-4 flex-shrink-0 mt-1" aria-hidden="true"></i>
<div>
<strong>Health profile needed.</strong> Please complete your height, weight, and ethnicity on your profile so the school can keep accurate wellness records.
<a href="<?= base_url('student/profile') ?>" class="student-health-float-link">Go to My Profile</a>
</div>
<button type="button" class="student-health-float-close" aria-label="Dismiss notification">&times;</button>
</div>
<script>
(function () {
  var toast = document.querySelector('.student-health-float');
  if (!toast) return;
  toast.querySelector('.student-health-float-close').addEventListener('click', function () {
    toast.classList.add('is-dismissed');
    toast.addEventListener('animationend', function () { toast.remove(); });
  });
})();
</script>
<?php endif; ?>

<?= view('partials/public_materials_modal') ?>

<?= $this->endSection() ?>


