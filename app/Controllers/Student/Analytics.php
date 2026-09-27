<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Models\StudentModel;
use App\Models\SubjectModel;

class Analytics extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    public function index()
    {
        if (! $this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        if (! $this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'))->with('error', 'Access denied.');
        }

        $studentModel = new StudentModel();
        $user = $this->auth->user();
        $student = $studentModel->where('user_id', $user->id)->first();

        if (! $student) {
            return redirect()->to(base_url('student/dashboard'))->with('error', 'Student record not found.');
        }

        helper('school_year');
        $schoolYear = get_current_school_year();
        $currentTerm = get_current_term();

        // Get student's section and subjects
        $studentWithSection = $studentModel->getStudentWithSection((int) $student['id']);
        $sectionId = $studentWithSection['section_id'] ?? null;

        $db = \Config\Database::connect();
        $section = $sectionId
            ? $db->table('sections')->where('id', (int) $sectionId)->get()->getRowArray()
            : null;

        // Non-numerical sections (Grade 1 CAMIA, SNED, SSES, ...) are assessed
        // with developmental-domain symbols (P/AP/D/B/NO-NA), not 0-100 grades,
        // so the numeric widgets could only ever report zeros for them. Build a
        // symbol-based payload instead.
        $isDomainMode = $section !== null
            && in_array((string) ($section['grading_type'] ?? 'numerical'), ['non_numerical', 'custom'], true);

        $subjects = [];
        if ($sectionId) {
            $subjectModel = new SubjectModel();
            $subjects = $subjectModel->getSectionSubjects((int) $sectionId);
        }

        // Get filter parameters
        $attendanceFilter = $this->request->getGet('attendance_filter') ?? 'semester';
        $termFilter = $this->request->getGet('term') ?? $currentTerm;
        $attendancePage = (int) ($this->request->getGet('attendance_page') ?? 1);
        $attendancePerPage = 10;

        // Calculate analytics
        $analytics = $this->calculateStudentAnalytics($student, $subjects, $schoolYear, $currentTerm);

        // Get attendance records with filtering
        $allAttendanceRecords = $this->getStudentAttendanceRecords((int) $student['id'], $schoolYear, $attendanceFilter, $termFilter);
        $analytics['attendanceStats'] = $this->calculateAttendanceStats($allAttendanceRecords);
        $analytics['attendanceRate'] = $analytics['attendanceStats']['attendanceRate'];

        // Paginate attendance records
        $totalRecords = count($allAttendanceRecords);
        $totalPages = max(1, (int) ceil($totalRecords / $attendancePerPage));
        $attendancePage = max(1, min($attendancePage, $totalPages));
        $offset = ($attendancePage - 1) * $attendancePerPage;
        $analytics['attendanceRecords'] = array_slice($allAttendanceRecords, $offset, $attendancePerPage);
        $analytics['attendancePagination'] = [
            'page' => $attendancePage,
            'perPage' => $attendancePerPage,
            'total' => $totalRecords,
            'totalPages' => $totalPages,
        ];

        // Get term trends for all terms (numeric sections only - a developmental
        // student has symbols per quarter instead of term grades)
        $analytics['termTrends'] = $isDomainMode
            ? []
            : $this->buildTermTrendsFromDb($student, $subjects, $schoolYear);

        return view('student/analytics', [
            'title' => 'My Analytics - CSCS Tap n Track',
            'student' => $studentWithSection,
            'subjects' => $subjects,
            'analytics' => $analytics,
            'schoolYear' => $schoolYear,
            'currentTerm' => $currentTerm,
            'selectedTerm' => $termFilter,
            'attendanceFilter' => $attendanceFilter,
            'section' => $section,
            'isDomainMode' => $isDomainMode,
            'domainAnalytics' => $isDomainMode
                ? $this->buildDomainAnalytics($student, (int) $section['id'], (int) ($section['grade_level'] ?? 0), $schoolYear)
                : [],
        ]);
    }

    /**
     * Symbol-based analytics for a student in a non-numerical (developmental)
     * section: how many of the section's performance indicators are assessed,
     * which symbols were given, mastery (P + AP among rated indicators) and how
     * many indicators are covered per quarter.
     *
     * Counting is driven by the indicators themselves rather than raw row
     * counts, so these widgets always agree with the developmental progress
     * table on the My Grades page. Indicators that are no longer part of the
     * section's domain list but still carry symbols are included too, so a
     * student's progress is never silently hidden.
     */
    private function buildDomainAnalytics(array $student, int $sectionId, int $gradeLevel, string $schoolYear): array
    {
        $db = \Config\Database::connect();

        $categoryModel = new \App\Models\SnedCategoryModel();
        $domains = $categoryModel->getActiveCategories($sectionId, $gradeLevel);

        $fieldModel     = new \App\Models\SnedCategoryFieldModel();
        $fieldDomain    = [];
        $fieldsByDomain = [];
        foreach ($domains as $domain) {
            $domainId = (int) $domain['id'];
            $fieldsByDomain[$domainId] = $fieldModel->getCategoryFields($domainId);
            foreach ($fieldsByDomain[$domainId] as $field) {
                $fieldDomain[(int) $field['id']] = $domainId;
            }
        }

        // The student's symbols. When the current school year has none, fall
        // back to the student's latest recorded year so symbols written before
        // the year setting was corrected are never hidden.
        $rows = $db->table('sned_grades')
            ->where('student_id', (int) $student['id'])
            ->where('school_year', $schoolYear)
            ->get()->getResultArray();

        if ($rows === []) {
            $latestYear = $db->table('sned_grades')
                ->select('school_year')
                ->where('student_id', (int) $student['id'])
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()->getRowArray();
            if ($latestYear && (string) $latestYear['school_year'] !== (string) $schoolYear) {
                $rows = $db->table('sned_grades')
                    ->where('student_id', (int) $student['id'])
                    ->where('school_year', $latestYear['school_year'])
                    ->get()->getResultArray();
            }
        }

        $domainTotals = [];
        foreach ($domains as $domain) {
            $domainId = (int) $domain['id'];
            $domainTotals[$domainId] = [
                'name'     => (string) ($domain['name'] ?? ''),
                'total'    => count($fieldsByDomain[$domainId] ?? []),
                'assessed' => 0, 'observed' => 0,
                'P' => 0, 'AP' => 0, 'D' => 0, 'B' => 0, 'NO' => 0,
            ];
        }

        // field_id => [quarter => symbol]
        $symbolByField   = [];
        $symbols         = ['P' => 0, 'AP' => 0, 'D' => 0, 'B' => 0, 'NO' => 0];
        $quarterCoverage = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

        foreach ($rows as $row) {
            $symbol = strtoupper(trim((string) ($row['grade_symbol'] ?? '')));
            // NO/NA counts as observed but never towards mastery.
            $bucket = $symbol === 'NO/NA'
                ? 'NO'
                : (in_array($symbol, ['P', 'AP', 'D', 'B'], true) ? $symbol : null);
            $q   = (int) ($row['quarter'] ?? 0);
            $fid = (int) ($row['field_id'] ?? 0);

            if ($bucket === null || $fid <= 0 || $q < 1 || $q > 4) {
                continue;
            }

            $symbolByField[$fid][$q] = $symbol;
            $symbols[$bucket]++;
            $quarterCoverage[$q]++;

            $domainId = $fieldDomain[$fid] ?? 0;
            if (isset($domainTotals[$domainId])) {
                $domainTotals[$domainId][$bucket]++;
                if ($bucket === 'NO') {
                    $domainTotals[$domainId]['observed']++;
                }
            }
        }

        // Indicators carrying symbols but missing from the section's current
        // domain list (unticked domain, deactivated indicator, older setup).
        $spareFieldIds = array_values(array_diff(array_keys($symbolByField), array_keys($fieldDomain)));
        if ($spareFieldIds !== []) {
            $spareFields = $db->table('sned_category_fields')
                ->whereIn('id', $spareFieldIds)
                ->get()->getResultArray();

            $spareCategoryIds = [];
            foreach ($spareFields as $spareField) {
                $spareCategoryIds[(int) $spareField['category_id']] = true;
            }

            $spareNames = [];
            if ($spareCategoryIds !== []) {
                $spareCategories = $db->table('sned_categories')
                    ->whereIn('id', array_keys($spareCategoryIds))
                    ->get()->getResultArray();
                foreach ($spareCategories as $spareCategory) {
                    $spareNames[(int) $spareCategory['id']] = (string) ($spareCategory['name'] ?? '');
                }
            }

            foreach ($spareFields as $spareField) {
                $fid = (int) $spareField['id'];
                $cid = (int) $spareField['category_id'];
                if (! isset($spareNames[$cid])) {
                    continue;
                }
                if (! isset($domainTotals[$cid])) {
                    $domainTotals[$cid] = [
                        'name'     => $spareNames[$cid],
                        'total'    => 0,
                        'assessed' => 0, 'observed' => 0,
                        'P' => 0, 'AP' => 0, 'D' => 0, 'B' => 0, 'NO' => 0,
                    ];
                }
                $domainTotals[$cid]['total']++;
                foreach ($symbolByField[$fid] as $spareSymbol) {
                    $spareBucket = $spareSymbol === 'NO/NA' ? 'NO' : $spareSymbol;
                    if (! isset($symbols[$spareBucket])) {
                        continue;
                    }
                    $domainTotals[$cid][$spareBucket]++;
                    if ($spareBucket === 'NO') {
                        $domainTotals[$cid]['observed']++;
                    }
                }
                $fieldDomain[$fid] = $cid;
            }
        }

        $totalIndicators = 0;
        foreach ($domainTotals as $domainTotal) {
            $totalIndicators += $domainTotal['total'];
        }

        // Distinct indicators that received at least one symbol.
        $assessed = 0;
        foreach (array_keys($symbolByField) as $assessedFieldId) {
            $assessed++;
            $cid = $fieldDomain[$assessedFieldId] ?? 0;
            if (isset($domainTotals[$cid])) {
                $domainTotals[$cid]['assessed']++;
            }
        }

        $domainList = [];
        foreach ($domainTotals as $domainTotal) {
            $rated = $domainTotal['P'] + $domainTotal['AP'] + $domainTotal['D'] + $domainTotal['B'];
            $domainList[] = [
                'name'        => $domainTotal['name'],
                'total'       => $domainTotal['total'],
                'assessed'    => $domainTotal['assessed'],
                'observed'    => $domainTotal['observed'],
                'proficient'  => $domainTotal['P'],
                'approaching' => $domainTotal['AP'],
                'developing'  => $domainTotal['D'],
                'beginning'   => $domainTotal['B'],
                'mastery'     => $rated > 0
                    ? round((($domainTotal['P'] + $domainTotal['AP']) / $rated) * 100, 1)
                    : 0.0,
            ];
        }

        $masteryBase = $symbols['P'] + $symbols['AP'] + $symbols['D'] + $symbols['B'];

        return [
            'domains'         => $domainList,
            'symbols'         => $symbols,
            'totalIndicators' => $totalIndicators,
            'assessed'        => $assessed,
            'observed'        => $symbols['NO'],
            'masteryRate'     => $masteryBase > 0
                ? round((($symbols['P'] + $symbols['AP']) / $masteryBase) * 100, 1)
                : 0.0,
            'completionRate'  => $totalIndicators > 0
                ? round(($assessed / $totalIndicators) * 100, 1)
                : 0.0,
            'quarterCoverage' => $quarterCoverage,
            'quartersDone'    => count(array_filter($quarterCoverage)),
        ];
    }

    /**
     * Calculate student-specific analytics
     */
    private function calculateStudentAnalytics(array $student, array $subjects, string $schoolYear, int $currentTerm): array
    {
        $analytics = [
            'totalSubjects' => count($subjects),
            'gradeDistribution' => [
                'excellent' => 0,
                'very_good' => 0,
                'good' => 0,
                'fair' => 0,
                'passing' => 0,
                'failing' => 0,
            ],
            'subjectAverages' => [],
            'studentAverage' => 0,
            'highestGrade' => null,
            'lowestGrade' => null,
            'subjectGrades' => [],
        ];

        if ($subjects === []) {
            return $analytics;
        }

        $studentId = (int) $student['id'];
        $subjectIds = array_map(static fn ($s) => (int) $s['id'], $subjects);
        
        // Load grades for current term
        $gradeMap = $this->loadStudentGradeMap($studentId, $subjectIds, $schoolYear, $currentTerm);

        $totalGrades = 0;
        $gradeCount = 0;
        $allGrades = [];

        foreach ($subjects as $subject) {
            $subjectId = (int) $subject['id'];
            $key = $studentId . '_' . $subjectId;
            
            if (! isset($gradeMap[$key])) {
                continue;
            }

            $grade = $gradeMap[$key];
            $allGrades[] = $grade;
            $totalGrades += $grade;
            $gradeCount++;

            $analytics['subjectGrades'][$subjectId] = [
                'subject' => $subject['subject_name'] ?? 'Subject',
                'grade' => $grade,
            ];

            $analytics['subjectAverages'][] = [
                'subject' => $subject['subject_name'] ?? 'Subject',
                'grade' => $grade,
            ];
        }

        if ($gradeCount > 0) {
            $analytics['studentAverage'] = round($totalGrades / $gradeCount, 2);
            
            $analytics['highestGrade'] = [
                'value' => max($allGrades),
                'subject' => $analytics['subjectGrades'][array_search(max($allGrades), array_column($analytics['subjectGrades'], 'grade'))]['subject'] ?? 'N/A'
            ];
            
            $analytics['lowestGrade'] = [
                'value' => min($allGrades),
                'subject' => $analytics['subjectGrades'][array_search(min($allGrades), array_column($analytics['subjectGrades'], 'grade'))]['subject'] ?? 'N/A'
            ];

            // Grade distribution for this student
            $avg = $analytics['studentAverage'];
            $bucket = $this->gradeDistributionBucket($avg);
            $analytics['gradeDistribution'][$bucket] = 1;
        }

        // Calculate improvement rate
        if ($currentTerm > 1) {
            $prevAvg = $this->getStudentAverageForTerm($student, $subjects, $schoolYear, $currentTerm - 1);
            if ($prevAvg > 0 && $analytics['studentAverage'] > 0) {
                $analytics['improvementRate'] = round((($analytics['studentAverage'] - $prevAvg) / $prevAvg) * 100, 1);
            }
        }

        return $analytics;
    }

    /**
     * Load student grades for specific subjects and term
     */
    private function loadStudentGradeMap(int $studentId, array $subjectIds, string $schoolYear, int $term): array
    {
        if ($subjectIds === []) {
            return [];
        }

        $db = \Config\Database::connect();
        $suPh = implode(',', array_fill(0, count($subjectIds), '?'));
        $sql = "SELECT subject_id, grade FROM grades
                WHERE school_year = ? AND term = ?
                AND student_id = ? AND subject_id IN ({$suPh})";
        
        $params = array_merge([$schoolYear, $term, $studentId], $subjectIds);
        $rows = $db->query($sql, $params)->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            if ($row['grade'] === null || $row['grade'] === '') {
                continue;
            }
            $map[(int) $row['subject_id']] = (float) $row['grade'];
        }

        return $map;
    }

    /**
     * Get student's average for a specific term
     */
    private function getStudentAverageForTerm(array $student, array $subjects, string $schoolYear, int $term): float
    {
        if ($subjects === []) {
            return 0.0;
        }

        $studentId = (int) $student['id'];
        $subjectIds = array_map(static fn ($s) => (int) $s['id'], $subjects);
        $gradeMap = $this->loadStudentGradeMap($studentId, $subjectIds, $schoolYear, $term);

        $grades = array_values($gradeMap);
        if (empty($grades)) {
            return 0.0;
        }

        return round(array_sum($grades) / count($grades), 2);
    }

    /**
     * Build term trends for student
     */
    private function buildTermTrendsFromDb(array $student, array $subjects, string $schoolYear): array
    {
        if ($subjects === []) {
            return [
                ['term' => 'T1', 'average' => 0],
                ['term' => 'T2', 'average' => 0],
                ['term' => 'T3', 'average' => 0],
            ];
        }

        $trends = [];
        for ($t = 1; $t <= 3; $t++) {
            $avg = $this->getStudentAverageForTerm($student, $subjects, $schoolYear, $t);
            $trends[] = [
                'term' => 'T' . $t,
                'average' => $avg,
            ];
        }

        return $trends;
    }

    /**
     * Get student attendance records with filtering
     */
    private function getStudentAttendanceRecords(int $studentId, string $schoolYear, string $filter = 'semester', ?int $term = null): array
    {
        $db = \Config\Database::connect();
        
        $where = "a.student_id = ?";
        $params = [$studentId];

        // Apply filters
        if ($filter === 'week') {
            $startDate = date('Y-m-d', strtotime('monday this week'));
            $where .= " AND a.date >= ?";
            $params[] = $startDate;
        } elseif ($filter === 'month') {
            $startDate = date('Y-m-d', strtotime('first day of this month'));
            $where .= " AND a.date >= ?";
            $params[] = $startDate;
        } elseif ($filter === 'term' && $term) {
            // Term-based filtering (approximate dates)
            $termStartDates = [
                1 => date('Y-m-d', strtotime('June 1')),
                2 => date('Y-m-d', strtotime('November 1')),
                3 => date('Y-m-d', strtotime('March 1')),
            ];
            $startDate = $termStartDates[$term] ?? date('Y-m-d', strtotime('June 1'));
            $where .= " AND a.date >= ?";
            $params[] = $startDate;
        }

        $query = "SELECT a.*, 
                  CONCAT(s.first_name, ' ', s.last_name) as student_name,
                  s.lrn
                  FROM attendance a
                  JOIN students s ON s.id = a.student_id
                  WHERE {$where}
                  ORDER BY a.date DESC, a.created_at DESC";

        return $db->query($query, $params)->getResultArray();
    }

    /**
     * Calculate attendance statistics
     */
    private function calculateAttendanceStats(array $attendanceRecords): array
    {
        $stats = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
            'total' => 0,
            'attendanceRate' => 0,
        ];

        foreach ($attendanceRecords as $record) {
            $status = strtolower((string) $record['status']);
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
            $stats['total']++;
        }

        if ($stats['total'] > 0) {
            $presentLike = $stats['present'] + $stats['late'];
            $stats['attendanceRate'] = round(($presentLike / $stats['total']) * 100, 1);
        }

        return $stats;
    }

    /**
     * Grade distribution bucket
     */
    private function gradeDistributionBucket(float $avg): string
    {
        if ($avg >= 90) {
            return 'excellent';
        }
        if ($avg >= 85) {
            return 'very_good';
        }
        if ($avg >= 80) {
            return 'good';
        }
        if ($avg >= 75) {
            return 'fair';
        }
        if ($avg >= 70) {
            return 'passing';
        }

        return 'failing';
    }
}