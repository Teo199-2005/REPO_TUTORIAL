<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc(school_name()) ?> - Progress Report Card (Non-Numerical)</title>
    <style>
<?= view('reports/_report_css') ?>

/* ------------------------------------------------------- learner information
   Same 17% / 33% label/value split as the Academic Report Card so every
   official document opens with the same information block. */
table.learner { width: 100%; border: 1pt solid #000; page-break-inside: avoid; }
table.learner td { border: 0.5pt solid #000; padding: 1.6mm 2mm; font-size: 10.5pt; }
table.learner td.k {
    width: 17%; background: #EDEDED; font-weight: bold;
    text-transform: uppercase; font-size: 9.5pt; letter-spacing: 0.2pt;
}
table.learner td.v { width: 33%; }

/* -------------------------------------------------------------------- legend
   Sits flush under the learner table (border-top: 0) the same way the
   Learner Development Report attaches its rating scale. */
.scale {
    border: 0.5pt solid #000; border-top: 0; background: #FAFAFA;
    padding: 1.2mm 2mm; font-size: 8.5pt; line-height: 1.25; page-break-inside: avoid;
}

/* ------------------------------------------------------ performance indicators
   1pt outer frame, 0.5pt inner rules and a header that repeats on every page:
   the same measurements as the Academic Report Card's grade table. */
table.marks { width: 100%; border: 1pt solid #000; page-break-inside: auto; }
table.marks thead th {
    background: #EDEDED; font-weight: bold; text-align: center;
    text-transform: uppercase; font-size: 9.5pt; letter-spacing: 0.2pt;
}
table.marks thead th.indicator { text-align: left; }
table.marks th, table.marks td { border: 0.5pt solid #000; padding: 1.3mm 1.5mm; font-size: 10pt; }
table.marks tbody td { text-align: center; }
table.marks tbody td.indicator { text-align: left; }
table.marks tbody tr { page-break-inside: avoid; }
table.marks thead { display: table-header-group; }

/* --------------------------------------------------------------- signatures
   This form is signed by four parties, so the shared two-column signature
   block is narrowed to four columns for this document only. The principal's
   column is wider so the name, rank and title sit on their own lines exactly
   as they do in the shared two-column layout. */
table.sign td { width: 23%; }
table.sign td.principal { width: 31%; }
    </style>
</head>
<body>
<?php
$logoB64 = (($logoBase64 ?? '') !== '') ? $logoBase64 : school_logo_base64();
$sealUri = deped_seal_data_uri();

$schoolYearPretty = str_replace('-', '–', (string) $schoolYear);
$principal        = school_principal();
$adviserName      = trim((string) ($student['adviser_name'] ?? ''));
?>
<?= view('reports/_letterhead', [
    'schoolName'  => school_name(),
    'reportTitle' => 'Progress Report Card (Non-Numerical)',
    'schoolYear'  => $schoolYearPretty,
    'logoB64'     => $logoB64,
    'sealUri'     => $sealUri,
]) ?>
    
    <table class="learner">
        <tr>
            <td class="k">Student Name</td>
            <td class="v"><?= esc($student['first_name'] . ' ' . $student['last_name']) ?></td>
            <td class="k">LRN</td>
            <td class="v"><?= esc($student['student_id'] ?? ($student['lrn'] ?? 'N/A')) ?></td>
        </tr>
        <tr>
            <td class="k">Grade/Section</td>
            <td class="v"><?= esc($section['section_name'] ?? 'N/A') ?></td>
            <td class="k">Gender</td>
            <td class="v"><?= esc($student['gender'] ?? 'N/A') ?></td>
        </tr>
        <tr>
            <td class="k">Report Date</td>
            <td class="v" colspan="3"><?= esc($reportDate) ?></td>
        </tr>
    </table>
    
    <div class="scale">
        <strong>Legend:</strong>
        <?php foreach ($gradeSymbols as $gs): ?>
            <?= esc($gs['symbol']) ?>=<?= esc($gs['label']) ?>;
        <?php endforeach; ?>
    </div>
    
    <?php foreach ($categories as $category): ?>
    <div class="section-title"><?= esc($category['name']) ?></div>
    <?php if (!empty($category['fields'])): ?>
    <table class="marks">
        <colgroup>
            <col style="width: 8mm;">
            <col>
            <col style="width: 11mm;">
            <col style="width: 11mm;">
            <col style="width: 11mm;">
            <col style="width: 11mm;">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th class="indicator">Performance indicators</th>
                <?php foreach ($quarters as $q): ?>
                <th><?= esc((string) $q) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($category['fields'] as $index => $field): ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td class="indicator"><?= esc($field['field_name']) ?></td>
                <?php foreach ($quarters as $q): ?>
                <td>
                    <?php
                    $symbol = $allGrades[$field['id']][$q]['grade_symbol'] ?? '';
                    echo $symbol ? esc($symbol) : '-';
                    ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php endforeach; ?>
    
    <table class="sign">
        <tr>
            <td>
                <div class="sign-label">Prepared By</div>
                <div class="sign-space"></div>
                <div class="sign-line"></div>
                <?php if ($adviserName !== ''): ?>
                    <div class="sign-name"><?= esc($adviserName) ?></div>
                <?php endif; ?>
                <div class="sign-title">Class Adviser</div>
            </td>
            <td class="principal">
                <div class="sign-label">Noted By</div>
                <div class="sign-space"></div>
                <div class="sign-line"></div>
                <div class="sign-name"><?= esc($principal['name']) ?></div>
                <div class="sign-rank"><?= esc($principal['rank']) ?></div>
                <div class="sign-title">School Principal</div>
            </td>
            <td>
                <div class="sign-label">&nbsp;</div>
                <div class="sign-space"></div>
                <div class="sign-line"></div>
                <div class="sign-title">Parent/Guardian</div>
            </td>
            <td>
                <div class="sign-label">&nbsp;</div>
                <div class="sign-space"></div>
                <div class="sign-line"></div>
                <div class="sign-title">Student</div>
            </td>
        </tr>
    </table>
    
    <div class="foot">
        <?= esc(school_name()) ?> &middot; Progress Report Card (Non-Numerical) &middot; School Year: <?= esc($schoolYearPretty) ?><br>
        Generated on <?= esc($reportDate) ?><br>
        This document contains confidential student information. Handle with care and maintain privacy.
    </div>
</body>
</html>