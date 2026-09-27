<?php

namespace App\Commands;

use App\Libraries\DatabaseBackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Integrity check for every stored database backup.
 *
 * Recomputes the SHA-256 of each dump, re-reads its structural markers and
 * compares the result with the checksum captured when the backup was created
 * (registry row, plus the `.meta.json` sidecar written next to the dump).
 *
 * Suggested schedule (weekly):
 *   0 3 * * 0 /usr/bin/php /path/to/project/spark backup:verify
 *
 * Usage:
 *   php spark backup:verify
 *   php spark backup:verify --id=12
 */
class BackupVerify extends BaseCommand
{
    use Concerns\ReadsCliOptions;

    protected $group       = 'Database';
    protected $name        = 'backup:verify';
    protected $description = 'Verifies the integrity (checksum + structure) of the stored database backups.';
    protected $usage       = 'backup:verify [--id=<n>]';
    protected $options     = [
        '--id' => 'Verify only this backup id.',
    ];

    public function run(array $params)
    {
        helper('audit');

        try {
            /** @var DatabaseBackupService $service */
            $service = service('databaseBackup');

            $idRaw = $this->optionValue($params, 'id');
            $id    = is_numeric($idRaw) ? (int) $idRaw : null;

            if ($id !== null) {
                $targets = [$service->find($id)];
            } else {
                $targets = $service->listBackups(1000);
            }

            if ($targets === [] || $targets === [null]) {
                CLI::write('No backups to verify.', 'yellow');

                return EXIT_SUCCESS;
            }

            $table   = [];
            $failed  = 0;
            $checked = 0;

            foreach ($targets as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $result = $service->verify((int) $row['id'], null, 'System scheduler (backup:verify)', 'cli');
                $checked++;

                if (! $result['ok']) {
                    $failed++;
                }

                $table[] = [
                    'id'         => (int) $row['id'],
                    'filename'   => (string) $row['filename'],
                    'result'     => $result['ok'] ? 'OK' : 'FAILED (' . $result['status'] . ')',
                    'checksum'   => mb_substr((string) ($result['checksum'] ?? ''), 0, 16),
                    'created_at' => (string) $row['created_at'],
                ];
            }

            CLI::table($table, ['id', 'filename', 'result', 'checksum', 'created_at']);
            CLI::write($checked . ' backup(s) checked, ' . $failed . ' failed.', $failed === 0 ? 'green' : 'red');

            if ($failed > 0) {
                CLI::write('A failed backup must NOT be restored; restore an older verified backup instead.', 'yellow');
            }

            return $failed === 0 ? EXIT_SUCCESS : EXIT_ERROR;
        } catch (Throwable $e) {
            CLI::error('Verification failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }
    }
}
