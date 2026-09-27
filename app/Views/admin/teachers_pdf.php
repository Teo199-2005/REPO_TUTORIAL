<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc(school_name()) ?> - Faculty Master List</title>
    <style>
<?= view('reports/_report_css', ['pageSize' => 'A4 landscape']) ?>

/* The faculty list runs nine columns; the body stays compact while the
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
$rows = $teachers ?? [];
$dash = static fn ($v): string => ($v === null || trim((string) $v) === '') ? '—' : (string) $v;
?>
<?= view('reports/_letterhead', [
    'schoolName'  => school_name(),
    'reportTitle' => 'Faculty Master List',
    'schoolYear'  => $schoolYearPretty,
    'logoB64'     => $logoB64,
    'sealUri'     => $sealUri,
]) ?>
<div class="report-info">Generated: <?= esc($reportDate) ?></div>
<div class="report-info">Filters: <?= esc($filtersSummary) ?></div>
<div class="report-info"><strong><?= count($rows) ?></strong> teacher<?= count($rows) === 1 ? '' : 's' ?> listed</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Emp. No.</th>
                <th>Name</th>
                <th>Age</th>
                <th>Sex</th>
                <th>Email</th>
                <th>Department</th>
                <th>Position</th>
                <th>Status</th>
                <th>Advisory Section</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9">No records match the current filters.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $t): ?>
                    <?php
                    $empNo = $t['government_employee_no'] ?? $t['employee_id'] ?? null;
                    $age = isset($t['age']) && $t['age'] !== null ? (string) $t['age'] : '—';
                    $statusLabel = ucfirst(str_replace('_', ' ', (string) ($t['employment_status'] ?? 'active')));
                    ?>
                    <tr>
                        <td><?= esc($dash($empNo)) ?></td>
                        <td><?= esc(trim((string) ($t['first_name'] ?? '') . ' ' . (string) ($t['last_name'] ?? ''))) ?></td>
                        <td><?= esc($age) ?></td>
                        <td><?= esc($dash($t['gender'] ?? null)) ?></td>
                        <td><?= esc($dash($t['email'] ?? null)) ?></td>
                        <td><?= esc($dash($t['department'] ?? null)) ?></td>
                        <td><?= esc($dash($t['position'] ?? null)) ?></td>
                        <td><?= esc($statusLabel) ?></td>
                        <td><?= esc($dash($t['section_name'] ?? null)) ?></td>
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