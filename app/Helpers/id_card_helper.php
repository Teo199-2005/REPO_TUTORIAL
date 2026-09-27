<?php

/**
 * Branding shared by every student ID card face (front and back).
 *
 * The principal comes from school_principal() (app/Helpers/principal_helper.php)
 * so the school office can update the name, rank and photo once from
 * Admin > Settings and every part of the system follows. Every value has a
 * hardcoded fallback, so the cards render correctly even when the setting rows
 * do not exist yet (fresh install, or a database that predates the feature).
 *
 * The result is memoized per request: each ID card page needs both settings,
 * and a print sheet renders the branding once per face.
 */
if (! function_exists('id_card_branding')) {
    function id_card_branding(): array
    {
        static $branding = null;

        if ($branding !== null) {
            return $branding;
        }

        $principal = school_principal();

        $branding = [
            'school_name'    => 'CAUAYAN SOUTH CENTRAL SCHOOL',
            'logo_url'       => base_url('LPHS2.png'),
            'tagline'        => 'DepEd — Cauayan City, Isabela',
            'address'        => 'Mabini Street, District I, Cauayan City, Isabela, Philippines',
            'principal_name' => $principal['name'],
            'principal_rank' => $principal['rank'],
        ];

        return $branding;
    }
}
