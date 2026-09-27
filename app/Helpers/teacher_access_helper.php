<?php

declare(strict_types=1);

/**
 * Teacher portal access resolution.
 *
 * A teacher login account (a Shield user holding the `teacher` group) is only
 * usable while it still has a `teachers` row whose registration is approved and
 * whose employment is active. These helpers are the single source of truth for
 * that decision so the login flow, the post-login dashboard redirect, the demo
 * shortcut and the per-request TeacherAccessFilter cannot drift apart.
 *
 * States returned by teacher_account_state() / teacher_record_state():
 *   not_teacher - the account is not a teacher account, nothing to decide here
 *   no_record   - teacher account whose `teachers` row no longer exists (deleted)
 *   pending     - self-registration waiting for an administrator
 *   rejected    - self-registration turned down by an administrator
 *   inactive    - registration approved but employment is no longer active
 *   approved    - usable
 */

if (! function_exists('teacher_account_known_emails')) {
    /**
     * Email addresses a Shield user is known by.
     *
     * Shield keeps the account email in the auth identity, not in the users
     * table, and the legacy `secret` column may even hold a password hash -
     * user_login_email() filters those out.
     *
     * @param object|null $user
     * @return list<string>
     */
    function teacher_account_known_emails($user): array
    {
        if (! is_object($user)) {
            return [];
        }

        $emails = [];

        if (function_exists('user_login_email')) {
            $email = user_login_email($user);
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        $raw = $user->email ?? null;
        if (is_string($raw) && $raw !== '' && strpos($raw, '@') !== false) {
            $emails[] = trim($raw);
        }

        if (method_exists($user, 'getEmail')) {
            try {
                $raw = $user->getEmail();
            } catch (\Throwable $e) {
                $raw = null;
            }

            if (is_string($raw) && $raw !== '' && strpos($raw, '@') !== false) {
                $emails[] = trim($raw);
            }
        }

        return array_values(array_unique(array_filter($emails, static function (string $value): bool {
            return $value !== ''
                && ! str_starts_with($value, '$2')
                && ! str_starts_with($value, '$argon');
        })));
    }
}

if (! function_exists('teacher_account_claims_teacher_role')) {
    /**
     * True when the account itself claims the teacher role (holds the group).
     *
     * Never throws: inGroup() hits the database and may fail on a broken
     * connection, in which case the account is simply not treated as a teacher
     * here (its own role checks still apply).
     *
     * @param object|null $user
     */
    function teacher_account_claims_teacher_role($user): bool
    {
        if (! is_object($user) || ! method_exists($user, 'inGroup')) {
            return false;
        }

        try {
            return (bool) $user->inGroup('teacher');
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (! function_exists('find_teacher_record_for_user')) {
    /**
     * Resolve the `teachers` row that belongs to a Shield user.
     *
     * The `teachers.user_id` link is authoritative. Legacy rows were sometimes
     * saved without that link (user_id NULL), so an unlinked row may still be
     * adopted by matching the account's email address. Rows that are already
     * linked to a different user are never adopted, so a brand new account can
     * not inherit a deleted teacher's record.
     *
     * @param object|null $user Shield user entity (null when not signed in).
     * @return array<string, mixed>|null
     */
    function find_teacher_record_for_user($user): ?array
    {
        if (! is_object($user) || empty($user->id)) {
            return null;
        }

        $teacherModel = model(\App\Models\TeacherModel::class);

        $teacher = $teacherModel->where('user_id', (int) $user->id)->first();

        if ($teacher) {
            return $teacher;
        }

        // The email fallback only applies to accounts that already claim the
        // teacher role, so an unlucky/shared email match can never pull a
        // student or admin account into the teacher rules.
        if (! teacher_account_claims_teacher_role($user)) {
            return null;
        }

        foreach (teacher_account_known_emails($user) as $email) {
            $teacher = $teacherModel
                ->where('email', $email)
                ->groupStart()
                    ->where('user_id', null)
                    ->orWhere('user_id', 0)
                ->groupEnd()
                ->first();

            if ($teacher) {
                return $teacher;
            }
        }

        return null;
    }
}

if (! function_exists('teacher_record_state')) {
    /**
     * Access state for a resolved `teachers` row.
     *
     * @param array<string, mixed>|null $teacher
     */
    function teacher_record_state(?array $teacher): string
    {
        if ($teacher === null) {
            return 'no_record';
        }

        $status = strtolower(trim((string) ($teacher['registration_status'] ?? 'approved')));

        if ($status === 'pending') {
            return 'pending';
        }

        if ($status === 'rejected') {
            return 'rejected';
        }

        $employment = strtolower(trim((string) ($teacher['employment_status'] ?? 'active')));

        if ($employment !== '' && $employment !== 'active') {
            return 'inactive';
        }

        return 'approved';
    }
}

if (! function_exists('teacher_account_state')) {
    /**
     * Access state for a login account (see the file docblock for the values).
     *
     * @param object|null $user Shield user entity.
     */
    function teacher_account_state($user): string
    {
        if (! is_object($user) || ! method_exists($user, 'inGroup')) {
            return 'not_teacher';
        }

        $inGroup = teacher_account_claims_teacher_role($user);
        $teacher = find_teacher_record_for_user($user);

        // A linked teachers row makes this a teacher account even when the
        // 'teacher' group is missing: a self-registration gets its row before
        // any group exists, and rejecting an application detaches the group
        // while the row stays behind. Without this, a rejected applicant could
        // slip past as a "roleless" account.
        if (! $inGroup && $teacher === null) {
            return 'not_teacher';
        }

        return teacher_record_state($teacher);
    }
}

if (! function_exists('teacher_account_allowed')) {
    /**
     * True when the account may sign in / reach the teacher portal.
     *
     * Non-teacher accounts are always "allowed" here - they are governed by
     * their own role checks.
     *
     * @param object|null $user Shield user entity.
     */
    function teacher_account_allowed($user): bool
    {
        return in_array(teacher_account_state($user), ['not_teacher', 'approved'], true);
    }
}

if (! function_exists('teacher_account_denial_message')) {
    /**
     * User-facing reason why a teacher account was refused.
     */
    function teacher_account_denial_message(string $state): string
    {
        return match ($state) {
            'pending' => 'Your teacher registration is still awaiting administrator approval. Please try again once your account has been approved.',
            'rejected' => 'Your teacher registration was rejected by the school administrator, so this account can no longer be used to sign in. Please contact the school office if you believe this is a mistake.',
            'inactive' => 'This teacher account is no longer active. Please contact the school office.',
            default => 'This account is no longer active. Please contact the school office.',
        };
    }
}