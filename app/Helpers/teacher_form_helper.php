<?php

declare(strict_types=1);

if (! function_exists('teacher_minimum_age')) {
    /**
     * The youngest age a person may register as a teacher.
     *
     * 18 is the DepEd floor for anyone holding a teaching post, and the public
     * form enforces it in the browser as well as on the server so an applicant is
     * told at the field rather than after a full submit.
     */
    function teacher_minimum_age(): int
    {
        return 18;
    }
}

if (! function_exists('teacher_oldest_age')) {
    /**
     * The oldest age the form accepts.
     *
     * A ceiling, not a policy: it exists only to reject a mistyped year (someone
     * born in 1923 entering 1923 into a 2026 form is a typo, not a hire), and it
     * is set far above any plausible retirement age so it can never reject a real
     * applicant.
     */
    function teacher_oldest_age(): int
    {
        return 100;
    }
}

if (! function_exists('teacher_dob_max')) {
    /** Latest acceptable date of birth, as Y-m-d. Nothing can be born tomorrow. */
    function teacher_dob_max(): string
    {
        return date('Y-m-d');
    }
}

if (! function_exists('teacher_dob_min')) {
    /**
     * Earliest acceptable date of birth, as Y-m-d.
     *
     * Derived from teacher_oldest_age() rather than hard-coded, so raising or
     * lowering the ceiling cannot leave the two disagreeing.
     */
    function teacher_dob_min(): string
    {
        return date('Y-m-d', strtotime('-' . teacher_oldest_age() . ' years'));
    }
}

if (! function_exists('teacher_dob_validation_rule')) {
    /**
     * Validation rule for the teacher's date of birth.
     *
     * The old rule was bare `valid_date`, which accepts a future date and a date
     * from a century ago. Both are rejected now. The minimum-age floor is the
     * substantive rule; teacher_dob_min() is the same bound expressed for the
     * browser's `min` attribute, so the two are derived from one age constant
     * and cannot drift.
     */
    function teacher_dob_validation_rule(): string
    {
        return 'required|valid_date|less_than_equal_to[' . teacher_dob_max() . ']|greater_than_equal_to[' . teacher_dob_min() . ']';
    }
}

if (! function_exists('teacher_dob_validation_messages')) {
    /**
     * @return array<string, array<string, string>>
     */
    function teacher_dob_validation_messages(): array
    {
        return [
            'date_of_birth' => [
                'required'              => 'Please enter your date of birth.',
                'valid_date'            => 'Please enter a valid date of birth.',
                'less_than_equal_to'    => 'The date of birth cannot be in the future.',
                'greater_than_equal_to' => 'Please check the year. The date of birth entered is more than ' . teacher_oldest_age() . ' years ago.',
            ],
        ];
    }
}

if (! function_exists('teacher_age_from')) {
    /**
     * The whole-years-old age for a Y-m-d birth date, or null when the date is
     * unusable or in the future.
     *
     * Server-side twin of the browser's age calculation, so the modal the applicant
     * sees and the rule that finally rejects them agree on the number.
     */
    function teacher_age_from(?string $dateOfBirth): ?int
    {
        if ($dateOfBirth === null || trim($dateOfBirth) === '') {
            return null;
        }

        try {
            $birth = new DateTime($dateOfBirth);
        } catch (\Throwable $e) {
            return null;
        }

        $now = new DateTime('today');

        if ($birth > $now) {
            return null;
        }

        return (int) $birth->diff($now)->y;
    }
}

if (! function_exists('teacher_is_underage')) {
    /**
     * True when the date of birth puts the applicant under teacher_minimum_age().
     */
    function teacher_is_underage(?string $dateOfBirth): bool
    {
        $age = teacher_age_from($dateOfBirth);

        return $age !== null && $age < teacher_minimum_age();
    }
}

if (! function_exists('teacher_other_option')) {
    /**
     * The "Other" choice that reveals a free-text companion box.
     *
     * Reused by every teacher selector that offers the escape hatch, so the value
     * posted by the dropdown, the value the normaliser looks for and the label the
     * applicant reads are all guaranteed to be the same string.
     */
    function teacher_other_option(): string
    {
        return 'Other';
    }
}

if (! function_exists('teacher_other_choices')) {
    /**
     * The list of teacher dropdowns paired with a free-text "Other" box.
     *
     * Each entry is [dropdown field, companion field, label for the box]. This is
     * the single source of truth: the view renders the selects and boxes from it,
     * the controller normalises every pair from it, and a test asserts that each
     * one has both halves. That is what stops a selector from shipping with an
     * "Other" entry and no box behind it - the exact dead end the student form
     * had with Nationality.
     *
     * Religion is deliberately absent: it has its own helpers and its own column
     * width (varchar(50)), and it is handled by religion_normalize_request().
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    function teacher_other_choices(): array
    {
        return [
            'position'    => ['position', 'position_other', 'Specify Position'],
            'subjects'    => ['subjects', 'subjects_other', 'Specify Teaching Area'],
            'designation' => ['designation', 'designation_other', 'Specify Designation'],
            'civil_status' => ['civil_status', 'civil_status_other', 'Specify Civil Status'],
        ];
    }
}

if (! function_exists('teacher_options_with_other')) {
    /**
     * A teacher option list with the "Other" escape hatch appended.
     *
     * Appended rather than hand-written into each list so the two can never drift:
     * an option list edited later still offers the escape hatch, and the appended
     * entry is never duplicated if it is already present.
     *
     * @param list<string> $options
     *
     * @return list<string>
     */
    function teacher_options_with_other(array $options): array
    {
        $other = teacher_other_option();

        if (in_array($other, $options, true)) {
            return $options;
        }

        $options[] = $other;

        return $options;
    }
}

if (! function_exists('teacher_other_choices_normalize_request')) {
    /**
     * Resolve every "Other" choice in the request before validation runs.
     *
     * Walks teacher_other_choices() so a selector cannot be added to the form
     * without its resolution rule following automatically. Without this, picking
     * "Other" would store the literal word "Other" as the teacher's position.
     *
     * @param object|null $request
     */
    function teacher_other_choices_normalize_request($request): void
    {
        foreach (teacher_other_choices() as [$field, $companion]) {
            other_choice_normalize_request($request, $field, $companion);
        }
    }
}

if (! function_exists('teacher_other_choices_validation_messages')) {
    /**
     * Messages for the selectors, worded so a blank "Other" box explains itself
     * instead of complaining about the dropdown.
     *
     * @return array<string, array<string, string>>
     */
    function teacher_other_choices_validation_messages(): array
    {
        $messages = [
            'position' => [
                'required'   => 'Please select a position. If it is not listed, choose "Other" and type it in the box provided.',
                'max_length' => 'Position must not be longer than 100 characters.',
            ],
            'subjects' => [
                'required'   => 'Please select a teaching area. If it is not listed, choose "Other" and type it in the box provided.',
                'max_length' => 'Teaching area must not be longer than 100 characters.',
            ],
            'designation' => [
                'required'   => 'Please select a designation. If it is not listed, choose "Other" and type it in the box provided.',
                'max_length' => 'Designation must not be longer than 100 characters.',
            ],
            'civil_status' => [
                'required'   => 'Please select a civil status. If it is not listed, choose "Other" and type it in the box provided.',
                'max_length' => 'Civil status must not be longer than 30 characters.',
            ],
        ];

        return $messages;
    }
}

if (! function_exists('teacher_month_options')) {
    /**
     * @return array<int, string>
     */
    function teacher_month_options(): array
    {
        return [
            1  => 'January',
            2  => 'February',
            3  => 'March',
            4  => 'April',
            5  => 'May',
            6  => 'June',
            7  => 'July',
            8  => 'August',
            9  => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }
}

if (! function_exists('teacher_fund_source_options')) {
    /**
     * @return list<string>
     */
    function teacher_fund_source_options(): array
    {
        return ['National', 'Local', 'Special Education Fund', 'Other'];
    }
}

if (! function_exists('teacher_position_options')) {
    /**
     * @return list<string>
     */
    function teacher_position_options(): array
    {
        return [
            'Teacher I',
            'Teacher II',
            'Teacher III',
            'Master Teacher I',
            'Master Teacher II',
            'Head Teacher I',
            'Head Teacher II',
            'Head Teacher III',
            'Head Teacher IV',
            'Head Teacher V',
            'Head Teacher VI',
            'Principal I',
            'Principal II',
            'Principal III',
            'Principal IV',
            'Assistant Principal',
            'Department Head',
            'Teacher',
        ];
    }
}

if (! function_exists('teacher_designation_options')) {
    /**
     * @return list<string>
     */
    function teacher_designation_options(): array
    {
        return [
            'Regular Permanent',
            'Regular Temporary',
            'Contractual',
            'Substitute',
            'Part-time',
            'Provisional',
        ];
    }
}

if (! function_exists('teacher_nature_of_appointment_options')) {
    /**
     * @return list<string>
     */
    function teacher_nature_of_appointment_options(): array
    {
        return [
            'Original',
            'Promotion',
            'Transfer',
            'Reappointment',
            'Reemployment',
            'Reinstatement',
        ];
    }
}

if (! function_exists('teacher_hiring_arrangement_options')) {
    /**
     * @return list<string>
     */
    function teacher_hiring_arrangement_options(): array
    {
        return ['Regular', 'Contractual', 'Part-time', 'Substitute', 'Provisional'];
    }
}

if (! function_exists('teacher_civil_status_options')) {
    /**
     * @return list<string>
     */
    function teacher_civil_status_options(): array
    {
        return ['Single', 'Married', 'Widowed', 'Separated', 'Annulled', 'Divorced'];
    }
}

if (! function_exists('teacher_eligibility_options')) {
    /**
     * @return list<string>
     */
    function teacher_eligibility_options(): array
    {
        return [
            'Licensure Examination for Teachers (LET)',
            'Philippine Board Examination for Teachers (PBET)',
            'Civil Service Professional',
            'Civil Service Subprofessional',
            'Bar/Board Eligibility',
            'Other',
        ];
    }
}

if (! function_exists('teacher_item_status_options')) {
    /**
     * @return list<string>
     */
    function teacher_item_status_options(): array
    {
        return ['Own Station', 'Detached', 'On Detail', 'On Leave', 'Other'];
    }
}

if (! function_exists('teacher_subject_options')) {
    /**
     * @return list<string>
     */
    function teacher_subject_options(): array
    {
        return [
            'Mathematics',
            'Science',
            'English',
            'Filipino',
            'Araling Panlipunan',
            'MAPEH',
            'Values Education',
            'TLE',
            'TLE FSC',
            'TLE/BPP',
            'Elementary General',
        ];
    }
}

if (! function_exists('teacher_format_tin')) {
    function teacher_format_tin($tin): ?string
    {
        if ($tin === null) {
            return null;
        }

        if (! is_string($tin)) {
            $tin = (string) $tin;
        }

        if (trim($tin) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $tin);
        if ($digits === null || strlen($digits) !== 9) {
            return null;
        }

        return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 3);
    }
}

if (! function_exists('teacher_normalize_philsys')) {
    function teacher_normalize_philsys(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);
        if ($digits === null || strlen($digits) !== 12) {
            return null;
        }

        return $digits;
    }
}

if (! function_exists('teacher_parse_date_parts')) {
    function teacher_parse_date_parts($month, $day, $year): ?string
    {
        $month = (int) $month;
        $day   = (int) $day;
        $year  = (int) $year;

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || $year < 1900 || $year > (int) date('Y')) {
            return null;
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}

if (! function_exists('teacher_date_parts_from_value')) {
    /**
     * @return array{month: int|null, day: int|null, year: int|null}
     */
    function teacher_date_parts_from_value(?string $date): array
    {
        if (empty($date)) {
            return ['month' => null, 'day' => null, 'year' => null];
        }

        try {
            $dt = new DateTime($date);

            return [
                'month' => (int) $dt->format('n'),
                'day'   => (int) $dt->format('j'),
                'year'  => (int) $dt->format('Y'),
            ];
        } catch (\Throwable $e) {
            return ['month' => null, 'day' => null, 'year' => null];
        }
    }
}

if (! function_exists('teacher_in_list_rule')) {
    function teacher_in_list_rule(array $options): string
    {
        return 'permit_empty|in_list[' . implode(',', $options) . ']';
    }
}

if (! function_exists('teacher_store_validation_rules')) {
    /**
     * @return array<string, string>
     */
    function teacher_store_validation_rules(bool $isCreate = true): array
    {
        $rules = [
            // Names allow Unicode letters (Ñ, accents), spaces, periods (Ma. Cruz,
            // Jr.), apostrophes and hyphens. Digits/symbols stay invalid on purpose.
            'first_name'           => 'required|min_length[2]|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'last_name'            => 'required|min_length[2]|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'employment_status'    => 'required|in_list[active,inactive,on_leave,resigned,terminated]',
            // Religion is required, mirroring the student registration policy.
            // The selector's "Other" choice is resolved to its typed text before
            // the rules run (religion_normalize_request()), so this checks the
            // real religion; the teachers.religion column holds 50 characters.
            'religion'             => 'required|max_length[50]',
            // Optional fields with basic validation
            'middle_name'          => 'permit_empty|min_length[2]|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]*$/u]',
            'gender'               => 'permit_empty|in_list[Male,Female]',
            'birth_month'          => 'permit_empty|integer|greater_than[0]|less_than[13]',
            'birth_day'            => 'permit_empty|integer|greater_than[0]|less_than[32]',
            'birth_year'           => 'permit_empty|integer|greater_than[1900]|less_than_equal_to[' . date('Y') . ']',
            'license_number'       => 'permit_empty|exact_length[7]|numeric',
            'tin'                  => 'permit_empty|regex_match[/^\d{3}-?\d{3}-?\d{3}$/]',
            'personnel_category'   => 'permit_empty|max_length[10]|alpha_numeric_space',
            'fund_source'          => teacher_in_list_rule(teacher_fund_source_options()),
            'position'             => teacher_in_list_rule(teacher_position_options()),
            'designation'          => teacher_in_list_rule(teacher_designation_options()),
            'nature_of_appointment'=> teacher_in_list_rule(teacher_nature_of_appointment_options()),
            'baccalaureate_degree' => 'permit_empty|max_length[255]',
            'prc_specialization'   => 'permit_empty|max_length[100]',
            'prc_major_units_percent' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
            'minor'                => 'permit_empty|max_length[100]',
            'masters_degree'       => 'permit_empty|max_length[255]',
            'government_employee_no' => 'permit_empty|max_length[20]|numeric',
            'hiring_arrangement'   => teacher_in_list_rule(teacher_hiring_arrangement_options()),
            'ethnic_group'         => 'permit_empty|max_length[50]',
            'item_status'          => teacher_in_list_rule(teacher_item_status_options()),
            'civil_status'         => teacher_in_list_rule(teacher_civil_status_options()),
            'philsys_number'       => 'permit_empty|exact_length[12]|numeric',
            'eligibility'          => teacher_in_list_rule(teacher_eligibility_options()),
            'service_month'        => 'permit_empty|greater_than_equal_to[0]|less_than[13]',
            'service_day'          => 'permit_empty|greater_than_equal_to[0]|less_than[32]',
            'service_year'         => 'permit_empty|greater_than_equal_to[0]|less_than_equal_to[' . date('Y') . ']',
            'new_station_month'    => 'permit_empty|greater_than_equal_to[0]|less_than[13]',
            'new_station_day'      => 'permit_empty|greater_than_equal_to[0]|less_than[32]',
            'new_station_year'     => 'permit_empty|greater_than_equal_to[0]|less_than_equal_to[' . date('Y') . ']',
            'contact_number'       => phone_validation_rule(),
            'address'              => 'permit_empty|max_length[500]',
            'subjects'             => teacher_in_list_rule(teacher_subject_options()),
            'date_hired'           => 'permit_empty|valid_date',
        ];

        if ($isCreate) {
            $rules['email']    = 'required|valid_email|is_unique[users.email]';
            // Shared password policy: length AND at least one digit.
            $rules['password'] = password_validation_rule('password');
        } else {
            $rules['email'] = 'permit_empty|valid_email';
        }

        return $rules;
    }
}

if (! function_exists('teacher_store_validation_messages')) {
    /**
     * Human-readable validation messages for the teacher form. Wire these into
     * $this->validate($rules, teacher_store_validation_messages()) wherever the
     * rules are used so the modal/UI shows *why* a field failed instead of the
     * generic "not in the correct format".
     *
     * @return array<string, array<string, string>>
     */
    function teacher_store_validation_messages(): array
    {
        $nameAllowed = 'letters (including Ñ and accented characters), spaces, periods, apostrophes, and hyphens';

        return [
            'first_name' => [
                'regex_match' => "The First Name field may only contain {$nameAllowed}. Digits and symbols are not allowed.",
                'min_length'  => 'The First Name field must be at least 2 characters long.',
            ],
            'middle_name' => [
                'regex_match' => "The Middle Name field may only contain {$nameAllowed}.",
                'min_length'  => 'The Middle Name field must be at least 2 characters long.',
            ],
            'last_name' => [
                'regex_match' => "The Last Name field may only contain {$nameAllowed}. Digits and symbols are not allowed.",
                'min_length'  => 'The Last Name field must be at least 2 characters long.',
            ],
            'email' => [
                'valid_email' => 'Please enter a valid email address.',
                'is_unique'   => 'That email address is already used by another account.',
            ],
            'employment_status' => [
                'in_list' => 'Please choose a valid Employment Status.',
            ],
            'license_number' => [
                'exact_length' => 'The PRC License Number must be exactly 7 digits.',
                'numeric'      => 'The PRC License Number must contain digits only.',
            ],
            'philsys_number' => [
                'exact_length' => 'The PhilSys Number must be exactly 12 digits.',
                'numeric'      => 'The PhilSys Number must contain digits only.',
            ],
            'tin' => [
                'regex_match' => 'The TIN must be formatted like 000-000-000 (or 000000000).',
            ],
            'contact_number' => [
                'required'    => 'The Contact Number must be exactly 11 digits and start with 09 (e.g., 09171234567).',
                'max_length'  => 'The Contact Number must be exactly 11 digits and start with 09 (e.g., 09171234567).',
                'regex_match' => 'The Contact Number must be exactly 11 digits and start with 09 (e.g., 09171234567).',
            ],
            'government_employee_no' => [
                'numeric' => 'The Employee No. must contain digits only.',
            ],
            'birth_month' => [
                'less_than' => 'Please choose a valid birth month (1-12).',
            ],
            'birth_day' => [
                'less_than' => 'Please choose a valid birth day (1-31).',
            ],
            'birth_year' => [
                'less_than_equal_to' => 'The Birth Year cannot be in the future.',
            ],
        ] + religion_validation_messages(50) + password_validation_messages('password');
    }
}

if (! function_exists('teacher_collect_personnel_from_request')) {
    /**
     * @return array<string, mixed>
     */
    function teacher_collect_personnel_from_request(\CodeIgniter\HTTP\IncomingRequest $request): array
    {
        $birthDate = teacher_parse_date_parts(
            $request->getPost('birth_month'),
            $request->getPost('birth_day'),
            $request->getPost('birth_year')
        );

        $serviceDate = teacher_parse_date_parts(
            $request->getPost('service_month'),
            $request->getPost('service_day'),
            $request->getPost('service_year')
        );

        $newStationDate = teacher_parse_date_parts(
            $request->getPost('new_station_month'),
            $request->getPost('new_station_day'),
            $request->getPost('new_station_year')
        );

        $tin = teacher_format_tin($request->getPost('tin'));
        $philsys = teacher_normalize_philsys($request->getPost('philsys_number'));

        $percent = $request->getPost('prc_major_units_percent');
        $percent = ($percent === null || $percent === '') ? null : (float) $percent;

        $dateHired = $request->getPost('date_hired');
        if (empty($dateHired) && $serviceDate) {
            $dateHired = $serviceDate;
        }

        return [
            'license_number'              => $request->getPost('license_number') ?: null,
            'tin'                         => $tin,
            'personnel_category'          => $request->getPost('personnel_category') ?: null,
            'first_name'                  => trim((string) $request->getPost('first_name')),
            'middle_name'                 => trim((string) $request->getPost('middle_name')) ?: null,
            'last_name'                   => trim((string) $request->getPost('last_name')),
            'gender'                      => $request->getPost('gender'),
            'date_of_birth'               => $birthDate,
            'fund_source'                 => $request->getPost('fund_source') ?: null,
            'position'                    => $request->getPost('position') ?: null,
            'designation'                 => $request->getPost('designation') ?: null,
            'nature_of_appointment'       => $request->getPost('nature_of_appointment') ?: null,
            'baccalaureate_degree'        => trim((string) $request->getPost('baccalaureate_degree')) ?: null,
            'prc_specialization'          => trim((string) $request->getPost('prc_specialization')) ?: null,
            'prc_major_units_percent'     => $percent,
            'minor'                       => trim((string) $request->getPost('minor')) ?: null,
            'masters_degree'              => trim((string) $request->getPost('masters_degree')) ?: null,
            'government_employee_no'      => $request->getPost('government_employee_no') ?: null,
            'hiring_arrangement'          => $request->getPost('hiring_arrangement') ?: null,
            'religion'                    => trim((string) $request->getPost('religion')) ?: null,
            'ethnic_group'                => trim((string) $request->getPost('ethnic_group')) ?: null,
            'item_status'                 => $request->getPost('item_status') ?: null,
            'civil_status'                => $request->getPost('civil_status') ?: null,
            'philsys_number'              => $philsys,
            'eligibility'                 => $request->getPost('eligibility') ?: null,
            'date_first_service'          => $serviceDate,
            'date_first_service_new_station' => $newStationDate,
            'contact_number'              => phone_normalize($request->getPost('contact_number')) ?: null,
            'address'                     => $request->getPost('address') ?: null,
            'department'                  => $request->getPost('subjects') ?: null,
            'specialization'              => $request->getPost('prc_specialization') ?: ($request->getPost('subjects') ?: null),
            'date_hired'                  => $dateHired,
            'employment_status'           => $request->getPost('employment_status'),
        ];
    }
}

if (! function_exists('teacher_format_date_display')) {
    function teacher_format_date_display(?string $date): string
    {
        if (empty($date)) {
            return '—';
        }

        try {
            return (new DateTime($date))->format('F j, Y');
        } catch (\Throwable $e) {
            return '—';
        }
    }
}

if (! function_exists('teacher_detail_value')) {
    function teacher_detail_value(?string $value, string $empty = 'Not specified'): string
    {
        if ($value === null || $value === '') {
            return '<span class="teacher-info-value empty">' . esc($empty) . '</span>';
        }

        return '<span class="teacher-info-value">' . esc($value) . '</span>';
    }
}
