<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\DatabaseBackupModel;
use App\Models\SystemSettingModel;
use Config\Backup as BackupConfig;
use Config\Database;
use Throwable;

/**
 * Database backup / restore engine for the admin "Backup & Restore" page and
 * the `backup:*` spark commands.
 *
 * Design notes
 * ------------
 * - Uses the database's native tools (mysqldump / mysql) so dumps are complete
 *   and restorable with the standard client.
 * - Credentials are never written to disk, never passed on the command line and
 *   never logged: the password travels in the child process environment
 *   (MYSQL_PWD). Process arguments are always passed as an ARRAY, with
 *   bypass_shell on Windows, so a shell can never reinterpret them — there is
 *   no string concatenation and therefore no command injection surface.
 * - A dump filename always matches FILENAME_PATTERN and every path is resolved
 *   inside the backup directory with a realpath containment check, so a crafted
 *   name can never escape the directory (path traversal).
 * - Rotation runs only AFTER a successful dump, so a failed backup can never
 *   cost the previous valid backup.
 */
class DatabaseBackupService
{
    public const KINDS = ['auto', 'manual', 'pre'];

    /**
     * Frequencies the admin can choose for the automatic backup schedule.
     * `backup:daily` is idempotent per slot, so it may safely run more often
     * than the chosen frequency (missed runs are caught up on the next run).
     */
    public const SCHEDULE_FREQUENCIES = ['daily', 'every12h', 'every8h', 'every6h', 'hourly', 'weekly'];

    /** Slot length in hours per frequency (weekly is handled separately). */
    public const SCHEDULE_INTERVALS = [
        'daily'    => 24,
        'every12h' => 12,
        'every8h'  => 8,
        'every6h'  => 6,
        'hourly'   => 1,
        'weekly'   => 0,
    ];

    /** Weekday names indexed by date('w') (0 = Sunday), used by weekly runs. */
    public const SCHEDULE_WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /** `system_settings` keys the effective schedule is stored under. */
    public const SETTING_SCHEDULE_ENABLED   = 'backup_schedule_enabled';
    public const SETTING_SCHEDULE_FREQUENCY = 'backup_schedule_frequency';
    public const SETTING_SCHEDULE_TIME      = 'backup_schedule_time';
    public const SETTING_SCHEDULE_WEEKDAY   = 'backup_schedule_weekday';

    /** The only filename shape that may ever reach the filesystem. */
    public const FILENAME_PATTERN = '/^db-(?:auto|manual|pre)-\d{8}-\d{6}-[a-f0-9]{8}\.sql$/';

    /** Sidecar description (checksum + metadata) written next to every dump. */
    public const META_SUFFIX = '.meta.json';

    /**
     * mysqldump option profiles, tried in order. A profile is only abandoned
     * when the client/server rejects one of its options (MariaDB's client has no
     * --set-gtid-purged; --routines needs extra privileges on restricted shared
     * hosting), so the backup still succeeds with the widest usable profile.
     */
    private const DUMP_PROFILES = [
        'full'       => ['--single-transaction', '--quick', '--no-tablespaces', '--set-gtid-purged=OFF', '--routines', '--triggers'],
        'compatible' => ['--single-transaction', '--quick', '--no-tablespaces', '--routines', '--triggers'],
        'minimal'    => ['--single-transaction', '--quick', '--triggers'],
        'plain'      => ['--single-transaction', '--quick'],
    ];

    /**
     * Environment variables a child process needs to start. They are re-added
     * when the SAPI (e.g. Apache module) exposes a stripped environment, because
     * proc_open() replaces the whole environment with the array we pass.
     */
    private const ESSENTIAL_ENV = [
        'SystemRoot', 'windir', 'TEMP', 'TMP', 'ComSpec', 'PATHEXT', 'PATH',
        'USERPROFILE', 'USERNAME', 'APPDATA', 'LOCALAPPDATA', 'NUMBER_OF_PROCESSORS',
        'HOME', 'TMPDIR', 'LANG', 'LC_ALL',
    ];

    private BackupConfig $config;

    private DatabaseBackupModel $model;

    private ?string $mysqldumpBinary = null;

    private bool $mysqldumpResolved = false;

    private ?string $mysqlBinary = null;

    private bool $mysqlResolved = false;

    private ?BackupOperationTracker $tracker = null;

    /** Memoized effective schedule for this request. */
    private ?array $scheduleCache = null;

    private ?SystemSettingModel $settingsModel = null;

    public function __construct(
        ?BackupConfig $config = null,
        ?DatabaseBackupModel $model = null,
        ?SystemSettingModel $settingsModel = null
    ) {
        $this->config        = $config ?? config(BackupConfig::class);
        $this->model         = $model ?? model(DatabaseBackupModel::class);
        $this->settingsModel = $settingsModel;
    }

    // -------------------------------------------------------------------------
    // Environment / availability
    // -------------------------------------------------------------------------

    /**
     * Can this server take (and restore) backups right now? Never throws.
     *
     * @return array{canBackup:bool,canRestore:bool,issues:list<string>,warnings:list<string>,mysqldump:?string,mysql:?string,database:string,host:string,driver:string,directory:?string}
     */
    public function isAvailable(): array
    {
        $issues   = [];
        $warnings = [];

        $connection = $this->connection();

        if (! in_array(strtolower($connection['driver']), ['mysqli', 'mysql'], true)) {
            $issues[] = 'Backups require the MySQL/MariaDB driver; the active driver is '
                . ($connection['driver'] !== '' ? $connection['driver'] : 'unknown') . '.';
        }

        foreach ($this->connectionIssues($connection) as $issue) {
            $issues[] = $issue;
        }

        $mysqldump = $this->resolveMysqldump();
        if ($mysqldump === null) {
            $issues[] = 'The mysqldump client was not found. Set backup.mysqldumpPath in .env to its absolute path.';
        }

        $mysql = $this->resolveMysql();
        if ($mysql === null) {
            $warnings[] = 'The mysql client was not found, so restoring is unavailable. Set backup.mysqlPath in .env to its absolute path.';
        }

        $directory = $this->directory();
        if ($directory === null) {
            $issues[] = 'The backup directory is not writable: ' . $this->configuredDirectory();
        }

        $canBackup = $issues === [];

        return [
            'canBackup'  => $canBackup,
            'canRestore' => $canBackup && $mysql !== null,
            'issues'     => $issues,
            'warnings'   => $warnings,
            'mysqldump'  => $mysqldump,
            'mysql'      => $mysql,
            'database'   => $connection['database'],
            'host'       => $connection['host'],
            'driver'     => $connection['driver'],
            'directory'  => $directory,
        ];
    }

    /**
     * Whether automatic backups are enabled (admin setting first, then the
     * `backup.enabled` fallback in .env).
     */
    public function isEnabled(): bool
    {
        return (bool) $this->scheduleSettings()['enabled'];
    }

    public function retentionCount(): int
    {
        $configured = $this->config->retentionCount;

        // A mistyped override must never collapse the window: an empty
        // `backup.retentionCount =` in .env arrives here as 0/'' and therefore
        // falls back to the documented default of 30 backups.
        if (! is_numeric($configured)) {
            return 30;
        }

        $retention = (int) $configured;

        return $retention >= 1 ? $retention : 30;
    }

    /** Process timeout in seconds; an empty/invalid override falls back to 1 hour. */
    public function processTimeout(): int
    {
        $configured = $this->config->processTimeout;

        return is_numeric($configured) && (int) $configured > 0 ? (int) $configured : 3600;
    }

    /** Hour of day (server time) the automatic schedule runs from. */
    public function scheduleHour(): int
    {
        return (int) $this->scheduleSettings()['hour'];
    }

    // -------------------------------------------------------------------------
    // Automatic schedule (admin-configurable, stored in `system_settings`)
    //
    // The admin page may change the frequency and time without touching .env or
    // cron; Config\Backup still supplies the defaults and stays authoritative
    // when the settings table is unavailable. The scheduled command is
    // idempotent per slot (day / N-hour window / hour / week), so a missed run
    // is caught up by the next one and frequent runs never duplicate a backup.
    // -------------------------------------------------------------------------

    /**
     * Effective schedule: database settings first, `Config\Backup` as fallback.
     *
     * @return array{enabled:bool,frequency:string,hour:int,minute:int,time:string,weekday:int,interval:int,label:string,source:string}
     */
    public function scheduleSettings(): array
    {
        if ($this->scheduleCache !== null) {
            return $this->scheduleCache;
        }

        $configHour = max(0, min(23, (int) $this->config->scheduleHour));

        $defaults = [
            'enabled'   => (bool) $this->config->enabled,
            'frequency' => 'daily',
            'hour'      => $configHour,
            'minute'    => 0,
            'time'      => sprintf('%02d:00', $configHour),
            'weekday'   => 0,
            'source'    => 'config',
        ];
        $defaults['label'] = self::scheduleLabel($defaults);

        $raw    = [];
        $source = 'config';

        try {
            $stored = [
                'enabled'   => $this->settingsModel()->getSetting(self::SETTING_SCHEDULE_ENABLED, null),
                'frequency' => $this->settingsModel()->getSetting(self::SETTING_SCHEDULE_FREQUENCY, null),
                'time'      => $this->settingsModel()->getSetting(self::SETTING_SCHEDULE_TIME, null),
                'weekday'   => $this->settingsModel()->getSetting(self::SETTING_SCHEDULE_WEEKDAY, null),
            ];

            if (array_filter($stored, static fn ($value): bool => $value !== null && $value !== '') !== []) {
                $raw    = $stored;
                $source = 'database';
            }
        } catch (Throwable $e) {
            // Table missing (pending migration) or unreachable: the configured
            // defaults keep the schedule working.
            $raw = [];
        }

        $settings           = self::normalizeSchedule($raw, $defaults);
        $settings['source'] = $source;

        return $this->scheduleCache = $settings;
    }

    /**
     * Validates and persists the schedule. The controller restricts this to
     * master admins; the change is written to the activity log.
     *
     * @param array<string, mixed> $input enabled|frequency|time|weekday
     * @param string $origin web|cli (audit context only)
     *
     * @return array{ok:bool,error:?string,message:string,settings:array<string,mixed>}
     */
    public function saveScheduleSettings(array $input, ?int $actorId = null, string $actorName = '', string $origin = 'web'): array
    {
        $before = $this->scheduleSettings();
        $check  = self::validateScheduleInput($input);

        if (! $check['ok']) {
            return ['ok' => false, 'error' => $check['error'], 'message' => '', 'settings' => $before];
        }

        $settings = $check['settings'];

        try {
            $model = $this->settingsModel();

            $model->setSetting(self::SETTING_SCHEDULE_ENABLED, $settings['enabled'] ? '1' : '0',
                'Automatic database backups enabled (Administration → Backup & Restore)');
            $model->setSetting(self::SETTING_SCHEDULE_FREQUENCY, $settings['frequency'],
                'Automatic backup frequency: daily | every12h | every8h | every6h | hourly | weekly');
            $model->setSetting(self::SETTING_SCHEDULE_TIME, $settings['time'],
                'Time of day (server time, HH:MM) the automatic backup schedule runs from');
            $model->setSetting(self::SETTING_SCHEDULE_WEEKDAY, (string) $settings['weekday'],
                'Weekday (0 = Sunday … 6 = Saturday) used by the weekly backup frequency');
        } catch (Throwable $e) {
            log_message('error', 'Saving the backup schedule failed: ' . $e->getMessage());

            return [
                'ok'       => false,
                'error'    => 'The schedule could not be saved (the system_settings table is unavailable).',
                'message'  => '',
                'settings' => $before,
            ];
        }

        // Drop the memoized value so the rest of the request sees the new plan.
        $this->scheduleCache = null;
        $effective           = $this->scheduleSettings();

        helper('audit');
        audit_event('system.backup_schedule_updated', [
            'category'      => 'settings',
            'status'        => 'success',
            'actor_user_id' => $actorId,
            'actor_name'    => $actorName,
            'resource_type' => 'setting',
            'resource_id'   => 'backup_schedule',
            'description'   => 'Automatic database backup schedule set to: ' . $effective['label']
                . ($effective['enabled'] ? '' : ' (paused)'),
            'metadata'      => ['origin' => $origin],
        ] + audit_diff(
            [
                'enabled'   => $before['enabled'] ? 'on' : 'off',
                'frequency' => $before['frequency'],
                'time'      => $before['time'],
                'weekday'   => (string) $before['weekday'],
            ],
            [
                'enabled'   => $effective['enabled'] ? 'on' : 'off',
                'frequency' => $effective['frequency'],
                'time'      => $effective['time'],
                'weekday'   => (string) $effective['weekday'],
            ],
            ['enabled', 'frequency', 'time', 'weekday']
        ));

        return [
            'ok'       => true,
            'error'    => null,
            'message'  => 'Backup schedule saved: ' . $effective['label'] . ($effective['enabled'] ? '' : ' (paused)') . '.',
            'settings' => $effective,
        ];
    }

    /**
     * Scheduling decision for the current moment: whether a new automatic
     * backup is due and when the next one is expected.
     *
     * @return array{enabled:bool,due:bool,slot:?int,next:?int,automatic:?array<string,mixed>,settings:array<string,mixed>}
     */
    public function scheduleState(?int $now = null): array
    {
        $settings = $this->scheduleSettings();
        $now      = $now ?? time();

        if (! $settings['enabled']) {
            return [
                'enabled'   => false,
                'due'       => false,
                'slot'      => null,
                'next'      => null,
                'automatic' => null,
                'settings'  => $settings,
            ];
        }

        $slot      = self::slotStart($settings, $now);
        $automatic = null;

        try {
            $automatic = $this->model->findLatestAutoBackup(date('Y-m-d H:i:s', $slot));
        } catch (Throwable $e) {
            // An unreadable registry must not stop a catch-up attempt: a failed
            // dump cannot cost an existing backup, and the slot check simply
            // repeats on the next scheduled run.
            $automatic = null;
        }

        $due = $automatic === null;

        return [
            'enabled'   => true,
            'due'       => $due,
            'slot'      => $slot,
            'next'      => $due ? $now : self::nextSlot($settings, $now),
            'automatic' => $automatic,
            'settings'  => $settings,
        ];
    }

    /**
     * Human label for a schedule, e.g. "Daily at 02:00".
     *
     * @param array<string, mixed> $schedule
     */
    public static function scheduleLabel(array $schedule): string
    {
        $hour      = max(0, min(23, (int) ($schedule['hour'] ?? 0)));
        $minute    = max(0, min(59, (int) ($schedule['minute'] ?? 0)));
        $time      = sprintf('%02d:%02d', $hour, $minute);
        $frequency = (string) ($schedule['frequency'] ?? 'daily');

        if ($frequency === 'weekly') {
            $weekday = max(0, min(6, (int) ($schedule['weekday'] ?? 0)));

            return 'Weekly on ' . self::SCHEDULE_WEEKDAYS[$weekday] . ' at ' . $time;
        }

        if ($frequency === 'hourly') {
            return 'Hourly at :' . sprintf('%02d', $minute);
        }

        if ($frequency === 'daily') {
            return 'Daily at ' . $time;
        }

        return 'Every ' . (int) (self::SCHEDULE_INTERVALS[$frequency] ?? 24) . ' hours from ' . $time;
    }

    /**
     * Normalises raw stored values (missing or invalid fields fall back to
     * $defaults, which must already be normalised). Used when reading.
     *
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $defaults
     *
     * @return array{enabled:bool,frequency:string,hour:int,minute:int,time:string,weekday:int,interval:int,label:string,source:string}
     */
    public static function normalizeSchedule(array $raw, array $defaults): array
    {
        $enabled = (bool) ($defaults['enabled'] ?? true);

        if (array_key_exists('enabled', $raw) && $raw['enabled'] !== null && $raw['enabled'] !== '') {
            $flag = filter_var($raw['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($flag !== null) {
                $enabled = $flag;
            }
        }

        $frequency = strtolower(trim((string) ($raw['frequency'] ?? '')));

        if (! in_array($frequency, self::SCHEDULE_FREQUENCIES, true)) {
            $frequency = (string) ($defaults['frequency'] ?? 'daily');
        }

        $hour   = max(0, min(23, (int) ($defaults['hour'] ?? 2)));
        $minute = max(0, min(59, (int) ($defaults['minute'] ?? 0)));
        $time   = trim((string) ($raw['time'] ?? ''));

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches) === 1
            && (int) $matches[1] <= 23 && (int) $matches[2] <= 59) {
            $hour   = (int) $matches[1];
            $minute = (int) $matches[2];
        }

        $weekday = is_numeric($raw['weekday'] ?? null)
            ? (int) $raw['weekday']
            : max(0, min(6, (int) ($defaults['weekday'] ?? 0)));

        if ($weekday < 0 || $weekday > 6) {
            $weekday = max(0, min(6, (int) ($defaults['weekday'] ?? 0)));
        }

        $schedule = [
            'enabled'   => $enabled,
            'frequency' => $frequency,
            'hour'      => $hour,
            'minute'    => $minute,
            'time'      => sprintf('%02d:%02d', $hour, $minute),
            'weekday'   => $weekday,
            'interval'  => (int) (self::SCHEDULE_INTERVALS[$frequency] ?? 24),
            'label'     => '',
            'source'    => (string) ($defaults['source'] ?? 'config'),
        ];
        $schedule['label'] = self::scheduleLabel($schedule);

        return $schedule;
    }

    /**
     * Strict validation for a save request: bad input is reported back to the
     * administrator instead of being silently replaced by a default.
     *
     * @param array<string, mixed> $input
     *
     * @return array{ok:bool,error:?string,settings:?array<string,mixed>}
     */
    public static function validateScheduleInput(array $input): array
    {
        $frequency = strtolower(trim((string) ($input['frequency'] ?? '')));

        if (! in_array($frequency, self::SCHEDULE_FREQUENCIES, true)) {
            return ['ok' => false, 'error' => 'Choose a valid backup frequency.', 'settings' => null];
        }

        $time = trim((string) ($input['time'] ?? ''));

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches) !== 1
            || (int) $matches[1] > 23 || (int) $matches[2] > 59) {
            return ['ok' => false, 'error' => 'Enter the backup time as HH:MM (24-hour clock).', 'settings' => null];
        }

        $weekday = (int) ($input['weekday'] ?? 0);

        if ($weekday < 0 || $weekday > 6) {
            return ['ok' => false, 'error' => 'Choose a weekday between Sunday and Saturday.', 'settings' => null];
        }

        return [
            'ok'       => true,
            'error'    => null,
            'settings' => self::normalizeSchedule([
                'enabled'   => ! empty($input['enabled']) ? '1' : '0',
                'frequency' => $frequency,
                'time'      => $time,
                'weekday'   => (string) $weekday,
            ], [
                'enabled'   => true,
                'frequency' => 'daily',
                'hour'      => 2,
                'minute'    => 0,
                'weekday'   => 0,
                'source'    => 'database',
            ]),
        ];
    }

    /**
     * Timestamp of the most recent scheduled slot at or before $now.
     *
     * A slot is the period the schedule promises one backup for: a calendar
     * day (daily), a 12/8/6-hour window, an hour (hourly) or a week (weekly).
     *
     * @param array<string, mixed> $schedule normalised schedule
     */
    public static function slotStart(array $schedule, int $now): int
    {
        $time   = sprintf('%02d:%02d', (int) ($schedule['hour'] ?? 0), (int) ($schedule['minute'] ?? 0));
        $anchor = strtotime(date('Y-m-d', $now) . ' ' . $time);

        if ($anchor === false) {
            return $now;
        }

        $frequency = (string) ($schedule['frequency'] ?? 'daily');

        if ($frequency === 'weekly') {
            $daysBack = ((int) date('w', $anchor) - (int) ($schedule['weekday'] ?? 0) + 7) % 7;
            $anchor  -= $daysBack * 86400;

            if ($anchor > $now) {
                $anchor -= 7 * 86400;
            }

            return $anchor;
        }

        if ($frequency === 'hourly') {
            $anchor = strtotime(date('Y-m-d', $now) . ' ' . sprintf('00:%02d', (int) ($schedule['minute'] ?? 0)));

            if ($anchor === false) {
                return $now;
            }

            while ($anchor + 3600 <= $now) {
                $anchor += 3600;
            }

            return $anchor;
        }

        if ($anchor > $now) {
            $anchor -= 86400;
        }

        $step = max(1, (int) ($schedule['interval'] ?? 24)) * 3600;

        while ($anchor + $step <= $now) {
            $anchor += $step;
        }

        return $anchor;
    }

    /**
     * Timestamp of the next slot strictly after $now.
     *
     * @param array<string, mixed> $schedule normalised schedule
     */
    public static function nextSlot(array $schedule, int $now): int
    {
        $slot = self::slotStart($schedule, $now);
        $step = (string) ($schedule['frequency'] ?? 'daily') === 'weekly'
            ? 7 * 86400
            : max(1, (int) ($schedule['interval'] ?? 24)) * 3600;

        while ($slot <= $now) {
            $slot += $step;
        }

        return $slot;
    }

    /**
     * Scheduler command for the effective schedule (cron on Linux/macOS, Task
     * Scheduler on Windows), shown on the admin page so the host runs the
     * command at the right moments. Running it more often is harmless: the
     * command creates at most one automatic backup per slot.
     *
     * @param array<string, mixed>|null $schedule
     */
    public function scheduleCronLine(?array $schedule = null): string
    {
        $schedule ??= $this->scheduleSettings();

        $time      = sprintf('%02d:%02d', (int) ($schedule['hour'] ?? 0), (int) ($schedule['minute'] ?? 0));
        $spark     = ROOTPATH . 'spark';
        $taskName  = 'CSCS Tap n Track - daily DB backup';
        $frequency = (string) ($schedule['frequency'] ?? 'daily');

        if (PHP_OS_FAMILY === 'Windows') {
            $task = 'schtasks /Create /F /TN "' . $taskName . '"';

            if ($frequency === 'weekly') {
                $day = strtoupper(substr(self::SCHEDULE_WEEKDAYS[(int) ($schedule['weekday'] ?? 0)], 0, 3));

                return $task . ' /SC WEEKLY /D ' . $day . ' /ST ' . $time
                    . ' /TR "' . PHP_BINARY . ' ' . $spark . ' backup:daily"';
            }

            if ($frequency === 'daily') {
                return $task . ' /SC DAILY /ST ' . $time
                    . ' /TR "' . PHP_BINARY . ' ' . $spark . ' backup:daily"';
            }

            return $task . ' /SC HOURLY /MO 1 /ST ' . $time
                . ' /TR "' . PHP_BINARY . ' ' . $spark . ' backup:daily"';
        }

        $minute = (int) ($schedule['minute'] ?? 0);
        $hour   = (int) ($schedule['hour'] ?? 0);

        if ($frequency === 'weekly') {
            return $minute . ' ' . $hour . ' * * ' . (int) ($schedule['weekday'] ?? 0)
                . ' /usr/bin/php ' . $spark . ' backup:daily';
        }

        if ($frequency === 'daily') {
            return $minute . ' ' . $hour . ' * * * /usr/bin/php ' . $spark . ' backup:daily';
        }

        return $minute . ' * * * * /usr/bin/php ' . $spark . ' backup:daily';
    }

    private function settingsModel(): SystemSettingModel
    {
        if ($this->settingsModel === null) {
            $this->settingsModel = model(SystemSettingModel::class);
        }

        return $this->settingsModel;
    }

    /**
     * Live state of the operation currently running (or the one that finished
     * most recently), for the admin page progress panel. Null when nothing has
     * been tracked yet. Read-only and fail-safe.
     *
     * @return array<string, mixed>|null
     */
    public function operationStatus(): ?array
    {
        try {
            return $this->tracker()->current();
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Progress / concurrency tracker, bound to the resolved backup directory.
     * Falls back to the configured directory so it can be constructed even when
     * the directory is not (yet) writable — the tracker then simply runs
     * untracked.
     */
    private function tracker(): BackupOperationTracker
    {
        if ($this->tracker === null) {
            $this->tracker = new BackupOperationTracker($this->directory() ?? $this->configuredDirectory());
        }

        return $this->tracker;
    }

    /**
     * Heartbeat callback for the long running client processes. It is a no-op
     * when no operation is tracked, and it keeps the PARENT operation fresh when
     * a nested safety backup runs (the nested create does not own the slot).
     */
    private function tick(): callable
    {
        return function (): void {
            $this->tracker()->heartbeat();
        };
    }

    /**
     * Live connection details. Internal only: the array contains the password
     * and must never be logged, stored or returned to a view.
     *
     * @return array{driver:string,host:string,port:int,user:string,password:string,database:string}
     */
    private function connection(): array
    {
        try {
            $db = Database::connect();
        } catch (Throwable $e) {
            return ['driver' => '', 'host' => '', 'port' => 0, 'user' => '', 'password' => '', 'database' => ''];
        }

        return [
            'driver'   => (string) ($db->DBDriver ?? ''),
            'host'     => (string) ($db->hostname ?? ''),
            'port'     => (int) ($db->port ?: 3306),
            'user'     => (string) ($db->username ?? ''),
            'password' => (string) ($db->password ?? ''),
            'database' => (string) ($db->database ?? ''),
        ];
    }

    /**
     * Defence in depth: refuse values that could never be legitimate connection
     * tokens (they are passed as separate argv entries, so this only guards
     * against a hostile/typo'd config value).
     *
     * @param array{driver:string,host:string,port:int,user:string,password:string,database:string} $connection
     *
     * @return list<string>
     */
    private function connectionIssues(array $connection): array
    {
        $issues = [];

        if (trim($connection['database']) === '') {
            $issues[] = 'No database name is configured.';
        } elseif (preg_match('/^[A-Za-z0-9_$.\-]+$/', $connection['database']) !== 1) {
            $issues[] = 'The configured database name contains characters that are not allowed.';
        }

        if ($connection['host'] !== '' && preg_match('/^[A-Za-z0-9_.\-:]+$/', $connection['host']) !== 1) {
            $issues[] = 'The configured database host contains characters that are not allowed.';
        }

        if ($connection['user'] !== '' && preg_match('/^[A-Za-z0-9_.\-@#$]+$/', $connection['user']) !== 1) {
            $issues[] = 'The configured database user contains characters that are not allowed.';
        }

        if ($connection['port'] <= 0 || $connection['port'] > 65535) {
            $issues[] = 'The configured database port is invalid.';
        }

        return $issues;
    }

    // -------------------------------------------------------------------------
    // Directory / client binaries
    // -------------------------------------------------------------------------

    /**
     * Backup directory (created on demand, guarded, outside the web root).
     * Returns null when it is missing and cannot be created/written.
     */
    public function directory(): ?string
    {
        $dir = $this->configuredDirectory();

        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return null;
        }

        $this->writeGuards($dir);

        if (! is_writable($dir)) {
            return null;
        }

        $real = realpath($dir);

        return $real === false ? null : rtrim($real, DIRECTORY_SEPARATOR);
    }

    public function configuredDirectory(): string
    {
        $configured = trim((string) $this->config->backupDir);
        $dir        = $configured !== '' ? $configured : WRITEPATH . 'backups';

        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);
    }

    /**
     * Keeps the directory unreadable from the web even if a misconfigured
     * deployment ever puts it inside the document root.
     */
    private function writeGuards(string $dir): void
    {
        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';

        if (! is_file($htaccess)) {
            @file_put_contents(
                $htaccess,
                "<IfModule authz_core_module>\n\tRequire all denied\n</IfModule>\n<IfModule !authz_core_module>\n\tDeny from all\n</IfModule>\n"
            );
        }

        $index = $dir . DIRECTORY_SEPARATOR . 'index.html';

        if (! is_file($index)) {
            @file_put_contents($index, "<!doctype html>\n<title>403 Forbidden</title>\n");
        }
    }

    public function resolveMysqldump(): ?string
    {
        if (! $this->mysqldumpResolved) {
            $this->mysqldumpBinary   = $this->resolveBinary('mysqldump', (string) $this->config->mysqldumpPath);
            $this->mysqldumpResolved = true;
        }

        return $this->mysqldumpBinary;
    }

    public function resolveMysql(): ?string
    {
        if (! $this->mysqlResolved) {
            $this->mysqlBinary   = $this->resolveBinary('mysql', (string) $this->config->mysqlPath);
            $this->mysqlResolved = true;
        }

        return $this->mysqlBinary;
    }

    /**
     * Absolute path of a usable client binary, or null. The configured path wins;
     * otherwise PATH and the usual install locations are probed (no shell call).
     */
    private function resolveBinary(string $name, string $configured): ?string
    {
        $configured = trim($configured);

        if ($configured !== '') {
            return is_file($configured) && is_executable($configured) ? $configured : null;
        }

        $extension  = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        $candidates = [];

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $path) {
            $path = trim($path);

            if ($path !== '') {
                $candidates[] = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $name . $extension;
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates[] = 'C:\\xampp\\mysql\\bin\\' . $name . '.exe';

            foreach ([
                'C:\\wamp64\\bin\\mysql\\*\\bin\\' . $name . '.exe',
                'C:\\wamp\\bin\\mysql\\*\\bin\\' . $name . '.exe',
                'C:\\laragon\\bin\\mysql\\*\\bin\\' . $name . '.exe',
                'C:\\Program Files\\MySQL\\*\\bin\\' . $name . '.exe',
                'C:\\Program Files (x86)\\MySQL\\*\\bin\\' . $name . '.exe',
            ] as $pattern) {
                foreach (glob($pattern) ?: [] as $match) {
                    $candidates[] = $match;
                }
            }
        } else {
            $candidates = array_merge($candidates, [
                '/usr/bin/' . $name,
                '/usr/local/bin/' . $name,
                '/usr/local/mysql/bin/' . $name,
                '/opt/homebrew/bin/' . $name,
                '/opt/local/bin/' . $name,
                '/bin/' . $name,
            ]);
        }

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Filenames, paths and integrity
    // -------------------------------------------------------------------------

    /**
     * Strict shape check for a dump filename (blocks path traversal, absolute
     * paths, separators, null bytes and every unexpected extension).
     */
    public static function isSafeFilename(string $filename): bool
    {
        if ($filename === '' || strlen($filename) > 191) {
            return false;
        }

        if (str_contains($filename, "\0") || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return false;
        }

        return preg_match(self::FILENAME_PATTERN, $filename) === 1;
    }

    /**
     * Absolute path of an EXISTING dump inside the backup directory, or null.
     * The realpath containment check makes a symlink escape impossible.
     */
    public function filePath(string $filename): ?string
    {
        if (! self::isSafeFilename($filename)) {
            return null;
        }

        $dir = $this->directory();

        if ($dir === null) {
            return null;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        if (! is_file($path)) {
            return null;
        }

        $real = realpath($path);

        if ($real === false) {
            return null;
        }

        return str_starts_with($real, $dir . DIRECTORY_SEPARATOR) ? $real : null;
    }

    /** Target path for a NEW file (the filename is already pattern-checked). */
    private function pathForWrite(string $filename): ?string
    {
        if (! self::isSafeFilename($filename)) {
            return null;
        }

        $dir = $this->directory();

        return $dir === null ? null : $dir . DIRECTORY_SEPARATOR . $filename;
    }

    private function generateFilename(string $kind): string
    {
        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (Throwable $e) {
            $suffix = substr(hash('sha256', uniqid('backup', true)), 0, 8);
        }

        return sprintf('db-%s-%s-%s-%s.sql', $kind, date('Ymd'), date('His'), $suffix);
    }

    /**
     * Single streaming pass over a dump file: SHA-256, size, table count and the
     * structural markers mysqldump writes. Memory use stays flat, so this is
     * safe for multi-gigabyte dumps.
     *
     * @return array{ok:bool,error:?string,checksum:string,size:int,tables:int}
     */
    public function inspect(string $path, ?callable $onTick = null): array
    {
        $failure = static fn (string $error): array => [
            'ok' => false, 'error' => $error, 'checksum' => '', 'size' => 0, 'tables' => 0,
        ];

        if (! is_file($path)) {
            return $failure('The backup file does not exist.');
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return $failure('The backup file could not be read.');
        }

        $hash   = hash_init('sha256');
        $size   = 0;
        $tables = 0;
        $head   = '';
        $tail   = '';
        $carry  = '';

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 1048576);

                if ($chunk === false) {
                    return $failure('The backup file could not be read completely.');
                }

                if ($chunk === '') {
                    break;
                }

                $size += strlen($chunk);
                hash_update($hash, $chunk);

                if (strlen($head) < 8192) {
                    $head .= substr($chunk, 0, 8192 - strlen($head));
                }

                $tail = substr($tail . $chunk, -4096);

                // Count table definitions, carrying the boundary so a statement
                // split across two chunks is still counted exactly once.
                $buffer  = $carry . $chunk;
                $tables += substr_count($buffer, "\nCREATE TABLE ");
                $carry = substr($buffer, -32);

                if ($onTick !== null) {
                    $onTick();
                }
            }
        } finally {
            fclose($handle);
        }

        if ($size === 0) {
            return $failure('The backup file is empty.');
        }

        if (preg_match('/^-- (?:MySQL|MariaDB) dump/m', $head) !== 1) {
            return $failure('The file is not a MySQL/MariaDB dump (dump header is missing).');
        }

        if (! str_contains($tail, '-- Dump completed')) {
            return $failure('The dump is incomplete: the "Dump completed" marker is missing (the file was truncated).');
        }

        if ($tables === 0) {
            return $failure('The dump contains no table definitions.');
        }

        return [
            'ok'       => true,
            'error'    => null,
            'checksum' => hash_final($hash),
            'size'     => $size,
            'tables'   => $tables,
        ];
    }

    /**
     * Shared integrity gate used by verify() and restore(): existence, structure
     * and checksum against the stored value.
     *
     * @param array<string, mixed> $row
     *
     * @return array{ok:bool,error:?string,status:string,path:?string,checksum:string,size:int,tables:int}
     */
    private function integrityCheck(array $row, ?callable $onTick = null): array
    {
        $filename = (string) ($row['filename'] ?? '');
        $path     = $this->filePath($filename);

        if ($path === null) {
            return [
                'ok' => false, 'error' => 'The backup file is missing from disk (or its name is invalid).',
                'status' => 'file_missing', 'path' => null, 'checksum' => '', 'size' => 0, 'tables' => 0,
            ];
        }

        $inspect = $this->inspect($path, $onTick);

        if (! $inspect['ok']) {
            return [
                'ok' => false, 'error' => (string) $inspect['error'], 'status' => 'invalid_file',
                'path' => $path, 'checksum' => $inspect['checksum'], 'size' => $inspect['size'], 'tables' => $inspect['tables'],
            ];
        }

        $stored = (string) ($row['checksum_sha256'] ?? '');

        if ($stored === '' || ! hash_equals($stored, $inspect['checksum'])) {
            return [
                'ok' => false,
                'error' => 'Checksum mismatch: the dump does not match the checksum recorded when it was created.',
                'status' => 'checksum_mismatch', 'path' => $path,
                'checksum' => $inspect['checksum'], 'size' => $inspect['size'], 'tables' => $inspect['tables'],
            ];
        }

        return [
            'ok' => true, 'error' => null, 'status' => 'ok', 'path' => $path,
            'checksum' => $inspect['checksum'], 'size' => $inspect['size'], 'tables' => $inspect['tables'],
        ];
    }

    // -------------------------------------------------------------------------
    // Audit trail
    // -------------------------------------------------------------------------

    /**
     * One fail-safe audit write. Backup/restore events are security relevant,
     * but a logging failure must never break the operation it describes.
     *
     * @param array<string, mixed> $metadata
     */
    private function audit(
        string $action,
        string $status,
        string $description,
        ?int $actorId,
        string $actorName,
        array $metadata = []
    ): void {
        // Durable, out-of-database record: a full restore replaces the very table
        // the activity log lives in, so the file log keeps the trail of the
        // backup/restore operations themselves.
        log_message(
            $status === 'success' ? 'info' : 'warning',
            'Database backup event [' . $action . '] ' . $description
        );

        try {
            service('auditLogger')->record([
                'action'        => $action,
                'category'      => 'system',
                'status'        => $status,
                'description'   => $description,
                'actor_user_id' => $actorId,
                'actor_name'    => $actorName,
                'resource_type' => 'db_backup',
                'resource_id'   => isset($metadata['backup_id']) && $metadata['backup_id'] !== null
                    ? (string) $metadata['backup_id']
                    : null,
                'metadata'      => $metadata,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Backup audit event failed [' . $action . ']: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Backup
    // -------------------------------------------------------------------------

    /**
     * Creates one dump, records it and then rotates the retention window.
     *
     * A failed dump never touches existing backups: the partial file is deleted,
     * the failure is audited and rotation is skipped.
     *
     * @param string $kind   auto|manual|pre
     * @param string $origin web|cli
     * @param list<string> $protectFilenames Dumps the rotation must not delete (files a running operation still needs).
     *
     * @return array{ok:bool,error:?string,backup:?array<string,mixed>,rotation:?array<string,mixed>,duration_seconds:float,profile:?string,warnings:list<string>}
     */
    public function create(string $kind, ?int $actorId = null, string $actorName = '', string $origin = 'web', array $protectFilenames = []): array
    {
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'manual';

        if (! $this->isEnabled() && $kind === 'auto') {
            return $this->createFailure('Automatic backups are disabled (backup.enabled = false).', $kind, $actorId, $actorName, $origin);
        }

        $availability = $this->isAvailable();

        if (! $availability['canBackup']) {
            return $this->createFailure(implode(' ', $availability['issues']), $kind, $actorId, $actorName, $origin);
        }

        $directory = $this->directory();

        if ($directory === null) {
            return $this->createFailure('The backup directory is not writable.', $kind, $actorId, $actorName, $origin);
        }

        // Long dumps/restores must survive the web SAPI time limit.
        @set_time_limit(0);
        @ignore_user_abort(true);

        $filename = $this->generateFilename($kind);
        $path     = $this->pathForWrite($filename);

        if ($path === null) {
            return $this->createFailure('The backup filename could not be resolved safely.', $kind, $actorId, $actorName, $origin);
        }

        $started  = microtime(true);
        $warnings = [];

        // Progress + concurrency tracking. Fail-safe: when the sidecar cannot
        // be written the dump still runs, it is simply not tracked. A restore
        // in flight refuses to start a backup (and vice versa, see begin()).
        $begin    = $this->tracker()->begin('backup', $actorId, $actorName, $origin, [
            'filename' => $filename,
            'kind'     => $kind,
        ]);
        $tracking = false;

        if (! $begin['ok']) {
            return $this->createFailure((string) $begin['error'], $kind, $actorId, $actorName, $origin);
        }

        $tracking = (bool) $begin['tracked'];

        if ($tracking) {
            $this->tracker()->stage('dump');
        }

        $dump = $this->runDump($path, $this->tick());

        if (! $dump['ok']) {
            @unlink($path);
            $this->trackerFinish($tracking, 'failed', (string) $dump['error']);

            return $this->createFailure((string) $dump['error'], $kind, $actorId, $actorName, $origin, $dump['attempts']);
        }

        if ($tracking) {
            $this->tracker()->stage('verify');
        }

        $inspect = $this->inspect($path, $this->tick());

        if (! $inspect['ok']) {
            @unlink($path);
            $this->trackerFinish($tracking, 'failed', (string) $inspect['error']);

            return $this->createFailure(
                'The dump was rejected by the integrity check: ' . (string) $inspect['error'],
                $kind, $actorId, $actorName, $origin, $dump['attempts']
            );
        }

        if ($dump['profile'] !== 'full') {
            $warnings[] = 'Used the "' . $dump['profile'] . '" mysqldump option profile '
                . '(some optional data such as stored routines may not be included).';
        }

        $row = [
            'filename'        => $filename,
            'kind'            => $kind,
            'size_bytes'      => $inspect['size'],
            'tables_count'    => $inspect['tables'],
            'checksum_sha256' => $inspect['checksum'],
            'dump_profile'    => (string) $dump['profile'],
            'server_version'  => $this->serverVersion(),
            'created_by'      => $actorId,
            'created_by_name' => $actorName !== '' ? mb_substr($actorName, 0, 191) : null,
            'verified_at'     => date('Y-m-d H:i:s'),
            'verify_result'   => 'ok',
            'notes'           => $kind === 'pre' ? 'Automatic safety backup taken before a restore.' : null,
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        if ($tracking) {
            $this->tracker()->stage('register');
        }

        try {
            $id = (int) $this->model->insert($row, true);
        } catch (Throwable $e) {
            @unlink($path);
            log_message('error', 'Database backup could not be registered: ' . $e->getMessage());
            $this->trackerFinish($tracking, 'failed', 'The backup could not be registered: ' . $e->getMessage());

            return $this->createFailure(
                'The backup file was created but could not be registered: ' . $e->getMessage(),
                $kind, $actorId, $actorName, $origin
            );
        }

        $row['id'] = $id;
        $this->writeMeta($row);

        $duration = round(microtime(true) - $started, 1);

        $this->audit(
            'system.backup_created',
            'success',
            'Database backup created: ' . $filename
                . ' (' . $this->formatBytes($inspect['size']) . ', ' . $inspect['tables'] . ' table(s))',
            $actorId,
            $actorName,
            [
                'backup_id'        => $id,
                'filename'         => $filename,
                'kind'             => $kind,
                'size_bytes'       => $inspect['size'],
                'tables_count'     => $inspect['tables'],
                'checksum_sha256'  => $inspect['checksum'],
                'dump_profile'     => $dump['profile'],
                'duration_seconds' => $duration,
                'origin'           => $origin,
            ]
        );

        // Only after a successful dump: keep the newest `retentionCount` backups.
        if ($tracking) {
            $this->tracker()->stage('rotate');
        }

        $rotation = $this->rotate($actorId, $actorName, $origin, $protectFilenames);

        $this->trackerFinish($tracking, 'ok', 'Backup created: ' . $filename);

        return [
            'ok'               => true,
            'error'            => null,
            'backup'           => $row,
            'rotation'         => $rotation,
            'duration_seconds' => $duration,
            'profile'          => (string) $dump['profile'],
            'warnings'         => $warnings,
        ];
    }

    /**
     * Marks the tracked operation as finished (no-op when untracked). The
     * tracker itself never throws; this keeps every exit path of create() and
     * the restore pipeline symmetrical.
     *
     * @param array<string, mixed> $extra
     */
    private function trackerFinish(bool $tracking, string $result, string $detail = '', array $extra = []): void
    {
        if (! $tracking) {
            return;
        }

        $this->tracker()->finish($result, $detail, $extra);
    }

    /**
     * Audits and packages a creation failure. No file is left behind and no
     * existing backup is touched.
     *
     * @param list<array<string,mixed>> $attempts
     *
     * @return array{ok:false,error:string,backup:null,rotation:null,duration_seconds:float,profile:null,warnings:list<string>}
     */
    private function createFailure(
        string $error,
        string $kind,
        ?int $actorId,
        string $actorName,
        string $origin,
        array $attempts = []
    ): array {
        $this->audit('system.backup_failed', 'failure', 'Database backup failed (' . $kind . '): ' . $error, $actorId, $actorName, [
            'kind'     => $kind,
            'error'    => mb_substr($error, 0, 500),
            'attempts' => $attempts,
            'origin'   => $origin,
        ]);

        return [
            'ok'               => false,
            'error'            => $error,
            'backup'           => null,
            'rotation'         => null,
            'duration_seconds' => 0.0,
            'profile'          => null,
            'warnings'         => [],
        ];
    }

    // -------------------------------------------------------------------------
    // mysqldump / mysql process handling
    // -------------------------------------------------------------------------

    /**
     * Runs mysqldump, retrying with a leaner option profile when the client or
     * the server rejects an optional flag (never on a credential/connection
     * error — retrying those would only waste time).
     *
     * @return array{ok:bool,error:?string,profile:?string,attempts:list<array<string,mixed>>}
     */
    private function runDump(string $targetPath, ?callable $onTick = null): array
    {
        $binary     = $this->resolveMysqldump();
        $connection = $this->connection();

        if ($binary === null) {
            return ['ok' => false, 'error' => 'The mysqldump client was not found.', 'profile' => null, 'attempts' => []];
        }

        $attempts = [];
        $error    = 'The backup process did not start.';

        foreach (self::DUMP_PROFILES as $profile => $flags) {
            $command = array_merge(
                [$binary],
                $this->connectionArgs($connection),
                $flags,
                ['--default-character-set=utf8mb4', $connection['database']]
            );

            $result = $this->runProcess($command, null, $targetPath, $connection['password'], $this->processTimeout(), $onTick);

            $attempts[] = [
                'profile'   => $profile,
                'exit_code' => $result['exit_code'],
                'error'     => $result['stderr'] !== '' ? $this->firstLine($result['stderr']) : null,
            ];

            if (! $result['timed_out'] && $result['exit_code'] === 0) {
                return ['ok' => true, 'error' => null, 'profile' => $profile, 'attempts' => $attempts];
            }

            $error = $result['timed_out']
                ? 'The dump timed out after ' . $this->processTimeout() . ' seconds.'
                : ($result['stderr'] !== '' ? $this->firstLine($result['stderr']) : 'mysqldump exited with code ' . $result['exit_code'] . '.');

            // Remove the partial file before the next attempt.
            @unlink($targetPath);

            if ($result['timed_out'] || ! $this->isRetryableDumpError($result['stderr'])) {
                break;
            }
        }

        return ['ok' => false, 'error' => $error, 'profile' => null, 'attempts' => $attempts];
    }

    /**
     * True when the stderr output looks like an unsupported option or a missing
     * privilege, i.e. a leaner profile may still succeed.
     */
    private function isRetryableDumpError(string $stderr): bool
    {
        if ($stderr === '') {
            return false;
        }

        if (preg_match('/Access denied for user|Unknown database|Can\'t connect|Connection refused|timed out/i', $stderr) === 1) {
            return false;
        }

        return preg_match(
            '/unknown (?:option|variable)|SHOW_ROUTINE|PROCESS privilege|SUPER or SET_USER_ID|Access denied; you need|ERROR 1227|ERROR 1044|ERROR 1142|ERROR 1419|is not allowed/i',
            $stderr
        ) === 1;
    }

    /**
     * Connection arguments for mysqldump/mysql. The password is deliberately
     * absent — it is passed through MYSQL_PWD in the process environment only.
     *
     * @param array{driver:string,host:string,port:int,user:string,password:string,database:string} $connection
     *
     * @return list<string>
     */
    private function connectionArgs(array $connection): array
    {
        $args = [];

        if ($connection['host'] !== '') {
            $args[] = '--host=' . $connection['host'];
        }

        if ($connection['port'] > 0) {
            $args[] = '--port=' . $connection['port'];
        }

        if ($connection['user'] !== '') {
            $args[] = '--user=' . $connection['user'];
        }

        return $args;
    }

    /**
     * Executes a client binary without a shell.
     *
     * The command is an argv array (never a string), the descriptor for stdout
     * can be a file so PHP never buffers the dump, and stdin can be a file so a
     * restore is streamed straight into the client. stderr is captured with a
     * hard cap so a hostile database cannot flood memory or the log.
     *
     * @param list<string> $command
     * @param string|null  $stdinPath  File piped into the process (restore), or null.
     * @param string|null  $stdoutPath File the process writes to (dump), or null (discarded).
     *
     * @return array{exit_code:int,stderr:string,timed_out:bool}
     */
    private function runProcess(array $command, ?string $stdinPath, ?string $stdoutPath, string $password, int $timeoutSeconds, ?callable $onTick = null): array
    {
        if (! function_exists('proc_open')) {
            return ['exit_code' => -1, 'stderr' => 'proc_open() is disabled on this server.', 'timed_out' => false];
        }

        $descriptors = [
            0 => $stdinPath !== null ? ['file', $stdinPath, 'rb'] : ['pipe', 'r'],
            1 => $stdoutPath !== null ? ['file', $stdoutPath, 'wb'] : ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // bypass_shell keeps cmd.exe out of the picture on Windows; a shell can
        // therefore never reinterpret an argument (no command injection).
        $options = ['bypass_shell' => true];

        $process = @proc_open($command, $descriptors, $pipes, null, $this->processEnvironment($password), $options);

        if (! is_resource($process)) {
            // Surface the real reason when PHP refuses to start the client. The
            // warning text never contains a credential (the password is not on
            // argv), so it is safe to keep.
            $error   = error_get_last();
            $details = isset($error['message']) && is_string($error['message']) ? ' ' . $error['message'] : '';

            return [
                'exit_code' => -1,
                'stderr'    => 'The database client process could not be started.' . $details,
                'timed_out' => false,
            ];
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $cap      = max(1024, (int) $this->config->maxDiagnosticBytes);
        $captured = '';
        $timedOut = false;
        $started  = time();
        $final    = null;
        $readable = [];

        foreach ([1, 2] as $index) {
            if (isset($pipes[$index]) && is_resource($pipes[$index])) {
                stream_set_blocking($pipes[$index], false);
                $readable[$index] = $pipes[$index];
            }
        }

        while ($readable !== []) {
            $read = array_values($readable);
            $write = null;
            $except = null;

            if (@stream_select($read, $write, $except, 0, 250000) === false) {
                break;
            }

            foreach ($read as $stream) {
                $chunk = fread($stream, 65536);

                if ($chunk === false) {
                    $key = array_search($stream, $readable, true);

                    if ($key !== false) {
                        unset($readable[$key]);
                    }

                    continue;
                }

                if ($chunk !== '') {
                    if (strlen($captured) < $cap) {
                        $captured .= substr($chunk, 0, $cap - strlen($captured));
                    }

                    continue;
                }

                if (feof($stream)) {
                    $key = array_search($stream, $readable, true);

                    if ($key !== false) {
                        unset($readable[$key]);
                    }
                }
            }

            $status = proc_get_status($process);

            if (! $status['running']) {
                $final = (int) $status['exitcode'];
                break;
            }

            if ($timeoutSeconds > 0 && (time() - $started) > $timeoutSeconds) {
                $timedOut = true;
                @proc_terminate($process, 9);
                break;
            }

            if ($onTick !== null) {
                $onTick();
            }
        }

        foreach ($readable as $stream) {
            if (is_resource($stream)) {
                $rest = (string) stream_get_contents($stream);

                if ($rest !== '' && strlen($captured) < $cap) {
                    $captured .= substr($rest, 0, $cap - strlen($captured));
                }

                fclose($stream);
            }
        }

        $closeCode = @proc_close($process);
        $exitCode  = ($final !== null && $final >= 0) ? $final : (int) $closeCode;

        if ($timedOut && $exitCode === 0) {
            $exitCode = -1;
        }

        return [
            'exit_code' => $exitCode,
            'stderr'    => $this->cleanDiagnostics($captured),
            'timed_out' => $timedOut,
        ];
    }

    /**
     * Environment for a client process: the current environment plus MYSQL_PWD
     * (never argv), with the essential variables restored when the SAPI exposes
     * a stripped environment (proc_open replaces the whole environment).
     *
     * @return array<string, string>
     */
    private function processEnvironment(string $password): array
    {
        $environment = getenv();

        if (! is_array($environment)) {
            $environment = [];
        }

        unset($environment['MYSQL_PWD'], $environment['MYSQL_PWD_FILE']);

        foreach (self::ESSENTIAL_ENV as $key) {
            if (($environment[$key] ?? '') === '') {
                $fallback = $_SERVER[$key] ?? ($_ENV[$key] ?? null);

                if (is_string($fallback) && $fallback !== '') {
                    $environment[$key] = $fallback;
                }
            }
        }

        if ($password !== '') {
            $environment['MYSQL_PWD'] = $password;
        }

        return $environment;
    }

    /**
     * Client diagnostics: control characters removed and capped. The password is
     * never part of the client's output (it is not on argv).
     */
    private function cleanDiagnostics(string $output): string
    {
        $output = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', trim($output));

        return trim(mb_substr($output, 0, max(200, (int) $this->config->maxDiagnosticBytes)));
    }

    private function firstLine(string $text): string
    {
        $line = trim(strtok($text, "\n") ?: $text);

        return mb_substr($line, 0, 300);
    }

    private function serverVersion(): ?string
    {
        try {
            $version = Database::connect()->getVersion();

            return is_string($version) && $version !== '' ? mb_substr($version, 0, 191) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) max(0, $bytes);
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return ($index === 0 ? (string) (int) $value : number_format($value, $value < 10 ? 2 : 1)) . ' ' . $units[$index];
    }

    // -------------------------------------------------------------------------
    // Retention (30-backup rotation)
    // -------------------------------------------------------------------------

    /**
     * Rows that have to be deleted to stay inside the retention window.
     *
     * Pure and deterministic so the rule can be unit-tested: `$rows` must be
     * ordered oldest first. The slice always starts at the OLDEST row, so the
     * newest backup (the one just created) is never returned while $max >= 1.
     * Because deletion is oldest-first, whatever daily granularity exists in the
     * window is preserved for as long as the window allows.
     *
     * @param list<array<string, mixed>> $rowsOldestFirst
     *
     * @return list<array<string, mixed>>
     */
    public static function planRotation(array $rowsOldestFirst, int $max): array
    {
        $max    = max(1, $max);
        $excess = count($rowsOldestFirst) - $max;

        if ($excess <= 0) {
            return [];
        }

        return array_slice(array_values($rowsOldestFirst), 0, $excess);
    }

    /**
     * Applies the retention window: oldest backups first, until the rolling
     * maximum is respected. Called only after a successful backup, so a failed
     * run can never remove the previous valid backup.
     *
     * @return array{deleted:int,kept:int,max:int,files_removed:int,error:?string,items:list<array<string,mixed>>}
     */
    public function rotate(?int $actorId = null, string $actorName = '', string $origin = 'cli', array $protectedFilenames = []): array
    {
        $max = $this->retentionCount();

        try {
            $rows     = $this->model->getOldestFirst();
            $toDelete = [];

            foreach (self::planRotation($rows, $max) as $row) {
                // Never delete a dump that a running operation still depends on
                // (e.g. the file a restore is about to read). The next backup
                // run will remove it once the window is exceeded again.
                if (in_array((string) ($row['filename'] ?? ''), $protectedFilenames, true)) {
                    continue;
                }

                $toDelete[] = $row;
            }

            $removed  = 0;
            $items    = [];

            foreach ($toDelete as $row) {
                $filename = (string) ($row['filename'] ?? '');
                $path     = $this->filePath($filename);
                $fileGone = $path === null;

                if ($path !== null) {
                    $fileGone = @unlink($path);

                    if (! $fileGone) {
                        log_message('warning', 'Rotation could not delete the backup file: ' . $filename);
                    }

                    @unlink($path . self::META_SUFFIX);
                }

                $this->model->deleteRecord((int) $row['id']);

                if ($fileGone) {
                    $removed++;
                }

                $items[] = [
                    'backup_id'    => (int) $row['id'],
                    'filename'     => $filename,
                    'file_removed' => (bool) $fileGone,
                ];

                $this->audit(
                    'system.backup_rotated',
                    $fileGone ? 'success' : 'failure',
                    'Retention: removed the oldest database backup ' . $filename
                        . ($fileGone ? '' : ' (the file could not be deleted and needs manual cleanup)'),
                    $actorId,
                    $actorName,
                    [
                        'backup_id'   => (int) $row['id'],
                        'filename'    => $filename,
                        'file_removed' => (bool) $fileGone,
                        'reason'      => 'retention window of ' . $max . ' backup(s)',
                        'origin'      => $origin,
                    ]
                );
            }

            return [
                'deleted'       => count($items),
                'kept'          => max(0, count($rows) - count($items)),
                'max'           => $max,
                'files_removed' => $removed,
                'error'         => null,
                'items'         => $items,
            ];
        } catch (Throwable $e) {
            log_message('error', 'Backup rotation failed: ' . $e->getMessage());

            return [
                'deleted' => 0, 'kept' => 0, 'max' => $max, 'files_removed' => 0,
                'error' => $e->getMessage(), 'items' => [],
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Verify
    // -------------------------------------------------------------------------

    /**
     * Recomputes the checksum and re-reads the structural markers of one dump.
     *
     * @return array{ok:bool,error:?string,row:?array<string,mixed>,checksum:?string,size:int,tables:int,status:string}
     */
    public function verify(int $id, ?int $actorId = null, string $actorName = '', string $origin = 'web'): array
    {
        $row = $this->model->find($id);

        if (! is_array($row)) {
            return ['ok' => false, 'error' => 'Backup not found.', 'row' => null, 'checksum' => null, 'size' => 0, 'tables' => 0, 'status' => 'missing'];
        }

        $check = $this->integrityCheck($row);
        $filename = (string) ($row['filename'] ?? '');

        if (! $check['ok']) {
            $this->model->markVerified($id, $check['status']);
            $this->audit(
                'system.backup_verification_failed',
                'failure',
                'Integrity check failed for ' . $filename . ': ' . $check['error'],
                $actorId,
                $actorName,
                [
                    'backup_id'       => $id,
                    'filename'        => $filename,
                    'status'          => $check['status'],
                    'stored_checksum' => (string) ($row['checksum_sha256'] ?? ''),
                    'actual_checksum' => $check['checksum'],
                    'origin'          => $origin,
                ]
            );

            return [
                'ok' => false, 'error' => $check['error'], 'row' => $row,
                'checksum' => $check['checksum'], 'size' => $check['size'], 'tables' => $check['tables'],
                'status' => $check['status'],
            ];
        }

        $this->model->markVerified($id, 'ok');
        $this->audit('system.backup_verified', 'success', 'Integrity verified for ' . $filename, $actorId, $actorName, [
            'backup_id' => $id,
            'filename'  => $filename,
            'checksum'  => $check['checksum'],
            'size'      => $check['size'],
            'tables'    => $check['tables'],
            'origin'    => $origin,
        ]);

        return [
            'ok' => true, 'error' => null, 'row' => $row,
            'checksum' => $check['checksum'], 'size' => $check['size'], 'tables' => $check['tables'], 'status' => 'ok',
        ];
    }

    // -------------------------------------------------------------------------
    // Restore
    // -------------------------------------------------------------------------

    /**
     * Restores one registered backup (the admin panel path).
     *
     * @return array{ok:bool,error:?string,safety_backup:?array<string,mixed>,duration_seconds:float}
     */
    public function restoreById(int $id, ?int $actorId = null, string $actorName = '', string $origin = 'web'): array
    {
        $row = $this->model->find($id);

        if (! is_array($row)) {
            return ['ok' => false, 'error' => 'Backup not found.', 'safety_backup' => null, 'duration_seconds' => 0.0];
        }

        return $this->performRestore($row, $actorId, $actorName, $origin);
    }

    /**
     * Restores a dump by filename (disaster recovery from the CLI, where the
     * registry row itself may be gone).
     *
     * The checksum reference comes from the registry when the row still exists,
     * otherwise from the `<filename>.meta.json` sidecar. A file with no checksum
     * reference is refused: an unverifiable dump is never restored.
     *
     * @return array{ok:bool,error:?string,safety_backup:?array<string,mixed>,duration_seconds:float}
     */
    public function restoreFile(string $filename, ?int $actorId = null, string $actorName = '', string $origin = 'cli'): array
    {
        if (! self::isSafeFilename($filename)) {
            return ['ok' => false, 'error' => 'The backup filename is not valid.', 'safety_backup' => null, 'duration_seconds' => 0.0];
        }

        $row = $this->model->findByFilename($filename);

        if ($row === null) {
            $meta = $this->readMeta($filename);

            if ($meta === null || ($meta['checksum_sha256'] ?? '') === '') {
                return [
                    'ok' => false,
                    'error' => 'No checksum reference is available for ' . $filename . ' (it is not registered and has no .meta.json sidecar), so it cannot be verified.',
                    'safety_backup' => null, 'duration_seconds' => 0.0,
                ];
            }

            $row = [
                'id'              => null,
                'filename'        => $filename,
                'kind'            => (string) ($meta['kind'] ?? 'manual'),
                'checksum_sha256' => (string) $meta['checksum_sha256'],
                'size_bytes'      => (int) ($meta['size_bytes'] ?? 0),
                'created_at'      => (string) ($meta['created_at'] ?? ''),
            ];
        }

        return $this->performRestore($row, $actorId, $actorName, $origin);
    }

    /**
     * The restore pipeline, in a fixed order:
     *
     *  1. availability of the mysql client;
     *  2. integrity gate (existence + structure + SHA-256 against the stored
     *     checksum) — an unverifiable dump is never restored;
     *  3. automatic pre-restore SAFETY backup — if it fails, nothing is restored;
     *  4. stream the dump into the mysql client;
     *  5. audit the outcome (success or failure) with the safety backup id.
     *
     * @param array<string, mixed> $row
     *
     * @return array{ok:bool,error:?string,safety_backup:?array<string,mixed>,duration_seconds:float}
     */
    private function performRestore(array $row, ?int $actorId, string $actorName, string $origin): array
    {
        $filename     = (string) ($row['filename'] ?? '');
        $backupId     = isset($row['id']) ? (int) $row['id'] : null;
        $availability = $this->isAvailable();

        if (! $availability['canRestore']) {
            return [
                'ok' => false,
                'error' => 'Restoring is unavailable on this server: '
                    . implode(' ', array_merge($availability['issues'], $availability['warnings'])),
                'safety_backup' => null, 'duration_seconds' => 0.0,
            ];
        }

        // Concurrency gate + progress tracking. Two restores (or a restore and
        // a backup) must never write to the database at the same time, and the
        // admin page uses the same slot to render live progress.
        $begin = $this->tracker()->begin('restore', $actorId, $actorName, $origin, ['filename' => $filename]);

        if (! $begin['ok']) {
            return [
                'ok' => false, 'error' => (string) $begin['error'],
                'safety_backup' => null, 'duration_seconds' => 0.0,
            ];
        }

        $tracking = (bool) $begin['tracked'];

        // 1. Integrity gate — refuse a missing, truncated or modified dump.
        if ($tracking) {
            $this->tracker()->stage('verify');
        }

        $check = $this->integrityCheck($row, $this->tick());

        if (! $check['ok']) {
            if ($backupId !== null) {
                $this->model->markVerified($backupId, $check['status']);
            }

            $this->audit(
                'system.backup_restore_blocked',
                'blocked',
                'Restore refused for ' . $filename . ': ' . $check['error'],
                $actorId,
                $actorName,
                [
                    'backup_id' => $backupId,
                    'filename'  => $filename,
                    'status'    => $check['status'],
                    'error'     => $check['error'],
                    'origin'    => $origin,
                ]
            );

            $this->trackerFinish($tracking, 'failed', 'Restore refused — ' . (string) $check['error']);

            return [
                'ok' => false, 'error' => 'Restore refused — ' . $check['error'],
                'safety_backup' => null, 'duration_seconds' => 0.0,
            ];
        }

        // 2. Safety backup first: a restore is irreversible without one.
        // The dump about to be restored is protected from this rotation pass.
        if ($tracking) {
            $this->tracker()->stage('safety');
        }

        $safety = $this->create('pre', $actorId, $actorName, $origin, [$filename]);

        if (! $safety['ok'] || $safety['backup'] === null) {
            $this->audit(
                'system.backup_restore_failed',
                'failure',
                'Restore aborted because the pre-restore safety backup failed: ' . (string) $safety['error'],
                $actorId,
                $actorName,
                [
                    'backup_id' => $backupId,
                    'filename'  => $filename,
                    'reason'    => 'safety_backup_failed',
                    'error'     => $safety['error'],
                    'origin'    => $origin,
                ]
            );

            $this->trackerFinish($tracking, 'failed', 'Safety backup failed: ' . (string) $safety['error']);

            return [
                'ok' => false,
                'error' => 'Restore aborted: the pre-restore safety backup failed (' . (string) $safety['error'] . '). The database was left untouched.',
                'safety_backup' => null, 'duration_seconds' => 0.0,
            ];
        }

        // 3. Stream the dump into the mysql client.
        $connection = $this->connection();
        $started    = microtime(true);

        @set_time_limit(0);
        @ignore_user_abort(true);

        $command = array_merge(
            [(string) $this->resolveMysql()],
            $this->connectionArgs($connection),
            ['--default-character-set=utf8mb4', $connection['database']]
        );

        if ($tracking) {
            $this->tracker()->stage('restore');
        }

        $result   = $this->runProcess($command, (string) $check['path'], null, $connection['password'], $this->processTimeout(), $this->tick());
        $duration = round(microtime(true) - $started, 1);

        if ($result['timed_out'] || $result['exit_code'] !== 0) {
            $reason = $result['timed_out']
                ? 'The restore timed out after ' . $this->processTimeout() . ' seconds.'
                : ($result['stderr'] !== '' ? $this->firstLine($result['stderr']) : 'mysql exited with code ' . $result['exit_code'] . '.');

            $this->audit(
                'system.backup_restore_failed',
                'failure',
                'Database restore from ' . $filename . ' failed: ' . $reason,
                $actorId,
                $actorName,
                [
                    'backup_id'          => $backupId,
                    'filename'           => $filename,
                    'checksum_sha256'    => $check['checksum'],
                    'safety_backup_id'   => $safety['backup']['id'] ?? null,
                    'safety_backup_file' => $safety['backup']['filename'] ?? null,
                    'error'              => $reason,
                    'duration_seconds'   => $duration,
                    'origin'             => $origin,
                ]
            );

            $this->trackerFinish($tracking, 'failed', $reason);

            return [
                'ok' => false,
                'error' => 'The restore did not complete: ' . $reason
                    . ' A safety backup (' . (string) ($safety['backup']['filename'] ?? '') . ') was taken first and can be restored.',
                'safety_backup' => $safety['backup'],
                'duration_seconds' => $duration,
            ];
        }

        if ($backupId !== null) {
            $this->model->markRestored($backupId);
        }

        // A restore replaces the registry (it lives in the restored database), so
        // the pre-restore safety backup created moments ago may have lost its row.
        // Re-adopt every dump that has a sidecar but no row, otherwise the file
        // would linger untracked and outside the rotation.
        $this->reconcileRegistry($actorId, $actorName, $origin);

        $this->audit(
            'system.backup_restored',
            'success',
            'Database restored from ' . $filename
                . ' (safety backup: ' . (string) ($safety['backup']['filename'] ?? '') . ')',
            $actorId,
            $actorName,
            [
                'backup_id'          => $backupId,
                'filename'           => $filename,
                'checksum_sha256'    => $check['checksum'],
                'safety_backup_id'   => $safety['backup']['id'] ?? null,
                'safety_backup_file' => $safety['backup']['filename'] ?? null,
                'duration_seconds'   => $duration,
                'origin'             => $origin,
            ]
        );

        $this->trackerFinish($tracking, 'ok', 'Restored from ' . $filename);

        return [
            'ok' => true, 'error' => null,
            'safety_backup' => $safety['backup'],
            'duration_seconds' => $duration,
        ];
    }

    // -------------------------------------------------------------------------
    // Registry helpers (delete / list / lookup / sidecar)
    // -------------------------------------------------------------------------

    /**
     * Deletes one backup: file first, then the registry row, always audited.
     *
     * @return array{ok:bool,error:?string,filename:?string,file_removed:bool}
     */
    public function deleteBackup(int $id, ?int $actorId = null, string $actorName = '', string $origin = 'web'): array
    {
        $row = $this->model->find($id);

        if (! is_array($row)) {
            return ['ok' => false, 'error' => 'Backup not found.', 'filename' => null, 'file_removed' => false];
        }

        $filename = (string) ($row['filename'] ?? '');
        $path     = $this->filePath($filename);
        $removed  = $path === null;

        if ($path !== null) {
            $removed = @unlink($path);

            if (! $removed) {
                log_message('warning', 'The backup file could not be deleted: ' . $filename);
            }

            @unlink($path . self::META_SUFFIX);
        }

        $this->model->deleteRecord($id);

        $this->audit(
            $removed ? 'system.backup_deleted' : 'system.backup_delete_failed',
            $removed ? 'success' : 'failure',
            'Database backup deleted: ' . $filename . ($removed ? '' : ' (the file could not be removed and needs manual cleanup)'),
            $actorId,
            $actorName,
            [
                'backup_id'    => $id,
                'filename'     => $filename,
                'file_removed' => (bool) $removed,
                'size_bytes'   => (int) ($row['size_bytes'] ?? 0),
                'origin'       => $origin,
            ]
        );

        return ['ok' => true, 'error' => null, 'filename' => $filename, 'file_removed' => (bool) $removed];
    }

    /**
     * Registered backups, newest first, enriched with the current file state.
     * No checksum is recomputed here (that is `verify`), so the listing stays
     * cheap even with multi-gigabyte dumps.
     *
     * @return list<array<string, mixed>>
     */
    public function listBackups(int $limit = 200): array
    {
        $rows = $this->model->getRecent($limit);

        foreach ($rows as &$row) {
            $path                  = $this->filePath((string) ($row['filename'] ?? ''));
            $row['file_exists']    = $path !== null;
            $row['current_size']   = $path !== null ? (int) filesize($path) : 0;
            $row['meta_exists']    = $path !== null && is_file($path . self::META_SUFFIX);
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->model->find($id);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByFilename(string $filename): ?array
    {
        return $this->model->findByFilename($filename);
    }

    /**
     * The automatic backup of the current calendar day, if it exists. Used by
     * `backup:daily` to create at most one automatic backup per day.
     *
     * @return array<string, mixed>|null
     */
    public function autoBackupForToday(): ?array
    {
        return $this->model->findAutoBackupForDate(date('Y-m-d'));
    }

    public function countBackups(): int
    {
        return $this->model->countBackups();
    }

    /**
     * Re-adopts dump files that exist on disk (with their sidecar) but no longer
     * have a registry row — the normal outcome of a restore, which replaces the
     * whole database, this registry included, with the dump's own contents.
     *
     * Only files matching FILENAME_PATTERN that carry a `.meta.json` checksum
     * sidecar are adopted, so nothing unexpected is ever registered.
     *
     * @return array{adopted:int,scanned:int}
     */
    public function reconcileRegistry(?int $actorId = null, string $actorName = '', string $origin = 'cli'): array
    {
        $result = ['adopted' => 0, 'scanned' => 0];

        try {
            $dir = $this->directory();

            if ($dir === null) {
                return $result;
            }

            foreach (glob($dir . DIRECTORY_SEPARATOR . 'db-*.sql') ?: [] as $path) {
                $filename = basename((string) $path);

                if (! self::isSafeFilename($filename)) {
                    continue;
                }

                $result['scanned']++;

                if ($this->model->findByFilename($filename) !== null) {
                    continue;
                }

                $meta = $this->readMeta($filename);

                if ($meta === null || ($meta['checksum_sha256'] ?? '') === '') {
                    continue;
                }

                $size = (int) @filesize($path);
                $kind = in_array((string) ($meta['kind'] ?? ''), self::KINDS, true) ? (string) $meta['kind'] : 'manual';
                $date = (string) ($meta['created_at'] ?? '');

                $row = [
                    'filename'        => $filename,
                    'kind'            => $kind,
                    'size_bytes'      => $size > 0 ? $size : (int) ($meta['size_bytes'] ?? 0),
                    'tables_count'    => (int) ($meta['tables_count'] ?? 0),
                    'checksum_sha256' => (string) $meta['checksum_sha256'],
                    'dump_profile'    => (string) ($meta['dump_profile'] ?? ''),
                    'server_version'  => $this->serverVersion(),
                    'created_by'      => $actorId,
                    'created_by_name' => $actorName !== '' ? $actorName : 'Restore recovery (re-adopted)',
                    'created_at'      => preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date) === 1 ? $date : date('Y-m-d H:i:s'),
                    'notes'           => 'Re-adopted after a restore replaced the backup registry.',
                ];

                $this->model->insert($row);
                $result['adopted']++;

                $this->audit(
                    'system.backup_adopted',
                    'success',
                    'Re-registered the database backup ' . $filename . ' after a restore replaced the backup registry',
                    $actorId,
                    $actorName,
                    [
                        'filename' => $filename,
                        'kind'     => $kind,
                        'size'     => $row['size_bytes'],
                        'checksum' => $row['checksum_sha256'],
                        'origin'   => $origin,
                    ]
                );
            }
        } catch (Throwable $e) {
            log_message('error', 'Backup registry reconciliation failed: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Sidecar description written next to the dump. It carries the checksum, so
     * a restore stays verifiable even when the registry (which lives in the very
     * database being restored) is gone.
     *
     * @param array<string, mixed> $row
     */
    private function writeMeta(array $row): void
    {
        $path = $this->pathForWrite((string) ($row['filename'] ?? ''));

        if ($path === null) {
            return;
        }

        $meta = [
            'generator'       => 'CSCS Tap n Track',
            'filename'        => (string) $row['filename'],
            'kind'            => (string) ($row['kind'] ?? 'manual'),
            'database'        => $this->connection()['database'],
            'size_bytes'      => (int) ($row['size_bytes'] ?? 0),
            'tables_count'    => (int) ($row['tables_count'] ?? 0),
            'checksum_sha256' => (string) ($row['checksum_sha256'] ?? ''),
            'dump_profile'    => (string) ($row['dump_profile'] ?? ''),
            'created_at'      => (string) ($row['created_at'] ?? ''),
            'created_by'      => (string) ($row['created_by_name'] ?? ''),
        ];

        $json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json !== false) {
            @file_put_contents($path . self::META_SUFFIX, $json . "\n");
        }
    }

    /**
     * Reads a sidecar description; the filename is validated and resolved inside
     * the backup directory before anything is opened.
     *
     * @return array<string, mixed>|null
     */
    public function readMeta(string $filename): ?array
    {
        if (! self::isSafeFilename($filename)) {
            return null;
        }

        $dir = $this->directory();

        if ($dir === null) {
            return null;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $filename . self::META_SUFFIX;

        if (! is_file($path)) {
            return null;
        }

        $real = realpath($path);

        if ($real === false || ! str_starts_with($real, $dir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($real), true);

        return is_array($decoded) ? $decoded : null;
    }
}
















