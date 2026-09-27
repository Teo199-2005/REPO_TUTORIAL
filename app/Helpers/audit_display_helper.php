<?php

declare(strict_types=1);

/**
 * Plain-English, icon-aware labels for the activity (audit) log.
 *
 * The activity log stores machine codes (`auth.login`, `system.backup_created`,
 * `db_backup`, `::1`) because they are stable identifiers for developers and for
 * the CSV export. Almost nobody reading the screen is a developer, though, so
 * every view that renders a log row must go through this helper to turn those
 * codes into a readable label, a Bootstrap icon and a colour tone.
 *
 * Design rules:
 *  - Display only. Nothing here writes to the database; the log is append-only.
 *  - Never fail. An unknown code (a new feature ships before this map is
 *    updated) must still render as a sensible, humanised label instead of
 *    throwing or showing a raw `system.foo_bar`.
 *  - Tone is semantic and drives colour, so the admin can spot problems by
 *    scanning one column instead of reading every row.
 *
 * The action map is split across three functions purely to keep each function
 * readable; audit_display_action_map() merges the parts.
 */

/**
 * @return array<string, array{label:string,icon:string,tone:string,help:string}>
 */
function audit_display_action_map(): array
{
    static $map;

    if ($map === null) {
        $map = audit_display_action_map_part_one();
        $map = audit_display_action_map_part_two($map);
        $map = audit_display_action_map_part_three($map);

        // The table above is written as a compact [label, icon, tone, help]
        // list so it stays readable; expose it in the associative shape the
        // rest of the app (and the docblocks) expect.
        $map = array_map(
            static fn (array $entry): array => [
                'label' => (string) ($entry['label'] ?? $entry[0] ?? ''),
                'icon'  => (string) ($entry['icon'] ?? $entry[1] ?? 'bi-question-circle'),
                'tone'  => (string) ($entry['tone'] ?? $entry[2] ?? 'muted'),
                'help'  => (string) ($entry['help'] ?? $entry[3] ?? ''),
            ],
            $map
        );
    }

    return $map;
}

/**
 * @return array<string, array{label:string,icon:string,tone:string,help:string}>
 */
function audit_display_action_map_part_one(): array
{
    return [
        // --- System / technical ---------------------------------------------
        'http.request' => ['Request submitted', 'bi-arrow-left-right', 'muted', 'Automatic entry written whenever a button or form is used. It records the technical details of the request.'],
        'system.backup_created' => ['Database backup created', 'bi-database-check', 'success', 'A copy of the whole database was saved so the data can be recovered later.'],
        'system.backup_verified' => ['Backup checked', 'bi-patch-check', 'success', 'A backup file was checked and found to be readable and complete.'],
        'system.backup_downloaded' => ['Backup downloaded', 'bi-download', 'info', 'A backup file was saved to the device that requested it.'],
        'system.backup_restored' => ['Database restored from backup', 'bi-cloud-arrow-down', 'warning', 'The live database was replaced with the contents of a backup file.'],
        'system.backup_rotated' => ['Old backups cleared', 'bi-hdd', 'info', 'Backups beyond the retention limit were removed to free up disk space.'],
        'system.backup_deleted' => ['Database backup deleted', 'bi-trash', 'danger', 'A backup file was permanently removed.'],
        'system.backup_schedule_updated' => ['Automatic backup schedule changed', 'bi-clock-history', 'info', 'How often the system makes a backup on its own was changed.'],
        'system.audit_exported' => ['Activity log exported', 'bi-file-earmark-spreadsheet', 'info', 'A copy of the activity log was downloaded as a spreadsheet file.'],
        'system.audit_verified' => ['Activity log checked for tampering', 'bi-fingerprint', 'success', 'The system re-checked every entry to prove none were secretly changed or deleted.'],
        'system.retention_purge' => ['Old activity entries removed', 'bi-hdd', 'warning', 'Entries older than the retention period were permanently removed.'],

        // --- Signing in / out -------------------------------------------------
        'auth.login' => ['Signed in', 'bi-box-arrow-in-right', 'success', 'The person opened their account successfully.'],
        'auth.logout' => ['Signed out', 'bi-door-open', 'muted', 'The person closed their account on this device.'],
        'auth.login_failed' => ['Sign-in attempt failed', 'bi-x-circle', 'danger', 'The email and password did not match an account.'],
        'auth.login_blocked' => ['Sign-in blocked', 'bi-slash-circle', 'warning', 'The sign-in was stopped for security reasons, such as too many failed tries.'],

        // --- Account & password ------------------------------------------------
        'account.registered' => ['Account created', 'bi-person-plus', 'success', 'A new portal account was registered.'],
        'account.password_changed' => ['Password changed', 'bi-key', 'warning', 'The password of an account was changed.'],
        'account.password_change_failed' => ['Password change failed', 'bi-x-circle', 'danger', 'An attempt to change a password did not succeed.'],
        'account.password_reset_approved' => ['Password reset approved', 'bi-check-circle', 'success', 'A requested password reset was approved and carried out.'],
        'account.password_reset_rejected' => ['Password reset declined', 'bi-x-circle', 'warning', 'A requested password reset was turned down.'],
        'account.password_reset_by_admin' => ['Password reset by administrator', 'bi-person-gear', 'warning', "An administrator reset the password of someone else's account."],
    ];
}

/**
 * @param array<string, array{label:string,icon:string,tone:string,help:string}> $map
 *
 * @return array<string, array{label:string,icon:string,tone:string,help:string}>
 */
function audit_display_action_map_part_two(array $map): array
{
    return $map + [
        // --- Staff access & permissions ---------------------------------------
        'role.account_created' => ['Staff account created', 'bi-person-plus', 'success', 'A new staff account with administrator access was added.'],
        'role.staff_deleted' => ['Staff account removed', 'bi-person-x', 'danger', 'A staff account was removed from the system.'],
        'role.staff_pages_updated' => ['Staff page access updated', 'bi-person-gear', 'warning', 'The list of pages a staff member is allowed to open was changed.'],

        // --- Students -----------------------------------------------------------
        'student.created' => ['Student record added', 'bi-person-plus', 'success', 'A new student record was created.'],
        'student.updated' => ['Student record updated', 'bi-pencil-square', 'info', 'Information on a student record was edited.'],
        'student.deleted' => ['Student record deleted', 'bi-trash', 'danger', 'A student record was permanently deleted.'],
        'student.archived' => ['Student archived', 'bi-archive', 'info', 'A student was moved out of the active list but the record was kept.'],
        'student.restored' => ['Student restored', 'bi-arrow-counterclockwise', 'success', 'A previously archived student was returned to the active list.'],
        'student.approved' => ['Student application approved', 'bi-person-check', 'success', 'A pending student application was accepted and enrolled.'],
        'student.rejected' => ['Student application declined', 'bi-person-x', 'warning', 'A pending student application was turned down.'],

        // --- Teachers -------------------------------------------------------------
        'teacher.created' => ['Teacher record added', 'bi-person-plus', 'success', 'A new teacher record was created.'],
        'teacher.updated' => ['Teacher record updated', 'bi-pencil-square', 'info', 'Information on a teacher record was edited.'],
        'teacher.deleted' => ['Teacher record deleted', 'bi-trash', 'danger', 'A teacher record was permanently deleted.'],
        'teacher.approved' => ['Teacher application approved', 'bi-person-check', 'success', 'A pending teacher application was accepted.'],
        'teacher.rejected' => ['Teacher application declined', 'bi-person-x', 'warning', 'A pending teacher application was turned down.'],
        'teacher.schedule_saved' => ['Teaching load saved', 'bi-calendar-week', 'success', 'The subjects or sections assigned to a teacher were saved.'],
    ];
}

/**
 * @param array<string, array{label:string,icon:string,tone:string,help:string}> $map
 *
 * @return array<string, array{label:string,icon:string,tone:string,help:string}>
 */
function audit_display_action_map_part_three(array $map): array
{
    return $map + [
        // --- Announcements & materials -------------------------------------------
        'announcement.created' => ['Announcement posted', 'bi-megaphone', 'success', 'A new announcement was published.'],
        'announcement.updated' => ['Announcement edited', 'bi-pencil-square', 'info', 'An existing announcement was edited.'],
        'announcement.deleted' => ['Announcement deleted', 'bi-trash', 'danger', 'An announcement was removed.'],
        'material.uploaded' => ['Material uploaded', 'bi-cloud-arrow-down', 'success', 'A file was added to the school materials.'],
        'material.updated' => ['Material updated', 'bi-pencil-square', 'info', 'The details or file of a material were changed.'],
        'material.deleted' => ['Material deleted', 'bi-trash', 'danger', 'A file was removed from the school materials.'],

        // --- Academics -------------------------------------------------------------
        'grade.saved' => ['Grades saved', 'bi-clipboard-check', 'success', 'Grades were entered or updated and saved.'],
        'schedule.created' => ['Schedule created', 'bi-calendar-week', 'success', 'A class schedule was created.'],
        'schedule.batch_saved' => ['Schedules saved', 'bi-calendar-week', 'success', 'A number of class schedules were saved at once.'],
        'schedule.deleted' => ['Schedule deleted', 'bi-trash', 'danger', 'A class schedule was removed.'],
        'profile.updated' => ['Profile updated', 'bi-person-circle', 'info', "Information on a user's own profile was edited."],
        'records.archived' => ['Record archived', 'bi-archive', 'info', 'A record was moved into storage. The record itself was kept.'],

        // --- Settings ----------------------------------------------------------------
        'settings.principal_updated' => ['Principal details updated', 'bi-person-badge', 'info', "The school principal's information was changed."],
        'settings.grading_toggled' => ['Grading turned on or off', 'bi-toggles', 'info', 'The grading feature was switched on or switched off.'],
        'settings.grading_type_changed' => ['Grading type changed', 'bi-toggles', 'info', 'The kind of grading used across the school was changed.'],
        'settings.registration_toggled' => ['Registration opened or closed', 'bi-toggles', 'info', 'New student registrations were opened or closed.'],
        'settings.term_updated' => ['School term updated', 'bi-calendar-week', 'info', 'The active term (e.g. First, Second, Third) was changed.'],
        'settings.school_year_updated' => ['School year updated', 'bi-calendar-week', 'info', 'The active school year was changed.'],
        'settings.landing_page_updated' => ['Landing page updated', 'bi-house-door', 'info', 'The public front page of the website was changed.'],
        'settings.childpro_gad_updated' => ['CHILDPRO / GAD updated', 'bi-people', 'info', 'The CHILDPRO / GAD page content was changed.'],
        'settings.programs_projects_updated' => ['Programs & Projects updated', 'bi-folder2-open', 'info', 'The Programs & Projects page content was changed.'],
        'settings.posters_updated' => ['Posters updated', 'bi-images', 'info', 'The posters shown on the website were changed.'],
        'settings.welcome_modal_updated' => ['Welcome modal updated', 'bi-stars', 'info', 'The welcome shown on first login was changed.'],
        'settings.personnel_edit_toggled' => ['Personnel editing turned on or off', 'bi-person-gear', 'info', 'Whether staff can edit personnel information was switched on or off.'],
    ];
}

/**
 * Humanised fallback label for a code that is not in the map.
 *
 * `system.backup_created` -> "Backup created", `foo.bar` -> "Bar". Keeps a
 * newly shipped action readable before this map is updated.
 */
function audit_display_fallback_label(string $action): string
{
    $parts = explode('.', trim($action));
    $verb  = ucfirst(str_replace(['_', '-'], ' ', (string) array_pop($parts)));

    if ($parts === []) {
        return $verb;
    }

    $subject = ucfirst(str_replace(['_', '-'], ' ', (string) array_pop($parts)));

    return $subject . ' ' . lcfirst($verb);
}

/**
 * Readable label, icon, tone and long help for one action code.
 *
 * @return array{label:string,icon:string,tone:string,help:string}
 */
function audit_display_action(string $action): array
{
    $action = trim((string) $action);
    $known  = audit_display_action_map()[$action] ?? null;

    if ($known !== null) {
        return $known;
    }

    return [
        'label' => $action === '' ? 'Unknown activity' : audit_display_fallback_label($action),
        'icon'  => 'bi-question-circle',
        'tone'  => 'muted',
        'help'  => 'Automatic system entry. No further explanation is available for this activity yet.',
    ];
}

/**
 * @return array{label:string,icon:string,tone:string}
 */
function audit_display_category(string $category): array
{
    return match ((string) $category) {
        'auth'     => ['label' => 'Signing in & out', 'icon' => 'bi-box-arrow-in-right', 'tone' => 'info'],
        'account'  => ['label' => 'Accounts & passwords', 'icon' => 'bi-key', 'tone' => 'info'],
        'role'     => ['label' => 'Staff access', 'icon' => 'bi-person-gear', 'tone' => 'warning'],
        'data'     => ['label' => 'School records', 'icon' => 'bi-people', 'tone' => 'info'],
        'settings' => ['label' => 'Settings changed', 'icon' => 'bi-gear', 'tone' => 'info'],
        'system'   => ['label' => 'System & backups', 'icon' => 'bi-hdd', 'tone' => 'muted'],
        default    => ['label' => (string) $category, 'icon' => 'bi-tag', 'tone' => 'muted'],
    };
}

/**
 * @return array{label:string,icon:string,tone:string,help:string}
 */
function audit_display_status(string $status): array
{
    return match ((string) $status) {
        'success' => ['label' => 'Success', 'icon' => 'bi-check-circle', 'tone' => 'success', 'help' => 'The activity finished normally.'],
        'failure' => ['label' => 'Failed', 'icon' => 'bi-x-circle', 'tone' => 'danger', 'help' => 'The activity did not finish. Check the details if this looks unexpected.'],
        'blocked' => ['label' => 'Blocked', 'icon' => 'bi-slash-circle', 'tone' => 'warning', 'help' => 'The activity was stopped on purpose, usually to protect the account or data.'],
        default   => ['label' => ucfirst((string) $status), 'icon' => 'bi-question-circle', 'tone' => 'muted', 'help' => 'Outcome of this activity.'],
    };
}

/**
 * @return array{label:string,icon:string}
 */
function audit_display_resource(string $type): array
{
    return match ((string) $type) {
        'announcement'           => ['label' => 'Announcement', 'icon' => 'bi-megaphone'],
        'db_backup'              => ['label' => 'Database backup', 'icon' => 'bi-database'],
        'grade'                  => ['label' => 'Grade', 'icon' => 'bi-clipboard-check'],
        'material'               => ['label' => 'Material', 'icon' => 'bi-journal-bookmark'],
        'password_reset_request' => ['label' => 'Password reset request', 'icon' => 'bi-key'],
        'record'                 => ['label' => 'Record', 'icon' => 'bi-archive'],
        'schedule'               => ['label' => 'Schedule', 'icon' => 'bi-calendar-week'],
        'section'                => ['label' => 'Section', 'icon' => 'bi-grid-3x3-gap'],
        'setting'                => ['label' => 'Setting', 'icon' => 'bi-gear'],
        'student'                => ['label' => 'Student', 'icon' => 'bi-mortarboard'],
        'teacher'                => ['label' => 'Teacher', 'icon' => 'bi-person-video3'],
        'user'                   => ['label' => 'User account', 'icon' => 'bi-person-circle'],
        default                  => ['label' => ucfirst(str_replace(['_', '-'], ' ', (string) $type)), 'icon' => 'bi-box'],
    };
}

/**
 * @return array{label:string,icon:string}
 */
function audit_display_role(string $role): array
{
    return match ((string) $role) {
        'superadmin'  => ['label' => 'Super administrator', 'icon' => 'bi-shield-lock'],
        'admin'       => ['label' => 'Administrator', 'icon' => 'bi-shield-check'],
        'admin_staff' => ['label' => 'Admin staff', 'icon' => 'bi-person-badge'],
        'teacher'     => ['label' => 'Teacher', 'icon' => 'bi-person-video3'],
        'student'     => ['label' => 'Student', 'icon' => 'bi-mortarboard'],
        'parent'      => ['label' => 'Parent', 'icon' => 'bi-people'],
        default       => ['label' => ucfirst((string) $role), 'icon' => 'bi-person-circle'],
    };
}


/**
 * Turn a raw IP address into something an administrator recognises.
 *
 * `::1`/`127.0.0.1` is the machine itself, private ranges are the school's own
 * network, and anything else reached the system over the internet.
 *
 * @return array{label:string,icon:string,help:string}
 */
function audit_display_ip(string $ip): array
{
    $ip = trim((string) $ip);

    if ($ip === '') {
        return ['label' => 'Not recorded', 'icon' => 'bi-dash', 'help' => 'No network address was recorded for this entry.'];
    }

    if ($ip === '::1' || $ip === '0:0:0:0:0:0:0:1' || str_starts_with($ip, '127.')) {
        return ['label' => 'This device', 'icon' => 'bi-laptop', 'help' => 'The activity came from the same computer that is viewing this page.'];
    }

    if (str_starts_with($ip, '10.')
        || str_starts_with($ip, '192.168.')
        || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip) === 1
        || preg_match('/^f[cd][0-9a-f]{2}:/i', $ip) === 1) {
        return ['label' => 'School network', 'icon' => 'bi-router', 'help' => 'The activity came from a computer inside the school network.'];
    }

    return ['label' => 'Internet connection', 'icon' => 'bi-globe2', 'help' => 'The activity came from a computer outside the school network.'];
}

/**
 * Plain-English name for the screen a request was made to.
 */
function audit_display_route(string $route): string
{
    $route = trim((string) $route, '/');

    if ($route === '') {
        return 'Unknown screen';
    }

    // Reuse the canonical admin page names so the log and the sidebar agree.
    if (str_starts_with($route, 'admin') && function_exists('admin_page_key_from_path')) {
        $key = admin_page_key_from_path($route);
        if ($key !== null) {
            return admin_page_label($key);
        }
    }

    $segments = explode('/', $route);

    return ucfirst(str_replace(['-', '_'], ' ', (string) end($segments)));
}

/**
 * Turn the raw technical description of a log row into a readable sentence.
 *
 * `http.request` rows carry "POST /admin/backups/create -> HTTP 200", which
 * means nothing to a school administrator. This rewrites those and leaves the
 * already-human descriptions ("Signed in", ...) untouched.
 *
 * @param array<string, mixed> $row
 */
function audit_display_description(array $row): string
{
    $description = trim((string) ($row['description'] ?? ''));

    if ((string) ($row['action'] ?? '') !== 'http.request') {
        return $description;
    }

    $method = strtoupper(trim((string) ($row['http_method'] ?? '')));
    $page   = audit_display_route((string) ($row['route'] ?? ''));
    $actor  = trim((string) ($row['actor_name'] ?? ''));

    $verb = match ($method) {
        'POST'   => 'submitted a form on the',
        'PUT'    => 'updated something on the',
        'PATCH'  => 'edited something on the',
        'DELETE' => 'deleted something on the',
        default  => 'used the',
    };

    $sentence = $actor !== ''
        ? $actor . ' ' . $verb . ' ' . $page . ' screen'
        : 'The ' . $page . ' screen was used' . ($method !== '' ? ' (' . $method . ')' : '');

    return rtrim($sentence) . '.';
}

/**
 * Distinct action codes for the filter dropdown, grouped by category and
 * labelled in plain English.
 *
 * The array *values* stay as the raw action code, so the submitted filter and
 * therefore the database query are completely unchanged.
 *
 * @param list<string> $actions
 *
 * @return array<string, list<array{value:string,label:string,icon:string,tone:string}>>
 */
function audit_display_grouped_actions(array $actions): array
{
    $grouped = [];

    foreach ($actions as $action) {
        $action = trim((string) $action);

        if ($action === '') {
            continue;
        }

        $display   = audit_display_action($action);
        $prefix    = str_contains($action, '.') ? (string) strstr($action, '.', true) : 'other';
        $grouped[$prefix][] = [
            'value' => $action,
            'label' => $display['label'],
            'icon'  => $display['icon'],
            'tone'  => $display['tone'],
        ];
    }

    $order = [
        'auth' => 1, 'account' => 2, 'role' => 3, 'student' => 4, 'teacher' => 5,
        'announcement' => 6, 'material' => 7, 'grade' => 8, 'schedule' => 9,
        'profile' => 10, 'records' => 11, 'settings' => 12, 'system' => 13, 'http' => 99,
    ];

    uksort($grouped, static fn (string $a, string $b): int => [$order[$a] ?? 50, $a] <=> [$order[$b] ?? 50, $b]);

    return $grouped;
}
