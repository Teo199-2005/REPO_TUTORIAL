<?php

declare(strict_types=1);

/**
 * Demo / Quick Access account support for the login page.
 *
 * The login screen advertises a handful of hardcoded "Quick Access" logins so
 * reviewers can look around the system without a real account. Those logins
 * live in the database, so they drift: the `users` row survives a data reset
 * while its `teachers` row is wiped, the password gets rotated, repeat seeding
 * piles up duplicate group rows, and so on.
 *
 * When a teachers row goes missing the teacher shortcut is refused by the
 * approval workflow with "This account is no longer active. Please contact the
 * school office." - a dead end for a demo login whose only purpose is to get
 * someone inside.
 *
 * This helper is the single source of truth for the demo fixtures (the
 * controller and the floater both read it) and owns the repair routine that
 * puts a demo account back into a usable state.
 *
 * SAFETY: every routine below refuses to touch anything that is not one of the
 * hardcoded demo addresses returned by demo_account_emails(). A real school
 * account can therefore never be re-approved, re-linked, group-cleaned or
 * password-reset through the quick access path. Demo logins themselves are also
 * limited to non-production environments (see demo_accounts_enabled()).
 */

if (! function_exists('demo_accounts_enabled')) {
    /**
     * Quick access is a development/review tool only. It must never expose a
     * shortcut on the school's live login page.
     */
    function demo_accounts_enabled(): bool
    {
        return ENVIRONMENT !== 'production';
    }
}

if (! function_exists('demo_account_fixtures')) {
    /**
     * Canonical quick-access accounts.
     *
     * `teacher` / `student` carry the fixture used to rebuild a wiped domain
     * row so a broken demo login can be restored without re-running the seeder.
     *
     * @return array<string, array<string, mixed>> role key => fixture
     */
    function demo_account_fixtures(): array
    {
        return [
            'admin' => [
                'role'     => 'admin',
                'group'    => 'admin',
                'name'     => 'Admin Access',
                'email'    => 'demo.admin@lphs.edu',
                'password' => 'DemoPass123!',
                'icon'     => 'bi-shield-lock-fill',
                'redirect' => 'admin/dashboard',
            ],
            'teacher/nonnumeric' => [
                'role'       => 'teacher',
                'group'      => 'teacher',
                'name'       => 'Maria Santos',
                'email'      => 'teacher.santos@lphs.edu',
                'password'   => 'Teacher123!',
                'icon'       => 'bi-person-badge-fill',
                'redirect'   => 'teacher/dashboard',
                'identifier' => 'PRC-2024-001',
                'teacher'    => [
                    'employee_id'    => 'TCHR-2024-001',
                    'license_number' => 'PRC-2024-001',
                    'first_name'     => 'Maria',
                    'last_name'      => 'Santos',
                    'gender'         => 'Female',
                    'date_of_birth'  => '1985-03-15',
                    'contact_number' => '09123456701',
                    'address'        => '123 Rizal St, Cauayan City',
                    'department'     => 'Mathematics',
                    'position'       => 'Teacher III',
                    'specialization' => 'Mathematics',
                    'date_hired'     => '2020-06-15',
                ],
            ],
            'teacher/numerical' => [
                'role'       => 'teacher',
                'group'      => 'teacher',
                'name'       => 'Juan Reyes',
                'email'      => 'teacher.reyes@lphs.edu',
                'password'   => 'Teacher123!',
                'icon'       => 'bi-person-badge-fill',
                'redirect'   => 'teacher/dashboard',
                'identifier' => 'EMP-2024-002',
                'teacher'    => [
                    'employee_id'    => 'EMP-2024-002',
                    'license_number' => 'EMP-2024-002',
                    'first_name'     => 'Juan',
                    'last_name'      => 'Reyes',
                    'gender'         => 'Male',
                    'date_of_birth'  => '1988-07-22',
                    'contact_number' => '09123456702',
                    'address'        => '456 Bonifacio Ave, Cauayan City',
                    'department'     => 'English',
                    'position'       => 'Teacher I',
                    'specialization' => 'English Literature',
                    'date_hired'     => '2022-07-01',
                ],
            ],
            'student/numerical' => [
                'role'       => 'student',
                'group'      => 'student',
                'name'       => 'Demo Student1',
                'email'      => 'demo.student1@lphs.edu',
                'password'   => 'DemoPass123!',
                'icon'       => 'bi-mortarboard-fill',
                'redirect'   => 'student/dashboard',
                'identifier' => '136001000010',
                'student'    => [
                    'lrn'                            => '136001000010',
                    'first_name'                     => 'Demo',
                    'last_name'                      => 'Student1',
                    'gender'                         => 'Male',
                    'date_of_birth'                  => '2015-01-01',
                    'grade_level'                    => 1,
                    'address'                        => 'Demo Address, City',
                    'contact_number'                 => '09123456789',
                    'emergency_contact_name'         => 'Demo Parent',
                    'emergency_contact_number'       => '09987654321',
                    'emergency_contact_relationship' => 'Parent',
                ],
            ],
            'student/nonnumeric' => [
                'role'       => 'student',
                'group'      => 'student',
                'name'       => 'Demo Student2',
                'email'      => 'demo.student2@lphs.edu',
                'password'   => 'DemoPass123!',
                'icon'       => 'bi-mortarboard-fill',
                'redirect'   => 'student/dashboard',
                'identifier' => 'STU-2024-DEMO',
                'student'    => [
                    'lrn'                            => 'STU-2024-DEMO',
                    'first_name'                     => 'Demo',
                    'last_name'                      => 'Student2',
                    'gender'                         => 'Female',
                    'date_of_birth'                  => '2015-05-15',
                    'grade_level'                    => 1,
                    'address'                        => 'Demo Address, City',
                    'contact_number'                 => '09234567890',
                    'emergency_contact_name'         => 'Demo Parent',
                    'emergency_contact_number'       => '09987654321',
                    'emergency_contact_relationship' => 'Parent',
                ],
            ],
        ];
    }
}

if (! function_exists('demo_account_fixture')) {
    /**
     * @return array<string, mixed>|null
     */
    function demo_account_fixture(string $roleKey): ?array
    {
        $fixtures = demo_account_fixtures();

        return $fixtures[strtolower(trim($roleKey))] ?? null;
    }
}

if (! function_exists('demo_account_emails')) {
    /**
     * Every address the repair routines are allowed to touch.
     *
     * @return list<string>
     */
    function demo_account_emails(): array
    {
        $emails = [];

        foreach (demo_account_fixtures() as $fixture) {
            $email = strtolower(trim((string) ($fixture['email'] ?? '')));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }
}

if (! function_exists('demo_account_is_demo_email')) {
    /**
     * Guard for every write in this file: demo addresses only, never real ones.
     */
    function demo_account_is_demo_email(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' && in_array($email, demo_account_emails(), true);
    }
}

if (! function_exists('demo_account_role_groups')) {
    /**
     * Floater layout: heading => role keys, in display order.
     *
     * @return array<string, list<string>>
     */
    function demo_account_role_groups(): array
    {
        return [
            'Administrator' => ['admin'],
            'Teachers'      => ['teacher/nonnumeric', 'teacher/numerical'],
            'Students'      => ['student/numerical', 'student/nonnumeric'],
        ];
    }
}

if (! function_exists('demo_account_reconcile')) {
    /**
     * Bring a quick-access account back into a usable state.
     *
     * Repairs, in order:
     *   1. missing `users` row                    -> create it
     *   2. disabled / soft-deleted account        -> re-enable
     *   3. password drift or unreadable hash      -> rewrite auth_identities
     *   4. duplicate or missing role group        -> de-duplicate, keep one
     *   5. missing teachers / students domain row -> rebuild it
     *
     * Never throws: an unreachable database yields status `unavailable` so the
     * login page still renders.
     *
     * @return array{status:string, repaired:bool, reason:string, user_id:int}
     */
    function demo_account_reconcile(string $roleKey): array
    {
        $result = [
            'status'   => 'unavailable',
            'repaired' => false,
            'reason'   => 'Demo database not reachable.',
            'user_id'  => 0,
        ];

        $fixture = demo_account_fixture($roleKey);
        if ($fixture === null) {
            $result['reason'] = 'Unknown quick-access role.';

            return $result;
        }

        $email = strtolower(trim((string) $fixture['email']));

        // Hard guard: nothing outside the demo whitelist is ever written to.
        if (! demo_account_is_demo_email($email)) {
            $result['status'] = 'error';
            $result['reason'] = 'Refused: not a demo account.';

            return $result;
        }

        try {
            $db = \Config\Database::connect();
        } catch (\Throwable $e) {
            return $result; // status stays `unavailable`
        }

        try {
            $userId = demo_account_ensure_user($db, $fixture, $result);
            if ($userId <= 0) {
                $result['status'] = 'error';
                $result['reason'] = 'Could not create the demo user row.';

                return $result;
            }

            $result['user_id'] = $userId;

            demo_account_ensure_password($db, $userId, $email, (string) $fixture['password'], $result);
            demo_account_ensure_group($db, $userId, (string) $fixture['group'], $result);

            $reason = match ((string) $fixture['role']) {
                'teacher' => demo_account_ensure_teacher_row($db, $fixture, $userId, $result),
                'student' => demo_account_ensure_student_row($db, $fixture, $userId, $result),
                default   => '',
            };

            if ($reason !== '') {
                $result['status'] = 'error';
                $result['reason'] = $reason;

                return $result;
            }

            $result['status'] = 'ok';
            $result['reason'] = $result['repaired']
                ? 'Quick access account repaired.'
                : 'Quick access account is ready.';

            return $result;
        } catch (\Throwable $e) {
            log_message('error', 'Quick access repair failed for ' . $email . ': ' . $e->getMessage());

            $result['status'] = 'error';
            $result['reason'] = 'Repair failed: ' . $e->getMessage();

            return $result;
        }
    }
}

if (! function_exists('demo_account_ensure_user')) {
    /**
     * Make sure the Shield `users` row exists, is enabled and is not soft-deleted.
     *
     * Repairs are counted through $result['repaired'] so the caller can tell
     * "already fine" apart from "we had to fix something".
     *
     * @param array<string, mixed> $fixture
     * @param array<string, mixed> $result  By reference: flips `repaired`.
     */
    function demo_account_ensure_user($db, array $fixture, array &$result): int
    {
        $email = strtolower(trim((string) $fixture['email']));
        $now   = date('Y-m-d H:i:s');

        $user = $db->table('users')->where('email', $email)->get()->getRow();

        if (! $user) {
            $name  = trim((string) ($fixture['name'] ?? 'Demo Account'));
            $parts = preg_split('/\s+/', $name, 2) ?: [$name];

            // `username` is left NULL on purpose: it carries a unique index and
            // the login form only ever matches on email.
            $db->table('users')->insert([
                'email'      => $email,
                'first_name' => $parts[0] ?? null,
                'last_name'  => $parts[1] ?? null,
                'active'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $insertId = (int) $db->insertID();
            if ($insertId <= 0) {
                return 0;
            }

            $result['repaired'] = true;

            return $insertId;
        }

        $userId  = (int) $user->id;
        $changes = [];

        // A disabled or soft-deleted demo account is exactly the kind of drift
        // that makes the quick-access button dead on arrival.
        if ((int) ($user->active ?? 0) !== 1) {
            $changes['active'] = 1;
        }

        if (! empty($user->deleted_at)) {
            $changes['deleted_at'] = null;
        }

        if (! empty($user->status)) {
            $changes['status']         = null;
            $changes['status_message'] = null;
        }

        if ($changes !== []) {
            $changes['updated_at'] = $now;
            $db->table('users')->where('id', $userId)->update($changes);
            $result['repaired'] = true;
        }

        return $userId;
    }
}

if (! function_exists('demo_account_ensure_password')) {
    /**
     * Make sure the demo password in auth_identities is the published one.
     *
     * Drift here is the second silent killer: re-running the seeder, a manual
     * reset, or a legacy row whose hash sits in `secret` instead of `secret2`
     * all leave the account unable to sign in even though the row exists.
     *
     * A third failure mode is a *stale* identity. `auth_identities` carries a
     * unique key on (type, secret), so an orphan row left behind by an early
     * seeder run - one that still claims the demo address as its name/secret
     * while hanging off a different, since-emptied users row - makes every
     * rewrite of the real account fail with "Duplicate entry
     * 'email_password-<demo address>'". The orphans are cleared first.
     *
     * @param array<string, mixed> $result By reference: flips `repaired`.
     */
    function demo_account_ensure_password($db, int $userId, string $email, string $password, array &$result): void
    {
        // $email already passed demo_account_is_demo_email() in the caller, so
        // this can only ever match rows that claim a demo address.
        $db->table('auth_identities')
            ->where('type', 'email_password')
            ->groupStart()
                ->where('name', $email)
                ->orWhere('secret', $email)
            ->groupEnd()
            ->where('user_id !=', $userId)
            ->delete();

        if ($db->affectedRows() > 0) {
            $result['repaired'] = true;
        }

        $identity = $db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRow();

        $matches = $identity
            && trim((string) ($identity->secret ?? '')) === $email
            && verify_auth_identity_password($password, $identity);

        if ($matches) {
            return;
        }

        // sync_auth_password() writes the canonical Shield layout
        // (name/secret = email, secret2 = hash) and replaces any stale rows.
        if (sync_auth_password($userId, $email, $password)) {
            $result['repaired'] = true;
        }
    }
}

if (! function_exists('demo_account_ensure_group')) {
    /**
     * Collapse a demo account down to exactly one row of its role group.
     *
     * `auth_groups_users` has no unique key on (user_id, group) - only a
     * foreign key on user_id - so every seeder run that inserts with ignore()
     * leaves another duplicate behind. This database currently holds ten rows
     * of `teacher` for a single demo teacher. Duplicates and leftover
     * wrong-role rows are removed; the fixture's group wins.
     *
     * @param array<string, mixed> $result By reference: flips `repaired`.
     */
    function demo_account_ensure_group($db, int $userId, string $group, array &$result): void
    {
        $rows = $db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->get()
            ->getResult();

        $keep   = null;
        $remove = [];

        foreach ($rows as $row) {
            $isRole    = (string) $row->group === $group;
            $isKeepable = $isRole && $keep === null;

            if ($isKeepable) {
                $keep = (int) $row->id;
                continue;
            }

            $remove[] = (int) $row->id;
        }

        if ($remove !== []) {
            $db->table('auth_groups_users')->whereIn('id', $remove)->delete();
            $result['repaired'] = true;
        }

        if ($keep !== null) {
            return;
        }

        $db->table('auth_groups_users')->insert([
            'user_id'    => $userId,
            'group'      => $group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result['repaired'] = true;
    }
}

if (! function_exists('demo_account_ensure_teacher_row')) {
    /**
     * Rebuild / re-approve the `teachers` row a demo teacher signs in with.
     *
     * This is the actual fix for "This account is no longer active. Please
     * contact the school office.": teacher_account_state() resolves the row via
     * find_teacher_record_for_user() and, with no row at all, reports
     * `no_record`, which the denial message renders as that generic refusal.
     *
     * A row that exists but sits on a stale user link, a pending/rejected
     * registration or a non-active employment status is repaired in place
     * instead of being duplicated, so attachments (sections, grades) survive.
     *
     * @param array<string, mixed> $fixture
     * @param array<string, mixed> $result By reference: flips `repaired`.
     * @return string Empty when healthy, otherwise a failure reason.
     */
    function demo_account_ensure_teacher_row($db, array $fixture, int $userId, array &$result): string
    {
        $email   = strtolower(trim((string) $fixture['email']));
        $profile = (array) ($fixture['teacher'] ?? []);
        $now     = date('Y-m-d H:i:s');

        $row = $db->table('teachers')->where('user_id', $userId)->get()->getRow();

        // Legacy rows created before user linking exist with the right email but
        // no user_id; adopt them rather than creating a second teachers record.
        if (! $row) {
            $row = $db->table('teachers')
                ->where('email', $email)
                ->groupStart()
                    ->where('user_id', null)
                    ->orWhere('user_id', 0)
                ->groupEnd()
                ->get()
                ->getRow();
        }

        if (! $row) {
            $insert = array_merge($profile, [
                'user_id'             => $userId,
                'email'               => $email,
                'employee_id'         => $profile['employee_id'] ?? null,
                'license_number'      => $profile['license_number'] ?? null,
                'employment_status'   => 'active',
                'registration_status' => 'approved',
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            if ($db->table('teachers')->insert($insert) === false) {
                return 'Could not rebuild the demo teacher record.';
            }

            $result['repaired'] = true;

            return '';
        }

        $teacherId = (int) $row->id;
        $changes   = [];

        if ((int) ($row->user_id ?? 0) !== $userId) {
            $changes['user_id'] = $userId;
        }

        if (trim((string) ($row->email ?? '')) === '') {
            $changes['email'] = $email;
        }

        if (strtolower(trim((string) ($row->registration_status ?? ''))) !== 'approved') {
            $changes['registration_status']      = 'approved';
            $changes['registration_notes']       = null;
            $changes['registration_reviewed_at'] = $now;
        }

        if (strtolower(trim((string) ($row->employment_status ?? ''))) !== 'active') {
            $changes['employment_status'] = 'active';
        }

        if (! empty($row->deleted_at)) {
            $changes['deleted_at'] = null;
        }

        if ($changes === []) {
            return '';
        }

        $changes['updated_at'] = $now;
        $db->table('teachers')->where('id', $teacherId)->update($changes);
        $result['repaired'] = true;

        return '';
    }
}

if (! function_exists('demo_account_ensure_student_row')) {
    /**
     * Rebuild the `students` row a demo student signs in with.
     *
     * Same class of drift as the teachers case: the `users` row outlives the
     * domain row, leaving a login that authenticates but has no enrollment to
     * show. Section context is borrowed from an existing demo student (or the
     * first Grade 1 section) so the demo dashboard is not empty, and is left
     * NULL when the school has not set up sections yet.
     *
     * @param array<string, mixed> $fixture
     * @param array<string, mixed> $result By reference: flips `repaired`.
     * @return string Empty when healthy, otherwise a failure reason.
     */
    function demo_account_ensure_student_row($db, array $fixture, int $userId, array &$result): string
    {
        $email   = strtolower(trim((string) $fixture['email']));
        $profile = (array) ($fixture['student'] ?? []);
        $lrn     = (string) ($profile['lrn'] ?? $fixture['identifier'] ?? '');
        $now     = date('Y-m-d H:i:s');

        $row = $db->table('students')->where('user_id', $userId)->get()->getRow();

        if (! $row && $lrn !== '') {
            $row = $db->table('students')->where('lrn', $lrn)->get()->getRow();
        }

        if (! $row) {
            $context = demo_account_student_section_context($db);

            $insert = array_merge($profile, [
                'user_id'           => $userId,
                'email'             => $email,
                'enrollment_status' => 'enrolled',
                'section_id'        => $context['section_id'],
                'school_year'       => $context['school_year'],
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            if ($db->table('students')->insert($insert) === false) {
                return 'Could not rebuild the demo student record.';
            }

            $result['repaired'] = true;

            return '';
        }

        $studentId = (int) $row->id;
        $changes   = [];

        if ((int) ($row->user_id ?? 0) !== $userId) {
            $changes['user_id'] = $userId;
        }

        if (trim((string) ($row->email ?? '')) === '') {
            $changes['email'] = $email;
        }

        if (strtolower(trim((string) ($row->enrollment_status ?? ''))) === '') {
            $changes['enrollment_status'] = 'enrolled';
        }

        if (! empty($row->deleted_at)) {
            $changes['deleted_at'] = null;
        }

        if ($changes === []) {
            return '';
        }

        $changes['updated_at'] = $now;
        $db->table('students')->where('id', $studentId)->update($changes);
        $result['repaired'] = true;

        return '';
    }
}

if (! function_exists('demo_account_student_section_context')) {
    /**
     * Section + school year a rebuilt demo student should be placed in.
     *
     * @return array{section_id:int|null, school_year:string|null}
     */
    function demo_account_student_section_context($db): array
    {
        $context = ['section_id' => null, 'school_year' => null];

        // Prefer the placement an existing demo student already has.
        $existing = $db->table('students')
            ->where('lrn', '136001000010')
            ->get()
            ->getRow();

        if ($existing && ! empty($existing->section_id)) {
            $context['section_id']  = (int) $existing->section_id;
            $context['school_year'] = $existing->school_year !== null ? (string) $existing->school_year : null;

            return $context;
        }

        // Otherwise fall back to the first open Grade 1 section.
        $section = $db->table('sections')
            ->where('grade_level', '1')
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRow();

        if ($section) {
            $context['section_id']  = (int) $section->id;
            $context['school_year'] = ! empty($section->school_year) ? (string) $section->school_year : null;
        }

        if ($context['school_year'] === null && function_exists('get_current_school_year')) {
            $context['school_year'] = (string) get_current_school_year();
        }

        return $context;
    }
}

if (! function_exists('demo_account_repair_all')) {
    /**
     * Run the repair pass over every quick-access account.
     *
     * @return array<string, array{status:string, repaired:bool, reason:string, user_id:int}>
     */
    function demo_account_repair_all(): array
    {
        $results = [];

        foreach (array_keys(demo_account_fixtures()) as $roleKey) {
            $results[$roleKey] = demo_account_reconcile($roleKey);
        }

        return $results;
    }
}


