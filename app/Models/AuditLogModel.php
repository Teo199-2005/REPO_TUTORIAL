<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Read-side access to the append-only `audit_logs` table.
 *
 * Writes go through App\Libraries\AuditLogger (which owns the hash chain).
 * This model is deliberately append-only: update()/delete() refuse to run so
 * no controller can silently rewrite history. The only removal path is
 * purgeOlderThan(), used by `php spark audit:prune`.
 */
class AuditLogModel extends Model
{
    protected $table          = 'audit_logs';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $protectFields  = true;

    protected $allowedFields = [
        'request_id', 'actor_user_id', 'actor_name', 'actor_role', 'action',
        'category', 'status', 'resource_type', 'resource_id', 'description',
        'ip_address', 'user_agent', 'http_method', 'route',
        'before_json', 'after_json', 'metadata_json',
        'prev_hash', 'hash', 'created_at',
    ];

    protected $validationRules = [
        'action'     => 'required|max_length[100]',
        'category'   => 'required|in_list[auth,account,role,data,settings,system]',
        'status'     => 'required|in_list[success,failure,blocked]',
        'created_at' => 'required|valid_date[Y-m-d H:i:s]',
    ];

    /** Sortable columns offered by the admin viewer. */
    public const SORTABLE = ['created_at', 'action', 'category', 'status', 'actor_name', 'resource_type'];

    /**
     * Append-only guard: updates are never allowed.
     */
    public function update($id = null, $row = null): bool
    {
        log_message('critical', 'Attempt to update an audit log record was blocked.');

        return false;
    }

    /**
     * Append-only guard: deletes are never allowed. Retention uses
     * purgeOlderThan(), which bypasses this on purpose.
     */
    public function delete($id = null, bool $purge = false): bool
    {
        log_message('critical', 'Attempt to delete an audit log record was blocked.');

        return false;
    }

    public function updateBatch(?array $set = null, ?string $index = null, int $batchSize = 100, bool $returnSQL = false): bool
    {
        log_message('critical', 'Attempt to batch-update audit log records was blocked.');

        return false;
    }

    /**
     * Filtered + paginated rows for the admin viewer.
     *
     * @param array{q?:string,action?:string,category?:string,status?:string,resource_type?:string,actor?:int,actor_ids?:list<int>,date_from?:string,date_to?:string,sort?:string,dir?:string} $filters
     *
     * @return array{rows:list<array>,total:int,per_page:int,page:int,total_pages:int}
     */
    public function getFilteredLogs(array $filters = [], int $perPage = 50, int $page = 1): array
    {
        $perPage = max(1, min(200, $perPage));
        $page    = max(1, $page);

        $builder = $this->select('*');

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $builder->groupStart()
                ->like('description', $q)
                ->orLike('actor_name', $q)
                ->orLike('resource_id', $q)
                ->orLike('action', $q)
                ->orLike('route', $q)
                ->orLike('ip_address', $q);

            // Legacy rows may store a placeholder as actor_name; the viewer
            // passes the ids whose resolved display name matches the query.
            $actorIds = array_values(array_unique(array_filter(
                array_map('intval', (array) ($filters['actor_ids'] ?? [])),
                static fn (int $id): bool => $id > 0
            )));

            if ($actorIds !== []) {
                $builder->orWhereIn('actor_user_id', $actorIds);
            }

            $builder->groupEnd();
        }

        foreach (['action', 'category', 'status', 'resource_type'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $builder->where($field, $value);
            }
        }

        if (! empty($filters['actor'])) {
            $builder->where('actor_user_id', (int) $filters['actor']);
        }

        if (! empty($filters['date_from'])) {
            $builder->where('created_at >=', $filters['date_from'] . ' 00:00:00');
        }

        if (! empty($filters['date_to'])) {
            $builder->where('created_at <=', $filters['date_to'] . ' 23:59:59');
        }

        $total = (int) $builder->countAllResults(false);

        $sort = in_array((string) ($filters['sort'] ?? ''), self::SORTABLE, true) ? (string) $filters['sort'] : 'created_at';
        $dir  = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $builder->orderBy($sort, $dir);
        if ($sort !== 'id') {
            $builder->orderBy('id', $dir);
        }

        $rows = $builder->findAll($perPage, ($page - 1) * $perPage);

        return [
            'rows'        => $rows,
            'total'       => $total,
            'per_page'    => $perPage,
            'page'        => $page,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Distinct action names for the filter dropdown.
     *
     * @return list<string>
     */
    public function getDistinctActions(int $limit = 300): array
    {
        $rows = $this->builder()
            ->select('action')
            ->distinct()
            ->orderBy('action', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): string => (string) $row['action'], $rows);
    }

    /**
     * Distinct resource types for the filter dropdown.
     *
     * @return list<string>
     */
    public function getDistinctResourceTypes(int $limit = 100): array
    {
        $rows = $this->builder()
            ->select('resource_type')
            ->distinct()
            ->where('resource_type IS NOT NULL')
            ->orderBy('resource_type', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_values(array_map(static fn (array $row): string => (string) $row['resource_type'], $rows));
    }

    /**
     * Distinct actor ids that have entries (for the actor filter dropdown).
     *
     * @return list<array{actor_user_id:int,actor_name:string,entries:int}>
     */
    public function getActors(int $limit = 200): array
    {
        $rows = $this->builder()
            ->select('actor_user_id, MAX(actor_name) AS actor_name, COUNT(*) AS entries')
            ->where('actor_user_id IS NOT NULL')
            ->groupBy('actor_user_id')
            ->orderBy('entries', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): array => [
            'actor_user_id' => (int) $row['actor_user_id'],
            'actor_name'    => (string) ($row['actor_name'] ?? ''),
            'entries'       => (int) $row['entries'],
        ], $rows);
    }

    /**
     * Headline counters for the viewer (today + last 24 hours actors).
     *
     * @return array{total:int,failures:int,actors:int}
     */
    public function getTodaySummary(): array
    {
        $since24h      = date('Y-m-d H:i:s', time() - 86400);
        $sinceMidnight = date('Y-m-d 00:00:00');

        $total    = (int) $this->builder()->where('created_at >=', $sinceMidnight)->countAllResults();
        $failures = (int) $this->builder()
            ->where('created_at >=', $sinceMidnight)
            ->where('status !=', 'success')
            ->countAllResults();
        $actors   = (int) $this->builder()
            ->select('actor_user_id')
            ->distinct()
            ->where('created_at >=', $since24h)
            ->where('actor_user_id IS NOT NULL')
            ->countAllResults();

        return ['total' => $total, 'failures' => $failures, 'actors' => $actors];
    }

    /**
     * Oldest entry timestamp (retention display).
     */
    public function getOldestTimestamp(): ?string
    {
        $row = $this->builder()->selectMin('created_at', 'oldest')->get()->getRowArray();

        return isset($row['oldest']) && $row['oldest'] !== null ? (string) $row['oldest'] : null;
    }

    /**
     * Retention purge — the ONLY delete path. Removes the oldest prefix so the
     * remaining rows still form a verifiable chain segment.
     *
     * @return int Number of deleted rows
     */
    public function purgeOlderThan(string $cutoffDateTime): int
    {
        $db = $this->db;

        $db->table('audit_logs')->where('created_at <', $cutoffDateTime)->delete();

        return (int) $db->affectedRows();
    }
}
