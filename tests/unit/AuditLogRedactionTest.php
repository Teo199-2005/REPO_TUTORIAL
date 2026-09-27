<?php

use App\Filters\AuditTrailFilter;
use App\Libraries\AuditLogger;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Security/privacy behaviour of the activity log writer, and the pure parts of
 * the request-trail filter. No database required.
 *
 * @internal
 */
final class AuditLogRedactionTest extends CIUnitTestCase
{
    private AuditLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new AuditLogger();
    }

    public function testSensitiveFieldsAreRedactedWhateverTheirShape(): void
    {
        $clean = $this->logger->sanitize([
            'password'         => 'SuperSecret1',
            'new_password'     => 'SuperSecret1',
            'confirm_password' => 'SuperSecret1',
            'auth_token'       => 'tok_123',
            'api_key'          => 'key_123',
            'secret'           => 'shhh',
            'csrf_token'       => 'csrf',
            'remember_token'   => 'remember',
            'pin'              => '1234',
            'title'            => 'Kept',
        ]);

        foreach (['password', 'new_password', 'confirm_password', 'auth_token', 'api_key', 'secret', 'csrf_token', 'remember_token', 'pin'] as $sensitive) {
            $this->assertSame(AuditLogger::REDACTED, $clean[$sensitive], $sensitive . ' must be redacted');
        }

        $this->assertSame('Kept', $clean['title'], 'non-sensitive values must survive');
    }

    public function testNestedStructuresAreSanitizedRecursively(): void
    {
        $clean = $this->logger->sanitize([
            'account' => [
                'email'  => 'student@example.com',
                'extras' => ['access_token' => 'a', 'label' => 'kept'],
            ],
        ]);

        $this->assertSame('student@example.com', $clean['account']['email']);
        $this->assertSame(AuditLogger::REDACTED, $clean['account']['extras']['access_token']);
        $this->assertSame('kept', $clean['account']['extras']['label']);
    }

    public function testAWholeNestedStructureUnderASensitiveKeyIsRedacted(): void
    {
        $clean = $this->logger->sanitize([
            'account' => ['email' => 'student@example.com'],
            'tokens'  => ['access' => 'a', 'refresh' => 'b'],
        ]);

        $this->assertSame('student@example.com', $clean['account']['email']);
        $this->assertSame(AuditLogger::REDACTED, $clean['tokens'], 'no part of a token payload may be stored');
    }

    public function testPasswordHashesAndLongHexSecretsAreRedactedByValue(): void
    {
        $clean = $this->logger->sanitize([
            // A real bcrypt body is 53 characters of ./A-Za-z0-9.
            'hash_field'   => '$2y$10$' . str_repeat('aB', 26) . 'Z',
            'opaque_value' => str_repeat('a', 64),
            'normal'       => 'note',
        ]);

        $this->assertSame(AuditLogger::REDACTED_HASH, $clean['hash_field']);
        $this->assertSame(AuditLogger::REDACTED_HASH, $clean['opaque_value']);
        $this->assertSame('note', $clean['normal']);
    }

    public function testControlCharactersAreStrippedToPreventLogInjection(): void
    {
        $value = "line one\nline two\r\nEVIL\ttab\x00null";

        $clean = $this->logger->sanitize($value);

        $this->assertStringNotContainsString("\n", $clean);
        $this->assertStringNotContainsString("\r", $clean);
        $this->assertStringNotContainsString("\t", $clean);
        $this->assertStringNotContainsString("\x00", $clean);
        $this->assertStringContainsString('line one line two', $clean);
    }

    public function testLongValuesAreTruncated(): void
    {
        $clean = $this->logger->sanitize(str_repeat('x', 5000));

        $this->assertLessThan(5000, strlen($clean));
        $this->assertStringContainsString('[truncated]', $clean);
    }

    public function testDiffKeepsOnlyChangedWhitelistedFields(): void
    {
        $diff = $this->logger->diff(
            ['first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => 'a@example.com'],
            ['first_name' => 'Ana', 'last_name' => 'Reyes', 'email' => 'a@example.com', 'password' => 'SuperSecret1'],
            ['first_name', 'last_name', 'email']
        );

        $this->assertArrayNotHasKey('first_name', $diff['before'], 'unchanged fields are dropped');
        $this->assertArrayNotHasKey('email', $diff['before']);
        $this->assertSame(['last_name'], $diff['changed']);
        $this->assertSame('Cruz', $diff['before']['last_name']);
        $this->assertSame('Reyes', $diff['after']['last_name']);
    }

    public function testDiffRedactsSensitiveFieldsEvenWhenWhitelisted(): void
    {
        $diff = $this->logger->diff(
            ['secret_field' => 'old'],
            ['secret_field' => 'new'],
            ['secret_field']
        );

        $this->assertSame(AuditLogger::REDACTED, $diff['before']['secret_field']);
        $this->assertSame(AuditLogger::REDACTED, $diff['after']['secret_field']);
    }

    public function testHashChainDetectsTampering(): void
    {
        $rows = [];
        $prev = null;

        for ($i = 1; $i <= 3; $i++) {
            $row = [
                'id'            => $i,
                'request_id'    => 'req-' . $i,
                'actor_user_id' => 1,
                'actor_name'    => 'Admin',
                'actor_role'    => 'admin',
                'action'        => 'student.updated',
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'student',
                'resource_id'   => (string) $i,
                'description'   => 'Entry ' . $i,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'PHPUnit',
                'http_method'   => 'POST',
                'route'         => 'admin/students/update/' . $i,
                'before_json'   => null,
                'after_json'    => null,
                'metadata_json' => null,
                'created_at'    => '2026-01-0' . $i . ' 10:00:00',
            ];

            $row['prev_hash'] = $prev;
            $row['hash']      = $this->logger->hashRow($row, $prev);
            $prev             = $row['hash'];

            $rows[] = $row;
        }

        $result = $this->logger->verifyChainRows($rows);
        $this->assertTrue($result['valid'], $result['message']);
        $this->assertSame(3, $result['checked']);

        // Editing a stored value must break verification for that row.
        $tampered                   = $rows;
        $tampered[1]['description'] = 'Rewritten history';
        $result                     = $this->logger->verifyChainRows($tampered);

        $this->assertFalse($result['valid']);
        $this->assertContains(2, $result['broken_ids']);

        // Deleting a row must break the chain link that followed it.
        $deleted = [$rows[0], $rows[2]];
        $result  = $this->logger->verifyChainRows($deleted);

        $this->assertFalse($result['valid']);
        $this->assertContains(3, $result['broken_ids']);
    }

    public function testCanonicalPayloadIsOrderStableAndIncludesPreviousHash(): void
    {
        $row = ['action' => 'auth.login', 'created_at' => '2026-01-01 00:00:00'];

        $payloadA = $this->logger->canonicalPayload($row, 'abc');
        $payloadB = $this->logger->canonicalPayload($row, 'abc');
        $payloadC = $this->logger->canonicalPayload($row, 'xyz');

        $this->assertSame($payloadA, $payloadB);
        $this->assertNotSame($payloadA, $payloadC, 'the previous hash must be part of the signed payload');
    }

    public function testRecordFailsSafeWhenTheTableDoesNotExist(): void
    {
        // The shared test connection may already have the audit table (the
        // database suite creates it), so it is removed for this check: a
        // missing table must degrade to a no-op instead of breaking the caller.
        $db = \Config\Database::connect();

        if ($db->tableExists('audit_logs')) {
            \Config\Database::forge()->dropTable('audit_logs', true);
        }

        $id = (new AuditLogger())->record(['action' => 'test.noop']);

        $this->assertNull($id);

        // Leave the shared test database as the other suites expect to find it.
        if (! $db->tableExists('audit_logs')) {
            require_once APPPATH . 'Database/Migrations/2026-09-26-000000_CreateAuditLogsTable.php';

            (new \App\Database\Migrations\CreateAuditLogsTable(\Config\Database::forge('tests')))->up();
        }
    }

    public function testAuditEventHelperNeverThrows(): void
    {
        audit_event('test.helper_noop', ['category' => 'system']);

        $this->assertTrue(true);
    }

    public function testRequestTrailSkipsReadsAndItsOwnEndpoints(): void
    {
        $this->assertTrue(AuditTrailFilter::shouldRecord('POST', 'admin/students/update/5'));
        $this->assertTrue(AuditTrailFilter::shouldRecord('DELETE', 'admin/students/bulkDeletePermanently'));

        $this->assertFalse(AuditTrailFilter::shouldRecord('GET', 'admin/students'));
        $this->assertFalse(AuditTrailFilter::shouldRecord('HEAD', 'admin/dashboard'));
        $this->assertFalse(AuditTrailFilter::shouldRecord('POST', 'admin/audit-log/verify'), 'the audit page logs its own actions');
        $this->assertFalse(AuditTrailFilter::shouldRecord('POST', ''), 'an empty path is not recordable');
    }

    public function testDescribeActorPrefersTheUsersProfileName(): void
    {
        $actor = $this->logger->describeActor($this->fakeUser([
            'id' => 2203, 'first_name' => 'Mark', 'last_name' => 'Loyd',
        ], ['admin']));

        $this->assertSame(2203, $actor['id']);
        $this->assertSame('Mark Loyd', $actor['name']);
        $this->assertSame('admin', $actor['role']);
    }

    public function testDescribeActorNeverFallsBackToALegacyIdentitySecret(): void
    {
        $hash = '$2y$10$' . str_repeat('aB', 26) . 'Z';

        $actor = $this->logger->describeActor($this->fakeUser([
            'id' => 918273, 'first_name' => null, 'last_name' => null,
            'username' => null, 'email' => '',
        ], ['student'], $hash));

        $this->assertSame('User #918273', $actor['name'], 'a password hash must never become the actor name');
        $this->assertSame('student', $actor['role']);
    }

    public function testDescribeActorKeepsTheFallbackNameWhenNothingUsableExists(): void
    {
        $hash  = '$2y$10$' . str_repeat('aB', 26) . 'Z';
        $actor = $this->logger->describeActor($this->fakeUser(['id' => 0], [], $hash), 'Ana Cruz');

        $this->assertNull($actor['id']);
        $this->assertSame('Ana Cruz', $actor['name']);
    }

    public function testDescribeActorWithoutAUserIsAnonymous(): void
    {
        $this->assertSame(['id' => null, 'name' => '', 'role' => ''], $this->logger->describeActor(null));
    }

    public function testActorNamePlaceholderDetectionCoversHashesAndBlankValues(): void
    {
        $this->assertTrue(audit_actor_name_is_placeholder(null));
        $this->assertTrue(audit_actor_name_is_placeholder(''));
        $this->assertTrue(audit_actor_name_is_placeholder('$2y$10$' . str_repeat('aB', 26) . 'Z'));
        $this->assertTrue(audit_actor_name_is_placeholder(str_repeat('a', 64)));
        $this->assertFalse(audit_actor_name_is_placeholder('Mark Loyd'));
    }

    public function testResolveActorNamesLeavesRowsWithoutPlaceholdersUntouched(): void
    {
        $rows = [
            ['actor_user_id' => 1, 'actor_name' => 'Admin One', 'actor_role' => 'admin'],
            ['actor_user_id' => null, 'actor_name' => null, 'actor_role' => null],
        ];

        $this->assertSame($rows, audit_resolve_actor_names($rows));
    }

    /**
     * Minimal stand-in for CodeIgniter\Shield\Entities\User.
     */
    private function fakeUser(array $attributes, array $groups = [], ?string $emailIdentitySecret = null): object
    {
        return new class ($attributes, $groups, $emailIdentitySecret) {
            public function __construct(
                private array $attributes,
                private array $groups,
                private ?string $emailIdentitySecret
            ) {
            }

            public function __get(string $key)
            {
                return $this->attributes[$key] ?? null;
            }

            public function __isset(string $key): bool
            {
                return isset($this->attributes[$key]);
            }

            public function getGroups(): array
            {
                return $this->groups;
            }

            public function getEmail(): ?string
            {
                return $this->emailIdentitySecret;
            }
        };
    }
}
