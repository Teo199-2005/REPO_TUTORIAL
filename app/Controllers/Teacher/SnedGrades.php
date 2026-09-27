<?php
namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Models\SnedCategoryModel;
use App\Models\SnedCategoryFieldModel;
use App\Models\SnedGradeModel;
use App\Models\StudentModel;
use App\Models\TeacherModel;

class SnedGrades extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
        helper(['sned', 'grade_level']);
    }

    public function index()
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        $teacherId = $this->auth->id();
        $teacherModel = new TeacherModel();
        $teacher = $teacherModel->where('user_id', $teacherId)->first();

        if (!$teacher) {
            return redirect()->to(base_url('/'))->with('error', 'Teacher record not found');
        }

        $db = \Config\Database::connect();
        $schoolYear = get_current_school_year();

        // Find non-numerical sections for this teacher
        // (whereIn, NOT where('... IN', ...) — the latter produces
        // invalid SQL: `grading_type` `IN` = ('non_numerical','custom')).
        $nonNumericalSections = $db->table('sections')
            ->select('sections.*')
            ->whereIn('sections.grading_type', ['non_numerical', 'custom'])
            ->where('sections.is_active', 1)
            ->where('sections.adviser_id', $teacher['id'])
            ->get()
            ->getResultArray();

        $sections = [];
        foreach ($nonNumericalSections as $section) {
            // Get students in this section
            $students = $db->table('students')
                ->select('id, first_name, middle_name, last_name, suffix, lrn, grade_level, enrollment_status')
                ->where('section_id', $section['id'])
                ->where('enrollment_status', 'enrolled')
                ->orderBy('last_name', 'ASC')
                ->orderBy('first_name', 'ASC')
                ->get()
                ->getResultArray();

            if (!empty($students)) {
                $section['students'] = $students;
                $sections[] = $section;
            }
        }

        return view('teacher/sned_index', [
            'sections' => $sections,
            'schoolYear' => $schoolYear,
            'teacher' => $teacher,
            'section' => !empty($sections) ? $sections[0] : null,
        ]);
    }

    public function categories($sectionId)
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        $db = \Config\Database::connect();
        $section = $db->table('sections')->where('id', $sectionId)->get()->getRowArray();
        if (!$section || !in_array($section['grading_type'] ?? 'numerical', ['non_numerical', 'custom'])) {
            return redirect()->to(base_url('teacher/sned'))->with('error', 'Invalid SNED section');
        }

        $categoryModel = new SnedCategoryModel();
        $categories = $categoryModel->getCategoriesWithFieldCounts($sectionId);

        return view('teacher/sned_categories', [
            'section' => $section,
            'categories' => $categories,
        ]);
    }

    public function gradeEntry($sectionId, $categoryId)
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        $teacherId = $this->auth->id();
        $teacherModel = new TeacherModel();
        $teacher = $teacherModel->where('user_id', $teacherId)->first();

        $db = \Config\Database::connect();
        $section = $db->table('sections')->where('id', $sectionId)->get()->getRowArray();
        if (!$section || !in_array($section['grading_type'] ?? 'numerical', ['non_numerical', 'custom'])) {
            return redirect()->to(base_url('teacher/sned'))->with('error', 'Invalid SNED section');
        }

        $categoryModel = new SnedCategoryModel();
        $category = $categoryModel->getCategoryWithFields($categoryId);
        if (!$category) {
            return redirect()->to(base_url("teacher/sned/categories/{$sectionId}"))->with('error', 'Category not found');
        }

        $students = $db->table('students')
            ->select('id, first_name, middle_name, last_name, suffix, lrn')
            ->where('section_id', $sectionId)
            ->where('enrollment_status', 'enrolled')
            ->orderBy('last_name', 'ASC')
            ->orderBy('first_name', 'ASC')
            ->get()
            ->getResultArray();

        $schoolYear = get_current_school_year();
        $gradeModel = new SnedGradeModel();
        $studentIds = array_column($students, 'id');

        // Get grades for each quarter
        $gradesByQuarter = [];
        foreach (sned_quarters() as $q) {
            $gradesByQuarter[$q] = $gradeModel->getStudentsWithGrades($studentIds, $schoolYear, $q);
        }

        // Load dynamic grading symbols for this section from the database
        $dynamicSymbols = $db->table('section_grading_symbols')
            ->where('section_id', $section['id'])
            ->where('is_active', 1)
            ->orderBy('display_order', 'ASC')
            ->get()
            ->getResultArray();

        return view('teacher/sned_grade_entry', [
            'section' => $section,
            'category' => $category,
            'students' => $students,
            'gradesByQuarter' => $gradesByQuarter,
            'schoolYear' => $schoolYear,
            'quarters' => sned_quarters(),
            'gradeSymbols' => $dynamicSymbols,
            'teacher' => $teacher,
        ]);
    }

    public function saveGrades()
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('teacher')) {
            return redirect()->json(['success' => false, 'message' => 'Unauthorized']);
        }

        $teacherId = $this->auth->id();
        $teacherModel = new TeacherModel();
        $teacher = $teacherModel->where('user_id', $teacherId)->first();

        $studentId = $this->request->getPost('student_id');
        $fieldId = $this->request->getPost('field_id');
        $quarter = $this->request->getPost('quarter');
        $gradeSymbol = $this->request->getPost('grade_symbol');
        $remarks = $this->request->getPost('remarks');

        if (!$studentId || !$fieldId || !$quarter) {
            return $this->response->setJSON(['success' => false, 'message' => 'Missing required fields']);
        }

        $gradeModel = new SnedGradeModel();
        $schoolYear = get_current_school_year();

        try {
            $gradeModel->upsertGrade([
                'student_id' => $studentId,
                'field_id' => $fieldId,
                'teacher_id' => $teacher['id'],
                'school_year' => $schoolYear,
                'quarter' => $quarter,
                'grade_symbol' => $gradeSymbol ?: null,
                'remarks' => $remarks ?: null,
            ]);

            return $this->response->setJSON(['success' => true, 'message' => 'Grade saved successfully']);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function manageFields($categoryId)
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        $categoryModel = new SnedCategoryModel();
        $category = $categoryModel->getCategoryWithFields($categoryId);
        if (!$category) {
            return redirect()->to(base_url('teacher/sned'))->with('error', 'Category not found');
        }

        return view('teacher/sned_manage_fields', [
            'category' => $category,
        ]);
    }

    public function addField()
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        $rules = [
            'category_id' => 'required|integer',
            'field_name' => 'required|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->with('error', 'Please provide a field name')->withInput();
        }

        $fieldModel = new SnedCategoryFieldModel();
        $categoryId = $this->request->getPost('category_id');
        $fieldName = $this->request->getPost('field_name');

        $fieldModel->insert([
            'category_id' => $categoryId,
            'field_name' => $fieldName,
            'display_order' => $fieldModel->getNextDisplayOrder($categoryId),
            'is_active' => 1,
        ]);

        return redirect()->to(base_url("teacher/sned/categories/{$categoryId}/fields"))
            ->with('success', 'Field added successfully');
    }

    public function editField()
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        $rules = [
            'field_id' => 'required|integer',
            'field_name' => 'required|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->with('error', 'Please provide a field name');
        }

        $fieldModel = new SnedCategoryFieldModel();
        $fieldId = $this->request->getPost('field_id');
        $field = $fieldModel->find($fieldId);

        if (!$field) {
            return redirect()->back()->with('error', 'Field not found');
        }

        $fieldModel->update($fieldId, [
            'field_name' => $this->request->getPost('field_name'),
        ]);

        return redirect()->to(base_url("teacher/sned/categories/{$field['category_id']}/fields"))
            ->with('success', 'Field updated successfully');
    }

    public function deleteField($fieldId)
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        $fieldModel = new SnedCategoryFieldModel();
        $field = $fieldModel->find($fieldId);

        if (!$field) {
            return redirect()->back()->with('error', 'Field not found');
        }

        $fieldModel->update($fieldId, ['is_active' => 0]);

        return redirect()->to(base_url("teacher/sned/categories/{$field['category_id']}/fields"))
            ->with('success', 'Field deactivated successfully');
    }

    public function reportCard($studentId)
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        try {
            $studentModel = new StudentModel();
            $student = $studentModel->find($studentId);

            // Verify student belongs to a non-numerical section
            $sectionModel = new \App\Models\SectionModel();
            $section = $sectionModel->find($student['section_id'] ?? 0);
            if (!$student || !$section || !in_array($section['grading_type'] ?? 'numerical', ['non_numerical', 'custom'])) {
                return redirect()->to(base_url('teacher/sned'))->with('error', 'Invalid SNED student');
            }

            $schoolYear = get_current_school_year();
            $categoryModel = new SnedCategoryModel();
            $gradeModel = new SnedGradeModel();

            $categories = $categoryModel->getAllCategoriesWithFields((int) $section['id'], (int) ($section['grade_level'] ?? 0));
            $allGrades = $gradeModel->getStudentAllGrades($studentId, $schoolYear);

            // Load dynamic grading symbols for this section
            $db = \Config\Database::connect();
            $gradingSymbols = $db->table('section_grading_symbols')
                ->where('section_id', $section['id'])
                ->where('is_active', 1)
                ->orderBy('display_order', 'ASC')
                ->get()
                ->getResultArray();

            return view('teacher/sned_report_card', [
                'student' => $student,
                'section' => $section,
                'categories' => $categories,
                'allGrades' => $allGrades,
                'schoolYear' => $schoolYear,
                'quarters' => sned_quarters(),
                'gradeSymbols' => $gradingSymbols,
                'reportDate' => date('F j, Y'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'SNED report card (HTML) failed for student ' . $studentId . ': ' . $e->getMessage());
            return redirect()->to(base_url('teacher/sned'))
                ->with('error', 'Could not open the report card: ' . $e->getMessage());
        }
    }

    public function reportCardPdf($studentId)
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        try {
            $studentModel = new StudentModel();
            $student = $studentModel->find($studentId);

            // Verify student belongs to a non-numerical section
            $sectionModel = new \App\Models\SectionModel();
            $section = $sectionModel->find($student['section_id'] ?? 0);
            if (!$student || !$section || !in_array($section['grading_type'] ?? 'numerical', ['non_numerical', 'custom'])) {
                return redirect()->to(base_url('teacher/sned'))->with('error', 'Invalid SNED student');
            }

            $schoolYear = get_current_school_year();
            $categoryModel = new SnedCategoryModel();
            $gradeModel = new SnedGradeModel();

            $categories = $categoryModel->getAllCategoriesWithFields((int) $section['id'], (int) ($section['grade_level'] ?? 0));
            $allGrades = $gradeModel->getStudentAllGrades($studentId, $schoolYear);

            // Load dynamic grading symbols for this section
            $db = \Config\Database::connect();
            $gradingSymbols = $db->table('section_grading_symbols')
                ->where('section_id', $section['id'])
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
                'logoBase64' => $this->getLogoBase64(),
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
            $sectionName = preg_replace('/\s+/', '_', $section['section_name'] ?? 'SNED');
            $filename = "Report_Card_" . $sectionName . "_" . preg_replace('/\s+/', '_', $student['first_name']) . "_" . preg_replace('/\s+/', '_', $student['last_name']) . ".pdf";
            // false = display the PDF in the browser instead of forcing a download.
            return $this->sendPdfInline($dompdf, $filename, false);
        } catch (\Throwable $e) {
            log_message('error', 'SNED report card PDF failed for student ' . $studentId . ': ' . $e->getMessage());
            return redirect()->to(base_url('teacher/sned'))
                ->with('error', 'Could not generate the report card PDF: ' . $e->getMessage());
        }
    }

    /**
     * Learner Development Report PDF — non-numerical sections.
     *
     * A separate document from the Academic Report Card: learner behaviour,
     * conduct, values and character development in the 2026 three-term
     * layout, with no subject grades, averages, ranking or final rating. The
     * SNED progress report above keeps the full developmental-domain view.
     */
    public function learnerDevelopmentReportPdf($studentId)
    {
        if (!$this->auth->loggedIn() || !$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        try {
            $db = \Config\Database::connect();
            $student = $db->table('students')
                ->select("students.*, sections.section_name, sections.grading_type, CONCAT(teachers.first_name, ' ', teachers.last_name) AS adviser_name")
                ->join('sections', 'sections.id = students.section_id', 'left')
                ->join('teachers', 'teachers.id = sections.adviser_id', 'left')
                ->where('students.id', $studentId)
                ->get()
                ->getRowArray();

            if (!$student || !in_array($student['grading_type'] ?? 'numerical', ['non_numerical', 'custom'], true)) {
                return redirect()->to(base_url('teacher/sned'))->with('error', 'Invalid non-numerical student');
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

            // false = display the PDF in the browser instead of forcing a download.
            return $this->sendPdfInline($dompdf, learner_development_pdf_filename($student, $section), false);
        } catch (\Throwable $e) {
            log_message('error', 'Learner development report PDF failed for student ' . $studentId . ': ' . $e->getMessage());
            return redirect()->to(base_url('teacher/sned'))
                ->with('error', 'Could not generate the learner development report: ' . $e->getMessage());
        }
    }

    private function getLogoBase64()
    {
        $logoPath = FCPATH . 'LPHS2.png';
        if (!is_file($logoPath)) {
            $logoPath = FCPATH . 'public/LPHS2.png';
        }
        if (is_file($logoPath)) {
            return base64_encode(file_get_contents($logoPath));
        }
        return '';
    }
}