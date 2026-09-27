<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The plain-English layer that turns activity-log machine codes
 * (auth.login, db_backup, ::1) into readable labels and icons.
 *
 * Pure functions only, so no database is required. The most important
 * guarantee is that this layer NEVER fails: a code missing from the map must
 * still render as something an administrator can read, because a new feature
 * will ship before the map is updated.
 *
 * @internal
 */
final class AuditDisplayTest extends CIUnitTestCase
{
    /**
     * Every action the application records must have a readable label and a
     * real icon. Guards against an audit_event() call being added without a
     * matching display entry.
     *
     * @dataProvider provideKnownActions
     */
    public function testKnownActionsHaveLabelIconAndTone(string $action): void
    {
        $display = audit_display_action($action);

        $this->assertNotSame('', $display['label'], $action . ' needs a label');
        $this->assertNotSame('', $display['icon'], $action . ' needs an icon');
        $this->assertNotSame('', $display['help'], $action . ' needs an explanation');
        $this->assertContains(
            $display['tone'],
            ['success', 'danger', 'warning', 'info', 'muted'],
            $action . ' has an unknown tone'
        );
    }

    /**
     * The label must be human prose, never the raw dotted code.
     */
    public function testLabelsAreNotMachineCodes(): void
    {
        foreach (array_keys(audit_display_action_map()) as $action) {
            $label = audit_display_action($action)['label'];

            $this->assertStringNotContainsString('_', $label, $action . ' label still looks like a code');
            $this->assertStringNotContainsString('.', $label, $action . ' label still looks like a code');
        }
    }

    /**
     * A newly shipped action must degrade gracefully rather than break the page.
     */
    public function testUnknownActionFallsBackToHumanisedLabel(): void
    {
        $display = audit_display_action('brand.new_action');

        // "brand.new_action" is decomposed into human words, never shown raw.
        $this->assertSame('Brand new action', $display['label']);
        $this->assertStringNotContainsString('_', $display['label']);
        $this->assertStringNotContainsString('.', $display['label']);
        $this->assertSame('muted', $display['tone']);
        $this->assertNotSame('', $display['help']);

        // A code with no namespace still reads as a sentence.
        $this->assertSame('Mystery thing', audit_display_fallback_label('mystery_thing'));
        $this->assertSame('Thing', audit_display_fallback_label('thing'));

        $this->assertSame('Unknown activity', audit_display_action('')['label']);
    }

    public function testDestructiveActionsAreVisuallyDistinct(): void
    {
        // Deleting things must be red so they stand out when scanning the list.
        foreach (['student.deleted', 'system.backup_deleted', 'role.staff_deleted'] as $action) {
            $this->assertSame('danger', audit_display_action($action)['tone'], $action);
        }
    }

    /**
     * @dataProvider provideIpAddresses
     */
    public function testIpAddressesAreDescribedInPlainLanguage(string $ip, string $expected): void
    {
        $this->assertSame($expected, audit_display_ip($ip)['label']);
    }

    public function testEmptyIpIsReportedRatherThanBlank(): void
    {
        $this->assertSame('Not recorded', audit_display_ip('')['label']);
    }

    /**
     * @dataProvider provideResources
     */
    public function testResourceTypesAreReadable(string $type, string $expected): void
    {
        $this->assertSame($expected, audit_display_resource($type)['label']);
    }

    /**
     * @dataProvider provideRoles
     */
    public function testRolesAreReadable(string $role, string $expected): void
    {
        $this->assertSame($expected, audit_display_role($role)['label']);
    }

    public function testStatusesUseTheAdminWording(): void
    {
        $this->assertSame('Success', audit_display_status('success')['label']);
        $this->assertSame('Failed', audit_display_status('failure')['label']);
        $this->assertSame('Blocked', audit_display_status('blocked')['label']);
    }

    /**
     * The raw description of an http.request row is developer output
     * ("POST /admin/backups/create -> HTTP 200") and must be rewritten.
     */
    public function testHttpRequestDescriptionBecomesASentence(): void
    {
        $sentence = audit_display_description([
            'action'      => 'http.request',
            'http_method' => 'POST',
            'route'       => 'admin/backups/create',
            'actor_name'  => 'Mark Loyd',
            'description' => 'POST /admin/backups/create -> HTTP 200',
        ]);

        $this->assertSame('Mark Loyd submitted a form on the Backup & Restore screen.', $sentence);
        $this->assertStringNotContainsString('HTTP', $sentence);
        $this->assertStringNotContainsString('->', $sentence);
    }

    public function testHttpRequestDescriptionHandlesMissingActor(): void
    {
        $sentence = audit_display_description([
            'action'      => 'http.request',
            'http_method' => 'POST',
            'route'       => 'login',
            'actor_name'  => '',
        ]);

        $this->assertStringContainsString('Login', $sentence);
        $this->assertStringEndsWith('.', $sentence);
    }

    /**
     * Descriptions that are already human must be left exactly as written:
     * rewriting them would lose detail such as a backup file name.
     */
    public function testHumanDescriptionsArePassedThroughUntouched(): void
    {
        $text = 'Database backup created: db-manual.sql (288.2 KB, 51 table(s))';

        $this->assertSame($text, audit_display_description([
            'action'      => 'system.backup_created',
            'description' => $text,
        ]));
    }

    /**
     * The filter dropdown must keep the raw code as the option value so the
     * submitted filter (and therefore the query) does not change.
     */
    public function testGroupedActionsKeepRawCodesAsValues(): void
    {
        $grouped = audit_display_grouped_actions([
            'auth.login',
            'student.created',
            'system.backup_created',
        ]);

        $this->assertSame(['auth', 'student', 'system'], array_keys($grouped));

        foreach ($grouped as $items) {
            foreach ($items as $item) {
                $this->assertArrayHasKey('value', $item);
                $this->assertArrayHasKey('label', $item);
                $this->assertNotSame('', $item['icon']);
            }
        }

        $this->assertSame('auth.login', $grouped['auth'][0]['value']);
        $this->assertSame('Signed in', $grouped['auth'][0]['label']);
    }

    public function testGroupedActionsToleratesEmptyInput(): void
    {
        $this->assertSame([], audit_display_grouped_actions([]));
        $this->assertSame([], audit_display_grouped_actions(['', '  ']));
    }

    /**
     * Admin routes are resolved through the canonical page names so the log and
     * the sidebar never disagree about what a screen is called.
     */
    public function testAdminRoutesUseTheSidebarPageNames(): void
    {
        $this->assertSame('Activity log', audit_display_route('admin/audit-log'));
        $this->assertSame('Backup & Restore', audit_display_route('admin/backups/create'));
        $this->assertSame('Unknown screen', audit_display_route(''));
    }

    public function testMapIsMemoisedAndConsistent(): void
    {
        $first  = audit_display_action_map();
        $second = audit_display_action_map();

        $this->assertSame($first, $second);
        $this->assertGreaterThan(50, count($first), 'the action map should cover the app');
    }

    public static function provideKnownActions(): array
    {
        return array_map(
            static fn (string $action): array => [$action],
            array_keys(audit_display_action_map())
        );
    }

    public static function provideIpAddresses(): array
    {
        return [
            ['::1', 'This device'],
            ['127.0.0.1', 'This device'],
            ['192.168.1.20', 'School network'],
            ['10.0.0.5', 'School network'],
            ['172.20.1.9', 'School network'],
            ['143.44.133.123', 'Internet connection'],
        ];
    }

    public static function provideResources(): array
    {
        return [
            ['db_backup', 'Database backup'],
            ['password_reset_request', 'Password reset request'],
            ['student', 'Student'],
            ['teacher', 'Teacher'],
            ['section', 'Section'],
        ];
    }

    public static function provideRoles(): array
    {
        return [
            ['admin', 'Administrator'],
            ['admin_staff', 'Admin staff'],
            ['teacher', 'Teacher'],
            ['student', 'Student'],
        ];
    }
}
