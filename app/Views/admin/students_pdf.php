<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc(school_name()) ?> - Students Master List</title>
    <style>
<?= view('reports/_report_css', ['pageSize' => 'A4 landscape']) ?>

/* The master list runs nine columns; the body stays compact while the
   letterhead, rules and footer follow the shared report family. */
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
$rows = $students ?? [];
$dash = static fn ($v): string => ($v === null || trim((string) $v) === '') ? '—' : (string) $v;
?>
<?= view('reports/_letterhead', [
    'schoolName'  => school_name(),
    'reportTitle' => 'Students Master List',
    'schoolYear'  => $schoolYearPretty,
    'logoB64'     => $logoB64,
    'sealUri'     => $sealUri,
]) ?>
<div class="report-info">Generated: <?= esc($reportDate) ?></div>
<div class="report-info">Filters: <?= esc($filtersSummary) ?></div>
<div class="report-info"><strong><?= count($rows) ?></strong> student<?= count($rows) === 1 ? '' : 's' ?> listed</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>LRN</th>
                <th>Name</th>
                <th>Gr</th>
                <th>Section</th>
                <th>Sex</th>
                <th>Type</th>
                <th>Religion</th>
                <th>Status</th>
                <th>Enrolled</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9">No records match the current filters.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $s): ?>
                    <?php
                    $name = trim(
                        (string) ($s['first_name'] ?? '') . ' ' .
                        (! empty($s['middle_name']) ? $s['middle_name'] . ' ' : '') .
                        (string) ($s['last_name'] ?? '')
                    );
                    $enrolled = ! empty($s['created_at'])
                        ? date('M j, Y', strtotime((string) $s['created_at']))
                        : '—';
                    $type = trim((string) ($s['student_type'] ?? ''));
                    ?>
                    <tr>
                        <td><?= esc($dash($s['lrn'] ?? null)) ?></td>
                        <td><?= esc($name) ?></td>
                        <td><?= esc((string) ($s['grade_level'] ?? '')) ?></td>
                        <td><?= esc($dash($s['section_name'] ?? null)) ?></td>
                        <td><?= esc($dash($s['gender'] ?? null)) ?></td>
                        <td><?= esc($type !== '' ? ucfirst($type) : '—') ?></td>
                        <td><?= esc($dash($s['religion'] ?? null)) ?></td>
                        <td><?= esc(ucfirst((string) ($s['enrollment_status'] ?? ''))) ?></td>
                        <td><?= esc($enrolled) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<div class="foot">
    <?= esc(school_name()) ?> &middot; Generated <?= date('F j, Y \a\t g:i A') ?>
</div>
</body>
</html>