<?php

use App\Libraries\DatabaseBackupService;
use App\Models\DatabaseBackupModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Registry behaviour against a real (in-memory SQLite) database: the migration,
 * the queries the dashboard and the rotation rely on, and the "the row may be
 * gone after a restore" case.
 *
 * The migration is applied explicitly (like tests/database/AuditLogDatabaseTest)
 * so the test does not depend on every other app migration supporting SQLite,
 * and the class is required by path because CI migration files are not
 * PSR-4 autoloadable.
 *
 * @internal
 */
final class DatabaseBackupModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;

    private DatabaseBackupModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->db->tableExists('database_backups')) {
            require_once APPPATH . 'Database/Migrations/2026-09-26-000001_CreateDatabaseBackupsTable.php';

            $migration = new \App\Database\Migrations\CreateDatabaseBackupsTable(\Config\Database::forge('tests'));
            $migration->up();
        }

        $this->model = new DatabaseBackupModel();
    }

    protected function tearDown(): void
    {
        if ($this->db->tableExists('database_backups')) {
            $this->db->table('database_backups')->emptyTable();
        }

        parent::tearDown();
    }

    public function testMigrationCreatesTheRegistryTableWithItsColumns(): void
    {
        $this->assertTrue($this->db->tableExists('database_backups'));

        foreach ([
            'id', 'filename', 'kind', 'size_bytes', 'tables_count', 'checksum_sha256',
            'dump_profile', 'server_version', 'created_by', 'created_by_name',
            'verified_at', 'verify_result', 'restored_at', 'restore_count', 'notes', 'created_at',
        ] as $column) {
            $this->assertTrue($this->db->fieldExists($column, 'database_backups'), $column . ' must exist');
        }
    }

    public function testListingIsNewestFirstAndRotationOrderIsOldestFirst(): void
    {
        $oldest = $this->insertRow(['created_at' => '2026-09-01 02:00:00', 'size_bytes' => 100]);
        $middle = $this->insertRow(['created_at' => '2026-09-02 02:00:00', 'size_bytes' => 200]);
        $newest = $this->insertRow(['created_at' => '2026-09-03 02:00:00', 'size_bytes' => 300]);

        $this->assertSame(
            [$newest, $middle, $oldest],
            array_map('intval', array_column($this->model->getRecent(), 'id'))
        );

        $this->assertSame(
            [$oldest, $middle, $newest],
            array_map('intval', array_column($this->model->getOldestFirst(), 'id'))
        );

        $this->assertSame(3, $this->model->countBackups());
        $this->assertSame(600, $this->model->getTotalSize());
        $this->assertSame($newest, (int) $this->model->getNewest()['id']);
        $this->assertSame($oldest, (int) $this->model->getOldest()['id']);
    }

    public function testAutoBackupForDateOnlyFindsAutomaticBackupsOfThatDay(): void
    {
        $today = date('Y-m-d');

        $manual = $this->insertRow(['kind' => 'manual', 'created_at' => $today . ' 01:00:00']);
        $auto   = $this->insertRow(['kind' => 'auto', 'created_at' => $today . ' 02:00:00']);

        $this->assertSame($auto, (int) $this->model->findAutoBackupForDate($today)['id'], 'the automatic backup of that day wins');
        $this->assertNotSame($manual, (int) $this->model->findAutoBackupForDate($today)['id']);
        $this->assertNull($this->model->findAutoBackupForDate('2020-01-01'));
        $this->assertNull($this->model->findAutoBackupForDate('not-a-date'));
    }

    public function testFindByFilenameAndDeleteRecord(): void
    {
        $id  = $this->insertRow();
        $row = $this->db->table('database_backups')->where('id', $id)->get()->getRowArray();

        $this->assertSame($id, (int) $this->model->findByFilename((string) $row['filename'])['id']);
        $this->assertNull($this->model->findByFilename('db-auto-19700101-000000-deadbeef.sql'));
        $this->assertNull($this->model->findByFilename(''));

        $this->assertTrue($this->model->deleteRecord($id));
        $this->assertSame(0, $this->model->countBackups());
    }

    public function testVerifyAndRestoreBookkeeping(): void
    {
        $id = $this->insertRow();

        $this->assertTrue($this->model->markVerified($id, 'checksum_mismatch'));
        $row = $this->db->table('database_backups')->where('id', $id)->get()->getRowArray();
        $this->assertSame('checksum_mismatch', $row['verify_result']);
        $this->assertNotNull($row['verified_at']);

        $this->assertTrue($this->model->markRestored($id));
        $row = $this->db->table('database_backups')->where('id', $id)->get()->getRowArray();
        $this->assertSame(1, (int) $row['restore_count']);
        $this->assertNotNull($row['restored_at']);

        // A restore replaces the whole database, so a row may vanish mid-flight:
        // that must be a clean "false", never a PHP error.
        $this->assertFalse($this->model->markRestored(999999));
        $this->assertFalse($this->model->markVerified(999999, 'ok'));
    }

    public function testRotationPlanOverRegisteredRowsKeepsTheNewestThirty(): void
    {
        for ($i = 0; $i < 31; $i++) {
            $this->insertRow(['created_at' => date('Y-m-d H:i:s', strtotime('-31 days +' . $i . ' days'))]);
        }

        $rows = $this->model->getOldestFirst();
        $plan = DatabaseBackupService::planRotation($rows, 30);

        $this->assertCount(1, $plan);
        $this->assertSame((int) $rows[0]['id'], (int) $plan[0]['id']);
        $this->assertSame((int) $rows[count($rows) - 1]['id'], (int) $this->model->getNewest()['id']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function insertRow(array $overrides = []): int
    {
        return (int) $this->model->insert(array_merge([
            'filename'        => sprintf('db-auto-%s-%s-%s.sql', date('Ymd'), date('His'), bin2hex(random_bytes(4))),
            'kind'            => 'auto',
            'size_bytes'      => 1024,
            'tables_count'    => 10,
            'checksum_sha256' => bin2hex(random_bytes(32)),
            'dump_profile'    => 'full',
            'created_by_name' => 'PHPUnit',
            'created_at'      => date('Y-m-d H:i:s'),
        ], $overrides), true);
    }
}

