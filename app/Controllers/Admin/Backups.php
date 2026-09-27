<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\DatabaseBackupService;
use Throwable;

/**
 * Admin dashboard for the database backups.
 *
 * Access model (defence in depth):
 *  1. the /admin route group is protected by the `adminaccess` filter;
 *  2. the page additionally requires the `backups` page key (granted to admin
 *     staff by a master admin), checked again here because a filter
 *     misconfiguration must never expose database dumps;
 *  3. the destructive operations — restore and delete — are restricted to
 *     master admins, and restore additionally requires the typed confirmation
 *     `RESTORE` plus a successful pre-restore safety backup;
 *  4. the automatic schedule (frequency + time of day) can only be changed by
 *     master admins; every change is audited.
 *
 * Every action is audited by App\Libraries\DatabaseBackupService.
 */
class Backups extends BaseController
{
    private const PER_PAGE = 200;
    private const CONFIRM_PHRASE = 'RESTORE';

    protected DatabaseBackupService $backups;

    public function __construct()
    {
        $this->backups = service('databaseBackup');
    }

    public function index()
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $rows       = [];
        $registryOk = true;

        try {
            $rows = $this->backups->listBackups(self::PER_PAGE);
        } catch (Throwable $e) {
            $registryOk = false;
            log_message('error', 'Database backup listing failed: ' . $e->getMessage());
            session()->setFlashdata('error', 'The backup registry could not be read (has the migration been run?).');
        }

        $totalSize   = 0;
        $lastAuto    = null;
        $kindCounts  = ['auto' => 0, 'manual' => 0, 'pre' => 0];
        $verified    = 0;
        $problemRows = 0;

        foreach ($rows as $row) {
            $totalSize += (int) ($row['size_bytes'] ?? 0);

            $kind = (string) ($row['kind'] ?? 'manual');
            $kindCounts[isset($kindCounts[$kind]) ? $kind : 'manual']++;

            if (($row['verify_result'] ?? null) === 'ok') {
                $verified++;
            }

            // "Needs attention": the file is gone, the sidecar checksum is
            // missing, or the last integrity check failed. Such a row can never
            // be restored and is surfaced separately.
            if (empty($row['file_exists'])
                || empty($row['meta_exists'])
                || in_array((string) ($row['verify_result'] ?? ''), ['checksum_mismatch', 'invalid_file', 'file_missing'], true)
            ) {
                $problemRows++;
            }

            if ($lastAuto === null && $kind === 'auto') {
                $lastAuto = $row;
            }
        }

        $todayAuto = null;

        try {
            $todayAuto = $this->backups->autoBackupForToday();
        } catch (Throwable $e) {
            $todayAuto = null;
        }

        $schedule      = $this->backups->scheduleSettings();
        $scheduleState = $this->backups->scheduleState();
        $nextRunAt     = $scheduleState['enabled'] && $scheduleState['next'] !== null
            ? date('Y-m-d H:i:s', (int) $scheduleState['next'])
            : '';
        $cron          = $this->backups->scheduleCronLine($schedule);

        try {
            $availability = $this->backups->isAvailable();
            $operation    = $this->backups->operationStatus();
        } catch (Throwable $e) {
            $availability = ['canBackup' => false, 'canRestore' => false, 'issues' => [], 'warnings' => [], 'database' => '', 'directory' => null];
            $operation    = null;
        }

        $directory = $availability['directory'] ?? null;
        $diskFree  = null;

        if (is_string($directory) && $directory !== '' && function_exists('disk_free_space')) {
            $free     = @disk_free_space($directory);
            $diskFree = is_float($free) || is_int($free) ? (int) $free : null;
        }

        $totalRegistered = count($rows);

        if ($registryOk) {
            try {
                $totalRegistered = max($totalRegistered, $this->backups->countBackups());
            } catch (Throwable $e) {
                $totalRegistered = count($rows);
            }
        }

        return view('admin/backups', [
            'title'             => 'Backup & Restore - CSCS Tap n Track',
            'registry_ok'       => $registryOk,
            'rows'              => $rows,
            'newest'            => $rows[0] ?? null,
            'oldest'            => $rows === [] ? null : $rows[count($rows) - 1],
            'total_backups'     => count($rows),
            'total_registered'  => $totalRegistered,
            'total_size'        => $totalSize,
            'kind_counts'       => $kindCounts,
            'verified_count'    => $verified,
            'problem_count'     => $problemRows,
            'last_auto'         => $lastAuto,
            'today_auto'        => $todayAuto,
            'next_run_at'       => $nextRunAt,
            'disk_free'         => $diskFree,
            'operation'         => $operation,
            'availability'      => $availability,
            'retention'         => $this->backups->retentionCount(),
            'cron_line'         => $cron,
            'schedule'          => $schedule,
            'schedule_due'      => (bool) ($scheduleState['due'] ?? false),
            'schedule_next_ts'  => $scheduleState['next'] ?? null,
            'schedule_hour'     => (int) $schedule['hour'],
            'schedule_enabled'  => (bool) $schedule['enabled'],
            'confirm_phrase'    => self::CONFIRM_PHRASE,
            'is_master_admin'   => is_master_admin(),
        ]);
    }

    /**
     * Live progress of the running backup/restore, polled by the page while an
     * operation is in flight. Read-only, and behind the same guard as the page
     * because it exposes actor names and dump filenames.
     */
    public function status()
    {
        if (($denied = $this->guard()) !== null) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'error'   => 'You are not allowed to view the backup status.',
                ]);
            }

            return $denied;
        }

        try {
            $operation = $this->backups->operationStatus();
        } catch (Throwable $e) {
            $operation = null;
        }

        return $this->response->setJSON([
            'success'     => true,
            'operation'   => $operation,
            'csrf_hash'   => csrf_hash(),
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Creates a backup on demand (manual kind). */
    public function create()
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        [$actorId, $actorName] = $this->actor();

        $result = $this->backups->create('manual', $actorId, $actorName, 'web');

        if (! $result['ok']) {
            return $this->respond(false, (string) $result['error'], 'admin/backups');
        }

        $backup  = $result['backup'] ?? [];
        $message = 'Backup created: ' . (string) ($backup['filename'] ?? '')
            . ' (' . DatabaseBackupService::formatBytes((int) ($backup['size_bytes'] ?? 0))
            . ', ' . (int) ($backup['tables_count'] ?? 0) . ' table(s))';

        $rotation = $result['rotation'];

        if (is_array($rotation) && ($rotation['deleted'] ?? 0) > 0) {
            $message .= '. Rotation removed ' . (int) $rotation['deleted'] . ' old backup(s).';
        }

        foreach ($result['warnings'] as $warning) {
            $message .= ' ' . $warning;
        }

        return $this->respond(true, $message, 'admin/backups', [
            'backup' => [
                'id'           => (int) ($backup['id'] ?? 0),
                'filename'     => (string) ($backup['filename'] ?? ''),
                'kind'         => (string) ($backup['kind'] ?? 'manual'),
                'size_bytes'   => (int) ($backup['size_bytes'] ?? 0),
                'tables_count' => (int) ($backup['tables_count'] ?? 0),
                'checksum'     => (string) ($backup['checksum_sha256'] ?? ''),
                'created_at'   => (string) ($backup['created_at'] ?? ''),
            ],
            'rotation' => is_array($rotation) ? [
                'deleted' => (int) ($rotation['deleted'] ?? 0),
                'kept'    => (int) ($rotation['kept'] ?? 0),
                'max'     => (int) ($rotation['max'] ?? 0),
            ] : null,
            'warnings'    => $result['warnings'],
            'duration'    => (float) ($result['duration_seconds'] ?? 0),
        ]);
    }

    /** Recomputes a backup's checksum and structure. */
    public function verify($id = null)
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $id = (int) $id;

        if ($id <= 0) {
            return $this->respond(false, 'Invalid backup.', 'admin/backups');
        }

        [$actorId, $actorName] = $this->actor();

        $result = $this->backups->verify($id, $actorId, $actorName, 'web');

        if (! $result['ok']) {
            return $this->respond(false, 'Verification failed: ' . (string) $result['error'], 'admin/backups', [
                'verify' => [
                    'id'     => $id,
                    'status' => (string) ($result['status'] ?? 'failed'),
                ],
            ]);
        }

        return $this->respond(
            true,
            'Integrity verified: checksum ' . substr((string) $result['checksum'], 0, 16) . '…, '
                . (int) $result['tables'] . ' table(s), ' . DatabaseBackupService::formatBytes((int) $result['size']) . '.',
            'admin/backups',
            [
                'verify' => [
                    'id'          => $id,
                    'status'      => 'ok',
                    'verified_at' => date('Y-m-d H:i:s'),
                    'checksum'    => (string) $result['checksum'],
                    'tables'      => (int) $result['tables'],
                    'size'        => (int) $result['size'],
                ],
            ]
        );
    }

    /** Streams a dump to the browser (the storage path is never exposed). */
    public function download($id = null)
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $id  = (int) $id;
        $row = $id > 0 ? $this->backups->find($id) : null;

        if ($row === null) {
            return redirect()->to(base_url('admin/backups'))->with('error', 'Backup not found.');
        }

        $path = $this->backups->filePath((string) $row['filename']);

        if ($path === null) {
            return redirect()->to(base_url('admin/backups'))->with('error', 'The backup file is missing from disk.');
        }

        [$actorId, $actorName] = $this->actor();

        audit_event('system.backup_downloaded', [
            'category'      => 'system',
            'status'        => 'success',
            'description'   => 'Database backup downloaded: ' . $row['filename'],
            'actor_user_id' => $actorId,
            'actor_name'    => $actorName,
            'resource_type' => 'db_backup',
            'resource_id'   => (string) $row['id'],
            'metadata'      => [
                'backup_id'   => (int) $row['id'],
                'filename'    => (string) $row['filename'],
                'size_bytes'  => (int) ($row['size_bytes'] ?? 0),
                'checksum'    => (string) ($row['checksum_sha256'] ?? ''),
            ],
        ]);

        // Large dumps: keep the download alive and stream from disk.
        @set_time_limit(0);

        return $this->response
            ->download($path, null)
            ->setFileName((string) $row['filename'])
            ->setContentType('application/sql');
    }

    /** Restores a backup (master admin only, explicit confirmation required). */
    public function restore()
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        if (! is_master_admin()) {
            return $this->respond(false, 'Only a master administrator can restore the database.', 'admin/backups');
        }

        if (strtoupper((string) $this->request->getMethod()) !== 'POST') {
            return redirect()->to(base_url('admin/backups'));
        }

        $id      = (int) $this->request->getPost('backup_id');
        $confirm = strtoupper(trim((string) $this->request->getPost('confirm')));

        if ($confirm !== self::CONFIRM_PHRASE) {
            return $this->respond(
                false,
                'Restore not confirmed: type ' . self::CONFIRM_PHRASE . ' exactly to proceed.',
                'admin/backups'
            );
        }

        if ($id <= 0) {
            return $this->respond(false, 'Invalid backup.', 'admin/backups');
        }

        [$actorId, $actorName] = $this->actor();

        $result = $this->backups->restoreById($id, $actorId, $actorName, 'web');

        if (! $result['ok']) {
            return $this->respond(false, (string) $result['error'], 'admin/backups');
        }

        return $this->respond(
            true,
            'Database restored from the selected backup in ' . (string) $result['duration_seconds'] . 's.'
                . ' Pre-restore safety backup: ' . (string) ($result['safety_backup']['filename'] ?? '(none)') . '.',
            'admin/backups',
            [
                'restore' => [
                    'id'                 => $id,
                    'duration_seconds'   => (float) ($result['duration_seconds'] ?? 0),
                    'safety_backup_id'   => (int) ($result['safety_backup']['id'] ?? 0),
                    'safety_backup_file' => (string) ($result['safety_backup']['filename'] ?? ''),
                ],
            ]
        );
    }

    /** Deletes a backup and its file (master admin only). */
    public function delete($id = null)
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        if (! is_master_admin()) {
            return $this->respond(false, 'Only a master administrator can delete a backup.', 'admin/backups');
        }

        $id = (int) $id;

        if ($id <= 0) {
            return $this->respond(false, 'Invalid backup.', 'admin/backups');
        }

        [$actorId, $actorName] = $this->actor();

        $result = $this->backups->deleteBackup($id, $actorId, $actorName, 'web');

        if (! $result['ok']) {
            return $this->respond(false, (string) $result['error'], 'admin/backups');
        }

        $message = 'Backup deleted: ' . (string) $result['filename'];

        if (! $result['file_removed']) {
            $message .= ' (the file could not be removed from disk and needs manual cleanup).';
        }

        return $this->respond(true, $message, 'admin/backups', [
            'deleted' => [
                'id'           => $id,
                'filename'     => (string) $result['filename'],
                'file_removed' => (bool) $result['file_removed'],
            ],
        ]);
    }

    /**
     * Saves the automatic-backup schedule (frequency, time of day, weekday for
     * weekly plans and the enabled switch). Master admins only: it changes
     * system-wide behaviour. Audited as `system.backup_schedule_updated`.
     */
    public function schedule()
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        if (! is_master_admin()) {
            return $this->respond(false, 'Only a master administrator can change the backup schedule.', 'admin/backups');
        }

        [$actorId, $actorName] = $this->actor();

        $result = $this->backups->saveScheduleSettings([
            'enabled'   => $this->request->getPost('schedule_enabled') !== null,
            'frequency' => (string) $this->request->getPost('schedule_frequency'),
            'time'      => (string) $this->request->getPost('schedule_time'),
            'weekday'   => (int) $this->request->getPost('schedule_weekday'),
        ], $actorId, $actorName, 'web');

        if (! $result['ok']) {
            return $this->respond(false, (string) $result['error'], 'admin/backups');
        }

        $settings = $result['settings'];

        return $this->respond(true, (string) $result['message'], 'admin/backups', [
            'schedule' => [
                'enabled'   => (bool) $settings['enabled'],
                'frequency' => (string) $settings['frequency'],
                'time'      => (string) $settings['time'],
                'weekday'   => (int) $settings['weekday'],
                'label'     => (string) $settings['label'],
            ],
        ]);
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

            if ($user === null || ! admin_staff_can_view_page($user, 'backups')) {
                return redirect()->to(base_url('admin/dashboard'))->with('error', 'You do not have access to the Backup & Restore page.');
            }
        } catch (Throwable $e) {
            return redirect()->to(base_url('login'));
        }

        return null;
    }

    /**
     * Current admin identity for the audit trail.
     *
     * @return array{0:int|null,1:string}
     */
    private function actor(): array
    {
        try {
            $user = auth()->user();
        } catch (Throwable $e) {
            $user = null;
        }

        if ($user === null) {
            return [null, ''];
        }

        $name = trim(((string) ($user->first_name ?? '')) . ' ' . ((string) ($user->last_name ?? '')));

        if ($name === '') {
            $name = trim((string) ($user->username ?? ''));
        }

        if ($name === '') {
            $name = 'User #' . (int) $user->id;
        }

        return [(int) $user->id, $name];
    }

    /**
     * Uniform result handling: JSON for AJAX callers, a flashed redirect for the
     * normal form posts used by the dashboard.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface
     */
    private function respond(bool $ok, string $message, string $redirect, array $data = [])
    {
        if ($this->request->isAJAX()) {
            $payload = ['csrf_hash' => csrf_hash()] + $data;

            return $this->response->setJSON(
                $ok
                    ? ['success' => true, 'message' => $message] + $payload
                    : ['success' => false, 'error' => $message] + $payload
            );
        }

        return redirect()->to(base_url($redirect))->with($ok ? 'success' : 'error', $message);
    }
}


