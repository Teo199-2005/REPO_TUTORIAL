<?php
namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Models\MaterialModel;
use App\Models\StudentModel;

class Materials extends BaseController
{
    protected $auth;
    protected $materialModel;
    protected $studentModel;

    public function __construct()
    {
        $this->auth = auth();
        $this->materialModel = new MaterialModel();
        $this->studentModel = new StudentModel();
    }

    public function index()
    {
        if (!$this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'));
        }

        // Get current student
        $student = $this->studentModel->where('user_id', $this->auth->user()->id)->first();
        if (!$student) {
            return redirect()->to(base_url('/'))->with('error', 'Student profile not found.');
        }

        // Get public materials and student-specific materials
        $publicMaterials = $this->materialModel->getPublicMaterials();
        $studentMaterials = $this->materialModel->getStudentMaterials($student['id']);

        return view('student/materials', [
            'title' => 'Learning Materials - CSCS Tap n Track',
            'publicMaterials' => $publicMaterials,
            'studentMaterials' => $studentMaterials,
            'student' => $student
        ]);
    }

    public function download($id)
    {
        if (!$this->auth->user()->inGroup('student')) {
            return redirect()->to(base_url('/'));
        }

        // Get current student
        $student = $this->studentModel->where('user_id', $this->auth->user()->id)->first();
        if (!$student) {
            return redirect()->to(base_url('/'))->with('error', 'Student profile not found.');
        }

        $material = $this->materialModel->find($id);
        if (!$material) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Material not found');
        }

        // Check if student can access this material (public or assigned to them)
        if (!$material->is_public && $material->student_id != $student['id']) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        $filePath = WRITEPATH . 'uploads/' . $material->file_path;
        if (!file_exists($filePath)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('File not found');
        }

        return $this->response->download($filePath, null);
    }
}
