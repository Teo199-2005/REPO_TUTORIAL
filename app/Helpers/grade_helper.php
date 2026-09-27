<?php

/**
 * Numeric-grade rules shared by the Enter Grades page, its controller and the
 * grades table itself.
 */

if (! function_exists('min_report_card_grade')) {
    /**
     * Lowest numeric grade a report card may ever show.
     *
     * Teachers type grades freely on the Enter Grades page, but nothing below
     * this value is stored: a posted 44 or 59 is saved as 60, so every stored
     * grade - and the term averages and final grades computed from it - stays
     * on the report card scale.
     */
    function min_report_card_grade(): float
    {
        return 60.0;
    }
}

if (! function_exists('max_report_card_grade')) {
    /**
     * Highest numeric grade on the report card scale.
     */
    function max_report_card_grade(): float
    {
        return 100.0;
    }
}

if (! function_exists('clamp_report_card_grade')) {
    /**
     * Pull a posted grade into the report card range [60, 100].
     *
     * Returns null when there is no usable number - a blank field, null, a
     * grading symbol such as 'P' or 'NO/NA', or free text - so callers can tell
     * "nothing was entered" apart from "something low was entered". Decimals
     * survive (78.5 stays 78.5) while 44 and 59 become 60, and anything above
     * 100 becomes 100.
     *
     * @param mixed $value Raw posted grade (string, float or symbol).
     */
    function clamp_report_card_grade($value): ?float
    {
        if ($value === null || $value === '' || is_bool($value) || ! is_numeric($value)) {
            return null;
        }

        $grade = (float) $value;

        if ($grade < min_report_card_grade()) {
            return min_report_card_grade();
        }

        if ($grade > max_report_card_grade()) {
            return max_report_card_grade();
        }

        return $grade;
    }
}
