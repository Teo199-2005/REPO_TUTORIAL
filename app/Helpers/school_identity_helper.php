<?php

declare(strict_types=1);

/**
 * School identity printed on the official report documents (Academic Report
 * Card, Learner Development Report).
 *
 * Cauayan South Central School is an elementary school in Cauayan City,
 * Isabela - Region II (Cagayan Valley), Schools Division of Cauayan City.
 * These values used to be typed into each view by hand, which is how a wrong
 * school name once ended up printed on a report card; every document now reads
 * them from here instead.
 *
 * Each value can be overridden from Admin > System Settings without touching
 * code (keys: school_name, school_region, school_division), the same way the
 * principal's name and rank are handled by school_principal().
 */

if (! function_exists('school_identity')) {
    /**
     * @return array{school_name: string, region: string, division: string}
     */
    function school_identity(): array
    {
        static $identity = null;

        if ($identity !== null) {
            return $identity;
        }

        // Setting key => printed line. The keys are what an admin may override
        // in `system_settings`; the array keys are what the views read.
        $lines = [
            'school_name'     => 'Cauayan South Central School',
            'school_region'   => 'Region II – Cagayan Valley',
            'school_division' => 'Schools Division of Cauayan City',
        ];

        $identity = [
            'school_name' => $lines['school_name'],
            'region'      => $lines['school_region'],
            'division'    => $lines['school_division'],
        ];

        try {
            if (class_exists(\App\Models\SystemSettingModel::class)) {
                $settings = new \App\Models\SystemSettingModel();
                foreach ($lines as $key => $fallback) {
                    $stored = trim((string) $settings->getSetting($key, ''));
                    if ($stored === '') {
                        continue;
                    }
                    $identity[array_search($key, array_keys($lines), true)] = $stored;
                }
            }
        } catch (\Throwable $e) {
            // A database hiccup must never stop a report card from printing:
            // the hardcoded defaults above are the school's real values.
        }

        return $identity;
    }
}

if (! function_exists('school_name')) {
    /**
     * The school's official name, e.g. "Cauayan South Central School".
     */
    function school_name(): string
    {
        return school_identity()['school_name'];
    }
}

if (! function_exists('school_region')) {
    /**
     * The DepEd region line, e.g. "Region II – Cagayan Valley".
     */
    function school_region(): string
    {
        return school_identity()['region'];
    }
}

if (! function_exists('school_division')) {
    /**
     * The schools-division line, e.g. "Schools Division of Cauayan City".
     */
    function school_division(): string
    {
        return school_identity()['division'];
    }
}
