<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AuditLogger;
use App\Models\AuditLogModel;
use Throwable;

/**
 * Admin viewer for the append-only activity/audit log.
 *
 * Access model (defense in depth):
 *  1. the /admin route group is protected by the `adminaccess` filter;
 *  2. admin staff additionally need the `audit_log` page granted by a master
 *     admin (checked again here, because a filter misconfiguration must not
 *     expose the trail);
 *  3. integrity verification is restricted to master admins.
 *
 * The page is read-only by design: no route can edit or delete entries.
 */
class AuditLog extends BaseController
{
    private const CATEGORY_LABELS = [
        'auth'     => 'Authentication',
        'account'  => 'Account',
        'role'     => 'Roles & permissions',
        'data'     => 'Records',
        'settings' => 'Settings',
        'system'   => 'System',
    ];

    private const PER_PAGE_CHOICES = [25, 50, 100, 200];
    private const EXPORT_ROW_LIMIT = 5000;

    protected AuditLogModel $logModel;

    /**
     * Named $auditLogger (not $logger) because CodeIgniter\Controller already
     * declares an untyped $logger property that must not be redeclared.
     */
    protected AuditLogger $auditLogger;

    public function __construct()
    {
        $this->logModel    = model(AuditLogModel::class);
        $this->auditLogger = service('auditLogger');
    }

    public function index()
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $filters = $this->withActorSearchIds($this->collectFilters());
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 50);
        if (! in_array($perPage, self::PER_PAGE_CHOICES, true)) {
            $perPage = 50;
        }

        try {
            $result = $this->logModel->getFilteredLogs($filters, $perPage, $page);
        } catch (Throwable $e) {
            log_message('error', 'Activity log query failed: ' . $e->getMessage());
            $result = ['rows' => [], 'total' => 0, 'per_page' => $perPage, 'page' => 1, 'total_pages' => 1];
        }

        $page = min($page, $result['total_pages']);

        try {
            $summary = $this->logModel->getTodaySummary();
            $actions = $this->logModel->getDistinctActions();
            $actors  = $this->logModel->getActors();
            $types   = $this->logModel->getDistinctResourceTypes();
            $oldest  = $this->logModel->getOldestTimestamp();
        } catch (Throwable $e) {
            $summary = ['total' => 0, 'failures' => 0, 'actors' => 0];
            $actions = [];
            $actors  = [];
            $types   = [];
            $oldest  = null;
        }

        // Legacy rows may hold a secret-like placeholder as the actor (the
        // Shield identity secret). Resolve real names for display only — the
        // stored rows are never modified (append-only + hash-chained log).
        $result['rows'] = audit_resolve_actor_names($result['rows']);
        $actors         = audit_resolve_actor_names($actors);

        return view('admin/audit_log', [
            'title'            => 'Activity log - CSCS Tap n Track',
            'rows'             => $result['rows'],
            'total'            => $result['total'],
            'per_page'         => $result['per_page'],
            'current_page'     => $page,
            'total_pages'      => $result['total_pages'],
            'showing_from'     => $result['total'] > 0 ? ($page - 1) * $result['per_page'] + 1 : 0,
            'showing_to'       => min($page * $result['per_page'], $result['total']),
            'filters'          => $filters,
            'actions'          => $actions,
            'actors'           => $actors,
            'resource_types'   => $types,
            'summary'          => $summary,
            'oldest_entry'     => $oldest,
            'retention_days'   => audit_retention_days(),
            'category_labels'  => self::CATEGORY_LABELS,
            'per_page_choices' => self::PER_PAGE_CHOICES,
            'is_master_admin'  => is_master_admin(),
        ]);
    }

    /**
     * Detail view for one event (server-rendered; all output escaped there).
     */
    public function show($id = null)
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $id = (int) $id;
        if ($id <= 0) {
            return redirect()->to(base_url('admin/audit-log'))->with('error', 'Invalid activity log entry.');
        }

        try {
            $row = $this->logModel->find($id);
        } catch (Throwable $e) {
            $row = null;
        }

        if (! is_array($row)) {
            return redirect()->to(base_url('admin/audit-log'))->with('error', 'Activity log entry not found.');
        }

        $row = audit_resolve_actor_names([$row])[0] ?? $row;

        return view('admin/audit_log_detail', [
            'title'           => 'Activity #' . $id . ' - CSCS Tap n Track',
            'entry'           => $row,
            'before'          => $this->decodeJson($row['before_json'] ?? null),
            'after'           => $this->decodeJson($row['after_json'] ?? null),
            'metadata'        => $this->decodeJson($row['metadata_json'] ?? null),
            'category_labels' => self::CATEGORY_LABELS,
            'filters'         => $this->collectFilters(),
        ]);
    }

    /**
     * CSV export of the current filter selection (capped, formula-safe).
     */
    public function export()
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $filters = $this->withActorSearchIds($this->collectFilters());

        try {
            $result = $this->logModel->getFilteredLogs($filters, self::EXPORT_ROW_LIMIT, 1);
            $rows   = audit_resolve_actor_names($result['rows']);
        } catch (Throwable $e) {
            log_message('error', 'Activity log export failed: ' . $e->getMessage());

            return redirect()->to(base_url('admin/audit-log'))->with('error', 'Export failed.');
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return redirect()->to(base_url('admin/audit-log'))->with('error', 'Export failed.');
        }

        fputcsv($handle, [
            'ID', 'Timestamp', 'Actor', 'Actor ID', 'Role', 'Action', 'Category',
            'Status', 'Resource type', 'Resource ID', 'Description',
            'IP address', 'Method', 'Path', 'Request ID',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, array_map([$this, 'csvCell'], [
                $row['id'] ?? '',
                $row['created_at'] ?? '',
                $row['actor_name'] ?? 'Anonymous',
                $row['actor_user_id'] ?? '',
                $row['actor_role'] ?? '',
                $row['action'] ?? '',
                $row['category'] ?? '',
                $row['status'] ?? '',
                $row['resource_type'] ?? '',
                $row['resource_id'] ?? '',
                $row['description'] ?? '',
                $row['ip_address'] ?? '',
                $row['http_method'] ?? '',
                $row['route'] ?? '',
                $row['request_id'] ?? '',
            ]));
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        audit_event('system.audit_exported', [
            'category'    => 'system',
            'status'      => 'success',
            'description' => 'Exported ' . count($rows) . ' activity log row(s) to CSV',
            'metadata'    => [
                'rows'    => count($rows),
                // actor_ids is an internal helper list, not part of the query.
                'filters' => array_diff_key($filters, ['actor_ids' => true]),
            ],
        ]);

        $filename = 'activity-log-' . date('Ymd-His') . '.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody("\xEF\xBB\xBF" . $csv);
    }

    /**
     * Recompute the whole hash chain and report tampering (master admin only).
     */
    public function verify()
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        if (! is_master_admin()) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON(['success' => false, 'error' => 'Only a master administrator can verify the audit log.']);
            }

            return redirect()->to(base_url('admin/audit-log'))->with('error', 'Only a master administrator can verify the audit log.');
        }

        if (strtoupper((string) $this->request->getMethod()) !== 'POST') {
            return redirect()->to(base_url('admin/audit-log'));
        }

        $result = $this->auditLogger->verifyChain();

        audit_event('system.audit_verified', [
            'category'    => 'system',
            'status'      => $result['valid'] ? 'success' : 'failure',
            'description' => 'Audit log integrity check: ' . $result['message'],
            'metadata'    => [
                'checked'    => $result['checked'],
                'valid'      => $result['valid'],
                'broken_ids' => $result['broken_ids'],
            ],
        ]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true] + $result);
        }

        return redirect()->to(base_url('admin/audit-log'))
            ->with($result['valid'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Server-side authorization for every action of this controller.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|null Null when allowed.
     */
    private function guard()
    {
        helper('admin_access');

        try {
            if (! auth()->loggedIn() || ! is_any_admin()) {
                return redirect()->to(base_url('login'));
            }

            $user = auth()->user();
            if ($user === null || ! admin_staff_can_view_page($user, 'audit_log')) {
                return redirect()->to(base_url('admin/dashboard'))->with('error', 'You do not have access to the activity log.');
            }
        } catch (Throwable $e) {
            return redirect()->to(base_url('login'));
        }

        return null;
    }

    /**
     * Attach the actor ids whose resolved display name matches the free-text
     * search, so legacy rows (whose stored actor_name is a placeholder) are
     * still findable by the actor's name.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function withActorSearchIds(array $filters): array
    {
        $filters['actor_ids'] = ($filters['q'] ?? '') !== '' ? audit_actor_ids_matching((string) $filters['q']) : [];

        return $filters;
    }

    /**
     * Validated filter set. Every value is bounded and whitelisted so it can
     * be echoed back into the form and used safely by the query builder.
     *
     * @return array<string, mixed>
     */
    private function collectFilters(): array
    {
        $raw = $this->request->getGet();

        $filters = [
            'q'             => mb_substr(trim((string) ($raw['q'] ?? '')), 0, 191),
            'action'        => $this->tokenOrEmpty($raw['action'] ?? null, 100),
            'category'      => $this->tokenOrEmpty($raw['category'] ?? null, 32),
            'status'        => $this->tokenOrEmpty($raw['status'] ?? null, 16),
            'resource_type' => $this->tokenOrEmpty($raw['resource_type'] ?? null, 50),
            'actor'         => max(0, (int) ($raw['actor'] ?? 0)),
            'date_from'     => $this->dateOrEmpty($raw['date_from'] ?? null),
            'date_to'       => $this->dateOrEmpty($raw['date_to'] ?? null),
            'sort'          => in_array($raw['sort'] ?? null, AuditLogModel::SORTABLE, true) ? (string) $raw['sort'] : 'created_at',
            'dir'           => strtolower((string) ($raw['dir'] ?? '')) === 'asc' ? 'asc' : 'desc',
        ];

        if (! in_array($filters['category'], AuditLogger::CATEGORIES, true)) {
            $filters['category'] = '';
        }
        if (! in_array($filters['status'], AuditLogger::STATUSES, true)) {
            $filters['status'] = '';
        }

        if ($filters['date_from'] !== '' && $filters['date_to'] !== '' && $filters['date_from'] > $filters['date_to']) {
            [$filters['date_from'], $filters['date_to']] = [$filters['date_to'], $filters['date_from']];
        }

        return $filters;
    }

    private function tokenOrEmpty(mixed $value, int $maxLength): string
    {
        $value = mb_substr(trim((string) $value), 0, $maxLength);

        return preg_match('/^[A-Za-z0-9._:\-]*$/', $value) === 1 ? $value : '';
    }

    private function dateOrEmpty(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
    }

    /**
     * Decode a JSON column for display; returns [] when empty/invalid.
     *
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Prevent CSV formula injection for values a spreadsheet would evaluate.
     */
    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }
}
