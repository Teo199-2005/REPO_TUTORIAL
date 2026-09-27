<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\StudentModel;
use App\Models\TeacherModel;
use App\Models\PasswordResetRequestModel;
use CodeIgniter\Shield\Models\UserModel;

class PasswordReset extends BaseController
{
    public function index()
    {
        return view('auth/forgot_password', [
            'title' => 'Reset Password - CSCS Tap n Track'
        ]);
    }

    public function verify()
    {
        $identifier = $this->request->getPost('identifier');
        
        if (!$identifier) {
            return redirect()->back()->with('error', 'Please enter your PRC License Number or LRN.');
        }

        // Check if it's a teacher (PRC License) or student (LRN)
        $teacherModel = new TeacherModel();
        $studentModel = new StudentModel();
        $userModel = new UserModel();
        
        $user = null;
        $userType = null;
        
        // Check teachers first
        // Check teachers first
        $teacher = $teacherModel->where('license_number', $identifier)->first();
        if ($teacher) {
            // A pending, rejected, inactive or deleted teacher record must not
            // be able to submit password-reset requests either - otherwise a
            // rejected teacher could regain a working password through the
            // reset workflow and sign in with it.
            helper('teacher_access');

            $teacherState = teacher_record_state($teacher);

            if ($teacherState !== 'approved') {
                log_message('info', 'Password reset blocked - teacher record state "' . $teacherState . '" for teacher ID: ' . $teacher['id']);

                return redirect()->back()->with('error', teacher_account_denial_message($teacherState));
            }

            $user = $userModel->find($teacher['user_id']);
            $userType = 'teacher';
        } else {
            // Check students
            $student = $studentModel->where('lrn', $identifier)->first();
            if ($student) {
                if ($student['user_id']) {
                    $user = $userModel->find($student['user_id']);
                    $userType = 'student';
                } else {
                    // Student exists but no user account - create one
                    $userData = [
                        'email' => $student['email'] ?: $student['lrn'] . '@student.lphs.edu',
                        'password' => 'temp123',
                        'active' => 1
                    ];
                    
                    $userId = $userModel->insert($userData);
                    if ($userId) {
                        // Update student with user_id
                        $studentModel->update($student['id'], ['user_id' => $userId]);
                        $user = $userModel->find($userId);
                        $userType = 'student';
                    }
                }
            }
        }
        
        if (!$user) {
            return redirect()->back()->with('error', 'No account found with that PRC License Number or LRN.');
        }

        // Get user ID safely
        $userId = null;
        if (is_object($user)) {
            $userId = $user->id ?? null;
        } elseif (is_array($user)) {
            $userId = $user['id'] ?? null;
        }
        
        if (!$userId) {
            return redirect()->back()->with('error', 'Unable to process password reset request.');
        }

        // A teacher whose registration is pending/rejected - or whose account
        // no longer has a usable teacher record - must not be able to work
        // around the login block by requesting a password reset.
        if ($userType === 'teacher') {
            helper('teacher_access');

            $teacherRecord = $teacher ?: find_teacher_record_for_user($user);
            $teacherState  = teacher_record_state($teacherRecord);

            if ($teacherState !== 'approved') {
                log_message('info', 'Password reset blocked - teacher record state "' . $teacherState . '" for user ID: ' . $userId);

                return redirect()->back()->with('error', teacher_account_denial_message($teacherState));
            }
        }

        // Create password reset request
        $resetRequestModel = new PasswordResetRequestModel();
        $token = bin2hex(random_bytes(32));
        
        // Get user email - prioritize student/teacher email over auth_identities
        $userEmail = null;
        
        if ($userType === 'student' && $student) {
            $userEmail = $student['email'] ?: $identifier . '@student.lphs.edu';
        } elseif ($userType === 'teacher' && $teacher) {
            $userEmail = $teacher['email'] ?: $identifier . '@teacher.lphs.edu';
        }
        
        if (!$userEmail) {
            // Try to get email from auth_identities
            $db = \Config\Database::connect();
            $identity = $db->table('auth_identities')
                ->where('user_id', $userId)
                ->where('type', 'email_password')
                ->get()
                ->getRowArray();
            $userEmail = $identity['secret'] ?? $identifier . '@student.lphs.edu';
        }
        
        $resetData = [
            'user_id' => $userId,
            'email' => $userEmail,
            'token' => $token,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $resetRequestModel->insert($resetData);

        return redirect()->to(base_url('forgot-password'))->with('success', 'Password reset request submitted. Please wait for admin approval.');
    }
}
