<?php

namespace App\Commands;

use App\Models\AuditLogModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Retention maintenance for the append-only activity log.
 *
 * Deletes only the OLDEST entries (a prefix of the chain), so the remaining
 * rows still verify; the purge itself is recorded as a system event.
 *
 * Usage:
 *   php spark audit:prune                 (dry run with the configured window)
 *   php spark audit:prune --days=365 --force
 */
class PruneAuditLogs extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'audit:prune';
    protected $description = 'Removes activity log entries older than the retention window (append-only log).';
    protected $usage       = 'audit:prune [--days=365] [--force]';
    protected $options     = [
        '--days'  => 'Retention window in days. Defaults to the audit_retention_days setting (365). Minimum 30.',
        '--force' => 'Actually delete. Without this flag the command only reports what it would remove.',
    ];

    public function run(array $params)
    {
        helper('audit');

        $days = null;
        if (isset($params['days']) && is_numeric($params['days'])) {
            $days = (int) $params['days'];
        }
        $days = $days ?? audit_retention_days();
        $days = max(30, $days);

        $force   = array_key_exists('force', $params) || CLI::getOption('force') !== null;
        $cutoff  = date('Y-m-d H:i:s', time() - ($days * 86400));

        CLI::write('Activity log retention: ' . $days . ' day(s)', 'yellow');
        CLI::write('Cutoff: entries created before ' . $cutoff, 'yellow');

        try {
            $model = new AuditLogModel();

            $oldest = $model->getOldestTimestamp();
            if ($oldest === null) {
                CLI::write('The activity log is empty. Nothing to prune.', 'green');

                return EXIT_SUCCESS;
            }

            $toDelete = (int) $model->builder()->where('created_at <', $cutoff)->countAllResults();

            if ($toDelete === 0) {
                CLI::write('No entries are older than the cutoff. Nothing to prune.', 'green');

                return EXIT_SUCCESS;
            }

            CLI::write($toDelete . ' entr(y/ies) would be removed (oldest entry: ' . $oldest . ').', 'yellow');

            if (! $force) {
                CLI::write('Dry run only. Re-run with --force to delete.', 'red');

                return EXIT_SUCCESS;
            }

            $deleted = $model->purgeOlderThan($cutoff);

            // Trace the purge itself now that space has been freed.
            audit_event('system.retention_purge', [
                'category'    => 'system',
                'status'      => 'success',
                'description' => 'Retention purge removed ' . $deleted . ' entr(y/ies) older than ' . $cutoff,
                'metadata'    => [
                    'deleted'        => $deleted,
                    'retention_days' => $days,
                    'cutoff'         => $cutoff,
                    'source'         => 'spark audit:prune',
                ],
            ]);

            CLI::write($deleted . ' entr(y/ies) deleted.', 'green');

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::error('Audit prune failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }
    }
}
