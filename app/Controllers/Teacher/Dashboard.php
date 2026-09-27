<?php

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Models\GradeModel;
use App\Models\StudentModel;
use App\Models\SubjectModel;
use App\Models\AttendanceModel;
use App\Models\SectionModel;

class Dashboard extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    /**
     * Verify that a teacher has a legitimate relationship with a student.
     * Teacher must be either the section adviser OR have a teaching schedule for the student's section.
     */
    private function verifyTeacherStudentRelationship(int $teacherRecordId, int $studentId): bool
    {
        $db = \Config\Database::connect();
        helper('school_year');
        $schoolYear = get_current_school_year();
        
        // Get student's section
        $student = $db->table('students')
            ->select('section_id')
            ->where('id', $studentId)
            ->where('enrollment_status', 'enrolled')
            ->get()
            ->getRow();
            
        if (!$student || !$student->section_id) {
            return false;
        }
        
        $sectionId = (int) $student->section_id;
        
        // Check 1: Is teacher the section adviser?
        $isAdviser = $db->table('sections')
            ->where('id', $sectionId)
            ->where('adviser_id', $teacherRecordId)
            ->countAllResults();
        
        if ($isAdviser > 0) {
            return true;
        }
        
        // Check 2: Does teacher have a teaching schedule for this section?
        $hasSchedule = $db->table('teacher_schedules')
            ->where('teacher_id', $teacherRecordId)
            ->where('section_id', $sectionId)
            ->where('school_year', $schoolYear)
            ->countAllResults();
        
        return $hasSchedule > 0;
    }

    public function index()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        helper('asset');

        $teacherId = $this->auth->id();
        $gradeModel = new GradeModel();
        $studentModel = new StudentModel();
        $subjectModel = new SubjectModel();

        // Get teacher's basic info
        $teacherModel = new \App\Models\TeacherModel();
        $teacher = $teacherModel->where('user_id', $teacherId)->first();

        $db = \Config\Database::connect();

        // Initialize default values
        $myStudents = [];
        $mySubjects = [];
        $recentGrades = [];
        $classAverages = [];
        $gradeDistribution = ['excellent' => 0, 'very_good' => 0, 'good' => 0, 'fair' => 0, 'failing' => 0];
        $quarterPerformance = [0, 0, 0, 0];

        // Determine if teacher has any SNED sections (grade_level = 7)
        $hasSnedSection = false;
        if ($teacher) {
            $hasSnedSection = $db->table('sections')
                ->where('adviser_id', $teacher['id'])
                ->where('grade_level', 7)
                ->where('is_active', 1)
                ->countAllResults() > 0;
        }

        if ($teacher) {
            // Get students from sections where this teacher is adviser
            $myStudents = $studentModel->select('students.id, students.first_name, students.last_name, students.grade_level, sections.section_name')
                ->join('sections', 'sections.id = students.section_id', 'left')
                ->where('sections.adviser_id', $teacher['id'])
                ->where('students.enrollment_status', 'enrolled')
                ->limit(10)
                ->findAll();
            
            // If no students from advised sections, get students from grades table
            if (empty($myStudents)) {
                $myStudents = $gradeModel->db->query("
                    SELECT DISTINCT s.id, s.first_name, s.last_name, s.grade_level, sec.section_name
                    FROM grades g
                    JOIN students s ON s.id = g.student_id
                    LEFT JOIN sections sec ON sec.id = s.section_id
                    WHERE g.teacher_id = ? AND g.school_year = ?
                    LIMIT 10
                ", [$teacher['id'], get_current_school_year()])->getResultArray();
            }

            // Get subjects taught by this teacher
            $mySubjects = $gradeModel->db->query("
                SELECT DISTINCT sub.id, sub.subject_name, sub.subject_code, sub.grade_level
                FROM grades g
                JOIN subjects sub ON sub.id = g.subject_id
                WHERE g.teacher_id = ? AND g.school_year = ?
            ", [$teacher['id'], get_current_school_year()])->getResultArray();

            // Get recent grades entered by this teacher
            $recentGrades = $gradeModel->db->query("
                SELECT g.*, s.first_name, s.last_name, sub.subject_name, g.created_at
                FROM grades g
                JOIN students s ON s.id = g.student_id
                JOIN subjects sub ON sub.id = g.subject_id
                WHERE g.teacher_id = ?
                ORDER BY g.created_at DESC
                LIMIT 5
            ", [$teacher['id']])->getResultArray();

            // Calculate class averages by subject
            foreach ($mySubjects as $subject) {
                $average = $gradeModel->db->query("
                    SELECT AVG(grade) as avg_grade
                    FROM grades
                    WHERE teacher_id = ? AND subject_id = ? AND school_year = ? AND grade IS NOT NULL
                ", [$teacher['id'], $subject['id'], get_current_school_year()])->getRowArray();

                $classAverages[] = [
                    'subject' => $subject['subject_name'],
                    'average' => $average['avg_grade'] ? round($average['avg_grade'], 2) : 0
                ];
            }

            // Get grade distribution
            $grades = $gradeModel->db->query("
                SELECT grade
                FROM grades
                WHERE teacher_id = ? AND school_year = ? AND grade IS NOT NULL
            ", [$teacher['id'], get_current_school_year()])->getResultArray();

            foreach ($grades as $grade) {
                $gradeValue = $grade['grade'];
                if ($gradeValue >= 90) {
                    $gradeDistribution['excellent']++;
                } elseif ($gradeValue >= 85) {
                    $gradeDistribution['very_good']++;
                } elseif ($gradeValue >= 80) {
                    $gradeDistribution['good']++;
                } elseif ($gradeValue >= 75) {
                    $gradeDistribution['fair']++;
                } else {
                    $gradeDistribution['failing']++;
                }
            }

            // Get quarter performance data
            for ($quarter = 1; $quarter <= 4; $quarter++) {
                $avg = $gradeModel->db->query("
                    SELECT AVG(grade) as avg_grade
                    FROM grades
                    WHERE teacher_id = ? AND school_year = ? AND term = ? AND grade IS NOT NULL
                ", [$teacher['id'], get_current_school_year(), $quarter])->getRowArray();

                $quarterPerformance[$quarter - 1] = $avg['avg_grade'] ? round($avg['avg_grade'], 2) : 0;
            }
        }

        // Featured poster for the teacher dashboard side column
        $teacherPoster = featured_dashboard_poster('teacher');

        return view('teacher/dashboard', [
            'title' => 'Teacher Dashboard - CSCS Tap n Track',
            'teacher' => $teacher,
            'featuredPosterTeacher'    => $teacherPoster['path'],
            'featuredPosterTeacherUrl' => $teacherPoster['url'],
            'myStudents' => $myStudents,
            'mySubjects' => $mySubjects,
            'recentGrades' => $recentGrades,
            'classAverages' => $classAverages,
            'gradeDistribution' => $gradeDistribution,
            'quarterPerformance' => $quarterPerformance,
            'totalStudents' => count($myStudents),
            'totalSubjects' => count($mySubjects),
            'currentQuarter' => $this->getCurrentQuarter(),
            'currentTerm'    => $this->getCurrentQuarter(),
            'hasSnedSection' => $hasSnedSection
        ]);
    }

    /**
     * Get current quarter from system settings
     */
    private function getCurrentQuarter()
    {
        // The administrator-configured term in system_settings is the single
        // source of truth (same decision Student\Dashboard::getCurrentTerm()
        // and the get_current_term() helper make).
        //
        // This used to probe method_exists($model, 'getCurrentQuarter'), but
        // SystemSettingModel only exposes getCurrentTerm(), so the probe never
        // matched and the page silently fell back to a month-of-year guess -
        // which is why changing the term in Admin > Settings never reached the
        // teacher's Enter Grades page or dashboard.
        try {
            $term = (new \App\Models\SystemSettingModel())->getCurrentTerm();

            if ($term >= 1 && $term <= 3) {
                return $term;
            }
        } catch (\Throwable $e) {
            // Database unavailable - fall through to the shared helper.
        }

        // Same fallback semantics as get_current_school_year(): the school
        // year helper is already used throughout this controller.
        helper('school_year');

        return get_current_term();
    }

    public function grades()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $studentModel = new StudentModel();
        $subjectModel = new SubjectModel();
        $gradeModel = new GradeModel();
        $db = \Config\Database::connect();
        
        // Get teacher record for logged-in user
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        
        $students = [];
        $subjects = [];
        $studentGrades = [];
        $totalPages = 1;
        $currentPage = 1;
        
        if ($teacher) {
            // Get pagination parameters
            $currentPage = (int) ($this->request->getGet('page') ?? 1);
            $perPage = 15;
            $offset = ($currentPage - 1) * $perPage;
            
            // Get total count for pagination
            $totalStudents = $studentModel->select('students.id')
                ->join('sections', 'sections.id = students.section_id', 'left')
                ->where('sections.adviser_id', $teacher['id'])
                ->where('students.enrollment_status', 'enrolled')
                ->countAllResults();
            
            $totalPages = ceil($totalStudents / $perPage);
            
            // Get students from advised sections with pagination
            $students = $studentModel->select('students.id, students.lrn, students.first_name, students.last_name, students.gender, students.grade_level, students.enrollment_status, sections.section_name, sections.grading_type')
                ->join('sections', 'sections.id = students.section_id', 'left')
                ->where('sections.adviser_id', $teacher['id'])
                ->where('students.enrollment_status', 'enrolled')
                ->orderBy('students.last_name', 'ASC')
                ->limit($perPage, $offset)
                ->findAll();
            
            // Get subjects for the grade levels of advised students
            if (!empty($students)) {
                $gradeLevels = array_unique(array_column($students, 'grade_level'));
                $subjects = $subjectModel->whereIn('grade_level', $gradeLevels)
                    ->orderBy('grade_level', 'ASC')
                    ->orderBy('subject_name', 'ASC')
                    ->findAll();
                
            // Get existing grades for current term
                $currentTerm = $this->getCurrentQuarter();
                foreach ($students as $student) {
                    foreach ($subjects as $subject) {
                        try {
                            $grade = $gradeModel->where('student_id', $student['id'])
                                ->where('subject_id', $subject['id'])
                                ->where('teacher_id', $teacher['id'])
                                ->where('term', $currentTerm)
                                ->where('school_year', get_current_school_year())
                                ->first();
                        } catch (\Throwable $e) {
                            $grade = null;
                        }
                        
                        $studentGrades[$student['id']][$subject['id']] = $grade;
                    }
                }
            }
        }
        
        // Get grading type for the advisory section
        $sectionGradingType = 'numerical';
        $gradingSymbols = [];
        $advisorySectionId = null;
        $advisorySectionGrade = 0;
        if ($teacher) {
            $advisorySection = $db->table('sections')
                ->select('id, grading_type, section_name, grade_level')
                ->where('adviser_id', $teacher['id'])
                ->where('is_active', 1)
                ->get()
                ->getRowArray();
            
            log_message('info', "SECTION DEBUG - Advisory Section: " . json_encode($advisorySection));
            
            if ($advisorySection) {
                $advisorySectionId = $advisorySection['id'];
                $advisorySectionGrade = (int) ($advisorySection['grade_level'] ?? 0);
                $sectionGradingType = $advisorySection['grading_type'] ?? 'numerical';
                
                // Get grading symbols for non-numerical sections
                if (in_array($sectionGradingType, ['non_numerical', 'custom'], true) && $advisorySectionId) {
                    $gradingSymbols = $db->table('section_grading_symbols')
                        ->where('section_id', $advisorySectionId)
                        ->where('is_active', 1)
                        ->orderBy('display_order', 'ASC')
                        ->get()
                        ->getResultArray();
                    
                    log_message('info', 'Grading symbols from DB for section ' . $advisorySectionId . ': ' . json_encode($gradingSymbols));
                    
                    // Fallback to default symbols if none found
                    if (empty($gradingSymbols)) {
                        log_message('warning', 'No grading symbols found in DB for section ' . $advisorySectionId . ', using fallback');
                        $gradingSymbols = [
                            ['symbol' => 'P', 'label' => 'Proficient', 'description' => 'The student consistently demonstrates the skill independently.', 'display_order' => 1],
                            ['symbol' => 'AP', 'label' => 'Approaching Proficiency', 'description' => 'The student is developing the skill with minimal assistance.', 'display_order' => 2],
                            ['symbol' => 'D', 'label' => 'Developing', 'description' => 'The student is beginning to develop the skill with guidance.', 'display_order' => 3],
                            ['symbol' => 'B', 'label' => 'Beginning', 'description' => 'The student needs significant support to develop the skill.', 'display_order' => 4],
                            ['symbol' => 'NO/NA', 'label' => 'Not Observed / Not Applicable', 'description' => 'The skill has not been observed or is not applicable at this time.', 'display_order' => 5],
                        ];
                    }
                    
                    log_message('info', 'Final grading symbols to pass to view: ' . json_encode($gradingSymbols));
                } else {
                    log_message('info', "NOT loading grading symbols - gradingType: {$sectionGradingType}, sectionId: " . ($advisorySectionId ?? 'null'));
                }
            }
        }
        
        // A non-numerical section is graded with developmental domains and
        // symbols — the numerical subject grid must never appear for it
        // (a symbol typed into a numeric field gets stored as 0.00). The
        // domain grid is rendered INLINE on this page; no portal redirect.
        $isNonNumericalSection = in_array($sectionGradingType, ['non_numerical', 'custom'], true);
        if ($isNonNumericalSection) {
            $subjects = [];
            $studentGrades = [];
        }

        // Developmental-domain grading data for the inline grid.
        $snedSection        = null;
        $snedDomains        = [];
        $snedQuarters       = sned_quarters();
        $snedGradesByQuarter = [];
        $snedActiveQuarter  = 1;
        $snedSchoolYear     = get_current_school_year();
        if ($isNonNumericalSection && $advisorySectionId !== null && !empty($students)) {
            try {
                $snedSection = $db->table('sections')
                    ->select('id, section_name, grade_level, grading_type')
                    ->where('id', $advisorySectionId)
                    ->get()
                    ->getRowArray();

                $categoryModel = new \App\Models\SnedCategoryModel();
                $snedDomains = $categoryModel->getAllCategoriesWithFields(
                    (int) $advisorySectionId,
                    $advisorySectionGrade
                );

                $snedGradeModel = new \App\Models\SnedGradeModel();
                $studentIds = array_map('intval', array_column($students, 'id'));
                foreach ($snedQuarters as $q) {
                    $snedGradesByQuarter[$q] = $snedGradeModel->getStudentsWithGrades($studentIds, $snedSchoolYear, $q);
                }

                $snedActiveQuarter = min(max((int) ($this->getCurrentQuarter() ?: 1), 1), 4);
            } catch (\Throwable $e) {
                log_message('error', 'Enter Grades: failed to load developmental-domain data for section ' . $advisorySectionId . ': ' . $e->getMessage());
                $snedDomains = [];
                $snedGradesByQuarter = [];
            }
        }

        return view('teacher/grades', [
            'title' => 'Enter Grades - CSCS Tap n Track',
            'students' => $students,
            'subjects' => $subjects,
            'teacher' => $teacher,
            'studentGrades' => $studentGrades,
            'subjectSections' => [],
            'currentQuarter' => $this->getCurrentQuarter(),
            'gradingEnabled' => true,
            'currentTerm' => $this->getCurrentQuarter(),
            'isAdvisory' => true,
            'isNonNumericalSection' => $isNonNumericalSection,
            'sectionGradingType' => $sectionGradingType,
            'advisoryData' => [
                'students' => $students,
                'studentGrades' => $studentGrades
            ],
            'subjectSectionsData' => [],
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'sectionGradingType' => $sectionGradingType,
            'gradingSymbols' => $gradingSymbols,
            'snedSection' => $snedSection,
            'snedDomains' => $snedDomains,
            'snedQuarters' => $snedQuarters,
            'snedGradesByQuarter' => $snedGradesByQuarter,
            'snedActiveQuarter' => $snedActiveQuarter,
            'snedSchoolYear' => $snedSchoolYear,
        ]);
    }

    public function saveGrades()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        
        $rules = [
            'student_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'quarter' => 'required|integer|greater_than[0]|less_than[5]',
            // The 60 report-card floor is not validated away here: a posted 44
            // or 59 is raised with clamp_report_card_grade() below and stored.
            'grade' => 'required|decimal|less_than_equal_to[100]',
            'remarks' => 'permit_empty|max_length[255]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Please check your input and try again.');
        }

        // Get teacher record
        $teacherModel = new \App\Models\TeacherModel();
        $teacher = $teacherModel->where('user_id', $this->auth->id())->first();
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }

        $studentId = (int) $this->request->getPost('student_id');
        
        // SECURITY: Verify teacher-student relationship before allowing grade save
        if (!$this->verifyTeacherStudentRelationship((int) $teacher['id'], $studentId)) {
            return redirect()->back()->with('error', 'You are not authorized to save grades for this student.');
        }

        // Report card floor: a posted 44 or 59 is stored as 60.
        $grade = clamp_report_card_grade($this->request->getPost('grade'));

        if ($grade === null) {
            return redirect()->back()->withInput()->with('error', 'Please enter a grade between ' . min_report_card_grade() . ' and ' . max_report_card_grade() . '.');
        }

        $data = [
            'student_id' => $studentId,
            'subject_id' => (int) $this->request->getPost('subject_id'),
            'teacher_id' => (int) $teacher['id'],
            'school_year' => get_current_school_year(),
            'quarter' => (int) $this->request->getPost('quarter'),
            'grade' => $grade,
            'remarks' => $this->request->getPost('remarks')
        ];

        $gradeModel = new GradeModel();
        $saved = $gradeModel->upsertGrade($data);

        if (!$saved) {
            return redirect()->back()->withInput()->with('error', 'Failed to save grade.');
        }

        return redirect()->back()->with('success', 'Grade saved successfully!');
    }

    public function saveBulkGrades()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        
        $teacherModel = new \App\Models\TeacherModel();
        $teacher = $teacherModel->where('user_id', $this->auth->id())->first();
        
        log_message('info', 'saveBulkGrades called by teacher ID: ' . ($teacher ? $teacher['id'] : 'NOT FOUND - checking user auth'));
        
        if (!$teacher) {
            log_message('error', 'Teacher record not found for user ID: ' . $this->auth->id());
            return redirect()->back()->with('error', 'Teacher record not found.');
        }

        // Log all POST data for debugging
        log_message('info', 'ALL POST DATA: ' . json_encode($_POST));
        log_message('info', 'ALL RAW POST DATA: ' . file_get_contents('php://input'));
        
        $grades = $this->request->getPost('grades');
        $term = $this->request->getPost('term');  
        $gradingType = $this->request->getPost('grading_type') ?? 'numerical';
        
        log_message('info', 'saveBulkGrades called with grades: ' . json_encode($grades) . ', term: ' . $term . ', gradingType: ' . $gradingType);
        log_message('info', 'Grades type: ' . gettype($grades) . ', Is array: ' . (is_array($grades) ? 'yes' : 'no'));
        
        if (!$grades || !is_array($grades)) {
            log_message('error', 'Invalid grade data - grades missing or empty. Posted data: ' . json_encode($_POST));
            return redirect()->back()->with('error', 'Invalid grade data - no grades submitted.');
        }
        
        if (!$term) {
            log_message('error', 'Invalid grade data - term missing. Posted data: ' . json_encode($_POST));
            return redirect()->back()->with('error', 'Invalid grade data - term missing.');
        }

        // The active term in system_settings is the single source of truth -
        // the same value the Enter Grades page displays. Trusting the posted
        // term here let a stale page (opened before the administrator switched
        // the term) silently file new grades under the old term.
        $term = $this->getCurrentQuarter();

        if ($term < 1 || $term > 4) {
            return redirect()->back()->with('error', 'Invalid term.');
        }

        $gradeModel = new GradeModel();
        $gradeModel->skipValidation(true);
        $savedCount = 0;
        $skippedCount = 0;
        $processedGrades = [];
        
        foreach ($grades as $studentId => $subjects) {
            log_message('info', "Processing student {$studentId}, subjects: " . json_encode($subjects));
            if (!is_array($subjects)) {
                log_message('warning', "Subjects is not an array for student {$studentId}: " . gettype($subjects));
                continue;
            }
            
            foreach ($subjects as $subjectId => $gradeValue) {
                log_message('info', "Processing subject {$subjectId}, value: '{$gradeValue}', empty: " . (empty($gradeValue) ? 'yes' : 'no'));
                
                // Skip empty values
                if ($gradeValue === '' || $gradeValue === null || $gradeValue === false) {
                    log_message('info', "Skipping empty value for student {$studentId}, subject {$subjectId}");
                    continue;
                }
                
                $processedGrades[] = ['student_id' => $studentId, 'subject_id' => $subjectId, 'value' => $gradeValue];
                
                $data = [
                    'student_id' => (int) $studentId,
                    'subject_id' => (int) $subjectId,
                    'teacher_id' => (int) $teacher['id'],
                    'school_year' => get_current_school_year(),
                    'term' => $term,
                    'remarks' => null
                ];
                
                // For non-numerical grading, store the symbol as-is
                // For numerical grading, validate and convert to float
                if ($gradingType === 'non_numerical') {
                    // Validate that the symbol is one of the allowed values
                    $allowedSymbols = ['P', 'AP', 'D', 'B', 'NO/NA'];
                    if (!in_array($gradeValue, $allowedSymbols)) {
                        log_message('warning', "Invalid symbol received: {$gradeValue}");
                        $skippedCount++;
                        continue;
                    }
                    $data['grade'] = $gradeValue;
                } else {
                    // Numerical grading - validate numeric value
                    if (!is_numeric($gradeValue)) {
                        log_message('warning', "Non-numeric grade in numerical mode: {$gradeValue}");
                        $skippedCount++;
                        continue;
                    }
                    $gradeFloat = (float) $gradeValue;
                    if ($gradeFloat > 100) {
                        log_message('warning', "Grade out of range: {$gradeFloat}");
                        $skippedCount++;
                        continue;
                    }
                    // Report card floor: a 44 or 59 typed into the entry page is
                    // stored as 60 instead of being saved off-scale. The model
                    // applies the same floor as a last gate.
                    $flooredGrade = clamp_report_card_grade($gradeFloat);
                    if ($flooredGrade !== $gradeFloat) {
                        log_message('info', "Grade {$gradeFloat} raised to {$flooredGrade} (report card floor).");
                    }
                    $data['grade'] = $flooredGrade;
                }
                
                if ($gradeModel->upsertGrade($data)) {
                    $savedCount++;
                } else {
                    $skippedCount++;
                }
            }
        }
        
        log_message('info', 'Final counts - Saved: ' . $savedCount . ', Skipped: ' . $skippedCount . ', Processed: ' . count($processedGrades));
        log_message('info', 'Processed grades details: ' . json_encode($processedGrades));
        
        if ($savedCount > 0) {
            audit_event('grade.saved', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'grade',
                'description'   => $savedCount . ' grade entr' . ($savedCount === 1 ? 'y' : 'ies') . ' saved by a teacher',
                'metadata'      => [
                    'teacher_id' => (int) ($teacher['id'] ?? 0),
                    'saved'      => (int) $savedCount,
                    'skipped'    => (int) $skippedCount,
                    'term'       => (int) ($this->request->getPost('quarter') ?? 0) ?: null,
                ],
            ]);

            return redirect()->back()->with('success', "Successfully saved {$savedCount} grade(s).");
        } else {
            $debugInfo = [
                'total_processed' => count($processedGrades),
                'saved' => $savedCount,
                'skipped' => $skippedCount,
                'term' => $term,
                'grading_type' => $gradingType,
                'first_few_grades' => array_slice($processedGrades, 0, 3),
                'all_post_grades' => is_array($grades) ? array_slice($grades, 0, 5) : 'not_array'
            ];
            return redirect()->back()->with('error', 'No grades saved. Details: ' . json_encode($debugInfo));
        }
    }

    public function students()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $studentModel = new StudentModel();
        $db = \Config\Database::connect();

        $teacher = $teacherModel->where('user_id', $teacherId)->first();

        $advisoryStudents = [];
        $advisorySection = null;
        $subjectSections = [];

        if ($teacher) {
            $advisorySection = $db->table('sections')
                ->select('id, section_name, grade_level')
                ->where('adviser_id', $teacher['id'])
                ->get()->getRowArray();

            if ($advisorySection) {
                $advisoryStudents = $studentModel->select('students.id, students.lrn, students.first_name, students.last_name, students.gender, students.grade_level, students.enrollment_status, students.can_view_report_card, sections.section_name, sections.grading_type')
                    ->join('sections', 'sections.id = students.section_id', 'left')
                    ->where('students.section_id', $advisorySection['id'])
                    ->where('students.enrollment_status', 'enrolled')
                    ->orderBy('students.last_name', 'ASC')
                    ->findAll();
            }

            $teachingSections = $db->query(
                "SELECT DISTINCT s.id, s.section_name, s.grade_level, sub.subject_name
                 FROM teacher_schedules ts
                 JOIN sections s ON s.id = ts.section_id
                 JOIN subjects sub ON sub.id = ts.subject_id
                 WHERE ts.teacher_id = ? AND (s.adviser_id != ? OR s.adviser_id IS NULL)
                 ORDER BY s.grade_level, s.section_name",
                [$teacher['id'], $teacher['id']]
            )->getResultArray();

            $sectionGroups = [];
            foreach ($teachingSections as $ts) {
                $sectionKey = $ts['id'];
                if (!isset($sectionGroups[$sectionKey])) {
                    $sectionGroups[$sectionKey] = [
                        'id' => $ts['id'],
                        'section_name' => $ts['section_name'],
                        'grade_level' => $ts['grade_level'],
                        'subjects' => []
                    ];
                }
                $sectionGroups[$sectionKey]['subjects'][] = $ts['subject_name'];
            }

            foreach ($sectionGroups as $section) {
                $sectionStudents = $studentModel->select('students.id, students.lrn, students.first_name, students.last_name, students.gender, students.grade_level, students.enrollment_status, students.can_view_report_card, sections.section_name, sections.grading_type')
                    ->join('sections', 'sections.id = students.section_id', 'left')
                    ->where('students.section_id', $section['id'])
                    ->where('students.enrollment_status', 'enrolled')
                    ->orderBy('students.last_name', 'ASC')
                    ->findAll();

                $subjectSections[] = [
                    'section' => $section,
                    'students' => $sectionStudents
                ];
            }
        }

        return view('teacher/students', [
            'title' => 'My Students - CSCS Tap n Track',
            'advisoryStudents' => $advisoryStudents,
            'advisorySection' => $advisorySection,
            'subjectSections' => $subjectSections,
            'teacher' => $teacher
        ]);
    }

    public function schedule()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        
        $payload = $this->mySchedulePayload();
        $teacher = $payload['teacher'];

        $db = \Config\Database::connect();
        $sections = $db->table('sections')->select('id, section_name, grade_level')->orderBy('grade_level', 'ASC')->orderBy('section_name', 'ASC')->get()->getResultArray();
        $subjects = [];
        if ($teacher) {
            $subjects = $db->query('SELECT DISTINCT sub.id, sub.subject_name FROM subjects sub JOIN teacher_schedules ts ON ts.subject_id = sub.id WHERE ts.teacher_id = ?', [$teacher['id']])->getResultArray();
        }

        return view('teacher/schedule_view', [
            'title' => 'My Schedule - CSCS Tap n Track',
            'teacher' => $teacher,
            'schedules' => $payload['schedules'],
            'subjects' => $subjects,
            'sections' => $sections
        ]);
    }

    /**
     * PDF twin of My Schedule (teacher/schedule-pdf).
     *
     * Renders the very same week through reports/_schedule_grid, so the
     * download can never disagree with the page it was taken from.
     */
    public function schedulePdf()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        helper('school_year');

        $payload = $this->mySchedulePayload();

        $html = view('teacher/schedule_pdf', [
            'teacher'    => $payload['teacher'],
            'schedules'  => $payload['schedules'],
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

        $lastName = (string) ($payload['teacher']['last_name'] ?? '');
        $suffix   = $lastName === ''
            ? ''
            : '_' . preg_replace('/[^A-Za-z0-9]+/', '_', $lastName);

        return $this->sendPdfInline($dompdf, 'CSCS_Schedule' . $suffix . '_' . date('Y-m-d') . '.pdf');
    }

    /**
     * The signed-in teacher's record plus the week shown on My Schedule: their
     * own blocks AND the timetable of every section they advise, shaped for the
     * weekly grid.
     *
     * Shared by schedule() and schedulePdf() so the page and its export always
     * read the same data.
     *
     * @return array{teacher: array<string, mixed>|null, schedules: array<string, array<string, array<string, mixed>>>}
     */
    private function mySchedulePayload(): array
    {
        $teacherModel = new \App\Models\TeacherModel();
        $teacher      = $teacherModel->where('user_id', $this->auth->id())->first();

        $schedules = [];

        if ($teacher) {
            // The adviser of a section has to see that section's timetable even
            // though the blocks are assigned to the teachers who teach them,
            // otherwise My Schedule reads "No schedule set" for an adviser
            // whose class is fully scheduled.
            $schedules = model(\App\Models\TeacherScheduleModel::class)
                ->getTeacherSchedule($teacher['id'], null, true);
        }

        return [
            'teacher'   => $teacher,
            'schedules' => $this->groupScheduleForView($schedules),
        ];
    }

    public function manageSchedule()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $scheduleModel = new \App\Models\TeacherScheduleModel();
        
        // Get teacher record
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        $schedules = [];
        
        if ($teacher) {
            $schedules = $scheduleModel->getTeacherSchedule($teacher['id']);
        }
        
        $db = \Config\Database::connect();
        $sections = $db->table('sections')->select('id, section_name, grade_level')->orderBy('grade_level', 'ASC')->orderBy('section_name', 'ASC')->get()->getResultArray();
        $subjects = [];
        if ($teacher) {
            $subjects = $db->query('SELECT DISTINCT sub.id, sub.subject_name FROM subjects sub JOIN teacher_schedules ts ON ts.subject_id = sub.id WHERE ts.teacher_id = ?', [$teacher['id']])->getResultArray();
        }

        return view('teacher/schedule', [
            'title' => 'Manage Schedule - CSCS Tap n Track',
            'teacher' => $teacher,
            'schedules' => $this->groupScheduleForView($schedules ?? []),
            'subjects' => $subjects,
            'sections' => $sections
        ]);
    }

    /**
     * Reshape flat teacher_schedules rows into the $schedules[day][timeSlot]
     * map the schedule views expect. Passing the raw flat list left the views
     * iterating row keys ('teacher_id', 'subject_id', ...) as if they were
     * 'HH:MM-HH:MM' slots, which threw PHP warnings that CI escalates to
     * exceptions — the "Whoops!" 500 on the live server.
     *
     * Unscheduled assignment-only rows (00:00:00-00:00:00, day 'TBD') carry no
     * timetable slot, so they are skipped.
     */
    private function groupScheduleForView(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $day = strtolower((string) ($row['day_of_week'] ?? ''));
            if ($day === '' || $day === 'tbd') {
                continue;
            }
            $start = (string) ($row['start_time'] ?? '');
            $end   = (string) ($row['end_time'] ?? '');
            if ($start === '' || $end === '' || $start === $end) {
                continue;
            }
            $grouped[$day][$start . '-' . $end] = $row;
        }
        return $grouped;
    }

    public function saveSchedule()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }

        $scheduleData = $this->request->getPost('schedule');
        $scheduleModel = new \App\Models\TeacherScheduleModel();
        
        // Delete existing schedules
        $db = \Config\Database::connect();
        $db->table('teacher_schedules')->where('teacher_id', $teacher['id'])->delete();
        
        $saved = 0;
        if (!empty($scheduleData)) {
            foreach ($scheduleData as $day => $slots) {
                foreach ($slots as $timeSlot => $data) {
                    if (!empty($data['subject_id']) && !empty($data['section_id'])) {
                        $scheduleModel->insert([
                            'teacher_id' => $teacher['id'],
                            'section_id' => (int) $data['section_id'],
                            'subject_id' => (int) $data['subject_id'],
                            'day' => $day,
                            'time_slot' => $timeSlot,
                            'room' => $data['room'] ?? ''
                        ]);
                        $saved++;
                    }
                }
            }
        }
        
        return redirect()->to(base_url('teacher/schedule'))->with('success', "Schedule saved! {$saved} entries added.");
    }

    public function attendance()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $studentModel = new StudentModel();
        $attendanceModel = new AttendanceModel();
        
        // Get teacher record for logged-in user
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        $students = [];
        $attendanceData = [];
        $selectedDate = $this->request->getGet('date') ?? date('Y-m-d');
        
        if ($teacher) {
            // Get students from advised sections
            $students = $studentModel->select('students.id, students.lrn, students.first_name, students.last_name, students.grade_level, sections.section_name')
                ->join('sections', 'sections.id = students.section_id', 'left')
                ->where('sections.adviser_id', $teacher['id'])
                ->where('students.enrollment_status', 'enrolled')
                ->orderBy('students.last_name', 'ASC')
                ->findAll();
            
            // Get attendance for selected date
            $attendanceRecords = $attendanceModel->getAttendanceByDate($teacher['id'], $selectedDate);
            foreach ($attendanceRecords as $record) {
                $attendanceData[$record['student_id']] = $record;
            }
        }
        
        return view('teacher/attendance', [
            'title' => 'Student Attendance - CSCS Tap n Track',
            'students' => $students,
            'teacher' => $teacher,
            'attendanceData' => $attendanceData,
            'selectedDate' => $selectedDate
        ]);
    }

    public function saveAttendance()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $attendanceModel = new AttendanceModel();
        
        // Get teacher record for logged-in user
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        $date = $this->request->getPost('date');
        $attendanceData = $this->request->getPost('attendance');
        
        if (!$date || !$attendanceData) {
            return redirect()->back()->with('error', 'Invalid attendance data.');
        }
        
        $saved = 0;
        foreach ($attendanceData as $studentId => $status) {
            $studentIdInt = (int) $studentId;
            
            // SECURITY: Verify teacher-student relationship before recording attendance
            if (!$this->verifyTeacherStudentRelationship((int) $teacher['id'], $studentIdInt)) {
                continue; // Skip students the teacher doesn't have a relationship with
            }
            
            $data = [
                'student_id' => $studentIdInt,
                'teacher_id' => (int) $teacher['id'],
                'date' => $date,
                'status' => $status,
                'remarks' => $this->request->getPost('remarks')[$studentId] ?? null
            ];
            
            if ($attendanceModel->markAttendance($data)) {
                $saved++;
            }
        }
        
        return redirect()->back()->with('success', "Attendance saved for {$saved} students.");
    }

    public function attendanceHistory()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $attendanceModel = new AttendanceModel();
        
        // Get teacher record
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        
        if (!$teacher) {
            return $this->response->setJSON(['success' => false, 'error' => 'Teacher record not found']);
        }
        
        $fromDate = $this->request->getGet('from');
        $toDate = $this->request->getGet('to');
        
        if (!$fromDate || !$toDate) {
            return $this->response->setJSON(['success' => false, 'error' => 'From and to dates are required']);
        }
        
        // Get attendance history
        $db = \Config\Database::connect();
        $history = $db->query("
            SELECT a.date, a.status, a.remarks, 
                   CONCAT(s.first_name, ' ', s.last_name) as student_name,
                   s.lrn
            FROM attendance a
            JOIN students s ON s.id = a.student_id
            WHERE a.teacher_id = ? AND a.date BETWEEN ? AND ?
            ORDER BY a.date DESC, s.last_name ASC
        ", [$teacher['id'], $fromDate, $toDate])->getResultArray();
        
        return $this->response->setJSON([
            'success' => true,
            'history' => $history
        ]);
    }

    public function attendanceHistoryPage()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $studentModel = new StudentModel();
        
        // Get teacher record
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        $students = [];
        
        if ($teacher) {
            // Get students from advised sections
            $students = $studentModel->select('students.id, students.lrn, students.first_name, students.last_name, students.grade_level, sections.section_name')
                ->join('sections', 'sections.id = students.section_id', 'left')
                ->where('sections.adviser_id', $teacher['id'])
                ->where('students.enrollment_status', 'enrolled')
                ->orderBy('students.last_name', 'ASC')
                ->findAll();
        }
        
        return view('teacher/attendance_history', [
            'title' => 'Attendance History - CSCS Tap n Track',
            'students' => $students,
            'teacher' => $teacher
        ]);
    }

    public function sections()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $sectionModel = new \App\Models\SectionModel();
        
        // Get teacher record
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        $sections = [];
        
        if ($teacher) {
            // Get sections where this teacher is adviser
            $sections = $sectionModel->select('sections.*, COUNT(students.id) as current_enrollment')
                ->join('students', 'students.section_id = sections.id AND students.enrollment_status = "enrolled"', 'left')
                ->where('sections.adviser_id', $teacher['id'])
                ->groupBy('sections.id')
                ->orderBy('sections.grade_level', 'ASC')
                ->orderBy('sections.section_name', 'ASC')
                ->findAll();
        }
        
        return view('teacher/sections', [
            'title' => 'My Sections - CSCS Tap n Track',
            'sections' => $sections,
            'teacher' => $teacher
        ]);
    }

    public function getSectionStudents($sectionId)
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $sectionModel = new \App\Models\SectionModel();
        $studentModel = new StudentModel();
        
        // Get teacher record
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        
        if (!$teacher) {
            return $this->response->setJSON(['success' => false, 'error' => 'Teacher record not found']);
        }
        
        // Verify teacher is adviser of this section
        $section = $sectionModel->where('id', $sectionId)
            ->where('adviser_id', $teacher['id'])
            ->first();
        
        if (!$section) {
            return $this->response->setJSON(['success' => false, 'error' => 'Section not found or not assigned to you']);
        }
        
        // Get students in this section
        $students = $studentModel->select('students.id, students.lrn, students.first_name, students.last_name, students.enrollment_status, students.created_at')
            ->where('students.section_id', $sectionId)
            ->where('students.enrollment_status', 'enrolled')
            ->orderBy('students.last_name', 'ASC')
            ->findAll();
        
        return $this->response->setJSON([
            'success' => true,
            'students' => $students
        ]);
    }

    public function getUnassignedStudents($gradeLevel)
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $studentModel = new StudentModel();
        
        // Get teacher record
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        
        if (!$teacher) {
            return $this->response->setJSON(['success' => false, 'error' => 'Teacher record not found']);
        }
        
        // Get unassigned students for this grade level
        $students = $studentModel->select('students.id, students.lrn, students.first_name, students.last_name')
            ->where('students.grade_level', $gradeLevel)
            ->where('students.enrollment_status', 'enrolled')
            ->where('(students.section_id IS NULL OR students.section_id = 0)')
            ->orderBy('students.last_name', 'ASC')
            ->findAll();
        
        if (empty($students)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No unassigned students found for ' . grade_level_label((int) $gradeLevel)
            ]);
        }
        
        return $this->response->setJSON([
            'success' => true,
            'students' => $students
        ]);
    }

    public function assignStudentsToSection($sectionId)
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $sectionModel = new \App\Models\SectionModel();
        $studentModel = new StudentModel();
        
        // Get teacher record
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        
        if (!$teacher) {
            return $this->response->setJSON(['success' => false, 'error' => 'Teacher record not found']);
        }
        
        // Verify teacher is adviser of this section
        $section = $sectionModel->where('id', $sectionId)
            ->where('adviser_id', $teacher['id'])
            ->first();
        
        if (!$section) {
            return $this->response->setJSON(['success' => false, 'error' => 'Section not found or not assigned to you']);
        }
        
        $input = $this->request->getJSON(true);
        $studentIds = $input['student_ids'] ?? [];
        
        if (empty($studentIds)) {
            return $this->response->setJSON(['success' => false, 'error' => 'No students selected']);
        }
        
        $assigned = 0;
        foreach ($studentIds as $studentId) {
            if ($studentModel->update($studentId, ['section_id' => $sectionId])) {
                $assigned++;
            }
        }
        
        return $this->response->setJSON([
            'success' => true,
            'message' => "Successfully assigned {$assigned} student(s) to the section"
        ]);
    }

    public function generateReportCard($studentId)
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }
        
        $teacherId = $this->auth->id();
        $teacherModel = new \App\Models\TeacherModel();
        $studentModel = new StudentModel();
        $gradeModel = new GradeModel();
        $subjectModel = new SubjectModel();
        
        // Get teacher record
        $teacher = $teacherModel->where('user_id', $teacherId)->first();
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found');
        }
        
        // Get student and verify teacher has access
        $student = $studentModel->select('students.*, sections.section_name, sections.grading_type, CONCAT(teachers.first_name, " ", teachers.last_name) as adviser_name')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->join('teachers', 'teachers.id = sections.adviser_id', 'left')
            ->where('students.id', $studentId)
            ->where('sections.adviser_id', $teacher['id'])
            ->first();
        
        if (!$student) {
            return redirect()->back()->with('error', 'Student not found or not in your section');
        }

        // Non-numerical (SNED) sections use developmental domains, not subjects.
        // Route those students to the SNED report card instead of producing an
        // empty numerical report card.
        if (in_array($student['grading_type'] ?? 'numerical', ['non_numerical', 'custom'], true)) {
            return redirect()->to(base_url('teacher/sned/report-card-pdf/' . $studentId));
        }
        
        // Get subjects for student's grade level
        $subjects = $subjectModel->where('grade_level', $student['grade_level'])
            ->where('is_active', true)
            ->findAll();
        
        // Get grades for current school year
        $schoolYear = get_current_school_year();
        $grades = [];
        $quarterAverages = [];
        
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterGrades = [];
            foreach ($subjects as $subject) {
                $grade = $gradeModel->where('student_id', $studentId)
                    ->where('subject_id', $subject['id'])
                    ->where('school_year', $schoolYear)
                    ->where('term', $quarter)
                    ->first();
                
                $quarterGrades[$subject['id']] = $grade ? $grade['grade'] : null;
            }
            $grades[$quarter] = $quarterGrades;
            
            // Calculate quarter average - only sum numeric grades
            $numericGrades = array_filter($quarterGrades, function($g) { return $g !== null && is_numeric($g); });
            $quarterAverages[$quarter] = !empty($numericGrades) ? array_sum($numericGrades) / count($numericGrades) : 0;
        }
        
        // Calculate final average - filter out non-numeric values
        $validQuarters = array_filter($quarterAverages, function($avg) { return is_numeric($avg) && $avg > 0; });
        $finalAverage = !empty($validQuarters) ? array_sum($validQuarters) / count($validQuarters) : 0;
        
        $data = [
            'student' => $student,
            'teacher' => $teacher,
            'subjects' => $subjects,
            'grades' => $grades,
            'quarterAverages' => $quarterAverages,
            'finalAverage' => $finalAverage,
            'schoolYear' => $schoolYear,
            'reportDate' => date('F j, Y')
        ];
        
        $html = view('teacher/report_card_pdf', $data);
        
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
     * Toggle whether a student may view their own report card
     * (teacher/students -> "View Access" badge / bulk toggle).
     */
    public function toggleReportCardAccess()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $payload   = $this->request->getJSON(true) ?: $this->request->getPost();
        $studentId = (int) ($payload['student_id'] ?? 0);
        $canView   = ! empty($payload['can_view']) ? 1 : 0;

        if ($studentId <= 0) {
            return $this->response->setJSON(['success' => false, 'error' => 'Student not specified']);
        }

        $teacher = model(\App\Models\TeacherModel::class)->where('user_id', $this->auth->id())->first();
        if (!$teacher) {
            return $this->response->setJSON(['success' => false, 'error' => 'Teacher record not found']);
        }

        // Only the advisory teacher may change report card access.
        $db      = \Config\Database::connect();
        $student = $db->table('students')
            ->select('students.id')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->where('students.id', $studentId)
            ->where('sections.adviser_id', $teacher['id'])
            ->get()->getRowArray();

        if (!$student) {
            return $this->response->setJSON(['success' => false, 'error' => 'Student not found or not in your section']);
        }

        $db->table('students')->where('id', $studentId)->update([
            'can_view_report_card' => $canView,
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON([
            'success'  => true,
            'can_view' => (bool) $canView,
            'message'  => $canView
                ? 'Report card access enabled.'
                : 'Report card access disabled.',
        ]);
    }

    /**
     * Send report card notifications to the selected students' accounts.
     * Payload: {student_ids: [..]}
     */
    public function sendAllReportCards()
    {
        return $this->sendReportCardNotifications();
    }

    /**
     * Send a report card notification to a single student.
     */
    public function sendReportCard()
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();

        return $this->sendReportCardNotifications([ (int) ($payload['student_id'] ?? 0) ]);
    }

    /**
     * Shared implementation for the report card notification endpoints.
     *
     * @param array|null $studentIds When null, ids are taken from the request.
     */
    private function sendReportCardNotifications(?array $studentIds = null)
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        if ($studentIds === null) {
            $payload    = $this->request->getJSON(true) ?: $this->request->getPost();
            $studentIds = array_map('intval', (array) ($payload['student_ids'] ?? []));
        }

        $studentIds = array_values(array_filter(array_map('intval', (array) $studentIds)));
        if (empty($studentIds)) {
            return $this->response->setJSON(['success' => false, 'error' => 'No students selected']);
        }

        $teacher = model(\App\Models\TeacherModel::class)->where('user_id', $this->auth->id())->first();
        if (!$teacher) {
            return $this->response->setJSON(['success' => false, 'error' => 'Teacher record not found']);
        }

        $db = \Config\Database::connect();

        // Restrict to students in this teacher's advisory section.
        $students = $db->table('students')
            ->select('students.id, students.first_name, students.last_name, students.user_id')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->where('sections.adviser_id', $teacher['id'])
            ->whereIn('students.id', $studentIds)
            ->get()->getResultArray();

        if (empty($students)) {
            return $this->response->setJSON(['success' => false, 'error' => 'No valid students in your section']);
        }

        $notificationModel = model(\App\Models\NotificationModel::class);
        $sent    = 0;
        $skipped = 0;

        foreach ($students as $s) {
            if (empty($s['user_id'])) {
                $skipped++;
                continue; // no student account to notify
            }

            $notificationModel->insert([
                'user_id'    => (int) $s['user_id'],
                'type'       => 'report_card',
                'title'      => 'Report Card Available',
                'message'    => 'Your report card is now available. Please contact your class adviser for details.',
                'is_read'    => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $sent++;
        }

        return $this->response->setJSON([
            'success' => true,
            'sent'    => $sent,
            'skipped' => $skipped,
            'message' => "Report card notifications sent to {$sent} student(s)"
                . ($skipped > 0 ? " ({$skipped} skipped - no student account)" : '') . '.',
        ]);
    }

    /**
     * Remove students from the teacher's advisory section.
     * Payload: {student_ids: [..]}
     */
    public function removeStudent()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $payload    = $this->request->getJSON(true) ?: $this->request->getPost();
        $studentIds = array_values(array_filter(array_map('intval', (array) ($payload['student_ids'] ?? []))));

        if (empty($studentIds)) {
            return $this->response->setJSON(['success' => false, 'error' => 'No students selected']);
        }

        $teacher = model(\App\Models\TeacherModel::class)->where('user_id', $this->auth->id())->first();
        if (!$teacher) {
            return $this->response->setJSON(['success' => false, 'error' => 'Teacher record not found']);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        // Only students inside this teacher's advisory section may be removed.
        $valid = $db->table('students')
            ->select('students.id')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->where('sections.adviser_id', $teacher['id'])
            ->whereIn('students.id', $studentIds)
            ->get()->getResultArray();

        foreach ($valid as $s) {
            $db->table('students')->where('id', $s['id'])->update([
                'section_id' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['success' => false, 'error' => 'Failed to remove students']);
        }

        return $this->response->setJSON([
            'success' => true,
            'removed' => count($valid),
            'message' => count($valid) . ' student(s) removed from your section.',
        ]);
    }

    /**
     * Submit grade recommendations from a subject teacher to the advisory
     * teacher (teacher/grades -> "Submit grade recommendations").
     * Payload: {section_id, subject_id, grades: {student_id: {term: value}}}
     */
    public function submitGradeRecommendation()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $payload   = $this->request->getJSON(true) ?: $this->request->getPost();
        $sectionId = (int) ($payload['section_id'] ?? 0);
        $subjectId = (int) ($payload['subject_id'] ?? 0);
        $grades    = (array) ($payload['grades'] ?? []);

        if ($sectionId <= 0 || $subjectId <= 0 || empty($grades)) {
            return $this->response->setJSON(['success' => false, 'error' => 'Invalid submission data']);
        }

        $teacherId = $this->auth->id();
        $teacher   = model(\App\Models\TeacherModel::class)->where('user_id', $teacherId)->first();
        if (!$teacher) {
            return $this->response->setJSON(['success' => false, 'error' => 'Teacher record not found']);
        }

        $db = \Config\Database::connect();

        // The submitter must actually teach this subject in this section.
        $teaches = $db->table('teacher_schedules')
            ->where('teacher_id', $teacher['id'])
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->countAllResults();

        if ($teaches === 0) {
            return $this->response->setJSON(['success' => false, 'error' => 'You do not teach this subject in this section']);
        }

        $section = $db->table('sections')
            ->select('id, section_name, adviser_id')
            ->where('id', $sectionId)
            ->get()->getRowArray();

        if (!$section) {
            return $this->response->setJSON(['success' => false, 'error' => 'Section not found']);
        }

        $schoolYear = get_current_school_year();
        $saved   = 0;
        $invalid = 0;

        $db->transStart();

        foreach ($grades as $studentId => $terms) {
            $studentId = (int) $studentId;
            if ($studentId <= 0 || !is_array($terms)) {
                $invalid++;
                continue;
            }

            foreach ($terms as $term => $value) {
                $term  = (int) $term;
                $value = trim((string) $value);
                if ($term < 1 || $term > 4 || $value === '') {
                    $invalid++;
                    continue;
                }

                // Recommendations land in the same grades table, so numeric
                // entries obey the report card floor too (44 or 59 -> 60).
                // Symbol grades (P, AP, D, B, NO/NA) pass through untouched.
                if (is_numeric($value)) {
                    $value = clamp_report_card_grade($value);
                }

                $existing = $db->table('grades')
                    ->where('student_id', $studentId)
                    ->where('subject_id', $subjectId)
                    ->where('school_year', $schoolYear)
                    ->where('term', $term)
                    ->where('deleted_at', null)
                    ->get()->getRowArray();

                if ($existing) {
                    $db->table('grades')->where('id', $existing['id'])->update([
                        'grade'      => $value,
                        'teacher_id' => $teacher['id'],
                        'remarks'    => 'Recommended',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $db->table('grades')->insert([
                        'student_id'    => $studentId,
                        'subject_id'    => $subjectId,
                        'teacher_id'    => $teacher['id'],
                        'school_year'   => $schoolYear,
                        'term'          => $term,
                        'grade'         => $value,
                        'remarks'       => 'Recommended',
                        'date_recorded' => date('Y-m-d H:i:s'),
                        'created_at'    => date('Y-m-d H:i:s'),
                        'updated_at'    => date('Y-m-d H:i:s'),
                    ]);
                }
                $saved++;
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['success' => false, 'error' => 'Failed to save grade recommendations']);
        }

        // Notify the advisory teacher (if any) that recommendations arrived.
        if (! empty($section['adviser_id'])) {
            $adviser = $db->table('teachers')
                ->select('user_id')
                ->where('id', (int) $section['adviser_id'])
                ->get()->getRowArray();

            if ($adviser && ! empty($adviser['user_id']) && (int) $adviser['user_id'] !== $teacherId) {
                model(\App\Models\NotificationModel::class)->insert([
                    'user_id'    => (int) $adviser['user_id'],
                    'type'       => 'grade_recommendation',
                    'title'      => 'Grade Recommendations Submitted',
                    'message'    => 'A subject teacher submitted grade recommendations for section ' . $section['section_name'] . '. Please review them under Enter Grades.',
                    'is_read'    => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'saved'   => $saved,
            'invalid' => $invalid,
            'message' => "Grade recommendations submitted ({$saved} entries saved).",
        ]);
    }

    }
