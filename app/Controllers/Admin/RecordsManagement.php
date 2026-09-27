<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ReportCardRecordModel;
use App\Models\GradeModel;
use App\Models\StudentModel;

class RecordsManagement extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();
        
        // Get school year from system settings
        $systemSettingModel = new \App\Models\SystemSettingModel();
        $systemSchoolYear = $systemSettingModel->getSetting('current_school_year');
        
        // Get available school years from sections table
        $schoolYears = $db->query("SELECT DISTINCT school_year FROM sections ORDER BY school_year DESC")->getResultArray();
        
        // Add system school year to list if not present
        if ($systemSchoolYear) {
            $found = false;
            foreach ($schoolYears as $year) {
                if ($year['school_year'] === $systemSchoolYear) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                array_unshift($schoolYears, ['school_year' => $systemSchoolYear]);
            }
        }
        
        $selectedYear = $this->request->getGet('year') ?? ($systemSchoolYear ?: (count($schoolYears) > 0 ? $schoolYears[0]['school_year'] : get_current_school_year()));
        
        // Get all sections from database - check if any exist for selected year first
        $allSections = $db->query("
            SELECT grade_level, section_name
            FROM sections
            WHERE school_year = ?
            ORDER BY grade_level ASC, section_name ASC
        ", [$selectedYear])->getResultArray();
        
        // If no sections found for selected year, get all sections and use grades school_year
        if (empty($allSections)) {
            $allSections = $db->query("
                SELECT DISTINCT s.grade_level, sec.section_name
                FROM students s
                JOIN sections sec ON sec.id = s.section_id
                JOIN grades g ON g.student_id = s.id
                WHERE g.school_year = ?
                ORDER BY s.grade_level ASC, sec.section_name ASC
            ", [$selectedYear])->getResultArray();
        }
        
        // Initialize structure with all sections
        $groupedRecords = [];
        foreach ($allSections as $section) {
            $grade = $section['grade_level'];
            $sectionName = $section['section_name'];
            $groupedRecords[$grade][$sectionName] = [];
        }
        
        // Get enrolled students in sections (LEFT JOIN grades so non-numerical
        // sections — which use SNED domain grades, not the grades table — still show)
        $students = $db->query("
            SELECT DISTINCT s.id, s.first_name, s.last_name, s.lrn, s.grade_level, sec.section_name, sec.grading_type
            FROM students s
            LEFT JOIN grades g ON g.student_id = s.id AND g.school_year = ?
            JOIN sections sec ON sec.id = s.section_id AND sec.school_year = ?
            WHERE s.enrollment_status = 'enrolled'
            ORDER BY s.grade_level ASC, sec.section_name ASC, s.last_name ASC
        ", [$selectedYear, $selectedYear])->getResultArray();
        
        // Add students to their sections
        foreach ($students as $student) {
            $grade = $student['grade_level'];
            $section = $student['section_name'];
            if (isset($groupedRecords[$grade][$section])) {
                $groupedRecords[$grade][$section][] = $student;
            }
        }
        
        // Remove grades with no sections
        foreach ($groupedRecords as $grade => $sections) {
            if (empty($sections)) {
                unset($groupedRecords[$grade]);
            }
        }
        
        return view('admin/records_management', [
            'title' => 'Records Management - CSCS Tap n Track',
            'schoolYears' => $schoolYears,
            'selectedYear' => $selectedYear,
            'groupedRecords' => $groupedRecords
        ]);
    }

    public function viewSection($gradeLevel, $sectionName)
    {
        $db = \Config\Database::connect();
        $systemSettingModel = new \App\Models\SystemSettingModel();
        $systemSchoolYear = $systemSettingModel->getSetting('current_school_year');
        $schoolYear = $this->request->getGet('year') ?? ($systemSchoolYear ?: get_current_school_year());
        
        // Get enrolled students in this section (LEFT JOIN grades so non-numerical
        // sections — which use SNED domain grades, not the grades table — still show)
        $students = $db->query("
            SELECT DISTINCT s.id, s.first_name, s.last_name, s.lrn, sec.grading_type
            FROM students s
            LEFT JOIN grades g ON g.student_id = s.id AND g.school_year = ?
            JOIN sections sec ON sec.id = s.section_id AND sec.school_year = ?
            WHERE s.grade_level = ? AND sec.section_name = ? AND s.enrollment_status = 'enrolled'
            ORDER BY s.last_name ASC
        ", [$schoolYear, $schoolYear, $gradeLevel, $sectionName])->getResultArray();
        
        $gradingType = $students[0]['grading_type'] ?? 'numerical';
        
        return view('admin/records_section', [
            'title' => 'Records Management - CSCS Tap n Track',
            'gradeLevel' => $gradeLevel,
            'sectionName' => $sectionName,
            'students' => $students,
            'gradingType' => $gradingType,
            'schoolYear' => $schoolYear
        ]);
    }

    public function archiveCompleted()
    {
        $systemSettingModel = new \App\Models\SystemSettingModel();
        $systemSchoolYear = $systemSettingModel->getSetting('current_school_year');
        $schoolYear = $this->request->getPost('school_year') ?? ($systemSchoolYear ?: get_current_school_year());
        $gradeModel = new GradeModel();
        $studentModel = new StudentModel();
        $recordModel = new ReportCardRecordModel();
        
        // Get all students with grades from the final term of the year
        $db = \Config\Database::connect();
        $studentsWithFinalTerm = $db->query("
            SELECT DISTINCT student_id
            FROM grades
            WHERE school_year = ? AND term = 3
        ", [$schoolYear])->getResultArray();

        $archived = 0;
        foreach ($studentsWithFinalTerm as $row) {
            // Check if already archived
            $exists = $recordModel->where('student_id', $row['student_id'])
                ->where('school_year', $schoolYear)
                ->first();
            
            if (!$exists) {
                if ($recordModel->archiveStudentRecord($row['student_id'], $schoolYear)) {
                    $archived++;
                }
            }
        }
        
        audit_event('records.archived', [
            'category'      => 'data',
            'status'        => 'success',
            'resource_type' => 'record',
            'resource_id'   => (string) $schoolYear,
            'description'   => "Archived {$archived} student record(s) for {$schoolYear}",
            'metadata'      => ['archived' => (int) $archived, 'school_year' => (string) $schoolYear],
        ]);

        return redirect()->back()->with('success', "Archived {$archived} student records for {$schoolYear}");
    }

    public function viewRecord($studentId)
    {
        $gradeModel = new GradeModel();
        $studentModel = new StudentModel();
        $subjectModel = new \App\Models\SubjectModel();
        $db = \Config\Database::connect();
        
        $systemSettingModel = new \App\Models\SystemSettingModel();
        $systemSchoolYear = $systemSettingModel->getSetting('current_school_year');
        $schoolYear = $this->request->getGet('year') ?? ($systemSchoolYear ?: get_current_school_year());
        
        $student = $db->query("
            SELECT s.*, sec.section_name, sec.adviser_id, CONCAT(t.first_name, ' ', t.last_name) as adviser_name
            FROM students s
            LEFT JOIN sections sec ON sec.id = s.section_id
            LEFT JOIN teachers t ON t.id = sec.adviser_id
            WHERE s.id = ?
        ", [$studentId])->getRowArray();
        
        if (!$student) {
            return redirect()->back()->with('error', 'Student not found');
        }
        
        // Get subjects assigned to student's section
        $subjects = $student['section_id'] ? $subjectModel->getSectionSubjects($student['section_id']) : [];
        
        // Get grades for all terms
        $grades = [];
        $termAverages = [];

        for ($t = 1; $t <= 3; $t++) {
            $termGrades = [];
            foreach ($subjects as $subject) {
                $grade = $gradeModel->where('student_id', $studentId)
                    ->where('subject_id', $subject['id'])
                    ->where('school_year', $schoolYear)
                    ->where('term', $t)
                    ->first();
                $termGrades[$subject['id']] = $grade ? $grade['grade'] : null;
            }
            $grades[$t] = $termGrades;

            $validGrades = array_filter($termGrades, function($g) { return $g !== null; });
            $termAverages[$t] = !empty($validGrades) ? array_sum($validGrades) / count($validGrades) : null;
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
        
        try {
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
            
            $filename = 'CSCS_Report_Card_' . $student['first_name'] . '_' . $student['last_name'] . '_' . $schoolYear . '.pdf';

            return $this->sendPdfInline($dompdf, $filename);
        } catch (\Exception $e) {
            log_message('error', 'PDF generation error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate report card: ' . $e->getMessage());
        }
    }

    /**
     * Developmental (non-numerical) report card for a student — admin view.
     * Reuses the same domain/categories/symbols data and layout as the
     * teacher SNED report card.
     */
    public function viewSnedRecord($studentId)
    {
        $student = $this->loadSnedStudent($studentId);
        if ($student === null) {
            return redirect()->to(base_url('admin/records'))->with('error', 'Invalid non-numerical student');
        }

        return view('teacher/sned_report_card', [
            'student'      => $student['student'],
            'section'      => $student['section'],
            'categories'   => $student['categories'],
            'allGrades'    => $student['allGrades'],
            'schoolYear'   => $student['schoolYear'],
            'quarters'     => sned_quarters(),
            'gradeSymbols' => $student['gradeSymbols'],
            'reportDate'   => date('F j, Y'),
            'pdfUrl'       => base_url('admin/records/sned-report-card-pdf/' . $studentId),
        ]);
    }

    /**
     * PDF export of the developmental (non-numerical) report card — admin.
     */
    public function viewSnedRecordPdf($studentId)
    {
        $student = $this->loadSnedStudent($studentId);
        if ($student === null) {
            return redirect()->to(base_url('admin/records'))->with('error', 'Invalid non-numerical student');
        }

        try {
            $html = view('teacher/sned_report_card_pdf', [
                'student'      => $student['student'],
                'section'      => $student['section'],
                'categories'   => $student['categories'],
                'allGrades'    => $student['allGrades'],
                'schoolYear'   => $student['schoolYear'],
                'quarters'     => sned_quarters(),
                'gradeSymbols' => $student['gradeSymbols'],
                'reportDate'   => date('F j, Y'),
                'logoBase64'   => school_logo_base64(),
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

            $sectionSlug = preg_replace('/\s+/', '_', $student['section']['section_name'] ?? 'SNED');
            $filename = 'Report_Card_' . $sectionSlug . '_'
                . preg_replace('/\s+/', '_', $student['student']['first_name']) . '_'
                . preg_replace('/\s+/', '_', $student['student']['last_name']) . '.pdf';

            return $this->sendPdfInline($dompdf, $filename);
        } catch (\Exception $e) {
            log_message('error', 'SNED report card PDF (admin) failed for student ' . $studentId . ': ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate report card: ' . $e->getMessage());
        }
    }

    /**
     * PDF export of the Learner Development Report — non-numerical learner.
     *
     * Conduct, values and character development on the 2026 three-term
     * layout. It sits beside the developmental report card above and carries
     * no subject grades, averages, ranking or final rating.
     */
    public function learnerDevelopmentReportPdf($studentId)
    {
        $student = $this->loadSnedStudent($studentId);
        if ($student === null) {
            return redirect()->to(base_url('admin/records'))->with('error', 'Invalid non-numerical student');
        }

        try {
            $html = view('teacher/learner_development_report_pdf', [
                'student'    => $student['student'],
                'section'    => $student['section'],
                'schoolYear' => $student['schoolYear'],
                'reportDate' => date('F j, Y'),
                'logoBase64' => school_logo_base64(),
                'report'     => learner_development_report_data(
                    $student['student'],
                    $student['section'],
                    $student['schoolYear']
                ),
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

            return $this->sendPdfInline(
                $dompdf,
                learner_development_pdf_filename($student['student'], $student['section'])
            );
        } catch (\Throwable $e) {
            log_message('error', 'Learner development report PDF (admin) failed for student ' . $studentId . ': ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate the learner development report: ' . $e->getMessage());
        }
    }

    /**
     * Shared loader for the admin developmental report card views.
     * Returns null when the student/section is not non-numerical.
     */
    private function loadSnedStudent($studentId)
    {
        $db = \Config\Database::connect();

        $student = $db->query("
            SELECT s.*, sec.section_name, sec.grading_type, sec.adviser_id,
                   CONCAT(t.first_name, ' ', t.last_name) as adviser_name
            FROM students s
            LEFT JOIN sections sec ON sec.id = s.section_id
            LEFT JOIN teachers t ON t.id = sec.adviser_id
            WHERE s.id = ?
        ", [$studentId])->getRowArray();

        if (!$student || !in_array($student['grading_type'] ?? 'numerical', ['non_numerical', 'custom'])) {
            return null;
        }

        $section = [
            'id'           => $student['section_id'],
            'section_name' => $student['section_name'],
            'grading_type' => $student['grading_type'],
            'grade_level'  => $student['grade_level'],
            'adviser_id'   => $student['adviser_id'],
        ];

        $systemSettingModel = new \App\Models\SystemSettingModel();
        $systemSchoolYear = $systemSettingModel->getSetting('current_school_year');
        $schoolYear = $this->request->getGet('year') ?? ($systemSchoolYear ?: get_current_school_year());

        $categoryModel = new \App\Models\SnedCategoryModel();
        $gradeModel = new \App\Models\SnedGradeModel();

        $categories = $categoryModel->getAllCategoriesWithFields((int) $section['id'], (int) ($section['grade_level'] ?? 0));
        $allGrades = $gradeModel->getStudentAllGrades($studentId, $schoolYear);

        $gradeSymbols = $db->table('section_grading_symbols')
            ->where('section_id', $section['id'])
            ->where('is_active', 1)
            ->orderBy('display_order', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'student'      => $student,
            'section'      => $section,
            'categories'   => $categories,
            'allGrades'    => $allGrades,
            'schoolYear'   => $schoolYear,
            'gradeSymbols' => $gradeSymbols,
        ];
    }
}

