<?php

declare(strict_types=1);

if (! function_exists('portal_nav_item')) {
    /**
     * @return array{href:string,icon:string,label:string,badge?:string}
     */
    function portal_nav_item(string $href, string $icon, string $label, ?string $badge = null): array
    {
        $item = [
            'href'  => $href,
            'icon'  => $icon,
            'label' => $label,
        ];
        if ($badge !== null && $badge !== '') {
            $item['badge'] = $badge;
        }

        return $item;
    }
}

if (! function_exists('portal_nav_for_user')) {
    /**
     * Categorized sidebar navigation for the logged-in user.
     *
     * @return array{
     *   role: string,
     *   portal_label: string,
     *   dashboard_url: string,
     *   account_panel_href: string,
     *   notifications_href: string,
     *   sections: list<array{title: string, items: list<array{href:string,icon:string,label:string,badge?:string}>}>
     * }
     */
    function portal_nav_for_user(?object $user): array
    {
        $defaults = [
            'role'                 => 'student',
            'portal_label'         => 'Student Portal',
            'dashboard_url'        => base_url('student/dashboard'),
            'account_panel_href'   => base_url('student/profile'),
            'notifications_href'   => base_url('student/notifications'),
            'sections'             => portal_nav_student_sections(),
        ];

        if ($user === null) {
            return $defaults;
        }

        helper('admin_access');

        if (user_is_any_admin($user)) {
            return [
                'role'               => 'admin',
                'portal_label'       => $user->inGroup('admin_staff') ? 'Admin staff portal' : 'Admin portal',
                'dashboard_url'      => $user->inGroup('admin_staff')
                    ? (admin_staff_post_login_redirect_url((int) $user->id) ?? base_url('admin/dashboard'))
                    : base_url('admin/dashboard'),
                'account_panel_href' => base_url('admin/profile'),
                'notifications_href' => base_url('admin/profile'),
                'sections'           => portal_nav_admin_sections($user),
            ];
        }

        if ($user->inGroup('teacher')) {
            return [
                'role'               => 'teacher',
                'portal_label'       => 'Teacher Portal',
                'dashboard_url'      => base_url('teacher/dashboard'),
                'account_panel_href'   => base_url('teacher/profile'),
                'notifications_href' => base_url('teacher/announcements'),
                'sections'           => portal_nav_teacher_sections(),
            ];
        }

        if ($user->inGroup('parent')) {
            return [
                'role'               => 'parent',
                'portal_label'       => 'Parent Portal',
                'dashboard_url'      => base_url('parent/dashboard'),
                'account_panel_href'   => base_url('parent/dashboard'),
                'notifications_href' => base_url('parent/announcements'),
                'sections'           => portal_nav_parent_sections(),
            ];
        }

        if ($user->inGroup('student')) {
            return [
                'role'               => 'student',
                'portal_label'       => 'Student Portal',
                'dashboard_url'      => base_url('student/dashboard'),
                'account_panel_href' => base_url('student/profile'),
                'notifications_href' => base_url('student/notifications'),
                'sections'           => portal_nav_student_sections(),
            ];
        }

        return $defaults;
    }
}

if (! function_exists('portal_nav_student_sections')) {
    /**
     * @return list<array{title: string, items: list<array{href:string,icon:string,label:string,badge?:string}>}>
     */
    function portal_nav_student_sections(?array $student = null): array
    {
        // While the student has not completed BMI + profile photo + 2x2 ID
        // photo, My Profile is the only unlocked sidebar page — the Dashboard
        // is locked too (the server side gate lives in
        // App\Filters\StudentAccessFilter).
        $complete = $student === null ? true : student_profile_complete($student);
        $openHrefs = $student === null || $complete ? [] : [
            base_url('student/profile'),
        ];

        $sections = [
            [
                'title' => 'Overview',
                'items' => [
                    portal_nav_item(base_url('student/dashboard'), 'bi-house', 'Dashboard'),
                ],
            ],
            [
                'title' => 'Academics',
                'items' => [
                    portal_nav_item(base_url('student/grades'), 'bi-bar-chart-line', 'My Grades'),
                    portal_nav_item(base_url('student/schedule'), 'bi-calendar-event', 'Class Schedule'),
                    portal_nav_item(base_url('student/analytics'), 'bi-graph-up', 'My Analytics'),
                ],
            ],
            [
                'title' => 'Updates',
                'items' => [
                    portal_nav_item(base_url('student/announcements'), 'bi-megaphone', 'Announcements', 'student-announcements-count'),
                    portal_nav_item(base_url('student/materials'), 'bi-folder2-open', 'Materials'),
                ],
            ],
            [
                'title' => 'Account',
                'items' => [
                    portal_nav_item(base_url('student/id-cards'), 'bi-credit-card-2-front', 'ID Card'),
                    portal_nav_item(base_url('student/platform-rating'), 'bi-stars', 'Rate platform'),
                    portal_nav_item(base_url('student/profile'), 'bi-person-circle', 'Profile'),
                ],
            ],
        ];

        if (! $complete) {
            foreach ($sections as &$section) {
                foreach ($section['items'] as &$item) {
                    if (! in_array($item['href'], $openHrefs, true)) {
                        $item['locked'] = true;
                    }
                }
                unset($item);
            }
            unset($section);
        }

        return $sections;
    }
}

if (! function_exists('portal_nav_teacher_sections')) {
    /**
     * @return list<array{title: string, items: list<array{href:string,icon:string,label:string,badge?:string}>}>
     */
    function portal_nav_teacher_sections(): array
    {
        return [
            [
                'title' => 'Overview',
                'items' => [
                    portal_nav_item(base_url('teacher/dashboard'), 'bi-speedometer2', 'Dashboard'),
                ],
            ],
            [
                'title' => 'Classes',
                'items' => [
                    portal_nav_item(base_url('teacher/students'), 'bi-people-fill', 'My Students'),
                    portal_nav_item(base_url('teacher/sections'), 'bi-grid-3x3-gap', 'My Sections'),
                    portal_nav_item(base_url('teacher/grades'), 'bi-bar-chart-line', 'Enter Grades'),
                    portal_nav_item(base_url('teacher/attendance'), 'bi-calendar-check', 'Attendance'),
                    portal_nav_item(base_url('teacher/schedule'), 'bi-calendar-event', 'My Schedule', 'schedule-updates-count'),
                ],
            ],
            [
                'title' => 'Insights',
                'items' => [
                    portal_nav_item(base_url('teacher/analytics'), 'bi-graph-up', 'Analytics'),
                    portal_nav_item(base_url('teacher/announcements'), 'bi-megaphone', 'Announcements', 'teacher-announcements-count'),
                    portal_nav_item(base_url('teacher/materials'), 'bi-folder2-open', 'Materials'),
                ],
            ],
            [
                'title' => 'Account',
                'items' => [
                    portal_nav_item(base_url('teacher/platform-rating'), 'bi-stars', 'Rate platform'),
                    portal_nav_item(base_url('teacher/profile'), 'bi-person-circle', 'Profile'),
                ],
            ],
        ];
    }
}

if (! function_exists('portal_nav_parent_sections')) {
    /**
     * @return list<array{title: string, items: list<array{href:string,icon:string,label:string,badge?:string}>}>
     */
    function portal_nav_parent_sections(): array
    {
        return [
            [
                'title' => 'Overview',
                'items' => [
                    portal_nav_item(base_url('parent/dashboard'), 'bi-speedometer2', 'Dashboard'),
                ],
            ],
            [
                'title' => 'Family',
                'items' => [
                    portal_nav_item(base_url('parent/children'), 'bi-people', 'My Children'),
                ],
            ],
            [
                'title' => 'Updates',
                'items' => [
                    portal_nav_item(base_url('parent/announcements'), 'bi-megaphone', 'Announcements'),
                ],
            ],
        ];
    }
}

if (! function_exists('portal_nav_admin_category_map')) {
    /**
     * Maps admin page keys to sidebar categories (order preserved).
     *
     * @return array<string, string>
     */
    function portal_nav_admin_category_map(): array
    {
        return [
            'dashboard'            => 'Overview',
            'pending_applications' => 'Enrollment',
            'students'             => 'Enrollment',
            'teachers'             => 'Faculty',
            'sections'             => 'Academics',
            'schedules'            => 'Academics',
            'analytics'            => 'Academics',
            'student_nutrition'    => 'Academics',
            'announcements'        => 'Communication',
            'materials'            => 'Communication',
            'password_resets'      => 'Administration',
            'records'              => 'Administration',
            'id_cards'             => 'Administration',
            'settings'             => 'Administration',
            'landing_page'         => 'System',
            'childpro_gad'         => 'System',
            'programs_projects'    => 'System',
            'platform_ratings'     => 'System',
            'audit_log'            => 'Administration',
            'backups'              => 'Administration',
            'profile'              => 'Account',
        ];
    }
}

if (! function_exists('portal_nav_admin_sections')) {
    /**
     * @return list<array{title: string, items: list<array{href:string,icon:string,label:string,badge?:string}>}>
     */
    function portal_nav_admin_sections(object $user): array
    {
        $categoryOrder = [
            'Overview',
            'Enrollment',
            'Faculty',
            'Academics',
            'Communication',
            'Administration',
            'System',
            'Account',
        ];

        $shortLabels = [
            'pending_applications' => 'Pending Student',
            'sections'             => 'Sections',
            'student_nutrition'    => 'Nutrition / BMI',
            'announcements'        => 'Announcements',
            'materials'            => 'Materials',
            'password_resets'      => 'Password resets',
            'id_cards'             => 'ID cards',
            'platform_ratings'     => 'Feedback',
            'landing_page'         => 'Landing page',
            'childpro_gad'         => 'CHILDPRO / GAD',
            'programs_projects'    => 'Programs & Projects',
            'backups'              => 'Backup & Restore',
        ];

        $grouped = [];
        foreach ($categoryOrder as $title) {
            $grouped[$title] = [];
        }

        foreach (admin_portal_nav_definition() as $def) {
            if (! admin_staff_can_view_page($user, $def['page'])) {
                continue;
            }

            $category = portal_nav_admin_category_map()[$def['page']] ?? 'Administration';
            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }

            $label = $shortLabels[$def['page']] ?? $def['label'];
            $item  = portal_nav_item($def['href'], $def['icon'], $label, $def['badge'] ?? null);
            $grouped[$category][] = $item;
        }

        $sections = [];
        foreach ($categoryOrder as $title) {
            if ($grouped[$title] === []) {
                continue;
            }
            $sections[] = [
                'title' => $title,
                'items' => $grouped[$title],
            ];
        }

        return $sections;
    }
}
