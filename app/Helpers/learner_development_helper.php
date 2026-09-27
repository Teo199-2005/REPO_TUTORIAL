<?php

declare(strict_types=1);

use App\Models\SnedCategoryModel;
use App\Models\SnedGradeModel;

/**
 * Learner Development Report (non-numerical) - shared data layer.
 *
 * The Learner Development Report is a SEPARATE document from the Academic
 * Report Card: it carries the learner's conduct, values and character
 * development, never a subject grade, a general average, a ranking or a final
 * rating. This helper is the single source of truth for the teacher, student
 * and admin PDF endpoints so all three print exactly the same document.
 *
 * Everything is read from the non-numerical tables that already exist
 * (sned_categories -> sned_category_fields -> sned_grades). Nothing is
 * calculated: a rating is whatever `sned_grades.grade_symbol` contains for the
 * learner's school year, printed verbatim.
 */

if (! function_exists('learner_development_terms')) {
    /**
     * 2026 DepEd three-term layout.
     *
     * The non-numerical tables still key their periods as `quarter` 1-4, so
     * Term N reads quarter N and quarter 4 is never printed: this document is
     * a three-term report, not a four-quarter one.
     *
     * @return array<int, array{label: string, quarter: int}>
     */
    function learner_development_terms(): array
    {
        return [
            1 => ['label' => 'Term 1', 'quarter' => 1],
            2 => ['label' => 'Term 2', 'quarter' => 2],
            3 => ['label' => 'Term 3', 'quarter' => 3],
        ];
    }
}

if (! function_exists('learner_development_core_values')) {
    /**
     * The four DepEd core values, always printed as the first table of the
     * report. A row with no matching indicator simply stays blank.
     *
     * @return list<string>
     */
    function learner_development_core_values(): array
    {
        return [
            'Maka-Diyos',
            'Makatao',
            'Makakalikasan',
            'Makabansa',
        ];
    }
}

if (! function_exists('learner_development_default_scale')) {
    /**
     * Rating descriptions used when a school has not configured its own scale
     * on the section. Ratings themselves are never invented - only these
     * descriptions are shown as a guide.
     *
     * @return list<array{symbol: string, label: string}>
     */
    function learner_development_default_scale(): array
    {
        return [
            ['symbol' => 'AO', 'label' => 'Always Observed'],
            ['symbol' => 'SO', 'label' => 'Sometimes Observed'],
            ['symbol' => 'RO', 'label' => 'Rarely Observed'],
            ['symbol' => 'NO', 'label' => 'Not Observed'],
        ];
    }
}

if (! function_exists('learner_development_behavior_keywords')) {
    /**
     * Indicator words that identify a conduct/character indicator even when it
     * sits in a domain that is not named after behaviour.
     *
     * @return list<string>
     */
    function learner_development_behavior_keywords(): array
    {
        return [
            'respect',
            'responsib',
            'cooperat',
            'honest',
            'disciplin',
            'obedien',
            'courteous',
            'polite',
            'attentive',
            'patient',
            'selfcontrol',
            'selfdiscipline',
            'punctual',
            'tidy',
            'neat',
            'goodmanners',
            'properbehavior',
        ];
    }
}

if (! function_exists('learner_development_normalize_label')) {
    /**
     * Lower-cased, punctuation-free form used only to match a stored indicator
     * ("Maka-Diyos", "Maka Diyos") to a core value row. Never used to alter
     * what is printed.
     */
    function learner_development_normalize_label(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value)));
    }
}

if (! function_exists('learner_development_is_conduct_domain')) {
    /**
     * Domains whose indicators describe behaviour, conduct, values or
     * character development.
     */
    function learner_development_is_conduct_domain(string $name): bool
    {
        return (bool) preg_match(
            '/behav|conduct|disciplin|character|value|attitude|trait|etiquette|social|emotion/i',
            $name
        );
    }
}

if (! function_exists('learner_development_scale')) {
    /**
     * Rating scale shown in the legend: the section's own configured symbols
     * when it has any, otherwise the DepEd observation scale.
     *
     * @return list<array{symbol: string, label: string}>
     */
    function learner_development_scale(int $sectionId): array
    {
        if ($sectionId <= 0) {
            return learner_development_default_scale();
        }

        try {
            $rows = \Config\Database::connect()
                ->table('section_grading_symbols')
                ->where('section_id', $sectionId)
                ->where('is_active', 1)
                ->orderBy('display_order', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            $rows = [];
        }

        $scale = [];
        foreach ($rows as $row) {
            $symbol = trim((string) ($row['symbol'] ?? ''));
            $label  = trim((string) ($row['label'] ?? ''));
            if ($symbol === '' && $label === '') {
                continue;
            }
            $scale[] = ['symbol' => $symbol, 'label' => $label];
        }

        return $scale === [] ? learner_development_default_scale() : $scale;
    }
}

if (! function_exists('learner_development_report_data')) {
    /**
     * Builds everything the PDF view needs for one learner.
     *
     * @param array|object $student    Row carrying at least id, first_name, last_name, lrn, grade_level
     * @param array|object $section    Row carrying at least id, section_name, grade_level
     * @param string|null  $schoolYear Defaults to the configured school year
     *
     * @return array{
     *     terms: array<int, array{label: string, quarter: int}>,
     *     coreValues: list<array{name: string, matched: bool, ratings: array<int, string>}>,
     *     behaviorIndicators: list<array{name: string, domain: string, ratings: array<int, string>}>,
     *     behaviorDomains: list<string>,
     *     remarks: list<string>,
     *     scale: list<array{symbol: string, label: string}>
     * }
     */
    function learner_development_report_data($student, $section, ?string $schoolYear = null): array
    {
        $student = is_object($student) ? get_object_vars($student) : (array) $student;
        $section = is_object($section) ? get_object_vars($section) : (array) $section;

        $studentId  = (int) ($student['id'] ?? 0);
        $sectionId  = (int) ($section['id'] ?? ($student['section_id'] ?? 0));
        $schoolYear = ($schoolYear !== null && trim($schoolYear) !== '')
            ? trim($schoolYear)
            : (function_exists('get_current_school_year') ? get_current_school_year() : '');

        $categories = [];
        $grades     = [];

        if ($studentId > 0 && $sectionId > 0) {
            $categoryModel = new SnedCategoryModel();
            $gradeModel    = new SnedGradeModel();

            $categories = $categoryModel->getAllCategoriesWithFields(
                $sectionId,
                (int) ($section['grade_level'] ?? $student['grade_level'] ?? 0)
            );
            $grades = $gradeModel->getStudentAllGrades($studentId, $schoolYear);
        }

        $terms = learner_development_terms();

        // Ratings are read straight from storage: no conversion, no maths.
        $ratingFor = static function ($fieldId, int $quarter) use ($grades): string {
            return trim((string) ($grades[$fieldId][$quarter]['grade_symbol'] ?? ''));
        };

        // Flat index of every active indicator with its domain, in the order
        // the school arranged them.
        $fields = [];
        foreach ($categories as $category) {
            $categoryName = (string) ($category['name'] ?? '');
            foreach (($category['fields'] ?? []) as $field) {
                $fieldId = (int) ($field['id'] ?? 0);
                if ($fieldId <= 0) {
                    continue;
                }
                $fields[$fieldId] = [
                    'name'   => trim((string) ($field['field_name'] ?? '')),
                    'domain' => $categoryName,
                ];
            }
        }


        // --- Core values -----------------------------------------------------
        $consumed   = [];
        $coreValues = [];

        foreach (learner_development_core_values() as $value) {
            $needle = learner_development_normalize_label($value);
            $row    = ['name' => $value, 'matched' => false, 'ratings' => []];

            foreach ($terms as $term => $meta) {
                $row['ratings'][$term] = '';
            }

            foreach ($fields as $fieldId => $field) {
                $haystack = learner_development_normalize_label($field['name']);
                if ($needle === '' || $haystack === '' || ! str_contains($haystack, $needle)) {
                    continue;
                }

                foreach ($terms as $term => $meta) {
                    $row['ratings'][$term] = $ratingFor($fieldId, (int) $meta['quarter']);
                }
                $row['matched'] = true;
                $consumed[$fieldId] = true;
                break;
            }

            $coreValues[] = $row;
        }

        // --- Behavioural indicators -----------------------------------------
        $keywords   = array_map('learner_development_normalize_label', learner_development_behavior_keywords());
        $indicators = [];
        $domains    = [];

        foreach ($categories as $category) {
            $categoryName = (string) ($category['name'] ?? '');
            $isConduct    = learner_development_is_conduct_domain($categoryName);

            foreach (($category['fields'] ?? []) as $field) {
                $fieldId = (int) ($field['id'] ?? 0);
                $name    = trim((string) ($field['field_name'] ?? ''));

                if ($fieldId <= 0 || $name === '' || isset($consumed[$fieldId])) {
                    continue;
                }

                if (! $isConduct) {
                    $haystack = learner_development_normalize_label($name);
                    $isTrait  = false;
                    foreach ($keywords as $keyword) {
                        if ($keyword !== '' && str_contains($haystack, $keyword)) {
                            $isTrait = true;
                            break;
                        }
                    }
                    if (! $isTrait) {
                        continue;
                    }
                }

                $ratings = [];
                foreach ($terms as $term => $meta) {
                    $ratings[$term] = $ratingFor($fieldId, (int) $meta['quarter']);
                }

                $indicators[] = ['name' => $name, 'domain' => $categoryName, 'ratings' => $ratings];

                if ($categoryName !== '' && ! in_array($categoryName, $domains, true)) {
                    $domains[] = $categoryName;
                }
            }
        }

        // --- Remarks ---------------------------------------------------------
        // Only what a teacher actually typed, deduplicated, earliest period
        // first. Parent/Guardian remarks are a blank line on the form: the
        // system has no place to store them.
        $remarks = [];
        if (! empty($grades)) {
            $collected = [];
            foreach ($grades as $byQuarter) {
                foreach ((array) $byQuarter as $quarter => $row) {
                    $text = trim((string) ($row['remarks'] ?? ''));
                    if ($text === '' || isset($collected[$text])) {
                        continue;
                    }
                    $collected[$text] = [
                        'quarter' => (int) $quarter,
                        'id'      => (int) ($row['id'] ?? 0),
                        'text'    => $text,
                    ];
                }
            }

            uasort($collected, static function (array $a, array $b): int {
                return [$a['quarter'], $a['id']] <=> [$b['quarter'], $b['id']];
            });

            $remarks = array_values(array_map(static fn (array $row): string => $row['text'], $collected));
        }

        return [
            'terms'              => $terms,
            'coreValues'         => $coreValues,
            'behaviorIndicators' => $indicators,
            'behaviorDomains'    => $domains,
            'remarks'            => $remarks,
            'scale'              => learner_development_scale($sectionId),
        ];
    }
}

if (! function_exists('learner_development_pdf_filename')) {
    /**
     * Download name for the report, e.g.
     * CSCS_Learner_Development_Report_Baybayin_Maria_Santos_2026-09-25.pdf
     *
     * @param array|object      $student
     * @param array|object|null $section
     */
    function learner_development_pdf_filename($student, $section = null): string
    {
        $student = is_object($student) ? get_object_vars($student) : (array) $student;
        $section = is_object($section) ? get_object_vars($section) : (array) $section;

        $safe = static function ($value): string {
            return (string) (preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) $value) ?: '');
        };

        $parts = array_filter([
            'CSCS_Learner_Development_Report',
            $safe($section['section_name'] ?? ''),
            $safe($student['first_name'] ?? ''),
            $safe($student['last_name'] ?? ''),
            date('Y-m-d'),
        ]);

        return implode('_', $parts) . '.pdf';
    }
}

