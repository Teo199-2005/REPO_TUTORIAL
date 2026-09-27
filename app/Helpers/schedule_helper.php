<?php

/**
 * Schedule conflict plumbing shared by the section schedule page
 * (Admin\Schedules) and the teacher schedule page (Admin\Teachers).
 *
 * Every writer of teacher_schedules used to hand-roll its own overlap query,
 * and each copy drifted slightly from the others, so a block rejected on one
 * page could be accepted on another. These helpers centralise the time maths
 * and the SQL fragments that resolve which item (subject or developmental
 * domain) a schedule row holds.
 */

if (!function_exists('schedule_normalize_time')) {
    /**
     * Normalise a submitted time to 'HH:MM:SS', or null when unusable.
     *
     * A native <input type="time"> posts 'HH:MM' (older browsers may post
     * 'HH:MM:SS'), while the columns are `time`. Overlap comparisons rely on
     * string comparison, which is only correct when every value has the same
     * fixed width — normalising here keeps that guarantee.
     */
    function schedule_normalize_time(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Accept H:MM, HH:MM and HH:MM:SS (24-hour clock only).
        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $matches)) {
            return null;
        }

        return sprintf(
            '%02d:%02d:%02d',
            (int) $matches[1],
            (int) $matches[2],
            isset($matches[3]) ? (int) $matches[3] : 0
        );
    }
}

if (!function_exists('schedule_times_overlap')) {
    /**
     * True when two time windows share at least one minute.
     *
     * Windows are half-open: 08:00-09:00 and 09:00-10:00 are ADJACENT and do
     * not overlap, so back-to-back blocks stay legal. A zero-length window
     * (start == end, e.g. the 00:00:00-00:00:00 teacher-assignment placeholder
     * rows) can never overlap anything, which keeps those rows out of every
     * conflict report without special-casing them at the call sites.
     */
    function schedule_times_overlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        $aStart = schedule_normalize_time($startA);
        $aEnd   = schedule_normalize_time($endA);
        $bStart = schedule_normalize_time($startB);
        $bEnd   = schedule_normalize_time($endB);

        if ($aStart === null || $aEnd === null || $bStart === null || $bEnd === null) {
            return false;
        }

        if ($aEnd <= $aStart || $bEnd <= $bStart) {
            return false; // zero-length (placeholder) rows occupy no time
        }

        return $aStart < $bEnd && $aEnd > $bStart;
    }
}

if (!function_exists('schedule_item_column')) {
    /**
     * The teacher_schedules column holding the item this section schedules.
     *
     * Non-numerical sections schedule developmental domains. Those must live
     * in domain_id: subject_id carries a FOREIGN KEY to subjects and 20 of the
     * 21 current domain ids collide with subject ids, so storing a domain
     * there would silently attach an unrelated subject. On a host whose DB
     * user cannot run DDL the column cannot be created, so fall back to
     * subject_id (such a host has no FK on that column, or the ALTER would
     * have worked).
     */
    function schedule_item_column(int $sectionId): string
    {
        if (! is_non_numerical_section($sectionId)) {
            return 'subject_id';
        }

        return schedule_domain_column_ready() ? 'domain_id' : 'subject_id';
    }
}

if (!function_exists('schedule_item_id_expr')) {
    /**
     * SQL expression resolving a schedule row's item id. Non-numerical
     * sections tolerate rows written before domain_id existed (domain id sits
     * in subject_id); for numerical rows domain_id is NULL so this returns
     * subject_id unchanged.
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
     * SELECT fragment resolving a schedule row's display name for conflict
     * messages.
     *
     * A row holds a subject (numerical sections) or a developmental domain
     * (non-numerical ones). Joining subjects alone left sub.subject_name NULL
     * for domain rows, so messages read "...occupied by <teacher> for  in
     * <section>" with the item name missing.
     */
    function schedule_item_name_select(): string
    {
        return schedule_domain_column_ready()
            ? 'COALESCE(sub.subject_name, sc.name) AS item_name'
            : 'sub.subject_name AS item_name';
    }
}

if (!function_exists('schedule_item_name_joins')) {
    /**
     * Joins feeding schedule_item_name_select().
     *
     * sned_categories is only joined when domain_id actually exists; on a host
     * that could not add the column the query must stay valid, so it degrades
     * to the previous subjects-only behaviour.
     */
    function schedule_item_name_joins(): string
    {
        $joins = 'LEFT JOIN subjects sub ON sub.id = ts.subject_id';

        if (schedule_domain_column_ready()) {
            $joins .= ' LEFT JOIN sned_categories sc ON sc.id = ts.domain_id';
        }

        return $joins;
    }
}

if (!function_exists('schedule_conflict_weekdays')) {
    /**
     * The days a timetable block may occupy.
     *
     * teacher_schedules.day_of_week also holds '' (legacy rows) and 'TBD'
     * (assignment placeholders written by the section page). Those rows carry
     * no timetable slot, so conflict matching — which filters on these days —
     * can never trip over them.
     */
    function schedule_conflict_weekdays(): array
    {
        return schedule_allowed_days();
    }
}

if (!function_exists('schedule_allowed_days')) {
    /**
     * Days accepted by the day_of_week ENUM column.
     *
     * MySQL runs without STRICT_TRANS_TABLES on this server, so an invalid
     * ENUM value is NOT rejected — it is silently stored as '' (empty enum),
     * producing schedule rows that no day tab can ever display. Both schedule
     * controllers validate against this list before writing.
     */
    function schedule_allowed_days(): array
    {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    }
}

if (!function_exists('schedule_day_list_sql')) {
    /**
     * schedule_allowed_days() as a quoted list for SQL IN (...) predicates.
     *
     * The day names come from a hard-coded list, never from request data, so
     * they are safe to inline. Kept in one place so every predicate that splits
     * teacher_schedules rows into "has a timetable slot" and "has none" agrees.
     */
    function schedule_day_list_sql(): string
    {
        return implode(', ', array_map(
            static fn (string $day): string => "'" . $day . "'",
            schedule_allowed_days()
        ));
    }
}

if (!function_exists('schedule_weekday_day_sql')) {
    /** True for rows that occupy a real timetable slot. */
    function schedule_weekday_day_sql(string $alias = 'ts'): string
    {
        return $alias . '.day_of_week IN (' . schedule_day_list_sql() . ')';
    }
}

if (!function_exists('schedule_assignment_day_sql')) {
    /**
     * True for the teacher-assignment rows written by the Sections page.
     *
     * Saving a teacher for a subject (Dashboard::assignSubjectTeacherOnly)
     * inserts a teacher_schedules row that carries the pair but no timetable
     * slot: the day is 'TBD' — or '' on a host whose day_of_week ENUM has not
     * gained TBD yet — and the window is 00:00:00-00:00:00. That row is the
     * record of WHO teaches the subject in the section, so the section page
     * reads it back to pre-select the Teacher field. Sharing the predicate keeps
     * the writer (Dashboard) and the reader (Schedules) from drifting apart.
     */
    function schedule_assignment_day_sql(string $alias = 'ts'): string
    {
        return $alias . '.day_of_week NOT IN (' . schedule_day_list_sql() . ')';
    }
}

if (!function_exists('schedule_room_max_length')) {
    /**
     * Mirrors the room varchar(50) column, so an over-long room is reported as
     * a clear validation error instead of being silently truncated.
     */
    function schedule_room_max_length(): int
    {
        return 50;
    }
}

if (!function_exists('schedule_validate_slot')) {
    /**
     * Validate and normalise one submitted timetable block.
     *
     * The single gate every write path (section page, batch save, teacher page)
     * runs its input through, so the same bad input cannot be accepted on one
     * page and rejected on another. Times come back normalised to 'HH:MM:SS',
     * which is what the overlap predicates rely on.
     *
     * @param array<string, mixed> $input section_id, subject_id (or domain id),
     *                                     teacher_id, day_of_week, start_time,
     *                                     end_time and room.
     *
     * @return array{ok: bool, error?: string, slot?: array<string, mixed>}
     */
    function schedule_validate_slot(array $input): array
    {
        $sectionId = (int) ($input['section_id'] ?? 0);
        $teacherId = (int) ($input['teacher_id'] ?? 0);
        $itemId    = (int) ($input['subject_id'] ?? 0);
        $day       = trim((string) ($input['day_of_week'] ?? ''));
        $room      = trim((string) ($input['room'] ?? ''));

        $start = schedule_normalize_time((string) ($input['start_time'] ?? ''));
        $end   = schedule_normalize_time((string) ($input['end_time'] ?? ''));

        if ($sectionId <= 0) {
            return ['ok' => false, 'error' => 'No section was selected for this block.'];
        }

        if ($itemId <= 0) {
            return ['ok' => false, 'error' => 'Select a subject (or developmental domain) for this block.'];
        }

        if ($teacherId <= 0) {
            return ['ok' => false, 'error' => 'Select a teacher for this block.'];
        }

        if (! in_array($day, schedule_allowed_days(), true)) {
            return ['ok' => false, 'error' => $day . ' is not a valid school day for a schedule block.'];
        }

        if ($start === null || $end === null) {
            return ['ok' => false, 'error' => 'Enter a valid start and end time (HH:MM).'];
        }

        if ($end <= $start) {
            return ['ok' => false, 'error' => 'The end time must be later than the start time.'];
        }

        if (strlen($room) > schedule_room_max_length()) {
            return [
                'ok'    => false,
                'error' => 'The room may not be longer than ' . schedule_room_max_length() . ' characters.',
            ];
        }

        return ['ok' => true, 'slot' => [
            'section_id'  => $sectionId,
            'teacher_id'  => $teacherId,
            'item_id'     => $itemId,
            'day_of_week' => $day,
            'start_time'  => $start,
            'end_time'    => $end,
            'room'        => $room,
        ]];
    }
}
