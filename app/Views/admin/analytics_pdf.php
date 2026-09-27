<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc(school_name()) ?> - Analytics Report</title>
    <style>
<?= view('reports/_report_css') ?>

/* Analytics-specific rules: the letterhead, section titles and footer come
   from the shared report stylesheet so every export matches the report card
   family; only the summary blocks below are local. */
.report-info { font-size: 9.5pt; text-align: center; margin-top: 0.8mm; }

.section { margin: 4mm 0; page-break-inside: avoid; }

table.data-table { width: 100%; border: 1pt solid #000; margin: 2.5mm 0; }
table.data-table th, table.data-table td {
    border: 0.5pt solid #000; padding: 1.6mm 2mm; text-align: left; font-size: 10pt;
}
table.data-table th {
    background: #EDEDED; font-weight: bold; text-transform: uppercase;
    font-size: 9.5pt; letter-spacing: 0.2pt;
}
table.data-table thead { display: table-header-group; }
table.data-table tr { page-break-inside: avoid; }

.metric-grid { display: table; width: 100%; border: 1pt solid #000; border-collapse: collapse; margin: 2.5mm 0; page-break-inside: avoid; }
.metric-row { display: table-row; }
.metric-label, .metric-value { display: table-cell; border: 0.5pt solid #000; padding: 1.8mm 2.5mm; font-size: 10pt; }
.metric-label {
    width: 60%; background: #EDEDED; font-weight: bold; text-transform: uppercase;
    font-size: 9.5pt; letter-spacing: 0.2pt;
}
.metric-value { width: 40%; text-align: right; font-weight: bold; }

.summary-box { border: 1pt solid #000; padding: 3mm 3.5mm; margin: 2.5mm 0; background: #F7F7F7; page-break-inside: avoid; }
.summary-box p { margin: 0 0 1.3mm 0; }
.summary-box p:last-child { margin-bottom: 0; }

.page-break { page-break-before: always; }
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
    'reportTitle' => 'Analytics Report',
    'schoolYear'  => $schoolYearPretty,
    'logoB64'     => $logoB64,
    'sealUri'     => $sealUri,
]) ?>
<div class="report-info">Term: T<?= esc((string) ($currentTerm ?? 1)) ?> &middot; Report Generated: <?= esc($reportDate) ?></div>

    <div class="section">
        <div class="section-title">Executive Summary</div>
        <div class="summary-box">
            <p><strong>Total Students:</strong> <?= array_sum($statusDistribution) ?></p>
            <p><strong>Enrolled Students:</strong> <?= $statusDistribution['enrolled'] ?></p>
            <p><strong>Pending Applications:</strong> <?= $statusDistribution['pending'] ?></p>
            <p><strong>Completion Rate:</strong> <?= $metrics['completionRate'] ?>%</p>
            <p><strong>Gender Balance:</strong> <?= $genderDistribution['male'] ?> Male, <?= $genderDistribution['female'] ?> Female</p>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Enrollment Status Distribution</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Enrolled</td>
                    <td><?= $statusDistribution['enrolled'] ?></td>
                    <td><?= array_sum($statusDistribution) > 0 ? round(($statusDistribution['enrolled'] / array_sum($statusDistribution)) * 100, 1) : 0 ?>%</td>
                </tr>
                <tr>
                    <td>Pending</td>
                    <td><?= $statusDistribution['pending'] ?></td>
                    <td><?= array_sum($statusDistribution) > 0 ? round(($statusDistribution['pending'] / array_sum($statusDistribution)) * 100, 1) : 0 ?>%</td>
                </tr>
                <tr>
                    <td>Approved</td>
                    <td><?= $statusDistribution['approved'] ?></td>
                    <td><?= array_sum($statusDistribution) > 0 ? round(($statusDistribution['approved'] / array_sum($statusDistribution)) * 100, 1) : 0 ?>%</td>
                </tr>
                <tr>
                    <td>Rejected</td>
                    <td><?= $statusDistribution['rejected'] ?></td>
                    <td><?= array_sum($statusDistribution) > 0 ? round(($statusDistribution['rejected'] / array_sum($statusDistribution)) * 100, 1) : 0 ?>%</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Grade Level Distribution</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Grade Level</th>
                    <th>Enrolled Students</th>
                    <th>Average Grade (T<?= esc((string) ($currentTerm ?? 1)) ?>)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (grade_level_options() as $g): ?>
                <tr>
                    <td><?= esc(grade_level_label($g)) ?></td>
                    <td><?= $gradeDistribution[$g] ?? 0 ?></td>
                    <td><?= ($gradeAverages[$g] ?? 0) > 0 ? $gradeAverages[$g] : 'N/A' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Gender Distribution</div>
        <div class="metric-grid">
            <div class="metric-row">
                <div class="metric-label">Male Students</div>
                <div class="metric-value"><?= $genderDistribution['male'] ?></div>
            </div>
            <div class="metric-row">
                <div class="metric-label">Female Students</div>
                <div class="metric-value"><?= $genderDistribution['female'] ?></div>
            </div>
            <div class="metric-row">
                <div class="metric-label">Total Students</div>
                <div class="metric-value"><?= $genderDistribution['male'] + $genderDistribution['female'] ?></div>
            </div>
            <div class="metric-row">
                <div class="metric-label">Gender Balance Gap</div>
                <div class="metric-value"><?= $metrics['genderBalance'] ?></div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Key Performance Metrics</div>
        <div class="metric-grid">
            <div class="metric-row">
                <div class="metric-label">Enrollment Completion Rate</div>
                <div class="metric-value"><?= $metrics['completionRate'] ?>%</div>
            </div>
            <div class="metric-row">
                <div class="metric-label">Pending Application Rate</div>
                <div class="metric-value"><?= $metrics['pendingRate'] ?>%</div>
            </div>
            <div class="metric-row">
                <div class="metric-label">Approval Rate</div>
                <div class="metric-value"><?= $metrics['approvalRate'] ?>%</div>
            </div>
        </div>
    </div>

<div class="foot">
    <?= esc(school_name()) ?> &middot; Analytics Report &middot; Generated <?= date('F j, Y \a\t g:i A') ?><br>
    This report contains confidential information. Distribution is restricted to authorized personnel only.
</div>
</body>
</html>
