<?php

declare(strict_types=1);

/**
 * Thin, fail-safe wrapper around App\Libraries\AuditLogger.
 *
 * Controllers call audit_event() at the point where a business decision is
 * made (login succeeded, student archived, page permissions changed...).
 * The helper never throws: logging must never be able to break the request.
 */

use App\Libraries\AuditLogger;

if (! function_exists('audit_event')) {
    /**
     * Record one activity/audit event.
     *
     * @param string $action  Machine-readable name, e.g. 'auth.login', 'student.updated'
     * @param array  $context Optional keys: category, status, resource_type,
     *                        resource_id, description, actor_user_id, actor_name,
     *                        actor_role, ip_address, user_agent, before, after,
     *                        metadata, http_method, route
     */
    function audit_event(string $action, array $context = []): void
    {
        try {
            service('auditLogger')->record($action === '' ? $context : ['action' => $action] + $context);
        } catch (Throwable $e) {
            // Fall back to the file log; the request itself continues.
            log_message('error', 'audit_event failed [' . $action . ']: ' . $e->getMessage());
        }
    }
}

if (! function_exists('audit_diff')) {
    /**
     * Changed fields only, sanitized, ready for audit_event()'s before/after.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param list<string>         $fields Empty = union of both arrays' keys
     *
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    function audit_diff(array $before, array $after, array $fields = []): array
    {
        try {
            $diff = service('auditLogger')->diff($before, $after, $fields);

            return ['before' => $diff['before'], 'after' => $diff['after']];
        } catch (Throwable $e) {
            return ['before' => [], 'after' => []];
        }
    }
}

if (! function_exists('audit_changed_fields')) {
    /**
     * Names of the fields that actually changed (for descriptions/metadata).
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param list<string>         $fields
     *
     * @return list<string>
     */
    function audit_changed_fields(array $before, array $after, array $fields = []): array
    {
        try {
            return service('auditLogger')->diff($before, $after, $fields)['changed'];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (! function_exists('audit_retention_days')) {
    /**
     * Retention window for the `php spark audit:prune` command, stored in
     * system_settings as `audit_retention_days`. Never below 30 days.
     */
    function audit_retention_days(): int
    {
        $days = 365;

        try {
            $value = (new \App\Models\SystemSettingModel())->getSetting('audit_retention_days', null);
            if ($value !== null && is_numeric($value)) {
                $days = (int) $value;
            }
        } catch (Throwable $e) {
            // Keep the default.
        }

        return max(30, $days);
    }
}

if (! function_exists('audit_actor_snapshot')) {
    /**
     * Lightweight actor info for events raised before/without a session lookup
     * (e.g. a failed login where the account exists but is not signed in).
     *
     * @return array{actor_user_id: int|null, actor_name: string, actor_role: string}
     */
    function audit_actor_snapshot(?object $user, ?string $fallbackName = null): array
    {
        $snapshot = [
            'actor_user_id' => null,
            'actor_name'    => (string) ($fallbackName ?? ''),
            'actor_role'    => '',
        ];

        try {
            // Same resolution the writer uses: profile names, the linked
            // students/teachers record, username, the real users.email — never
            // a legacy identity secret. See AuditLogger::describeActor().
            $actor = service('auditLogger')->describeActor($user, (string) ($fallbackName ?? ''));

            $snapshot['actor_user_id'] = $actor['id'];
            $snapshot['actor_name']    = $actor['name'];
            $snapshot['actor_role']    = $actor['role'];
        } catch (Throwable $e) {
            // Keep whatever we have.
        }

        return $snapshot;
    }
}

if (! function_exists('audit_actor_name_is_placeholder')) {
    /**
     * True when a stored actor name is empty or a leftover secret-like value.
     *
     * Legacy rows persisted the Shield identity secret as the actor name
     * (for some accounts that secret was the password hash), so those values
     * are placeholders to be resolved from actor_user_id at display time.
     */
    function audit_actor_name_is_placeholder(?string $name): bool
    {
        $name = trim((string) $name);

        if ($name === '') {
            return true;
        }

        return preg_match('/^\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}$/', $name) === 1
            || str_starts_with($name, '$argon2')
            || preg_match('/^[a-f0-9]{64,128}$/i', $name) === 1;
    }
}

if (! function_exists('audit_first_usable_actor_name')) {
    /**
     * First candidate that is a real name (non-empty and not secret-like).
     *
     * @param list<string> $candidates
     */
    function audit_first_usable_actor_name(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '' && ! audit_actor_name_is_placeholder($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}

if (! function_exists('audit_resolve_actor_names')) {
    /**
     * Resolve display names for audit rows whose stored `actor_name` is empty
     * or a leftover secret (legacy entries).
     *
     * The activity log is append-only and hash-chained, so stored rows are
     * NEVER modified: the returned copy only overrides `actor_name` for
     * rendering or CSV export. Unknown actors fall back to "User #<id>", and
     * on any error the rows are returned unchanged.
     *
     * @param array<int, array<string, mixed>> $rows Rows with actor_user_id
     *                                               and actor_name keys.
     *
     * @return array<int, array<string, mixed>>
     */
    function audit_resolve_actor_names(array $rows): array
    {
        try {
            $roles = [];

            foreach ($rows as $row) {
                $id = (int) ($row['actor_user_id'] ?? 0);

                if ($id <= 0 || ! audit_actor_name_is_placeholder((string) ($row['actor_name'] ?? ''))) {
                    continue;
                }

                $role = strtolower(trim((string) ($row['actor_role'] ?? '')));

                if (! isset($roles[$id]) || ($roles[$id] === '' && $role !== '')) {
                    $roles[$id] = $role;
                }
            }

            if ($roles === []) {
                return $rows;
            }

            $names = audit_actor_names_by_user_id(array_keys($roles), $roles);

            foreach ($rows as $index => $row) {
                $id = (int) ($row['actor_user_id'] ?? 0);

                if ($id <= 0 || ! audit_actor_name_is_placeholder((string) ($row['actor_name'] ?? ''))) {
                    continue;
                }

                $resolved = $names[$id] ?? '';

                $rows[$index]['actor_name'] = $resolved !== '' ? $resolved : 'User #' . $id;
            }
        } catch (Throwable $e) {
            return $rows;
        }

        return $rows;
    }
}

if (! function_exists('audit_actor_names_by_user_id')) {
    /**
     * user_id => display name map for audit actors, resolved (in order) from
     * the users profile, the linked students/teachers record, the username
     * and the users.email column. Secret-like values are never returned.
     *
     * @param list<int>          $userIds
     * @param array<int, string> $roles   user_id => lower-cased role hint
     *
     * @return array<int, string>
     */
    function audit_actor_names_by_user_id(array $userIds, array $roles = []): array
    {
        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($userIds === []) {
            return [];
        }

        $userNames    = [];
        $profileNames = [];

        try {
            $db = \Config\Database::connect();

            $rows = $db->table('users')
                ->select('id, email, username, first_name, last_name')
                ->whereIn('id', $userIds)
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $userNames[(int) $row['id']] = audit_first_usable_actor_name([
                    trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
                    (string) ($row['username'] ?? ''),
                    (string) ($row['email'] ?? ''),
                ]);
            }

            foreach (['students', 'teachers'] as $table) {
                $rows = $db->table($table)
                    ->select('user_id, first_name, middle_name, last_name, suffix')
                    ->whereIn('user_id', $userIds)
                    ->get()
                    ->getResultArray();

                foreach ($rows as $row) {
                    $id   = (int) ($row['user_id'] ?? 0);
                    $name = audit_first_usable_actor_name([
                        implode(' ', array_filter([
                            (string) ($row['first_name'] ?? ''),
                            (string) ($row['middle_name'] ?? ''),
                            (string) ($row['last_name'] ?? ''),
                            (string) ($row['suffix'] ?? ''),
                        ], static fn (string $part): bool => trim($part) !== '')),
                    ]);

                    if ($id > 0 && $name !== '') {
                        $profileNames[$table][$id] = $name;
                    }
                }
            }
        } catch (Throwable $e) {
            // Missing tables/columns: fall through with whatever was read.
        }

        $names = [];

        foreach ($userIds as $id) {
            if (($userNames[$id] ?? '') !== '') {
                $names[$id] = $userNames[$id];

                continue;
            }

            $role   = strtolower((string) ($roles[$id] ?? ''));
            $tables = $role === 'teacher' ? ['teachers', 'students'] : ['students', 'teachers'];

            foreach ($tables as $table) {
                if (($profileNames[$table][$id] ?? '') !== '') {
                    $names[$id] = $profileNames[$table][$id];

                    break;
                }
            }
        }

        return $names;
    }
}

if (! function_exists('audit_actor_ids_matching')) {
    /**
     * User ids whose current display name matches a free-text query, so the
     * activity log search also finds rows whose stored actor_name is a legacy
     * placeholder (e.g. a hashed identity secret).
     *
     * Every word of the query must appear in the candidate's name parts
     * (so "Mark Loyd" still matches "Mark Mqweqwe Loyd"). Matching is done in
     * PHP so it behaves identically on MySQL and SQLite.
     *
     * @return list<int>
     */
    function audit_actor_ids_matching(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $words    = array_values(array_filter(preg_split('/\s+/', mb_strtolower($query)) ?: [], static fn (string $word): bool => $word !== ''));
        $matched  = [];

        if ($words === []) {
            return [];
        }

        try {
            $db = \Config\Database::connect();

            $sources = [
                ['users', 'id', ['first_name', 'last_name', 'username', 'email']],
                ['students', 'user_id', ['first_name', 'middle_name', 'last_name', 'suffix']],
                ['teachers', 'user_id', ['first_name', 'middle_name', 'last_name', 'suffix']],
            ];

            foreach ($sources as [$table, $idColumn, $nameColumns]) {
                $rows = $db->table($table)
                    ->select(implode(', ', array_merge([$idColumn], $nameColumns)))
                    ->get()
                    ->getResultArray();

                foreach ($rows as $row) {
                    $id = (int) ($row[$idColumn] ?? 0);

                    if ($id <= 0) {
                        continue;
                    }

                    $parts = [];

                    foreach ($nameColumns as $column) {
                        $value = trim((string) ($row[$column] ?? ''));

                        if ($value !== '') {
                            $parts[] = $value;
                        }
                    }

                    $haystack = mb_strtolower(implode(' ', $parts));

                    if ($haystack === '') {
                        continue;
                    }

                    foreach ($words as $word) {
                        if (! str_contains($haystack, $word)) {
                            continue 2;
                        }
                    }

                    $matched[$id] = true;
                }
            }
        } catch (Throwable $e) {
            return array_map('intval', array_keys($matched));
        }

        return array_map('intval', array_keys($matched));
    }
}

