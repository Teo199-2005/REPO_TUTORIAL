<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\StudentModel;
use App\Models\SectionModel;

class IdCards extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    public function index()
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $studentModel = new StudentModel();
        $sectionModel = new SectionModel();

        // Get filter parameters
        $gradeFilter = $this->request->getGet('grade');
        $sectionFilter = $this->request->getGet('section');
        $searchTerm = $this->request->getGet('search');
        $page = (int) ($this->request->getGet('page') ?? 1);
        $perPage = 9;

        // Build query with filters - include all students, not just enrolled
        $builder = $studentModel->select('students.*, sections.section_name')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->where('students.deleted_at IS NULL');

        if ($gradeFilter) {
            $builder->where('students.grade_level', $gradeFilter);
        }

        if ($sectionFilter) {
            $builder->where('students.section_id', $sectionFilter);
        }

        if ($searchTerm) {
            $builder->groupStart()
                ->like('students.first_name', $searchTerm)
                ->orLike('students.last_name', $searchTerm)
                ->orLike('students.lrn', $searchTerm)
                ->groupEnd();
        }

        // Get total count for pagination
        $totalStudents = $builder->countAllResults(false);
        $totalPages = ceil($totalStudents / $perPage);
        $offset = ($page - 1) * $perPage;

        $students = $builder->groupBy('students.id')
            ->orderBy('students.created_at', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        // Get student photos - use photo_path if available
        foreach ($students as &$student) {
            if (!empty($student['id_photo_path'])) {
                $student['photo'] = $student['id_photo_path'];
            } elseif (!empty($student['photo_path'])) {
                // Legacy fallback for students without a dedicated 2x2 yet.
                $student['photo'] = $student['photo_path'];
            } else {
                $student['photo'] = null;
            }
        }

        // Get all sections for filter dropdown
        $allSections = $sectionModel->select('id, section_name, grade_level')
            ->where('is_active', true)
            ->orderBy('grade_level', 'ASC')
            ->orderBy('section_name', 'ASC')
            ->findAll();

        return view('admin/id_cards', [
            'title' => 'Student ID Cards - CSCS Tap n Track',
            'students' => $students,
            'allSections' => $allSections,
            'gradeFilter' => $gradeFilter,
            'sectionFilter' => $sectionFilter,
            'searchTerm' => $searchTerm,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalStudents' => $totalStudents
        ]);
    }

    public function viewCard($studentId)
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $studentModel = new StudentModel();

        $student = $studentModel->select('students.*, sections.section_name')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->where('students.id', $studentId)
            ->first();

        if (!$student) {
            return redirect()->back()->with('error', 'Student not found');
        }

        // Get student photo
        // Prefer the dedicated 2x2 ID picture; fall back to the legacy photo for students who have no ID photo yet.
        $student['photo'] = !empty($student['id_photo_path']) ? $student['id_photo_path'] : (!empty($student['photo_path']) ? $student['photo_path'] : null);

        return view('admin/id_card_clean', [
            'title' => 'Student ID Card - ' . $student['first_name'] . ' ' . $student['last_name'],
            'student' => $student
        ]);
    }

    public function printCard($studentId)
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $studentModel = new StudentModel();

        $student = $studentModel->select('students.*, sections.section_name')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->where('students.id', $studentId)
            ->first();

        if (!$student) {
            return redirect()->back()->with('error', 'Student not found');
        }

        // Get student photo
        // Prefer the dedicated 2x2 ID picture; fall back to the legacy photo for students who have no ID photo yet.
        $student['photo'] = !empty($student['id_photo_path']) ? $student['id_photo_path'] : (!empty($student['photo_path']) ? $student['photo_path'] : null);

        return view('admin/id_card_print', [
            'title' => 'Student ID Card - ' . $student['first_name'] . ' ' . $student['last_name'],
            'student' => $student
        ]);
    }

    public function generateLrn($studentId)
    {
        if (! is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $studentModel = new StudentModel();
        $student = $studentModel->find($studentId);

        if (!$student) {
            return $this->response->setJSON(['success' => false, 'message' => 'Student not found']);
        }

        if ($student['lrn']) {
            return $this->response->setJSON(['success' => false, 'message' => 'Student already has an LRN']);
        }

        // Generate unique LRN
        $lastStudent = $studentModel->select('lrn')
            ->orderBy('lrn', 'DESC')
            ->first();
        
        if ($lastStudent && is_numeric($lastStudent['lrn'])) {
            $nextNumber = intval($lastStudent['lrn']) + 1;
        } else {
            $nextNumber = 100000000001; // Start with 12-digit LRN
        }
        
        $newLrn = (string)$nextNumber;
        $updated = $studentModel->update($studentId, ['lrn' => $newLrn]);

        if ($updated) {
            return $this->response->setJSON(['success' => true, 'lrn' => $newLrn]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to update student']);
        }
    }
}
