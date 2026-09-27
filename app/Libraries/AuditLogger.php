<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Database;
use Throwable;

/**
 * Central writer for the append-only activity/audit log (`audit_logs`).
 *
 * Responsibilities:
 *  - normalize + sanitize an event (never persist passwords/tokens/secrets,
 *    never persist control characters, cap every string and JSON blob);
 *  - resolve the actor (logged-in user, name, role) and request context;
 *  - chain every row to the previous one with HMAC-SHA256 so the admin
 *    "Verify integrity" action can detect edits/deletions;
 *  - fail safe: a logging failure must never break the request it describes.
 *
 * The library deliberately talks to the `audit_logs` table through the query
 * builder (single INSERT, one memoized read for the previous hash) so it adds
 * minimal overhead to the requests that call it.
 */
class AuditLogger
{
    public const CATEGORIES = ['auth', 'account', 'role', 'data', 'settings', 'system'];
    public const STATUSES   = ['success', 'failure', 'blocked'];

    public const REDACTED      = '[REDACTED]';
    public const REDACTED_HASH = '[REDACTED-HASH]';

    /** Maximum length of a stored JSON blob; larger payloads are replaced. */
    private const MAX_JSON_BYTES = 16000;

    /** Maximum length of a scalar value inside a JSON blob. */
    private const MAX_VALUE_LENGTH = 2000;

    /**
     * Field names (normalized) whose value must never be stored.
     * Substring match, so `new_password`, `auth_token`, `api_key` are covered.
     */
    private const SENSITIVE_SUBSTRINGS = [
        'password', 'passwd', 'passphrase', 'secret', 'token', 'apikey',
        'api_key', 'authorization', 'cookie', 'csrf', 'otp', 'credential',
        'private_key', 'privatekey', 'salt', 'sessionid', 'session_id',
        'remember', 'recoverycode', 'recovery_code',
    ];

    /** Exact normalized words that are sensitive (avoids matching e.g. "spin"). */
    private const SENSITIVE_WORDS = ['pin', 'key'];

    private ?string $requestId = null;
    private ?string $chainKey  = null;
    private ?string $prevHash  = null;
    private bool $prevHashLoaded = false;
    private ?bool $tableExists   = null;
    private bool $missingTableLogged = false;
    private ?array $actorCache = null;

    /**
     * A per-request correlation id. Generated lazily when no filter has set one
     * (e.g. CLI commands), and never longer than 40 characters.
     */
    public function requestId(): string
    {
        if ($this->requestId === null) {
            try {
                $this->requestId = bin2hex(random_bytes(8));
            } catch (Throwable $e) {
                $this->requestId = substr(hash('sha256', uniqid('audit', true)), 0, 16);
            }
        }

        return $this->requestId;
    }

    /**
     * Set the request correlation id (AuditTrailFilter does this early in the
     * request so every row written during the request shares it).
     */
    public function setRequestId(string $requestId): void
    {
        $requestId = trim($requestId);
        if ($requestId !== '') {
            $this->requestId = substr($requestId, 0, 40);
        }
    }

    /**
     * True when the audit table exists. Cached per instance.
     */
    public function available(): bool
    {
        if ($this->tableExists === null) {
            try {
                $this->tableExists = Database::connect()->tableExists('audit_logs');
            } catch (Throwable $e) {
                $this->tableExists = false;
            }

            if ($this->tableExists === false && ! $this->missingTableLogged) {
                $this->missingTableLogged = true;
                log_message('warning', 'Audit log table is not available; activity logging is disabled. Run "php spark migrate".');
            }
        }

        return $this->tableExists;
    }

    /**
     * Persist one audit event.
     *
     * Accepted keys: action, category, status, actor_user_id, actor_name,
     * actor_role, resource_type, resource_id, description, ip_address,
     * user_agent, http_method, route, before, after, metadata.
     * Unknown keys are ignored; anything sensitive is redacted.
     *
     * @return int|null Inserted row id, or null when the write was skipped/failed.
     */
    public function record(array $event): ?int
    {
        try {
            if (! $this->available()) {
                return null;
            }

            $action = $this->oneLine((string) ($event['action'] ?? ''), 100);
            if ($action === '') {
                return null;
            }

            $actor = $this->actorContext();
            $http  = $this->requestContext();

            // An explicit event name wins unless it is missing or looks like a
            // leftover secret (legacy rows stored the identity secret, i.e. the
            // password hash, as the actor). The resolved session actor is
            // always preference-checked by describeActor().
            $actorName = $this->oneLine((string) ($event['actor_name'] ?? ''), 191);
            if ($actorName === '' || $this->isSecretLike($actorName)) {
                $actorName = $actor['name'];
            }

            $row = [
                'request_id'    => $this->requestId(),
                'actor_user_id' => $this->intOrNull($event['actor_user_id'] ?? $actor['id']),
                'actor_name'    => $this->oneLine($actorName, 191) ?: null,
                'actor_role'    => $this->oneLine((string) ($event['actor_role'] ?? $actor['role'] ?? ''), 50) ?: null,
                'action'        => $action,
                'category'      => $this->enumOr((string) ($event['category'] ?? ''), self::CATEGORIES, 'data'),
                'status'        => $this->enumOr((string) ($event['status'] ?? ''), self::STATUSES, 'success'),
                'resource_type' => $this->oneLine((string) ($event['resource_type'] ?? ''), 50) ?: null,
                'resource_id'   => $this->oneLine((string) ($event['resource_id'] ?? ''), 64) ?: null,
                'description'   => $this->oneLine((string) ($event['description'] ?? ''), 255) ?: null,
                'ip_address'    => $this->ipOrNull($event['ip_address'] ?? $http['ip_address']),
                'user_agent'    => $this->oneLine((string) ($event['user_agent'] ?? $http['user_agent']), 255) ?: null,
                'http_method'   => $this->oneLine((string) ($event['http_method'] ?? $http['http_method']), 10) ?: null,
                'route'         => $this->oneLine((string) ($event['route'] ?? $http['route']), 191) ?: null,
                'before_json'   => $this->jsonBlob($event['before'] ?? null),
                'after_json'    => $this->jsonBlob($event['after'] ?? null),
                'metadata_json' => $this->jsonBlob($event['metadata'] ?? null),
                'created_at'    => date('Y-m-d H:i:s'),
            ];

            $row['prev_hash'] = $this->previousHash();
            $row['hash']      = $this->hashRow($row, $row['prev_hash']);

            $db = Database::connect();
            $db->table('audit_logs')->insert($row);
            $id = (int) $db->insertID();

            // Keep the in-request chain correct for any further events.
            $this->prevHash       = $row['hash'];
            $this->prevHashLoaded = true;

            return $id;
        } catch (Throwable $e) {
            // Never break the request: the file log is the fallback trail.
            log_message('error', 'Failed to write audit log entry [' . ($event['action'] ?? 'unknown') . ']: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Recursively strip sensitive values and unsafe characters from a payload.
     *
     * Public so it can be unit-tested without a database.
     */
    public function sanitize(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 6) {
            return '[depth-limit]';
        }

        if (is_array($value)) {
            $clean = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if ($count++ >= 200) {
                    $clean['_truncated'] = 'too many entries';
                    break;
                }

                $clean[$key] = $this->isSensitiveKey((string) $key)
                    ? self::REDACTED
                    : $this->sanitize($item, $depth + 1);
            }

            return $clean;
        }

        if (is_object($value)) {
            return $this->sanitize(get_object_vars($value), $depth + 1);
        }

        if (is_string($value)) {
            $value = $this->stripControlChars($value);

            if (preg_match('/^\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}$/', $value) === 1
                || str_starts_with($value, '$argon2')
                || preg_match('/^[a-f0-9]{64,128}$/i', $value) === 1) {
                return self::REDACTED_HASH;
            }

            if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
                return mb_substr($value, 0, self::MAX_VALUE_LENGTH) . '...[truncated]';
            }

            return $value;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return '[unsupported]';
    }

    /**
     * Build a minimal before/after pair: only the requested fields and only
     * those whose value actually changed, both sanitized.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param list<string>         $fields Empty = union of both array keys.
     *
     * @return array{before: array<string, mixed>, after: array<string, mixed>, changed: list<string>}
     */
    public function diff(array $before, array $after, array $fields = []): array
    {
        $fields = $fields === []
            ? array_values(array_unique(array_merge(array_keys($before), array_keys($after))))
            : $fields;

        $changed = [];
        $old     = [];
        $new     = [];

        foreach ($fields as $field) {
            $oldValue = $before[$field] ?? null;
            $newValue = $after[$field] ?? null;

            if ($this->valuesEqual($oldValue, $newValue)) {
                continue;
            }

            $changed[] = (string) $field;

            // A whitelisted field name is not a licence to store secrets: the
            // field name is checked exactly like an array key would be.
            if ($this->isSensitiveKey((string) $field)) {
                $old[$field] = self::REDACTED;
                $new[$field] = self::REDACTED;

                continue;
            }

            $old[$field] = $this->sanitize($oldValue);
            $new[$field] = $this->sanitize($newValue);
        }

        return ['before' => $old, 'after' => $new, 'changed' => $changed];
    }

    /**
     * Canonical, order-stable representation of a row used for the HMAC.
     */
    public function canonicalPayload(array $row, ?string $prevHash): string
    {
        $parts = [
            (string) ($prevHash ?? ''),
            (string) ($row['request_id'] ?? ''),
            (string) ($row['actor_user_id'] ?? ''),
            (string) ($row['actor_name'] ?? ''),
            (string) ($row['actor_role'] ?? ''),
            (string) ($row['action'] ?? ''),
            (string) ($row['category'] ?? ''),
            (string) ($row['status'] ?? ''),
            (string) ($row['resource_type'] ?? ''),
            (string) ($row['resource_id'] ?? ''),
            (string) ($row['description'] ?? ''),
            (string) ($row['ip_address'] ?? ''),
            (string) ($row['user_agent'] ?? ''),
            (string) ($row['http_method'] ?? ''),
            (string) ($row['route'] ?? ''),
            (string) ($row['before_json'] ?? ''),
            (string) ($row['after_json'] ?? ''),
            (string) ($row['metadata_json'] ?? ''),
            (string) ($row['created_at'] ?? ''),
        ];

        return implode("\x1f", $parts);
    }

    /**
     * HMAC for one row, chained to the previous row's hash.
     */
    public function hashRow(array $row, ?string $prevHash): string
    {
        return hash_hmac('sha256', $this->canonicalPayload($row, $prevHash), $this->chainKey());
    }

    /**
     * Verify an ordered (id ASC) list of rows against the hash chain.
     *
     * The first row's stored `prev_hash` is the anchor: for a full table it is
     * null (genesis), and after a retention purge it is the hash of the last
     * removed row, which still lets every remaining segment be verified.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array{checked:int,valid:bool,broken_ids:list<int>,message:string}
     */
    public function verifyChainRows(array $rows): array
    {
        $checked   = 0;
        $broken    = [];
        $prevHash  = null;
        $firstSeen = false;

        foreach ($rows as $row) {
            $checked++;
            $id = (int) ($row['id'] ?? 0);

            if (! hash_equals((string) ($row['hash'] ?? ''), $this->hashRow($row, $firstSeen ? $prevHash : ($row['prev_hash'] ?? null)))) {
                $broken[] = $id;
            }

            if ($firstSeen && ! hash_equals((string) ($row['prev_hash'] ?? ''), (string) $prevHash)) {
                $broken[] = $id;
            }

            $firstSeen = true;
            $prevHash  = (string) ($row['hash'] ?? '');
        }

        $broken = array_values(array_unique($broken));

        return [
            'checked'    => $checked,
            'valid'      => $broken === [],
            'broken_ids' => $broken,
            'message'    => $broken === []
                ? 'No tampering detected in ' . $checked . ' checked record(s).'
                : count($broken) . ' record(s) failed verification. First: #' . $broken[0] . '.',
        ];
    }

    /**
     * Walk the whole table (batched) and verify the chain.
     *
     * @return array{checked:int,valid:bool,broken_ids:list<int>,message:string}
     */
    public function verifyChain(int $batchSize = 1000): array
    {
        if (! $this->available()) {
            return ['checked' => 0, 'valid' => false, 'broken_ids' => [], 'message' => 'The audit log table does not exist.'];
        }

        $db        = Database::connect();
        $lastId    = 0;
        $allRows   = [];

        try {
            do {
                $rows = $db->table('audit_logs')
                    ->where('id >', $lastId)
                    ->orderBy('id', 'ASC')
                    ->limit($batchSize)
                    ->get()
                    ->getResultArray();

                foreach ($rows as $row) {
                    $lastId  = (int) $row['id'];
                    $allRows[] = $row;
                }
            } while (count($rows) === $batchSize);
        } catch (Throwable $e) {
            return ['checked' => count($allRows), 'valid' => false, 'broken_ids' => [], 'message' => 'Verification failed: ' . $e->getMessage()];
        }

        return $this->verifyChainRows($allRows);
    }

    /**
     * Hash of the most recent row (memoized per request). Keeping this in the
     * instance means a request that writes several events pays one SELECT.
     */
    private function previousHash(): ?string
    {
        if ($this->prevHashLoaded) {
            return $this->prevHash;
        }

        $this->prevHashLoaded = true;
        $this->prevHash       = null;

        try {
            $row = Database::connect()->table('audit_logs')
                ->select('hash')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRowArray();

            if ($row !== null) {
                $this->prevHash = (string) $row['hash'];
            }
        } catch (Throwable $e) {
            $this->prevHash = null;
        }

        return $this->prevHash;
    }

    /**
     * HMAC key. Priority: dedicated audit key, app encryption key, then a
     * random key auto-provisioned once in system_settings.
     *
     * An attacker who can only edit rows (SQL injection, leaked DB backup,
     * direct table access) cannot forge the chain without this key.
     */
    private function chainKey(): string
    {
        if ($this->chainKey !== null) {
            return $this->chainKey;
        }

        $candidates = [
            (string) env('audit.hashKey', ''),
            (string) env('encryption.key', ''),
        ];

        try {
            $candidates[] = (string) config('Encryption')->key;
        } catch (Throwable $e) {
            // Config not available; ignore.
        }

        foreach ($candidates as $candidate) {
            if (trim($candidate) !== '') {
                return $this->chainKey = $candidate;
            }
        }

        $this->chainKey = $this->provisionedKey();

        return $this->chainKey;
    }

    /**
     * Read (or create once) the installation's audit hash key in
     * system_settings. Falls back to an empty key when the table is missing.
     */
    private function provisionedKey(): string
    {
        try {
            $db = Database::connect();

            $row = $db->table('system_settings')
                ->select('setting_value')
                ->where('setting_key', 'audit_hash_key')
                ->get()
                ->getRowArray();

            if ($row !== null && trim((string) $row['setting_value']) !== '') {
                return (string) $row['setting_value'];
            }

            $key = bin2hex(random_bytes(32));
            $db->table('system_settings')->insert([
                'setting_key'   => 'audit_hash_key',
                'setting_value' => $key,
                'description'   => 'HMAC key for the tamper-evident activity log. Keep secret.',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            return $key;
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Actor (id/name/role) of the current session, or empty values for
     * anonymous requests (e.g. a failed login). Cached per instance.
     *
     * @return array{id: int|null, name: string, role: string}
     */
    private function actorContext(): array
    {
        if ($this->actorCache !== null) {
            return $this->actorCache;
        }

        $user = null;

        try {
            if (function_exists('auth') && auth()->loggedIn()) {
                $user = auth()->user();
            }
        } catch (Throwable $e) {
            // Session/database unavailable: fall through to the anonymous case.
        }

        return $this->actorCache = $this->describeActor($user);
    }

    /**
     * Resolve the actor (id/name/role) for a user object, or the anonymous
     * placeholder when none is given.
     *
     * Display-name precedence:
     *  1. users.first_name + users.last_name;
     *  2. the linked students/teachers profile (full name, matching how the
     *     admin student/teacher lists render names);
     *  3. users.username;
     *  4. the users.email column — never $user->email, which Shield's User
     *     entity resolves to the identity secret. Legacy rows of this app
     *     stored the password hash there, so it must never leak into the log;
     *  5. the caller-provided fallback name.
     *
     * Candidates that look like a stored secret are skipped; a signed-in user
     * with no usable name is recorded as "User #<id>".
     *
     * Public so the audit helper can reuse the exact same resolution
     * (see audit_actor_snapshot()).
     *
     * @return array{id: int|null, name: string, role: string}
     */
    public function describeActor(?object $user = null, string $fallbackName = ''): array
    {
        $context = ['id' => null, 'name' => '', 'role' => ''];

        try {
            $fallbackName = $this->oneLine($fallbackName, 191);

            if ($user === null) {
                $context['name'] = $fallbackName;

                return $context;
            }

            $userId        = (int) ($user->id ?? 0);
            $context['id'] = $userId > 0 ? $userId : null;

            $groups = method_exists($user, 'getGroups') ? $user->getGroups() : [];
            $groups = is_array($groups) ? array_values(array_map('strtolower', array_map('strval', $groups))) : [];
            $context['role'] = $groups !== [] ? (string) reset($groups) : '';

            // Evaluated lazily so the common case (a users row with names)
            // causes no extra query at all.
            $candidates = [
                static fn (): string => trim((string) ($user->first_name ?? '') . ' ' . (string) ($user->last_name ?? '')),
                fn (): string => $this->profileNameFromLinkedRecord($userId, $groups),
                static fn (): string => trim((string) ($user->username ?? '')),
                fn (): string => $this->userEmailColumn($userId),
                static function () use ($user): string {
                    try {
                        return function_exists('user_login_email') ? user_login_email($user) : '';
                    } catch (Throwable $e) {
                        return '';
                    }
                },
                static fn (): string => $fallbackName,
            ];

            foreach ($candidates as $candidate) {
                $candidate = $this->oneLine($candidate(), 191);

                if ($candidate !== '' && ! $this->isSecretLike($candidate)) {
                    $context['name'] = $candidate;

                    break;
                }
            }

            if ($context['name'] === '' && $userId > 0) {
                $context['name'] = 'User #' . $userId;
            }
        } catch (Throwable $e) {
            // Keep whatever was resolved so far; logging must never break.
        }

        return $context;
    }

    /**
     * Full name of the students/teachers profile linked to a user id.
     *
     * The role decides which table is authoritative; an unknown role probes
     * students first, then teachers. A missing table/column is not an error.
     *
     * @param list<string> $groups Lower-cased group titles.
     */
    private function profileNameFromLinkedRecord(int $userId, array $groups): string
    {
        if ($userId <= 0) {
            return '';
        }

        if (in_array('student', $groups, true)) {
            $tables = ['students'];
        } elseif (in_array('teacher', $groups, true)) {
            $tables = ['teachers'];
        } elseif ($groups === []) {
            $tables = ['students', 'teachers'];
        } else {
            // Another role (admin, parent...): the users row is authoritative.
            return '';
        }

        foreach ($tables as $table) {
            try {
                $row = Database::connect()->table($table)
                    ->select('first_name, middle_name, last_name, suffix')
                    ->where('user_id', $userId)
                    ->get()
                    ->getRowArray();

                if ($row === null) {
                    continue;
                }

                $name = $this->oneLine(implode(' ', array_filter([
                    (string) ($row['first_name'] ?? ''),
                    (string) ($row['middle_name'] ?? ''),
                    (string) ($row['last_name'] ?? ''),
                    (string) ($row['suffix'] ?? ''),
                ], static fn (string $part): bool => trim($part) !== '')), 191);

                if ($name !== '') {
                    return $name;
                }
            } catch (Throwable $e) {
                // Missing table/column or unavailable database: next candidate.
            }
        }

        return '';
    }

    /**
     * The users.email column. Shield's User entity ->email resolves through
     * getEmail() to the identity secret, which is why it cannot be used here.
     */
    private function userEmailColumn(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        try {
            $row = Database::connect()->table('users')
                ->select('email')
                ->where('id', $userId)
                ->get()
                ->getRowArray();

            return $row === null ? '' : trim((string) ($row['email'] ?? ''));
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * True when a value looks like a stored password hash / opaque secret.
     * Mirrors the by-value redaction rule of sanitize().
     */
    private function isSecretLike(string $value): bool
    {
        return preg_match('/^\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}$/', $value) === 1
            || str_starts_with($value, '$argon2')
            || preg_match('/^[a-f0-9]{64,128}$/i', $value) === 1;
    }

    /**
     * IP / user agent / method / path of the current HTTP request (CLI-safe).
     *
     * @return array{ip_address: string|null, user_agent: string, http_method: string, route: string}
     */
    private function requestContext(): array
    {
        $context = ['ip_address' => null, 'user_agent' => '', 'http_method' => '', 'route' => ''];

        try {
            if (is_cli()) {
                $context['http_method'] = 'CLI';
                $context['route']       = 'spark ' . (string) ($_SERVER['argv'][1] ?? 'command');

                return $context;
            }

            $request = service('request');
            if ($request !== null) {
                $context['ip_address']  = (string) $request->getIPAddress();
                $context['user_agent']  = (string) $request->getUserAgent()->getAgentString();
                $context['http_method'] = (string) $request->getMethod();
                $context['route']       = trim((string) $request->getUri()->getPath(), '/');
            }
        } catch (Throwable $e) {
            // Not an HTTP request context.
        }

        return $context;
    }

    /**
     * A key is sensitive when its normalized form contains a forbidden
     * substring or equals a forbidden word.
     */
    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $key) ?? $key);

        foreach (self::SENSITIVE_SUBSTRINGS as $needle) {
            if ($needle !== '' && str_contains($normalized, $needle)) {
                return true;
            }
        }

        $words = preg_split('/[^a-z0-9]+/', $normalized) ?: [];

        return array_intersect($words, self::SENSITIVE_WORDS) !== [];
    }

    /**
     * Remove control characters (including CR/LF) that would allow log
     * injection or corrupt a single-line column.
     */
    private function stripControlChars(string $value): string
    {
        return (string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
    }

    /**
     * Single-line, control-character-free, length-capped string.
     */
    private function oneLine(string $value, int $maxLength): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $this->stripControlChars($value)));

        return mb_substr($value, 0, $maxLength);
    }

    /**
     * Sanitize + JSON-encode a payload, replacing anything too large with a
     * marker (never truncating mid-JSON).
     */
    private function jsonBlob(mixed $payload): ?string
    {
        if ($payload === null || $payload === [] || $payload === '') {
            return null;
        }

        $clean = $this->sanitize($payload);
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
        $json  = json_encode($clean, $flags);

        if ($json === false) {
            return null;
        }

        if (strlen($json) > self::MAX_JSON_BYTES) {
            $json = json_encode([
                '_truncated' => true,
                '_bytes'     => strlen($json),
            ], $flags) ?: null;
        }

        return $json;
    }

    private function ipOrNull(mixed $ip): ?string
    {
        if (! is_string($ip) || $ip === '') {
            return null;
        }

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? substr($ip, 0, 45) : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param list<string> $allowed
     */
    private function enumOr(string $value, array $allowed, string $default): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function valuesEqual(mixed $a, mixed $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return json_encode($this->sanitize($a)) === json_encode($this->sanitize($b));
        }

        if (is_bool($a) || is_bool($b) || $a === null || $b === null) {
            return $a == $b;
        }

        return (string) $a === (string) $b;
    }
}

