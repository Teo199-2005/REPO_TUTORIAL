<?php

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Models\SectionModel;
use App\Models\StudentModel;
use App\Models\SubjectModel;
use App\Models\TeacherModel;
use Dompdf\Dompdf;
use Dompdf\Options;

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

        if (! $this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'))->with('error', 'Access denied.');
        }

        $userId = $this->auth->id();
        $teacherModel = new TeacherModel();
        $teacher = $teacherModel->where('user_id', $userId)->first();

        if (! $teacher) {
            helper('school_year');

            return view('teacher/analytics', [
                'title' => 'Class Analytics - CSCS Tap n Track',
                'error' => 'Teacher record not found',
                'schoolYear' => get_current_school_year(),
                'currentTerm' => get_current_term(),
                'analyticsSectionCount' => 0,
                'analytics' => $this->getEmptyAnalytics(0),
            ]);
        }

        helper('school_year');
        $schoolYear = get_current_school_year();
        $currentTerm = get_current_term();

        $scope = $this->collectScopedStudentsAndSubjects($teacher, $schoolYear);
        $myStudents = $scope['students'];
        $mySubjects = $scope['subjects'];
        $teacherSection = $scope['primarySection'];
        $sectionCount = count($scope['sectionIds']);

        $analytics = $this->calculateAnalytics($myStudents, $mySubjects, $schoolYear, $currentTerm, (int) $teacher['id']);

        $analytics['attendanceRecords'] = $this->getAttendanceRecords((int) $teacher['id'], $myStudents);
        $analytics['attendanceStats'] = $this->calculateAttendanceStats($analytics['attendanceRecords']);
        $analytics['attendanceRate'] = $analytics['attendanceStats']['attendanceRate'];

        if (! empty($analytics['studentPerformance'])) {
            usort($analytics['studentPerformance'], static fn ($a, $b) => $b['average'] <=> $a['average']);
        }

        if ($analytics['classAverage'] == 0 && empty($analytics['subjectAverages'])) {
            $emptyAnalytics = $this->getEmptyAnalytics(count($myStudents));
            if (isset($analytics['attendanceStats']) && $analytics['attendanceStats']['total'] > 0) {
                $emptyAnalytics['attendanceStats'] = $analytics['attendanceStats'];
                $emptyAnalytics['attendanceRecords'] = $analytics['attendanceRecords'];
                $emptyAnalytics['attendanceRate'] = $analytics['attendanceRate'];
            }
            $emptyAnalytics['termTrends'] = $analytics['termTrends'];
            $emptyAnalytics['studentsGradedForDistribution'] = (int) ($analytics['studentsGradedForDistribution'] ?? 0);
            $analytics = $emptyAnalytics;
        }

        // Non-numerical sections (Grade 1 CAMIA, SNED, SSES, ...) are assessed
        // with developmental-domain symbols (P/AP/D/B/NO-NA), not numeric
        // grades — the whole numeric pipeline reports zeros for them, so build
        // a symbol-based analytics payload instead.
        $isDomainMode = isset($teacherSection['id'], $teacherSection['grading_type'])
            && ($teacherSection['grading_type'] ?? 'numerical') === 'non_numerical';

        return view('teacher/analytics', [
            'title' => 'Class Analytics - CSCS Tap n Track',
            'teacher' => $teacher,
            'myStudents' => $myStudents,
            'mySubjects' => $mySubjects,
            'analytics' => $analytics,
            'schoolYear' => $schoolYear,
            'currentTerm' => $currentTerm,
            'teacherSection' => $teacherSection,
            'analyticsSectionCount' => $sectionCount,
            'isDomainMode' => $isDomainMode,
            'domainAnalytics' => $isDomainMode
                ? $this->buildDomainAnalytics($myStudents, (int) $teacherSection['id'], $schoolYear)
                : [],
        ]);
    }

    /**
     * Symbol-based analytics for a non-numerical section.
     *
     * Aggregates sned_grades for the section's students over the developmental
     * domains configured for the section (the ones ticked on the Sections page,
     * falling back to every shared domain). NO/NA entries count as observed
     * (towards completion) but never towards mastery.
     */
    private function buildDomainAnalytics(array $students, int $sectionId, string $schoolYear): array
    {
        helper('grade_level');
        $db = \Config\Database::connect();

        $empty = [
            'domains' => [], 'students' => [],
            'symbols' => ['P' => 0, 'AP' => 0, 'D' => 0, 'B' => 0, 'NO' => 0],
            'totalIndicators' => 0, 'assessed' => 0, 'observed' => 0,
            'masteryRate' => 0.0, 'completionRate' => 0.0,
            'quarterCoverage' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
        ];

        if ($students === []) {
            return $empty;
        }

        // The section's own domains: the ticked subset, or every shared domain.
        $selectedIds = function_exists('section_selected_domain_ids')
            ? section_selected_domain_ids($sectionId)
            : null;

        $categoryModel = new \App\Models\SnedCategoryModel();
        $section = $db->table('sections')->where('id', $sectionId)->get()->getRowArray();
        if ($selectedIds === null) {
            $domains = $categoryModel->getActiveCategories($sectionId, (int) ($section['grade_level'] ?? 0));
        } elseif ($selectedIds !== []) {
            $domains = $db->table('sned_categories')
                ->whereIn('id', $selectedIds)
                ->where('is_active', 1)
                ->orderBy('display_order', 'ASC')
                ->get()->getResultArray();
        } else {
            $domains = [];
        }

        if ($domains === []) {
            return $empty;
        }

        $fieldModel = new \App\Models\SnedCategoryFieldModel();
        $fieldDomain = [];
        $domainTotals = [];
        foreach ($domains as $domain) {
            $fields = $fieldModel->getCategoryFields((int) $domain['id']);
            $domainTotals[(int) $domain['id']] = [
                'name'   => $domain['name'],
                'total'  => count($fields),
                'assessed' => 0, 'observed' => 0, 'P' => 0, 'AP' => 0, 'D' => 0, 'B' => 0, 'NO' => 0,
            ];
            foreach ($fields as $field) {
                $fieldDomain[(int) $field['id']] = (int) $domain['id'];
            }
        }

        $studentIds = array_map(static fn ($s) => (int) $s['id'], $students);
        $rows = $db->table('sned_grades')
            ->whereIn('student_id', $studentIds)
            ->where('school_year', $schoolYear)
            ->get()->getResultArray();

        $studentStats = [];
        foreach ($students as $s) {
            $studentStats[(int) $s['id']] = [
                'name' => trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')),
                'assessed' => 0, 'observed' => 0,
                'P' => 0, 'AP' => 0, 'D' => 0, 'B' => 0, 'NO' => 0,
            ];
        }

        $symbols = ['P' => 0, 'AP' => 0, 'D' => 0, 'B' => 0, 'NO' => 0];
        $quarterCoverage = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

        foreach ($rows as $row) {
            $symbol   = strtoupper(trim((string) ($row['grade_symbol'] ?? '')));
            $sid      = (int) $row['student_id'];
            $domainId = (int) ($fieldDomain[(int) $row['field_id']] ?? 0);
            $q        = (int) ($row['quarter'] ?? 0);

            if ($symbol === '') {
                continue;
            }

            // NO/NA counts towards completion (the indicator was observed) but
            // never towards mastery.
            $bucket = $symbol === 'NO/NA'
                ? 'NO'
                : (in_array($symbol, ['P', 'AP', 'D', 'B'], true) ? $symbol : null);
            if ($bucket === null) {
                continue;
            }

            if (isset($studentStats[$sid])) {
                $studentStats[$sid]['assessed']++;
                $studentStats[$sid][$bucket]++;
                if ($bucket === 'NO') {
                    $studentStats[$sid]['observed']++;
                }
            }
            if (isset($domainTotals[$domainId])) {
                $domainTotals[$domainId]['assessed']++;
                $domainTotals[$domainId][$bucket]++;
                if ($bucket === 'NO') {
                    $domainTotals[$domainId]['observed']++;
                }
            }
            $symbols[$bucket]++;
            if (isset($quarterCoverage[$q])) {
                $quarterCoverage[$q]++;
            }
        }

        $totalIndicators = array_sum(array_column($domainTotals, 'total'));
        $assessed        = $symbols['P'] + $symbols['AP'] + $symbols['D'] + $symbols['B'] + $symbols['NO'];
        $observed        = $symbols['NO'];
        $masteryBase     = $assessed - $observed;

        $masteryOf = static function (array $s): float {
            $base = $s['P'] + $s['AP'] + $s['D'] + $s['B'];
            return $base > 0 ? round((($s['P'] + $s['AP']) / $base) * 100, 1) : 0.0;
        };

        $domainList = [];
        foreach ($domainTotals as $d) {
            $domainList[] = [
                'name'        => $d['name'],
                'total'       => $d['total'],
                'assessed'    => $d['assessed'],
                'observed'    => $d['observed'],
                'proficient'  => $d['P'],
                'approaching' => $d['AP'],
                'developing'  => $d['D'],
                'beginning'   => $d['B'],
                'mastery'     => $masteryOf($d),
            ];
        }

        $studentList = [];
        foreach ($studentStats as $s) {
            $s['mastery'] = $masteryOf($s);
            $studentList[] = $s;
        }

        return [
            'domains' => $domainList,
            'students' => $studentList,
            'symbols' => $symbols,
            'totalIndicators' => $totalIndicators,
            'assessed' => $assessed,
            'observed' => $observed,
            'masteryRate' => $masteryBase > 0
                ? round((($symbols['P'] + $symbols['AP']) / $masteryBase) * 100, 1)
                : 0.0,
            'completionRate' => $totalIndicators > 0
                ? round(($assessed / $totalIndicators) * 100, 1)
                : 0.0,
            'quarterCoverage' => $quarterCoverage,
        ];
    }

    /**
     * Students in sections where this teacher is adviser or on their schedule for the school year;
     * subjects = union of section subjects for those sections.
     *
     * @return array{students: list<array>, subjects: list<array>, sectionIds: list<int>, primarySection: ?array}
     */
    private function collectScopedStudentsAndSubjects(array $teacher, string $schoolYear): array
    {
        $db = \Config\Database::connect();
        $sectionModel = new SectionModel();
        $studentModel = new StudentModel();
        $subjectModel = new SubjectModel();

        $advisorySections = $sectionModel->where('adviser_id', $teacher['id'])->findAll();
        $advisoryIds = array_column($advisorySections, 'id');

        $scheduleRows = $db->table('teacher_schedules')
            ->select('section_id')
            ->where('teacher_id', $teacher['id'])
            ->where('school_year', $schoolYear)
            ->groupBy('section_id')
            ->get()
            ->getResultArray();
        $scheduledIds = array_column($scheduleRows, 'section_id');

        $allSectionIds = array_values(array_unique(array_filter(array_merge($advisoryIds, array_map('intval', $scheduledIds)))));

        if ($allSectionIds === []) {
            return [
                'students' => [],
                'subjects' => [],
                'sectionIds' => [],
                'primarySection' => null,
            ];
        }

        $students = $studentModel
            ->select('students.*, sections.section_name, sections.grade_level as section_grade')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->whereIn('students.section_id', $allSectionIds)
            ->where('students.enrollment_status', 'enrolled')
            ->orderBy('students.last_name', 'ASC')
            ->orderBy('students.first_name', 'ASC')
            ->findAll();

        $seen = [];
        $uniqueStudents = [];
        foreach ($students as $s) {
            $id = (int) $s['id'];
            if (! isset($seen[$id])) {
                $seen[$id] = true;
                $uniqueStudents[] = $s;
            }
        }

        $subjectsById = [];
        foreach ($allSectionIds as $sid) {
            foreach ($subjectModel->getSectionSubjects((int) $sid) as $sub) {
                $subjectsById[(int) $sub['id']] = $sub;
            }
        }

        $primarySection = $advisorySections[0] ?? $sectionModel->find($allSectionIds[0]);

        return [
            'students' => $uniqueStudents,
            'subjects' => array_values($subjectsById),
            'sectionIds' => $allSectionIds,
            'primarySection' => $primarySection,
        ];
    }

    private function calculateAnalytics(array $students, array $subjects, string $schoolYear, int $currentTerm, ?int $teacherId = null): array
    {
        $analytics = [
            'totalStudents' => count($students),
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
            'termTrends' => [],
            'studentPerformance' => [],
            'attendanceRate' => 0,
            'improvementRate' => 0,
            'classAverage' => 0,
            'studentsGradedForDistribution' => 0,
        ];

        if ($students === [] || $subjects === []) {
            $analytics['termTrends'] = $this->buildTermTrendsFromDb($students, $subjects, $schoolYear);

            return $analytics;
        }

        $studentIds = array_map(static fn ($s) => (int) $s['id'], $students);
        $subjectIds = array_map(static fn ($s) => (int) $s['id'], $subjects);
        $gradeMap   = $this->loadScopedGradeMap($studentIds, $subjectIds, $schoolYear, $currentTerm);

        $totalGrades = 0;
        $gradeCount  = 0;

        foreach ($subjects as $subject) {
            $subjectId     = (int) $subject['id'];
            $subjectGrades = [];
            foreach ($students as $student) {
                $key = (int) $student['id'] . '_' . $subjectId;
                if (! isset($gradeMap[$key])) {
                    continue;
                }
                $g = $gradeMap[$key];
                $subjectGrades[] = $g;
                $totalGrades += $g;
                $gradeCount++;
            }

            if ($subjectGrades !== []) {
                $analytics['subjectAverages'][] = [
                    'subject' => $subject['subject_name'] ?? 'Subject',
                    'average' => round(array_sum($subjectGrades) / count($subjectGrades), 2),
                    'count'   => count($subjectGrades),
                ];
            }
        }

        if ($gradeCount > 0) {
            $analytics['classAverage'] = round($totalGrades / $gradeCount, 2);
        }

        $graded = 0;
        foreach ($students as $student) {
            $vals = $this->scopedGradesForStudent((int) $student['id'], $subjectIds, $gradeMap);
            if ($vals === []) {
                continue;
            }
            $graded++;
            $avg = array_sum($vals) / count($vals);
            $bucket = $this->gradeDistributionBucket($avg);
            $analytics['gradeDistribution'][$bucket]++;
            $analytics['studentPerformance'][] = [
                'name'        => $student['first_name'] . ' ' . $student['last_name'],
                'average'     => round($avg, 2),
                'grade_count' => count($vals),
            ];
        }
        $analytics['studentsGradedForDistribution'] = $graded;

        $analytics['termTrends'] = $this->buildTermTrendsFromDb($students, $subjects, $schoolYear);

        if ($currentTerm > 1) {
            $prevAvg = $this->getClassAverageForTerm($students, $subjects, $schoolYear, $currentTerm - 1);
            if ($prevAvg > 0 && $analytics['classAverage'] > 0) {
                $analytics['improvementRate'] = round((($analytics['classAverage'] - $prevAvg) / $prevAvg) * 100, 1);
            }
        }

        if ($teacherId) {
            log_message('info', 'Teacher analytics: teacher_id=' . $teacherId . ' students=' . count($students) . ' subjects=' . count($subjects));
        }

        return $analytics;
    }

    /**
     * @param list<int> $studentIds
     * @param list<int> $subjectIds
     * @return array<string, float> keys "{studentId}_{subjectId}"
     */
    private function loadScopedGradeMap(array $studentIds, array $subjectIds, string $schoolYear, int $term): array
    {
        if ($studentIds === [] || $subjectIds === []) {
            return [];
        }

        $db     = \Config\Database::connect();
        $stPh   = implode(',', array_fill(0, count($studentIds), '?'));
        $suPh   = implode(',', array_fill(0, count($subjectIds), '?'));
        $sql    = "SELECT student_id, subject_id, grade FROM grades
                   WHERE school_year = ? AND term = ?
                   AND student_id IN ({$stPh}) AND subject_id IN ({$suPh})";
        $params = array_merge([$schoolYear, $term], $studentIds, $subjectIds);
        $rows   = $db->query($sql, $params)->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            if ($row['grade'] === null || $row['grade'] === '') {
                continue;
            }
            $map[(int) $row['student_id'] . '_' . (int) $row['subject_id']] = (float) $row['grade'];
        }

        return $map;
    }

    /**
     * @param list<int> $subjectIds
     * @param array<string, float> $gradeMap
     * @return list<float>
     */
    private function scopedGradesForStudent(int $studentId, array $subjectIds, array $gradeMap): array
    {
        $vals = [];
        foreach ($subjectIds as $subjectId) {
            $key = $studentId . '_' . (int) $subjectId;
            if (isset($gradeMap[$key])) {
                $vals[] = $gradeMap[$key];
            }
        }

        return $vals;
    }

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

    /**
     * @param list<array> $students
     * @param list<array> $subjects
     * @return list<array{term:string,average:float|int}>
     */
    private function buildTermTrendsFromDb(array $students, array $subjects, string $schoolYear): array
    {
        if ($students === [] || $subjects === []) {
            return [
                ['term' => 'T1', 'average' => 0],
                ['term' => 'T2', 'average' => 0],
                ['term' => 'T3', 'average' => 0],
                ['term' => 'T4', 'average' => 0],
            ];
        }

        $db = \Config\Database::connect();
        $studentIds = array_column($students, 'id');
        $subjectIds = array_column($subjects, 'id');
        $stPh = implode(',', array_fill(0, count($studentIds), '?'));
        $suPh = implode(',', array_fill(0, count($subjectIds), '?'));

        $trends = [];
        for ($t = 1; $t <= 4; $t++) {
            $sql = "SELECT AVG(g.grade) as a FROM grades g
                WHERE g.school_year = ? AND g.term = ?
                AND g.student_id IN ({$stPh}) AND g.subject_id IN ({$suPh})";
            $params = array_merge([$schoolYear, $t], $studentIds, $subjectIds);
            $row = $db->query($sql, $params)->getRow();
            $trends[] = [
                'term' => 'T' . $t,
                'average' => ($row && $row->a !== null) ? round((float) $row->a, 2) : 0,
            ];
        }

        return $trends;
    }

    /**
     * @param list<array> $students
     * @param list<array> $subjects
     */
    private function getClassAverageForTerm(array $students, array $subjects, string $schoolYear, int $term): float
    {
        if ($students === [] || $subjects === []) {
            return 0.0;
        }

        $db = \Config\Database::connect();
        $studentIds = array_column($students, 'id');
        $subjectIds = array_column($subjects, 'id');
        $stPh = implode(',', array_fill(0, count($studentIds), '?'));
        $suPh = implode(',', array_fill(0, count($subjectIds), '?'));
        $sql = "SELECT AVG(g.grade) as a FROM grades g
            WHERE g.school_year = ? AND g.term = ?
            AND g.student_id IN ({$stPh}) AND g.subject_id IN ({$suPh})";
        $params = array_merge([$schoolYear, $term], $studentIds, $subjectIds);
        $row = $db->query($sql, $params)->getRow();

        return ($row && $row->a !== null) ? round((float) $row->a, 2) : 0.0;
    }

    public function exportPdf()
    {
        if (! $this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        helper(['admin_access', 'school_year']);

        set_time_limit(120);
        ini_set('memory_limit', '256M');

        $teacherModel = new TeacherModel();
        $teacher = null;
        $userId = $this->auth->id();

        $teacherIdParam = $this->request->getGet('teacher_id');
        if (is_any_admin() && $teacherIdParam !== null && $teacherIdParam !== '') {
            $tid = (int) $teacherIdParam;
            if ($tid > 0) {
                $teacher = $teacherModel->find($tid);
            }
        }

        if (! $teacher && is_any_admin()) {
            $teacherName = $this->request->getGet('teacher');
            if ($teacherName) {
                $nameParts = explode(' ', trim((string) $teacherName), 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';
                $matches = $teacherModel->where('first_name', $firstName)->where('last_name', $lastName)->findAll();
                $teacher = count($matches) === 1 ? $matches[0] : null;
            }
        }

        if (! $teacher) {
            if (! $this->auth->user()->inGroup('teacher')) {
                return redirect()->to(base_url('/'))->with('error', 'Teacher not found or access denied.');
            }
            $teacher = $teacherModel->where('user_id', $userId)->first();
        }

        if (! $teacher) {
            return redirect()->back()->with('error', 'Teacher record not found');
        }

        if (! is_any_admin() && (int) $teacher['user_id'] !== (int) $userId) {
            return redirect()->back()->with('error', 'Access denied');
        }

        $schoolYear = get_current_school_year();
        $currentTerm = get_current_term();

        $scope = $this->collectScopedStudentsAndSubjects($teacher, $schoolYear);
        $myStudents = $scope['students'];
        $mySubjects = $scope['subjects'];

        $analytics = $this->calculateAnalytics($myStudents, $mySubjects, $schoolYear, $currentTerm, (int) $teacher['id']);

        $analytics['attendanceRecords'] = $this->getAttendanceRecords((int) $teacher['id'], $myStudents);
        $analytics['attendanceStats'] = $this->calculateAttendanceStats($analytics['attendanceRecords']);
        $analytics['attendanceRate'] = $analytics['attendanceStats']['attendanceRate'];

        if (! empty($analytics['studentPerformance'])) {
            usort($analytics['studentPerformance'], static fn ($a, $b) => $b['average'] <=> $a['average']);
        }

        if ($analytics['classAverage'] == 0 && empty($analytics['subjectAverages'])) {
            // Swap in the empty template but KEEP the real attendance data — a
            // non-numerical section has no numeric grades, so without this the
            // exported PDF reported 0.0% attendance even with records present.
            $emptyAnalytics = $this->getEmptyAnalytics(count($myStudents));
            if (($analytics['attendanceStats']['total'] ?? 0) > 0) {
                $emptyAnalytics['attendanceStats'] = $analytics['attendanceStats'];
                $emptyAnalytics['attendanceRecords'] = $analytics['attendanceRecords'];
                $emptyAnalytics['attendanceRate'] = $analytics['attendanceRate'];
            }
            $emptyAnalytics['termTrends'] = $analytics['termTrends'];
            $emptyAnalytics['studentsGradedForDistribution'] = (int) ($analytics['studentsGradedForDistribution'] ?? 0);
            $analytics = $emptyAnalytics;
        }

        // Non-numerical sections (Grade 1 CAMIA, SNED, SSES, ...) are assessed
        // with developmental-domain symbols, so the PDF renders the
        // symbol-based report instead of the numeric grade pipeline.
        $teacherSection = $scope['primarySection'];
        $isDomainMode = isset($teacherSection['id'], $teacherSection['grading_type'])
            && ($teacherSection['grading_type'] ?? 'numerical') === 'non_numerical';

        $data = [
            'teacher' => $teacher,
            'myStudents' => $myStudents,
            'mySubjects' => $mySubjects,
            'analytics' => $analytics,
            'schoolYear' => $schoolYear,
            'currentTerm' => $currentTerm,
            'teacherSection' => $teacherSection,
            'isDomainMode' => $isDomainMode,
            'domainAnalytics' => $isDomainMode
                ? $this->buildDomainAnalytics($myStudents, (int) $teacherSection['id'], $schoolYear)
                : [],
            'reportDate' => date('F j, Y', time()),
            'reportTime' => date('g:i A', time()),
        ];

        $html = view('teacher/analytics_pdf', $data);

        $options = new Options();
        $options->set('defaultFont', 'Times');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'CSCS_Teacher_Analytics_' . date('Y-m-d') . '.pdf';

        return $this->sendPdfInline($dompdf, $filename);
    }

    public function sendToAdmin()
    {
        if (! $this->auth->loggedIn()) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        if (! $this->verifyAnalyticsCsrf()) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'error' => 'Invalid security token. Refresh the page and try again.']);
        }

        if (! $this->auth->user()->inGroup('teacher')) {
            return $this->response->setJSON(['success' => false, 'error' => 'Teachers only']);
        }

        helper('school_year');

        $teacherModel = new TeacherModel();
        $userId = $this->auth->id();
        $teacher = $teacherModel->where('user_id', $userId)->first();

        if (! $teacher) {
            return $this->response->setJSON(['success' => false, 'error' => 'Teacher not found']);
        }

        $schoolYear = get_current_school_year();
        $currentTerm = get_current_term();

        $scope = $this->collectScopedStudentsAndSubjects($teacher, $schoolYear);
        $myStudents = $scope['students'];
        $mySubjects = $scope['subjects'];

        $analytics = $this->calculateAnalytics($myStudents, $mySubjects, $schoolYear, $currentTerm, (int) $teacher['id']);
        $analytics['attendanceRecords'] = $this->getAttendanceRecords((int) $teacher['id'], $myStudents);
        $analytics['attendanceStats'] = $this->calculateAttendanceStats($analytics['attendanceRecords']);
        $analytics['attendanceRate'] = $analytics['attendanceStats']['attendanceRate'];

        if ($analytics['classAverage'] == 0 && empty($analytics['subjectAverages'])) {
            // Keep the real attendance data when swapping in the empty
            // template (same fix as exportPdf) so announcements report the
            // actual attendance instead of zeros.
            $emptyAnalytics = $this->getEmptyAnalytics(count($myStudents));
            if (($analytics['attendanceStats']['total'] ?? 0) > 0) {
                $emptyAnalytics['attendanceStats'] = $analytics['attendanceStats'];
                $emptyAnalytics['attendanceRecords'] = $analytics['attendanceRecords'];
                $emptyAnalytics['attendanceRate'] = $analytics['attendanceRate'];
            }
            $emptyAnalytics['termTrends'] = $analytics['termTrends'];
            $analytics = $emptyAnalytics;
        }

        $announcementModel = model('AnnouncementModel');

        $title = 'Class Analytics Report - ' . $teacher['first_name'] . ' ' . $teacher['last_name'];
        $body = $this->formatAnalyticsForAnnouncement($analytics, $teacher, $schoolYear, $currentTerm);
        $slug = 'class-analytics-t' . (int) $teacher['id'] . '-' . date('Y-m-d-H-i-s') . '-' . bin2hex(random_bytes(4));

        $announcementData = [
            'title' => $title,
            'slug' => $slug,
            'body' => $body,
            'target_roles' => 'admin',
            'published_at' => date('Y-m-d H:i:s', time()),
            'created_by' => $this->auth->id(),
        ];

        if ($announcementModel->save($announcementData)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Analytics report sent to admin']);
        }

        return $this->response->setJSON(['success' => false, 'error' => 'Failed to create announcement']);
    }

    private function formatAnalyticsForAnnouncement(array $analytics, array $teacher, string $schoolYear, int $currentTerm): string
    {
        $body = '<h4>Class Analytics Report</h4>';
        $body .= '<p><strong>Teacher:</strong> ' . esc($teacher['first_name'] . ' ' . $teacher['last_name']) . '</p>';
        $body .= '<p><strong>School Year:</strong> ' . esc($schoolYear) . '</p>';
        $body .= '<p><strong>Term:</strong> ' . (int) $currentTerm . '</p>';
        $body .= '<p><strong>Report Date:</strong> ' . date('F j, Y g:i A', time()) . '</p>';

        $body .= '<h5>Summary Statistics</h5><ul>';
        $body .= '<li>Total Students: ' . (int) ($analytics['totalStudents'] ?? 0) . '</li>';
        $body .= '<li>Class Average: ' . number_format((float) ($analytics['classAverage'] ?? 0), 1) . '%</li>';
        $body .= '<li>Attendance Rate: ' . number_format((float) ($analytics['attendanceRate'] ?? 0), 1) . '%</li>';
        $body .= '<li>Improvement vs prior term: ' . number_format((float) ($analytics['improvementRate'] ?? 0), 1) . '%</li>';
        $body .= '</ul>';

        if (! empty($analytics['attendanceStats'])) {
            $body .= '<h5>Attendance Details</h5><ul>';
            $body .= '<li>Present: ' . (int) ($analytics['attendanceStats']['present'] ?? 0) . '</li>';
            $body .= '<li>Absent: ' . (int) ($analytics['attendanceStats']['absent'] ?? 0) . '</li>';
            $body .= '<li>Late: ' . (int) ($analytics['attendanceStats']['late'] ?? 0) . '</li>';
            $body .= '<li>Excused: ' . (int) ($analytics['attendanceStats']['excused'] ?? 0) . '</li>';
            $body .= '</ul>';
        }

        if (! empty($analytics['subjectAverages'])) {
            $body .= '<h5>Subject Averages</h5><ul>';
            foreach ($analytics['subjectAverages'] as $subject) {
                $body .= '<li>' . esc($subject['subject']) . ': ' . number_format((float) $subject['average'], 1) . '%</li>';
            }
            $body .= '</ul>';
        }

        return $body;
    }

    private function getAttendanceRecords(int $teacherId, array $students): array
    {
        $db = \Config\Database::connect();
        $studentIds = array_column($students, 'id');

        if ($studentIds === []) {
            return [];
        }

        $placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
        $query = "SELECT a.*, CONCAT(s.first_name, ' ', s.last_name) as student_name, s.lrn
                  FROM attendance a
                  JOIN students s ON s.id = a.student_id
                  WHERE a.teacher_id = ? AND a.student_id IN ({$placeholders})
                  ORDER BY a.date DESC, s.last_name ASC";

        $params = array_merge([$teacherId], $studentIds);

        return $db->query($query, $params)->getResultArray();
    }

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

    private function verifyAnalyticsCsrf(): bool
    {
        $security = \Config\Services::security();
        try {
            return $security->verify($this->request);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getEmptyAnalytics(int $studentCount = 0): array
    {
        return [
            'totalStudents' => $studentCount,
            'totalSubjects' => 0,
            'gradeDistribution' => [
                'excellent' => 0,
                'very_good' => 0,
                'good' => 0,
                'fair' => 0,
                'passing' => 0,
                'failing' => 0,
            ],
            'subjectAverages' => [],
            'termTrends' => [
                ['term' => 'T1', 'average' => 0],
                ['term' => 'T2', 'average' => 0],
                ['term' => 'T3', 'average' => 0],
            ],
            'studentPerformance' => [],
            'attendanceRate' => 0,
            'improvementRate' => 0,
            'classAverage' => 0,
            'attendanceRecords' => [],
            'studentsGradedForDistribution' => 0,
            'attendanceStats' => [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'excused' => 0,
                'total' => 0,
                'attendanceRate' => 0,
            ],
        ];
    }
}
