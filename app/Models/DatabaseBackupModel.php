<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Registry access for stored database dumps (table `database_backups`).
 *
 * Rows are only ever written by App\Libraries\DatabaseBackupService, which
 * owns the files on disk. This model exposes the small set of queries the
 * backup service and the admin dashboard need.
 */
class DatabaseBackupModel extends Model
{
    protected $table          = 'database_backups';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $protectFields  = true;

    protected $allowedFields = [
        'filename', 'kind', 'size_bytes', 'tables_count', 'checksum_sha256',
        'dump_profile', 'server_version', 'created_by', 'created_by_name',
        'verified_at', 'verify_result', 'restored_at', 'restore_count',
        'notes', 'created_at',
    ];

    protected $validationRules = [
        'filename'        => 'required|max_length[191]',
        'kind'            => 'required|in_list[auto,manual,pre]',
        'checksum_sha256' => 'required|exact_length[64]|alpha_numeric',
        'created_at'      => 'required|valid_date[Y-m-d H:i:s]',
    ];

    /**
     * Newest first, for the admin listing.
     *
     * @return list<array<string, mixed>>
     */
    public function getRecent(int $limit = 200): array
    {
        return $this->builder()
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(max(1, min(1000, $limit)))
            ->get()
            ->getResultArray();
    }

    /**
     * Oldest first — the exact order the rotation has to delete in.
     *
     * @return list<array<string, mixed>>
     */
    public function getOldestFirst(): array
    {
        return $this->builder()
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getNewest(): ?array
    {
        $row = $this->builder()
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    public function getOldest(): ?array
    {
        $row = $this->builder()
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    public function countBackups(): int
    {
        return (int) $this->builder()->countAllResults();
    }

    public function getTotalSize(): int
    {
        $row = $this->builder()->selectSum('size_bytes', 'total')->get()->getRowArray();

        return (int) ($row['total'] ?? 0);
    }

    public function findByFilename(string $filename): ?array
    {
        if ($filename === '') {
            return null;
        }

        $row = $this->builder()->where('filename', $filename)->limit(1)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * The automatic backup created on a given calendar day (Y-m-d), if any.
     */
    public function findAutoBackupForDate(string $date): ?array
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        $row = $this->builder()
            ->where('kind', 'auto')
            ->like('created_at', $date . '%', 'after')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Newest automatic backup, optionally restricted to the ones created at or
     * after `$since` ('Y-m-d H:i:s'). Used by the scheduled command to decide
     * whether the current schedule slot already has its automatic backup.
     */
    public function findLatestAutoBackup(?string $since = null): ?array
    {
        $builder = $this->builder()
            ->where('kind', 'auto')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(1);

        if ($since !== null && $since !== '') {
            $builder->where('created_at >=', $since);
        }

        $row = $builder->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Records the outcome of an integrity check.
     *
     * Returns false when there was no row to update (e.g. a restore replaced the
     * registry while the check was running).
     */
    public function markVerified(int $id, string $result): bool
    {
        $this->builder()->where('id', $id)->update([
            'verified_at'   => date('Y-m-d H:i:s'),
            'verify_result' => $result,
        ]);

        return $this->db->affectedRows() > 0;
    }

    /**
     * Records that a backup was used for a restore.
     *
     * The row may legitimately be gone: a restore replaces the whole database,
     * including this registry, with the contents of the dump.
     */
    public function markRestored(int $id): bool
    {
        $row = $this->builder()->select('restore_count')->where('id', $id)->get()->getRowArray();

        if (! is_array($row)) {
            return false;
        }

        return (bool) $this->builder()->where('id', $id)->update([
            'restored_at'   => date('Y-m-d H:i:s'),
            'restore_count' => (int) ($row['restore_count'] ?? 0) + 1,
        ]);
    }

    /**
     * Removes the registry row (the service removes the files first).
     */
    public function deleteRecord(int $id): bool
    {
        return (bool) $this->builder()->where('id', $id)->delete();
    }
}
