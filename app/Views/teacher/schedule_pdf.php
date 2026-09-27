<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc(school_name()) ?> - My Schedule</title>
    <style>
<?= view('reports/_report_css', ['pageSize' => 'A4 landscape']) ?>
<?= view('reports/_schedule_grid_css') ?>
    </style>
</head>
<body>
<?php
$teacherName = trim(
    (string) ($teacher['first_name'] ?? '') . ' ' .
    (! empty($teacher['middle_name']) ? $teacher['middle_name'] . ' ' : '') .
    (string) ($teacher['last_name'] ?? '')
);
?>
<?= view('reports/_letterhead', [
    'schoolName'  => school_name(),
    'reportTitle' => 'My Schedule',
    'schoolYear'  => str_replace('-', '–', (string) $schoolYear),
    'logoB64'     => school_logo_base64(),
    'sealUri'     => deped_seal_data_uri(),
]) ?>
<div class="report-info"><strong><?= esc($teacherName !== '' ? $teacherName : 'Teacher') ?></strong><?= ! empty($teacher['position']) ? ' &middot; ' . esc($teacher['position']) : '' ?></div>
<div class="report-info">Includes the classes you teach and the sections you advise &middot; Generated: <?= esc($reportDate) ?></div>

<?= view('reports/_schedule_grid', [
    'schedules'   => $schedules,
    'showTeacher' => true,
    'showSection' => true,
]) ?>

</body>
</html>
