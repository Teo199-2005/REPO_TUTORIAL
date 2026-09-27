<?php

use App\Libraries\BackupOperationTracker;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Behaviour of the progress / concurrency tracker that backs the admin page's
 * live progress panel and the "never run two restores at once" guard.
 *
 * Everything here is pure filesystem behaviour — no database, no processes.
 *
 * @internal
 */
final class BackupOperationTrackerTest extends CIUnitTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup-tracker-test-' . bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0700, true);
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
    // Claim / progress
    // ---------------------------------------------------------------------

    public function testBeginClaimsTheSlotAndReportsTheInitialStage(): void
    {
        $tracker = $this->tracker();

        $begin = $tracker->begin('backup', 7, 'Adviser Admin', 'web', ['kind' => 'manual']);

        $this->assertTrue($begin['ok']);
        $this->assertTrue($begin['tracked']);
        $this->assertFileExists($tracker->path());

        $state = $tracker->current();

        $this->assertNotNull($state);
        $this->assertTrue($state['running']);
        $this->assertFalse($state['stale']);
        $this->assertFalse($state['finished']);
        $this->assertSame('backup', $state['operation']);
        $this->assertSame('starting', $state['stage']);
        $this->assertSame('Adviser Admin', $state['actor_name']);
        $this->assertSame('manual', $state['kind']);
        $this->assertSame(getmypid(), $state['pid']);

        $this->assertTrue($tracker->ownedByCurrentProcess(), 'the process that claimed the slot owns it');
        $this->assertTrue($tracker->isBusy(), 'a live operation is busy');
    }

    public function testStageTransitionsAndHeartbeatKeepTheSlotFresh(): void
    {
        $tracker = $this->tracker();
        $tracker->begin('backup', 7, 'Admin', 'web');

        $tracker->stage('dump');
        $tracker->stage('verify');
        $tracker->stage('register');

        $state = $tracker->current();

        $this->assertSame('register', $state['stage']);
        $this->assertSame('Registering the backup', $state['stage_label']);
        $this->assertTrue($state['running']);

        // A heartbeat only refreshes updated_at; the stage is unchanged.
        $tracker->heartbeat();

        $this->assertSame('register', $tracker->current()['stage']);
    }

    public function testFinishRecordsTheOutcomeAndStopsTheOperation(): void
    {
        $tracker = $this->tracker();
        $tracker->begin('restore', 7, 'Admin', 'web');
        $tracker->stage('restore');
        $tracker->finish('failed', 'mysql exited with code 1.');

        $state = $tracker->current();

        $this->assertFalse($state['running']);
        $this->assertTrue($state['finished']);
        $this->assertFalse($state['stale']);
        $this->assertSame('failed', $state['result']);
        $this->assertSame('failed', $state['stage']);
        $this->assertSame('mysql exited with code 1.', $state['detail']);

        $this->assertFalse($tracker->ownedByCurrentProcess(), 'a finished operation releases the process ownership');
        $this->assertFalse($tracker->isBusy(), 'a finished operation no longer blocks');
    }

    // ---------------------------------------------------------------------
    // Concurrency guard
    // ---------------------------------------------------------------------

    public function testALiveOperationFromAnotherProcessBlocksARestore(): void
    {
        $tracker = $this->tracker();
        $this->writeForeignState(['operation' => 'backup', 'stage' => 'dump']);

        $begin = $tracker->begin('restore', 9, 'Me', 'web');

        $this->assertFalse($begin['ok']);
        $this->assertStringContainsString('already running', (string) $begin['error']);
        $this->assertStringContainsString('Other Admin', (string) $begin['error']);
    }

    public function testABackupIsRefusedWhileAnotherProcessRestores(): void
    {
        $tracker = $this->tracker();
        $this->writeForeignState(['operation' => 'restore', 'stage' => 'restore']);

        $begin = $tracker->begin('backup', 9, 'Me', 'web');

        $this->assertFalse($begin['ok']);
        $this->assertStringContainsString('starting a backup', (string) $begin['error']);
    }

    public function testTwoBackupsMayRunInParallel(): void
    {
        $tracker = $this->tracker();
        $this->writeForeignState(['operation' => 'backup', 'stage' => 'dump']);

        $begin = $tracker->begin('backup', 9, 'Me', 'web');

        $this->assertTrue($begin['ok']);
        $this->assertTrue($begin['tracked'], 'the newest backup takes over the progress slot');
    }

    public function testAnAbandonedOperationGoesStaleAndNoLongerBlocks(): void
    {
        $tracker = $this->tracker();
        $this->writeForeignState(['updated_at' => time() - (BackupOperationTracker::STALE_SECONDS + 60)]);

        $state = $tracker->current();

        $this->assertFalse($state['running']);
        $this->assertTrue($state['stale']);

        $begin = $tracker->begin('restore', 9, 'Me', 'web');

        $this->assertTrue($begin['ok']);
        $this->assertTrue($begin['tracked']);
    }

    public function testAFinishedOperationDoesNotBlockANewOne(): void
    {
        $tracker = $this->tracker();
        $tracker->begin('backup', 7, 'Admin', 'web');
        $tracker->finish('ok', 'Backup created');

        $begin = $tracker->begin('restore', 7, 'Admin', 'web');

        $this->assertTrue($begin['ok']);
        $this->assertTrue($begin['tracked']);
    }

    public function testANestedOperationFromTheSameProcessKeepsTheParentSlot(): void
    {
        $tracker = $this->tracker();

        $parent = $tracker->begin('restore', 7, 'Admin', 'web', ['filename' => 'db-manual-1.sql']);
        $tracker->stage('safety');

        // The pre-restore safety backup runs in the same process:
        $nested = $tracker->begin('backup', 7, 'Admin', 'web', ['kind' => 'pre']);

        $this->assertTrue($parent['tracked']);
        $this->assertTrue($nested['ok']);
        $this->assertFalse($nested['tracked'], 'the nested operation must not report stages of its own');

        $tracker->stage('restore');

        $state = $tracker->current();

        $this->assertSame('restore', $state['operation'], 'the parent restore still owns the slot');
        $this->assertSame('restore', $state['stage']);
        $this->assertSame('db-manual-1.sql', $state['filename']);
    }

    public function testAProcessCannotUpdateASlotAnotherProcessTookOver(): void
    {
        $tracker = $this->tracker();
        $tracker->begin('backup', 7, 'Admin', 'web');
        $tracker->stage('dump');

        // Another backup overwrote the slot with its own token:
        $this->writeForeignState(['operation' => 'backup', 'stage' => 'verify']);

        $tracker->stage('register');

        $state = $tracker->current();

        $this->assertSame('verify', $state['stage'], 'the foreign state must not be clobbered');
    }
    // ---------------------------------------------------------------------
    // Robustness
    // ---------------------------------------------------------------------

    public function testAnUnwritableLocationRunsUntrackedInsteadOfFailing(): void
    {
        $tracker = new BackupOperationTracker($this->tempDir . DIRECTORY_SEPARATOR . 'missing-subdir');

        $begin = $tracker->begin('backup', 7, 'Admin', 'web');

        $this->assertTrue($begin['ok'], 'a tracker failure must never fail the backup');
        $this->assertFalse($begin['tracked']);
        $this->assertNull($tracker->current());

        // Stage / heartbeat / finish stay silent no-ops.
        $tracker->stage('dump');
        $tracker->heartbeat();
        $tracker->finish('ok');

        $this->assertNull($tracker->current());
    }

    public function testACorruptSidecarIsIgnoredInsteadOfBreaking(): void
    {
        $tracker = $this->tracker();
        file_put_contents($tracker->path(), '{not json');

        $this->assertNull($tracker->current());

        $begin = $tracker->begin('backup', 7, 'Admin', 'web');

        $this->assertTrue($begin['ok']);
        $this->assertTrue($begin['tracked']);
    }

    public function testDisplayValuesAreCleanedAndBounded(): void
    {
        $tracker = $this->tracker();
        $tracker->begin('backup', 7, "Admin\x07 \n Name", 'web', ['filename' => str_repeat('x', 300) . '.sql']);

        $state = $tracker->current();

        $this->assertStringNotContainsString("\x07", $state['actor_name']);
        $this->assertSame('Admin Name', trim($state['actor_name']));
        $this->assertLessThanOrEqual(191, strlen($state['filename']));
    }

    public function testStageLabelsCoverEveryServiceStage(): void
    {
        foreach (['starting', 'dump', 'verify', 'register', 'rotate', 'safety', 'restore', 'done', 'failed'] as $stage) {
            $this->assertNotSame($stage, BackupOperationTracker::stageLabel($stage), 'every stage needs a human label');
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function tracker(): BackupOperationTracker
    {
        return new BackupOperationTracker($this->tempDir);
    }

    /**
     * Simulates an operation recorded by ANOTHER process (different pid).
     *
     * @param array<string, mixed> $overrides
     */
    private function writeForeignState(array $overrides = []): void
    {
        $state = array_merge([
            'version'    => 1,
            'token'      => bin2hex(random_bytes(8)),
            'pid'        => getmypid() + 1000,
            'operation'  => 'backup',
            'stage'      => 'dump',
            'result'     => '',
            'detail'     => '',
            'actor_id'   => 5,
            'actor_name' => 'Other Admin',
            'origin'     => 'web',
            'filename'   => 'db-auto-20260926-020001-abcdef12.sql',
            'kind'       => 'auto',
            'started_at' => time() - 30,
            'updated_at' => time() - 1,
        ], $overrides);

        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . BackupOperationTracker::FILENAME, json_encode($state));
    }
}


