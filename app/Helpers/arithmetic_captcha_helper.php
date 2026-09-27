<?php

/**
 * Arithmetic CAPTCHA helper.
 *
 * A lightweight, dependency-free CAPTCHA for the login form: the visitor is
 * asked to solve "8 + 5 = ?" and the expected answer is kept server-side in the
 * session. Only the question is ever handed to the browser.
 *
 * Security model
 * --------------
 *  - The answer is written to the SESSION ONLY. It is never rendered into HTML,
 *    JavaScript, a hidden input, a cookie readable by scripts, or an API
 *    response. The public functions return the question string alone.
 *  - `arithmetic_captcha_check()` REMOVES the answer from the session before it
 *    returns (pop-on-read), so a challenge is strictly one-time: replaying an
 *    old submission, double-clicking "ACCESS SYSTEM", or having the page open
 *    in two tabs cannot reuse a solved answer.
 *  - Every page load calls `arithmetic_captcha_generate()` again, and a failed
 *    or successful login attempt redirects back to `login()`, which regenerates
 *    the challenge. Combined with the pop-on-read this means each attempt faces
 *    a fresh question.
 *  - Fail closed: a missing, non-numeric, or expired (TTL) challenge is
 *    reported as INCORRECT rather than skipped, so a client can never talk its
 *    way past the check by omitting or mangling the field.
 *
 * The client-side attributes on the input (`inputmode`, `pattern`) are UX only.
 * The server-side comparison below is the actual security control.
 */

if (! defined('ARITHMETIC_CAPTCHA_MIN_OPERAND')) {
    /** Smallest operand that may appear in a generated question. */
    define('ARITHMETIC_CAPTCHA_MIN_OPERAND', 1);
}

if (! defined('ARITHMETIC_CAPTCHA_MAX_OPERAND')) {
    /** Largest operand that may appear in a generated question. */
    define('ARITHMETIC_CAPTCHA_MAX_OPERAND', 20);
}

if (! defined('ARITHMETIC_CAPTCHA_TTL')) {
    /**
     * Lifetime of a challenge in seconds. A tab left open for hours can no
     * longer be submitted against a stale question; `login()` re-issues one.
     */
    define('ARITHMETIC_CAPTCHA_TTL', 300);
}

if (! function_exists('arithmetic_captcha_answer_key')) {
    /**
     * Session key holding the expected answer.
     */
    function arithmetic_captcha_answer_key(): string
    {
        return 'arithmetic_captcha_answer';
    }
}

if (! function_exists('arithmetic_captcha_question_key')) {
    /**
     * Session key holding the question text to re-render on the next load.
     */
    function arithmetic_captcha_question_key(): string
    {
        return 'arithmetic_captcha_question';
    }
}

if (! function_exists('arithmetic_captcha_issued_key')) {
    /**
     * Session key holding the challenge creation timestamp.
     */
    function arithmetic_captcha_issued_key(): string
    {
        return 'arithmetic_captcha_issued_at';
    }
}

if (! function_exists('arithmetic_captcha_random_operand')) {
    /**
     * Random integer in the configured operand range (inclusive).
     */
    function arithmetic_captcha_random_operand(): int
    {
        return random_int(ARITHMETIC_CAPTCHA_MIN_OPERAND, ARITHMETIC_CAPTCHA_MAX_OPERAND);
    }
}

if (! function_exists('arithmetic_captcha_generate')) {
    /**
     * Create a new arithmetic challenge and store the expected answer in the
     * session. Called on every login page load and by the AJAX refresh
     * endpoint, which guarantees a fresh question for every render.
     *
     * Operators are limited to addition and subtraction, operands stay within
     * 1-20, and subtraction is generated as (larger - smaller) so the result is
     * never negative.
     *
     * @return string The question to display, e.g. "12 + 9 = ?"
     */
    function arithmetic_captcha_generate(): string
    {
        $first  = arithmetic_captcha_random_operand();
        $second = arithmetic_captcha_random_operand();

        if (random_int(0, 1) === 1) {
            $operator = '+';
            $answer   = $first + $second;
        } else {
            $operator = '-';

            // Swap when needed so the subtraction can never go negative.
            if ($second > $first) {
                [$first, $second] = [$second, $first];
            }

            $answer = $first - $second;
        }

        $question = $first . ' ' . $operator . ' ' . $second . ' = ?';

        // Session-only storage. These keys are never sent to the client.
        session()->set(arithmetic_captcha_answer_key(), $answer);
        session()->set(arithmetic_captcha_question_key(), $question);
        session()->set(arithmetic_captcha_issued_key(), time());

        // Only the question is returned - never the answer.
        return $question;
    }
}

if (! function_exists('arithmetic_captcha_question')) {
    /**
     * The question currently held in the session, or null when there is none.
     * Safe to render; contains no answer material.
     */
    function arithmetic_captcha_question(): ?string
    {
        $question = session()->get(arithmetic_captcha_question_key());

        return is_string($question) && $question !== '' ? $question : null;
    }
}

if (! function_exists('arithmetic_captcha_forget')) {
    /**
     * Drop any stored challenge. Called by check() on every validation and
     * available for callers that need to clear state explicitly.
     */
    function arithmetic_captcha_forget(): void
    {
        session()->remove([
            arithmetic_captcha_answer_key(),
            arithmetic_captcha_question_key(),
            arithmetic_captcha_issued_key(),
        ]);
    }
}


if (! function_exists('arithmetic_captcha_check')) {
    /**
     * Validate a submitted answer against the session challenge.
     *
     * The stored answer is removed from the session UNCONDITIONALLY before the
     * comparison result is known, so every attempt burns the challenge whether
     * it is right or wrong. That is what prevents reuse.
     *
     * @param mixed $answer Raw POST value.
     *
     * @return bool True only when a live challenge exists and matches.
     */
    function arithmetic_captcha_check($answer): bool
    {
        $session  = session();
        $expected = $session->get(arithmetic_captcha_answer_key());
        $issuedAt = $session->get(arithmetic_captcha_issued_key());

        // Pop-on-read: the challenge is single-use regardless of the outcome.
        arithmetic_captcha_forget();

        if (! is_int($expected) || ! is_numeric($issuedAt)) {
            // No usable challenge (never issued, already consumed, or reset).
            return false;
        }

        if ((time() - (int) $issuedAt) > ARITHMETIC_CAPTCHA_TTL) {
            // Stale challenge - fail closed rather than accept it.
            return false;
        }

        // Normalise both sides to integers. Casting rather than using loose
        // comparison avoids "05"/"5" type-juggling surprises.
        $submitted = is_string($answer) ? trim($answer) : $answer;

        if (! is_numeric($submitted)) {
            return false;
        }

        return (int) $submitted === $expected;
    }
}

if (! function_exists('arithmetic_captcha_rule')) {
    /**
     * Validation rules for the CAPTCHA POST field. Merge into a controller's
     * rules so a missing or malformed answer is caught by validate().
     *
     * @return array<string, string>
     */
    function arithmetic_captcha_rule(): array
    {
        return ['captcha_answer' => 'required|numeric'];
    }
}

if (! function_exists('arithmetic_captcha_validation_messages')) {
    /**
     * Human-readable messages for the CAPTCHA field.
     *
     * @return array<string, string>
     */
    function arithmetic_captcha_validation_messages(): array
    {
        return [
            'captcha_answer' => [
                'required' => 'Please solve the arithmetic problem to continue.',
                'numeric'  => 'Please enter numbers only for the arithmetic problem.',
            ],
        ];
    }
}
