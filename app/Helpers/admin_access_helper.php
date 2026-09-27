<?php

declare(strict_types=1);

if (! function_exists('admin_valid_page_keys')) {
    /**
     * Canonical admin portal page keys (checkboxes / authorization).
     *
     * @return list<string>
     */
    function admin_valid_page_keys(): array
    {
        return [
            'dashboard',
            'students',
            'teachers',
            'sections',
            'schedules',
            'analytics',
            'student_nutrition',
            'announcements',
            'materials',
            'password_resets',
            'settings',
            'landing_page',
            'childpro_gad',
            'programs_projects',
            'records',
            'id_cards',
            'platform_ratings',
            'pending_applications',
            'profile',
            'audit_log',
            'backups',
        ];
    }
}

if (! function_exists('admin_page_label')) {
    function admin_page_label(string $key): string
    {
        $labels = [
            'dashboard' => 'Dashboard',
            'students' => 'Students',
            'pending_applications' => 'Pending applications',
            'teachers' => 'Teachers',
            'sections' => 'Sections & subjects',
            'schedules' => 'Schedules',
            'analytics' => 'Analytics',
            'student_nutrition' => 'Student nutrition / BMI',
            'announcements' => 'Announcements',
            'materials' => 'School materials',
            'password_resets' => 'Password resets',
            'settings' => 'Settings',
            'landing_page' => 'Landing page',
            'childpro_gad' => 'CHILDPRO / GAD',
            'programs_projects' => 'Programs & Projects',
            'records' => 'Records',
            'id_cards' => 'Student ID cards',
            'platform_ratings' => 'Platform feedback',
            'profile' => 'Profile',
            'audit_log' => 'Activity log',
            'backups' => 'Backup & Restore',
        ];

        return $labels[$key] ?? $key;
    }
}

if (! function_exists('admin_page_icon')) {
    /** Bootstrap Icons class (without `bi ` prefix) for admin portal page keys. */
    function admin_page_icon(string $key): string
    {
        return match ($key) {
            'dashboard' => 'bi-speedometer2',
            'students' => 'bi-people-fill',
            'pending_applications' => 'bi-clock-history',
            'teachers' => 'bi-person-video3',
            'sections' => 'bi-grid-3x3-gap',
            'schedules' => 'bi-calendar-week',
            'analytics' => 'bi-graph-up-arrow',
            'student_nutrition' => 'bi-heart-pulse',
            'announcements' => 'bi-megaphone',
            'materials' => 'bi-journal-bookmark',
            'password_resets' => 'bi-key',
            'settings' => 'bi-gear',
            'landing_page' => 'bi-house-door',
            'childpro_gad' => 'bi-people',
            'programs_projects' => 'bi-folder2-open',
            'records' => 'bi-archive',
            'id_cards' => 'bi-person-badge',
            'platform_ratings' => 'bi-stars',
            'profile' => 'bi-person-circle',
            'audit_log' => 'bi-shield-check',
            'backups' => 'bi-database-check',
            default => 'bi-layout-text-window-reverse',
        };
    }
}

if (! function_exists('admin_landing_page_order')) {
    /**
     * Order used to pick default URL after login for admin_staff.
     *
     * @return list<string>
     */
    function admin_landing_page_order(): array
    {
        return admin_valid_page_keys();
    }
}

if (! function_exists('is_master_admin')) {
    function is_master_admin(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->inGroup('admin');
    }
}

if (! function_exists('is_admin_staff')) {
    function is_admin_staff(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->inGroup('admin_staff');
    }
}

if (! function_exists('is_any_admin')) {
    function is_any_admin(): bool
    {
        return is_master_admin() || is_admin_staff();
    }
}

if (! function_exists('user_is_any_admin')) {
    function user_is_any_admin(?object $user): bool
    {
        return $user !== null && ($user->inGroup('admin') || $user->inGroup('admin_staff'));
    }
}

if (! function_exists('admin_portal_nav_definition')) {
    /**
     * Full admin sidebar: each item includes a permission page key.
     *
     * @return list<array{page:string,href:string,icon:string,label:string,badge?:string}>
     */
    function admin_portal_nav_definition(): array
    {
        return [
            ['page' => 'dashboard', 'href' => base_url('admin/dashboard'), 'icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
            ['page' => 'pending_applications', 'href' => base_url('admin/students/pending'), 'icon' => 'bi-clock-history', 'label' => 'Pending Applications', 'badge' => 'pending-applications-count'],
            ['page' => 'students', 'href' => base_url('admin/students'), 'icon' => 'bi-people-fill', 'label' => 'Students'],
            ['page' => 'teachers', 'href' => base_url('admin/teachers'), 'icon' => 'bi-person-video3', 'label' => 'Teachers'],
            ['page' => 'teachers', 'href' => base_url('admin/teachers/pending'), 'icon' => 'bi-person-plus', 'label' => 'Pending Teacher', 'badge' => 'pending-teachers-count'],
            ['page' => 'sections', 'href' => base_url('admin/sections'), 'icon' => 'bi-grid-3x3-gap', 'label' => 'Sections & Subjects'],
            ['page' => 'schedules', 'href' => base_url('admin/schedules'), 'icon' => 'bi-calendar-week', 'label' => 'Schedules'],
            ['page' => 'analytics', 'href' => base_url('admin/analytics'), 'icon' => 'bi-graph-up', 'label' => 'Analytics'],
            ['page' => 'student_nutrition', 'href' => base_url('admin/student-nutrition'), 'icon' => 'bi-heart-pulse', 'label' => 'Student nutrition / BMI'],
            ['page' => 'announcements', 'href' => base_url('admin/announcements'), 'icon' => 'bi-megaphone', 'label' => 'Announcements & Notifications', 'badge' => 'announcements-count'],
            ['page' => 'materials', 'href' => base_url('admin/materials'), 'icon' => 'bi-journal-bookmark', 'label' => 'School Materials'],
            ['page' => 'password_resets', 'href' => base_url('admin/password-resets'), 'icon' => 'bi-key', 'label' => 'Password Resets', 'badge' => 'password-resets-count'],
            ['page' => 'records', 'href' => base_url('admin/records'), 'icon' => 'bi-archive', 'label' => 'Records'],
            ['page' => 'id_cards', 'href' => base_url('admin/id-cards'), 'icon' => 'bi-person-badge', 'label' => 'Student ID cards'],
            ['page' => 'settings', 'href' => base_url('admin/settings'), 'icon' => 'bi-gear', 'label' => 'Settings'],
            ['page' => 'landing_page', 'href' => base_url('admin/landing-page'), 'icon' => 'bi-house-door', 'label' => 'Landing Page'],
            ['page' => 'childpro_gad', 'href' => base_url('admin/childpro-gad'), 'icon' => 'bi-people', 'label' => 'CHILDPRO / GAD'],
            ['page' => 'programs_projects', 'href' => base_url('admin/programs-projects'), 'icon' => 'bi-folder2-open', 'label' => 'Programs & Projects'],
            ['page' => 'platform_ratings', 'href' => base_url('admin/platform-ratings'), 'icon' => 'bi-stars', 'label' => 'Platform feedback'],
            ['page' => 'audit_log', 'href' => base_url('admin/audit-log'), 'icon' => 'bi-shield-check', 'label' => 'Activity log'],
            ['page' => 'backups', 'href' => base_url('admin/backups'), 'icon' => 'bi-database-check', 'label' => 'Backup & Restore'],
            ['page' => 'profile', 'href' => base_url('admin/profile'), 'icon' => 'bi-person-circle', 'label' => 'Profile'],
        ];
    }
}

if (! function_exists('admin_staff_pages_from_db')) {
    /**
     * @return list<string>
     */
    function admin_staff_pages_from_db(int $userId): array
    {
        $row = \Config\Database::connect()->table('users')
            ->select('admin_allowed_pages')
            ->where('id', $userId)
            ->get()
            ->getRowArray();

        if ($row === null || $row['admin_allowed_pages'] === null || $row['admin_allowed_pages'] === '') {
            return [];
        }

        $decoded = json_decode($row['admin_allowed_pages'], true);

        return is_array($decoded) ? array_values(array_intersect(admin_valid_page_keys(), $decoded)) : [];
    }
}

if (! function_exists('admin_save_allowed_pages')) {
    /**
     * @param list<string> $pages
     */
    function admin_save_allowed_pages(int $userId, array $pages): void
    {
        $clean = array_values(array_intersect(admin_valid_page_keys(), $pages));
        \Config\Database::connect()->table('users')->where('id', $userId)->update([
            'admin_allowed_pages' => json_encode($clean),
        ]);
    }
}

if (! function_exists('admin_staff_has_page')) {
    function admin_staff_has_page(int $userId, string $pageKey): bool
    {
        return in_array($pageKey, admin_staff_pages_from_db($userId), true);
    }
}

if (! function_exists('admin_page_key_from_path')) {
    /**
     * Map request path (e.g. admin/students/pending) to a canonical page key.
     * Returns null if the area is unknown (admin_staff will be denied).
     */
    function admin_page_key_from_path(string $path): ?string
    {
        $path = trim($path, '/');
        $segments = $path === '' ? [] : explode('/', $path);
        if (($segments[0] ?? '') !== 'admin') {
            return null;
        }

        $seg = $segments[1] ?? 'dashboard';

        if ($seg === 'students') {
            $action = $segments[2] ?? '';
            if ($action === 'pending' || $action === 'pending-count') {
                return 'pending_applications';
            }

            return 'students';
        }

        if ($seg === 'subjects') {
            return 'sections';
        }

        // Developmental domains (SNED) are managed from the Settings page and
        // from the per-section Subjects button, so staff need the permission of
        // the page hosting that UI. Without this mapping the key resolved to
        // null and every sned/* request was denied for admin_staff.
        if ($seg === 'sned') {
            return 'settings';
        }

        if (in_array($seg, ['fix-enrollment-counts'], true)) {
            return 'dashboard';
        }

        $key = str_replace('-', '_', $seg);

        return in_array($key, admin_valid_page_keys(), true) ? $key : null;
    }
}

if (! function_exists('admin_staff_can_view_page')) {
    function admin_staff_can_view_page(object $user, string $pageKey): bool
    {
        if ($user->inGroup('admin')) {
            return true;
        }
        if (! $user->inGroup('admin_staff')) {
            return false;
        }

        return admin_staff_has_page((int) $user->id, $pageKey);
    }
}

if (! function_exists('admin_page_url')) {
    function admin_page_url(string $pageKey): string
    {
        $map = [
            'dashboard' => 'admin/dashboard',
            'students' => 'admin/students',
            'pending_applications' => 'admin/students/pending',
            'teachers' => 'admin/teachers',
            'sections' => 'admin/sections',
            'schedules' => 'admin/schedules',
            'analytics' => 'admin/analytics',
            'announcements' => 'admin/announcements',
            'materials' => 'admin/materials',
            'password_resets' => 'admin/password-resets',
            'settings' => 'admin/settings',
            'landing_page' => 'admin/landing-page',
            'childpro_gad' => 'admin/childpro-gad',
            'programs_projects' => 'admin/programs-projects',
            'records' => 'admin/records',
            'id_cards' => 'admin/id-cards',
            'platform_ratings' => 'admin/platform-ratings',
            'audit_log' => 'admin/audit-log',
            'backups' => 'admin/backups',
            'profile' => 'admin/profile',
        ];

        $uri = $map[$pageKey] ?? 'admin/dashboard';

        return base_url($uri);
    }
}

if (! function_exists('admin_staff_post_login_redirect_url')) {
    /**
     * First allowed admin URL for admin_staff, or null if none assigned.
     */
    function admin_staff_post_login_redirect_url(int $userId): ?string
    {
        $pages = admin_staff_pages_from_db($userId);
        if ($pages === []) {
            return null;
        }
        foreach (admin_landing_page_order() as $key) {
            if (in_array($key, $pages, true)) {
                return admin_page_url($key);
            }
        }

        return admin_page_url($pages[0]);
    }
}
