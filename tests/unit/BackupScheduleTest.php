<?php

use App\Libraries\DatabaseBackupService;
use App\Models\DatabaseBackupModel;
use App\Models\SystemSettingModel;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Backup;

/**
 * Admin-configurable automatic backup schedule: slot maths, validation,
 * configuration fallbacks and the due/next decision. Pure logic — no database
 * connection and no dump process is involved.
 *
 * @internal
 */
final class BackupScheduleTest extends CIUnitTestCase
{
    // ---------------------------------------------------------------------
    // Normalisation / validation
    // ---------------------------------------------------------------------

    public function testNormalizeFallsBackPerField(): void
    {
        $schedule = DatabaseBackupService::normalizeSchedule([
            'enabled'   => 'not-a-boolean',
            'frequency' => 'nope',
            'time'      => '99:99',
            'weekday'   => '9',
        ], $this->defaults());

        $this->assertTrue($schedule['enabled']);
        $this->assertSame('daily', $schedule['frequency']);
        $this->assertSame('02:00', $schedule['time']);
        $this->assertSame(0, $schedule['weekday']);
        $this->assertSame(24, $schedule['interval']);
        $this->assertSame('Daily at 02:00', $schedule['label']);
    }

    public function testNormalizeAcceptsStoredValues(): void
    {
        $schedule = DatabaseBackupService::normalizeSchedule([
            'enabled'   => '0',
            'frequency' => 'every6h',
            'time'      => '06:05',
            'weekday'   => '3',
        ], $this->defaults());

        $this->assertFalse($schedule['enabled']);
        $this->assertSame('every6h', $schedule['frequency']);
        $this->assertSame('06:05', $schedule['time']);
        $this->assertSame(6, $schedule['interval']);
        $this->assertSame(3, $schedule['weekday']);
        $this->assertSame('Every 6 hours from 06:05', $schedule['label']);
    }

    public function testValidationRejectsBadInput(): void
    {
        $bad = [
            ['frequency' => 'sometimes', 'time' => '02:00', 'weekday' => 0],
            ['frequency' => 'daily', 'time' => '25:00', 'weekday' => 0],
            ['frequency' => 'daily', 'time' => 'noon', 'weekday' => 0],
            ['frequency' => 'weekly', 'time' => '02:00', 'weekday' => 7],
            ['frequency' => '', 'time' => '', 'weekday' => 0],
        ];

        foreach ($bad as $input) {
            $result = DatabaseBackupService::validateScheduleInput($input);

            $this->assertFalse($result['ok'], json_encode($input));
            $this->assertNotEmpty($result['error']);
            $this->assertNull($result['settings']);
        }
    }

    public function testValidationAcceptsAndNormalizes(): void
    {
        $result = DatabaseBackupService::validateScheduleInput([
            'enabled'   => '1',
            'frequency' => 'weekly',
            'time'      => '3:05',
            'weekday'   => '6',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('03:05', $result['settings']['time']);
        $this->assertSame(6, $result['settings']['weekday']);
        $this->assertSame('Weekly on Saturday at 03:05', $result['settings']['label']);
    }

    // ---------------------------------------------------------------------
    // Slot maths (server local time; fixed reference: Sat 2026-09-26 15:40)
    // ---------------------------------------------------------------------

    public function testDailySlots(): void
    {
        $now = strtotime('2026-09-26 15:40:00');

        $atTwo = ['frequency' => 'daily', 'hour' => 2, 'minute' => 0, 'interval' => 24];
        $this->assertSame('2026-09-26 02:00', date('Y-m-d H:i', DatabaseBackupService::slotStart($atTwo, $now)));
        $this->assertSame('2026-09-27 02:00', date('Y-m-d H:i', DatabaseBackupService::nextSlot($atTwo, $now)));

        // The anchor has not arrived yet today: the previous day is the last slot.
        $atTwenty = ['frequency' => 'daily', 'hour' => 20, 'minute' => 0, 'interval' => 24];
        $this->assertSame('2026-09-25 20:00', date('Y-m-d H:i', DatabaseBackupService::slotStart($atTwenty, $now)));
        $this->assertSame('2026-09-26 20:00', date('Y-m-d H:i', DatabaseBackupService::nextSlot($atTwenty, $now)));
    }

    public function testIntervalSlots(): void
    {
        $now = strtotime('2026-09-26 15:40:00');

        $six = ['frequency' => 'every6h', 'hour' => 2, 'minute' => 0, 'interval' => 6];
        $this->assertSame('2026-09-26 14:00', date('Y-m-d H:i', DatabaseBackupService::slotStart($six, $now)));
        $this->assertSame('2026-09-26 20:00', date('Y-m-d H:i', DatabaseBackupService::nextSlot($six, $now)));

        $twelve = ['frequency' => 'every12h', 'hour' => 2, 'minute' => 0, 'interval' => 12];
        $this->assertSame('2026-09-26 14:00', date('Y-m-d H:i', DatabaseBackupService::slotStart($twelve, $now)));
        $this->assertSame('2026-09-27 02:00', date('Y-m-d H:i', DatabaseBackupService::nextSlot($twelve, $now)));

        // Before the first anchor of the day the window spans midnight.
        $this->assertSame(
            '2026-09-25 20:00',
            date('Y-m-d H:i', DatabaseBackupService::slotStart($six, strtotime('2026-09-26 01:00:00')))
        );
    }

    public function testHourlySlots(): void
    {
        $hourly = ['frequency' => 'hourly', 'hour' => 0, 'minute' => 15, 'interval' => 1];

        $this->assertSame(
            '2026-09-26 15:15',
            date('Y-m-d H:i', DatabaseBackupService::slotStart($hourly, strtotime('2026-09-26 15:40:00')))
        );
        $this->assertSame(
            '2026-09-26 14:15',
            date('Y-m-d H:i', DatabaseBackupService::slotStart($hourly, strtotime('2026-09-26 15:10:00')))
        );
        $this->assertSame(
            '2026-09-26 16:15',
            date('Y-m-d H:i', DatabaseBackupService::nextSlot($hourly, strtotime('2026-09-26 15:40:00')))
        );
    }

    public function testWeeklySlots(): void
    {
        $now = strtotime('2026-09-26 15:40:00'); // Saturday

        $saturday = ['frequency' => 'weekly', 'hour' => 3, 'minute' => 30, 'weekday' => 6, 'interval' => 0];
        $this->assertSame('2026-09-26 03:30', date('Y-m-d H:i', DatabaseBackupService::slotStart($saturday, $now)));
        $this->assertSame('2026-10-03 03:30', date('Y-m-d H:i', DatabaseBackupService::nextSlot($saturday, $now)));

        $sunday = ['frequency' => 'weekly', 'hour' => 3, 'minute' => 30, 'weekday' => 0, 'interval' => 0];
        $this->assertSame('2026-09-20 03:30', date('Y-m-d H:i', DatabaseBackupService::slotStart($sunday, $now)));
        $this->assertSame('2026-09-27 03:30', date('Y-m-d H:i', DatabaseBackupService::nextSlot($sunday, $now)));
    }

    public function testLabels(): void
    {
        $this->assertSame(
            'Daily at 02:00',
            DatabaseBackupService::scheduleLabel(['frequency' => 'daily', 'hour' => 2, 'minute' => 0])
        );
        $this->assertSame(
            'Every 8 hours from 06:30',
            DatabaseBackupService::scheduleLabel(['frequency' => 'every8h', 'hour' => 6, 'minute' => 30])
        );
        $this->assertSame(
            'Hourly at :05',
            DatabaseBackupService::scheduleLabel(['frequency' => 'hourly', 'hour' => 0, 'minute' => 5])
        );
        $this->assertSame(
            'Weekly on Wednesday at 23:15',
            DatabaseBackupService::scheduleLabel(['frequency' => 'weekly', 'hour' => 23, 'minute' => 15, 'weekday' => 3])
        );
    }

    // ---------------------------------------------------------------------
    // Effective settings / state / persistence
    // ---------------------------------------------------------------------

    public function testSettingsFallBackToConfigWhenNothingIsStored(): void
    {
        $service = $this->service();

        $settings = $service->scheduleSettings();

        $this->assertSame('config', $settings['source']);
        $this->assertTrue($settings['enabled']);
        $this->assertSame('Daily at 02:00', $settings['label']);
        $this->assertTrue($service->isEnabled());
        $this->assertSame(2, $service->scheduleHour());
    }

    public function testSettingsPreferStoredValues(): void
    {
        $service = $this->service([
            'backup_schedule_enabled'   => '0',
            'backup_schedule_frequency' => 'every12h',
            'backup_schedule_time'      => '06:30',
            'backup_schedule_weekday'   => '3',
        ]);

        $settings = $service->scheduleSettings();

        $this->assertSame('database', $settings['source']);
        $this->assertFalse($settings['enabled']);
        $this->assertSame('every12h', $settings['frequency']);
        $this->assertSame('06:30', $settings['time']);
        $this->assertSame(3, $settings['weekday']);
        $this->assertSame('Every 12 hours from 06:30', $settings['label']);
        $this->assertFalse($service->isEnabled());

        $state = $service->scheduleState();

        $this->assertFalse($state['enabled']);
        $this->assertFalse($state['due'], 'a paused schedule is never due');
        $this->assertNull($state['next']);
    }

    public function testStateIsDueWhenTheSlotHasNoAutomaticBackup(): void
    {
        $now     = strtotime('2026-09-26 15:40:00');
        $service = $this->service($this->storedDaily());

        $state = $service->scheduleState($now);

        $this->assertTrue($state['enabled']);
        $this->assertTrue($state['due']);
        $this->assertSame($now, $state['next'], 'a due backup runs on the next scheduler pass');
        $this->assertNull($state['automatic']);
    }

    public function testStateIsNotDueWhenTheSlotAlreadyHasABackup(): void
    {
        $now     = strtotime('2026-09-26 15:40:00');
        $service = $this->service($this->storedDaily(), '2026-09-26 03:00:00');

        $state = $service->scheduleState($now);

        $this->assertFalse($state['due']);
        $this->assertSame('2026-09-27 02:00', date('Y-m-d H:i', (int) $state['next']));
        $this->assertSame('2026-09-26 03:00:00', (string) $state['automatic']['created_at']);
    }

    public function testSavingPersistsEverySetting(): void
    {
        $settings = $this->stubSettingsModel([]);
        $service  = $this->service([], null, $settings);

        $result = $service->saveScheduleSettings([
            'enabled'   => true,
            'frequency' => 'every8h',
            'time'      => '06:30',
            'weekday'   => 0,
        ], 7, 'Test Admin', 'unit');

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Every 8 hours from 06:30', $result['message']);
        $this->assertSame('1', $settings->getSetting('backup_schedule_enabled'));
        $this->assertSame('every8h', $settings->getSetting('backup_schedule_frequency'));
        $this->assertSame('06:30', $settings->getSetting('backup_schedule_time'));
        $this->assertSame('0', $settings->getSetting('backup_schedule_weekday'));
    }

    public function testSavingRejectsBadInputWithoutWriting(): void
    {
        $settings = $this->stubSettingsModel([]);
        $service  = $this->service([], null, $settings);

        $result = $service->saveScheduleSettings([
            'enabled'   => true,
            'frequency' => 'daily',
            'time'      => '31:00',
            'weekday'   => 0,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
        $this->assertNull($settings->getSetting('backup_schedule_time'));
    }

    public function testCronLineFollowsTheSchedule(): void
    {
        $service = $this->service($this->storedDaily());

        $daily = $service->scheduleCronLine();
        $this->assertStringContainsString('backup:daily', $daily);

        $hourly = $service->scheduleCronLine(DatabaseBackupService::normalizeSchedule(
            ['frequency' => 'hourly', 'time' => '02:30'],
            $this->defaults()
        ));

        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertStringContainsString('/SC DAILY /ST 02:00', $daily);
            $this->assertStringContainsString('/SC HOURLY', $hourly);
        } else {
            $this->assertStringContainsString('0 2 * * *', $daily);
            $this->assertStringContainsString('30 * * * *', $hourly);
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return ['enabled' => true, 'frequency' => 'daily', 'hour' => 2, 'minute' => 0, 'weekday' => 0, 'source' => 'config'];
    }

    /** @return array<string, string> */
    private function storedDaily(): array
    {
        return [
            'backup_schedule_enabled'   => '1',
            'backup_schedule_frequency' => 'daily',
            'backup_schedule_time'      => '02:00',
            'backup_schedule_weekday'   => '0',
        ];
    }

    /**
     * @param array<string, mixed> $stored stored system_settings values
     */
    private function service(array $stored = [], ?string $newestAuto = null, ?SystemSettingModel $settingsModel = null): DatabaseBackupService
    {
        $config               = new Backup();
        $config->enabled      = true;
        $config->scheduleHour = 2;

        return new DatabaseBackupService(
            $config,
            $this->stubBackupModel($newestAuto),
            $settingsModel ?? $this->stubSettingsModel($stored)
        );
    }

    private function stubBackupModel(?string $newestAutoCreatedAt): DatabaseBackupModel
    {
        return new class ($newestAutoCreatedAt) extends DatabaseBackupModel {
            private ?string $createdAt;

            public function __construct(?string $createdAt = null)
            {
                $this->createdAt = $createdAt;
            }

            public function findLatestAutoBackup(?string $since = null): ?array
            {
                if ($this->createdAt === null) {
                    return null;
                }

                if ($since !== null && $this->createdAt < $since) {
                    return null;
                }

                return ['id' => 1, 'kind' => 'auto', 'created_at' => $this->createdAt];
            }
        };
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stubSettingsModel(array $values): SystemSettingModel
    {
        return new class ($values) extends SystemSettingModel {
            /** @var array<string, mixed> */
            private array $values;

            public function __construct(array $values = [])
            {
                $this->values = $values;
            }

            public function getSetting($key, $default = null)
            {
                return $this->values[$key] ?? $default;
            }

            public function setSetting($key, $value, $description = null)
            {
                $this->values[$key] = $value;

                return true;
            }
        };
    }
}
