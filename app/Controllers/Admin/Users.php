<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\Shield\Models\UserModel;

/**
 * Admin user management UI was removed; this controller only handles
 * master-only admin staff page permissions (routes under admin/dashboard/…).
 */
class Users extends BaseController
{
    protected $auth;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->auth = auth();
        $this->userModel = new UserModel();
    }

    public function editStaffPermissions($id)
    {
        helper('admin_access');
        if (! is_master_admin()) {
            return redirect()->to(base_url('/'))->with('error', 'Only a master administrator can edit staff page access.');
        }

        $user = $this->userModel->findById((int) $id);
        if (! $user || ! $user->inGroup('admin_staff')) {
            return redirect()->to(base_url('admin/dashboard'))->with('error', 'That account is not admin staff.');
        }

        return view('admin/users_staff_permissions', [
            'title' => 'Staff page access - CSCS Tap n Track',
            'targetUser' => $user,
            'selectedPages' => admin_staff_pages_from_db((int) $id),
        ]);
    }

    public function updateStaffPermissions($id)
    {
        helper('admin_access');
        if (! is_master_admin()) {
            return redirect()->to(base_url('/'))->with('error', 'Only a master administrator can update staff page access.');
        }

        if ($this->request->getMethod() !== 'POST') {
            return redirect()->to(base_url('admin/dashboard'));
        }

        $user = $this->userModel->findById((int) $id);
        if (! $user || ! $user->inGroup('admin_staff')) {
            return redirect()->to(base_url('admin/dashboard'))->with('error', 'That account is not admin staff.');
        }

        $pages = $this->request->getPost('pages');
        $pageList = is_array($pages) ? $pages : [];
        $pageList = array_values(array_intersect(admin_valid_page_keys(), $pageList));
        if ($pageList === []) {
            return redirect()->back()->withInput()->with('error', 'Select at least one page.');
        }

        $previousPages = admin_staff_pages_from_db((int) $id);

        admin_save_allowed_pages((int) $id, $pageList);

        audit_event('role.staff_pages_updated', [
            'category'      => 'role',
            'status'        => 'success',
            'resource_type' => 'user',
            'resource_id'   => (string) $id,
            'description'   => 'Admin staff page access updated',
        ] + audit_diff(
            ['pages' => implode(', ', $previousPages)],
            ['pages' => implode(', ', $pageList)],
            ['pages']
        ) + [
            'metadata' => [
                'target_email' => (string) ($user->email ?? ''),
                'pages_after'  => $pageList,
            ],
        ]);

        return redirect()->to(base_url('admin/dashboard'))->with('success', 'Page access updated.');
    }
    /**
     * Delete one admin staff account (master admins only). Used by the
     * checkbox selection on the Admin Dashboard "Admin staff" card.
     */
    public function deleteStaff($id)
    {
        $result = $this->deleteStaffUser((int) $id);

        return $this->response->setJSON([
            'success' => $result['ok'],
            'message' => $result['message'],
            'error'   => $result['ok'] ? null : $result['message'],
        ]);
    }

    /**
     * Delete several admin staff accounts in one request.
     * Expects JSON: { ids: [id, id, ...] }
     */
    public function deleteStaffBatch()
    {
        $ids = $this->request->getJSON(true)['ids'] ?? [];
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));

        if ($ids === []) {
            return $this->response->setJSON(['success' => false, 'error' => 'No accounts selected.']);
        }

        $deleted = 0;
        $failed  = [];
        foreach ($ids as $id) {
            $result = $this->deleteStaffUser($id);
            if ($result['ok']) {
                $deleted++;
            } else {
                $failed[] = $result['message'];
            }
        }

        if ($deleted === 0) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'No accounts were deleted. ' . implode(' ', $failed),
            ]);
        }

        $message = $deleted . ' admin staff account(s) deleted.';
        if ($failed !== []) {
            $message .= ' ' . count($failed) . ' skipped: ' . implode(' ', $failed);
        }

        return $this->response->setJSON(['success' => true, 'message' => $message]);
    }

    /**
     * Shared deletion for one admin staff account. Soft-deletes the user,
     * removes the admin_staff group row and clears page access. Refuses to
     * touch master admins or the logged-in account.
     */
    private function deleteStaffUser(int $id): array
    {
        helper('admin_access');
        if (! is_master_admin()) {
            return ['ok' => false, 'message' => 'Only a master administrator can delete admin staff accounts.'];
        }

        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Invalid account.'];
        }

        if ((int) ($this->auth->id() ?? 0) === $id) {
            return ['ok' => false, 'message' => 'You cannot delete the account you are signed in with.'];
        }

        $user = $this->userModel->findById($id);
        if (! $user || ! $user->inGroup('admin_staff')) {
            return ['ok' => false, 'message' => 'Account #' . $id . ' is not admin staff.'];
        }

        if ($user->inGroup('admin')) {
            return ['ok' => false, 'message' => 'Account #' . $id . ' is a master administrator and cannot be deleted here.'];
        }

        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $removedSnapshot = [
            'email' => (string) ($user->email ?? ''),
            'pages' => implode(', ', admin_staff_pages_from_db($id)),
        ];

        $db->table('auth_groups_users')
            ->where('user_id', $id)
            ->where('group', 'admin_staff')
            ->delete();

        $db->table('users')->where('id', $id)->update([
            'deleted_at'          => $now,
            'admin_allowed_pages' => null,
            'active'              => 0,
            'updated_at'          => $now,
        ]);

        log_message('info', 'Admin staff account deleted: user #' . $id . ' by user #' . ($this->auth->id() ?? '?'));

        audit_event('role.staff_deleted', [
            'category'      => 'role',
            'status'        => 'success',
            'resource_type' => 'user',
            'resource_id'   => (string) $id,
            'description'   => 'Admin staff account deleted',
            'before'        => $removedSnapshot,
            'metadata'      => ['target_email' => $removedSnapshot['email']],
        ]);

        return ['ok' => true, 'message' => 'Deleted.'];
    }
}

