<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Profile extends BaseController
{
    public function index()
    {
        if (!auth()->loggedIn() || ! is_any_admin()) {
            return redirect()->to(base_url('login'));
        }

        $user = auth()->user();
        $db = \Config\Database::connect();
        
        // Get fresh user data from database
        $userData = $db->table('users')->where('id', $user->id)->get()->getRow();
        
        return view('admin/profile', [
            'title' => 'My Profile - CSCS Tap n Track',
            'admin' => [
                'id' => $userData->id,
                'email' => $userData->email,
                'first_name' => $userData->first_name ?? '',
                'last_name' => $userData->last_name ?? ''
            ]
        ]);
    }

    public function update()
    {
        if (!auth()->loggedIn() || ! is_any_admin()) {
            return redirect()->to(base_url('login'));
        }

        $rules = [
            'first_name' => 'required|max_length[100]',
            'last_name' => 'required|max_length[100]',
            'email' => 'required|valid_email|max_length[255]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Please fill in all required fields correctly.');
        }

        $user = auth()->user();
        $db = \Config\Database::connect();

        $before = (array) $db->table('users')
            ->select('first_name, last_name, email')
            ->where('id', $user->id)
            ->get()
            ->getRowArray();

        $data = [
            'first_name' => $this->request->getPost('first_name'),
            'last_name' => $this->request->getPost('last_name'),
            'email' => $this->request->getPost('email'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($db->table('users')->where('id', $user->id)->update($data)) {
            audit_event('profile.updated', [
                'category'    => 'account',
                'status'      => 'success',
                'resource_type' => 'user',
                'resource_id' => (string) $user->id,
                'description' => 'Admin profile details updated',
            ] + audit_diff($before, [
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'],
            ], ['first_name', 'last_name', 'email']));

            return redirect()->to(base_url('admin/profile'))->with('success', 'Profile updated successfully.');
        }

        return redirect()->back()->with('error', 'Failed to update profile.');
    }

    public function changePassword()
    {
        if (!auth()->loggedIn() || ! is_any_admin()) {
            return redirect()->to(base_url('login'));
        }

        $currentPassword = $this->request->getPost('current_password');
        $newPassword = $this->request->getPost('new_password');
        $confirmPassword = $this->request->getPost('confirm_password');

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            return redirect()->back()->with('error', 'All password fields are required.');
        }

        if ($newPassword !== $confirmPassword) {
            return redirect()->back()->with('error', 'New passwords do not match.');
        }

        $policyError = password_meets_policy($newPassword);
        if ($policyError !== null) {
            return redirect()->back()->with('error', $policyError);
        }

        $user = auth()->user();
        $db = \Config\Database::connect();

        $identity = $db->table('auth_identities')
            ->where('user_id', $user->id)
            ->where('type', 'email_password')
            ->get()->getRow();

        if (!$identity) {
            return redirect()->back()->with('error', 'Authentication identity not found.');
        }

        $currentPassword = trim($currentPassword);

        // Verify the current password exactly like the login page does
        // (verify_auth_identity_password checks the canonical hash in secret2
        // AND any legacy hash sitting in secret).
        if (! verify_auth_identity_password($currentPassword, $identity)) {
            audit_event('account.password_change_failed', [
                'category'      => 'account',
                'status'        => 'failure',
                'resource_type' => 'user',
                'resource_id'   => (string) $user->id,
                'description'   => 'Password change rejected: current password incorrect',
            ]);

            return redirect()->back()->with('error', 'Current password is incorrect.');
        }

        // The email always comes from the users table — never from
        // identity->secret, which in legacy rows may hold a password hash
        // instead of an email address.
        $userRow = $db->table('users')->select('email')->where('id', $user->id)->get()->getRow();
        $email = trim((string) ($userRow->email ?? ''));

        if ($email === '') {
            return redirect()->back()->with('error', 'Account email not found. Please contact support.');
        }

        // Save in the canonical layout (name/secret = email, secret2 = hash).
        // Critically, this REPLACES the whole identity row — wiping any legacy
        // password hash from secret, which is what kept an OLD password
        // working after a change. sync_auth_password() is the same writer the
        // registration and demo-account flows use.
        if (! sync_auth_password((int) $user->id, $email, $newPassword)) {
            return redirect()->back()->with('error', 'Failed to change password. Please try again.');
        }

        log_message('info', 'Admin profile password changed for user ID: ' . $user->id);

        audit_event('account.password_changed', [
            'category'      => 'account',
            'status'        => 'success',
            'resource_type' => 'user',
            'resource_id'   => (string) $user->id,
            'description'   => 'Password changed from the admin profile page',
            'metadata'      => ['method' => 'self_service'],
        ]);

        return redirect()->to(base_url('admin/profile'))->with('success', 'Password changed successfully. Use your new password the next time you sign in.');
    }
}

