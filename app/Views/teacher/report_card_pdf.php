<?php
/**
 * Teacher copy of the Academic Report Card.
 *
 * The document itself lives in reports/academic_report_card_pdf.php so the
 * teacher, student and admin copies are guaranteed to be the same design; this
 * view only maps Teacher\Dashboard::generateReportCard()'s data keys onto it.
 *
 * @var array      $student
 * @var array      $subjects
 * @var array      $grades          [term][subject_id] => grade
 * @var array      $quarterAverages Per-term averages
 * @var float|null $finalAverage
 * @var string     $schoolYear
 * @var string     $reportDate
 */
echo view('reports/academic_report_card_pdf', [
    'student'         => $student,
    'subjects'        => $subjects ?? [],
    'grades'          => $grades ?? [],
    'quarterAverages' => $quarterAverages ?? [],
    'finalAverage'    => $finalAverage ?? 0,
    'schoolYear'      => $schoolYear,
    'reportDate'      => $reportDate,
    'attendance'      => $attendance ?? null,
    'logoBase64'      => $logoBase64 ?? '',
]);
