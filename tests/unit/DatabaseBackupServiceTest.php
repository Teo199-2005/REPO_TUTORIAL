<?php

use App\Libraries\DatabaseBackupService;
use App\Models\DatabaseBackupModel;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Backup;

/**
 * Pure behaviour of the backup engine: filename/path safety, rotation planning,
 * dump inspection (the integrity gate used before a restore) and the settings
 * guards. No database and no mysqldump process is involved.
 *
 * @internal
 */
final class DatabaseBackupServiceTest extends CIUnitTestCase
{
    private DatabaseBackupService $service;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // The model is only used for the pure/inspection helpers here, so the
        // constructor that would open a database connection is skipped.
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'db-backup-test-' . bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0700, true);

        $config            = new Backup();
        $config->backupDir = $this->tempDir;

        $this->service = new DatabaseBackupService($config, $this->stubModel());
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->tempDir);

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // Filenames and paths
    // ---------------------------------------------------------------------

    public function testGeneratedNamesAreAccepted(): void
    {
        foreach (['auto', 'manual', 'pre'] as $kind) {
            $name = sprintf('db-%s-%s-%s-%s.sql', $kind, date('Ymd'), date('His'), bin2hex(random_bytes(4)));

            $this->assertTrue(DatabaseBackupService::isSafeFilename($name), $name . ' must be accepted');
        }
    }

    public function testTraversalAndUnexpectedNamesAreRejected(): void
    {
        $hostile = [
            '',
            '.',
            '..',
            '../../.env',
            '..\\..\\lphs_sms_live_import.sql',
            '/etc/passwd',
            'C:\\Windows\\win.ini',
            'db-auto-20260926-120000-abcdef12.sql/../../.env',
            'db-auto-20260926-120000-abcdef12.sql.meta.json',
            'db-auto-20260926-120000-abcdef1.sql',   // suffix too short
            'db-auto-20260926-120000-ABCDEF12.sql',  // uppercase hex
            'db-other-20260926-120000-abcdef12.sql', // unknown kind
            'evil.sql',
            str_repeat('a', 200) . '.sql',
        ];

        foreach ($hostile as $name) {
            $this->assertFalse(DatabaseBackupService::isSafeFilename($name), var_export($name, true) . ' must be rejected');
        }
    }

    public function testFilePathOnlyResolvesExistingSafeFilesInsideTheBackupDirectory(): void
    {
        $name = sprintf('db-manual-%s-%s-%s.sql', date('Ymd'), date('His'), bin2hex(random_bytes(4)));
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . $name, "SELECT 1;\n");

        $this->assertSame(
            realpath($this->tempDir . DIRECTORY_SEPARATOR . $name),
            $this->service->filePath($name)
        );

        // Valid shape but the file does not exist.
        $this->assertNull($this->service->filePath(sprintf('db-manual-%s-%s-%s.sql', date('Ymd'), date('His'), 'deadbeef')));

        // Traversal / absolute / malformed names never resolve.
        foreach (['../../.env', '..\\..\\lphs_sms_live_import.sql', '/etc/passwd', 'C:\\Windows\\win.ini', 'evil.sql'] as $hostile) {
            $this->assertNull($this->service->filePath($hostile), $hostile . ' must not resolve');
        }
    }

    // ---------------------------------------------------------------------
    // Rotation planning
    // ---------------------------------------------------------------------

    public function testPlanRotationKeepsTheWindowAndDeletesOldestFirst(): void
    {
        $rows = [];

        for ($i = 1; $i <= 35; $i++) {
            $rows[] = ['id' => $i, 'filename' => sprintf('db-auto-202609%02d-020000-deadbeef.sql', $i)];
        }

        $plan = DatabaseBackupService::planRotation($rows, 30);

        $this->assertCount(5, $plan);
        $this->assertSame([1, 2, 3, 4, 5], array_column($plan, 'id'), 'the oldest five must be removed');
        $this->assertNotContains(35, array_column($plan, 'id'), 'the newest backup must never be removed');
    }

    public function testPlanRotationDoesNothingInsideTheWindow(): void
    {
        $rows = [['id' => 1], ['id' => 2], ['id' => 3]];

        $this->assertSame([], DatabaseBackupService::planRotation($rows, 30));
        $this->assertSame([], DatabaseBackupService::planRotation($rows, 3));
    }

    public function testPlanRotationKeepsAtLeastTheNewestBackup(): void
    {
        $rows = [['id' => 1], ['id' => 2], ['id' => 3]];

        $plan = DatabaseBackupService::planRotation($rows, 1);

        $this->assertSame([1, 2], array_column($plan, 'id'));
        $this->assertNotContains(3, array_column($plan, 'id'));
    }

    // ---------------------------------------------------------------------
    // Dump inspection (integrity gate)
    // ---------------------------------------------------------------------

    public function testInspectAcceptsACompleteDump(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'dump.sql';
        file_put_contents($path, $this->dumpContents(3));

        $result = $this->service->inspect($path);

        $this->assertTrue($result['ok'], (string) $result['error']);
        $this->assertSame(3, $result['tables']);
        $this->assertSame(hash_file('sha256', $path), $result['checksum']);
        $this->assertGreaterThan(0, $result['size']);
    }

    public function testInspectRejectsATruncatedDump(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'truncated.sql';
        $dump = $this->dumpContents(2);
        file_put_contents($path, substr($dump, 0, (int) (strlen($dump) * 0.8)));

        $result = $this->service->inspect($path);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Dump completed', (string) $result['error']);
    }

    public function testInspectRejectsForeignEmptyAndMissingFiles(): void
    {
        $html = $this->tempDir . DIRECTORY_SEPARATOR . 'page.sql';
        file_put_contents($html, "<html><body>CREATE TABLE nope</body></html>\n");

        $empty = $this->tempDir . DIRECTORY_SEPARATOR . 'empty.sql';
        file_put_contents($empty, '');

        $this->assertFalse($this->service->inspect($html)['ok']);
        $this->assertFalse($this->service->inspect($empty)['ok']);
        $this->assertFalse($this->service->inspect($this->tempDir . DIRECTORY_SEPARATOR . 'missing.sql')['ok']);
    }

    public function testInspectCountsTablesSplitAcrossChunkBoundaries(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'boundary.sql';

        // Put one CREATE TABLE exactly on the 1 MiB read boundary, so it can only
        // be counted by the carry logic between chunks.
        $header   = "-- MySQL dump 10.13\n--\n-- Host: localhost\n";
        $boundary = 1048576;
        $padding  = max(0, $boundary - strlen($header) - 6);
        $dump     = $header . str_repeat('-', $padding) . "\nCREATE TABLE `boundary` (id INT);\n"
            . "CREATE TABLE `after` (id INT);\n--\n-- Dump completed on " . date('Y-m-d H:i:s') . "\n";

        file_put_contents($path, $dump);

        $result = $this->service->inspect($path);

        $this->assertTrue($result['ok'], (string) $result['error']);
        $this->assertSame(2, $result['tables'], 'a definition straddling the chunk boundary must be counted exactly once');
    }

    // ---------------------------------------------------------------------
    // Settings guards
    // ---------------------------------------------------------------------

    public function testRetentionFallsBackToThirtyWhenTheOverrideIsMisconfigured(): void
    {
        $config = new Backup();

        $config->retentionCount = 0; // what an empty `backup.retentionCount =` becomes
        $this->assertSame(30, $this->serviceWithConfig($config)->retentionCount());

        $config->retentionCount = 7;
        $this->assertSame(7, $this->serviceWithConfig($config)->retentionCount());
    }

    public function testProcessTimeoutFallsBackToOneHourWhenMisconfigured(): void
    {
        $config = new Backup();

        $config->processTimeout = 0;
        $this->assertSame(3600, $this->serviceWithConfig($config)->processTimeout());

        $config->processTimeout = 120;
        $this->assertSame(120, $this->serviceWithConfig($config)->processTimeout());
    }

    public function testOperationStatusIsNullAndFailSafeWithoutATrackedOperation(): void
    {
        $this->assertNull($this->service->operationStatus(), 'no sidecar means no operation to report');

        // A corrupt sidecar must be ignored, never fatal.
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . '.operation.json', '{broken');

        $this->assertNull($this->service->operationStatus());
    }

    public function testInspectionCallsTheProgressTickWhileHashing(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'tick.sql';
        file_put_contents($path, $this->dumpContents(3));

        $ticks = 0;
        $result = $this->service->inspect($path, static function () use (&$ticks): void {
            $ticks++;
        });

        $this->assertTrue($result['ok'], (string) $result['error']);
        $this->assertGreaterThan(0, $ticks, 'the heartbeat callback must run while the file is being read');
    }

    public function testFormatBytesIsHumanReadable(): void
    {
        $this->assertSame('0 B', DatabaseBackupService::formatBytes(0));
        $this->assertSame('512 B', DatabaseBackupService::formatBytes(512));
        $this->assertSame('1.00 KB', DatabaseBackupService::formatBytes(1024));
        $this->assertSame('1.50 MB', DatabaseBackupService::formatBytes(1572864));
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function stubModel(): DatabaseBackupModel
    {
        return new class extends DatabaseBackupModel {
            public function __construct()
            {
            }
        };
    }

    private function serviceWithConfig(Backup $config): DatabaseBackupService
    {
        return new DatabaseBackupService($config, $this->stubModel());
    }

    /**
     * Minimal file that satisfies every marker mysqldump writes.
     */
    private function dumpContents(int $tables): string
    {
        $dump = "-- MySQL dump 10.13  Distrib 8.0.36, for Win64 (x86_64)\n--\n-- Host: localhost    Database: cscs_sms\n--\n\n";

        for ($i = 1; $i <= $tables; $i++) {
            $dump .= "DROP TABLE IF EXISTS `t{$i}`;\nCREATE TABLE `t{$i}` (\n  `id` int NOT NULL\n);\nINSERT INTO `t{$i}` VALUES (1);\n";
        }

        return $dump . "--\n-- Dump completed on " . date('Y-m-d H:i:s') . "\n";
    }
}

