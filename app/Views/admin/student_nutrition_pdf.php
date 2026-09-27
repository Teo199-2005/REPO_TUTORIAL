<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc(school_name()) ?> - Student Nutrition / BMI Report</title>
    <style>
<?= view('reports/_report_css', ['pageSize' => 'A4 landscape']) ?>

/* The 11-column health table needs a compact body; the letterhead, rules
   and footer follow the shared report family. */
.report-info { font-size: 8.5pt; text-align: center; margin-top: 0.6mm; }

table.data-table { width: 100%; border: 1pt solid #000; margin-top: 2.5mm; }
table.data-table th, table.data-table td {
    border: 0.5pt solid #000; padding: 1mm 1.4mm; font-size: 8pt;
    text-align: left; vertical-align: top;
}
table.data-table th {
    background: #EDEDED; font-weight: bold; text-transform: uppercase;
    font-size: 7.5pt; letter-spacing: 0.2pt; white-space: nowrap;
}
table.data-table thead { display: table-header-group; }
table.data-table tr { page-break-inside: avoid; }
    </style>
</head>
<body>
<?php
$logoB64 = school_logo_base64();
$sealUri = deped_seal_data_uri();
$schoolYearPretty = str_replace('-', '–', (string) $schoolYear);
?>
<?= view('reports/_letterhead', [
    'schoolName'  => school_name(),
    'reportTitle' => 'Student Nutrition / BMI Report',
    'schoolYear'  => $schoolYearPretty,
    'logoB64'     => $logoB64,
    'sealUri'     => $sealUri,
]) ?>
<div class="report-info">Generated: <?= esc($reportDate) ?></div>
<div class="report-info">Filters: <?= esc($filtersSummary) ?></div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>LRN</th>
                <th>Gr</th>
                <th>Section</th>
                <th>Age</th>
                <th>Sex</th>
                <th>H cm</th>
                <th>W kg</th>
                <th>BMI</th>
                <th>Ethnicity</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php helper('nutrition'); ?>
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
                $stLabel = ! empty($s['nutrition_status'])
                    ? \App\Libraries\StudentNutritionClassifier::statusLabel($s['nutrition_status'])
                    : '—';
                ?>
                <tr>
                    <td><?= esc($name) ?></td>
                    <td><?= esc((string) ($s['lrn'] ?? '')) ?></td>
                    <td><?= esc((string) ($s['grade_level'] ?? '')) ?></td>
                    <td><?= esc((string) ($s['section_name'] ?? '')) ?></td>
                    <td><?= esc($ageY) ?></td>
                    <td><?= esc(substr((string) ($s['gender'] ?? ''), 0, 1)) ?></td>
                    <td><?= esc((string) ($s['height_cm'] ?? '')) ?></td>
                    <td><?= esc((string) ($s['weight_kg'] ?? '')) ?></td>
                    <td><?= esc((string) ($s['bmi'] ?? '')) ?></td>
                    <td><?= esc(ethnicity_option_label($s['ethnicity'] ?? null)) ?></td>
                    <td><?= esc($stLabel) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<div class="foot">
    For school health screening and planning only &mdash; not a medical diagnosis. Confidential.<br>
    <?= esc(school_name()) ?> &middot; Generated <?= date('F j, Y \a\t g:i A') ?>
</div>
</body>
</html>
