<?php

if (!function_exists('get_current_school_year')) {
    function get_current_school_year(): string
    {
        try {
            $db = \Config\Database::connect();
            $setting = $db->table('system_settings')
                ->where('setting_key', 'current_school_year')
                ->get()
                ->getRowArray();
            
            if ($setting && !empty($setting['setting_value'])) {
                return $setting['setting_value'];
            }
        } catch (\Exception $e) {
            // Fall back to calculated year if database error
        }
        
        // Fallback: calculate based on current date
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        
        if ($currentMonth >= 6) {
            return $currentYear . '-' . ($currentYear + 1);
        } else {
            return ($currentYear - 1) . '-' . $currentYear;
        }
    }
}

if (!function_exists('get_current_term')) {
    function get_current_term(): int
    {
        try {
            $db = \Config\Database::connect();
            $setting = $db->table('system_settings')
                ->where('setting_key', 'current_term')
                ->get()
                ->getRowArray();

            if ($setting && !empty($setting['setting_value'])) {
                return (int) $setting['setting_value'];
            }
        } catch (\Exception $e) {
            // Fall back to calculated term
        }

        // Fallback: calculate based on current month.
        // Elementary term layout: Jun-Sep = Term 1, Oct-Jan = Term 2, Feb-May = Term 3.
        $month = (int) date('n');
        if ($month >= 6 && $month <= 9) return 1;
        if ($month >= 10 || $month <= 1) return 2;
        return 3;
    }
}

if (! function_exists('previous_school_year_choices')) {
    /**
     * School years a transferee could be coming from: the running one plus the
     * previous ten, newest first. A transferee may have left school a while
     * ago, so this is wider than school_year_choices() (which is the only two
     * years an administrator may pick for the current term).
     *
     * @param \DateTimeImmutable|null $today Injectable for tests; defaults to today
     *
     * @return list<string> YYYY-YYYY values, current one first
     */
    function previous_school_year_choices(?\DateTimeImmutable $today = null): array
    {
        $today = $today ?? new \DateTimeImmutable('today');

        $start = (int) $today->format('Y');
        if ((int) $today->format('n') < 6) {
            $start -= 1;
        }

        $years = [];
        for ($back = 0; $back <= 10; $back++) {
            $from = $start - $back;
            $years[] = $from . '-' . ($from + 1);
        }

        return $years;
    }
}

if (! function_exists('school_year_choices')) {
    /**
     * The only two school years an administrator may pick: the running one and
     * the one before it, both derived from today's date (the Philippine school
     * year opens in June). Example for 2026: ['2026-2027', '2025-2026'].
     *
     * @param \DateTimeImmutable|null $today Injectable for tests; defaults to today
     *
     * @return list<string> Exactly two YYYY-YYYY values, current one first
     */
    function school_year_choices(?\DateTimeImmutable $today = null): array
    {
        $today = $today ?? new \DateTimeImmutable('today');

        $start = (int) $today->format('Y');
        if ((int) $today->format('n') < 6) {
            // January to May: the year that opened last June is still running
            // (mirrors the fallback in get_current_school_year()).
            $start -= 1;
        }

        return [
            $start . '-' . ($start + 1),
            ($start - 1) . '-' . $start,
        ];
    }
}

