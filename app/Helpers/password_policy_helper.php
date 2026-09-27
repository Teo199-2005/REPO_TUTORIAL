<?php

declare(strict_types=1);

if (! function_exists('password_policy')) {
    /**
     * The school-wide password policy, in one place.
     *
     * Every form that creates or sets a password (student/teacher registration,
     * admin enrol & account creation, change-password and every reset flow)
     * must use these values, so the rule shown to the user and the rule enforced
     * on the server can never drift apart.
     *
     * @return array{min_length:int, min_digits:int}
     */
    function password_policy(): array
    {
        return [
            'min_length' => 8,
            'min_digits' => 1,
        ];
    }
}

if (! function_exists('password_policy_min_length')) {
    /**
     * Minimum number of characters a password must contain.
     */
    function password_policy_min_length(): int
    {
        return password_policy()['min_length'];
    }
}

if (! function_exists('password_policy_min_digits')) {
    /**
     * Minimum number of digits a password must contain.
     */
    function password_policy_min_digits(): int
    {
        return password_policy()['min_digits'];
    }
}

if (! function_exists('password_policy_regex')) {
    /**
     * Regex fragment enforcing "at least N digits", for the server rule.
     *
     * A lookahead so the digit requirement is checked independently of the
     * minimum length.
     */
    function password_policy_regex(): string
    {
        return '(?=(?:.*\\d){' . password_policy_min_digits() . ',})';
    }
}

if (! function_exists('password_validation_rule')) {
    /**
     * The single server-side validation rule for a new password.
     *
     * @param string $field Field name the rule applies to
     */
    function password_validation_rule(string $field = 'password'): string
    {
        return 'required|min_length[' . password_policy_min_length() . ']'
            . '|regex_match[/^' . password_policy_regex() . '.{1,}$/]';
    }
}

if (! function_exists('password_validation_messages')) {
    /**
     * Consistent messages for the shared rule, so every form explains the
     * requirement the same way instead of only saying "minimum 8 characters".
     *
     * @return array<string, array<string, string>>
     */
    function password_validation_messages(string $field = 'password'): array
    {
        return [
            $field => [
                'required'    => 'Please enter a password.',
                'min_length'  => 'Password must be at least ' . password_policy_min_length() . ' characters long.',
                'regex_match' => 'Password must be at least ' . password_policy_min_length()
                    . ' characters long and include at least ' . password_policy_min_digits() . ' digit'
                    . (password_policy_min_digits() === 1 ? '.' : 's.'),
            ],
        ];
    }
}

if (! function_exists('password_policy_requirements')) {
    /**
     * The requirement list rendered under every password field, so the wording
     * is identical on every screen.
     *
     * @return list<array{icon:string, label:string}>
     */
    function password_policy_requirements(): array
    {
        return [
            [
                'icon'  => 'bi-check-circle-fill',
                'label' => 'At least ' . password_policy_min_length() . ' characters',
            ],
            [
                'icon'  => 'bi-check-circle-fill',
                'label' => 'At least ' . password_policy_min_digits() . ' digit'
                    . (password_policy_min_digits() === 1 ? '' : 's'),
            ],
        ];
    }
}

if (! function_exists('password_policy_summary')) {
    /**
     * One-line summary for placeholders and tight layouts.
     */
    function password_policy_summary(): string
    {
        return 'At least ' . password_policy_min_length() . ' characters, including at least '
            . password_policy_min_digits() . ' digit'
            . (password_policy_min_digits() === 1 ? '' : 's');
    }
}

if (! function_exists('password_meets_policy')) {
    /**
     * Server-side equivalent of the client rule, for code paths that validate a
     * password by hand instead of through $this->validate().
     *
     * @return string|null An error message, or null when the password is valid
     */
    function password_meets_policy(?string $password): ?string
    {
        $password = (string) $password;

        if ($password === '') {
            return 'Please enter a password.';
        }

        if (mb_strlen($password) < password_policy_min_length()) {
            return 'Password must be at least ' . password_policy_min_length() . ' characters long.';
        }

        if (preg_match_all('/\d/', $password) < password_policy_min_digits()) {
            return 'Password must include at least ' . password_policy_min_digits() . ' digit'
                . (password_policy_min_digits() === 1 ? '.' : 's.');
        }

        return null;
    }
}
