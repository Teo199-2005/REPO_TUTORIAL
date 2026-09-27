<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\StudentModel;
use App\Models\TeacherModel;
// use App\Models\UserModel;
use App\Models\AnnouncementModel;
use App\Models\SectionModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $studentModel = new StudentModel();
        $teacherModel = new TeacherModel();
        // $userModel = new UserModel();
        $announcementModel = new AnnouncementModel();
        
        $selectedYear = $this->request->getGet('year') ?? date('Y');
        // Get current term from system settings (single source of truth).
        // The school year is already read from the database via
        // get_current_school_year(); the term must behave the same way so an
        // administrator's change on the Settings page (which writes to the DB
        // only) is reflected immediately everywhere. A per-browser session
        // value goes stale as soon as any other browser/device updates the
        // setting, so it is never used as a term source.
        helper('school_year');
        $currentTerm = get_current_term();
        
        // Get enrollment by grade for selected school year.
        // Use the same school year format as the sections page for consistency.
        helper('school_year');
        $schoolYear = get_current_school_year();
        $db = \Config\Database::connect();
        helper('grade_level');
        $enrollmentByGrade = [];
        foreach (grade_level_options() as $grade) {
            $row = $db->query("
                SELECT COUNT(*) AS total
                FROM students st
                LEFT JOIN sections s ON s.id = st.section_id
                WHERE st.grade_level = ?
                  AND st.enrollment_status = 'enrolled'
                  AND st.deleted_at IS NULL
                  AND (
                        (st.section_id IS NOT NULL AND s.school_year = ?)
                        OR
                        (st.section_id IS NULL AND st.school_year = ?)
                        OR
                        (st.section_id IS NULL AND (st.school_year IS NULL OR st.school_year = '') AND YEAR(st.created_at) = ?)
                  )
            ", [$grade, $schoolYear, $schoolYear, $selectedYear])->getRow();

            $enrollmentByGrade[$grade] = (int) ($row->total ?? 0);
        }

        // Enrollment widget statistics (KPIs, growth, freshness)
        $enrollmentTotal = array_sum($enrollmentByGrade);
        $enrollmentActiveGrades = count(array_filter($enrollmentByGrade));

        $enrollmentTopGrade = null;
        $enrollmentLowGrade = null;
        foreach ($enrollmentByGrade as $grade => $count) {
            if ($count <= 0) {
                continue;
            }
            $gradeLabel = grade_level_label((int) $grade);
            if ($enrollmentTopGrade === null || $count > $enrollmentTopGrade['count']) {
                $enrollmentTopGrade = ['grade' => $grade, 'label' => $gradeLabel, 'count' => $count];
            }
            if ($enrollmentLowGrade === null || $count < $enrollmentLowGrade['count']) {
                $enrollmentLowGrade = ['grade' => $grade, 'label' => $gradeLabel, 'count' => $count];
            }
        }

        // Growth vs previous school year (same enrollment scope as the grade query)
        $syParts = explode('-', $schoolYear);
        $prevSchoolYear = (count($syParts) === 2 && is_numeric($syParts[0]) && is_numeric($syParts[1]))
            ? ((int) $syParts[0] - 1) . '-' . ((int) $syParts[1] - 1)
            : null;
        $currentYearTotal = $this->enrollmentTotalForYear($schoolYear, (int) $selectedYear);
        $prevYearTotal = $prevSchoolYear !== null ? $this->enrollmentTotalForYear($prevSchoolYear, (int) $selectedYear - 1) : 0;
        $enrollmentGrowth = $prevYearTotal > 0
            ? round((($currentYearTotal - $prevYearTotal) / $prevYearTotal) * 100, 1)
            : null;

        // Last data refresh (newest enrolled student record in the same scope)
        $lastUpdatedRow = $db->query("
            SELECT MAX(st.created_at) AS last_at
            FROM students st
            LEFT JOIN sections s ON s.id = st.section_id
            WHERE st.enrollment_status = 'enrolled'
              AND st.deleted_at IS NULL
              AND (
                    (st.section_id IS NOT NULL AND s.school_year = ?)
                    OR
                    (st.section_id IS NULL AND st.school_year = ?)
                    OR
                    (st.section_id IS NULL AND (st.school_year IS NULL OR st.school_year = '') AND YEAR(st.created_at) = ?)
              )
        ", [$schoolYear, $schoolYear, $selectedYear])->getRow();
        $enrollmentLastUpdated = $lastUpdatedRow->last_at ?? null;

        $enrollmentChartLabels = grade_level_chart_labels();
        $enrollmentChartValues = array_map(
            static fn (int $g): int => (int) ($enrollmentByGrade[$g] ?? 0),
            grade_level_options()
        );
        
        // Get recent enrollments
        $recentEnrollments = $studentModel->orderBy('created_at', 'DESC')
                                         ->limit(10)
                                         ->findAll();
        
        // Get recent announcements
        $recentAnnouncements = $announcementModel->orderBy('created_at', 'DESC')
                                                ->limit(5)
                                                ->findAll();
        
        // Get available years
        $availableYears = [];
        $currentYear = date('Y');
        for ($year = $currentYear - 2; $year <= $currentYear + 1; $year++) {
            $availableYears[] = $year;
        }

        // Get registration and grading status
        $systemSettingModel = new \App\Models\SystemSettingModel();
        $registrationSetting = $systemSettingModel->getSetting('registration_enabled', null);
        if ($registrationSetting === null) {
            $registrationSetting = $systemSettingModel->getSetting('enrollment_enabled', 1); // backward compatibility
        }
        $registrationEnabled = (bool) $registrationSetting;
        $gradingEnabled = (bool) $systemSettingModel->getSetting('grading_enabled', 1);

        helper('admin_access');
        $adminStaffList = [];
        try {
            if (is_master_admin()) {
                $adminStaffList = $db->query("
                    SELECT u.id, u.email, u.first_name, u.last_name
                    FROM users u
                    INNER JOIN auth_groups_users ag ON ag.user_id = u.id AND ag.`group` = 'admin_staff'
                    WHERE u.deleted_at IS NULL
                    ORDER BY u.id DESC
                ")->getResultArray();
            }
        } catch (\Throwable $e) {
            $adminStaffList = [];
        }
        
        $data = [
            'title' => 'Admin Dashboard - CSCS Tap n Track',
            'total_students' => $studentModel->where('enrollment_status', 'enrolled')->countAllResults(),
            'total_teachers' => $teacherModel->countAll(),
            'total_users' => 0, // $userModel->countAll(),
            'pending_enrollments' => $studentModel->where('enrollment_status', 'pending')->countAllResults(),
            'currentTerm' => $currentTerm,
            'schoolYear' => $schoolYear,
            'selectedYear' => $selectedYear,
            'availableYears' => $availableYears,
            'enrollmentByGrade' => $enrollmentByGrade,
            'enrollmentChartLabels' => $enrollmentChartLabels,
            'enrollmentChartValues' => $enrollmentChartValues,
            'enrollmentTotal' => $enrollmentTotal,
            'enrollmentActiveGrades' => $enrollmentActiveGrades,
            'enrollmentTopGrade' => $enrollmentTopGrade,
            'enrollmentLowGrade' => $enrollmentLowGrade,
            'enrollmentGrowth' => $enrollmentGrowth,
            'enrollmentLastUpdated' => $enrollmentLastUpdated,
            'recentEnrollments' => $recentEnrollments,
            'recentAnnouncements' => $recentAnnouncements,
            'registrationEnabled' => $registrationEnabled,
            'gradingEnabled' => $gradingEnabled,
            'adminStaffList' => $adminStaffList,
        ];

        return view('admin/dashboard', $data);
    }

    public function createAdmin()
    {
        helper('admin_access');

        if (! is_master_admin()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Only a master administrator can create admin accounts.',
            ]);
        }

        if ($this->request->getMethod() !== 'POST') {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid request method']);
        }

        $email = $this->request->getPost('email');
        $firstName = $this->request->getPost('first_name');
        $lastName = $this->request->getPost('last_name');
        $password = $this->request->getPost('password');
        $accountType = $this->request->getPost('account_type');
        $pages = $this->request->getPost('pages');

        if (empty($email) || empty($firstName) || empty($lastName) || empty($password)) {
            return $this->response->setJSON(['success' => false, 'message' => 'All fields are required']);
        }

        // Shared password policy: length AND at least one digit.
        $policyError = password_meets_policy($password);
        if ($policyError !== null) {
            return $this->response->setJSON(['success' => false, 'message' => $policyError]);
        }

        if (! in_array($accountType, ['master', 'staff'], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid account type.']);
        }

        $group = $accountType === 'staff' ? 'admin_staff' : 'admin';
        $allowedPagesJson = null;

        if ($group === 'admin_staff') {
            $pageList = is_array($pages) ? $pages : [];
            $pageList = array_values(array_intersect(admin_valid_page_keys(), $pageList));
            if ($pageList === []) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Select at least one page for admin staff.',
                ]);
            }
            $allowedPagesJson = json_encode($pageList);
        }

        $db = \Config\Database::connect();

        $existing = $db->table('users')->where('email', $email)->get()->getRow();
        if ($existing) {
            return $this->response->setJSON(['success' => false, 'message' => 'Email already exists']);
        }

        $userData = [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'admin_allowed_pages' => $allowedPagesJson,
            'created_at' => date('Y-m-d H:i:s', time()),
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];

        try {
            if (! $db->table('users')->insert($userData)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to create admin account']);
            }

            $userId = $db->insertID();

            $authData = [
                'user_id' => $userId,
                'type' => 'email_password',
                'name' => $email,
                'secret' => $email,
                'secret2' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $db->table('auth_identities')->insert($authData);

            $db->table('auth_groups_users')->insert([
                'user_id' => $userId,
                'group' => $group,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Admin account creation failed: ' . $e->getMessage());
            // Rollback: delete the user if auth identity or group insertion failed
            if (isset($userId)) {
                $db->table('users')->where('id', $userId)->delete();
            }
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to create admin account: ' . $e->getMessage()]);
        }

        $label = $group === 'admin_staff' ? 'Admin staff account' : 'Master admin account';

        audit_event('role.account_created', [
            'category'      => 'role',
            'status'        => 'success',
            'resource_type' => 'user',
            'resource_id'   => (string) $userId,
            'description'   => $label . ' created',
            'after'         => [
                'email' => $email,
                'name'  => trim($firstName . ' ' . $lastName),
                'role'  => $group,
            ],
            'metadata'      => [
                'role'  => $group,
                'pages' => $group === 'admin_staff' ? ($pageList ?? []) : [],
            ],
        ]);

        return $this->response->setJSON(['success' => true, 'message' => $label . ' created successfully']);
    }
    
    public function updateTerm()
    {
        if ($this->request->getMethod() === 'POST') {
            $term = (int) $this->request->getPost('term');

            if ($term >= 1 && $term <= 3) {
                session()->set('current_term', $term);

                try {
                    $systemSettingModel = new \App\Models\SystemSettingModel();

                    $db = \Config\Database::connect();
                    $db->query("CREATE TABLE IF NOT EXISTS `system_settings` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `setting_key` varchar(100) NOT NULL,
                        `setting_value` text,
                        `description` text,
                        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `setting_key` (`setting_key`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                    $systemSettingModel->setCurrentTerm($term);
                } catch (\Exception $e) {
                    log_message('error', 'System settings update failed: ' . $e->getMessage());
                }

                audit_event('settings.term_updated', [
                    'category'      => 'settings',
                    'status'        => 'success',
                    'resource_type' => 'setting',
                    'resource_id'   => 'current_term',
                    'description'   => 'Active grading term changed to Term ' . $term,
                    'after'         => ['current_term' => $term],
                ]);

                return $this->response->setJSON(['success' => true, 'message' => 'Term updated successfully']);
            }

            return $this->response->setJSON(['success' => false, 'message' => 'Invalid term']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Invalid request method']);
    }
    
    public function toggleEnrollment()
    {
        if ($this->request->getMethod() === 'POST') {
            $systemSettingModel = new \App\Models\SystemSettingModel();
            $registrationSetting = $systemSettingModel->getSetting('registration_enabled', null);
            if ($registrationSetting === null) {
                $registrationSetting = $systemSettingModel->getSetting('enrollment_enabled', 1); // backward compatibility
            }
            $currentStatus = (bool) $registrationSetting;
            $newStatus = !$currentStatus;
            
            // Write the new key; also write the old one for backward compatibility with any untouched pages
            $systemSettingModel->setSetting('registration_enabled', $newStatus ? '1' : '0', 'Enable or disable student registration');
            $systemSettingModel->setSetting('enrollment_enabled', $newStatus ? '1' : '0', 'Enable or disable student enrollment');

            audit_event('settings.registration_toggled', [
                'category'      => 'settings',
                'status'        => 'success',
                'resource_type' => 'setting',
                'resource_id'   => 'registration_enabled',
                'description'   => 'Student registration ' . ($newStatus ? 'enabled' : 'disabled'),
            ] + audit_diff(['registration_enabled' => $currentStatus ? '1' : '0'], ['registration_enabled' => $newStatus ? '1' : '0'], ['registration_enabled']));

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Registration ' . ($newStatus ? 'enabled' : 'disabled') . ' successfully',
                'enabled' => $newStatus
            ]);
        }
        
        return $this->response->setJSON(['success' => false, 'message' => 'Invalid request method']);
    }
    
    public function toggleGrading()
    {
        if ($this->request->getMethod() === 'POST') {
            $systemSettingModel = new \App\Models\SystemSettingModel();
            $currentStatus = (bool) $systemSettingModel->getSetting('grading_enabled', 1);
            $newStatus = !$currentStatus;
            
            $systemSettingModel->setSetting('grading_enabled', $newStatus ? '1' : '0', 'Enable or disable grade input for teachers');

            audit_event('settings.grading_toggled', [
                'category'      => 'settings',
                'status'        => 'success',
                'resource_type' => 'setting',
                'resource_id'   => 'grading_enabled',
                'description'   => 'Grade input ' . ($newStatus ? 'enabled' : 'disabled'),
            ] + audit_diff(['grading_enabled' => $currentStatus ? '1' : '0'], ['grading_enabled' => $newStatus ? '1' : '0'], ['grading_enabled']));

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Grading ' . ($newStatus ? 'enabled' : 'disabled') . ' successfully',
                'enabled' => $newStatus
            ]);
        }
        
        return $this->response->setJSON(['success' => false, 'message' => 'Invalid request method']);
    }
    
    public function sections()
    {
        if (!auth()->user() || !is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $sectionModel = new SectionModel();
        $teacherModel = new TeacherModel();
        $schoolYear = get_current_school_year();

        // Get filter parameters
        $gradeFilter = $this->request->getGet('grade');
        $adviserFilter = $this->request->getGet('adviser_status');
        $searchTerm = $this->request->getGet('search');

        // Get sections with adviser information (pass null to skip school year filter, show all)
        $sections = $sectionModel->getSectionsWithAdviser(null);

        // Apply filters
        if (!empty($gradeFilter)) {
            $sections = array_filter($sections, fn($s) => $s['grade_level'] == $gradeFilter);
        }

        if (!empty($adviserFilter)) {
            if ($adviserFilter === 'with_adviser') {
                $sections = array_filter($sections, fn($s) => !empty($s['adviser_name']));
            } elseif ($adviserFilter === 'no_adviser') {
                $sections = array_filter($sections, fn($s) => empty($s['adviser_name']));
            }
        }

        if (!empty($searchTerm)) {
            $sections = array_filter($sections, function($s) use ($searchTerm) {
                return stripos($s['section_name'], $searchTerm) !== false ||
                       stripos($s['adviser_name'] ?? '', $searchTerm) !== false;
            });
        }

        // Get available teachers (exclude those who are already advisers)
        $availableTeachers = $teacherModel->select('teachers.*')
            ->where('teachers.id NOT IN (SELECT DISTINCT adviser_id FROM sections WHERE adviser_id IS NOT NULL)', null, false)
            ->where('teachers.employment_status', 'active')
            ->orderBy('teachers.last_name', 'ASC')
            ->findAll();

        return view('admin/sections', [
            'title' => 'Manage Sections - CSCS Tap n Track',
            'sections' => $sections,
            'availableTeachers' => $availableTeachers,
            'gradeFilter' => $gradeFilter,
            'adviserFilter' => $adviserFilter,
            'searchTerm' => $searchTerm
        ]);
    }

    public function assignAdviser($sectionId)
    {
        if (!is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $sectionModel = new SectionModel();
        $teacherModel = new TeacherModel();
        $adviserId = $this->request->getPost('adviser_id');

        if (empty($adviserId)) {
            return redirect()->back()->with('error', 'Please select a teacher to assign as adviser.');
        }

        $section = $sectionModel->find($sectionId);
        if (!$section) {
            return redirect()->back()->with('error', 'Section not found.');
        }

        $teacher = $teacherModel->find($adviserId);
        if (!$teacher || $teacher['employment_status'] !== 'active') {
            return redirect()->back()->with('error', 'Teacher not found or not active.');
        }
        
        // Check if teacher is already an adviser
        $db = \Config\Database::connect();
        $existingAdviser = $db->table('sections')
            ->where('adviser_id', $adviserId)
            ->countAllResults();
        
        if ($existingAdviser > 0) {
            return redirect()->back()->with('error', 'This teacher is already assigned as an adviser to another section.');
        }

        $result = $db->query("UPDATE sections SET adviser_id = ?, updated_at = NOW() WHERE id = ?", [$adviserId, $sectionId]);
        
        if ($result) {
            return redirect()->back()->with('success', 'Teacher successfully assigned as section adviser.');
        } else {
            return redirect()->back()->with('error', 'Failed to assign teacher as adviser.');
        }
    }

    public function getSectionTeachers($sectionId)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $db = \Config\Database::connect();
        
        // Get adviser
        $adviser = $db->table('sections s')
            ->select('t.id, t.first_name, t.last_name, t.email')
            ->join('teachers t', 't.id = s.adviser_id')
            ->where('s.id', $sectionId)
            ->get()->getRow();
        
        // Get subject teachers (including placeholder assignments). For
        // non-numerical sections teachers are assigned to developmental
        // domains stored in teacher_schedules.domain_id — joining subjects
        // there would return nothing, so read the domain name instead.
        $section = $db->table('sections')->select('grading_type')->where('id', $sectionId)->get()->getRow();
        $isDomainMode = $section && ($section->grading_type ?? '') === 'non_numerical' && schedule_domain_column_ready();

        if ($isDomainMode) {
            $subjectTeachers = $db->table('teacher_schedules ts')
                ->select('t.id, t.first_name, t.last_name, t.email, sc.name as subject_name, ts.day_of_week, ts.start_time, ts.end_time, ts.id as schedule_id')
                ->join('teachers t', 't.id = ts.teacher_id')
                ->join('sned_categories sc', 'sc.id = ts.domain_id', 'left')
                ->where('ts.section_id', $sectionId)
                ->groupBy('t.id, sc.id, ts.id')
                ->get()->getResultArray();
        } else {
            $subjectTeachers = $db->table('teacher_schedules ts')
                ->select('t.id, t.first_name, t.last_name, t.email, sub.subject_name, ts.day_of_week, ts.start_time, ts.end_time, ts.id as schedule_id')
                ->join('teachers t', 't.id = ts.teacher_id')
                ->join('subjects sub', 'sub.id = ts.subject_id', 'left')
                ->where('ts.section_id', $sectionId)
                ->groupBy('t.id, sub.id, ts.id')
                ->get()->getResultArray();
        }
        
        return $this->response->setJSON([
            'success' => true,
            'adviser' => $adviser,
            'subjectTeachers' => $subjectTeachers,
            'domain_mode' => $isDomainMode,
        ]);
    }

    public function removeSubjectTeacher($scheduleId)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $db = \Config\Database::connect();

        $row = $db->table('teacher_schedules')
            ->select('id, teacher_id, section_id, subject_id, domain_id')
            ->where('id', (int) $scheduleId)
            ->get()
            ->getRowArray();

        if (!$row) {
            return $this->response->setJSON(['success' => false, 'message' => 'Assignment not found (it may have already been removed). Refresh and try again.']);
        }

        // Historical data contains duplicate rows for the same
        // section + teacher + subject (or domain) pair — older code inserted
        // one row per assignment click without a unique constraint. The "Assign
        // Teachers" modal passes all=1 so one Remove click also cleans up every
        // duplicate row for that assignment; without it the teacher kept
        // appearing assigned no matter how many times Remove was clicked.
        // The View Teachers checkbox flow omits all=1 and removes just the one
        // selected timetable slot, leaving the other slots intact.
        $deleteAll = $this->request->getGet('all') === '1';

        if ($deleteAll) {
            $query = $db->table('teacher_schedules')
                ->where('section_id', $row['section_id'])
                ->where('teacher_id', $row['teacher_id']);

            if ($row['domain_id'] !== null) {
                $query->where('domain_id', $row['domain_id']);
            } else {
                $query->where('subject_id', $row['subject_id']);
            }

            $deleted = $query->delete();
            $count   = $db->affectedRows();
        } else {
            $deleted = $db->table('teacher_schedules')->where('id', $row['id'])->delete();
            $count   = 1;
        }

        if ($deleted) {
            return $this->response->setJSON([
                'success' => true,
                'message' => $count > 1
                    ? "Teacher removed successfully ({$count} duplicate schedule rows cleaned up)"
                    : 'Teacher removed successfully',
            ]);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Failed to remove teacher']);
    }

    public function removeAdviser($sectionId)
    {
        if (!is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $sectionModel = new SectionModel();
        $section = $sectionModel->find($sectionId);
        if (!$section) {
            return redirect()->back()->with('error', 'Section not found.');
        }

        $success = $sectionModel->update($sectionId, ['adviser_id' => null]);
        if ($success) {
            return redirect()->back()->with('success', 'Adviser removed from section successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to remove adviser from section.');
        }
    }

    public function getSectionStudents($sectionId)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $studentModel = new StudentModel();
        $sectionModel = new SectionModel();
        $section = $sectionModel->find($sectionId);
        if (!$section) {
            return $this->response->setJSON(['success' => false, 'message' => 'Section not found']);
        }

        $students = $studentModel->select('id, lrn, first_name, last_name, created_at')
                                 ->where('section_id', $sectionId)
                                 ->where('enrollment_status', 'enrolled')
                                 ->orderBy('last_name', 'ASC')
                                 ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'students' => $students,
            'section' => $section
        ]);
    }

    public function getSectionSubjects($sectionId)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $db = \Config\Database::connect();

        // Non-numerical sections are assessed with developmental domains, not
        // subjects, so section_subjects is legitimately empty for them. Those
        // sections must report the domains the admin ticked on the Sections
        // page (falling back to every shared domain when never configured).
        if (is_non_numerical_section($sectionId)) {
            $section = $db->table('sections')->where('id', (int) $sectionId)->get()->getRowArray();
            $selectedIds = section_selected_domain_ids((int) $sectionId);

            if ($selectedIds === null) {
                $domains = (new \App\Models\SnedCategoryModel())
                    ->getActiveCategories((int) $sectionId, (int) ($section['grade_level'] ?? 0));
            } elseif ($selectedIds !== []) {
                $domains = $db->table('sned_categories')
                    ->whereIn('id', $selectedIds)
                    ->where('is_active', 1)
                    ->orderBy('display_order', 'ASC')
                    ->get()->getResultArray();
            } else {
                $domains = [];
            }

            // Same shape the subject branch returns so the modal's JS is shared.
            $subjects = [];
            foreach ($domains as $domain) {
                $subjects[] = [
                    'id'           => (int) $domain['id'],
                    'subject_name' => $domain['name'],
                    'subject_code' => null,
                    'is_domain'    => true,
                ];
            }

            return $this->response->setJSON([
                'success'         => true,
                'subjects'        => $subjects,
                'is_non_numerical' => true,
                'domain_mode'     => true,
            ]);
        }

        // Get section-specific subjects with section-specific is_active status
        $subjects = $db->query(
            "SELECT s.*, ss.is_active, ss.id as section_subject_id FROM subjects s
             INNER JOIN section_subjects ss ON s.id = ss.subject_id
             WHERE ss.section_id = ?
             ORDER BY s.subject_name ASC",
            [$sectionId]
        )->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'subjects' => $subjects
        ]);
    }

    public function addSubject()
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'error' => 'Access denied']);
        }

        try {
            $db = \Config\Database::connect();
            
            $data = [
                'subject_code' => $this->request->getPost('subject_code'),
                'subject_name' => $this->request->getPost('subject_name'),
                'grade_level' => $this->request->getPost('grade_level'),
                'is_active' => $this->request->getPost('is_active') ? 1 : 0
            ];
            
            $result = $db->table('subjects')->insert($data);
            
            if ($result) {
                return $this->response->setJSON(['success' => true, 'message' => 'Subject added successfully']);
            }
            
            return $this->response->setJSON(['success' => false, 'error' => 'Failed to add subject']);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function editSubject($id)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'error' => 'Access denied']);
        }

        try {
            $db = \Config\Database::connect();

            $subject = $db->table('subjects')->where('id', $id)->get()->getRowArray();
            if (!$subject) {
                return $this->response->setJSON(['success' => false, 'error' => 'Subject not found']);
            }

            $code = trim((string) $this->request->getPost('subject_code'));
            $name = trim((string) $this->request->getPost('subject_name'));

            if ($code === '' || $name === '') {
                return $this->response->setJSON(['success' => false, 'error' => 'Subject code and name are required']);
            }

            // subject_code / subject_name are GLOBAL fields on the subjects table
            // (shared by every section that offers this subject). They must always
            // be updated here. The old code skipped this entirely whenever a
            // section_subject_id was posted, which is why edits reported success
            // but nothing changed.
            $result = $db->table('subjects')->where('id', $id)->update([
                'subject_code' => $code,
                'subject_name' => $name,
            ]);
            if ($result === false) {
                return $this->response->setJSON(['success' => false, 'error' => 'Failed to update subject']);
            }

            // is_active is PER-SECTION (section_subjects.is_active). Only touch it
            // when the request actually posts a value — the Edit Subject modal no
            // longer sends one, so the per-section status stays untouched.
            $isActiveValue = $this->request->getPost('is_active');
            $sectionSubjectId = $this->request->getPost('section_subject_id');
            if ($isActiveValue !== null && $sectionSubjectId) {
                $isActiveInt = ($isActiveValue === '1' || $isActiveValue === 1 || $isActiveValue === true || $isActiveValue === 'true') ? 1 : 0;
                $db->table('section_subjects')->where('id', $sectionSubjectId)->update(['is_active' => $isActiveInt]);
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Subject updated successfully']);
        } catch (\Exception $e) {
            // subject_code has a UNIQUE index — surface a friendly duplicate message
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return $this->response->setJSON(['success' => false, 'error' => 'That subject code is already used by another subject']);
            }
            return $this->response->setJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function deleteSubject($id)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'error' => 'Access denied']);
        }

        try {
            $db = \Config\Database::connect();
            
            $result = $db->table('subjects')->where('id', $id)->delete();
            
            if ($result) {
                return $this->response->setJSON(['success' => true, 'message' => 'Subject deleted successfully']);
            }
            
            return $this->response->setJSON(['success' => false, 'error' => 'Subject not found or already deleted']);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function getUnassignedStudents($gradeLevel)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        try {
            $search = trim($this->request->getGet('search') ?? '');
            $page = max(1, (int)($this->request->getGet('page') ?? 1));
            $perPage = min(50, max(20, (int)($this->request->getGet('per_page') ?? 20)));

            // Use direct SQL query like teacher controller for consistency
            $db = \Config\Database::connect();
            
            $searchCondition = '';
            $params = [$gradeLevel];
            
            if (!empty($search)) {
                $searchCondition = ' AND (first_name LIKE ? OR last_name LIKE ? OR lrn LIKE ? OR CONCAT(first_name, " ", last_name) LIKE ?)';
                $searchParam = '%' . $search . '%';
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
            }
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM students 
                           WHERE grade_level = ? 
                           AND enrollment_status = 'enrolled' 
                           AND section_id IS NULL" . $searchCondition;
            
            $totalResult = $db->query($countQuery, $params)->getRow();
            $totalStudents = $totalResult->total;
            
            // Get paginated results
            $offset = ($page - 1) * $perPage;
            $perPage = (int) max(1, min(100, (int) $perPage));
            $offset = (int) max(0, (int) $offset);
            $dataQuery = "SELECT id, lrn, first_name, last_name, grade_level FROM students 
                          WHERE grade_level = ? 
                          AND enrollment_status = 'enrolled' 
                          AND section_id IS NULL" . $searchCondition . "
                          ORDER BY last_name ASC 
                          LIMIT {$perPage} OFFSET {$offset}";
            
            $students = $db->query($dataQuery, $params)->getResultArray();
            
            $totalPages = max(1, ceil($totalStudents / $perPage));

            return $this->response->setJSON([
                'success' => true,
                'students' => $students,
                'search_term' => $search,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'totalStudents' => $totalStudents,
                    'perPage' => $perPage
                ]
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error in getUnassignedStudents: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error loading students: ' . $e->getMessage()
            ]);
        }
    }
    

    public function assignStudentsToSection($sectionId)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        try {
            $studentModel = new StudentModel();
            $sectionModel = new SectionModel();
            $section = $sectionModel->find($sectionId);
            if (!$section) {
                return $this->response->setJSON(['success' => false, 'message' => 'Section not found']);
            }

            $input = json_decode($this->request->getBody(), true);
            $studentIds = $input['student_ids'] ?? [];

            if (empty($studentIds)) {
                return $this->response->setJSON(['success' => false, 'message' => 'No students selected']);
            }

            // Check current enrollment and capacity
            $currentEnrollment = $studentModel->where('section_id', $sectionId)
                                             ->where('enrollment_status', 'enrolled')
                                             ->countAllResults();
            
            $availableSlots = $section['max_capacity'] - $currentEnrollment;
            
            if ($availableSlots <= 0) {
                return $this->response->setJSON([
                    'success' => false, 
                    'message' => "Section is at full capacity ({$currentEnrollment}/{$section['max_capacity']}). Cannot assign more students."
                ]);
            }
            
            if (count($studentIds) > $availableSlots) {
                return $this->response->setJSON([
                    'success' => false, 
                    'message' => "Cannot assign " . count($studentIds) . " students. Only {$availableSlots} slots available in this section."
                ]);
            }

            $assignedCount = 0;
            $alreadyAssigned = 0;
            $db = \Config\Database::connect();
            
            foreach ($studentIds as $studentId) {
                // Use direct database query to check and update atomically, set can_view_report_card to 0 by default
                $result = $db->query(
                    "UPDATE students SET section_id = ?, can_view_report_card = 0, updated_at = NOW() WHERE id = ? AND (section_id IS NULL OR section_id = 0) AND enrollment_status = 'enrolled'", 
                    [$sectionId, $studentId]
                );
                
                if ($db->affectedRows() > 0) {
                    $assignedCount++;
                } else {
                    $alreadyAssigned++;
                }
            }

            // Update section enrollment count
            $sectionModel->updateEnrollmentCount($sectionId);

            if ($assignedCount > 0) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => "Successfully assigned {$assignedCount} student(s) to {$section['section_name']}",
                    'assigned_count' => $assignedCount
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'No students were assigned. Students may already be assigned to sections.'
                ]);
            }
        } catch (\Exception $e) {
            log_message('error', 'Error in assignStudentsToSection: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error assigning students: ' . $e->getMessage()
            ]);
        }
    }

    public function removeStudentFromSection($studentId)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'error' => 'Access denied']);
        }

        $db = \Config\Database::connect();
        $student = $db->table('students')->where('id', $studentId)->get()->getRow();
        
        if (!$student) {
            return $this->response->setJSON(['success' => false, 'error' => 'Student not found']);
        }

        // Remove student from section using raw SQL query to ensure it works
        $db->query("UPDATE students SET section_id = NULL, updated_at = NOW() WHERE id = ?", [$studentId]);
        
        // Update section enrollment count if student was in a section
        if ($student->section_id) {
            $sectionModel = new SectionModel();
            $sectionModel->updateEnrollmentCount($student->section_id);
        }
        
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Student removed from section successfully'
        ]);
    }

    public function removeStudentsBulk()
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'error' => 'Access denied']);
        }

        $input = json_decode($this->request->getBody(), true);
        $studentIds = $input['student_ids'] ?? [];

        if (empty($studentIds)) {
            return $this->response->setJSON(['success' => false, 'error' => 'No students selected']);
        }

        $db = \Config\Database::connect();
        $sectionModel = new SectionModel();
        $removedCount = 0;
        $sectionsToUpdate = [];

        foreach ($studentIds as $studentId) {
            // Get current section before removing
            $student = $db->table('students')->where('id', $studentId)->get()->getRow();
            if ($student) {
                if ($student->section_id) {
                    $sectionsToUpdate[$student->section_id] = true;
                }
                // Use raw SQL to bypass all model restrictions
                $db->query("UPDATE students SET section_id = NULL, updated_at = NOW() WHERE id = ?", [$studentId]);
                $removedCount++;
            }
        }

        // Update enrollment counts for affected sections
        foreach (array_keys($sectionsToUpdate) as $sectionId) {
            $sectionModel->updateEnrollmentCount($sectionId);
        }

        if ($removedCount > 0) {
            return $this->response->setJSON([
                'success' => true,
                'message' => "Successfully removed {$removedCount} student(s) from section"
            ]);
        }

        return $this->response->setJSON(['success' => false, 'error' => 'Failed to remove students']);
    }

    public function updateSection($sectionId)
    {
        if (!is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $sectionModel = new SectionModel();
        $section = $sectionModel->find($sectionId);
        if (!$section) {
            return redirect()->back()->with('error', 'Section not found.');
        }

        $rules = [
            'section_name' => 'required|max_length[100]',
            'grade_level' => 'required|integer|in_list[0,1,2,3,4,5,6,7,99]',
            'school_year' => 'required|max_length[9]|regex_match[/^\d{4}-\d{4}$/]',
            'max_capacity' => 'required|integer|greater_than[0]',
            'grading_type' => 'permit_empty|in_list[numerical,non_numerical,custom]'
        ];
        if (!$this->validate($rules)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => false, 
                    'message' => 'Validation failed: ' . implode(', ', $this->validator->getErrors())
                ]);
            }
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'section_name' => $this->request->getPost('section_name'),
            'grade_level' => (int) $this->request->getPost('grade_level'),
            'school_year' => $this->request->getPost('school_year'),
            'max_capacity' => (int) $this->request->getPost('max_capacity'),
        ];
        
        // Only update grading_type if provided (single edit + bulk edit)
        $gradingType = $this->request->getPost('grading_type');
        if ($gradingType) {
            $oldType = $section['grading_type'] ?? 'numerical';
            $data['grading_type'] = $gradingType;

            // Non-numerical sections are graded through the SNED
            // developmental-domains portal — leftover subject links and stale
            // numeric grades (e.g. symbols typed into numeric fields, stored as
            // 0.00) must never survive. The cleanup runs on EVERY save of a
            // non-numerical section, so a section that was converted before
            // this fix existed is repaired the next time the admin saves it.
            if ($gradingType === 'non_numerical') {
                $db = \Config\Database::connect();

                // 1. Remove the section's subject links.
                $db->table('section_subjects')->where('section_id', $sectionId)->delete();

                // 2. Remove stale numeric grades of the section's students.
                $db->query(
                    "DELETE g FROM grades g
                     JOIN students st ON st.id = g.student_id
                     WHERE st.section_id = ?",
                    [$sectionId]
                );

                // 3. Seed default grading symbols once, on the switch.
                if ($oldType !== 'non_numerical') {
                $hasSymbols = $db->table('section_grading_symbols')
                    ->where('section_id', $sectionId)
                    ->countAllResults() > 0;
                if (! $hasSymbols) {
                    $defaultSymbols = [
                        ['symbol' => 'P',     'label' => 'Proficient',                   'description' => 'Meets expectations',            'display_order' => 1],
                        ['symbol' => 'AP',    'label' => 'Approaching Proficiency',      'description' => 'Nearly meets expectations',     'display_order' => 2],
                        ['symbol' => 'D',     'label' => 'Developing',                   'description' => 'Still developing skills',       'display_order' => 3],
                        ['symbol' => 'B',     'label' => 'Beginning',                    'description' => 'Beginning to learn',            'display_order' => 4],
                        ['symbol' => 'NO/NA', 'label' => 'Not Observed / Not Applicable', 'description' => 'Skill not yet observed or not applicable', 'display_order' => 5],
                    ];
                    foreach ($defaultSymbols as $sym) {
                        $db->table('section_grading_symbols')->insert([
                            'section_id'    => $sectionId,
                            'symbol'        => $sym['symbol'],
                            'label'         => $sym['label'],
                            'description'   => $sym['description'],
                            'display_order' => $sym['display_order'],
                            'is_active'     => 1,
                        ]);
                    }
                }
                }
            }
        }

        if (($section['current_enrollment'] ?? 0) > $data['max_capacity']) {
            $data['current_enrollment'] = $data['max_capacity'];
        }

        if ($sectionModel->update($sectionId, $data)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => true, 'message' => 'Section updated successfully']);
            }
            return redirect()->back()->with('success', 'Section updated successfully.');
        }
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to update section']);
        }
        return redirect()->back()->with('error', 'Failed to update section.');
    }

    public function deleteSection($sectionId)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $sectionModel = new SectionModel();
        // withDeleted() so rows that were only soft-deleted in the past can
        // still be found and permanently purged.
        $section = $sectionModel->withDeleted()->find($sectionId);

        if (!$section) {
            return $this->response->setJSON(['success' => false, 'message' => 'Section not found']);
        }

        // Check if section has enrolled students
        $studentModel = new StudentModel();
        $enrolledCount = $studentModel->where('section_id', $sectionId)
                                     ->where('enrollment_status', 'enrolled')
                                     ->countAllResults();

        if ($enrolledCount > 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => "Cannot delete section. It has {$enrolledCount} enrolled student(s). Please move students to other sections first."
            ]);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Detach any remaining students (pending/applicants, etc.). students.section_id
            // has no foreign key, so it must be cleared explicitly or the rows
            // would point at a section that no longer exists.
            $db->table('students')
                ->where('section_id', $sectionId)
                ->update(['section_id' => null, 'updated_at' => date('Y-m-d H:i:s')]);

            // Permanently remove the section row. The second argument forces a
            // hard DELETE instead of a soft delete (deleted_at stamp), so the
            // row is gone from the database. section_subjects /
            // teacher_schedules / quizzes rows cascade via foreign keys.
            $sectionModel->delete($sectionId, true);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to delete section']);
            }

            log_message('info', 'Section permanently deleted: ID ' . $sectionId . ' by admin.');

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Section deleted successfully'
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Section delete error: ' . $e->getMessage());

            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to delete section: ' . $e->getMessage(),
            ]);
        }
    }

    public function createSection()
    {
        if (!is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        if ($this->request->getMethod() !== 'POST') {
            return redirect()->back()->with('error', 'Invalid request method.');
        }

        $rules = [
            'section_name' => 'required|max_length[100]',
            'grade_level' => 'required|integer|' . grade_level_in_list_rule(),
            'school_year' => 'required|max_length[9]|regex_match[/^\d{4}-\d{4}$/]',
            'grading_type' => 'permit_empty|in_list[numerical,non_numerical]',
            'max_capacity' => 'required|integer|greater_than[0]|less_than_equal_to[50]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $sectionModel = new SectionModel();
        
        // Check if section name already exists for the same grade level and school year
        $existing = $sectionModel->where('section_name', $this->request->getPost('section_name'))
                                 ->where('grade_level', $this->request->getPost('grade_level'))
                                 ->where('school_year', $this->request->getPost('school_year'))
                                 ->first();
        
        if ($existing) {
            return redirect()->back()->withInput()->with('error', 'A section with this name already exists for this grade level and school year.');
        }

        // Default to the grade level's configured grading type (Settings >
        // Subject Management > Edit) unless the form explicitly provides one.
        $gradeLevel = (int) $this->request->getPost('grade_level');
        $gradingType = $this->request->getPost('grading_type') ?: grade_grading_type($gradeLevel);

        $data = [
            'section_name' => $this->request->getPost('section_name'),
            'grade_level' => (int) $this->request->getPost('grade_level'),
            'school_year' => $this->request->getPost('school_year'),
            'grading_type' => $gradingType,
            'max_capacity' => (int) $this->request->getPost('max_capacity'),
            'current_enrollment' => 0,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $sectionId = $sectionModel->insert($data);
        
        if ($sectionId) {
            // If non-numerical, seed default grading symbols for this section
            if ($gradingType === 'non_numerical') {
                $db = \Config\Database::connect();
                $defaultSymbols = [
                    ['symbol' => 'P',     'label' => 'Proficient',                 'description' => 'The student consistently demonstrates the skill independently.', 'display_order' => 1],
                    ['symbol' => 'AP',    'label' => 'Approaching Proficiency',    'description' => 'The student is developing the skill with minimal assistance.',      'display_order' => 2],
                    ['symbol' => 'D',     'label' => 'Developing',                 'description' => 'The student is beginning to develop the skill with guidance.',     'display_order' => 3],
                    ['symbol' => 'B',     'label' => 'Beginning',                  'description' => 'The student needs significant support to develop the skill.',   'display_order' => 4],
                    ['symbol' => 'NO/NA', 'label' => 'Not Observed / Not Applicable', 'description' => 'The skill has not been observed or is not applicable at this time.', 'display_order' => 5],
                ];
                $now = date('Y-m-d H:i:s');
                foreach ($defaultSymbols as $sym) {
                    $sym['section_id'] = $sectionId;
                    $sym['created_at'] = $now;
                    $sym['updated_at'] = $now;
                    $db->table('section_grading_symbols')->insert($sym);
                }
            }
            
            return redirect()->back()->with('success', 'Section created successfully!');
        } else {
            return redirect()->back()->withInput()->with('error', 'Failed to create section. Please try again.');
        }
    }

    public function analytics()
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        helper('school_year');
        $currentTerm = get_current_term();
        $schoolYear  = get_current_school_year();
        // Year filter: past 2 years, current year, plus SY end-year (e.g. SY 2026-2027 -> 2027).
        $currentCalendarYear = (int) date('Y');
        $syEndYear = $currentCalendarYear;
        $yearPartsTmp = explode('-', (string) $schoolYear);
        if (count($yearPartsTmp) === 2 && is_numeric($yearPartsTmp[1])) {
            $syEndYear = (int) $yearPartsTmp[1];
        }
        $maxYearTmp = max($currentCalendarYear, $syEndYear);
        $availableYears = [];
        for ($ayTmp = $maxYearTmp - 2; $ayTmp <= $maxYearTmp; $ayTmp++) {
            $availableYears[] = $ayTmp;
        }
        $selectedYear = (int) ($this->request->getGet('year') ?? $currentCalendarYear);
        if (! in_array($selectedYear, $availableYears, true)) {
            $selectedYear = $currentCalendarYear;
        }


        $studentModel = new StudentModel();
        $teacherModel = new TeacherModel();
        
        // Period filter (mirrors the student analytics page): week | month | term | all
        $period = (string) ($this->request->getGet('period') ?? 'all');
        if (! in_array($period, ['week', 'month', 'term', 'all'], true)) {
            $period = 'all';
        }
        $cutoff = null;
        if ($period === 'week') {
            $cutoff = date('Y-m-d 00:00:00', strtotime('-6 days'));
        } elseif ($period === 'month') {
            $cutoff = date('Y-m-d 00:00:00', strtotime('-29 days'));
        }

        // Scope a student query to the selected period + selected year.
        // Year scope: students.school_year LIKE 'selectedYear%' OR YEAR(created_at) = selectedYear,
        // so filtering by 2026 shows SY 2026-2027 + records created in 2026,
        // filtering by 2027 shows SY 2026-2027 records with 2027 dates / SY 2027-2028.
        $applyPeriod = function ($builder) use ($period, $cutoff, $schoolYear, $selectedYear) {
            if ($period === 'term') {
                // Term-level date ranges are not tracked in the database,
                // so the current school year is the closest available scope.
                $builder->where('school_year', $schoolYear);
            } elseif ($cutoff !== null) {
                $builder->where('created_at >=', $cutoff);
            } else {
                $builder->groupStart()
                    ->like('school_year', (string) $selectedYear . '%', 'after')
                    ->orWhere('YEAR(created_at) =', $selectedYear, false)
                    ->groupEnd();
            }
            return $builder;
        };

        $maleCount = $applyPeriod($studentModel->where('gender', 'Male')->where('enrollment_status', 'enrolled'))->countAllResults();
        $femaleCount = $applyPeriod($studentModel->where('gender', 'Female')->where('enrollment_status', 'enrolled'))->countAllResults();
        
        // Get enrollment status distribution
        $enrolledCount = $applyPeriod($studentModel->where('enrollment_status', 'enrolled'))->countAllResults();
        $pendingCount = $applyPeriod($studentModel->where('enrollment_status', 'pending'))->countAllResults();
        $approvedCount = $applyPeriod($studentModel->where('enrollment_status', 'approved'))->countAllResults();
        $rejectedCount = $applyPeriod($studentModel->where('enrollment_status', 'rejected'))->countAllResults();
        
        // Get enrollment by grade
        $gradeDistribution = [];
        foreach (grade_level_options() as $grade) {
            $gradeDistribution[$grade] = $applyPeriod($studentModel->where('grade_level', $grade)
                                                   ->where('enrollment_status', 'enrolled'))
                                                   ->countAllResults();
        }
        
        // Get teacher stats (active employment only)
        $totalTeachers = $teacherModel->where('employment_status', 'active')->countAllResults();
        $teachersWithSections = $teacherModel
            ->where('employment_status', 'active')
            ->where('id IN (SELECT DISTINCT adviser_id FROM sections WHERE adviser_id IS NOT NULL)', null, false)
            ->countAllResults();
        
        // Get recent enrolled students
        $recentEnrolled = $applyPeriod($studentModel->where('enrollment_status', 'enrolled'))
                                      ->orderBy('created_at', 'DESC')
                                      ->limit(5)
                                      ->findAll();
        
        $data = [
            'title' => 'Analytics Dashboard - CSCS Tap n Track',
            'period' => $period,
            'currentTerm' => $currentTerm,
            'schoolYear' => $schoolYear,
            'genderDistribution' => [
                'male' => $maleCount,
                'female' => $femaleCount
            ],
            'statusDistribution' => [
                'enrolled' => $enrolledCount,
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount
            ],
            'gradeDistribution' => $gradeDistribution,
            'teacherStats' => [
                'active' => $totalTeachers,
                'with_adviser' => $teachersWithSections,
                'without_adviser' => $totalTeachers - $teachersWithSections
            ],
            'recentEnrolled' => $recentEnrolled,
            'metrics' => [
                'completionRate' => $enrolledCount > 0 ? round(($enrolledCount / ($enrolledCount + $pendingCount)) * 100) : 0,
                'pendingRate' => $pendingCount > 0 ? round(($pendingCount / ($enrolledCount + $pendingCount)) * 100) : 0,
                'approvalRate' => $approvedCount > 0 ? round(($approvedCount / ($enrolledCount + $pendingCount + $approvedCount)) * 100) : 0,
                'genderBalance' => abs($maleCount - $femaleCount)
            ],
            'gradeAverages' => $this->getGradeAverages(),
            'selectedYear' => $selectedYear ?? $currentCalendarYear ?? (int) date('Y'),
            'availableYears' => $availableYears ?? [((int) date('Y')) - 2, ((int) date('Y')) - 1, (int) date('Y')],
        ];
        
        return view('admin/analytics', $data);
    }

    private function enrollmentTotalForYear(string $schoolYear, int $calendarYear): int
    {
        $db = \Config\Database::connect();
        $row = $db->query("
            SELECT COUNT(*) AS total
            FROM students st
            LEFT JOIN sections s ON s.id = st.section_id
            WHERE st.enrollment_status = 'enrolled'
              AND st.deleted_at IS NULL
              AND (
                    (st.section_id IS NOT NULL AND s.school_year = ?)
                    OR
                    (st.section_id IS NULL AND st.school_year = ?)
                    OR
                    (st.section_id IS NULL AND (st.school_year IS NULL OR st.school_year = '') AND YEAR(st.created_at) = ?)
              )
        ", [$schoolYear, $schoolYear, $calendarYear])->getRow();

        return (int) ($row->total ?? 0);
    }

    private function getGradeAverages()
    {
        helper('school_year');
        $db = \Config\Database::connect();
        $currentTerm = get_current_term();
        $schoolYear = get_current_school_year();

        $gradeAverages = [];
        foreach (grade_level_options() as $grade) {
            $result = $db->query(
                "SELECT AVG(g.grade) as avg_grade 
                 FROM grades g
                 JOIN students s ON g.student_id = s.id
                 WHERE s.grade_level = ? AND g.term = ? AND g.school_year = ? AND s.enrollment_status = 'enrolled'",
                [$grade, $currentTerm, $schoolYear]
            )->getRow();

            $gradeAverages[$grade] = $result && $result->avg_grade ? round((float) $result->avg_grade, 1) : 0;
        }

        return $gradeAverages;
    }

    public function fixEnrollmentCounts()
    {
        if (!is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $sectionModel = new SectionModel();
        $studentModel = new StudentModel();
        $sections = $sectionModel->findAll();
        $fixed = 0;

        foreach ($sections as $section) {
            $actualCount = $studentModel->where('section_id', $section['id'])
                                      ->where('enrollment_status', 'enrolled')
                                      ->countAllResults();
            
            if ($section['current_enrollment'] != $actualCount) {
                $sectionModel->update($section['id'], ['current_enrollment' => $actualCount]);
                $fixed++;
            }
        }

        return redirect()->back()->with('success', "Fixed enrollment counts for {$fixed} sections.");
    }

    public function autoAssignPreview($gradeLevel)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $studentModel = new StudentModel();
        $sectionModel = new SectionModel();

        // Get unassigned students for this grade level, ordered by newest first
        $students = $studentModel->where('grade_level', $gradeLevel)
            ->where('enrollment_status', 'enrolled')
            ->where('(section_id IS NULL OR section_id = 0)')
            ->orderBy('created_at', 'DESC')
            ->findAll();

        if (empty($students)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No unassigned students found for ' . grade_level_label((int) $gradeLevel)
            ]);
        }

        // Get active sections for this grade level with available capacity
        $sections = $sectionModel->where('grade_level', $gradeLevel)
            ->where('is_active', 1)
            ->findAll();

        if (empty($sections)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No active sections found for ' . grade_level_label((int) $gradeLevel)
            ]);
        }

        // Calculate available capacity for each section
        $sectionsWithCapacity = [];
        $totalAvailableSlots = 0;

        foreach ($sections as $section) {
            $availableSlots = $section['max_capacity'] - $section['current_enrollment'];
            if ($availableSlots > 0) {
                $sectionsWithCapacity[] = [
                    'id' => $section['id'],
                    'section_name' => $section['section_name'],
                    'current_enrollment' => $section['current_enrollment'],
                    'max_capacity' => $section['max_capacity'],
                    'available_slots' => $availableSlots
                ];
                $totalAvailableSlots += $availableSlots;
            }
        }

        if (empty($sectionsWithCapacity)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'All sections are at full capacity'
            ]);
        }

        // Limit students to available capacity
        if (count($students) > $totalAvailableSlots) {
            $students = array_slice($students, 0, $totalAvailableSlots);
        }

        // Calculate proportional distribution
        $assignments = [];
        $totalStudents = count($students);
        $assignedSoFar = 0;

        // Sort sections by ID (first created = first filled)
        usort($sectionsWithCapacity, function($a, $b) {
            return $a['id'] - $b['id'];
        });

        // Fill sections sequentially
        foreach ($sectionsWithCapacity as $section) {
            $remainingStudents = $totalStudents - $assignedSoFar;
            
            if ($remainingStudents <= 0) {
                break;
            }
            
            $assignCount = min($remainingStudents, $section['available_slots']);

            if ($assignCount > 0) {
                $assignments[] = [
                    'section_id' => $section['id'],
                    'count' => $assignCount
                ];
                $assignedSoFar += $assignCount;
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'students' => $students,
            'sections' => $sectionsWithCapacity,
            'assignments' => $assignments
        ]);
    }

    public function autoAssignExecute($gradeLevel)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $studentModel = new StudentModel();
        $sectionModel = new SectionModel();
        $db = \Config\Database::connect();

        // Get the same data as preview
        $previewData = json_decode($this->autoAssignPreview($gradeLevel)->getBody(), true);
        
        if (!$previewData['success']) {
            return $this->response->setJSON($previewData);
        }

        $students = $previewData['students'];
        $assignments = $previewData['assignments'];

        // Keep students in order (newest first, already sorted in preview)

        $assignedCount = 0;
        $studentIndex = 0;

        // Assign students according to the plan
        foreach ($assignments as $assignment) {
            $sectionId = $assignment['section_id'];
            $count = $assignment['count'];
            $sectionAssigned = 0;

            for ($i = 0; $i < $count && $studentIndex < count($students); $i++) {
                $student = $students[$studentIndex];
                
                // Double-check student is still unassigned before updating
                $checkResult = $db->query(
                    "SELECT section_id FROM students WHERE id = ? AND enrollment_status = 'enrolled'",
                    [$student['id']]
                )->getRow();
                
                if ($checkResult && ($checkResult->section_id === null || $checkResult->section_id == 0)) {
                    $result = $db->query(
                        "UPDATE students SET section_id = ?, updated_at = NOW() WHERE id = ? AND (section_id IS NULL OR section_id = 0) AND enrollment_status = 'enrolled'",
                        [$sectionId, $student['id']]
                    );

                    if ($db->affectedRows() > 0) {
                        $assignedCount++;
                        $sectionAssigned++;
                    }
                }
                
                $studentIndex++;
            }

            // Update section enrollment count
            $sectionModel->updateEnrollmentCount($sectionId);
        }

        $totalUnassigned = $studentModel->where('grade_level', $gradeLevel)
            ->where('enrollment_status', 'enrolled')
            ->where('(section_id IS NULL OR section_id = 0)')
            ->countAllResults();
        
        $message = "Successfully assigned {$assignedCount} students to " . grade_level_label((int) $gradeLevel) . ' sections';
        if ($totalUnassigned > 0) {
            $message .= ". {$totalUnassigned} students remain unassigned (no available capacity)";
        }
        
        return $this->response->setJSON([
            'success' => true,
            'message' => $message
        ]);
    }

    public function exportPdf()
    {
        if (!auth()->user() || !is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $studentModel = new StudentModel();
        $db = \Config\Database::connect();

        // Gender distribution
        $genderDistribution = [
            'male' => $studentModel->where('gender', 'Male')->where('enrollment_status', 'enrolled')->countAllResults(),
            'female' => $studentModel->where('gender', 'Female')->where('enrollment_status', 'enrolled')->countAllResults()
        ];

        // Grade level distribution
        $gradeDistribution = [];
        foreach (grade_level_options() as $grade) {
            $gradeDistribution[$grade] = $studentModel->where('grade_level', $grade)
                ->where('enrollment_status', 'enrolled')
                ->countAllResults();
        }

        // Enrollment status distribution
        $statusDistribution = [
            'enrolled' => $studentModel->where('enrollment_status', 'enrolled')->countAllResults(),
            'pending'  => $studentModel->where('enrollment_status', 'pending')->countAllResults(),
            'approved' => $studentModel->where('enrollment_status', 'approved')->countAllResults(),
            'rejected' => $studentModel->where('enrollment_status', 'rejected')->countAllResults(),
        ];

        // Calculate metrics
        $total = array_sum($statusDistribution);
        $metrics = [
            'completionRate' => $total > 0 ? round(($statusDistribution['enrolled'] / $total) * 100) : 0,
            'pendingRate'    => $total > 0 ? round(($statusDistribution['pending'] / $total) * 100) : 0,
            'approvalRate'   => ($statusDistribution['approved'] + $statusDistribution['pending']) > 0
                                ? round(($statusDistribution['approved'] / ($statusDistribution['approved'] + $statusDistribution['pending'])) * 100)
                                : 0,
            'genderBalance'  => ($genderDistribution['male'] + $genderDistribution['female']) > 0
                                ? abs($genderDistribution['male'] - $genderDistribution['female'])
                                : 0,
        ];

        helper('school_year');
        $gradeAverages = [];
        $currentTerm = get_current_term();
        $schoolYear = get_current_school_year();
        foreach (grade_level_options() as $grade) {
            $result = $db->query(
                "SELECT AVG(g.grade) as avg_grade 
                 FROM grades g
                 JOIN students s ON g.student_id = s.id
                 WHERE s.grade_level = ? AND g.term = ? AND g.school_year = ? AND s.enrollment_status = 'enrolled'",
                [$grade, $currentTerm, $schoolYear]
            )->getRow();

            $gradeAverages[$grade] = $result && $result->avg_grade ? round((float) $result->avg_grade, 1) : 0;
        }

        $teacherModel = new TeacherModel();
        $activeTeachers = $teacherModel->where('employment_status', 'active')->countAllResults();
        $teachersWithSections = $teacherModel
            ->where('employment_status', 'active')
            ->where('id IN (SELECT DISTINCT adviser_id FROM sections WHERE adviser_id IS NOT NULL)', null, false)
            ->countAllResults();

        $data = [
            'genderDistribution' => $genderDistribution,
            'gradeDistribution' => $gradeDistribution,
            'statusDistribution' => $statusDistribution,
            'metrics' => $metrics,
            'gradeAverages' => $gradeAverages,
            'reportDate' => date('F j, Y'),
            'schoolYear' => $schoolYear,
            'currentTerm' => $currentTerm,
            'teacherStats' => [
                'active' => $activeTeachers,
                'with_adviser' => $teachersWithSections,
                'without_adviser' => max(0, $activeTeachers - $teachersWithSections),
            ],
        ];

        $html = view('admin/analytics_pdf', $data);
        
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Times');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'CSCS_Analytics_Report_' . date('Y-m-d') . '.pdf';

        return $this->sendPdfInline($dompdf, $filename);
    }

    public function rebalanceGrade($gradeLevel)
    {
        if (!is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $studentModel = new StudentModel();
        $sectionModel = new SectionModel();
        $db = \Config\Database::connect();

        // Get all sections for this grade
        $sections = $sectionModel->where('grade_level', $gradeLevel)
            ->where('is_active', 1)
            ->orderBy('section_name', 'ASC')
            ->findAll();

        if (empty($sections)) {
            return redirect()->back()->with('error', 'No sections found for ' . grade_level_label((int) $gradeLevel));
        }

        // Get all assigned students for this grade
        $students = $studentModel->where('grade_level', $gradeLevel)
            ->where('enrollment_status', 'enrolled')
            ->whereIn('section_id', array_column($sections, 'id'))
            ->orderBy('created_at', 'DESC')
            ->findAll();

        // Clear all section assignments
        foreach ($students as $student) {
            $db->query("UPDATE students SET section_id = NULL WHERE id = ?", [$student['id']]);
        }

        // Redistribute evenly
        $totalCapacity = array_sum(array_column($sections, 'max_capacity'));
        $studentCount = count($students);
        $studentIndex = 0;

        foreach ($sections as $section) {
            $proportion = $section['max_capacity'] / $totalCapacity;
            $assignCount = min(
                (int) round($studentCount * $proportion),
                $section['max_capacity'],
                $studentCount - $studentIndex
            );

            for ($i = 0; $i < $assignCount && $studentIndex < $studentCount; $i++) {
                $db->query(
                    "UPDATE students SET section_id = ?, updated_at = NOW() WHERE id = ?",
                    [$section['id'], $students[$studentIndex]['id']]
                );
                $studentIndex++;
            }

            $sectionModel->updateEnrollmentCount($section['id']);
        }

        return redirect()->back()->with('success', "Rebalanced {$studentIndex} students across " . grade_level_label((int) $gradeLevel) . ' sections');
    }


    public function assignSubjectsToSection()
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $input = json_decode($this->request->getBody(), true);
        $sectionId = $input['section_id'] ?? null;
        $subjectIds = $input['subject_ids'] ?? [];

        if (!$sectionId || empty($subjectIds)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid data']);
        }

        $db = \Config\Database::connect();
        $added = 0;

        foreach ($subjectIds as $subjectId) {
            // Check if already assigned
            $exists = $db->table('section_subjects')
                ->where('section_id', $sectionId)
                ->where('subject_id', $subjectId)
                ->countAllResults();
            
            if ($exists == 0) {
                $db->table('section_subjects')->insert([
                    'section_id' => $sectionId,
                    'subject_id' => $subjectId
                ]);
                $added++;
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => "{$added} subject(s) assigned to section"
        ]);
    }

    public function getAllTeachers()
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $teacherModel = new TeacherModel();
        $teachers = $teacherModel->where('employment_status', 'active')
            ->orderBy('last_name', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'teachers' => $teachers
        ]);
    }

    public function getSubjectAssignments($sectionId)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $db = \Config\Database::connect();

        // Non-numerical sections assign teachers to developmental domains, which
        // live in teacher_schedules.domain_id — joining subjects here would
        // return nothing (or the wrong subject, since ids collide).
        if (is_non_numerical_section($sectionId)) {
            if (! schedule_domain_column_ready()) {
                return $this->response->setJSON([
                    'success'     => true,
                    'assignments' => [],
                    'needs_schema' => true,
                ]);
            }

            $assignments = $db->query(
                "SELECT ts.id as schedule_id, ts.teacher_id, ts.domain_id as subject_id,
                        CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                        sc.name as subject_name, NULL as subject_code
                 FROM teacher_schedules ts
                 JOIN teachers t ON t.id = ts.teacher_id
                 JOIN sned_categories sc ON sc.id = ts.domain_id
                 WHERE ts.section_id = ?",
                [$sectionId]
            )->getResultArray();

            return $this->response->setJSON([
                'success'     => true,
                'assignments' => $assignments,
                'domain_mode' => true,
            ]);
        }

        $assignments = $db->query(
            "SELECT ts.id as schedule_id, ts.teacher_id, ts.subject_id, 
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                    s.subject_name, s.subject_code
             FROM teacher_schedules ts
             JOIN teachers t ON t.id = ts.teacher_id
             JOIN subjects s ON s.id = ts.subject_id
             WHERE ts.section_id = ?",
            [$sectionId]
        )->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'assignments' => $assignments
        ]);
    }

    public function assignSubjectTeacher()
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $teacherId = $this->request->getPost('teacher_id');
        $sectionId = $this->request->getPost('section_id');
        $subjectId = $this->request->getPost('subject_id');

        if (!$teacherId || !$sectionId || !$subjectId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Teacher, section, and subject are required']);
        }

        // Same convention as assignSubjectTeacherOnly(): the item id is a
        // subject for numerical sections and a developmental domain for
        // non-numerical ones, and insertTeacherAssignment() routes it to the
        // correct column (subject_id vs domain_id).
        $result = $this->insertTeacherAssignment(
            (int) $teacherId,
            (int) $sectionId,
            (int) $subjectId,
            'Monday',
            '07:00:00',
            '08:00:00',
            ''
        );

        return $this->response->setJSON([
            'success' => $result['ok'],
            'message' => $result['message'],
        ]);
    }
    
    /**
     * Insert one teacher assignment row.
     *
     * Non-numerical sections assign teachers to developmental domains, which
     * MUST go into teacher_schedules.domain_id: subject_id carries a FOREIGN KEY
     * to subjects and 20 of the 21 current domain ids collide with subject ids,
     * so writing a domain id there would silently attach an unrelated subject.
     *
     * @return array{ok: bool, message: string}
     */
    private function insertTeacherAssignment(
        int $teacherId,
        int $sectionId,
        int $itemId,
        string $dayOfWeek,
        string $startTime,
        string $endTime,
        string $room
    ): array {
        $db = \Config\Database::connect();
        $isDomain = is_non_numerical_section($sectionId);
        $column   = $isDomain ? 'domain_id' : 'subject_id';

        if ($isDomain && ! schedule_domain_column_ready()) {
            return [
                'ok'      => false,
                'message' => 'Your database is missing the teacher_schedules.domain_id column, which is required to '
                    . 'assign teachers to developmental domains. Run the ALTER TABLE statement for domain support, then try again.',
            ];
        }

        $exists = $db->table('teacher_schedules')
            ->where('teacher_id', $teacherId)
            ->where('section_id', $sectionId)
            ->where($column, $itemId)
            ->countAllResults();

        if ($exists > 0) {
            // Already assigned is the requested end state, so report success.
            // Reporting a failure here made the bulk "Assign Teachers" submit
            // say "Failed to assign 1 teacher(s)" even though the teacher was
            // (sometimes through a leftover duplicate row) already assigned.
            return [
                'ok'      => true,
                'message' => $isDomain
                    ? 'Teacher is already assigned to this domain'
                    : 'Teacher is already assigned to this subject',
            ];
        }

        // day_of_week is an ENUM that only recently gained 'TBD'. If this host
        // could not take that change, fall back to a real weekday (with the
        // unscheduled 00:00-00:00 window) rather than letting MySQL truncate it.
        $note = '';
        if (! schedule_day_accepts_tbd()) {
            $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            if (! in_array($dayOfWeek, $validDays, true)) {
                $dayOfWeek = 'Monday';
                $note = ' (saved as "Monday, unscheduled" because the database does not yet allow a TBD weekday)';
            }
        }

        $data = [
            'teacher_id'  => $teacherId,
            'section_id'  => $sectionId,
            'day_of_week' => $dayOfWeek,
            'start_time'  => $startTime,
            'end_time'    => $endTime,
            'room'        => $room,
            'school_year' => get_current_school_year(),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if ($isDomain) {
            $data['domain_id']  = $itemId;
            $data['subject_id'] = null;
        } else {
            $data['subject_id'] = $itemId;
        }

        try {
            if ($db->table('teacher_schedules')->insert($data)) {
                return ['ok' => true, 'message' => 'Teacher assigned successfully' . $note];
            }
        } catch (\Throwable $e) {
            log_message('error', 'insertTeacherAssignment failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Failed to assign teacher: ' . $e->getMessage()];
        }

        return ['ok' => false, 'message' => 'Failed to assign teacher'];
    }

    public function assignSubjectTeacherOnly()
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $teacherId = $this->request->getPost('teacher_id');
        $sectionId = $this->request->getPost('section_id');
        $subjectId = $this->request->getPost('subject_id');

        if (!$teacherId || !$sectionId || !$subjectId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Teacher, section, and subject are required']);
        }

        // Assignment without a timetable yet — same convention as before.
        $result = $this->insertTeacherAssignment(
            (int) $teacherId,
            (int) $sectionId,
            (int) $subjectId,
            'TBD',
            '00:00:00',
            '00:00:00',
            ''
        );

        return $this->response->setJSON(['success' => $result['ok'], 'message' => $result['message']]);
    }
    
    public function getGradeSections($gradeLevel)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }
        
        $sectionModel = new SectionModel();
        $studentModel = new StudentModel();
        
        $sections = $sectionModel->where('grade_level', $gradeLevel)
            ->where('is_active', 1)
            ->orderBy('section_name', 'ASC')
            ->findAll();
        
        // Add current enrollment count to each section
        foreach ($sections as &$section) {
            $section['current_enrollment'] = $studentModel->where('section_id', $section['id'])
                ->where('enrollment_status', 'enrolled')
                ->countAllResults();
        }
        
        return $this->response->setJSON([
            'success' => true,
            'sections' => $sections
        ]);
    }
    
    public function getSectionCapacityInfo($sectionId)
    {
        if (!is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }
        
        $sectionModel = new SectionModel();
        $studentModel = new StudentModel();
        
        $section = $sectionModel->find($sectionId);
        if (!$section) {
            return $this->response->setJSON(['success' => false, 'message' => 'Section not found']);
        }
        
        $currentEnrollment = $studentModel->where('section_id', $sectionId)
            ->where('enrollment_status', 'enrolled')
            ->countAllResults();
        
        $section['current_enrollment'] = $currentEnrollment;
        
        return $this->response->setJSON([
            'success' => true,
            'section' => $section
        ]);
    }
}

