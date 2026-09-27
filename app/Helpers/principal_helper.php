<?php

declare(strict_types=1);

use App\Models\SystemSettingModel;

if (! function_exists('school_principal')) {
    /**
     * The current school principal (school head), used by every part of the
     * system that signs or introduces the school head.
     *
     * Public schools change their principal every few years, so the name, the
     * rank and the photo are all stored in `system_settings` and can be
     * replaced from Admin > Settings without touching the code. Every value
     * has a hardcoded fallback so pages still render on a fresh install, or on
     * a database that predates the feature.
     *
     * Used by: student ID cards, report cards (student, teacher and SNED
     * progress reports), the public About page, the site footer and the
     * automated email templates.
     *
     * The result is memoized per request — a printed report card asks for the
     * principal once per page.
     *
     * @return array{
     *     name: string,
     *     rank: string,
     *     photo_path: string,
     *     photo_url: string
     * }
     */
    function school_principal(): array
    {
        static $principal = null;

        if ($principal !== null) {
            return $principal;
        }

        $settings = new SystemSettingModel();

        $name  = trim((string) $settings->getSetting('school_principal_name', ''));
        $rank  = trim((string) $settings->getSetting('school_principal_rank', ''));
        $photo = trim((string) $settings->getSetting('school_principal_photo', ''));

        $name = $name !== '' ? $name : 'Djoanne B. Pascual';
        $rank = $rank !== '' ? $rank : 'Principal IV';

        // Until an admin uploads a photo the About page keeps showing the
        // bundled picture, so a missing upload never leaves a broken image.
        $photoPath = $photo !== ''
            ? ltrim(str_replace('\\', '/', $photo), '/')
            : 'principal2.png';

        $principal = [
            'name'       => $name,
            'rank'       => $rank,
            'photo_path' => $photoPath,
            'photo_url'  => function_exists('asset_url') ? asset_url($photoPath) : base_url($photoPath),
        ];

        return $principal;
    }
}

if (! function_exists('school_principal_name')) {
    /**
     * Shorthand for school_principal()['name'].
     */
    function school_principal_name(): string
    {
        return school_principal()['name'];
    }
}
