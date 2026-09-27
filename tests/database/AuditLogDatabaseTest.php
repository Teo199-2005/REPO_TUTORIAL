<?php

use App\Libraries\AuditLogger;
use App\Models\AuditLogModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * End-to-end behaviour of the activity log against a real (in-memory) database:
 * inserts, hash chaining, redaction, append-only guards, filtering and the
 * retention purge.
 *
 * The audit migration is applied explicitly (instead of $refresh = true) so the
 * test does not depend on every other app migration supporting SQLite, and the
 * migration class is required by path because CI migration files are not
 * PSR-4 autoloadable (see composer.json "exclude-from-classmap").
 *
 * @internal
 */
final class AuditLogDatabaseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;

    private AuditLogger $logger;

    private bool $createdProfileTables = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->db->tableExists('audit_logs')) {
            require_once APPPATH . 'Database/Migrations/2026-09-26-000000_CreateAuditLogsTable.php';

            $migration = new \App\Database\Migrations\CreateAuditLogsTable(\Config\Database::forge('tests'));
            $migration->up();
        }

        $this->logger = new AuditLogger();
    }

    protected function tearDown(): void
    {
        if ($this->db->tableExists('audit_logs')) {
            $this->db->table('audit_logs')->emptyTable();
        }

        if ($this->createdProfileTables) {
            $forge = \Config\Database::forge('tests');

            foreach (['students', 'users'] as $table) {
                if ($this->db->tableExists($table)) {
                    $forge->dropTable($table, true);
                }
            }

            $this->createdProfileTables = false;
        }

        parent::tearDown();
    }

    public function testRecordPersistsAllContextAndVerifiesChain(): void
    {
        $id = $this->logger->record([
            'action'        => 'student.updated',
            'category'      => 'data',
            'status'        => 'success',
            'actor_user_id' => 7,
            'actor_name'    => 'Maria Santos',
            'actor_role'    => 'admin',
            'resource_type' => 'student',
            'resource_id'   => '42',
            'description'   => 'Student record updated',
            'before'        => ['section_id' => '5'],
            'after'         => ['section_id' => '9'],
            'metadata'      => ['changed' => ['section_id']],
            'ip_address'    => '203.0.113.10',
            'user_agent'    => 'PHPUnit/1.0',
            'http_method'   => 'POST',
            'route'         => 'admin/students/update/42',
        ]);

        $this->assertIsInt($id);

        $row = $this->db->table('audit_logs')->where('id', $id)->get()->getRowArray();

        $this->assertSame('student.updated', $row['action']);
        $this->assertSame('data', $row['category']);
        $this->assertSame('success', $row['status']);
        $this->assertSame(7, (int) $row['actor_user_id']);
        $this->assertSame('Maria Santos', $row['actor_name']);
        $this->assertSame('student', $row['resource_type']);
        $this->assertSame('42', $row['resource_id']);
        $this->assertNull($row['prev_hash']);
        $this->assertNotEmpty($row['hash']);

        $result = $this->logger->verifyChain();
        $this->assertTrue($result['valid'], $result['message']);
        $this->assertSame(1, $result['checked']);
    }

    public function testSensitiveValuesAreNeverPersisted(): void
    {
        $id = $this->logger->record([
            'action'   => 'account.password_changed',
            'category' => 'account',
            'before'   => ['password' => 'SuperSecret1', 'api_key' => 'key-123'],
            'after'    => ['password' => 'EvenMoreSecret2'],
            'metadata' => ['password_hash' => password_hash('SuperSecret1', PASSWORD_DEFAULT)],
        ]);

        $row = $this->db->table('audit_logs')->where('id', $id)->get()->getRowArray();

        $this->assertStringNotContainsString('SuperSecret1', (string) $row['before_json']);
        $this->assertStringNotContainsString('key-123', (string) $row['before_json']);
        $this->assertStringNotContainsString('EvenMoreSecret2', (string) $row['after_json']);
        $this->assertStringContainsString(AuditLogger::REDACTED, (string) $row['before_json']);
        $this->assertStringNotContainsString('$2y$', (string) $row['metadata_json']);
    }

    public function testChainLinksConsecutiveRowsAndDetectsDirectTampering(): void
    {
        $first  = $this->logger->record(['action' => 'auth.login', 'category' => 'auth']);
        $second = $this->logger->record(['action' => 'auth.logout', 'category' => 'auth']);

        $rows = $this->db->table('audit_logs')->orderBy('id', 'ASC')->get()->getResultArray();

        $this->assertSame(2, count($rows));
        $this->assertSame($rows[0]['hash'], $rows[1]['prev_hash'], 'each row must chain to the previous one');
        $this->assertTrue($this->logger->verifyChainRows($rows)['valid']);

        // Simulate an attacker rewriting a stored row with SQL (the kind of edit
        // the hash chain is designed to expose).
        $this->db->table('audit_logs')->where('id', $first)->update(['description' => 'fabricated']);

        $result = $this->logger->verifyChain();
        $this->assertFalse($result['valid']);
        $this->assertContains((int) $first, $result['broken_ids']);
        $this->assertNotSame(0, (int) $second);
    }

    public function testModelIsAppendOnly(): void
    {
        $id    = $this->logger->record(['action' => 'student.created', 'category' => 'data']);
        $model = new AuditLogModel();

        $this->assertFalse($model->update($id, ['description' => 'changed']));
        $this->assertFalse($model->delete($id));
        $this->assertNotNull($model->find($id), 'the row must survive a blocked update/delete');

        $row = $model->find($id);
        $this->assertSame('student.created', $row['action']);
    }

    public function testFilteredLogsRespectActionStatusActorAndSearch(): void
    {
        $this->logger->record(['action' => 'auth.login', 'category' => 'auth', 'status' => 'success', 'actor_user_id' => 1, 'actor_name' => 'Admin One', 'description' => 'Signed in']);
        $this->logger->record(['action' => 'auth.login_failed', 'category' => 'auth', 'status' => 'failure', 'description' => 'Sign-in failed for ana']);
        $this->logger->record(['action' => 'student.updated', 'category' => 'data', 'status' => 'success', 'actor_user_id' => 1, 'actor_name' => 'Admin One', 'resource_type' => 'student']);

        $model = new AuditLogModel();

        $authRows = $model->getFilteredLogs(['category' => 'auth'], 50, 1);
        $this->assertSame(2, $authRows['total']);
        $this->assertCount(2, $authRows['rows']);

        $failures = $model->getFilteredLogs(['status' => 'failure'], 50, 1);
        $this->assertSame(1, $failures['total']);
        $this->assertSame('auth.login_failed', $failures['rows'][0]['action']);

        $byActor = $model->getFilteredLogs(['actor' => 1], 50, 1);
        $this->assertSame(2, $byActor['total']);

        $search = $model->getFilteredLogs(['q' => 'ana'], 50, 1);
        $this->assertSame(1, $search['total']);

        $paged = $model->getFilteredLogs([], 2, 2);
        $this->assertSame(2, $paged['per_page']);
        $this->assertSame(2, $paged['total_pages']);
        $this->assertCount(1, $paged['rows']);

        $actions = $model->getDistinctActions();
        $this->assertContains('student.updated', $actions);

        $types = $model->getDistinctResourceTypes();
        $this->assertContains('student', $types);
    }

    public function testRetentionPurgeRemovesOnlyTheOldestRowsAndKeepsChainVerifiable(): void
    {
        $this->logger->record(['action' => 'auth.login', 'category' => 'auth']);

        // Backdate the oldest row beyond the retention window.
        $this->db->table('audit_logs')->update(['created_at' => date('Y-m-d H:i:s', time() - (400 * 86400))]);

        $this->logger->record(['action' => 'auth.logout', 'category' => 'auth']);
        $this->logger->record(['action' => 'auth.login', 'category' => 'auth']);

        $model   = new AuditLogModel();
        $cutoff  = date('Y-m-d H:i:s', time() - (365 * 86400));
        $deleted = $model->purgeOlderThan($cutoff);

        $this->assertSame(1, $deleted);

        $rows = $this->db->table('audit_logs')->orderBy('id', 'ASC')->get()->getResultArray();
        $this->assertCount(2, $rows);

        // The surviving segment still verifies: its first row anchors the chain.
        $result = $this->logger->verifyChainRows($rows);
        $this->assertTrue($result['valid'], $result['message']);
    }

    public function testDisplayResolutionMapsALegacyHashedActorNameToTheStudentName(): void
    {
        $this->createActorProfileTables();

        $hash = '$2y$10$' . str_repeat('aB', 26) . 'Z';

        $this->db->table('users')->insert([
            'id' => 2180, 'email' => '', 'username' => null, 'first_name' => null, 'last_name' => null,
        ]);
        $this->db->table('students')->insert([
            'user_id' => 2180, 'first_name' => 'Mark', 'middle_name' => 'Mqweqwe',
            'last_name' => 'Loyd', 'suffix' => null,
        ]);

        // A legacy row: the stored actor name is the identity secret (a hash).
        $this->db->table('audit_logs')->insert([
            'actor_user_id' => 2180,
            'actor_name'    => $hash,
            'actor_role'    => 'student',
            'action'        => 'auth.login',
            'category'      => 'auth',
            'status'        => 'success',
            'hash'          => str_repeat('a', 64),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insertID();

        $rows     = $this->db->table('audit_logs')->where('id', $id)->get()->getResultArray();
        $resolved = audit_resolve_actor_names($rows);

        $this->assertSame('Mark Mqweqwe Loyd', $resolved[0]['actor_name']);

        // The append-only row itself must never be rewritten.
        $stored = $this->db->table('audit_logs')->where('id', $id)->get()->getRowArray();
        $this->assertSame($hash, $stored['actor_name']);
    }

    public function testActorSearchMatchesResolvedDisplayNames(): void
    {
        $this->createActorProfileTables();

        $this->db->table('users')->insert([
            'id' => 2180, 'email' => '', 'username' => null, 'first_name' => null, 'last_name' => null,
        ]);
        $this->db->table('students')->insert([
            'user_id' => 2180, 'first_name' => 'Mark', 'middle_name' => 'Mqweqwe',
            'last_name' => 'Loyd', 'suffix' => null,
        ]);

        $this->assertContains(2180, audit_actor_ids_matching('Mark Loyd'));
        $this->assertContains(2180, audit_actor_ids_matching('loyd'));
        $this->assertNotContains(2180, audit_actor_ids_matching('Nobody Here'));
    }

    /**
     * Minimal users/students tables so actor resolution can be exercised
     * without applying migrations that SQLite may not support.
     */
    private function createActorProfileTables(): void
    {
        if (! $this->db->tableExists('users')) {
            $forge = \Config\Database::forge('tests');
            $forge->addField([
                'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
                'email'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'username'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'last_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('users', true);
        }

        if (! $this->db->tableExists('students')) {
            $forge = \Config\Database::forge('tests');
            $forge->addField([
                'id'          => ['type' => 'INTEGER', 'auto_increment' => true],
                'user_id'     => ['type' => 'INTEGER', 'null' => true],
                'first_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'middle_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'last_name'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'suffix'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('students', true);
        }

        $this->createdProfileTables = true;
    }
}
