<?php

if (!function_exists('grade_level_options')) {
    /**
     * Kindergarten (0) through Grade 6 — canonical grade-level list.
     * Grade 7 = SNED (Special Needs Education)
     * 99 = Custom (user-defined grade/class name)
     *
     * @return list<int>
     */
    function grade_level_options(): array
    {
        return [0, 1, 2, 3, 4, 5, 6, 7, 99];
    }
}

if (!function_exists('grade_min_age_reference')) {
    /**
     * Minimum age at the start of the school year for the grade levels that
     * have one, together with the typical age range shown to applicants.
     *
     * Kindergarten (0) = 5, Grade 1 = 6, ... Grade 6 = 11. Grade 7 (SNED) and
     * 99 (Custom) carry no minimum and are deliberately excluded so the
     * registration form never blocks them.
     *
     * @return list<array{grade:int,label:string,min_age:int,typical_min:int,typical_max:int}>
     */
    function grade_min_age_reference(): array
    {
        $rows = [];

        foreach (range(0, 6) as $grade) {
            $minAge = $grade + 5;
            $rows[] = [
                'grade'       => $grade,
                'label'       => grade_level_label($grade),
                'min_age'     => $minAge,
                'typical_min' => $minAge,
                'typical_max' => $minAge + 1,
            ];
        }

        return $rows;
    }
}

if (!function_exists('grade_min_age')) {
    /**
     * Minimum age for one grade level, or null when the level has none
     * (SNED / Custom).
     */
    function grade_min_age(?int $gradeLevel): ?int
    {
        if ($gradeLevel === null) {
            return null;
        }

        foreach (grade_min_age_reference() as $row) {
            if ($row['grade'] === $gradeLevel) {
                return $row['min_age'];
            }
        }

        return null;
    }
}

if (!function_exists('grade_grading_types')) {
    /**
     * Per-grade-level grading type map (numerical vs non_numerical), stored in
     * the system_settings table as JSON under the key 'grade_grading_types'.
     * Grade 7 (SNED) is always non_numerical; everything else defaults to
     * numerical unless the admin switched it on the Settings page.
     *
     * @return array<int, string>
     */
    function grade_grading_types(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $raw = null;
        try {
            $row = \Config\Database::connect()
                ->table('system_settings')
                ->where('setting_key', 'grade_grading_types')
                ->get()
                ->getRowArray();
            $raw = $row['setting_value'] ?? null;
        } catch (\Throwable $e) {
            $raw = null;
        }

        $decoded = $raw ? json_decode((string) $raw, true) : null;
        $map = is_array($decoded) ? $decoded : [];

        // Normalize values.
        $normalized = [];
        foreach ($map as $grade => $type) {
            $normalized[(int) $grade] = $type === 'non_numerical' ? 'non_numerical' : 'numerical';
        }

        // SNED (grade 7) is always non-numerical.
        $normalized[7] = 'non_numerical';

        return $cache = $normalized;
    }
}

if (!function_exists('grade_grading_type')) {
    /**
     * Grading type for one grade level: 'numerical' or 'non_numerical'.
     */
    function grade_grading_type(int $gradeLevel): string
    {
        $types = grade_grading_types();
        return $types[$gradeLevel] ?? 'numerical';
    }
}

if (!function_exists('save_grade_grading_types')) {
    /**
     * Persist the per-grade grading-type map into system_settings.
     *
     * @param array<int, string> $types
     */
    function save_grade_grading_types(array $types): bool
    {
        $normalized = [];
        foreach ($types as $grade => $type) {
            $normalized[(int) $grade] = $type === 'non_numerical' ? 'non_numerical' : 'numerical';
        }
        $normalized[7] = 'non_numerical';

        $db = \Config\Database::connect();
        $json = json_encode($normalized);

        $exists = $db->table('system_settings')
            ->where('setting_key', 'grade_grading_types')
            ->countAllResults();

        if ($exists > 0) {
            return $db->table('system_settings')
                ->where('setting_key', 'grade_grading_types')
                ->update(['setting_value' => $json]);
        }

        return (bool) $db->table('system_settings')->insert([
            'setting_key'   => 'grade_grading_types',
            'setting_value' => $json,
        ]);
    }
}

if (!function_exists('section_domain_selections')) {
    /**
     * Per-section developmental-domain selection map, stored in system_settings
     * as JSON under the key 'section_domains' — e.g. {"82":[1,4,7]}.
     *
     * A section id missing from the map means "never configured", which the
     * callers treat as "uses every shared domain" so sections that existed
     * before this feature keep grading exactly as they did.
     *
     * @param bool $flush Clear the per-request cache (used after saving).
     * @return array<int, list<int>>
     */
    function section_domain_selections(bool $flush = false): array
    {
        static $cache = null;

        if ($flush) {
            $cache = null;
        }

        if ($cache !== null) {
            return $cache;
        }

        $raw = null;
        try {
            $row = \Config\Database::connect()
                ->table('system_settings')
                ->where('setting_key', 'section_domains')
                ->get()
                ->getRowArray();
            $raw = $row['setting_value'] ?? null;
        } catch (\Throwable $e) {
            $raw = null;
        }

        $decoded = $raw ? json_decode((string) $raw, true) : null;
        $map = is_array($decoded) ? $decoded : [];

        $normalized = [];
        foreach ($map as $sectionId => $ids) {
            if (! is_array($ids)) {
                continue;
            }
            $normalized[(int) $sectionId] = array_values(array_unique(array_map('intval', $ids)));
        }

        return $cache = $normalized;
    }
}

if (!function_exists('section_selected_domain_ids')) {
    /**
     * Developmental-domain ids chosen for one section.
     *
     * @return list<int>|null Null when the section was never configured
     *                        (= use every shared domain).
     */
    function section_selected_domain_ids(int $sectionId): ?array
    {
        $map = section_domain_selections();

        return array_key_exists($sectionId, $map) ? $map[$sectionId] : null;
    }
}

if (!function_exists('save_section_selected_domain_ids')) {
    /**
     * Remember which shared developmental domains a section uses.
     *
     * @param list<int> $ids
     */
    function save_section_selected_domain_ids(int $sectionId, array $ids): bool
    {
        $map = section_domain_selections();
        $map[(int) $sectionId] = array_values(array_unique(array_map('intval', $ids)));

        $db = \Config\Database::connect();
        $json = json_encode($map);

        $exists = $db->table('system_settings')
            ->where('setting_key', 'section_domains')
            ->countAllResults();

        if ($exists > 0) {
            $ok = $db->table('system_settings')
                ->where('setting_key', 'section_domains')
                ->update(['setting_value' => $json]);
        } else {
            $ok = $db->table('system_settings')->insert([
                'setting_key'   => 'section_domains',
                'setting_value' => $json,
            ]);
        }

        // Drop the cache so the rest of this request sees the new selection.
        section_domain_selections(true);

        return (bool) $ok;
    }
}

if (!function_exists('ensure_schedule_domain_support')) {
    /**
     * Non-numerical sections schedule developmental domains, not subjects.
     *
     * A domain id must never be written into teacher_schedules.subject_id:
     * that column has a FOREIGN KEY to subjects and 20 of the 21 existing
     * domains share an id with an unrelated subject, so the insert would
     * silently attach the wrong subject. Instead domains go into a dedicated
     * nullable domain_id column (subject_id stays NULL).
     *
     * Also relaxes day_of_week to accept 'TBD', which the assignment-only rows
     * (no schedule yet) are created with — the original enum only allowed
     * Monday..Friday, so those inserts were truncated or rejected.
     *
     * Self-healing and defensive: on a host whose DB user cannot run DDL the
     * caller can fall back gracefully. Returns true when domain_id exists.
     */
    function ensure_schedule_domain_support(): bool
    {
        static $checked = false;

        if ($checked) {
            return true;
        }

        $db = \Config\Database::connect();

        $hasDomainId = false;
        try {
            foreach ($db->getFieldData('teacher_schedules') as $field) {
                if (($field->name ?? '') === 'domain_id') {
                    $hasDomainId = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        if (! $hasDomainId) {
            try {
                $db->query('ALTER TABLE `teacher_schedules` ADD COLUMN `domain_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `subject_id`');
                $hasDomainId = true;
            } catch (\Throwable $e) {
                // Tolerate a concurrent worker having added it already.
                $hasDomainId = str_contains($e->getMessage(), 'Duplicate column');
            }
        }

        if ($hasDomainId) {
            try {
                $db->query('ALTER TABLE `teacher_schedules` ADD INDEX `teacher_schedules_domain_id_index` (`domain_id`)');
            } catch (\Throwable $e) {
                // Index already present — nothing to do.
            }

            try {
                $db->query(
                    'ALTER TABLE `teacher_schedules` ADD CONSTRAINT `teacher_schedules_domain_id_foreign` '
                    . 'FOREIGN KEY (`domain_id`) REFERENCES `sned_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
                );
            } catch (\Throwable $e) {
                // FK already present or the engine refused it; the column alone
                // is still fully functional so this is not fatal.
            }
        }

        // Allow the 'TBD' placeholder the assignment-only rows use.
        try {
            $col = $db->query("SHOW COLUMNS FROM `teacher_schedules` LIKE 'day_of_week'")->getRowArray();
            $type = $col['Type'] ?? '';
            if ($type !== '' && ! str_contains($type, 'TBD')) {
                $db->query(
                    "ALTER TABLE `teacher_schedules` MODIFY COLUMN `day_of_week` "
                    . "ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','TBD') NOT NULL"
                );
            }
        } catch (\Throwable $e) {
            // Non-fatal: the caller writes a real weekday instead.
        }

        return $checked = $hasDomainId;
    }
}

if (!function_exists('schedule_domain_column_ready')) {
    /** True when teacher_schedules.domain_id is usable. */
    function schedule_domain_column_ready(): bool
    {
        static $ready = null;

        if ($ready === null) {
            $ready = ensure_schedule_domain_support();
        }

        return $ready;
    }
}

if (!function_exists('schedule_day_accepts_tbd')) {
    /** True when day_of_week accepts the 'TBD' placeholder. */
    function schedule_day_accepts_tbd(): bool
    {
        static $accepts = null;

        if ($accepts !== null) {
            return $accepts;
        }

        try {
            $col  = \Config\Database::connect()
                ->query("SHOW COLUMNS FROM `teacher_schedules` LIKE 'day_of_week'")
                ->getRowArray();
            $type = $col['Type'] ?? '';
            return $accepts = ($type !== '' && str_contains($type, 'TBD'));
        } catch (\Throwable $e) {
            return $accepts = false;
        }
    }
}

if (!function_exists('schedule_item_id_expr')) {
    /**
     * SQL expression resolving a teacher_schedules row's item id.
     *
     * A row holds either a subject id (numerical sections) or a developmental
     * domain id (non-numerical ones) — the latter lives in domain_id because
     * subject_id carries a FOREIGN KEY to subjects and domain ids collide with
     * subject ids. Falls back to subject_id alone when the host could not add
     * the domain_id column, so queries stay valid everywhere.
     *
     * @param string $alias teacher_schedules alias used in the query.
     */
    function schedule_item_id_expr(string $alias = 'ts'): string
    {
        return schedule_domain_column_ready()
            ? "COALESCE({$alias}.domain_id, {$alias}.subject_id)"
            : "{$alias}.subject_id";
    }
}

if (!function_exists('schedule_item_name_select')) {
    /**
     * SELECT fragment resolving a schedule row's display name, covering both
     * subjects and developmental domains. Requires schedule_item_name_joins().
     */
    function schedule_item_name_select(string $alias = 'ts'): string
    {
        return schedule_domain_column_ready()
            ? 'COALESCE(sub.subject_name, sc.name)'
            : 'sub.subject_name';
    }
}

if (!function_exists('schedule_item_name_joins')) {
    /**
     * Joins feeding schedule_item_name_select(). sned_categories is only joined
     * when domain_id exists so the SQL never references a missing column.
     *
     * @param string $alias teacher_schedules alias used in the query.
     */
    function schedule_item_name_joins(string $alias = 'ts'): string
    {
        $joins = "LEFT JOIN subjects sub ON sub.id = {$alias}.subject_id";

        if (schedule_domain_column_ready()) {
            $joins .= " LEFT JOIN sned_categories sc ON sc.id = {$alias}.domain_id";
        }

        return $joins;
    }
}

if (!function_exists('is_non_numerical_section')) {
    /**
     * True when a section is assessed with developmental domains rather than
     * subjects. Returns false for a missing section instead of throwing.
     */
    function is_non_numerical_section(int $sectionId): bool
    {
        static $cache = [];

        if (array_key_exists($sectionId, $cache)) {
            return $cache[$sectionId];
        }

        try {
            $row = \Config\Database::connect()
                ->table('sections')
                ->select('grading_type')
                ->where('id', $sectionId)
                ->get()->getRowArray();

            return $cache[$sectionId] = (($row['grading_type'] ?? 'numerical') === 'non_numerical');
        } catch (\Throwable $e) {
            return $cache[$sectionId] = false;
        }
    }
}

if (!function_exists('grade_level_min')) {
    function grade_level_min(): int
    {
        return 0;
    }
}

if (!function_exists('grade_level_max')) {
    function grade_level_max(): int
    {
        return 7;
    }
}

if (!function_exists('grade_level_label')) {
    function grade_level_label(int $gradeLevel, ?string $customName = null): string
    {
        if ($gradeLevel === 0) {
            return 'Kindergarten';
        }

        if ($gradeLevel === 7) {
            return 'SNED (Special Needs Education)';
        }

        if ($gradeLevel === 99) {
            return $customName ?: 'Custom';
        }

        return 'Grade ' . $gradeLevel;
    }
}

if (!function_exists('is_custom_grade_level')) {
    function is_custom_grade_level(?int $gradeLevel, ?string $gradingType = null): bool
    {
        if ($gradeLevel === 99) {
            return true;
        }
        if ($gradingType === 'custom') {
            return true;
        }
        return false;
    }
}

if (!function_exists('grade_level_in_list_rule')) {
    function grade_level_in_list_rule(): string
    {
        return 'in_list[0,1,2,3,4,5,6,7,99]';
    }
}

if (!function_exists('grade_level_chart_labels')) {
    /**
     * Compact labels for chart axes (SNED without the spelled-out meaning).
     *
     * @return list<string>
     */
    function grade_level_chart_labels(): array
    {
        return array_map(
            static fn (int $g): string => $g === 7 ? 'SNED' : grade_level_label($g),
            grade_level_options()
        );
    }
}

if (!function_exists('grade_level_announcement_role')) {
    function grade_level_announcement_role(int $gradeLevel): string
    {
        if ($gradeLevel === 99) {
            return 'custom';
        }
        return 'grade_' . $gradeLevel;
    }
}

if (!function_exists('is_graduating_grade')) {
    function is_graduating_grade(int $gradeLevel): bool
    {
        return $gradeLevel === grade_level_max();
    }
}

if (!function_exists('is_sned_grade')) {
    function is_sned_grade(int $gradeLevel): bool
    {
        return $gradeLevel === 7;
    }
}

if (!function_exists('grade_level_js_labels')) {
    /**
     * Map of grade level int => label for JavaScript.
     *
     * @return array<string, string>
     */
    function grade_level_js_labels(): array
    {
        $map = [];
        foreach (grade_level_options() as $g) {
            $map[(string) $g] = grade_level_label($g);
        }

        return $map;
    }
}

if (!function_exists('grade_level_display_name')) {
    /**
     * Get the display name for a section's grade level.
     * Handles custom sections with custom names.
     */
    function grade_level_display_name(array $section): string
    {
        $gradeLevel = (int) ($section['grade_level'] ?? 0);
        
        if ($gradeLevel === 99 || ($section['grading_type'] ?? 'numerical') === 'custom') {
            $customName = $section['grade_level_custom'] ?? null;
            return $customName ?: 'Custom';
        }
        
        return grade_level_label($gradeLevel);
    }
}

if (!function_exists('getSymbolBadgeClass')) {
    /**
     * Get Bootstrap badge class for grading symbols
     */
    function getSymbolBadgeClass(string $symbol): string
    {
        $symbolClasses = [
            'P' => 'success',
            'AP' => 'info',
            'D' => 'warning',
            'B' => 'danger',
            'NO/NA' => 'secondary'
        ];
        
        return $symbolClasses[$symbol] ?? 'primary';
    }
}
