<?php
namespace App\Models;

use CodeIgniter\Model;

class PasswordResetRequestModel extends Model
{
    protected $table = 'password_reset_requests';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'user_id',
        'email',
        'token',
        'expires_at',
        'status',
        'used_at',
        'approved_by',
        'admin_notes',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [];
    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Filtered + paginated password reset requests for the admin list page.
     *
     * @param array  $filters Accepted keys: 'status' (pending|approved|rejected|used|expired), 'q' (email / student name / LRN search)
     * @param int    $perPage Rows per page
     * @param int    $page    1-based page number
     *
     * @return array{rows: list<array>, total: int, per_page: int, page: int, total_pages: int}
     */
    public function getFilteredRequestsWithDetails(array $filters = [], int $perPage = 15, int $page = 1): array
    {
        $perPage = max(1, $perPage);
        $page    = max(1, $page);

        $builder = $this->select('password_reset_requests.*, password_reset_requests.email as user_email, students.first_name, students.last_name, students.lrn as student_id')
            ->join('users', 'users.id = password_reset_requests.user_id')
            ->join('students', 'students.user_id = users.id', 'left');

        if (! empty($filters['status'])) {
            $builder->where('password_reset_requests.status', $filters['status']);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $builder->groupStart()
                ->like('password_reset_requests.email', $q)
                ->orLike('students.first_name', $q)
                ->orLike('students.last_name', $q)
                ->orLike('students.lrn', $q)
                ->groupEnd();
        }

        // countAllResults(false) keeps the builder intact for the rows query below.
        $total = (int) $builder->countAllResults(false);

        $rows = $builder
            ->orderBy('password_reset_requests.created_at', 'DESC')
            ->findAll($perPage, ($page - 1) * $perPage);

        return [
            'rows'        => $rows,
            'total'       => $total,
            'per_page'    => $perPage,
            'page'        => $page,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Mark expired requests as expired
     */
    public function markExpiredRequests()
    {
        return $this->where('expires_at <', date('Y-m-d H:i:s'))
            ->where('status', 'pending')
            ->set('status', 'expired')
            ->update();
    }

    /**
     * Approve a password reset request
     */
    public function approveRequest($requestId, $adminId, $notes = null)
    {
        return $this->update($requestId, [
            'status' => 'approved',
            'approved_by' => $adminId,
            'admin_notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Reject a password reset request
     */
    public function rejectRequest($requestId, $adminId, $notes = null)
    {
        return $this->update($requestId, [
            'status' => 'rejected',
            'approved_by' => $adminId,
            'admin_notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}