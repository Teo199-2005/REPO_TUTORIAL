<?php

declare(strict_types=1);

/**
 * Middle name policy shared by the student registration form and the admin
 * student edit pages.
 *
 * Applicants and administrators tick "No middle name" when the student does
 * not have one; the middle name field is then cleared and left out of
 * validation entirely. When a middle name IS supplied it must be at least two
 * characters, so a lone letter (e.g. "J") is rejected.
 */
if (! function_exists('NO_MIDDLE_NAME_POST_KEY')) {
    define('NO_MIDDLE_NAME_POST_KEY', 'no_middle_name');
}

if (! function_exists('no_middle_name_rule')) {
    /**
     * The in_list value the checkbox posts, reused by the view and controller.
     */
    function no_middle_name_rule(): string
    {
        return '1';
    }
}

if (! function_exists('student_has_no_middle_name')) {
    /**
     * Whether the submitted/old form data ticks the "No middle name" box.
     */
    function student_has_no_middle_name(): bool
    {
        return (string) service('request')->getPost(NO_MIDDLE_NAME_POST_KEY) === no_middle_name_rule();
    }
}

if (! function_exists('middle_name_validation_rule')) {
    /**
     * The validation rule for a student's middle name.
     *
     * With the box ticked there is nothing to validate (the field is disabled
     * and never submitted). Otherwise the middle name is required and must be
     * at least two characters long.
     */
    function middle_name_validation_rule(): string
    {
        if (student_has_no_middle_name()) {
            return 'permit_empty|max_length[100]';
        }

        return 'required|min_length[2]|max_length[100]';
    }
}

if (! function_exists('normalize_middle_name')) {
    /**
     * The value to persist for the middle name.
     *
     * Returns null when the box is ticked, so a stale value typed before the
     * box was checked can never survive, and null when the field is blank.
     */
    function normalize_middle_name(): ?string
    {
        if (student_has_no_middle_name()) {
            return null;
        }

        $value = trim((string) service('request')->getPost('middle_name'));

        return $value === '' ? null : $value;
    }
}

if (! function_exists('nationality_options')) {
    /**
     * @return list<string>
     */
    function nationality_options(): array
    {
        return [
            'Filipino',
            'American',
            'Australian',
            'British',
            'Canadian',
            'Chinese',
            'Indian',
            'Indonesian',
            'Japanese',
            'Korean',
            'Malaysian',
            'Singaporean',
            'Thai',
            'Vietnamese',
            'Other',
        ];
    }
}

if (! function_exists('religion_options')) {
    /**
     * The ten most populated religious affiliations in the Philippines, ranked
     * by the PSA 2020 Census of Population and Housing (household population).
     *
     * Share of the total population is shown for reference; applicants whose
     * affiliation is not listed pick "Other" and type it in the companion box.
     *
     * @return list<string>
     */
    function religion_options(): array
    {
        return [
            'Roman Catholic',               // 78.8 %
            'Islam',                        //  6.4 %
            'Evangelical / Born Again',     //  4.8 %
            'Protestant',                   //  2.8 %
            'Iglesia ni Cristo',            //  2.6 %
            'Seventh-day Adventist',        //  0.8 %
            "Jehovah's Witnesses",          //  0.4 %
            'Church of Christ',             //  0.4 %
            'Jesus is Lord Church (JILCW)', //  0.3 %
            'Indigenous / Tribal Religion', //  0.2 %
        ];
    }
}

if (! function_exists('religion_other_option')) {
    /**
     * Value of the "Other" choice that reveals the free-text companion field.
     */
    function religion_other_option(): string
    {
        return 'Other';
    }
if (! function_exists('nationality_other_option')) {
    /**
     * Value of the "Other" choice in the nationality list.
     *
     * nationality_options() has always ended with "Other", but until now nothing
     * was waiting behind it: picking it stored the literal word "Other" as the
     * student's nationality and offered no box to type the real one. The option
     * was therefore a dead end rather than an escape hatch.
     */
    function nationality_other_option(): string
    {
        return 'Other';
    }
}

if (! function_exists('nationality_is_other')) {
    /**
     * True when a submitted nationality value is the "Other" choice.
     *
     * @param mixed $value
     */
    function nationality_is_other($value): bool
    {
        return is_string($value)
            && strcasecmp(trim($value), nationality_other_option()) === 0;
    }
}

if (! function_exists('other_choice_normalize_fields')) {
    /**
     * Resolve a posted "<field> + <field>_other" pair before validation runs.
     *
     * One implementation shared by every selector that offers an "Other" escape
     * hatch, so nationality does not become a third hand-copied version of the
     * religion rules that then has to be kept in step with them by hand.
     *
     * @param array<string, mixed> $data
     * @param string               $field     the selector's own field name
     * @param string               $companion the free-text box revealed by "Other"
     *
     * @return array<string, mixed>
     */
    function other_choice_normalize_fields(
        array $data,
        string $field,
        string $companion,
        string $otherValue = 'Other'
    ): array {
        if (! is_string($data[$field] ?? null)
            || strcasecmp(trim($data[$field]), $otherValue) !== 0
        ) {
            return $data;
        }

        $custom = $data[$companion] ?? '';

        // A non-string payload (e.g. nationality_other[]) resolves to an empty
        // value, which the shared `required` rule then rejects with a message
        // that points at the box rather than the dropdown.
        $data[$field] = is_string($custom) ? trim($custom) : '';

        return $data;
    }
}

if (! function_exists('other_choice_normalize_request')) {
    /**
     * Rewrite an "Other" choice in every bag of a request, so validation, the
     * database write and withInput()/old() all see the resolved value.
     *
     * @param object|null $request
     */
    function other_choice_normalize_request($request, string $field, string $companion): void
    {
        $normalise = static fn (array $data): array
            => other_choice_normalize_fields($data, $field, $companion);

        if (is_array($_POST)) {
            $_POST = $normalise($_POST);
        }
        if (is_array($_REQUEST)) {
            $_REQUEST = $normalise($_REQUEST);
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
            $request->setGlobal($bag, $normalise($data));
        }
    }
}

if (! function_exists('nationality_normalize_request')) {
    /**
     * Swap the nationality "Other" choice for its free-text companion before
     * validation, so the literal word "Other" is never stored.
     *
     * @param object|null $request
     */
    function nationality_normalize_request($request): void
    {
        other_choice_normalize_request($request, 'nationality', 'nationality_other');
    }
}

if (! function_exists('nationality_validation_messages')) {
    /**
     * Messages for nationality, worded like the religion ones so a blank "Other"
     * box explains itself instead of complaining about the dropdown.
     *
     * @return array<string, array<string, string>>
     */
    function nationality_validation_messages(int $maxLength = 100): array
    {
        return [
            'nationality' => [
                'required'   => 'Please select your nationality. If it is not listed, choose "Other" and type it in the box provided.',
                'max_length' => "Nationality must not be longer than {$maxLength} characters.",
            ],
        ];
    }
}


}

if (! function_exists('religion_is_other')) {
    /**
     * True when a submitted religion value is the "Other" choice.
     *
     * @param mixed $value
     */
    function religion_is_other($value): bool
    {
        return is_string($value) && strcasecmp(trim($value), religion_other_option()) === 0;
    }
}

if (! function_exists('religion_normalize_fields')) {
    /**
     * Resolve a posted religion value BEFORE validation runs.
     *
     * The registration form pairs the religion selector with an optional
     * free-text box (`religion_other`). Choosing "Other" swaps the literal
     * choice for whatever the applicant typed - an empty string when the box
     * was left blank, so the shared `required` rule rejects it instead of
     * storing the meaningless word "Other".
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    function religion_normalize_fields(array $data): array
    {
        // Shared with nationality so the two cannot drift apart.
        return other_choice_normalize_fields($data, 'religion', 'religion_other', religion_other_option());
    }
}

if (! function_exists('religion_normalize_request')) {
    /**
     * Rewrite the religion value in every bag of a request (see
     * phone_normalize_request() for why all of them are touched) so validation,
     * the database write and withInput()/old() all see the resolved value.
     *
     * @param object|null $request
     */
    function religion_normalize_request($request): void
    {
        other_choice_normalize_request($request, 'religion', 'religion_other');
    }
}

if (! function_exists('religion_validation_messages')) {
    /**
     * Messages for the religion field so a blank "Other" box explains itself
     * instead of complaining about the whole field.
     *
     * @param int $maxLength Maximum characters, so forms with a shorter column
     *                       (teachers: varchar(50)) can reuse the same wording.
     *
     * @return array<string, array<string, string>>
     */
    function religion_validation_messages(int $maxLength = 100): array
    {
        return [
            'religion' => [
                'required'   => 'Please select your religion. If it is not listed, choose "Other" and type it in the box provided.',
                'max_length' => "Religion must not be longer than {$maxLength} characters.",
            ],
        ];
    }
}

if (! function_exists('emergency_contact_relationship_other_option')) {
    /**
     * Value of the "Other" choice that reveals the free-text companion field.
     */
    function emergency_contact_relationship_other_option(): string
    {
        return 'Other';
    }
}

if (! function_exists('emergency_contact_relationship_is_other')) {
    /**
     * True when a posted relationship value is the "Other" choice.
     *
     * @param mixed $value
     */
    function emergency_contact_relationship_is_other($value): bool
    {
        return is_string($value)
            && strcasecmp(trim($value), emergency_contact_relationship_other_option()) === 0;
    }
}

if (! function_exists('emergency_contact_relationship_normalize_fields')) {
    /**
     * Resolve a posted relationship BEFORE validation runs.
     *
     * Same contract as religion_normalize_fields(): picking "Other" swaps the
     * literal choice for whatever was typed in the companion box, so the
     * database never stores the meaningless word "Other". An empty box yields
     * an empty string, which the shared `required` rule then rejects.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    function emergency_contact_relationship_normalize_fields(array $data): array
    {
        if (! emergency_contact_relationship_is_other($data['emergency_contact_relationship'] ?? null)) {
            return $data;
        }

        $custom = $data['emergency_contact_relationship_other'] ?? '';

        // A non-string payload resolves to empty, which `required` rejects.
        $data['emergency_contact_relationship'] = is_string($custom) ? trim($custom) : '';

        return $data;
    }
}

if (! function_exists('emergency_contact_relationship_normalize_request')) {
    /**
     * Rewrite the relationship in every bag of a request (see
     * religion_normalize_request() for the rationale) so validation, the
     * database write and withInput()/old() all see the resolved value.
     *
     * @param object|null $request
     */
    function emergency_contact_relationship_normalize_request($request): void
    {
        if (is_array($_POST)) {
            $_POST = emergency_contact_relationship_normalize_fields($_POST);
        }
        if (is_array($_REQUEST)) {
            $_REQUEST = emergency_contact_relationship_normalize_fields($_REQUEST);
        }

        if (! is_object($request)
            || ! method_exists($request, 'fetchGlobal')
            || ! method_exists($request, 'setGlobal')
        ) {
            return;
        }

        $globals = $request->fetchGlobal('data') ?? [];

        if (is_array($globals)) {
            $request->setGlobal('data', emergency_contact_relationship_normalize_fields($globals));
        }
    }
}

if (! function_exists('emergency_contact_relationship_options')) {
    /**
     * @return list<string>
     */
    function emergency_contact_relationship_options(): array
    {
        return [
            'Father',
            'Mother',
            'Guardian',
            'Grandfather',
            'Grandmother',
            'Uncle',
            'Aunt',
            'Stepfather',
            'Stepmother',
            'Sibling',
            'Other',
        ];
    }
}

if (! function_exists('emergency_contact_relationship_rule')) {
    /**
     * Validation rule for the emergency contact relationship.
     *
     * Deliberately NOT an in_list rule: choosing "Other" resolves the value to
     * free text before validation (see the normalizer), so a custom
     * relationship must be allowed through. Mirrors the religion rule.
     */
    function emergency_contact_relationship_rule(): string
    {
        return 'required|max_length[50]';
    }
}

if (! function_exists('emergency_contact_relationship_validation_messages')) {
    /**
     * Messages for the relationship field so a blank "Other" box explains itself
     * instead of complaining about the whole field.
     *
     * @return array<string, array<string, string>>
     */
    function emergency_contact_relationship_validation_messages(): array
    {
        return [
            'emergency_contact_relationship' => [
                'required'   => 'Please select a relationship. If it is not listed, choose "Other" and type it in the box provided.',
                'max_length' => 'Relationship must not be longer than 50 characters.',
            ],
        ];
    }
}
