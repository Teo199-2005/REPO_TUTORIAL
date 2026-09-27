<?php

/**
 * Philippine mobile number helpers.
 *
 * Every contact number in the app is stored as plain 09XXXXXXXXX (11 digits):
 * the 09 prefix is implied, only 9 further digits are accepted, and the same
 * rules are enforced in the browser by public/js/phone-input.js.
 */

if (! function_exists('phone_normalize')) {
    /**
     * Normalise a stored or submitted mobile number to 09XXXXXXXXX.
     *
     * null and '' pass straight through, so nullable columns and the existing
     * "empty string" write behaviour stay exactly as they were.
     *
     *  +639171234567 / 639171234567 -> 09171234567
     *  9171234567                   -> 09171234567
     *  0917-123-4567 / 0917 123 4567-> 09171234567
     *  0917 1234                    -> 09171234  (incomplete, still validated)
     *
     * @param mixed $value
     *
     * @return string|null
     */
    function phone_normalize($value)
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return '';
        }

        // Country code, then a leading 9 typed without its 0.
        if (strpos($digits, '63') === 0 && strlen($digits) >= 12) {
            $digits = '0' . substr($digits, 2);
        }
        if ($digits[0] === '9') {
            $digits = '0' . $digits;
        }

        // Whatever is left is the subscriber part; the value always starts 09.
        if (strpos($digits, '09') === 0) {
            $rest = substr($digits, 2);
        } elseif ($digits[0] === '0') {
            $rest = substr($digits, 1);
        } else {
            $rest = $digits;
        }

        return '09' . substr($rest, 0, 9);
    }
}

if (! function_exists('phone_is_valid')) {
    /**
     * True when the value is a complete 09 + 9 digits mobile number.
     * Blank values are not valid; pair this with permit_empty where optional.
     *
     * @param mixed $value
     */
    function phone_is_valid($value): bool
    {
        return preg_match('/^09\d{9}$/', (string) phone_normalize($value)) === 1;
    }
}

if (! function_exists('phone_normalize_fields')) {
    /**
     * Return a copy of a request payload with every phone field normalised to
     * 09XXXXXXXXX, so validation rules can require /^09\d{9}$/ even for legacy
     * values that still contain dashes, spaces or a +63 country code.
     *
     * @param array<string, mixed> $data
     * @param list<string>         $fields
     *
     * @return array<string, mixed>
     */
    function phone_normalize_fields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = phone_normalize($data[$field]);
            }
        }

        return $data;
    }
}

if (! function_exists('phone_validation_rule')) {
    /**
     * Shared validation rule string for optional 09XXXXXXXXX fields.
     */
    function phone_validation_rule(): string
    {
        return 'permit_empty|max_length[11]|regex_match[/^09\d{9}$/]';
    }
}

if (! function_exists('phone_required_validation_rule')) {
    /**
     * Shared validation rule string for mandatory 09XXXXXXXXX fields.
     */
    function phone_required_validation_rule(): string
    {
        return 'required|max_length[11]|regex_match[/^09\d{9}$/]';
    }
}

if (! function_exists('phone_normalize_request')) {
    /**
     * Normalise every phone field of a request BEFORE validation runs.
     *
     * CodeIgniter caches each superglobal inside the request on first read:
     * Controller::validate() pulls its data through getVar() (the "request"
     * bag) while writes read getPost() (the "post" bag), and
     * redirect()->withInput() re-displays the raw $_POST. Rewrite all four so
     * a single normalised 09XXXXXXXXX value flows through validation, the
     * database, and any repopulated form.
     *
     * Accepts any object exposing fetchGlobal()/setGlobal() (typed loosely so
     * this helper stays testable outside a full request cycle).
     *
     * @param object|null         $request
     * @param list<string>        $fields  Request keys holding phone numbers
     */
    function phone_normalize_request($request, array $fields): void
    {
        // Keep the raw superglobals in sync for withInput()/old() repopulating.
        // ($_POST/$_REQUEST are superglobals: always in scope, no `global` needed.)
        if (is_array($_POST)) {
            $_POST = phone_normalize_fields($_POST, $fields);
        }
        if (is_array($_REQUEST)) {
            $_REQUEST = phone_normalize_fields($_REQUEST, $fields);
        }

        if (! is_object($request)
            || ! method_exists($request, 'fetchGlobal')
            || ! method_exists($request, 'setGlobal')
        ) {
            return;
        }

        foreach (['post', 'request'] as $bag) {
            $data = $request->fetchGlobal($bag);
            if (! is_array($data)) {
                continue;
            }
            $request->setGlobal($bag, phone_normalize_fields($data, $fields));
        }
    }
}

if (! function_exists('phone_validation_messages')) {
    /**
     * Human-readable messages for the shared phone rules so a rejected number
     * explains the 09 + 9 digits format instead of the generic "not in the
     * correct format".
     *
     * @param array<string, string> $fields field name => human label
     *
     * @return array<string, array<string, string>>
     */
    function phone_validation_messages(array $fields): array
    {
        $messages = [];

        foreach ($fields as $field => $label) {
            $message = "The {$label} must be exactly 11 digits and start with 09 (e.g., 09171234567).";

            $messages[$field] = [
                'required'    => $message,
                'max_length'  => $message,
                'regex_match' => $message,
            ];
        }

        return $messages;
    }
}
