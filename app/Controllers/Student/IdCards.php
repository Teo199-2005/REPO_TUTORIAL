<?php
namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Models\StudentModel;

class IdCards extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    /**
     * Digital ID card for the LOGGED-IN student only.
     * The record is looked up through the user's own user_id, so a student
     * can never view another student's card by changing the URL.
     */
    public function index()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        $studentModel = new StudentModel();
        $user = $this->auth->user();
        $student = $studentModel->where('user_id', $user->id)->first();

        if (!$student) {
            return redirect()->to(base_url('student/dashboard'))->with('error', 'Student profile not found.');
        }

        $student = $studentModel->getStudentWithSection((int) $student['id']);

        // The ID card always uses the dedicated 2x2 picture (id_photo_path),
        // which is separate from the circle profile photo (photo_path).
        $student['photo'] = !empty($student['id_photo_path']) ? $student['id_photo_path'] : null;

        return view('student/id_card', [
            'title' => 'My ID Card - CSCS Tap n Track',
            'student' => $student,
        ]);
    }
}
