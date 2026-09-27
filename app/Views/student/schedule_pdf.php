<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc(school_name()) ?> - Class Schedule</title>
    <style>
<?= view('reports/_report_css', ['pageSize' => 'A4 landscape']) ?>
<?= view('reports/_schedule_grid_css') ?>
    </style>
</head>
<body>
<?php
$studentName = trim(
    (string) ($student['first_name'] ?? '') . ' ' .
    (! empty($student['middle_name']) ? $student['middle_name'] . ' ' : '') .
    (string) ($student['last_name'] ?? '')
);
?>
<?= view('reports/_letterhead', [
    'schoolName'  => school_name(),
    'reportTitle' => 'Class Schedule',
    'schoolYear'  => str_replace('-', '–', (string) $schoolYear),
    'logoB64'     => school_logo_base64(),
    'sealUri'     => deped_seal_data_uri(),
]) ?>
<div class="report-info"><strong><?= esc($studentName) ?></strong><?= ! empty($student['section_name']) ? ' &middot; Grade ' . esc(grade_level_label((int) ($student['grade_level'] ?? 0))) . ' - ' . esc($student['section_name']) : '' ?></div>
<div class="report-info">Generated: <?= esc($reportDate) ?></div>

<?= view('reports/_schedule_grid', [
    'schedules'   => $schedules,
    'showTeacher' => true,
    'showSection' => false,
]) ?>

</body>
</html>
