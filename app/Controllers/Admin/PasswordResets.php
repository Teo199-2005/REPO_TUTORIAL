<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\PasswordResetRequestModel;
use CodeIgniter\Shield\Models\UserModel;

class PasswordResets extends BaseController
{
    protected $resetRequestModel;
    protected $userModel;

    public function __construct()
    {
        $this->resetRequestModel = model(PasswordResetRequestModel::class);
        $this->userModel = model(UserModel::class);
    }

    /**
     * Display password reset requests
     */
    public function index()
    {
        $status   = trim((string) ($this->request->getGet('status') ?? ''));
        if (! in_array($status, ['pending', 'approved', 'rejected', 'used', 'expired'], true)) {
            $status = '';
          }
        $q        = trim((string) ($this->request->getGet('q') ?? ''));
        $perPage  = (int) ($this->request->getGet('per_page') ?? 15);
        if (! in_array($perPage, [15, 25, 50], true)) {
            $perPage = 15;
        }
        $page     = max(1, (int) ($this->request->getGet('page') ?? 1));

        try {
            $this->resetRequestModel->markExpiredRequests();

            $result   = $this->resetRequestModel->getFilteredRequestsWithDetails(
                ['status' => $status, 'q' => $q],
                $perPage,
                $page
            );
            $requests    = $result['rows'];
            $total       = $result['total'];
            $totalPages  = $result['total_pages'];
            $page        = min($page, $totalPages);
        } catch (\Exception $e) {
            $requests   = [];
            $total      = 0;
            $totalPages = 1;
            $page       = 1;
        }

        return view('admin/password_resets', [
            'title'        => 'Password Reset Requests - CSCS Tap n Track',
            'requests'     => $requests,
            'table_missing' => false,
            'filter_status' => $status,
            'filter_q'      => $q,
            'per_page'      => $perPage,
            'current_page'  => $page,
            'total'         => $total,
            'total_pages'   => $totalPages,
            'showing_from'  => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
            'showing_to'    => min($page * $perPage, $total),
        ]);
    }

    /**
     * Approve a password reset request
     */
    public function approve($requestId)
    {
        // Check if user is admin
        try {
            if (!auth()->loggedIn() || ! is_any_admin()) {
                return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
            }
        } catch (\Exception $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $notes = $this->request->getPost('notes');
        $adminId = auth()->user()->id;

        // Get the request details first
        $request = $this->resetRequestModel
            ->select('password_reset_requests.*, students.id as student_id')
            ->join('users', 'users.id = password_reset_requests.user_id')
            ->join('students', 'students.user_id = users.id', 'left')
            ->find($requestId);

        if (!$request) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Request not found']);
        }

        // A pending, rejected, inactive or deleted teacher must not be able to
        // regain a working password through the reset workflow. Reject the
        // stale request instead of approving it.
        $targetUser = model(\CodeIgniter\Shield\Models\UserModel::class)->find((int) ($request['user_id'] ?? 0));

        if ($targetUser && teacher_account_claims_teacher_role($targetUser)) {
            helper('teacher_access');

            $teacherState = teacher_record_state(find_teacher_record_for_user($targetUser));

            if ($teacherState !== 'approved') {
                log_message('info', 'Password reset approval blocked - teacher record state "' . $teacherState . '" for user ID: ' . $request['user_id']);

                $this->resetRequestModel->rejectRequest($requestId, $adminId, 'Auto-rejected: teacher account is no longer approved.');

                return $this->response->setJSON([
                    'success'   => false,
                    'error'     => 'This request belongs to a teacher account that is no longer approved. It was rejected.',
                    'csrf_hash' => csrf_hash(),
                ]);
            }
        }

        $success = $this->resetRequestModel->approveRequest($requestId, $adminId, $notes);

        if ($success) {
            audit_event('account.password_reset_approved', [
                'category'      => 'account',
                'status'        => 'success',
                'resource_type' => 'password_reset_request',
                'resource_id'   => (string) $requestId,
                'description'   => 'Password reset request approved',
                'metadata'      => ['target_user_id' => (int) ($request['user_id'] ?? 0), 'has_notes' => $notes !== null && $notes !== ''],
            ]);

            return $this->response->setJSON([
                'success' => true,
                'redirect' => base_url("admin/password-resets/change/{$requestId}"),
                'message' => 'Password reset request approved. Redirecting to change password...',
                'csrf_hash' => csrf_hash()
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON([
            'error' => 'Failed to approve password reset request.',
            'csrf_hash' => csrf_hash()
        ]);
    }

    /**
     * Reject a password reset request
     */
    public function reject($requestId)
    {
        // Check if user is admin
        try {
            if (!auth()->loggedIn() || ! is_any_admin()) {
                return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
            }
        } catch (\Exception $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $notes = $this->request->getPost('notes');
        $adminId = auth()->user()->id;

        $success = $this->resetRequestModel->rejectRequest($requestId, $adminId, $notes);

        if ($success) {
            audit_event('account.password_reset_rejected', [
                'category'      => 'account',
                'status'        => 'success',
                'resource_type' => 'password_reset_request',
                'resource_id'   => (string) $requestId,
                'description'   => 'Password reset request rejected',
                'metadata'      => ['has_notes' => $notes !== null && $notes !== ''],
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Password reset request rejected.',
                'csrf_hash' => csrf_hash()
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON([
            'error' => 'Failed to reject password reset request.',
            'csrf_hash' => csrf_hash()
        ]);
    }

    /**
     * Get request details for modal
     */
    public function getRequestDetails($requestId)
    {
        // Check if user is admin
        try {
            if (!auth()->loggedIn() || ! is_any_admin()) {
                return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
            }
        } catch (\Exception $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $request = $this->resetRequestModel
            ->select('password_reset_requests.*, users.email as user_email, students.first_name, students.last_name, students.student_id, students.contact_number, admin_users.email as approved_by_email')
            ->join('users', 'users.id = password_reset_requests.user_id')
            ->join('students', 'students.user_id = users.id', 'left')
            ->join('users as admin_users', 'admin_users.id = password_reset_requests.approved_by', 'left')
            ->find($requestId);

        if (!$request) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Request not found']);
        }

        return $this->response->setJSON($request);
    }

    /**
     * Generate reset link for approved request
     */
    public function generateResetLink($requestId)
    {
        // Check if user is admin
        try {
            if (!auth()->loggedIn() || ! is_any_admin()) {
                return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
            }
        } catch (\Exception $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $request = $this->resetRequestModel->find($requestId);

        if (!$request || $request['status'] !== 'approved') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Request not found or not approved']);
        }

        $resetLink = base_url('reset-password/' . $request['token']);

        return $this->response->setJSON([
            'success' => true,
            'reset_link' => $resetLink,
            'expires_at' => $request['expires_at']
        ]);
    }

    /**
     * Get count of pending password reset requests
     */
    public function getCount()
    {
        // Check if user is admin
        try {
            if (!auth()->loggedIn() || ! is_any_admin()) {
                return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
            }
        } catch (\Exception $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        try {
            // Mark expired requests first
            $this->resetRequestModel->markExpiredRequests();

            // Get count of pending requests
            $count = $this->resetRequestModel
                ->where('status', 'pending')
                ->where('expires_at >', date('Y-m-d H:i:s'))
                ->countAllResults();

            return $this->response->setJSON(['count' => $count]);
        } catch (\Exception $e) {
            // If table doesn't exist, return 0 count
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                return $this->response->setJSON(['count' => 0]);
            }
            throw $e;
        }
    }

    /**
     * Approve all pending password reset requests
     */
    public function approveAll()
    {
        // Check if user is admin
        try {
            if (!auth()->loggedIn() || ! is_any_admin()) {
                return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
            }
        } catch (\Exception $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $adminId = auth()->user()->id;
        
        $success = $this->resetRequestModel
            ->where('status', 'pending')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->set([
                'status' => 'approved',
                'approved_by' => $adminId,
                'admin_notes' => 'Bulk approved by admin',
                'updated_at' => date('Y-m-d H:i:s')
            ])
            ->update();

        if ($success) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'All pending requests approved successfully.',
                'csrf_hash' => csrf_hash()
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON([
            'error' => 'Failed to approve all requests.',
            'csrf_hash' => csrf_hash()
        ]);
    }

    /**
     * Reject all pending password reset requests
     */
    public function rejectAll()
    {
        // Check if user is admin
        try {
            if (!auth()->loggedIn() || ! is_any_admin()) {
                return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
            }
        } catch (\Exception $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $adminId = auth()->user()->id;
        
        $success = $this->resetRequestModel
            ->where('status', 'pending')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->set([
                'status' => 'rejected',
                'approved_by' => $adminId,
                'admin_notes' => 'Bulk rejected by admin',
                'updated_at' => date('Y-m-d H:i:s')
            ])
            ->update();

        if ($success) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'All pending requests rejected successfully.',
                'csrf_hash' => csrf_hash()
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON([
            'error' => 'Failed to reject all requests.',
            'csrf_hash' => csrf_hash()
        ]);
    }

    /**
     * Delete a password reset request
     */
    public function delete($requestId)
    {
        // Check if user is admin
        try {
            if (!auth()->loggedIn() || ! is_any_admin()) {
                return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
            }
        } catch (\Exception $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $success = $this->resetRequestModel->delete($requestId);

        if ($success) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Password reset request deleted successfully.',
                'csrf_hash' => csrf_hash()
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON([
            'error' => 'Failed to delete password reset request.',
            'csrf_hash' => csrf_hash()
        ]);
    }

    /**
     * Show password change page for approved request
     */
    public function change($requestId)
    {
        $db = \Config\Database::connect();
        $request = $db->table('password_reset_requests')
            ->where('id', $requestId)
            ->get()
            ->getRowArray();
        
        if (!$request) {
            return redirect()->to('admin/password-resets')->with('error', 'Request not found');
        }
        
        return view('admin/password_reset_change', [
            'title' => 'Change Password - CSCS Tap n Track',
            'reset' => $request
        ]);
    }

    /**
     * Process password change
     */
    public function changePassword()
    {
        $resetId = $this->request->getPost('reset_id');
        $newPassword = $this->request->getPost('new_password');
        $confirmPassword = $this->request->getPost('confirm_password');

        if (!$resetId || !$newPassword || !$confirmPassword) {
            return redirect()->back()->with('error', 'All fields are required.');
        }

        if ($newPassword !== $confirmPassword) {
            return redirect()->back()->with('error', 'Passwords do not match.');
        }

        // Shared password policy: length AND at least one digit.
        $policyError = password_meets_policy($newPassword);
        if ($policyError !== null) {
            return redirect()->back()->with('error', $policyError);
        }

        $request = $this->resetRequestModel->find($resetId);
        if (!$request) {
            return redirect()->back()->with('error', 'Reset request not found.');
        }

        // Defense in depth: never write a usable password for a teacher whose
        // record is pending, rejected, inactive or deleted, even if the request
        // slipped past approval.
        $targetUser = model(\CodeIgniter\Shield\Models\UserModel::class)->find((int) ($request['user_id'] ?? 0));

        if ($targetUser && teacher_account_claims_teacher_role($targetUser)) {
            helper('teacher_access');

            $teacherState = teacher_record_state(find_teacher_record_for_user($targetUser));

            if ($teacherState !== 'approved') {
                log_message('info', 'Password change blocked - teacher record state "' . $teacherState . '" for user ID: ' . $request['user_id']);

                return redirect()->back()->with('error', teacher_account_denial_message($teacherState));
            }
        }

        // Update password in auth_identities table
        $db = \Config\Database::connect();
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Delete ALL existing auth_identities for this user (all types)
        $db->table('auth_identities')
            ->where('user_id', $request['user_id'])
            ->delete();
        
        // Also delete from auth_tokens and auth_remember_tokens if they exist
        try {
            $db->table('auth_tokens')->where('user_id', $request['user_id'])->delete();
            $db->table('auth_remember_tokens')->where('user_id', $request['user_id'])->delete();
        } catch (\Exception $e) {
            // Tables might not exist, ignore
        }
        
        // Get clean email without mailto: prefixes
        $cleanEmail = str_replace('mailto:', '', $request['email']);
        
        // Create new auth identity with the new password
        $updated = $db->table('auth_identities')->insert([
            'user_id' => $request['user_id'],
            'type' => 'email_password',
            'name' => $cleanEmail,
            'secret' => $cleanEmail,
            'secret2' => $hashedPassword,
            'expires' => null,
            'extra' => null,
            'force_reset' => 0,
            'last_used_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($updated) {
            // Mark request as used
            $this->resetRequestModel->update($resetId, ['status' => 'used']);

            audit_event('account.password_reset_by_admin', [
                'category'      => 'account',
                'status'        => 'success',
                'resource_type' => 'user',
                'resource_id'   => (string) ($request['user_id'] ?? ''),
                'description'   => 'Password set by an administrator from a reset request',
                'metadata'      => ['request_id' => (int) $resetId, 'method' => 'password_reset_request'],
            ]);

            return redirect()->to('admin/password-resets')->with('success', 'Password changed successfully!');
        }
        
        return redirect()->back()->with('error', 'Failed to update password.');
    }
}

