<?php

namespace App\Commands;

use App\Libraries\DatabaseBackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Disaster-recovery restore from the command line.
 *
 * This is the path of last resort for when the admin area itself cannot be used
 * (e.g. the database was dropped). It runs exactly the same pipeline as the
 * admin panel:
 *
 *   1. the dump must pass the integrity check (structure + SHA-256 against the
 *      registered checksum, or the `.meta.json` sidecar when the registry row is
 *      gone);
 *   2. a pre-restore safety backup is created automatically — if it fails, the
 *      restore is aborted and the database is left untouched;
 *   3. the dump is streamed into the `mysql` client;
 *   4. the whole operation is written to the activity log.
 *
 * The explicit `--confirm=RESTORE` token is mandatory because the command is
 * destructive and irreversible without the safety backup.
 *
 * Usage:
 *   php spark backup:restore --id=12 --confirm=RESTORE
 *   php spark backup:restore --file=db-auto-20260926-020001-ab12cd34.sql --confirm=RESTORE
 */
class BackupRestore extends BaseCommand
{
    use Concerns\ReadsCliOptions;

    protected $group       = 'Database';
    protected $name        = 'backup:restore';
    protected $description = 'Restores the database from a stored backup (creates a safety backup first and verifies integrity).';
    protected $usage       = 'backup:restore --id=<n>|--file=<name> --confirm=RESTORE';
    protected $options     = [
        '--id'      => 'Backup id shown by "php spark backup:daily --list" or on the admin page.',
        '--file'    => 'Dump filename inside the backup directory (used when the registry is unavailable).',
        '--confirm' => 'Must be exactly RESTORE.',
    ];

    public function run(array $params)
    {
        helper('audit');

        try {
            /** @var DatabaseBackupService $service */
            $service = service('databaseBackup');

            $confirm = strtoupper(trim((string) ($this->optionValue($params, 'confirm') ?? '')));

            if ($confirm !== 'RESTORE') {
                CLI::error('Restore aborted: pass --confirm=RESTORE to confirm this destructive operation.');

                return EXIT_ERROR;
            }

            $idRaw = $this->optionValue($params, 'id');
            $id    = is_numeric($idRaw) ? (int) $idRaw : null;
            $file  = $this->optionValue($params, 'file');
            $file  = $file !== null ? trim($file) : null;

            if ($id === null && ($file === null || $file === '')) {
                CLI::error('Restore aborted: provide --id=<n> (registered backup) or --file=<name>.');

                return EXIT_ERROR;
            }

            $availability = $service->isAvailable();

            if (! $availability['canRestore']) {
                foreach (array_merge($availability['issues'], $availability['warnings']) as $issue) {
                    CLI::error($issue);
                }

                return EXIT_ERROR;
            }

            CLI::write('Target database: ' . $availability['database'], 'yellow');
            CLI::write('Taking a safety backup and verifying the dump first ...', 'yellow');

            $actor = 'CLI operator (backup:restore)';

            $result = $id !== null
                ? $service->restoreById($id, null, $actor, 'cli')
                : $service->restoreFile((string) $file, null, $actor, 'cli');

            if (! $result['ok']) {
                CLI::error('Restore failed: ' . (string) $result['error']);

                if ($result['safety_backup'] !== null) {
                    CLI::write('A safety backup is available: ' . (string) ($result['safety_backup']['filename'] ?? ''), 'yellow');
                }

                return EXIT_ERROR;
            }

            CLI::write('Restore completed in ' . (string) $result['duration_seconds'] . ' second(s).', 'green');
            CLI::write('Pre-restore safety backup: ' . (string) ($result['safety_backup']['filename'] ?? '(none)'), 'green');

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::error('Restore command failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }
    }
}
