<?php
namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Models\TeacherModel;
use CodeIgniter\Shield\Models\UserModel;

class Profile extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    public function index()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        $teacherModel = new TeacherModel();
        $teacher = $teacherModel->where('user_id', $this->auth->id())->first();

        if (!$teacher) {
            return redirect()->to(base_url('teacher/dashboard'))->with('error', 'Teacher profile not found.');
        }

        $db = \Config\Database::connect();
        $section = $db->table('sections')
            ->where('adviser_id', $teacher['id'])
            ->get()->getRow();
        
        $department = $section
            ? grade_level_label((int) $section->grade_level, $section->grade_level_custom ?? null)
            : 'Not Assigned';

        return view('teacher/profile', [
            'title' => 'My Profile - CSCS Tap n Track',
            'teacher' => $teacher,
            'department' => $department
        ]);
    }

    public function update()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }
        
        // Verify this is a POST request
        if (!$this->request->getMethod() === 'post') {
            return redirect()->back()->with('error', 'Invalid request method.');
        }

        $teacherModel = new TeacherModel();
        $teacher = $teacherModel->where('user_id', $this->auth->id())->first();
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher profile not found.');
        }

        // Get current email to check if it's being changed
        $currentEmail = $teacher['email'];
        $newEmail = $this->request->getPost('email');
        
        // Normalise legacy phone formats before validation and the writes below.
        phone_normalize_request($this->request, ['contact_number']);

        // Build validation rules
        $rules = [
            'first_name' => 'required|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'middle_name' => 'permit_empty|min_length[2]|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'last_name' => 'required|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'contact_number' => phone_validation_rule(),
            'address' => 'permit_empty|max_length[255]'
        ];
        
        // Only add email uniqueness validation if email is being changed
        if ($currentEmail !== $newEmail) {
            $rules['email'] = 'required|valid_email|max_length[255]|is_unique[teachers.email]';
        } else {
            $rules['email'] = 'required|valid_email|max_length[255]';
        }

        if (!$this->validate($rules, phone_validation_messages(['contact_number' => 'Contact Number']))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'first_name' => $this->request->getPost('first_name'),
            'middle_name' => $this->request->getPost('middle_name') ?: null,
            'last_name' => $this->request->getPost('last_name'),
            'email' => $this->request->getPost('email'),
            'contact_number' => $this->request->getPost('contact_number') ?: null,
            'address' => $this->request->getPost('address') ?: null
        ];

        try {
            // Log the update attempt
            log_message('info', 'Attempting to update teacher profile for teacher ID: ' . $teacher['id']);
            log_message('info', 'Update data: ' . json_encode($data));
            
            // Skip model validation and update directly
            $teacherModel->skipValidation(true);
            if ($teacherModel->update($teacher['id'], $data)) {
                log_message('info', 'Teacher profile updated successfully for teacher ID: ' . $teacher['id']);
                return redirect()->back()->with('success', 'Profile updated successfully!');
            } else {
                $teacherModel->skipValidation(false);
                $errors = $teacherModel->errors();
                log_message('error', 'Teacher profile update failed. Validation errors: ' . json_encode($errors));
                
                if (!empty($errors)) {
                    return redirect()->back()->withInput()->with('errors', $errors);
                }
                return redirect()->back()->with('error', 'Failed to update profile.');
            }
        } catch (\Exception $e) {
            log_message('error', 'Profile update exception: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()->with('error', 'An error occurred while updating profile.');
        }
    }

    /**
     * Update the teacher's official Personnel Record (Personnel Record tab).
     *
     * Editing is only possible while the school administrator has the
     * teacher's personnel_edit_enabled flag switched on. All inputs go through
     * the same validation rules, option lists and formatters the admin forms
     * use (app/Helpers/teacher_form_helper.php), so names reject digits and
     * symbols, TIN/PhilSys/PRC fields enforce their formats, and every
     * appointment field is a proper dropdown.
     */
    public function updatePersonnel()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        if (strtoupper((string) $this->request->getMethod()) !== 'POST') {
            return redirect()->back()->with('error', 'Invalid request method.');
        }

        $teacherModel = new TeacherModel();
        $teacher = $teacherModel->where('user_id', $this->auth->id())->first();
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher profile not found.');
        }

        // The administrator must currently allow personnel-record editing.
        if ((int) ($teacher['personnel_edit_enabled'] ?? 1) !== 1) {
            log_message('warning', 'Blocked personnel record edit - editing disabled for teacher ID: ' . $teacher['id']);

            return redirect()->back()->with('error', 'Personnel record editing is currently disabled by the school administrator.');
        }

        helper('teacher_form');

        // Religion's "Other" choice is swapped for its typed text before the
        // rules run, so validation and the write see the real religion.
        religion_normalize_request($this->request);

        $newEmail = trim((string) $this->request->getPost('email'));
        $currentEmail = (string) $teacher['email'];
        $emailChanged = strcasecmp($newEmail, $currentEmail) !== 0;

        // Same rules the admin forms use (name regex rejects digits/symbols,
        // TIN/PhilSys/PRC formats, dropdown in-list checks) plus email checks.
        $rules = teacher_store_validation_rules(false);

        if ($emailChanged) {
            $rules['email'] = "required|valid_email|max_length[255]|is_unique[teachers.email,id,{$teacher['id']}]|is_unique[users.email]";
        } else {
            $rules['email'] = 'required|valid_email|max_length[255]';
        }

        if (!$this->validate($rules, teacher_store_validation_messages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = teacher_collect_personnel_from_request($this->request);

        try {
            $db = \Config\Database::connect();
            $db->transStart();

            $teacherModel->skipValidation(true);
            $teacherModel->update($teacher['id'], $data);
            $teacherModel->skipValidation(false);

            // Keep the login account in sync when the email changes, so the
            // teacher can still sign in with the new address.
            if ($emailChanged) {
                $userId = (int) $this->auth->id();

                $db->table('users')
                    ->where('id', $userId)
                    ->update(['email' => $newEmail, 'updated_at' => date('Y-m-d H:i:s')]);

                $db->table('auth_identities')
                    ->where('user_id', $userId)
                    ->where('type', 'email_password')
                    ->update(['name' => $newEmail, 'updated_at' => date('Y-m-d H:i:s')]);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return redirect()->back()->withInput()->with('error', 'Failed to update personnel record.');
            }

            log_message('info', 'Personnel record updated by teacher ID: ' . $teacher['id'] . ($emailChanged ? ' (email changed)' : ''));

            return redirect()->to(base_url('teacher/profile') . '#tab-personnel')
                ->with('success', 'Personnel record updated successfully!');
        } catch (\Throwable $e) {
            log_message('error', 'Personnel record update error: ' . $e->getMessage());

            return redirect()->back()->withInput()->with('error', 'An error occurred while updating the personnel record.');
        }
    }

    public function changePassword()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        $rules = [
            'current_password' => 'required',
            'new_password' => password_validation_rule('new_password'),
            'confirm_password' => 'required|matches[new_password]'
        ];

        if (!$this->validate($rules, password_validation_messages('new_password'))) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        try {
            $db = \Config\Database::connect();
            $userId = $this->auth->id();
            
            // Get current password hash from auth_identities
            $identity = $db->table('auth_identities')
                ->where('user_id', $userId)
                ->where('type', 'email_password')
                ->get()
                ->getRow();
                
            if (!$identity) {
                return redirect()->back()->with('error', 'User authentication record not found.');
            }

            $currentPassword = $this->request->getPost('current_password');
            
            // Verify current password - check both secret and secret2 fields for compatibility
            $passwordValid = false;
            
            // Check secret field first (new format)
            if ($identity->secret && password_verify($currentPassword, $identity->secret)) {
                $passwordValid = true;
            }
            // Check secret2 field (legacy format)
            elseif (isset($identity->secret2) && $identity->secret2 && password_verify($currentPassword, $identity->secret2)) {
                $passwordValid = true;
            }
            // Fallback: check if it's a plain text match (for very old records)
            elseif ($identity->secret && !str_starts_with($identity->secret, '$2y$') && $currentPassword === $identity->secret) {
                $passwordValid = true;
            }
            
            if (!$passwordValid) {
                return redirect()->back()->with('error', 'Current password is incorrect.');
            }

            // Delete ALL existing auth_identities for this user to prevent multiple valid passwords
            $db->table('auth_identities')
                ->where('user_id', $userId)
                ->delete();
            
            // Create new auth identity with the new password. Canonical Shield
            // format: name = email, secret = email, secret2 = hash. This keeps
            // Shield's User::getEmail() returning the real email address.
            $newPasswordHash = password_hash($this->request->getPost('new_password'), PASSWORD_DEFAULT);
            $identityEmail   = user_login_email($this->auth->user()) ?: (string) ($identity->name ?? '');
            $updated = $db->table('auth_identities')->insert([
                'user_id' => $userId,
                'type' => 'email_password',
                'name' => $identityEmail,
                'secret' => $identityEmail,
                'secret2' => $newPasswordHash,
                'expires' => null,
                'extra' => null,
                'force_reset' => 0,
                'last_used_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
                
            if ($updated) {
                audit_event('account.password_changed', [
                    'category'      => 'account',
                    'status'        => 'success',
                    'resource_type' => 'user',
                    'resource_id'   => (string) $userId,
                    'description'   => 'Password changed from the teacher profile page',
                    'metadata'      => ['method' => 'self_service'],
                ]);

                return redirect()->back()->with('success', 'Password changed successfully!');
            } else {
                return redirect()->back()->with('error', 'Failed to change password.');
            }
            
        } catch (\Exception $e) {
            log_message('error', 'Password change error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while changing password.');
        }
    }
}
