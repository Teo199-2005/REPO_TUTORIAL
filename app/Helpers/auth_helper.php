<?php

declare(strict_types=1);

if (! function_exists('verify_auth_identity_password')) {
    /**
     * Verify a plaintext password against an auth_identities row (Shield + legacy formats).
     */
    function verify_auth_identity_password(string $password, ?object $identity): bool
    {
        if ($identity === null) {
            return false;
        }

        $secret  = (string) ($identity->secret ?? '');
        $secret2 = (string) ($identity->secret2 ?? '');

        foreach ([$secret2, $secret] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $info = password_get_info($candidate);
            if (($info['algo'] ?? 0) !== 0 && password_verify($password, $candidate)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('sync_auth_password')) {
    /**
     * Store login password in auth_identities (works with Auth::performLoginAttempt
     * and Shield). Canonical Shield format:
     *   name    = email
     *   secret  = email
     *   secret2 = password hash
     * verify_auth_identity_password() also tolerates the legacy layout where the
     * hash sits in secret, but keeping secret = email is what makes Shield's
     * User::getEmail() return the address instead of a password hash.
     */
    function sync_auth_password(int $userId, string $email, string $plainPassword): bool
    {
        if ($userId <= 0 || $plainPassword === '') {
            return false;
        }

        $db             = \Config\Database::connect();
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $now            = date('Y-m-d H:i:s');
        $cleanEmail     = str_replace('mailto:', '', trim($email));

        $existing = $db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRow();

        $payload = [
            'name'       => $cleanEmail,
            'secret'     => $cleanEmail,
            'secret2'    => $hashedPassword,
            'updated_at' => $now,
        ];

        if ($existing) {
            return $db->table('auth_identities')
                ->where('user_id', $userId)
                ->where('type', 'email_password')
                ->update($payload);
        }

        $payload['user_id']      = $userId;
        $payload['type']         = 'email_password';
        $payload['expires']      = null;
        $payload['extra']        = null;
        $payload['force_reset']  = 0;
        $payload['last_used_at'] = null;
        $payload['created_at']   = $now;

        return $db->table('auth_identities')->insert($payload) !== false;
    }
}

if (! function_exists('user_login_email')) {
    /**
     * Safely resolve the email address of a Shield user.
     *
     * Shield's User::getEmail() returns the identity's `secret` column, which
     * in legacy rows of this app may hold a password hash instead of the
     * email. This helper only trusts values that actually look like an email
     * and falls back to the identity `name` column otherwise.
     */
    function user_login_email($user): string
    {
        if ($user === null) {
            return '';
        }

        // Same guard the greeting uses, so the two can never disagree about what
        // counts as an address. Anything secret-shaped is refused here too, which
        // means even a hash that somehow carried an "@" could not be mistaken for
        // a mailbox and printed.
        $isEmail = static function ($value): bool {
            return is_string($value)
                && $value !== ''
                && str_contains($value, '@')
                && ! user_value_is_secret($value);
        };

        $candidate = method_exists($user, 'getEmail') ? $user->getEmail() : null;
        if ($isEmail($candidate)) {
            return trim((string) $candidate);
        }

        try {
            $identity = $user->getEmailIdentity();
            if ($identity) {
                if ($isEmail($identity->name ?? null)) {
                    return trim((string) $identity->name);
                }
                if ($isEmail($identity->secret ?? null)) {
                    return trim((string) $identity->secret);
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return '';
    }
}

if (! function_exists('user_value_is_secret')) {
    /**
     * Whether a string is shaped like a stored credential rather than a name.
     *
     * The single place that decides this, because it is used on both the display
     * path and the email path and the two must never disagree: a value accepted
     * as an email here would be printed into the greeting a few lines later.
     *
     * Three independent checks, because a prefix test alone is not enough:
     *   1. the known algorithm prefixes ($2y$/$argon...), which catches the
     *      legacy rows where the identity secret held a bcrypt string;
     *   2. password_get_info(), which asks PHP itself "is this a recognised
     *      hash?" and so also covers algorithms this app has never used;
     *   3. a length floor, because no human name, username or mailbox is 40+
     *      characters of unbroken base64 and every supported hash is.
     *
     * @param mixed $value
     */
    function user_value_is_secret($value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);

        if ($value === '') {
            return false;
        }

        // (1) Known prefixes. Cheap, and it names the offending format in a
        // debugger rather than leaving a bare false.
        if (str_starts_with($value, '$2') || str_starts_with($value, '$argon')) {
            return true;
        }

        // (2) Let PHP decide. Returns algo 0 (null) for anything it does not
        // recognise as a hash, so an ordinary name passes straight through.
        if ((password_get_info($value)['algo'] ?? 0) !== 0) {
            return true;
        }

        // (3) Length floor. A guard rather than a rule: this is the backstop for
        // a hash format PHP does not know, and it cannot reject a real name.
        //
        // Exempt anything containing an "@". A mailbox has no spaces and can
        // legally run to 254 characters, so without this exemption a long but
        // perfectly valid address (a long custom domain, a long local part) would
        // be thrown away and the greeting would lose a name it could have used.
        if (str_contains($value, '@')) {
            return false;
        }

        return strlen($value) >= 40 && ! str_contains($value, ' ');
    }
}

if (! function_exists('user_display_name')) {
    /**
     * A human-readable name for a signed-in user, safe to put on the page.
     *
     * NEVER use $user->email for this. Shield's User entity resolves ->email to
     * the identity secret, and legacy rows of this app stored the password hash
     * in that column, so it prints a bcrypt string into the page source on every
     * logged-in request. AuditLogger::describeActor() documents the same hazard
     * for the audit trail; this is the same guard for the UI.
     *
     * Precedence, cheapest and most reliable first:
     *   1. users.first_name + users.last_name
     *   2. users.username
     *   3. user_login_email(), which already rejects anything secret-shaped
     *   4. the caller's fallback
     *
     * Every candidate is checked by shape rather than by which column it came
     * from, so even a mis-mapped column cannot leak: a value that looks like a
     * stored secret is discarded and resolution continues to the next step.
     *
     * Returns '' when nothing usable exists, so a caller can render a bare
     * greeting rather than an empty one.
     */
    function user_display_name($user, string $fallback = ''): string
    {
        if ($user === null) {
            return $fallback;
        }

        $clean = static function ($value): string {
            if (! is_string($value)) {
                return '';
            }

            $value = trim($value);

            if ($value === '') {
                return '';
            }

            // A password hash is never a name. Rejected on shape, not on origin,
            // so the wrong column cannot get past this.
            return user_value_is_secret($value) ? '' : $value;
        };

        $first = $clean($user->first_name ?? null);
        $last  = $clean($user->last_name ?? null);
        if ($first !== '' || $last !== '') {
            return trim($first . ' ' . $last);
        }

        $username = $clean($user->username ?? null);
        if ($username !== '') {
            return $username;
        }

        $email = $clean(user_login_email($user));
        if ($email !== '') {
            return $email;
        }

        return $fallback;
    }
}

if (! function_exists('user_role_label')) {
    /**
     * A role label for a signed-in user, used as the greeting's last resort.
     *
     * Exists so a user row with no usable name still gets something meaningful
     * ("Teacher", "Admin") instead of a bare "Welcome" with nothing after it. Also
     * shape-guarded, because it is built from the same entity.
     */
    function user_role_label($user): string
    {
        if ($user === null || ! is_object($user) || ! method_exists($user, 'inGroup')) {
            return '';
        }

        try {
            foreach (['admin_staff', 'admin', 'teacher', 'student', 'parent'] as $group) {
                if ($user->inGroup($group)) {
                    return ucfirst(str_replace('_', ' ', $group));
                }
            }
        } catch (\Throwable $e) {
            // A group lookup must never break the page it is decorating.
        }

        return '';
    }
}

if (! function_exists('ensure_user_in_group')) {
    function ensure_user_in_group(int $userId, string $group): bool
    {
        if ($userId <= 0 || $group === '') {
            return false;
        }

        $db = \Config\Database::connect();

        $exists = $db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', $group)
            ->countAllResults();

        if ($exists > 0) {
            return true;
        }

        return $db->table('auth_groups_users')->insert([
            'user_id'    => $userId,
            'group'      => $group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
