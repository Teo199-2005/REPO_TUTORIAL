<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc(school_name()) ?> - Class Analytics Report</title>
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
    'reportTitle' => 'Class Analytics Report',
    'schoolYear'  => $schoolYearPretty,
    'logoB64'     => $logoB64,
    'sealUri'     => $sealUri,
]) ?>
<div class="report-info">Teacher: <?= esc($teacher['first_name'] . ' ' . $teacher['last_name']) ?> &middot; Term: <?= esc((string) $currentTerm) ?></div>
<?php if (!empty($teacherSection['section_name'])): ?>
<div class="report-info">Section: <?= esc($teacherSection['section_name']) ?><?= !empty($isDomainMode) ? ' &middot; Developmental Domains (symbols, not grades)' : '' ?></div>
<?php endif; ?>
<div class="report-info">Report Generated: <?= esc($reportDate) ?> at <?= esc($reportTime ?? date('g:i A')) ?></div>

    <div class="section">
        <div class="section-title">Class Overview</div>
        <div class="summary-box">
            <p><strong>Total Students:</strong> <?= $analytics['totalStudents'] ?></p>
            <?php if (!empty($isDomainMode) && !empty($domainAnalytics)): ?>
            <p><strong>Assessment Type:</strong> Developmental Domains (symbols &mdash; not numeric grades)</p>
            <p><strong>Completion (indicators assessed):</strong> <?= number_format($domainAnalytics['completionRate'], 1) ?>%</p>
            <p><strong>Mastery (Proficient + Approaching):</strong> <?= number_format($domainAnalytics['masteryRate'], 1) ?>%</p>
            <?php else: ?>
            <p><strong>Total Subjects:</strong> <?= $analytics['totalSubjects'] ?></p>
            <p><strong>Class Average:</strong> <?= number_format($analytics['classAverage'], 1) ?>%</p>
            <p><strong>Improvement Rate:</strong> +<?= number_format($analytics['improvementRate'], 1) ?>%</p>
            <?php endif; ?>
            <p><strong>Attendance Rate:</strong> <?= number_format($analytics['attendanceRate'], 1) ?>%</p>
        </div>
    </div>

    <?php
    $gradedForDist = (int) ($analytics['studentsGradedForDistribution'] ?? 0);
    $distDenom = max(1, $gradedForDist);
    ?>
    <?php if (!empty($isDomainMode) && !empty($domainAnalytics)): ?>
    <?php
    $symbolMeanings = [
        'P'  => 'Proficient',
        'AP' => 'Approaching Proficiency',
        'D'  => 'Developing',
        'B'  => 'Beginning',
        'NO' => 'Not Observed / Not Applicable',
    ];
    $domainAssessed = max(1, (int) ($domainAnalytics['assessed'] ?? 0));
    ?>
    <div class="section">
        <div class="section-title">Symbol Distribution</div>
        <p style="font-size: 0.8125rem; margin: 0 0 8px 0;">Percentages are of the <?= (int) $domainAnalytics['assessed'] ?> assessed indicators. NO/NA counts as observed but not towards mastery.</p>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Meaning</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($symbolMeanings as $symbol => $meaning): ?>
                <?php $count = (int) ($domainAnalytics['symbols'][$symbol] ?? 0); ?>
                <tr>
                    <td><strong><?= esc($symbol === 'NO' ? 'NO/NA' : $symbol) ?></strong></td>
                    <td><?= esc($meaning) ?></td>
                    <td style="text-align: center;"><?= $count ?></td>
                    <td style="text-align: center;"><?= round(($count / $domainAssessed) * 100, 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
                <tr style="font-weight: bold; background-color: #f0f0f0;">
                    <td colspan="2">Total Assessed</td>
                    <td style="text-align: center;"><?= (int) $domainAnalytics['assessed'] ?></td>
                    <td style="text-align: center;"><?= (int) $domainAnalytics['totalIndicators'] > 0 ? round(((int) $domainAnalytics['assessed'] / (int) $domainAnalytics['totalIndicators']) * 100, 1) : 0 ?>% of indicators</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="section">
        <div class="section-title">Developmental Domains</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>Indicators</th>
                    <th>Assessed</th>
                    <th>P</th>
                    <th>AP</th>
                    <th>D</th>
                    <th>B</th>
                    <th>Mastery (P+AP)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $domainTotalsRow = ['total' => 0, 'assessed' => 0, 'proficient' => 0, 'approaching' => 0, 'developing' => 0, 'beginning' => 0];
                foreach ($domainAnalytics['domains'] as $domain):
                    $domainTotalsRow['total'] += (int) ($domain['total'] ?? 0);
                    $domainTotalsRow['assessed'] += (int) ($domain['assessed'] ?? 0);
                    $domainTotalsRow['proficient'] += (int) ($domain['proficient'] ?? 0);
                    $domainTotalsRow['approaching'] += (int) ($domain['approaching'] ?? 0);
                    $domainTotalsRow['developing'] += (int) ($domain['developing'] ?? 0);
                    $domainTotalsRow['beginning'] += (int) ($domain['beginning'] ?? 0);
                ?>
                <tr>
                    <td><?= esc($domain['name']) ?></td>
                    <td style="text-align: center;"><?= (int) $domain['total'] ?></td>
                    <td style="text-align: center;"><?= (int) $domain['assessed'] ?></td>
                    <td style="text-align: center;"><?= (int) $domain['proficient'] ?></td>
                    <td style="text-align: center;"><?= (int) $domain['approaching'] ?></td>
                    <td style="text-align: center;"><?= (int) $domain['developing'] ?></td>
                    <td style="text-align: center;"><?= (int) $domain['beginning'] ?></td>
                    <td style="text-align: center;"><?= number_format($domain['mastery'], 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
                <tr style="font-weight: bold; background-color: #f0f0f0;">
                    <td>Total</td>
                    <td style="text-align: center;"><?= $domainTotalsRow['total'] ?></td>
                    <td style="text-align: center;"><?= $domainTotalsRow['assessed'] ?></td>
                    <td style="text-align: center;"><?= $domainTotalsRow['proficient'] ?></td>
                    <td style="text-align: center;"><?= $domainTotalsRow['approaching'] ?></td>
                    <td style="text-align: center;"><?= $domainTotalsRow['developing'] ?></td>
                    <td style="text-align: center;"><?= $domainTotalsRow['beginning'] ?></td>
                    <td style="text-align: center;"><?= number_format($domainAnalytics['masteryRate'], 1) ?>%</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Assessment Coverage per Quarter</div>
        <p style="font-size: 0.8125rem; margin: 0 0 8px 0;">Number of indicators rated per quarter (symbols are stored per quarter, independent of the admin term).</p>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Quarter</th>
                    <th>Indicators Assessed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($domainAnalytics['quarterCoverage'] as $quarter => $count): ?>
                <tr>
                    <td>Quarter <?= (int) $quarter ?></td>
                    <td style="text-align: center;"><?= (int) $count ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="section">
        <div class="section-title">Student Developmental Summary</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Indicators Assessed</th>
                    <th>P</th>
                    <th>AP</th>
                    <th>D</th>
                    <th>B</th>
                    <th>NO/NA</th>
                    <th>Mastery (P+AP)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($domainAnalytics['students'] as $student): ?>
                <tr>
                    <td><?= esc($student['name']) ?></td>
                    <td style="text-align: center;"><?= (int) $student['assessed'] ?></td>
                    <td style="text-align: center;"><?= (int) $student['P'] ?></td>
                    <td style="text-align: center;"><?= (int) $student['AP'] ?></td>
                    <td style="text-align: center;"><?= (int) $student['D'] ?></td>
                    <td style="text-align: center;"><?= (int) $student['B'] ?></td>
                    <td style="text-align: center;"><?= (int) $student['NO'] ?></td>
                    <td style="text-align: center;"><?= number_format($student['mastery'], 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="section">
        <div class="section-title">Grade Distribution</div>
        <p style="font-size: 0.8125rem; margin: 0 0 8px 0;">Percentages are of students with at least one grade this term in the subjects included (<?= $gradedForDist ?> students).</p>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Grade Range</th>
                    <th>Count (students)</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Excellent (90-100)</td>
                    <td><?= $analytics['gradeDistribution']['excellent'] ?></td>
                    <td><?= round(($analytics['gradeDistribution']['excellent'] / $distDenom) * 100, 1) ?>%</td>
                </tr>
                <tr>
                    <td>Very Good (85-89)</td>
                    <td><?= $analytics['gradeDistribution']['very_good'] ?></td>
                    <td><?= round(($analytics['gradeDistribution']['very_good'] / $distDenom) * 100, 1) ?>%</td>
                </tr>
                <tr>
                    <td>Good (80-84)</td>
                    <td><?= $analytics['gradeDistribution']['good'] ?></td>
                    <td><?= round(($analytics['gradeDistribution']['good'] / $distDenom) * 100, 1) ?>%</td>
                </tr>
                <tr>
                    <td>Fair (75-79)</td>
                    <td><?= $analytics['gradeDistribution']['fair'] ?></td>
                    <td><?= round(($analytics['gradeDistribution']['fair'] / $distDenom) * 100, 1) ?>%</td>
                </tr>
                <tr>
                    <td>Passing (70-74)</td>
                    <td><?= $analytics['gradeDistribution']['passing'] ?></td>
                    <td><?= round(($analytics['gradeDistribution']['passing'] / $distDenom) * 100, 1) ?>%</td>
                </tr>
                <tr>
                    <td>Failing (<70)</td>
                    <td><?= $analytics['gradeDistribution']['failing'] ?></td>
                    <td><?= round(($analytics['gradeDistribution']['failing'] / $distDenom) * 100, 1) ?>%</td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php if (!empty($analytics['subjectAverages'])): ?>
    <div class="section" style="margin-bottom: 80px;">
        <div class="section-title">Subject Performance</div>
        <table class="data-table" style="margin-bottom: 30px;">
            <thead>
                <tr>
                    <th style="width: 60%;">Subject</th>
                    <th style="width: 20%;">Average Grade</th>
                    <th style="width: 20%;">Students Graded</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($analytics['subjectAverages'] as $subject): ?>
                <tr>
                    <td style="padding: 10px 8px;"><?= esc($subject['subject']) ?></td>
                    <td style="padding: 10px 8px; text-align: center;"><?= number_format($subject['average'], 1) ?>%</td>
                    <td style="padding: 10px 8px; text-align: center;"><?= $subject['count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="section">
        <div class="section-title">Attendance Summary</div>
        <div class="summary-box">
            <p><strong>Overall Attendance Rate:</strong> <?= number_format($analytics['attendanceStats']['attendanceRate'] ?? 0, 1) ?>%</p>
            <p><strong>Total Records:</strong> <?= $analytics['attendanceStats']['total'] ?? 0 ?></p>
            <p><strong>Present:</strong> <?= $analytics['attendanceStats']['present'] ?? 0 ?> students</p>
            <p><strong>Absent:</strong> <?= $analytics['attendanceStats']['absent'] ?? 0 ?> students</p>
            <p><strong>Late:</strong> <?= $analytics['attendanceStats']['late'] ?? 0 ?> students</p>
            <p><strong>Excused:</strong> <?= $analytics['attendanceStats']['excused'] ?? 0 ?> students</p>
        </div>
        
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
                    <td>Present</td>
                    <td><?= $analytics['attendanceStats']['present'] ?? 0 ?></td>
                    <td><?= $analytics['attendanceStats']['total'] > 0 ? round(($analytics['attendanceStats']['present'] / $analytics['attendanceStats']['total']) * 100, 1) : 0 ?>%</td>
                </tr>
                <tr>
                    <td>Absent</td>
                    <td><?= $analytics['attendanceStats']['absent'] ?? 0 ?></td>
                    <td><?= $analytics['attendanceStats']['total'] > 0 ? round(($analytics['attendanceStats']['absent'] / $analytics['attendanceStats']['total']) * 100, 1) : 0 ?>%</td>
                </tr>
                <tr>
                    <td>Late</td>
                    <td><?= $analytics['attendanceStats']['late'] ?? 0 ?></td>
                    <td><?= $analytics['attendanceStats']['total'] > 0 ? round(($analytics['attendanceStats']['late'] / $analytics['attendanceStats']['total']) * 100, 1) : 0 ?>%</td>
                </tr>
                <tr>
                    <td>Excused</td>
                    <td><?= $analytics['attendanceStats']['excused'] ?? 0 ?></td>
                    <td><?= $analytics['attendanceStats']['total'] > 0 ? round(($analytics['attendanceStats']['excused'] / $analytics['attendanceStats']['total']) * 100, 1) : 0 ?>%</td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php if (empty($isDomainMode)): ?>
    <?php if (!empty($analytics['studentPerformance'])): ?>
    <div class="section">
        <div class="section-title">Top Performing Students</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 10%;">Rank</th>
                    <th style="width: 35%;">Student Name</th>
                    <th style="width: 20%;">Average Grade</th>
                    <th style="width: 20%;">Performance Level</th>
                    <th style="width: 15%;">Subjects</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Sort students by average (descending)
                usort($analytics['studentPerformance'], function($a, $b) {
                    return $b['average'] <=> $a['average'];
                });
                ?>
                <?php foreach (array_slice($analytics['studentPerformance'], 0, 10) as $index => $student): ?>
                <?php
                $performanceLevel = $student['average'] >= 90 ? 'Excellent (90-100%)' : 
                                  ($student['average'] >= 85 ? 'Very Good (85-89%)' : 
                                  ($student['average'] >= 80 ? 'Good (80-84%)' : 
                                  ($student['average'] >= 75 ? 'Fair (75-79%)' : 
                                  ($student['average'] >= 70 ? 'Passing (70-74%)' : 'Below 70%'))));
                $rankStyle = $index < 3 ? 'font-weight: bold; color: #d4af37;' : '';
                ?>
                <tr>
                    <td style="text-align: center; <?= $rankStyle ?>"><?= $index + 1 ?></td>
                    <td style="<?= $rankStyle ?>"><?= esc($student['name']) ?></td>
                    <td style="text-align: center; <?= $rankStyle ?>"><?= number_format($student['average'], 1) ?>%</td>
                    <td style="<?= $rankStyle ?>"><?= $performanceLevel ?></td>
                    <td style="text-align: center; <?= $rankStyle ?>"><?= $student['grade_count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (count($analytics['studentPerformance']) > 10): ?>
        <p style="text-align: center; font-style: italic; margin-top: 10px;">Showing top 10 students. Total students with grades: <?= count($analytics['studentPerformance']) ?></p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="section">
        <div class="section-title">Student Performance</div>
        <div class="summary-box">
            <p><strong>No student performance data available.</strong> Grades have not been recorded for the current term yet.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="section">
        <div class="section-title">Term Performance Trends</div>
        <div class="metric-grid">
            <?php foreach ($analytics['termTrends'] as $trend): ?>
            <div class="metric-row">
                <div class="metric-label"><?= esc($trend['term']) ?></div>
                <div class="metric-value"><?= number_format($trend['average'], 1) ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="section">
        <div class="section-title">Recommendations</div>
        <div class="summary-box">
            <?php if (!empty($isDomainMode) && !empty($domainAnalytics)): ?>
                <?php if ((int) ($domainAnalytics['assessed'] ?? 0) === 0): ?>
                <p><strong>Developmental Status:</strong> No indicators have been assessed yet. Begin rating developmental-domain indicators in Enter Grades.</p>
                <?php elseif (($domainAnalytics['masteryRate'] ?? 0) >= 75): ?>
                <p><strong>Developmental Status:</strong> Strong progress &mdash; <?= number_format($domainAnalytics['masteryRate'], 1) ?>% of rated indicators are Proficient or Approaching Proficiency.</p>
                <?php else: ?>
                <p><strong>Developmental Status:</strong> <?= number_format($domainAnalytics['masteryRate'], 1) ?>% mastery (P+AP) across <?= (int) $domainAnalytics['assessed'] ?> assessed indicators (<?= (int) $domainAnalytics['totalIndicators'] ?> total). Continue observing and rating the remaining indicators.</p>
                <?php endif; ?>
            <?php elseif (($analytics['classAverage'] ?? 0) >= 85): ?>
                <p><strong>Performance Status:</strong> Excellent! Your class is performing exceptionally well with an average of <?= number_format($analytics['classAverage'], 1) ?>%.</p>
            <?php elseif (($analytics['classAverage'] ?? 0) >= 75): ?>
                <p><strong>Performance Status:</strong> Good progress! Class average is <?= number_format($analytics['classAverage'], 1) ?>%. Consider targeted support for struggling students.</p>
            <?php else: ?>
                <p><strong>Performance Status:</strong> Needs attention. Class average is <?= number_format($analytics['classAverage'], 1) ?>%. Implement intervention strategies.</p>
            <?php endif; ?>

            <?php if (($analytics['attendanceStats']['total'] ?? 0) > 0): ?>
            <p><strong>Attendance Impact:</strong> Attendance rate of <?= number_format($analytics['attendanceRate'], 1) ?>% across <?= (int) ($analytics['attendanceStats']['total'] ?? 0) ?> records supports consistent participation.</p>
            <?php else: ?>
            <p><strong>Attendance Impact:</strong> No attendance records yet &mdash; begin taking attendance to correlate participation with performance.</p>
            <?php endif; ?>
            
            <?php if (!empty($analytics['subjectAverages'])): ?>
                <?php
                $lowestSubject = array_reduce($analytics['subjectAverages'], function($carry, $item) {
                    return (!$carry || $item['average'] < $carry['average']) ? $item : $carry;
                });
                ?>
                <p><strong>Subject Focus:</strong> Consider additional support for <?= esc($lowestSubject['subject']) ?> (<?= number_format($lowestSubject['average'], 1) ?>% average).</p>
            <?php endif; ?>
        </div>
    </div>

<div class="foot">
    <?= esc(school_name()) ?> &middot; Class Analytics Report &middot; Generated <?= date('F j, Y \a\t g:i A', time()) ?><br>
    This report contains confidential information. Distribution is restricted to authorized personnel only.
</div>
</body>
</html>
