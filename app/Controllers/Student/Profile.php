<?php
namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Libraries\StudentNutritionClassifier;
use App\Models\StudentModel;
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

        $studentModel = new StudentModel();
        $user = $this->auth->user();
        $student = $studentModel->where('user_id', $user->id)->first();

        if (!$student) {
            return redirect()->to(base_url('student/dashboard'))->with('error', 'Student profile not found.');
        }

        $student = $studentModel->getStudentWithSection((int) $student['id']);

        return view('student/profile', [
            'title' => 'My Profile - CSCS Tap n Track',
            'student' => $student,
        ]);
    }

    public function update()
    {
        if (!$this->auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        // Normalise legacy phone formats before validation and the writes below.
        phone_normalize_request($this->request, ['phone']);

        $rules = [
            'first_name' => 'required|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            // Optional, but when supplied a middle name must be at least two
            // characters (no lone letter) — the same policy the registration
            // form applies. permit_empty also keeps a blank field valid.
            'middle_name' => 'permit_empty|min_length[2]|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'last_name' => 'required|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'email' => 'required|valid_email|max_length[255]',
            'phone' => phone_validation_rule(),
            'address' => 'max_length[500]',
            'height_cm' => 'permit_empty|decimal|greater_than_equal_to[80]|less_than_equal_to[250]',
            'weight_kg' => 'permit_empty|decimal|greater_than_equal_to[15]|less_than_equal_to[200]',
            'ethnicity' => 'permit_empty|max_length[120]',
            'photo' => 'permit_empty|is_image[photo]|max_size[photo,2048]',
            'id_photo' => 'permit_empty|is_image[id_photo]|max_size[id_photo,2048]',
        ];

        if (!$this->validate($rules, phone_validation_messages([
            'phone' => 'Phone Number',
        ]))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $studentModel = new StudentModel();
        $user = $this->auth->user();
        $student = $studentModel->where('user_id', $user->id)->first();
        
        if (!$student) {
            return redirect()->back()->with('error', 'Student not found.');
        }

        $heightRaw = $this->request->getPost('height_cm');
        $weightRaw = $this->request->getPost('weight_kg');
        $ethRaw    = $this->request->getPost('ethnicity');

        $height = ($heightRaw === null || $heightRaw === '') ? null : (float) $heightRaw;
        $weight = ($weightRaw === null || $weightRaw === '') ? null : (float) $weightRaw;
        $eth    = ($ethRaw === null || trim((string) $ethRaw) === '') ? null : trim((string) $ethRaw);

        $nutrition = [
            'height_cm' => $height,
            'weight_kg' => $weight,
            'ethnicity' => $eth,
            'bmi' => null,
            'nutrition_status' => null,
        ];

        if ($height !== null && $weight !== null && $eth !== null) {
            $classified = StudentNutritionClassifier::classify(
                $height,
                $weight,
                $student['gender'] ?? null,
                $student['date_of_birth'] ?? null
            );
            $nutrition['bmi'] = $classified['bmi'];
            $nutrition['nutrition_status'] = $classified['nutrition_status'];
        }

        $data = [
            'first_name' => $this->request->getPost('first_name'),
            'middle_name' => $this->request->getPost('middle_name'),
            'last_name' => $this->request->getPost('last_name'),
            'email' => $this->request->getPost('email'),
            'contact_number' => $this->request->getPost('phone'),
            'address' => $this->request->getPost('address'),
            'height_cm' => $nutrition['height_cm'],
            'weight_kg' => $nutrition['weight_kg'],
            'ethnicity' => $nutrition['ethnicity'],
            'bmi' => $nutrition['bmi'],
            'nutrition_status' => $nutrition['nutrition_status'],
        ];

        // Optional profile photo upload — this photo is what appears on the
        // student's digital ID card (admin ID cards page + student ID card page).
        $photoFile = $this->request->getFile('photo');
        if ($photoFile !== null && $photoFile->isValid() && ! $photoFile->hasMoved()) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (! in_array($photoFile->getMimeType(), $allowedMimes, true)) {
                return redirect()->back()->withInput()->with('error', 'Profile photo must be a JPG, PNG, or WebP image.');
            }

            if ($photoFile->getSizeByUnit('mb') > 2) {
                return redirect()->back()->withInput()->with('error', 'Profile photo must be 2MB or smaller.');
            }

            $photoDir = FCPATH . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student_photos';
            if (! is_dir($photoDir)) {
                mkdir($photoDir, 0775, true);
            }

            $photoName = $photoFile->getRandomName();
            $photoFile->move($photoDir, $photoName);

            // Remove the previous photo so orphaned files don't pile up
            if (! empty($student['photo_path'])) {
                $oldPhoto = FCPATH . 'public' . DIRECTORY_SEPARATOR . 'uploads'
                    . DIRECTORY_SEPARATOR . ltrim((string) $student['photo_path'], '/\\');
                if (is_file($oldPhoto)) {
                    @unlink($oldPhoto);
                }
            }

            $data['photo_path'] = 'student_photos/' . $photoName;
        }

        // Optional 2x2 ID picture - SEPARATE from the circle profile photo;
        // stored in its own directory and shown on the digital ID card.
        $idPhotoFile = $this->request->getFile('id_photo');
        if ($idPhotoFile !== null && $idPhotoFile->isValid() && ! $idPhotoFile->hasMoved()) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (! in_array($idPhotoFile->getMimeType(), $allowedMimes, true)) {
                return redirect()->back()->withInput()->with('error', '2x2 ID picture must be a JPG, PNG, or WebP image.');
            }

            if ($idPhotoFile->getSizeByUnit('mb') > 2) {
                return redirect()->back()->withInput()->with('error', '2x2 ID picture must be 2MB or smaller.');
            }

            $idPhotoDir = FCPATH . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student_id_photos';
            if (! is_dir($idPhotoDir)) {
                mkdir($idPhotoDir, 0775, true);
            }

            $idPhotoName = $idPhotoFile->getRandomName();
            $idPhotoFile->move($idPhotoDir, $idPhotoName);
            if (! empty($student['id_photo_path'])) {
                $oldIdPhoto = FCPATH . 'public' . DIRECTORY_SEPARATOR . 'uploads'
                    . DIRECTORY_SEPARATOR . ltrim((string) $student['id_photo_path'], '/\\');
                if (is_file($oldIdPhoto)) {
                    @unlink($oldIdPhoto);
                }
            }

            $data['id_photo_path'] = 'student_id_photos/' . $idPhotoName;
        }
        $updateResult = $studentModel->update($student['id'], $data);
        
        if ($updateResult) {
            // Also update the user's email in users table if email changed
            if ($data['email'] !== $student['email']) {
                $userModel = new UserModel();
                try {
                    $userModel->update($user->id, ['email' => $data['email']]);
                } catch (\Exception $e) {
                    // Log the error but don't fail the entire update
                    log_message('warning', 'Failed to update user email: ' . $e->getMessage());
                }
            }
            $alreadyComplete = student_profile_complete($student) || student_profile_complete(array_merge($student, $data));
            audit_event('profile.updated', [
                'category'      => 'account',
                'status'        => 'success',
                'resource_type' => 'student',
                'resource_id'   => (string) ($student['id'] ?? ''),
                'description'   => 'Student updated their own profile',
                'metadata'      => ['profile_complete' => (bool) $alreadyComplete],
            ]);

                return redirect()->back()->with('success', $alreadyComplete ? 'Profile complete! Your portal is now fully unlocked.' : 'Profile updated successfully!');
        } else {
            return redirect()->back()->with('error', 'Failed to update profile.');
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

        $currentPassword = $this->request->getPost('current_password');
        $newPassword = $this->request->getPost('new_password');
        
        // Get current user
        $user = $this->auth->user();
        
        // Verify current password using Shield's method
        try {
            $db = \Config\Database::connect();
            
            // Get student record to find user_id
            $studentModel = new StudentModel();
            $student = $studentModel->where('user_id', $user->id)->first();
            
            if (!$student) {
                log_message('error', 'No student record found for user ID: ' . $user->id);
                return redirect()->back()->with('error', 'Student record not found.');
            }
            
            // Check if student has temp_password (demo accounts)
            if (!empty($student['temp_password'])) {
                // For demo accounts, check against temp_password
                if ($currentPassword !== $student['temp_password']) {
                    log_message('warning', 'Temp password verification failed for student: ' . $student['lrn']);
                    return redirect()->back()->with('error', 'Current password is incorrect.');
                }
            } else {
                // For regular accounts, check against auth_identities
                $identity = $db->table('auth_identities')
                    ->where('user_id', $user->id)
                    ->where('type', 'email_password')
                    ->get()
                    ->getRow();
                    
                if (!$identity) {
                    log_message('error', 'No identity found for user ID: ' . $user->id);
                    return redirect()->back()->with('error', 'User authentication record not found.');
                }
                
                // Debug logging
                log_message('info', 'Password verification attempt for user ID: ' . $user->id . ', provided: ' . $currentPassword);
                
                // Check both secret and secret2 fields for compatibility
                $passwordValid = false;
                if (!empty($identity->secret2) && password_verify($currentPassword, $identity->secret2)) {
                    $passwordValid = true;
                } elseif (!empty($identity->secret) && password_verify($currentPassword, $identity->secret)) {
                    $passwordValid = true;
                }
                
                if (!$passwordValid) {
                    log_message('warning', 'Password verification failed for user ID: ' . $user->id);
                    return redirect()->back()->with('error', 'Current password is incorrect.');
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Password verification error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred during password verification.');
        }

        // Update password in auth_identities table and clear temp_password
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update auth_identities table (update both secret and secret2 fields)
            $result = $db->table('auth_identities')
                ->where('user_id', $user->id)
                ->where('type', 'email_password')
                ->update([
                    // Keep the canonical format: secret = email, secret2 = hash.
                    'secret2' => $hashedPassword,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            
            // Clear temp_password from student record if it exists
            if (!empty($student['temp_password'])) {
                $studentModel->update($student['id'], ['temp_password' => null]);
            }

            if ($result) {
                log_message('info', 'Password updated successfully for user ID: ' . $user->id);
                audit_event('account.password_changed', [
                    'category'      => 'account',
                    'status'        => 'success',
                    'resource_type' => 'user',
                    'resource_id'   => (string) $user->id,
                    'description'   => 'Password changed from the student profile page',
                    'metadata'      => ['method' => 'self_service'],
                ]);

                return redirect()->back()->with('success', 'Password changed successfully!');
            } else {
                log_message('error', 'Failed to update password for user ID: ' . $user->id);
                return redirect()->back()->with('error', 'Failed to change password.');
            }
        } catch (\Exception $e) {
            log_message('error', 'Password update error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while updating password.');
        }
    }
}
