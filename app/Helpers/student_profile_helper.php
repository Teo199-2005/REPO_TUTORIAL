<?php

/**
 * Student portal completion helpers.
 *
 * A student unlocks every sidebar page — Dashboard included — only after
 * completing all three requirements; until then My Profile (and logout) are
 * the only reachable student pages:
 *   1. BMI fields   — height_cm + weight_kg + ethnicity (computed into bmi)
 *   2. Profile picture — students.photo_path (shown in the circle avatar)
 *   3. 2x2 ID picture  — students.id_photo_path (shown on the square ID card)
 *
 * Enforced server-side by App\Filters\StudentAccessFilter and rendered as a
 * checklist by the profile/dashboard views. The sidebar lock flags come from
 * portal_nav_student_sections() in portal_nav_helper.php.
 */

if (! function_exists('student_profile_requirements')) {
    /**
     * Per-requirement completion flags for the logged-in student.
     *
     * @param array|null $student Row from the students table (or null)
     *
     * @return array{nutrition: bool, photo: bool, id_photo: bool}
     */
    function student_profile_requirements(?array $student): array
    {
        return [
            'nutrition' => \App\Models\StudentModel::isNutritionProfileComplete($student),
            'photo'     => ! empty($student['photo_path'] ?? null),
            'id_photo'  => ! empty($student['id_photo_path'] ?? null),
        ];
    }
}

if (! function_exists('student_profile_complete')) {
    /**
     * True when every requirement is satisfied and the portal is unlocked.
     *
     * @param array|null $student Row from the students table (or null)
     */
    function student_profile_complete(?array $student): bool
    {
        foreach (student_profile_requirements($student) as $done) {
            if (! $done) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('student_profile_unlock_checklist')) {
    /**
     * Unlock checklist for the student portal gate.
     *
     * Every requirement carries a human reason for its state, so the UI can
     * never show a bare "x" without saying what is still missing, plus a deep
     * link to the exact field that fixes it.
     *
     * @param array|null $student Row from the students table (or null)
     *
     * @return array{
     *     complete: bool,
     *     done: int,
     *     total: int,
     *     percent: int,
     *     missing: list<string>,
     *     items: list<array{key: string, label: string, met: bool, why: string, action: string, link: string}>
     * }
     */
    function student_profile_unlock_checklist(?array $student): array
    {
        $reqs = student_profile_requirements($student);

        $blankNumber = static function ($value): bool {
            return $value === null || $value === '' || (float) $value <= 0;
        };
        $blankText = static function ($value): bool {
            return $value === null || trim((string) $value) === '';
        };

        // Name the exact BMI fields that are still blank (same rules the
        // completeness check applies) instead of a generic "BMI fields" x.
        $missingBmi = [];
        if ($blankNumber($student['height_cm'] ?? null)) {
            $missingBmi[] = 'height (cm)';
        }
        if ($blankNumber($student['weight_kg'] ?? null)) {
            $missingBmi[] = 'weight (kg)';
        }
        if ($blankText($student['ethnicity'] ?? null)) {
            $missingBmi[] = 'ethnicity';
        }

        $profileUrl = base_url('student/profile');

        $items = [
            [
                'key'    => 'nutrition',
                'label'  => 'BMI fields (height, weight, ethnicity)',
                'met'    => $reqs['nutrition'],
                'why'    => $reqs['nutrition']
                    ? 'Height, weight and ethnicity are on file.'
                    : 'Still blank: ' . ($missingBmi === [] ? 'height, weight, ethnicity' : implode(', ', $missingBmi)) . '.',
                'action' => 'Fill in',
                'link'   => $profileUrl . '#height_cm',
            ],
            [
                'key'    => 'photo',
                'label'  => 'Profile picture',
                'met'    => $reqs['photo'],
                'why'    => $reqs['photo']
                    ? 'Your profile picture is uploaded.'
                    : 'No profile picture uploaded yet.',
                'action' => 'Upload',
                'link'   => $profileUrl . '#photo',
            ],
            [
                'key'    => 'id_photo',
                'label'  => '2x2 ID picture',
                'met'    => $reqs['id_photo'],
                'why'    => $reqs['id_photo']
                    ? 'Your 2x2 ID picture is uploaded.'
                    : 'No 2x2 ID picture uploaded yet.',
                'action' => 'Upload',
                'link'   => $profileUrl . '#id_photo',
            ],
        ];

        $done    = 0;
        $missing = [];
        foreach ($items as $item) {
            if ($item['met']) {
                $done++;
            } else {
                $missing[] = $item['label'];
            }
        }

        $total = count($items);

        return [
            'complete' => $total > 0 && $done === $total,
            'done'     => $done,
            'total'    => $total,
            'percent'  => $total > 0 ? (int) round(($done / $total) * 100) : 100,
            'missing'  => $missing,
            'items'    => $items,
        ];
    }
}

if (! function_exists('student_profile_unlock_reason')) {
    /**
     * One-line explanation of why a sidebar page is locked (used as the link
     * tooltip). Empty when the portal is already unlocked.
     */
    function student_profile_unlock_reason(?array $student): string
    {
        $checklist = student_profile_unlock_checklist($student);

        if ($checklist['complete']) {
            return '';
        }

        return 'Locked until your profile is complete. Still missing: '
            . implode('; ', $checklist['missing']) . '.';
    }
}

if (! function_exists('profile_unlock_brand')) {
    /**
     * Minimal school branding for the portal-unlock card.
     *
     * @return array{school: string, product: string, portal: string, logo: string, profile_url: string}
     */
    function profile_unlock_brand(): array
    {
        return [
            'school'      => 'Cauayan South Central School',
            'product'     => 'CSCS Tap n Track',
            'portal'      => 'Student Portal',
            'logo'        => function_exists('school_logo_url') ? school_logo_url() : asset_url('LPHS2.png'),
            'profile_url' => base_url('student/profile'),
        ];
    }
}

