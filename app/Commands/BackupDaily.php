<?php

namespace App\Commands;

use App\Libraries\DatabaseBackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Scheduled database backup.
 *
 * The frequency and the time of day are configured by an administrator under
 * Administration → Backup & Restore; `backup.enabled` / `backup.scheduleHour`
 * in .env are only the defaults used until the admin saves a schedule.
 *
 * Idempotent per schedule slot: with a daily plan at most ONE automatic backup
 * is created per calendar day, with every-N-hours plans one per N-hour window,
 * with an hourly plan one per hour and with a weekly plan one per week. The
 * command may therefore run more often than the plan (interval plans are
 * served best by an hourly cron) and a missed run — server off, deployment —
 * is caught up on the next one.
 *
 *   Linux (crontab -e), daily plan at 02:00:
 *     0 2 * * * /usr/bin/php /path/to/project/spark backup:daily >> /dev/null 2>&1
 *   Linux, hourly / every-N-hours plan (the minute matches the configured time):
 *     0 * * * * /usr/bin/php /path/to/project/spark backup:daily >> /dev/null 2>&1
 *
 *   Windows (Task Scheduler): program `php`, arguments
 *     "C:\path\to\project\spark" backup:daily
 *
 * Retention: after a successful dump the service keeps the newest 30 backups
 * and removes the oldest ones first. A failed dump leaves every existing backup
 * untouched.
 *
 * Usage:
 *   php spark backup:daily
 *   php spark backup:daily --force      (ignore "already backed up in this slot")
 *   php spark backup:daily --list       (only show the stored backups)
 */
class BackupDaily extends BaseCommand
{
    use Concerns\ReadsCliOptions;

    protected $group       = 'Database';
    protected $name        = 'backup:daily';
    protected $description = 'Creates the scheduled database backup (daily, every N hours, hourly or weekly) and applies the rolling rotation window.';
    protected $usage       = 'backup:daily [--force] [--list]';
    protected $options     = [
        '--force' => 'Create a backup even when an automatic backup already exists for today.',
        '--list'  => 'Print the stored backups and exit without creating a new one.',
    ];

    public function run(array $params)
    {
        helper('audit');

        try {
            /** @var DatabaseBackupService $service */
            $service = service('databaseBackup');

            $schedule     = $service->scheduleSettings();
            $availability = $service->isAvailable();

            CLI::write('Database : ' . ($availability['database'] !== '' ? $availability['database'] : '(not configured)'), 'yellow');
            CLI::write('Directory: ' . ($availability['directory'] ?? $service->configuredDirectory()), 'yellow');
            CLI::write('Retention: ' . $service->retentionCount() . ' backup(s) kept', 'yellow');
            CLI::write('Schedule : ' . $schedule['label'] . ($schedule['enabled'] ? '' : ' (paused)'), 'yellow');

            if ($this->hasFlag($params, 'list')) {
                return $this->printInventory($service);
            }

            if (! $availability['canBackup']) {
                foreach ($availability['issues'] as $issue) {
                    CLI::error($issue);
                }

                return EXIT_ERROR;
            }

            if (! $schedule['enabled']) {
                CLI::write('The automatic backup schedule is paused in the admin settings. Nothing to do.', 'yellow');

                return EXIT_SUCCESS;
            }

            $force = $this->hasFlag($params, 'force');

            if (! $force) {
                $state = $service->scheduleState();

                if (! $state['due']) {
                    CLI::write('An automatic backup for the current schedule window already exists.', 'green');

                    if ($state['next'] !== null) {
                        CLI::write('Next run: ' . date('D Y-m-d H:i', (int) $state['next'])
                            . ' (use --force to create another backup now).');
                    }

                    return EXIT_SUCCESS;
                }
            }

            CLI::write('Creating the database backup ...', 'yellow');

            $result = $service->create('auto', null, 'System scheduler (backup:daily)', 'cli');

            if (! $result['ok']) {
                CLI::error('Backup failed: ' . (string) $result['error']);
                CLI::write('Existing backups were left untouched.', 'yellow');

                return EXIT_ERROR;
            }

            $backup = $result['backup'] ?? [];

            CLI::write('Backup created: ' . (string) ($backup['filename'] ?? ''), 'green');
            CLI::write('  size    : ' . $service->formatBytes((int) ($backup['size_bytes'] ?? 0)), 'green');
            CLI::write('  tables  : ' . (int) ($backup['tables_count'] ?? 0), 'green');
            CLI::write('  profile : ' . (string) ($result['profile'] ?? ''), 'green');
            CLI::write('  seconds : ' . (string) ($result['duration_seconds'] ?? 0), 'green');

            foreach ($result['warnings'] as $warning) {
                CLI::write($warning, 'yellow');
            }

            $rotation = $result['rotation'];

            if (is_array($rotation) && ($rotation['deleted'] ?? 0) > 0) {
                CLI::write('Rotation removed ' . $rotation['deleted'] . ' old backup(s); '
                    . $rotation['kept'] . ' kept (maximum ' . $rotation['max'] . ').', 'yellow');
            } else {
                CLI::write('Rotation: nothing to remove (window not exceeded).', 'green');
            }

            if (is_array($rotation) && ($rotation['error'] ?? null) !== null) {
                CLI::error('Rotation reported an error: ' . $rotation['error']);
            }

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::error('Backup command failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }
    }

    private function printInventory(DatabaseBackupService $service): int
    {
        $rows = $service->listBackups(1000);

        if ($rows === []) {
            CLI::write('No backups stored yet.', 'yellow');

            return EXIT_SUCCESS;
        }

        $table = [];

        foreach ($rows as $row) {
            $table[] = [
                'id'         => (int) $row['id'],
                'filename'   => (string) $row['filename'],
                'kind'       => (string) $row['kind'],
                'size'       => $service->formatBytes((int) $row['size_bytes']),
                'file'       => ! empty($row['file_exists']) ? 'ok' : 'MISSING',
                'created_at' => (string) $row['created_at'],
            ];
        }

        CLI::table($table, ['id', 'filename', 'kind', 'size', 'file', 'created_at']);

        return EXIT_SUCCESS;
    }
}
