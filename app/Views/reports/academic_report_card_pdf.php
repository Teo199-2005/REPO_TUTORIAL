<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc(school_name()) ?> - Academic Report Card</title>
    <style>
<?= view('reports/_report_css') ?>

/* ------------------------------------------------------- learner information
   Two columns of label/value pairs: 17% label, 33% value on each side. */
table.learner { width: 100%; border: 1pt solid #000; page-break-inside: avoid; }
table.learner td { border: 0.5pt solid #000; padding: 1.6mm 2mm; font-size: 10.5pt; }
table.learner td.k {
    width: 17%; background: #EDEDED; font-weight: bold;
    text-transform: uppercase; font-size: 9.5pt; letter-spacing: 0.2pt;
}
table.learner td.v { width: 33%; }

/* ------------------------------------------------------------ academic grades
   40 / 10 / 10 / 10 / 15 / 15 column split, 0.5pt inner and 1pt outer rules. */
table.grades { width: 100%; border: 1pt solid #000; page-break-inside: avoid; }
table.grades th, table.grades td { border: 0.5pt solid #000; padding: 1.6mm 1.5mm; font-size: 10pt; }
table.grades thead th {
    background: #EDEDED; font-weight: bold; text-align: center;
    text-transform: uppercase; font-size: 9.5pt; letter-spacing: 0.2pt;
}
table.grades thead th.left { text-align: left; }
table.grades tbody td { height: 8mm; text-align: center; }
table.grades tbody td.area { text-align: left; }
table.grades tfoot td {
    background: #EDEDED; font-weight: bold; text-align: center;
    font-size: 10.5pt; height: 8mm; border-top: 1pt solid #000;
}
table.grades tfoot td.area { text-align: left; }
table.grades tbody tr { page-break-inside: avoid; }

/* --------------------------------------------------------- general average */
table.summary { width: 100%; border: 1pt solid #000; margin-top: 2.5mm; page-break-inside: avoid; }
table.summary td { border: 0.5pt solid #000; padding: 2.2mm 2.5mm; font-size: 11pt; }
table.summary td.k {
    width: 25%; background: #EDEDED; font-weight: bold;
    text-transform: uppercase; font-size: 10pt; letter-spacing: 0.2pt;
}
table.summary td.v { width: 25%; text-align: center; font-weight: bold; font-size: 12pt; }

/* --------------------------------------------------------------- attendance */
table.attend { width: 52%; border: 1pt solid #000; margin-top: 2.5mm; page-break-inside: avoid; }
table.attend th, table.attend td { border: 0.5pt solid #000; padding: 1.4mm 2mm; font-size: 9.5pt; }
table.attend th { background: #EDEDED; font-weight: bold; text-align: left; }
table.attend td.v { text-align: center; font-weight: bold; }
    </style>
</head>
<body>
<?php
$logoB64 = (($logoBase64 ?? '') !== '') ? $logoBase64 : school_logo_base64();
$sealUri = deped_seal_data_uri();

$attendanceData = $attendance ?? null;
if (is_object($attendanceData)) {
    $attendanceData = get_object_vars($attendanceData);
}

$subjects = $subjects ?? [];
$grades   = $grades ?? [];
// The teacher endpoint passes $quarterAverages, the student endpoint
// $termAverages; both are the per-term averages of the same three terms.
$averages    = $quarterAverages ?? $termAverages ?? [];
$finalAverage = $finalAverage ?? null;

$schoolYearPretty = str_replace('-', '–', (string) $schoolYear);
$principal        = school_principal();
?>
<?= view('reports/_letterhead', [
    'schoolName'  => school_name(),
    'reportTitle' => 'Academic Report Card',
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
        <td class="v"><?= esc($student['section_name'] ?? 'Not Assigned') ?></td>
    </tr>
    <tr>
        <td class="k">School Year</td>
        <td class="v"><?= esc($schoolYearPretty) ?></td>
        <td class="k">Adviser</td>
        <td class="v"><?= esc($student['adviser_name'] ?? 'Not Assigned') ?></td>
    </tr>
</table>

<div class="section-title">Academic Grades</div>
<table class="grades">
    <colgroup>
        <col style="width: 40%;">
        <col style="width: 10%;">
        <col style="width: 10%;">
        <col style="width: 10%;">
        <col style="width: 15%;">
        <col style="width: 15%;">
    </colgroup>
    <thead>
        <tr>
            <th class="left">Learning Area</th>
            <th>Term 1</th>
            <th>Term 2</th>
            <th>Term 3</th>
            <th>Final Rating</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($subjects === []): ?>
            <tr>
                <td class="area" colspan="6" style="text-align: center;">No learning areas recorded for this learner.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($subjects as $subject): ?>
        <tr>
            <td class="area"><?= esc($subject['subject_name']) ?></td>
            <?php for ($term = 1; $term <= 3; $term++): ?>
                <td>
                    <?php
                    $grade = $grades[$term][$subject['id']] ?? null;
                    echo $grade !== null && $grade !== ''
                        ? esc(is_numeric($grade) ? number_format((float) $grade, 0) : $grade)
                        : '-';
                    ?>
                </td>
            <?php endfor; ?>
            <td>
                <?php
                // Subject final rating: the mean of the numeric terms only.
                // Non-numeric entries (symbols such as "NO/NA") are ignored,
                // exactly as before - this is a display change, not a change of
                // grading rules.
                $subjectGrades = [];
                for ($t = 1; $t <= 3; $t++) {
                    if (isset($grades[$t][$subject['id']]) && $grades[$t][$subject['id']] !== null) {
                        $subjectGrades[] = $grades[$t][$subject['id']];
                    }
                }
                $numericGrades = array_filter($subjectGrades, static fn ($g) => is_numeric($g));
                $subjectFinal   = ! empty($numericGrades) ? array_sum($numericGrades) / count($numericGrades) : 0;

                echo $subjectFinal > 0 ? number_format($subjectFinal, 0) : '-';
                ?>
            </td>
            <td>
                <?php
                if ($subjectFinal >= 75) {
                    echo 'PASSED';
                } elseif ($subjectFinal > 0) {
                    echo 'FAILED';
                } else {
                    echo 'NO GRADE';
                }
                ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td class="area">Average</td>
            <td><?= (isset($averages[1]) && $averages[1] > 0) ? number_format((float) $averages[1], 1) : '-' ?></td>
            <td><?= (isset($averages[2]) && $averages[2] > 0) ? number_format((float) $averages[2], 1) : '-' ?></td>
            <td><?= (isset($averages[3]) && $averages[3] > 0) ? number_format((float) $averages[3], 1) : '-' ?></td>
            <td><?= $finalAverage > 0 ? number_format((float) $finalAverage, 1) : '-' ?></td>
            <td><?= $finalAverage >= 75 ? 'PASSED' : ($finalAverage > 0 ? 'FAILED' : 'NO GRADE') ?></td>
        </tr>
    </tfoot>
</table>

<table class="summary">
    <tr>
        <td class="k">General Average</td>
        <td class="v"><?= $finalAverage > 0 ? number_format((float) $finalAverage, 1) : '-' ?></td>
        <td class="k">Final Remarks</td>
        <td class="v"><?= $finalAverage >= 75 ? 'PASSED' : ($finalAverage > 0 ? 'FAILED' : 'NO GRADE') ?></td>
    </tr>
</table>


<?php if (is_array($attendanceData) && $attendanceData !== []): ?>
    <div class="section-title">Attendance</div>
    <table class="attend">
        <tr>
            <th>Attendance Item</th>
            <th style="width: 34%; text-align: center;">Value</th>
        </tr>
        <tr>
            <td>Days Present</td>
            <td class="v"><?= esc($attendanceData['days_present'] ?? '') ?></td>
        </tr>
        <tr>
            <td>Days Absent</td>
            <td class="v"><?= esc($attendanceData['days_absent'] ?? '') ?></td>
        </tr>
        <tr>
            <td>Days Tardy</td>
            <td class="v"><?= esc($attendanceData['days_tardy'] ?? '') ?></td>
        </tr>
    </table>
<?php endif; ?>

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
    <?= esc(school_name()) ?> &middot; Academic Report Card &middot; School Year: <?= esc($schoolYearPretty) ?><br>
    Generated on <?= esc($reportDate) ?><br>
    This document contains confidential student information. Handle with care and maintain privacy.
</div>
</body>
</html>

