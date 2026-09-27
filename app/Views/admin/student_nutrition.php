<?= $this->extend('dashboard_layout') ?>
<?= $this->section('content') ?>
<?php helper('nutrition'); ?>

<?php
  // Filter state is resolved first so both the header (export link, active
  // filter summary) and the filter bar below read from the same values.
  $nutFilterValues = [
    'grade_level'      => (string) ($filter_grade ?? ''),
    'section_id'       => (string) ($filter_section ?? ''),
    'nutrition_status' => (string) ($filter_status ?? ''),
    'q'                => (string) ($filter_q ?? ''),
  ];

  $nutActive = admin_filter_count_active(
    $nutFilterValues,
    ['grade_level', 'section_id', 'nutrition_status', 'q']
  );

  $nutPdfParams = admin_filter_query($nutFilterValues, ['grade_level', 'section_id', 'nutrition_status', 'q']);
  $nutPdfUrl    = base_url('admin/student-nutrition/export-pdf')
    . ($nutPdfParams !== [] ? '?' . http_build_query($nutPdfParams) : '');

  $nutSubtitle = 'Enrolled students, their BMI and nutrition screening. The export respects the filters below.';
  if ($nutActive['total'] > 0) {
    $nutSubtitle .= ' ' . (string) ($filtersSummary ?? '');
  }

  echo view('admin/partials/page_header', ['pageHeader' => [
    'icon'     => 'bi-heart-pulse',
    'title'    => 'Student nutrition / BMI',
    'subtitle' => $nutSubtitle,
    'actions'  => '<a class="btn btn-outline-primary" href="' . esc($nutPdfUrl) . '" target="_blank" rel="noopener">'
      . '<i class="bi bi-file-earmark-pdf"></i> Export PDF</a>',
  ]]);
?>

<!-- Filters — shared admin standard -->
<?php
  $nutGradeOptions = [admin_filter_option('', 'All grade levels')];
  foreach (grade_level_options() as $g) {
    $nutGradeOptions[] = admin_filter_option((string) $g, grade_level_label($g));
  }

  $nutSectionOptions = [admin_filter_option('', 'All sections')];
  foreach ($sections as $sec) {
    $nutSectionOptions[] = admin_filter_option(
      (string) $sec['id'],
      'G' . $sec['grade_level'] . ' — ' . $sec['section_name']
    );
  }

  $nutStatusOptions = [];
  foreach (nutrition_status_filter_options() as $val => $lab) {
    $nutStatusOptions[] = admin_filter_option((string) $val, (string) $lab);
  }

  echo view('admin/partials/filter_bar', ['filterBar' => [
    'action'      => base_url('admin/student-nutrition'),
    'id'          => 'nutritionFilter',
    'label'       => 'Filter the nutrition and BMI list',
    'resetUrl'    => base_url('admin/student-nutrition'),
    'activeCount' => $nutActive['total'],
    'totalCount'  => $nutActive['total'],
    'primary'     => [
      [
        'name' => 'q', 'label' => 'Search', 'icon' => 'bi-search', 'type' => 'search',
        'value'       => admin_filter_value($nutFilterValues, 'q'),
        'placeholder' => 'Name or LRN',
      ],
      [
        'name' => 'grade_level', 'label' => 'Grade level', 'icon' => 'bi-mortarboard',
        'value'   => admin_filter_value($nutFilterValues, 'grade_level'),
        'options' => $nutGradeOptions,
      ],
      [
        'name' => 'section_id', 'label' => 'Section', 'icon' => 'bi-people',
        'value'   => admin_filter_value($nutFilterValues, 'section_id'),
        'options' => $nutSectionOptions,
      ],
      [
        'name' => 'nutrition_status', 'label' => 'Nutrition status', 'icon' => 'bi-heart-pulse',
        'value'   => admin_filter_value($nutFilterValues, 'nutrition_status'),
        'options' => $nutStatusOptions,
      ],
    ],
    'advanced' => [],
  ]]);
?>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 admin-table" data-js-paged="1">
        <thead class="table-light">
          <tr>
            <th><i class="bi bi-person me-1 text-muted"></i>Name</th>
            <th><i class="bi bi-hash me-1 text-muted"></i>LRN</th>
            <th><i class="bi bi-mortarboard me-1 text-muted"></i>Grade</th>
            <th><i class="bi bi-people me-1 text-muted"></i>Section</th>
            <th><i class="bi bi-calendar3 me-1 text-muted"></i>Age (y)</th>
            <th><i class="bi bi-gender-ambiguous me-1 text-muted"></i>Sex</th>
            <th><i class="bi bi-arrows-vertical me-1 text-muted"></i>Height</th>
            <th><i class="bi bi-speedometer2 me-1 text-muted"></i>Weight</th>
            <th><i class="bi bi-calculator me-1 text-muted"></i>BMI</th>
            <th><i class="bi bi-globe me-1 text-muted"></i>Ethnicity</th>
            <th><i class="bi bi-activity me-1 text-muted"></i>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($students === []): ?>
            <tr><td colspan="11" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No students match these filters.</td></tr>
          <?php endif; ?>
          <?php foreach ($students as $s): ?>
            <?php
            $ageY = '—';
            if (! empty($s['date_of_birth'])) {
                try {
                    $dob = new \DateTimeImmutable($s['date_of_birth']);
                    $ageY = (string) $dob->diff(new \DateTimeImmutable('today'))->y;
                } catch (\Throwable) {
                    $ageY = '—';
                }
            }
            $name = trim($s['first_name'] . ' ' . ($s['middle_name'] ? $s['middle_name'] . ' ' : '') . $s['last_name']);
            ?>
            <tr>
              <td><?= esc($name) ?></td>
              <td><?= esc((string) ($s['lrn'] ?? '')) ?></td>
              <td><?= esc((string) ($s['grade_level'] ?? '')) ?></td>
              <td><?= esc((string) ($s['section_name'] ?? '—')) ?></td>
              <td><?= esc($ageY) ?></td>
              <td><?= esc((string) ($s['gender'] ?? '')) ?></td>
              <td><?= $s['height_cm'] !== null && $s['height_cm'] !== '' ? esc((string) $s['height_cm']) : '—' ?></td>
              <td><?= $s['weight_kg'] !== null && $s['weight_kg'] !== '' ? esc((string) $s['weight_kg']) : '—' ?></td>
              <td><?= $s['bmi'] !== null && $s['bmi'] !== '' ? esc((string) $s['bmi']) : '—' ?></td>
              <td><?= esc(ethnicity_option_label($s['ethnicity'] ?? null)) ?></td>
              <td>
                <?php if (! empty($s['nutrition_status'])): ?>
                  <span class="badge bg-secondary"><?= esc(\App\Libraries\StudentNutritionClassifier::statusLabel($s['nutrition_status'])) ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?= $this->endSection() ?>
