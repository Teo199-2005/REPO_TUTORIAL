<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc(school_name()) ?> - Learner Development Report</title>
    <style>
<?= view('reports/_report_css') ?>

/* --------------------------------------------------- learner information */
table.learner { width: 100%; border: 1pt solid #000; page-break-inside: avoid; }
table.learner td { border: 0.5pt solid #000; padding: 1.4mm 2mm; font-size: 10.5pt; }
table.learner td.k {
    width: 17%; background: #EDEDED; font-weight: bold;
    text-transform: uppercase; font-size: 9.5pt; letter-spacing: 0.2pt;
}
table.learner td.v { width: 33%; }

.scale {
    border: 0.5pt solid #000; border-top: 0; background: #FAFAFA;
    padding: 1.2mm 2mm; font-size: 8.5pt; line-height: 1.25; page-break-inside: avoid;
}

/* ------------------------------- core values and behavioural indicators */
table.assess { width: 100%; border: 1pt solid #000; page-break-inside: auto; }
table.assess thead th {
    background: #EDEDED; font-weight: bold; text-align: center;
    text-transform: uppercase; font-size: 9.5pt; letter-spacing: 0.2pt;
}
table.assess thead th.left { text-align: left; }
table.assess th, table.assess td { border: 0.5pt solid #000; padding: 1.4mm 1.5mm; font-size: 10pt; }
table.assess tbody td { height: 7.5mm; text-align: center; }
table.assess tbody td.area { text-align: left; }
table.assess tbody tr.group td {
    background: #EDEDED; font-weight: bold; text-align: left;
    font-size: 9pt; text-transform: uppercase; letter-spacing: 0.2pt;
}
table.assess tbody tr { page-break-inside: avoid; }
table.assess thead { display: table-header-group; }

/* ---------------------------------------------------------------- remarks
   Teacher and parent remarks sit side by side so the form keeps its single
   page even when the behavioural table is long. */
table.remarks { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
table.remarks td { width: 50%; border: 0; padding: 0; vertical-align: top; }
table.remarks td + td { padding-left: 2.5mm; }
.remarks-head {
    font-size: 9.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3pt;
    margin-bottom: 1.2mm;
}
.remarks-box {
    border: 1pt solid #000; min-height: 16mm; padding: 1.6mm 2.5mm;
    font-size: 10pt;
}
.remarks-box p { margin: 0 0 2mm 0; }
.remarks-line { border-bottom: 0.5pt solid #999; height: 11pt; margin: 0 0 1.5pt 0; }
    </style>
</head>
<body>
<?php
$logoB64 = (($logoBase64 ?? '') !== '') ? $logoBase64 : school_logo_base64();
$sealUri = deped_seal_data_uri();

$report    = $report ?? [];
$terms      = $report['terms'] ?? [];
$coreValues = $report['coreValues'] ?? [];
$indicators = $report['behaviorIndicators'] ?? [];
$domains    = $report['behaviorDomains'] ?? [];
$remarks    = $report['remarks'] ?? [];
$scale      = $report['scale'] ?? [];

// A domain sub-heading only helps when the indicators come from more than one
// developmental domain; with a single domain the table stays flat.
$groupedIndicators = count($domains) > 1;

$schoolYearPretty = str_replace('-', '–', (string) $schoolYear);
?>
<?= view('reports/_letterhead', [
    'schoolName'  => school_name(),
    'reportTitle' => 'Learner Development Report',
    'schoolYear'  => $schoolYearPretty,
    'logoB64'     => $logoB64,
    'sealUri'     => $sealUri,
]) ?>

<div class="section-title">Learner Information</div>
<table class="learner">
    <tr>
        <td class="k">Learner Name</td>
        <td class="v"><?= esc(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))) ?></td>
        <td class="k">Grade Level</td>
        <td class="v"><?= esc(grade_level_label((int) ($student['grade_level'] ?? 0))) ?></td>
    </tr>
    <tr>
        <td class="k">LRN</td>
        <td class="v"><?= esc($student['lrn'] ?? 'N/A') ?></td>
        <td class="k">Section</td>
        <td class="v"><?= esc($section['section_name'] ?? 'Not Assigned') ?></td>
    </tr>
    <tr>
        <td class="k">School Year</td>
        <td class="v"><?= esc($schoolYearPretty) ?></td>
        <td class="k">Adviser</td>
        <td class="v"><?= esc($student['adviser_name'] ?? 'Not Assigned') ?></td>
    </tr>
</table>

<?php if ($scale !== []): ?>
    <div class="scale">
        <strong>Rating Scale:</strong>
        <?php foreach ($scale as $i => $entry): ?>
            <?php if ($i > 0): ?>&nbsp;&nbsp;|&nbsp;&nbsp;<?php endif; ?>
            <strong><?= esc($entry['symbol']) ?></strong>
            <?php if (($entry['label'] ?? '') !== ''): ?>= <?= esc($entry['label']) ?><?php endif; ?>
        <?php endforeach; ?>
        <br>
        <em>Ratings are printed exactly as recorded by the class adviser.</em>
    </div>
<?php endif; ?>

<div class="section-title">Core Values</div>
<table class="assess">
    <colgroup>
        <col style="width: 40%;">
        <col style="width: 20%;">
        <col style="width: 20%;">
        <col style="width: 20%;">
    </colgroup>
    <thead>
        <tr>
            <th class="left">Core Values</th>
            <?php foreach ($terms as $term): ?>
                <th><?= esc($term['label']) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($coreValues as $value): ?>
            <tr>
                <td class="area"><?= esc($value['name']) ?></td>
                <?php foreach ($terms as $index => $term): ?>
                    <?php $rating = trim((string) ($value['ratings'][$index] ?? '')); ?>
                    <td><?php echo $rating !== '' ? esc($rating) : '-'; ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="section-title">Behavioral Indicators</div>
<?php if ($indicators !== []): ?>
    <table class="assess">
        <colgroup>
            <col style="width: 40%;">
            <col style="width: 20%;">
            <col style="width: 20%;">
            <col style="width: 20%;">
        </colgroup>
        <thead>
            <tr>
                <th class="left">Indicator</th>
                <?php foreach ($terms as $term): ?>
                    <th><?= esc($term['label']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $currentDomain = null;
            foreach ($indicators as $indicator):
                if ($groupedIndicators && $indicator['domain'] !== '' && $indicator['domain'] !== $currentDomain):
                    $currentDomain = $indicator['domain'];
                    ?>
                    <tr class="group"><td colspan="<?= count($terms) + 1 ?>"><?= esc($currentDomain) ?></td></tr>
                <?php endif; ?>
                <tr>
                    <td class="area"><?= esc($indicator['name']) ?></td>
                    <?php foreach ($terms as $index => $term): ?>
                        <?php $rating = trim((string) ($indicator['ratings'][$index] ?? '')); ?>
                        <td><?php echo $rating !== '' ? esc($rating) : '-'; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="remarks-box">
        <em>No behavioral indicators have been configured for this section.</em>
    </div>
<?php endif; ?>


<div class="section-title">Remarks</div>
<table class="remarks">
    <tr>
        <td>
            <div class="remarks-head">Teacher Remarks</div>
            <div class="remarks-box">
                <?php if ($remarks !== []): ?>
                    <?php foreach ($remarks as $remark): ?>
                        <p><?= esc($remark) ?></p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="remarks-line"></div>
                    <div class="remarks-line"></div>
                <?php endif; ?>
            </div>
        </td>
        <td>
            <div class="remarks-head">Parent/Guardian Remarks</div>
            <div class="remarks-box">
                <div class="remarks-line"></div>
                <div class="remarks-line"></div>
            </div>
        </td>
    </tr>
</table>

<?php $principal = school_principal(); ?>
<table class="sign">
    <tr>
        <td>
            <div class="sign-label">Prepared By</div>
            <div class="sign-space"></div>
            <div class="sign-line"></div>
            <div class="sign-name"><?= esc($student['adviser_name'] ?? 'Not Assigned') ?></div>
            <div class="sign-title">Class Adviser</div>
        </td>
        <td>
            <div class="sign-label">Noted By</div>
            <div class="sign-space"></div>
            <div class="sign-line"></div>
            <div class="sign-name"><?= esc($principal['name']) ?></div>
            <div class="sign-rank"><?= esc($principal['rank']) ?></div>
            <div class="sign-title">School Principal</div>
        </td>
    </tr>
</table>

<div class="foot">
    <?= esc(school_name()) ?> &middot; Learner Development Report &middot; School Year: <?= esc($schoolYearPretty) ?><br>
    Generated on <?= esc($reportDate) ?><br>
    This document contains confidential learner information. Handle with care and maintain privacy.
</div>
</body>
</html>

