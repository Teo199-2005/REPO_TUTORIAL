<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Repairs the login page's quick-access demo accounts.
 *
 * The controller self-heals a demo account whenever its quick-access button is
 * used, so this command is for the cases where you cannot click through - a
 * fresh deployment, or a server where the login page is behind a cache.
 *
 *   php spark demo:repair
 *
 * Only the hardcoded demo addresses are ever touched (see
 * app/Helpers/demo_accounts_helper.php).
 */
class RepairDemoAccounts extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'demo:repair';
    protected $description = 'Repair the Quick Access demo accounts (missing teacher/student rows, duplicate groups, drifted passwords).';
    protected $usage       = 'demo:repair';

    public function run(array $params)
    {
        if (! demo_accounts_enabled()) {
            CLI::error('Quick Access demo logins are disabled outside non-production environments.');
            CLI::write('Current environment: ' . ENVIRONMENT, 'yellow');

            return EXIT_ERROR;
        }

        CLI::write('Repairing Quick Access demo accounts...', 'yellow');
        CLI::newLine();

        $results = demo_account_repair_all();

        $repaired = 0;
        $ready    = 0;
        $failed   = 0;

        foreach ($results as $roleKey => $result) {
            $fixture = demo_account_fixture($roleKey);
            $email   = (string) ($fixture['email'] ?? $roleKey);

            switch ($result['status']) {
                case 'ok':
                    if ($result['repaired']) {
                        $repaired++;
                        CLI::write('  REPAIRED  ' . str_pad($roleKey, 20) . $email, 'green');
                    } else {
                        $ready++;
                        CLI::write('  OK        ' . str_pad($roleKey, 20) . $email, 'white');
                    }
                    break;

                case 'error':
                    $failed++;
                    CLI::write('  FAILED    ' . str_pad($roleKey, 20) . $email, 'red');
                    CLI::write('            ' . $result['reason'], 'red');
                    break;

                default:
                    $failed++;
                    CLI::write('  UNAVAIL   ' . str_pad($roleKey, 20) . $email, 'red');
                    CLI::write('            ' . $result['reason'], 'red');
                    break;
            }
        }

        CLI::newLine();
        CLI::write(sprintf(
            'Done. %d repaired, %d already healthy, %d failed.',
            $repaired,
            $ready,
            $failed
        ), $failed > 0 ? 'red' : 'green');

        return $failed > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
