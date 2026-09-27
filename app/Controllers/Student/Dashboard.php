<?php
namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Models\StudentModel;
use App\Models\GradeModel;
use App\Models\AnnouncementModel;
use App\Models\SubjectModel;
use App\Models\NotificationModel;

class Dashboard extends BaseController
{
    protected $auth;
    protected $studentRecord;
    
    public function __construct()
    {
        $this->auth = auth();
    }

    protected function getStudentRecord()
    {
        if (!$this->studentRecord) {
            $studentModel = new StudentModel();
            $this->studentRecord = $studentModel->where('user_id', $this->auth->id())->first();
        }
        return $this->studentRecord;
    }

    /**
     * Generate performance message based on grade average
     */
    protected function getPerformanceMessage($average)
    {
        if ($average === null) {
            return ['message' => 'No grades yet', 'class' => 'bg-secondary text-white', 'icon' => 'bi-clipboard-data'];
        }

        if ($average >= 95) {
            return ['message' => 'Outstanding!', 'class' => 'bg-success text-white', 'icon' => 'bi-trophy-fill'];
        } elseif ($average >= 90) {
            return ['message' => 'Excellent work!', 'class' => 'bg-success text-white', 'icon' => 'bi-star-fill'];
        } elseif ($average >= 85) {
            return ['message' => 'Great work!', 'class' => 'bg-success text-white', 'icon' => 'bi-hand-thumbs-up-fill'];
        } elseif ($average >= 80) {
            return ['message' => 'Good job!', 'class' => 'bg-info text-white', 'icon' => 'bi-check-circle-fill'];
        } elseif ($average >= 75) {
            return ['message' => 'Keep improving!', 'class' => 'bg-warning text-white', 'icon' => 'bi-graph-up-arrow'];
        } else {
            return ['message' => 'Need more effort!', 'class' => 'bg-danger text-white', 'icon' => 'bi-lightning-charge-fill'];
        }
    }

    public function index()
    {
        // TEMPORARY: Bypass authentication to test if the issue is with auth or the view
        try {
            // Try to get auth status without redirecting
            $isLoggedIn = $this->auth->loggedIn();
            $authStatus = $isLoggedIn ? 'Logged in' : 'Not logged in';
        } catch (\Throwable $e) {
            $authStatus = 'Auth error: ' . $e->getMessage();
        }

        // Get recent notifications for current user
        $notificationModel = new NotificationModel();
        $notifications = $notificationModel->where('user_id', $this->auth->id() ?? 1)
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->findAll();
        
        $unreadCount = $notificationModel->where('user_id', $this->auth->id() ?? 1)
            ->where('is_read', false)
            ->countAllResults();

        // Try to get real student data if possible
        $student = null;
        $termAverage = null;
        $currentTerm = $this->getCurrentTerm();
        $schoolYear = get_current_school_year();

        try {
            if ($this->auth->loggedIn() && $this->auth->user()->inGroup('student')) {
                $studentModel = new \App\Models\StudentModel();
                $gradeModel = new \App\Models\GradeModel();

                $student = $studentModel->where('user_id', $this->auth->id())->first();

                if ($student) {
                    $termAverage = $gradeModel->getTermAverage($student['id'], $schoolYear, $currentTerm);
                    try {
                        $subjectModel = new \App\Models\SubjectModel();
                        $overviewSubjects = $student['section_id'] ? $subjectModel->getSectionSubjects($student['section_id']) : [];
                        $overviewGrades = [];
                        foreach (array_slice($overviewSubjects, 0, 5) as $subject) {
                            $gradeRow = $gradeModel->where('student_id', $student['id'])
                                ->where('subject_id', $subject['id'])
                                ->where('school_year', $schoolYear)
                                ->where('term', $currentTerm)
                                ->first();
                            $overviewGrades[] = [
                                'subject_name' => $subject['subject_name'] ?? '',
                                'subject_code' => $subject['subject_code'] ?? '',
                                'grade'        => ($gradeRow && $gradeRow['grade'] !== null) ? (float) $gradeRow['grade'] : null,
                            ];
                        }
                        $overviewTotalSubjects = count($overviewSubjects);
                        $overviewGradedCount = count(array_filter($overviewGrades, static fn($r) => $r['grade'] !== null));
                    } catch (\Throwable $e) {
                        $overviewGrades = [];
                        $overviewTotalSubjects = 0;
                        $overviewGradedCount = 0;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall back to test data if there's an error
        }

        // If no authenticated student, redirect to login
        if (!$student) {
            return redirect()->to(base_url('login'));
        }

        // The Dashboard is part of the locked area: a student whose profile is
        // still incomplete may only use My Profile, so send them there instead
        // of rendering a portal they cannot explore yet.
        if (! student_profile_complete($student)) {
            return redirect()->to(base_url('student/profile'))
                ->with('info', 'Your portal is locked until your profile is complete. Finish the checklist on My Profile to unlock the Dashboard and the other pages.');
        }

        // Featured poster for student dashboard
        helper('asset');
        $studentPoster = featured_dashboard_poster('student');

        return view('student/dashboard', [
            'title' => 'Student Dashboard - CSCS Tap n Track',
            'student' => $student,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'recentGrades' => [],
            'termAverage' => $termAverage,
            'currentTerm' => $currentTerm,
            'performanceMessage' => $this->getPerformanceMessage($termAverage),
            'overviewGrades' => $overviewGrades ?? [],
            'overviewTotalSubjects' => $overviewTotalSubjects ?? 0,
            'overviewGradedCount' => $overviewGradedCount ?? 0,
            'featuredPosterStudent'    => $studentPoster['path'],
            'featuredPosterStudentUrl' => $studentPoster['url'],
            'nutrition_profile_incomplete' => ! StudentModel::isNutritionProfileComplete($student),
        ]);
    }

    public function profile()
    {
        // Check if user is authenticated
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }
        
        if (!$this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'))->with('error', 'Access denied. Student role required.');
        }

        $student = $this->getStudentRecord();
        if (!$student) {
            return redirect()->to(base_url('/'))->with('error', 'Student record not found.');
        }

        $studentModel = new StudentModel();
        $studentWithSection = $studentModel->getStudentWithSection($student['id']);

        return view('student/profile', [
            'title' => 'My Profile - CSCS Tap n Track',
            'student' => $studentWithSection
        ]);
    }

    public function grades()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        if (!$this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'))->with('error', 'Access denied. Student role required.');
        }

        $student = $this->getStudentRecord();
        if (!$student) {
            return redirect()->to(base_url('/'))->with('error', 'Student record not found.');
        }

        $gradeModel = new GradeModel();
        $subjectModel = new SubjectModel();

        $schoolYear = $this->request->getGet('school_year') ?? get_current_school_year();
        $term = (int) ($this->request->getGet('term') ?? $this->getCurrentTerm());

        // Section grading type: non-numerical sections are assessed with
        // developmental symbols - numeric averages (and any stale 0.00 rows
        // left over from before the switch) must never be displayed.
        $sectionGradingType = 'numerical';
        if (!empty($student['section_id'])) {
            $sec = \Config\Database::connect()->table('sections')
                ->select('grading_type')
                ->where('id', (int) $student['section_id'])
                ->get()->getRowArray();
            $sectionGradingType = $sec['grading_type'] ?? 'numerical';
        }
        $isNonNumerical = in_array($sectionGradingType, ['non_numerical', 'custom'], true);

        // Get subjects assigned to student's section
        $subjects = (!$isNonNumerical && $student['section_id']) ? $subjectModel->getSectionSubjects($student['section_id']) : [];

        // Get grades for the selected term
        $grades = [];
        foreach ($subjects as $subject) {
            $grade = $gradeModel->where('student_id', $student['id'])
                ->where('subject_id', $subject['id'])
                ->where('school_year', $schoolYear)
                ->where('term', $term)
                ->first();

            $grades[] = [
                'subject' => $subject,
                'grade' => $grade
            ];
        }

        $termAverage = $gradeModel->getTermAverage($student['id'], $schoolYear, $term);
        $gwa = $gradeModel->getFinalAverage($student['id'], $schoolYear);

        $allTermGrades = [];
        for ($t = 1; $t <= 3; $t++) {
            $allTermGrades[$t] = $gradeModel->getTermAverage($student['id'], $schoolYear, $t);
        }

        $canEnrollNextYear = ($gwa !== null && $gwa >= 75.0);

        if ($isNonNumerical) {
            // Symbols-based section: hide numeric averages entirely.
            $termAverage = null;
            $gwa = null;
            $allTermGrades = [1 => null, 2 => null, 3 => null];
            $canEnrollNextYear = false;
        } elseif ($gwa === null) {
            $gwa = null;
            $allTermGrades = [1 => null, 2 => null, 3 => null];
            $canEnrollNextYear = false;
        }

        // Developmental-domain progress for non-numerical sections (SNED /
        // custom): the section's domains and their performance indicators,
        // each with the student's quarterly symbols (P/AP/D/B/NO-NA), plus
        // summary stats. Replaces the numeric term/GWA view for them.
        $domainProgress = [];
        $domainSummary = [
            'assessed'        => 0,
            'totalIndicators' => 0,
            'masteryRate'     => 0.0,
            'completionRate'  => 0.0,
            'symbols'         => ['P' => 0, 'AP' => 0, 'D' => 0, 'B' => 0, 'NO' => 0],
            'quarterCounts'   => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
            'quartersDone'    => 0,
        ];
        if ($isNonNumerical && !empty($student['section_id'])) {
            $db = \Config\Database::connect();
            $section = $db->table('sections')
                ->where('id', (int) $student['section_id'])
                ->get()->getRowArray();

            // The student's recorded symbols are loaded first, so every widget
            // below is counted from exactly the indicators shown in the table
            // (no "0 assessed" beside a 100% mastery rate).
            $rows = $db->table('sned_grades')
                ->where('student_id', (int) $student['id'])
                ->where('school_year', $schoolYear)
                ->get()->getResultArray();

            if ($rows === []) {
                // The student's symbols may be stamped with a legacy /
                // mis-stamped school year (rows written before the year
                // setting was corrected). Fall back to the student's latest
                // recorded year so their progress is never hidden by the
                // year filter.
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

            // field_id => [quarter => symbol].
            $symbolByField = [];
            foreach ($rows as $row) {
                $symbol = strtoupper(trim((string) ($row['grade_symbol'] ?? '')));
                if ($symbol === '') {
                    continue;
                }
                // NO/NA counts as observed but never towards mastery.
                $bucket = $symbol === 'NO/NA'
                    ? 'NO'
                    : (in_array($symbol, ['P', 'AP', 'D', 'B'], true) ? $symbol : null);
                if ($bucket === null) {
                    continue;
                }

                $q   = (int) ($row['quarter'] ?? 0);
                $fid = (int) ($row['field_id'] ?? 0);
                if ($fid <= 0 || $q < 1 || $q > 4) {
                    continue;
                }

                $symbolByField[$fid][$q] = $symbol;
            }

            // The section's own domains: the ticked subset, or every shared domain.
            $selectedIds = function_exists('section_selected_domain_ids')
                ? section_selected_domain_ids((int) $student['section_id'])
                : null;
            if ($selectedIds === null) {
                $domainRowsList = (new \App\Models\SnedCategoryModel())
                    ->getActiveCategories((int) $student['section_id'], (int) ($section['grade_level'] ?? 0));
            } elseif ($selectedIds !== []) {
                $domainRowsList = $db->table('sned_categories')
                    ->whereIn('id', $selectedIds)
                    ->where('is_active', 1)
                    ->orderBy('display_order', 'ASC')
                    ->get()->getResultArray();
            } else {
                $domainRowsList = [];
            }

            $fieldModel  = new \App\Models\SnedCategoryFieldModel();
            $fieldDomain = [];
            foreach ($domainRowsList as $domain) {
                $domainId = (int) $domain['id'];
                $fields   = [];
                foreach ($fieldModel->getCategoryFields($domainId) as $field) {
                    $fieldId  = (int) $field['id'];
                    $fields[] = [
                        'id'      => $fieldId,
                        'name'    => (string) ($field['field_name'] ?? ''),
                        'symbols' => $symbolByField[$fieldId] ?? [],
                    ];
                    $fieldDomain[$fieldId] = $domainId;
                }
                $domainProgress[$domainId] = [
                    'id'     => $domainId,
                    'name'   => (string) ($domain['name'] ?? ''),
                    'fields' => $fields,
                ];
            }

            // Safety net: symbols recorded for indicators outside that list
            // (a domain unticked later, a deactivated indicator, or rows left
            // over from an earlier setup) are still shown, so a student's
            // progress is never silently hidden.
            $spareFieldIds = array_values(array_diff(array_keys($symbolByField), array_keys($fieldDomain)));
            if ($spareFieldIds !== []) {
                $spareFields = $db->table('sned_category_fields')
                    ->whereIn('id', $spareFieldIds)
                    ->get()->getResultArray();

                $spareCategoryIds = [];
                foreach ($spareFields as $spareField) {
                    $spareCategoryId = (int) ($spareField['category_id'] ?? 0);
                    if ($spareCategoryId > 0) {
                        $spareCategoryIds[$spareCategoryId] = true;
                    }
                }

                $spareCategoryNames = [];
                if ($spareCategoryIds !== []) {
                    $spareCategories = $db->table('sned_categories')
                        ->whereIn('id', array_keys($spareCategoryIds))
                        ->get()->getResultArray();
                    foreach ($spareCategories as $spareCategory) {
                        $spareCategoryNames[(int) $spareCategory['id']] = (string) ($spareCategory['name'] ?? '');
                    }
                }

                foreach ($spareFields as $spareField) {
                    $spareFieldId    = (int) $spareField['id'];
                    $spareCategoryId = (int) ($spareField['category_id'] ?? 0);
                    if (isset($fieldDomain[$spareFieldId]) || ! isset($spareCategoryNames[$spareCategoryId])) {
                        continue;
                    }
                    if (! isset($domainProgress[$spareCategoryId])) {
                        $domainProgress[$spareCategoryId] = [
                            'id'     => $spareCategoryId,
                            'name'   => $spareCategoryNames[$spareCategoryId],
                            'fields' => [],
                        ];
                    }
                    $domainProgress[$spareCategoryId]['fields'][] = [
                        'id'      => $spareFieldId,
                        'name'    => (string) ($spareField['field_name'] ?? ''),
                        'symbols' => $symbolByField[$spareFieldId],
                    ];
                    $fieldDomain[$spareFieldId] = $spareCategoryId;
                }
            }

            $domainProgress = array_values($domainProgress);

            // Totals, counted strictly from the indicators listed above.
            $domainSummary['totalIndicators'] = 0;
            $ratedFields = [];
            foreach ($domainProgress as $domainRow) {
                foreach ($domainRow['fields'] as $field) {
                    $domainSummary['totalIndicators']++;
                    if ($field['symbols'] !== []) {
                        $ratedFields[(int) $field['id']] = true;
                    }
                    foreach ($field['symbols'] as $quarter => $symbol) {
                        $bucket = $symbol === 'NO/NA' ? 'NO' : $symbol;
                        if (! isset($domainSummary['symbols'][$bucket])) {
                            continue;
                        }
                        $domainSummary['symbols'][$bucket]++;
                        if (isset($domainSummary['quarterCounts'][(int) $quarter])) {
                            $domainSummary['quarterCounts'][(int) $quarter]++;
                        }
                    }
                }
            }

            $domainSummary['assessed'] = count($ratedFields);
            $domainSummary['completionRate'] = $domainSummary['totalIndicators'] > 0
                ? round(($domainSummary['assessed'] / $domainSummary['totalIndicators']) * 100, 1)
                : 0.0;

            $rated = $domainSummary['symbols']['P'] + $domainSummary['symbols']['AP'];
            $masteryBase = $domainSummary['symbols']['P'] + $domainSummary['symbols']['AP']
                + $domainSummary['symbols']['D'] + $domainSummary['symbols']['B'];
            $domainSummary['masteryRate'] = $masteryBase > 0
                ? round(($rated / $masteryBase) * 100, 1)
                : 0.0;

            foreach ($domainSummary['quarterCounts'] as $count) {
                if ($count > 0) {
                    $domainSummary['quartersDone']++;
                }
            }
        }

        // Next-school-year promotion context (eligibility, application status,
        // graduating/failed/SNED handling) used by the My Grades page.
        $promotion = $this->getPromotionContext($student, $gwa === null ? null : (float) $gwa, $allTermGrades);
        $canEnrollNextYear = $promotion['eligibleToApply'];

        return view('student/grades', [
            'title' => 'My Grades - CSCS Tap n Track',
            'student' => $student,
            'grades' => $grades,
            'schoolYear' => $schoolYear,
            'term' => $term,
            'termAverage' => $termAverage,
            'gwa' => $gwa,
            'canEnrollNextYear' => $canEnrollNextYear,
            'allTermGrades' => $allTermGrades,
            'promotion' => $promotion,
            'isNonNumerical' => $isNonNumerical,
            'domainProgress' => $domainProgress,
            'domainSummary' => $domainSummary,
        ]);
    }

    /**
     * Student applies (requests) enrollment to the next grade level for the
     * next school year. Creates a pending row in next_year_applications that
     * the admin approves (promotes) or rejects from the admin portal.
     */
    public function applyNextYear()
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('student')) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $student = $this->getStudentRecord();
        if (!$student) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Student record not found.']);
        }

        $gradeModel = new GradeModel();
        $schoolYear = get_current_school_year();

        $allTermGrades = [];
        for ($t = 1; $t <= 3; $t++) {
            $allTermGrades[$t] = $gradeModel->getTermAverage($student['id'], $schoolYear, $t);
        }
        $gwa = $gradeModel->getFinalAverage($student['id'], $schoolYear);

        // Re-validate eligibility server-side (never trust the client).
        $context = $this->getPromotionContext($student, $gwa === null ? null : (float) $gwa, $allTermGrades);

        if ($context['isGraduating']) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'You have completed the highest level offered by this school. No further application is needed.',
            ]);
        }

        if ($context['application'] && in_array($context['application']['status'], ['pending', 'approved'], true)) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'You already have a ' . $context['application']['status'] . ' application for S.Y. ' . $context['nextSchoolYear'] . '.',
            ]);
        }

        if (!$context['eligibleToApply']) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'You are not yet eligible to apply for the next school year. Please talk to your adviser.',
            ]);
        }

        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $inserted = $db->table('next_year_applications')->insert([
            'student_id'          => (int) $student['id'],
            'current_grade_level' => $context['gradeLevel'],
            'next_grade_level'    => $context['nextGradeLevel'],
            'gwa'                 => $gwa !== null ? number_format((float) $gwa, 2, '.', '') : '0.00',
            'school_year'         => $context['nextSchoolYear'],
            'status'              => 'pending',
            'applied_at'          => $now,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        if (!$inserted) {
            return $this->response->setJSON(['success' => false, 'error' => 'Failed to submit your application. Please try again.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Your application to enroll in ' . $context['nextGradeLabel'] . ' for S.Y. ' . $context['nextSchoolYear'] . ' has been submitted. Please wait for the school admin\'s approval.',
        ]);
    }

    /**
     * Compute everything the My Grades page needs about next-school-year
     * promotion: grading type, term completion, pass/fail, graduation and the
     * student's existing next_year_applications row (if any).
     */
    private function getPromotionContext(array $student, ?float $gwa, array $allTermGrades): array
    {
        $db = \Config\Database::connect();

        $gradeLevel = (int) ($student['grade_level'] ?? 0);
        $schoolYear = get_current_school_year();

        // Next school year, e.g. 2026-2027 -> 2027-2028
        $years = explode('-', $schoolYear);
        $nextSchoolYear = count($years) === 2 ? (($years[0] + 1) . '-' . ($years[1] + 1)) : $schoolYear;

        // Grading type of the student's section (numerical vs SNED/non-numerical/custom)
        $gradingType = 'numerical';
        if (!empty($student['section_id'])) {
            $section = $db->table('sections')
                ->select('grading_type')
                ->where('id', (int) $student['section_id'])
                ->get()->getRowArray();
            $gradingType = $section['grading_type'] ?? 'numerical';
        }
        $isNonNumerical = in_array($gradingType, ['non_numerical', 'custom'], true);

        // Grade levels with no "next level" in this school:
        // 6 = Grade 6 (elementary exit), 7 = SNED, 99 = custom class
        $isGraduating = in_array($gradeLevel, [6, 7, 99], true);

        $context = [
            'gradeLevel'      => $gradeLevel,
            'gradeLabel'      => grade_level_label($gradeLevel),
            'nextGradeLevel'  => $gradeLevel + 1,
            'nextGradeLabel'  => grade_level_label($gradeLevel + 1),
            'nextSchoolYear'  => $nextSchoolYear,
            'isGraduating'    => $isGraduating,
            'isNonNumerical'  => $isNonNumerical,
            'termsCompleted'  => false,
            'snedQuartersDone'  => 0,
            'snedQuartersTotal' => 0,
            'passed'          => false,
            'failed'          => false,
            'eligibleToApply' => false,
            'application'     => null,
        ];

        if ($isNonNumerical) {
            // SNED / custom sections use developmental symbols (P/AP/D/B/NO-NA)
            // instead of numeric grades, so there is no GWA. Eligibility is
            // based on completing ALL quarters; the pass/fail judgment and the
            // final approval remain with the adviser/admin.
            $snedGrades = new \App\Models\SnedGradeModel();
            // SNED quarters are fixed 1-4 (see sned_helper sned_quarters()).
            $quarters   = [1, 2, 3, 4];
            $quartersDone = 0;
            foreach ($quarters as $q) {
                if (!empty($snedGrades->getStudentQuarterGrades((int) $student['id'], $schoolYear, (int) $q))) {
                    $quartersDone++;
                }
            }
            $context['snedQuartersDone']  = $quartersDone;
            $context['snedQuartersTotal'] = count($quarters);
            $context['termsCompleted']    = ($quartersDone >= count($quarters));
            $context['eligibleToApply']   = !$isGraduating && $context['termsCompleted'];
        } else {
            // Numerical: student must have grades in all 3 terms and a GWA of
            // at least 75 to be eligible for promotion.
            $termsCompleted = isset($allTermGrades[1], $allTermGrades[2], $allTermGrades[3])
                && $allTermGrades[1] !== null
                && $allTermGrades[2] !== null
                && $allTermGrades[3] !== null;

            $passed = $termsCompleted && $gwa !== null && $gwa >= 75.0;
            $failed = $termsCompleted && $gwa !== null && $gwa < 75.0;

            $context['termsCompleted'] = $termsCompleted;
            $context['passed']         = $passed;
            $context['failed']         = $failed;
            $context['eligibleToApply'] = !$isGraduating && $passed;
        }

        // Existing application for the next school year (if the student applied).
        if (!$isGraduating) {
            $context['application'] = $db->table('next_year_applications')
                ->where('student_id', (int) $student['id'])
                ->where('school_year', $nextSchoolYear)
                ->orderBy('id', 'DESC')
                ->get()->getRowArray();
        }

        return $context;
    }

    public function schedule()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }
        
        if (!$this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'))->with('error', 'Access denied. Student role required.');
        }

        $student = $this->getStudentRecord();
        if (!$student) {
            return redirect()->to(base_url('/'))->with('error', 'Student record not found.');
        }

        $student   = $this->getStudentRecord();
        $schedules = $this->studentWeekSchedule($student);

        return view('student/schedule', [
            'title' => 'Class Schedule - CSCS Tap n Track',
            'student' => $student,
            'schedules' => $schedules
        ]);
    }

    /**
     * PDF twin of the Class Schedule page (student/schedule-pdf).
     *
     * Reads the same week through studentWeekSchedule() and lays it out with
     * reports/_schedule_grid, so the download can never disagree with the page.
     */
    public function schedulePdf()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        if (!$this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'))->with('error', 'Access denied. Student role required.');
        }

        $student = $this->getStudentRecord();
        if (!$student) {
            return redirect()->to(base_url('/'))->with('error', 'Student record not found.');
        }

        $html = view('student/schedule_pdf', [
            'student'    => $student,
            'schedules'  => $this->studentWeekSchedule($student),
            'schoolYear' => get_current_school_year(),
            'reportDate' => date('F j, Y'),
        ]);

        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Times');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $this->sendPdfInline($dompdf, 'CSCS_Class_Schedule_' . date('Y-m-d') . '.pdf');
    }

    /**
     * The student's section timetable as $schedules[day]['H:i-H:i'] = row.
     *
     * Shared by schedule() and schedulePdf(). A student without a section - or a
     * query that cannot run - yields an empty week, which both callers render as
     * the "no schedule" message.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function studentWeekSchedule(array $student): array
    {
        $schedules = [];

        if (empty($student['section_id'])) {
            return $schedules;
        }

        $db = \Config\Database::connect();

        try {
            $classSchedules = $db->query("
                SELECT ts.*, sub.subject_name,
                       CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                FROM teacher_schedules ts
                LEFT JOIN subjects sub ON sub.id = ts.subject_id
                LEFT JOIN teachers t ON t.id = ts.teacher_id
                WHERE ts.section_id = ? AND ts.school_year = ?
                ORDER BY ts.day_of_week, ts.start_time
            ", [$student['section_id'], get_current_school_year()])->getResultArray();

            // Organize schedules by day and time
            foreach ($classSchedules as $schedule) {
                $timeSlot = date('H:i', strtotime($schedule['start_time'])) . '-' . date('H:i', strtotime($schedule['end_time']));
                $schedules[strtolower($schedule['day_of_week'])][$timeSlot] = $schedule;
            }
        } catch (\Exception $e) {
            $schedules = [];
        }

        return $schedules;
    }

    public function announcements()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }
        
        if (!$this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'))->with('error', 'Access denied. Student role required.');
        }

        $student = $this->getStudentRecord();
        $db = \Config\Database::connect();
        $userId = (int) $this->auth->user()->id;

        $filter = strtolower(trim((string) $this->request->getGet('filter')));
        if (! in_array($filter, ['all', 'unread', 'read'], true)) {
            $filter = 'all';
        }
        $search = trim((string) $this->request->getGet('q'));
        $page   = max(1, (int) $this->request->getGet('page'));
        $perPage = 8;

        // Visibility scope shared by data + count queries.
        $gradeKey = $student ? 'grade_' . (int) $student['grade_level'] : null;
        $sectionKey = ($student && (int) ($student['section_id'] ?? 0) > 0)
            ? 'section_' . (int) $student['section_id'] : null;
        $visibleKeys = array_values(array_filter(['student', 'all', $gradeKey, $sectionKey]));

        $countScoped = static function (string $having = '') use ($db, $userId, $visibleKeys, $search): int {
            $b = $db->table('announcements a')
                ->select('a.id')
                ->join('announcement_reads ar', 'ar.announcement_id = a.id AND ar.user_id = ' . $userId, 'left')
                ->where('a.deleted_at', null)
                ->whereIn('a.target_roles', $visibleKeys)
                ->groupBy('a.id');
            if ($search !== '') {
                $b->groupStart()->like('a.title', $search)->orLike('a.body', $search)->groupEnd();
            }
            if ($having !== '') {
                $b->having($having);
            }
            $row = $db->query('SELECT COUNT(*) AS total FROM (' . $b->getCompiledSelect() . ') AS scoped')->getRowArray();
            return (int) ($row['total'] ?? 0);
        };

        $havingUnread = 'MAX(CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END) = 0';
        $havingRead = 'MAX(CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END) = 1';
        $pillCounts = [
            'all'    => $countScoped(),
            'unread' => $countScoped($havingUnread),
            'read'   => $countScoped($havingRead),
        ];
        $total = $pillCounts[$filter] ?? $pillCounts['all'];
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        // Page of announcements + sender info.
        $builder = $db->table('announcements a');
        $builder->select('a.*, CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END as is_read', false);
        $builder->select('u.username AS sender_username, u.first_name AS sender_first, u.last_name AS sender_last');
        $builder->select('t.first_name AS teacher_first, t.last_name AS teacher_last');
        $builder->join('announcement_reads ar', 'ar.announcement_id = a.id AND ar.user_id = ' . $userId, 'left');
        $builder->where('a.deleted_at', null);
        $builder->join('users u', 'u.id = a.created_by', 'left');
        $builder->join('teachers t', 't.user_id = u.id', 'left');
        $builder->whereIn('a.target_roles', $visibleKeys);
        $builder->groupBy('a.id');
        if ($search !== '') {
            $builder->groupStart()->like('a.title', $search)->orLike('a.body', $search)->groupEnd();
        }
        if ($filter === 'unread') {
            $builder->having('is_read = 0');
        } elseif ($filter === 'read') {
            $builder->having('is_read = 1');
        }
        $builder->orderBy('a.created_at', 'DESC');
        $builder->limit($perPage, $offset);
        $announcements = $builder->get()->getResultArray();

        foreach ($announcements as &$row) {
            $teacherFirst = trim((string) ($row['teacher_first'] ?? ''));
            $teacherLast = trim((string) ($row['teacher_last'] ?? ''));
            $senderFirst = trim((string) ($row['sender_first'] ?? ''));
            $senderLast = trim((string) ($row['sender_last'] ?? ''));
            if ($teacherFirst !== '' || $teacherLast !== '') {
                $row['sender_name'] = trim($teacherFirst . ' ' . $teacherLast);
            } elseif ($senderFirst !== '' || $senderLast !== '') {
                $row['sender_name'] = trim($senderFirst . ' ' . $senderLast);
            } elseif (! empty($row['sender_username'])) {
                $row['sender_name'] = (string) $row['sender_username'];
            } else {
                $row['sender_name'] = 'School Administration';
            }
            $row['sent_at'] = $row['published_at'] ?? $row['created_at'];
        }
        unset($row);

        return view('student/announcements', [
            'title' => 'Announcements - CSCS Tap n Track',
            'announcements' => $announcements,
            'filter' => $filter,
            'search' => $search,
            'pillCounts' => $pillCounts,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function viewAnnouncement($id)
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }
        
        if (!$this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'))->with('error', 'Access denied. Student role required.');
        }

        $announcementModel = new AnnouncementModel();
        $announcement = $announcementModel->find((int) $id);

        if (!$announcement) {
            return redirect()->to('student/announcements')->with('error', 'Announcement not found.');
        }

        $db = \Config\Database::connect();
        $userId = $this->auth->user()->id;
        $exists = $db->table('announcement_reads')->where(['announcement_id' => (int) $id, 'user_id' => $userId])->get()->getRow();
        if (!$exists) {
            $db->table('announcement_reads')->insert(['announcement_id' => (int) $id, 'user_id' => $userId, 'read_at' => date('Y-m-d H:i:s')]);
        }

        // Sender info for the detail header.
        $senderName = 'School Administration';
        try {
            $senderRow = $db->table('announcements a')
                ->select('u.username, u.first_name, u.last_name, t.first_name AS teacher_first, t.last_name AS teacher_last')
                ->join('users u', 'u.id = a.created_by', 'left')
                ->join('teachers t', 't.user_id = u.id', 'left')
                ->where('a.id', (int) $id)
                ->get()->getRowArray();
            if ($senderRow) {
                $tf = trim((string) ($senderRow['teacher_first'] ?? ''));
                $tl = trim((string) ($senderRow['teacher_last'] ?? ''));
                $sf = trim((string) ($senderRow['first_name'] ?? ''));
                $sl = trim((string) ($senderRow['last_name'] ?? ''));
                if ($tf !== '' || $tl !== '') {
                    $senderName = trim($tf . ' ' . $tl);
                } elseif ($sf !== '' || $sl !== '') {
                    $senderName = trim($sf . ' ' . $sl);
                } elseif (! empty($senderRow['username'])) {
                    $senderName = (string) $senderRow['username'];
                }
            }
        } catch (\Throwable $e) {
            // Keep fallback label.
        }
        $announcement['sender_name'] = $senderName;
        $announcement['sent_at'] = $announcement['published_at'] ?? $announcement['created_at'];

        return view('student/announcement_view', [
            'title' => $announcement['title'] . ' - CSCS Tap n Track',
            'announcement' => $announcement
        ]);
    }

    public function updateProfile()
    {
        // Check if user is authenticated
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }
        
        if (!$this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'))->with('error', 'Access denied. Student role required.');
        }

        $student = $this->getStudentRecord();
        if (!$student) {
            return redirect()->to(base_url('/'))->with('error', 'Student record not found.');
        }

        // Normalise legacy phone formats before validation and the writes below.
        phone_normalize_request($this->request, ['contact_number', 'emergency_contact_number']);

        $rules = [
            'contact_number' => phone_validation_rule(),
            'address' => 'permit_empty',
            'emergency_contact_name' => 'permit_empty|max_length[255]',
            'emergency_contact_number' => phone_validation_rule(),
            'emergency_contact_relationship' => 'permit_empty|max_length[50]'
        ];

        if (!$this->validate($rules, phone_validation_messages([
            'contact_number' => 'Contact Number',
            'emergency_contact_number' => 'Emergency Contact Number',
        ]))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $studentModel = new StudentModel();
        $updateData = [
            'contact_number' => $this->request->getPost('contact_number'),
            'address' => $this->request->getPost('address'),
            'emergency_contact_name' => $this->request->getPost('emergency_contact_name'),
            'emergency_contact_number' => $this->request->getPost('emergency_contact_number'),
            'emergency_contact_relationship' => $this->request->getPost('emergency_contact_relationship')
        ];

        if ($studentModel->update($student['id'], $updateData)) {
            return redirect()->to(base_url('student/profile'))->with('success', 'Profile updated successfully.');
        }

        return redirect()->back()->with('error', 'Failed to update profile.');
    }

    /**
     * Get current term from system settings
     */
    private function getCurrentTerm(): int
    {
        $db = \Config\Database::connect();
        $termSetting = $db->table('system_settings')
            ->where('setting_key', 'current_term')
            ->get()->getRowArray();

        return (int) ($termSetting['setting_value'] ?? 1);
    }

    /**
     * Determine if app is in development/test mode to allow UI preview without auth
     */
    private function isDevTestMode(): bool
    {
        return defined('ENVIRONMENT') && ENVIRONMENT !== 'production';
    }


    public function viewReportCard()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }
        
        if (!$this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'))->with('error', 'Access denied.');
        }

        $student = $this->getStudentRecord();
        if (!$student) {
            return redirect()->to(base_url('/'))->with('error', 'Student record not found.');
        }
        
        // Check if student can view report card
        if (($student['can_view_report_card'] ?? 0) == 0) {
            return redirect()->to(base_url('student/grades'))->with('error', 'Report card access has been disabled by your teacher.');
        }
        
        // Get student with section and teacher information
        $studentModel = new StudentModel();
        $student = $studentModel->select('students.*, sections.section_name, sections.adviser_id, sections.grading_type, CONCAT(teachers.first_name, " ", teachers.last_name) as adviser_name')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->join('teachers', 'teachers.id = sections.adviser_id', 'left')
            ->where('students.id', $student['id'])
            ->first();

        $schoolYear = get_current_school_year();

        // Non-numerical sections are assessed with developmental domains and
        // symbols - render the symbols-based report card instead of the
        // numeric one (which would only show empty terms / 0.00).
        $sectionGradingType = $student['grading_type'] ?? 'numerical';
        if (in_array($sectionGradingType, ['non_numerical', 'custom'], true)) {
            return $this->snedReportCardPdf($student, $schoolYear);
        }

        $gradeModel = new GradeModel();
        $subjectModel = new SubjectModel();
        
        // Get subjects assigned to student's section
        $subjects = $student['section_id'] ? $subjectModel->getSectionSubjects($student['section_id']) : [];
        
        // Get grades for all terms
        $grades = [];
        $termAverages = [];

        for ($term = 1; $term <= 3; $term++) {
            $termGrades = [];
            foreach ($subjects as $subject) {
                $grade = $gradeModel->where('student_id', $student['id'])
                    ->where('subject_id', $subject['id'])
                    ->where('school_year', $schoolYear)
                    ->where('term', $term)
                    ->first();

                $termGrades[$subject['id']] = $grade ? $grade['grade'] : null;
            }
            $grades[$term] = $termGrades;

            $validGrades = array_filter($termGrades, function($g) { return $g !== null; });
            $termAverages[$term] = !empty($validGrades) ? array_sum($validGrades) / count($validGrades) : null;
        }

        $validTerms = array_filter($termAverages, function($avg) { return $avg !== null; });
        $finalAverage = !empty($validTerms) ? array_sum($validTerms) / count($validTerms) : null;

        $data = [
            'student' => $student,
            'subjects' => $subjects,
            'grades' => $grades,
            'termAverages' => $termAverages,
            'finalAverage' => $finalAverage,
            'schoolYear' => $schoolYear,
            'reportDate' => date('F j, Y'),
            'logoBase64' => school_logo_base64(),
        ];
        
        $html = view('student/report_card_pdf', $data);
        
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Times');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'CSCS_Report_Card_' . $student['first_name'] . '_' . $student['last_name'] . '_' . date('Y-m-d') . '.pdf';

        return $this->sendPdfInline($dompdf, $filename);
    }

    /**
     * Learner Development Report PDF — the learner's own copy.
     *
     * Non-numerical sections only: values, conduct and character development
     * on the 2026 three-term layout, never a subject grade or an average.
     */
    public function learnerDevelopmentReport()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        if (!$this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'))->with('error', 'Access denied.');
        }

        $record = $this->getStudentRecord();
        if (!$record) {
            return redirect()->to(base_url('/'))->with('error', 'Student record not found.');
        }

        if (($record['can_view_report_card'] ?? 0) == 0) {
            return redirect()->to(base_url('student/grades'))->with('error', 'Report card access has been disabled by your teacher.');
        }

        try {
            $db = \Config\Database::connect();
            $student = $db->table('students')
                ->select("students.*, sections.section_name, sections.grading_type, CONCAT(teachers.first_name, ' ', teachers.last_name) AS adviser_name")
                ->join('sections', 'sections.id = students.section_id', 'left')
                ->join('teachers', 'teachers.id = sections.adviser_id', 'left')
                ->where('students.id', (int) $record['id'])
                ->get()
                ->getRowArray();

            if (!$student || !in_array($student['grading_type'] ?? 'numerical', ['non_numerical', 'custom'], true)) {
                return redirect()->to(base_url('student/grades'))
                    ->with('error', 'The learner development report is only available for non-numerical sections.');
            }

            $section = [
                'id'           => (int) $student['section_id'],
                'section_name' => (string) ($student['section_name'] ?? ''),
                'grading_type' => (string) $student['grading_type'],
                'grade_level'  => (int) ($student['grade_level'] ?? 0),
            ];

            $schoolYear = get_current_school_year();

            $html = view('teacher/learner_development_report_pdf', [
                'student'    => $student,
                'section'    => $section,
                'schoolYear' => $schoolYear,
                'reportDate' => date('F j, Y'),
                'logoBase64' => school_logo_base64(),
                'report'     => learner_development_report_data($student, $section, $schoolYear),
            ]);

            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'Times');
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', false);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return $this->sendPdfInline($dompdf, learner_development_pdf_filename($student, $section));
        } catch (\Throwable $e) {
            log_message('error', 'Learner development report PDF (student) failed: ' . $e->getMessage());
            return redirect()->to(base_url('student/grades'))
                ->with('error', 'Could not generate the learner development report.');
        }
    }

    /**
     * Symbols-based report card for students in non-numerical sections:
     * developmental domains x quarters, graded with the section's symbols
     * (Proficient / Approaching Proficiency / Developing / Beginning / NO-NA).
     */
    private function snedReportCardPdf(array $student, string $schoolYear)
    {
        $db = \Config\Database::connect();
        $section = $db->table('sections')
            ->where('id', (int) ($student['section_id'] ?? 0))
            ->get()->getRowArray();

        if (!$section) {
            return redirect()->to(base_url('student/grades'))->with('error', 'Section not found.');
        }

        $categoryModel = new \App\Models\SnedCategoryModel();
        $gradeModel = new \App\Models\SnedGradeModel();

        $categories = $categoryModel->getAllCategoriesWithFields((int) $section['id'], (int) ($section['grade_level'] ?? 0));
        $allGrades = $gradeModel->getStudentAllGrades((int) $student['id'], $schoolYear);
        $gradingSymbols = $db->table('section_grading_symbols')
            ->where('section_id', (int) $section['id'])
            ->where('is_active', 1)
            ->orderBy('display_order', 'ASC')
            ->get()
            ->getResultArray();

        $html = view('teacher/sned_report_card_pdf', [
            'student' => $student,
            'section' => $section,
            'categories' => $categories,
            'allGrades' => $allGrades,
            'schoolYear' => $schoolYear,
            'quarters' => sned_quarters(),
            'gradeSymbols' => $gradingSymbols,
            'reportDate' => date('F j, Y'),
            'logoBase64' => school_logo_base64(),
        ]);

        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Times');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $safeName = static function (?string $value): string {
            return preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) $value) ?: 'Report_Card';
        };
        $filename = 'SNED_Report_Card_' . $safeName($section['section_name'] ?? null) . '_'
            . $safeName($student['first_name'] ?? null) . '_' . $safeName($student['last_name'] ?? null) . '.pdf';

        return $this->sendPdfInline($dompdf, $filename);
    }

}
