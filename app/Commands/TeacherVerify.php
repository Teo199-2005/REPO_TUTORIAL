<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Models\UserModel;

/**
 * TEMPORARY verification for the rejected-teacher access fix.
 * Run: php spark teacher:verify
 */
class TeacherVerify extends BaseCommand
{
    protected $group       = 'Debug';
    protected $name        = 'teacher:verify';
    protected $description = 'TEMP - verify rejected/orphaned teacher accounts are blocked';

    private array $created = ['users' => [], 'teachers' => []];

    public function run(array $params)
    {
        helper(['auth', 'teacher_access']);

        $db = \Config\Database::connect();

        CLI::write('=== 1. Helper wiring ===', 'yellow');
        foreach ([
            'teacher_account_state',
            'teacher_account_allowed',
            'teacher_account_denial_message',
            'teacher_record_state',
            'find_teacher_record_for_user',
        ] as $fn) {
            CLI::write('  ' . $fn . ': ' . (function_exists($fn) ? 'OK' : 'MISSING'), function_exists($fn) ? 'green' : 'red');
        }

        CLI::write("\n=== 2. Record-state matrix (pure function) ===", 'yellow');
        $cases = [
            'pending row'  => ['registration_status' => 'pending', 'employment_status' => 'inactive'],
            'rejected row' => ['registration_status' => 'rejected', 'employment_status' => 'inactive'],
            'approved row' => ['registration_status' => 'approved', 'employment_status' => 'active'],
            'resigned row' => ['registration_status' => 'approved', 'employment_status' => 'resigned'],
            'no row'       => null,
        ];
        foreach ($cases as $label => $row) {
            $state    = teacher_record_state($row);
            $expected = match ($label) {
                'pending row'  => 'pending',
                'rejected row' => 'rejected',
                'approved row' => 'approved',
                'resigned row' => 'inactive',
                default        => 'no_record',
            };
            CLI::write(sprintf('  %-14s => %-10s %s', $label, $state, $state === $expected ? 'OK' : 'FAIL (expected ' . $expected . ')'), $state === $expected ? 'green' : 'red');
        }

        CLI::write("\n=== 3. Existing orphan teacher-group accounts in the DB ===", 'yellow');
        $orphans = $db->query(
            "SELECT u.id, u.email, u.active,
                    (SELECT COUNT(*) FROM auth_groups_users g WHERE g.user_id = u.id AND g.`group`='teacher') grp_rows,
                    (SELECT COUNT(*) FROM auth_identities i WHERE i.user_id = u.id AND i.secret2 IS NOT NULL) pw_rows
             FROM users u
             WHERE EXISTS (SELECT 1 FROM auth_groups_users g WHERE g.user_id = u.id AND g.`group`='teacher')
               AND NOT EXISTS (SELECT 1 FROM teachers t WHERE t.user_id = u.id)"
        )->getResultArray();

        if ($orphans === []) {
            CLI::write('  (none)', 'green');
        }

        foreach ($orphans as $orphan) {
            $user = model(UserModel::class)->find((int) $orphan['id']);
            if (! $user) {
                CLI::write('  user ' . $orphan['id'] . ': could not load', 'red');
                continue;
            }
            $state   = teacher_account_state($user);
            $allowed = teacher_account_allowed($user);
            $ok      = ($state === 'no_record' && ! $allowed);

            CLI::write(sprintf(
                '  user %-5s grp=%s pw=%s active=%s => state=%-10s allowed=%-3s %s',
                $orphan['id'],
                $orphan['grp_rows'],
                $orphan['pw_rows'],
                $orphan['active'],
                $state,
                $allowed ? 'YES' : 'no',
                $ok ? 'OK (blocked)' : 'CHECK'
            ), $ok ? 'green' : 'red');
        }

        CLI::write("\n=== 4. Live scenario: pending / rejected / deleted ===", 'yellow');
        foreach (['pending', 'rejected', 'deleted'] as $scenario) {
            $this->scenario($scenario, $db);
        }

        $this->cleanup($db);

        CLI::write("\n=== 5. Existing approved teachers must still be allowed ===", 'yellow');
        $users = $db->query(
            "SELECT DISTINCT u.id
             FROM users u
             JOIN auth_groups_users g ON g.user_id = u.id AND g.`group`='teacher'
             JOIN teachers t ON t.user_id = u.id
             WHERE t.registration_status='approved' AND t.employment_status='active'
             LIMIT 5"
        )->getResultArray();

        foreach ($users as $row) {
            $user = model(UserModel::class)->find((int) $row['id']);
            if (! $user) {
                continue;
            }
            $state   = teacher_account_state($user);
            $allowed = teacher_account_allowed($user);
            $ok      = ($state === 'approved' && $allowed);

            CLI::write(sprintf('  user %-5s => state=%-9s allowed=%-3s %s', $row['id'], $state, $allowed ? 'YES' : 'no', $ok ? 'OK' : 'FAIL'), $ok ? 'green' : 'red');
        }
    }

    private function scenario(string $scenario, $db): void
    {
        $suffix = 'zzverify' . $scenario . random_int(1000, 9999);
        $email  = $suffix . '@verify.test';
        $now    = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'email'      => $email,
            'username'   => $suffix,
            'active'     => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = (int) $db->insertID();

        if ($userId <= 0) {
            CLI::write('  ' . $scenario . ': could not create user', 'red');

            return;
        }

        $db->table('auth_identities')->insert([
            'user_id'    => $userId,
            'type'       => 'email_password',
            'name'       => $email,
            'secret'     => $email,
            'secret2'    => password_hash('Verify123!', PASSWORD_DEFAULT),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('auth_groups_users')->insert([
            'user_id'    => $userId,
            'group'      => 'teacher',
            'created_at' => $now,
        ]);

        $db->table('teachers')->insert([
            'user_id'             => $userId,
            'first_name'          => 'Verify',
            'last_name'           => ucfirst($scenario),
            'gender'              => 'Male',
            'email'               => $email,
            'position'            => 'Teacher',
            'employment_status'   => 'inactive',
            'registration_status' => $scenario === 'pending' ? 'pending' : ($scenario === 'rejected' ? 'rejected' : 'approved'),
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $teacherId = (int) $db->insertID();

        $this->created['users'][] = $userId;

        if ($scenario === 'deleted') {
            // Simulate what TeacherModel::delete($id, true) leaves behind when
            // only the teachers row is removed but the login account survives.
            $db->table('teachers')->where('id', $teacherId)->delete();
            $teacherId = 0;
        } else {
            $this->created['teachers'][] = $teacherId;
        }

        $user = model(UserModel::class)->find($userId);

        if (! $user) {
            CLI::write('  ' . $scenario . ': user disappeared', 'red');

            return;
        }

        $state   = teacher_account_state($user);
        $allowed = teacher_account_allowed($user);

        $expectedState = match ($scenario) {
            'pending'  => 'pending',
            'rejected' => 'rejected',
            default    => 'no_record',
        };

        $pass = ($state === $expectedState && ! $allowed);

        CLI::write(sprintf(
            '  %-9s user=%-6s teacher=%-6s => state=%-10s allowed=%-3s %s',
            $scenario,
            $userId,
            $teacherId > 0 ? $teacherId : '-',
            $state,
            $allowed ? 'YES' : 'no',
            $pass ? 'OK (blocked)' : 'FAIL (expected ' . $expectedState . ')'
        ), $pass ? 'green' : 'red');

        if ($scenario === 'rejected') {
            CLI::write('      message: ' . teacher_account_denial_message($state), 'white');
        }
    }

    private function cleanup($db): void
    {
        foreach ($this->created['users'] as $id) {
            $db->table('users')->where('id', $id)->delete();
        }

        foreach ($this->created['teachers'] as $id) {
            if ($id > 0) {
                $db->table('teachers')->where('id', $id)->delete();
            }
        }

        CLI::write("\n  cleaned up " . count($this->created['users']) . ' verification user(s).', 'yellow');
    }
}