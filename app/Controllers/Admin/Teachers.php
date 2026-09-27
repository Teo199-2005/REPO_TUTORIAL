<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\TeacherModel;
use App\Models\SystemSettingModel;
use CodeIgniter\Shield\Models\UserModel;
use App\Models\SectionModel;
use App\Models\TeacherScheduleModel;
use App\Models\SubjectModel;

class Teachers extends BaseController
{
    /**
     * Display teachers list
     */
    public function index()
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $teacherModel = model(TeacherModel::class);
        $db = \Config\Database::connect();
        
        $search = $this->request->getGet('search');
        $assignment = $this->request->getGet('assignment');
        $sortBy = $this->request->getGet('sort_by');
        $sortOrder = strtolower((string) $this->request->getGet('sort_order')) === 'desc' ? 'DESC' : 'ASC';

        // Multi-filter fields - every set filter narrows the list further (AND).
        $status     = trim((string) $this->request->getGet('status'));
        $position   = trim((string) $this->request->getGet('position'));
        $department = trim((string) $this->request->getGet('department'));
        $gender     = trim((string) $this->request->getGet('gender'));
        $religion   = trim((string) $this->request->getGet('religion'));
        $grade      = trim((string) $this->request->getGet('grade'));

        if (! in_array($sortBy, ['name', 'age'], true)) {
            $sortBy = 'name';
        }
        
        // Get all teachers
        $builder = $teacherModel->select('teachers.*');

        // Teacher self-registrations awaiting approval (or rejected) are handled
        // on the Pending Registrations page, not in the main faculty list.
        $builder->groupStart()
                ->where('teachers.registration_status IS NULL')
                ->orWhere('teachers.registration_status', 'approved')
                ->groupEnd();
        
        if ($search) {
            $builder->groupStart()
                   ->like('teachers.first_name', $search)
                   ->orLike('teachers.last_name', $search)
                   ->orLike('teachers.license_number', $search)
                   ->orLike('teachers.email', $search)
                   ->orLike('teachers.government_employee_no', $search)
                   ->orLike('teachers.tin', $search)
                   ->orLike('teachers.philsys_number', $search)
                   ->groupEnd();
        }

        if ($status !== '') {
            $builder->where('teachers.employment_status', $status);
        }

        if ($position !== '') {
            $builder->where('teachers.position', $position);
        }

        if ($department !== '') {
            $builder->where('teachers.department', $department);
        }

        if ($gender === 'Male' || $gender === 'Female') {
            $builder->where('teachers.gender', $gender);
        }

        if ($religion !== '') {
            $builder->where('teachers.religion', $religion);
        }

        // Grade level handled: advisory class OR any scheduled teaching section
        // in that grade. EXISTS sub-selects keep everything in one findAll().
        if ($grade !== '' && ctype_digit($grade)) {
            $gradeInt = (int) $grade;
            $builder->groupStart()
                    ->where("EXISTS (SELECT 1 FROM sections adv WHERE adv.adviser_id = teachers.id AND adv.grade_level = {$gradeInt})", null, false)
                    ->orWhere("EXISTS (SELECT 1 FROM teacher_schedules ts2 JOIN sections sec2 ON sec2.id = ts2.section_id WHERE ts2.teacher_id = teachers.id AND sec2.grade_level = {$gradeInt})", null, false)
                    ->groupEnd();
        }
        
        if ($sortBy === 'age') {
            // Age ASC = younger first (newer DOB); DESC = older first (earlier DOB).
            $builder->where('teachers.date_of_birth IS NOT NULL');
            if ($sortOrder === 'ASC') {
                $builder->orderBy('teachers.date_of_birth', 'DESC');
            } else {
                $builder->orderBy('teachers.date_of_birth', 'ASC');
            }
            $builder->orderBy('teachers.last_name', 'ASC')
                   ->orderBy('teachers.first_name', 'ASC');
        } else {
            $builder->orderBy('teachers.last_name', $sortOrder)
                   ->orderBy('teachers.first_name', $sortOrder);
        }

        $teachers = $builder->findAll();
        
        // Get advisory sections and teaching sections for each teacher
        foreach ($teachers as &$teacher) {
            // Calculate age from date of birth for table display.
            $teacher['age'] = null;
            if (! empty($teacher['date_of_birth'])) {
                try {
                    $dob = new \DateTime((string) $teacher['date_of_birth']);
                    $today = new \DateTime('today');
                    $teacher['age'] = $dob->diff($today)->y;
                } catch (\Throwable $e) {
                    $teacher['age'] = null;
                }
            }

            // Get advisory section
            $advisorySection = $db->table('sections')
                ->select('section_name, grade_level')
                ->where('adviser_id', $teacher['id'])
                ->get()->getRow();
            
            $teacher['section_name'] = $advisorySection->section_name ?? null;
            $teacher['grade_level'] = $advisorySection->grade_level ?? null;
            
            // Get teaching sections (from teacher_schedules)
            $teachingSections = $db->query(
                "SELECT DISTINCT s.section_name, s.grade_level, sub.subject_name
                 FROM teacher_schedules ts 
                 JOIN sections s ON s.id = ts.section_id 
                 JOIN subjects sub ON sub.id = ts.subject_id
                 WHERE ts.teacher_id = ? AND s.adviser_id != ?",
                [$teacher['id'], $teacher['id']]
            )->getResultArray();
            
            $teacher['teaching_sections'] = $teachingSections;
        }
        
        // Apply assignment filter
        if ($assignment === 'assigned') {
            $teachers = array_filter($teachers, fn($t) => !empty($t['section_name']));
        } elseif ($assignment === 'unassigned') {
            $teachers = array_filter($teachers, fn($t) => empty($t['section_name']));
        }

        $pendingRegistrationCount = (int) $db->table('teachers')
            ->where('registration_status', 'pending')
            ->where('deleted_at', null)
            ->countAllResults();

        // Global personnel-record editing switch (applies to ALL teachers).
        $settingModel = model(SystemSettingModel::class);
        $personnelEditGlobal = (int) ($settingModel->getSetting('personnel_edit_global', '1')) === 1;

        // Filter dropdown values reflect the data actually recorded, so new
        // positions / departments / religions appear without code changes.
        $statusOptions = array_column($db->query(
            "SELECT DISTINCT employment_status AS v FROM teachers WHERE deleted_at IS NULL AND employment_status IS NOT NULL AND employment_status <> '' ORDER BY employment_status ASC"
        )->getResultArray(), 'v');
        $positionOptions = array_column($db->query(
            "SELECT DISTINCT position AS v FROM teachers WHERE deleted_at IS NULL AND position IS NOT NULL AND position <> '' ORDER BY position ASC"
        )->getResultArray(), 'v');
        $departmentOptions = array_column($db->query(
            "SELECT DISTINCT department AS v FROM teachers WHERE deleted_at IS NULL AND department IS NOT NULL AND department <> '' ORDER BY department ASC"
        )->getResultArray(), 'v');
        $religionOptions = array_column($db->query(
            "SELECT DISTINCT religion AS v FROM teachers WHERE deleted_at IS NULL AND religion IS NOT NULL AND religion <> '' ORDER BY religion ASC"
        )->getResultArray(), 'v');

        return view('admin/teachers', [
            'title' => 'Manage Teachers - CSCS Tap n Track',
            'teachers' => $teachers,
            'search' => $search,
            'assignment' => $assignment,
            'sortBy' => $sortBy,
            'sortOrder' => strtolower($sortOrder),
            'status' => $status,
            'position' => $position,
            'department' => $department,
            'gender' => $gender,
            'religion' => $religion,
            'grade' => $grade,
            'statusOptions' => $statusOptions,
            'positionOptions' => $positionOptions,
            'departmentOptions' => $departmentOptions,
            'religionOptions' => $religionOptions,
            'pendingRegistrationCount' => $pendingRegistrationCount,
            'personnelEditGlobal' => $personnelEditGlobal,
        ]);
    }

    /**
     * Export the filtered faculty list as a PDF. Mirrors the list page: every
     * row matching the active filters.
     */
    public function exportPdf()
    {
        if (! auth()->user() || ! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $data = [
            'teachers'       => $this->exportRows(),
            'reportDate'     => date('F j, Y'),
            'schoolYear'     => get_current_school_year(),
            'filtersSummary' => $this->filtersSummary(),
        ];

        $html = view('admin/teachers_pdf', $data);

        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Times');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $this->sendPdfInline($dompdf, 'CSCS_Teachers_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Human-readable filter summary printed under the PDF letterhead.
     */
    private function filtersSummary(): string
    {
        $parts = [];

        $search = trim((string) $this->request->getGet('search'));
        if ($search !== '') {
            $parts[] = 'Search: ' . $search;
        }

        $assignment = $this->request->getGet('assignment');
        if ($assignment === 'assigned') {
            $parts[] = 'With Advisory Class';
        } elseif ($assignment === 'unassigned') {
            $parts[] = 'No Advisory Class';
        }

        $status = trim((string) $this->request->getGet('status'));
        if ($status !== '') {
            $parts[] = 'Status: ' . ucfirst(str_replace('_', ' ', $status));
        }

        $position = trim((string) $this->request->getGet('position'));
        if ($position !== '') {
            $parts[] = 'Position: ' . $position;
        }

        $department = trim((string) $this->request->getGet('department'));
        if ($department !== '') {
            $parts[] = 'Department: ' . $department;
        }

        $grade = trim((string) $this->request->getGet('grade'));
        if ($grade !== '' && ctype_digit($grade)) {
            $parts[] = 'Grade: ' . grade_level_label((int) $grade);
        }

        $gender = trim((string) $this->request->getGet('gender'));
        if ($gender === 'Male' || $gender === 'Female') {
            $parts[] = 'Sex: ' . $gender;
        }

        $religion = trim((string) $this->request->getGet('religion'));
        if ($religion !== '') {
            $parts[] = 'Religion: ' . $religion;
        }

        return $parts === [] ? 'All faculty' : implode(' | ', $parts);
    }

    /**
     * Every row matching the current filters, with the same advisory-section
     * enrichment and assignment (advisory class) filter the list page applies.
     *
     * @return list<array<string, mixed>>
     */
    private function exportRows(): array
    {
        $teacherModel = model(TeacherModel::class);
        $db = \Config\Database::connect();

        $search = $this->request->getGet('search');
        $assignment = $this->request->getGet('assignment');
        $sortBy = $this->request->getGet('sort_by');
        $sortOrder = strtolower((string) $this->request->getGet('sort_order')) === 'desc' ? 'DESC' : 'ASC';

        $status = trim((string) $this->request->getGet('status'));
        $position = trim((string) $this->request->getGet('position'));
        $department = trim((string) $this->request->getGet('department'));
        $gender = trim((string) $this->request->getGet('gender'));
        $religion = trim((string) $this->request->getGet('religion'));
        $grade = trim((string) $this->request->getGet('grade'));

        if (! in_array($sortBy, ['name', 'age'], true)) {
            $sortBy = 'name';
        }

        $builder = $teacherModel->select('teachers.*');

        // Pending registrations have their own page (same as index()).
        $builder->groupStart()
                ->where('teachers.registration_status IS NULL')
                ->orWhere('teachers.registration_status', 'approved')
                ->groupEnd();

        if ($search) {
            $builder->groupStart()
                    ->like('teachers.first_name', $search)
                    ->orLike('teachers.last_name', $search)
                    ->orLike('teachers.license_number', $search)
                    ->orLike('teachers.email', $search)
                    ->orLike('teachers.government_employee_no', $search)
                    ->orLike('teachers.tin', $search)
                    ->orLike('teachers.philsys_number', $search)
                    ->groupEnd();
        }

        if ($status !== '') {
            $builder->where('teachers.employment_status', $status);
        }

        if ($position !== '') {
            $builder->where('teachers.position', $position);
        }

        if ($department !== '') {
            $builder->where('teachers.department', $department);
        }

        if ($gender === 'Male' || $gender === 'Female') {
            $builder->where('teachers.gender', $gender);
        }

        if ($religion !== '') {
            $builder->where('teachers.religion', $religion);
        }

        // Grade level handled: advisory class OR any scheduled teaching section
        // in that grade (same EXISTS sub-selects as index()).
        if ($grade !== '' && ctype_digit($grade)) {
            $gradeInt = (int) $grade;
            $builder->groupStart()
                    ->where("EXISTS (SELECT 1 FROM sections adv WHERE adv.adviser_id = teachers.id AND adv.grade_level = {$gradeInt})", null, false)
                    ->orWhere("EXISTS (SELECT 1 FROM teacher_schedules ts2 JOIN sections sec2 ON sec2.id = ts2.section_id WHERE ts2.teacher_id = teachers.id AND sec2.grade_level = {$gradeInt})", null, false)
                    ->groupEnd();
        }

        if ($sortBy === 'age') {
            // Age ASC = younger first (newer DOB); DESC = older first (earlier DOB).
            $builder->where('teachers.date_of_birth IS NOT NULL');
            if ($sortOrder === 'ASC') {
                $builder->orderBy('teachers.date_of_birth', 'DESC');
            } else {
                $builder->orderBy('teachers.date_of_birth', 'ASC');
            }
            $builder->orderBy('teachers.last_name', 'ASC')
                   ->orderBy('teachers.first_name', 'ASC');
        } else {
            $builder->orderBy('teachers.last_name', $sortOrder)
                   ->orderBy('teachers.first_name', $sortOrder);
        }

        $teachers = $builder->findAll();

        foreach ($teachers as &$teacher) {
            // Age for the PDF column (same derivation as the list page).
            $teacher['age'] = null;
            if (! empty($teacher['date_of_birth'])) {
                try {
                    $dob = new \DateTime((string) $teacher['date_of_birth']);
                    $today = new \DateTime('today');
                    $teacher['age'] = $dob->diff($today)->y;
                } catch (\Throwable $e) {
                    $teacher['age'] = null;
                }
            }

            // Advisory class for the PDF column.
            $advisorySection = $db->table('sections')
                ->select('section_name, grade_level')
                ->where('adviser_id', $teacher['id'])
                ->get()->getRow();

            $teacher['section_name'] = $advisorySection->section_name ?? null;
            $teacher['grade_level'] = $advisorySection->grade_level ?? null;
        }
        unset($teacher);

        // Assignment filter is applied in PHP on the list page too (advisory class only).
        if ($assignment === 'assigned') {
            $teachers = array_filter($teachers, fn($t) => !empty($t['section_name']));
        } elseif ($assignment === 'unassigned') {
            $teachers = array_filter($teachers, fn($t) => empty($t['section_name']));
        }

        return array_values($teachers);
    }

    /**
     * Show create teacher form
     */
    public function create()
    {
        // Check if user is admin
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        return view('admin/teachers_create', [
            'title' => 'Add New Teacher - CSCS Tap n Track'
        ]);
    }

    /**
     * Store new teacher
     */
    public function store()
    {
        // Check if user is admin
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        helper('teacher_form');

        // Normalise legacy phone formats before validation and the writes below.
        phone_normalize_request($this->request, ['contact_number']);

        // Religion's "Other" choice is swapped for its typed text before the
        // rules run, so the stored value is the real religion (same as students).
        religion_normalize_request($this->request);

        $rules = teacher_store_validation_rules(true);

        if (!$this->validate($rules, teacher_store_validation_messages())) {
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $personnel = teacher_collect_personnel_from_request($this->request);
        if (empty($personnel['date_of_birth'])) {
            return redirect()->back()->withInput()->with('error', 'Please enter a valid date of birth.');
        }

        $userModel = model(UserModel::class);
        $teacherModel = model(TeacherModel::class);

        // Start database transaction
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Create user record
            $db->table('users')->insert([
                'email' => $this->request->getPost('email'),
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            $userId = $db->insertID();
            if (!$userId) {
                throw new \Exception('Failed to create user account');
            }

            // Create password hash and auth identity.
            // Canonical Shield format: secret = email, secret2 = password hash.
            $hashedPassword = password_hash($this->request->getPost('password'), PASSWORD_DEFAULT);
            $teacherEmail   = str_replace('mailto:', '', trim((string) $this->request->getPost('email')));
            $db->table('auth_identities')->insert([
                'user_id' => $userId,
                'type' => 'email_password',
                'name' => $teacherEmail,
                'secret' => $teacherEmail,
                'secret2' => $hashedPassword,
                'expires' => null,
                'extra' => null,
                'force_reset' => 0,
                'last_used_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Add user to teacher group
            $db->table('auth_groups_users')->insert([
                'user_id' => $userId,
                'group' => 'teacher',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $teacherData = array_merge($personnel, [
                'user_id'     => $userId,
                'email'       => $this->request->getPost('email'),
            ]);

            $teacherModel->skipValidation(true);
            if (!$teacherModel->save($teacherData)) {
                $teacherModel->skipValidation(false);
                throw new \Exception('Failed to create teacher record: ' . implode(', ', $teacherModel->errors()));
            }
            $teacherModel->skipValidation(false);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Transaction failed');
            }

            audit_event('teacher.created', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'teacher',
                'resource_id'   => (string) ($teacherModel->getInsertID() ?: $userId),
                'description'   => 'Teacher created from the admin portal',
                'after'         => [
                    'name'  => trim((string) $this->request->getPost('first_name') . ' ' . (string) $this->request->getPost('last_name')),
                    'email' => (string) $this->request->getPost('email'),
                ],
            ]);

            return redirect()->to('admin/teachers')
                ->with('success', 'Teacher created successfully.');

        } catch (\Exception $e) {
            $db->transRollback();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create teacher: ' . $e->getMessage());
        }
    }

    /**
     * Show teacher self-registrations awaiting approval.
     */
    public function pending()
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $teacherModel = model(TeacherModel::class);

        $pendingTeachers = $teacherModel
            ->select('teachers.*, users.email as user_email, users.active as user_active, users.created_at as registered_at')
            ->join('users', 'users.id = teachers.user_id', 'left')
            ->where('teachers.registration_status', 'pending')
            ->orderBy('teachers.created_at', 'DESC')
            ->findAll();

        return view('admin/teachers_pending', [
            'title'           => 'Pending Teacher Registrations - CSCS Tap n Track',
            'pendingTeachers' => $pendingTeachers,
        ]);
    }

    /**
     * JSON count of pending teacher registrations (for the sidebar badge).
     */
    public function getPendingCount()
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $count = model(TeacherModel::class)
            ->where('registration_status', 'pending')
            ->countAllResults();

        return $this->response->setJSON(['count' => $count]);
    }

    /**
     * Approve a teacher self-registration: activate the login account and
     * attach the 'teacher' group.
     */
    public function approve($teacherId)
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $teacherModel = model(TeacherModel::class);
        $teacher = $teacherModel->find($teacherId);

        if (! $teacher) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Teacher not found']);
        }

        if (($teacher['registration_status'] ?? '') !== 'pending') {
            return $this->response->setStatusCode(422)
                ->setJSON(['error' => 'Only pending registrations can be approved.']);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $userId = (int) ($teacher['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new \Exception('This registration has no linked login account.');
            }

            // Activate the login account
            $db->table('users')->where('id', $userId)->update([
                'active'     => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Attach the teacher group (idempotent)
            $hasGroup = $db->table('auth_groups_users')
                ->where('user_id', $userId)
                ->where('group', 'teacher')
                ->countAllResults() > 0;

            if (! $hasGroup) {
                $db->table('auth_groups_users')->insert([
                    'user_id'    => $userId,
                    'group'      => 'teacher',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $teacherModel->skipValidation(true);
            $teacherModel->update($teacherId, [
                'registration_status'      => 'approved',
                'registration_reviewed_at' => date('Y-m-d H:i:s'),
                'registration_reviewed_by' => (int) (auth()->id() ?? 0) ?: null,
                'employment_status'        => 'active',
            ]);
            $teacherModel->skipValidation(false);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Transaction failed');
            }

            log_message('info', 'Teacher registration approved: teacher id ' . $teacherId);

            $name = trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? ''));

            audit_event('teacher.approved', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'teacher',
                'resource_id'   => (string) $teacherId,
                'description'   => 'Teacher registration approved',
                'metadata'      => ['name' => $name, 'email' => $teacher['email'] ?? null],
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Registration approved. ' . ($name !== '' ? $name : 'This teacher') . ' can now sign in.',
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();

            log_message('error', 'Teacher registration approval failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)
                ->setJSON(['error' => 'Failed to approve registration.']);
        }
    }

    /**
     * Reject a teacher self-registration. The login account stays inactive.
     */
    public function reject($teacherId)
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $teacherModel = model(TeacherModel::class);
        $teacher = $teacherModel->find($teacherId);

        if (! $teacher) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Teacher not found']);
        }

        if (($teacher['registration_status'] ?? '') !== 'pending') {
            return $this->response->setStatusCode(422)
                ->setJSON(['error' => 'Only pending registrations can be rejected.']);
        }

        $teacherModel->skipValidation(true);
        $teacherModel->update($teacherId, [
            'registration_status'      => 'rejected',
            'registration_reviewed_at' => date('Y-m-d H:i:s'),
            'registration_reviewed_by' => (int) (auth()->id() ?? 0) ?: null,
            'employment_status'        => 'inactive',
        ]);
        $teacherModel->skipValidation(false);

        // Keep the login account disabled and detach the teacher group so the
        // applicant cannot sign in or reach any teacher portal route.
        $userId = (int) ($teacher['user_id'] ?? 0);
        if ($userId > 0) {
            $db = \Config\Database::connect();

            $db->table('users')->where('id', $userId)->update([
                'active'     => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $db->table('auth_groups_users')
                ->where('user_id', $userId)
                ->where('group', 'teacher')
                ->delete();
        }

        log_message('info', 'Teacher registration rejected: teacher id ' . $teacherId);

        audit_event('teacher.rejected', [
            'category'      => 'data',
            'status'        => 'success',
            'resource_type' => 'teacher',
            'resource_id'   => (string) $teacherId,
            'description'   => 'Teacher registration rejected',
            'metadata'      => [
                'name'  => trim((string) ($teacher['first_name'] ?? '') . ' ' . (string) ($teacher['last_name'] ?? '')),
                'email' => $teacher['email'] ?? null,
            ],
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Teacher registration rejected.',
        ]);
    }

    /**
     * Show edit teacher form
     */
    public function edit($teacherId)
    {
        // Check if user is admin
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $teacherModel = model(TeacherModel::class);

        // Get teacher with user details - using WHERE clause instead of find().
        // The account email lives in users.email but older/linked-less records only
        // have teachers.email, and a LEFT JOIN would null it out; fall back to the
        // teachers column so the form never renders a blank (and therefore
        // unsavable) email field.
        $teacher = $teacherModel->select(
                "teachers.*, COALESCE(NULLIF(users.email, ''), teachers.email) AS email",
                false
            )
            ->join('users', 'users.id = teachers.user_id', 'left')
            ->where('teachers.id', $teacherId)
            ->first();

        if (!$teacher) {
            return redirect()->to('admin/teachers')
                ->with('error', 'Teacher not found.');
        }

        return view('admin/teachers_edit', [
            'title' => 'Edit Teacher - CSCS Tap n Track',
            'teacher' => $teacher
        ]);
    }

    /**
     * Get teacher edit form for modal
     */
    public function editForm($teacherId)
    {
        // Check if user is admin
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized access']);
        }

        // Validate teacher ID
        if (!is_numeric($teacherId) || $teacherId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid teacher ID']);
        }

        $teacherModel = model(TeacherModel::class);
        
        try {
            // Check if teacher exists without join first
            $teacherExists = $teacherModel->find($teacherId);
            if (!$teacherExists) {
                return $this->response->setStatusCode(404)->setJSON(['error' => 'Teacher not found']);
            }

            // Get teacher with user details - using WHERE clause instead of find().
            // Fall back to teachers.email when the linked account has no email (see
            // edit() above) so the modal's required Email field is always populated.
            $teacher = $teacherModel->select(
                    "teachers.*, COALESCE(NULLIF(users.email, ''), teachers.email) AS email",
                    false
                )
                ->join('users', 'users.id = teachers.user_id', 'left')
                ->where('teachers.id', $teacherId)
                ->first();

            if (!$teacher) {
                return $this->response->setStatusCode(404)->setJSON(['error' => 'Teacher data could not be loaded']);
            }

            return view('admin/partials/teacher_edit_form', [
                'teacher' => $teacher
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error loading teacher edit form: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Server error occurred while loading teacher data']);
        }
    }

    /**
     * Update teacher
     */
    public function update($teacherId)
    {
        try {
            if (! is_any_admin()) {
                return $this->respondUpdate(false, 'Unauthorized', [], 403);
            }

            helper('teacher_form');
            $teacherModel = model(TeacherModel::class);
            $teacher = $teacherModel->find($teacherId);

            if (! $teacher) {
                return $this->respondUpdate(false, 'Teacher not found', [], 404);
            }

            // Normalise legacy phone formats before validation and the writes below.
            phone_normalize_request($this->request, ['contact_number']);

            // Religion's "Other" choice is swapped for its typed text before the
            // rules run, so validation and the write see the real religion.
            religion_normalize_request($this->request);

            $rules = teacher_store_validation_rules(false);
            $newEmail = $this->request->getPost('email');
            if ($newEmail && $newEmail !== $teacher['email']) {
                $rules['email'] = "required|valid_email|is_unique[teachers.email,id,{$teacherId}]";
            } else {
                $rules['email'] = 'required|valid_email';
            }

            if (! $this->validate($rules, teacher_store_validation_messages())) {
                return $this->respondUpdate(false, 'Validation failed', $this->validator->getErrors(), 422);
            }

            $personnel = teacher_collect_personnel_from_request($this->request);
            if (empty($personnel['date_of_birth'])) {
                return $this->respondUpdate(false, 'Validation failed', [
                    'birth_day' => 'Please enter a valid date of birth.',
                ], 422);
            }

            $db = \Config\Database::connect();
            $db->transStart();

            $teacherData = $personnel;
            if ($newEmail) {
                $teacherData['email'] = $newEmail;
                if ($newEmail !== $teacher['email']) {
                    $db->table('users')->where('id', $teacher['user_id'])->update(['email' => $newEmail]);
                }
            }

            $teacherModel->skipValidation(true);
            $teacherModel->update($teacherId, $teacherData);
            $teacherModel->skipValidation(false);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->respondUpdate(false, 'Failed to update teacher data.');
            }

            $trackedFields = [
                'first_name', 'last_name', 'middle_name', 'email', 'contact_number', 'address',
                'gender', 'date_of_birth', 'civil_status', 'position', 'employment_status',
                'license_number', 'employee_id', 'baccalaureate_degree', 'masters_degree',
            ];
            $beforeSnapshot = array_intersect_key((array) $teacher, array_flip($trackedFields));
            $afterSnapshot  = array_intersect_key((array) ($teacherModel->find($teacherId) ?? $teacherData), array_flip($trackedFields));

            audit_event('teacher.updated', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'teacher',
                'resource_id'   => (string) $teacherId,
                'description'   => 'Teacher record updated',
            ] + audit_diff($beforeSnapshot, $afterSnapshot, $trackedFields));

            return $this->respondUpdate(true, 'Teacher updated successfully.');
        } catch (\Exception $e) {
            log_message('error', 'Teacher update error: ' . $e->getMessage());

            return $this->respondUpdate(false, 'Server error occurred: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * JSON for AJAX modal updates; redirect for full-page edit form.
     */
    private function respondUpdate(bool $success, string $message, array $errors = [], int $status = 200)
    {
        $isAjax = $this->request->isAJAX()
            || str_contains((string) $this->request->getHeaderLine('Accept'), 'application/json');

        if ($isAjax) {
            $payload = ['success' => $success, 'message' => $message];
            if (! $success) {
                $payload['error'] = $message;
            }
            if ($errors !== []) {
                $payload['errors'] = $errors;
            }

            return $this->response->setStatusCode($status)->setJSON($payload);
        }

        if ($success) {
            return redirect()->to('admin/teachers')->with('success', $message);
        }

        return redirect()->back()->withInput()->with('error', $message)->with('errors', $errors);
    }



    /**
     * Toggle whether the teacher may edit their own Personnel Record
     * (teacher/profile -> Personnel Record tab).
     */
    public function toggleGlobalPersonnelEdit()
    {
        try {
            if (! is_any_admin()) {
                return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
            }

            $settingModel = model(SystemSettingModel::class);
            $newState = ((int) ($settingModel->getSetting('personnel_edit_global', '1')) === 1) ? 0 : 1;
            $settingModel->setSetting('personnel_edit_global', (string) $newState, 'Global personnel record editing switch for all teachers');

            // Keep every teacher's per-teacher flag in sync so the teacher
            // portal's check (Teacher\Profile::updatePersonnel) follows the
            // same switch. The column is self-healed because some deployments
            // predate the migration.
            $db = \Config\Database::connect();
            $synced = $this->ensurePersonnelEditColumn();
            if ($synced) {
                $db->table('teachers')->update(['personnel_edit_enabled' => $newState]);
            }

            log_message('info', 'Personnel record editing ' . ($newState ? 'ENABLED' : 'DISABLED') . ' for ALL teachers by admin.');

            audit_event('settings.personnel_edit_toggled', [
                'category'      => 'settings',
                'status'        => 'success',
                'resource_type' => 'setting',
                'resource_id'   => 'personnel_edit_global',
                'description'   => 'Personnel record editing ' . ($newState ? 'enabled' : 'disabled') . ' for all teachers',
            ] + audit_diff(
                ['personnel_edit_global' => $newState ? '0' : '1'],
                ['personnel_edit_global' => (string) $newState],
                ['personnel_edit_global']
            ));

            return $this->response->setJSON([
                'success' => true,
                'enabled' => (bool) $newState,
                'synced'  => $synced,
                'message' => $newState
                    ? 'Personnel record editing is now ENABLED for all teachers.'
                    : 'Personnel record editing is now DISABLED for all teachers.',
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Global toggle personnel edit error: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to toggle personnel record editing.']);
        }
    }

    /**
     * Self-healing: make sure teachers.personnel_edit_enabled exists (older
     * live databases may predate the migration and hosts may block DDL).
     */
    private function ensurePersonnelEditColumn(): bool
    {
        $db = \Config\Database::connect();
        foreach ($db->getFieldData('teachers') as $field) {
            if (($field->name ?? '') === 'personnel_edit_enabled') {
                return true;
            }
        }
        try {
            $db->query("ALTER TABLE `teachers` ADD COLUMN `personnel_edit_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `employment_status`");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Could not add personnel_edit_enabled column: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete teacher
     */
    public function delete($teacherId)
    {
        // Check if user is admin
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $teacherModel = model(TeacherModel::class);
        $userModel = model(UserModel::class);

        // withDeleted() so rows that were only soft-deleted in the past can
        // still be found and permanently purged.
        $teacher = $teacherModel->withDeleted()->find($teacherId);
        if (!$teacher) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Teacher not found']);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Detach the teacher from any sections they advise. sections.adviser_id
            // has no foreign key, so it must be cleared explicitly or the row
            // would point at a teacher that no longer exists.
            $db->table('sections')
                ->where('adviser_id', $teacherId)
                ->update(['adviser_id' => null, 'updated_at' => date('Y-m-d H:i:s')]);

            // Permanently remove the teacher row. The second argument forces a
            // hard DELETE instead of a soft delete (deleted_at stamp), so the
            // row is gone from the database. attendance/quizzes/teacher_schedules
            // rows cascade via foreign keys.
            $teacherModel->delete($teacherId, true);

            // Permanently remove the login account (auth_* tables cascade).
            if (! empty($teacher['user_id'])) {
                $userModel->delete((int) $teacher['user_id'], true);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to delete teacher.']);
            }

            log_message('info', 'Teacher permanently deleted: ID ' . $teacherId . ' by admin.');

            audit_event('teacher.deleted', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'teacher',
                'resource_id'   => (string) $teacherId,
                'description'   => 'Teacher permanently deleted',
                'before'        => [
                    'name'  => trim((string) ($teacher['first_name'] ?? '') . ' ' . (string) ($teacher['last_name'] ?? '')),
                    'email' => $teacher['email'] ?? null,
                ],
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Teacher deleted successfully.'
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Teacher delete error: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Failed to delete teacher: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * View teacher details as full page
     */
    public function viewTeacher($teacherId)
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $teacherModel = model(TeacherModel::class);
        $sectionModel = model(SectionModel::class);

        // Get teacher details
        $teacher = $teacherModel->where('id', $teacherId)->first();

        if (!$teacher) {
            return redirect()->to('admin/teachers')->with('error', 'Teacher not found');
        }

        // Get sections assigned to this teacher
        $sections = $sectionModel->where('adviser_id', $teacherId)->findAll();

        return view('admin/teacher_view', [
            'title' => 'Teacher Details - CSCS Tap n Track',
            'teacher' => $teacher,
            'sections' => $sections
        ]);
    }

    /**
     * Get teacher details for modal display
     */
    public function getTeacherDetails($teacherId)
    {
        // Check if user is admin
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $teacherModel = model(TeacherModel::class);
        $sectionModel = model(SectionModel::class);

        // Get teacher details - using WHERE clause instead of find()
        $teacher = $teacherModel->where('id', $teacherId)->first();

        if (!$teacher) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Teacher not found']);
        }

        // Get sections assigned to this teacher
        $sections = $sectionModel->where('adviser_id', $teacherId)->findAll();

        return view('admin/partials/teacher_details_modal', [
            'teacher' => $teacher,
            'sections' => $sections
        ]);
    }

    /**
     * Manage teacher schedule
     */
    public function schedule($teacherId)
    {
        if (!auth()->user() || ! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $teacherModel = model(TeacherModel::class);
        $scheduleModel = model(TeacherScheduleModel::class);
        $subjectModel = model(SubjectModel::class);
        $sectionModel = model(SectionModel::class);

        $teacher = $teacherModel->find($teacherId);
        if (!$teacher) {
            return redirect()->to('admin/teachers')->with('error', 'Teacher not found');
        }

        $schedules = $scheduleModel->getTeacherSchedule($teacherId);
        
        // Get unique section-subject combinations assigned to this teacher
        $db = \Config\Database::connect();
        
        // Get assignments from teacher_schedules
        $assignments = $db->query("
            SELECT DISTINCT ts.section_id, ts.subject_id, 
                   s.section_name, s.grade_level, 
                   sub.subject_name
            FROM teacher_schedules ts
            JOIN sections s ON s.id = ts.section_id
            JOIN subjects sub ON sub.id = ts.subject_id
            WHERE ts.teacher_id = ? AND ts.school_year = ?
        ", [$teacherId, get_current_school_year()])->getResultArray();
        
        // Also get advisory section with all its subjects
        $advisorySection = $db->query("
            SELECT s.id as section_id, s.section_name, s.grade_level
            FROM sections s
            WHERE s.adviser_id = ?
        ", [$teacherId])->getRow();
        
        if ($advisorySection) {
            // Get all subjects for the advisory section's grade level
            $advisorySubjects = $db->query("
                SELECT id as subject_id, subject_name
                FROM subjects
                WHERE grade_level = ? AND is_active = 1
            ", [$advisorySection->grade_level])->getResultArray();
            
            // Add advisory section-subject combinations to assignments
            foreach ($advisorySubjects as $subject) {
                $assignments[] = [
                    'section_id' => $advisorySection->section_id,
                    'subject_id' => $subject['subject_id'],
                    'section_name' => $advisorySection->section_name,
                    'grade_level' => $advisorySection->grade_level,
                    'subject_name' => $subject['subject_name']
                ];
            }
        }
        
        // Extract unique sections and subjects from assignments
        $sectionIds = [];
        $subjectIds = [];
        $assignedCombinations = [];
        
        foreach ($assignments as $assignment) {
            $sectionIds[$assignment['section_id']] = [
                'id' => $assignment['section_id'],
                'section_name' => $assignment['section_name'],
                'grade_level' => $assignment['grade_level']
            ];
            $subjectIds[$assignment['subject_id']] = [
                'id' => $assignment['subject_id'],
                'subject_name' => $assignment['subject_name'],
                'grade_level' => $assignment['grade_level']
            ];
            $assignedCombinations[$assignment['section_id']][] = $assignment['subject_id'];
        }
        
        $sections = array_values($sectionIds);
        $subjects = array_values($subjectIds);
        
        // Group subjects by grade level for JavaScript
        $subjectsByGrade = [];
        foreach ($subjects as $subject) {
            $subjectsByGrade[$subject['grade_level']][] = $subject;
        }

        return view('admin/teacher_schedule', [
            'title' => 'Manage Schedule - ' . $teacher['first_name'] . ' ' . $teacher['last_name'],
            'teacher' => $teacher,
            'schedules' => $schedules,
            'subjects' => $subjects,
            'subjectsByGrade' => $subjectsByGrade,
            'sections' => $sections,
            'assignedCombinations' => $assignedCombinations,
            // A teacher cannot be in two rooms at once. Overlaps that already
            // exist (legacy rows, rows from another school year) are shown here
            // instead of being rediscovered one rejected block at a time.
            'conflicts' => $scheduleModel->findExistingTeacherConflicts($teacherId),
        ]);
    }

    /**
     * Get smart schedule suggestions
     */
    public function getScheduleSuggestions($teacherId)
    {
        if (! is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        $input = $this->request->getJSON(true);
        $subjectId = $input['subject_id'] ?? null;
        $sectionId = $input['section_id'] ?? null;
        $day = $input['day'] ?? null;

        if (!$subjectId || !$sectionId) {
            return $this->response->setJSON(['success' => false, 'error' => 'Missing parameters']);
        }

        $db = \Config\Database::connect();
        $scheduleModel = model(TeacherScheduleModel::class);
        $subjectModel = model(SubjectModel::class);
        $sectionModel = model(SectionModel::class);

        // Get subject and section details
        $subject = $subjectModel->find($subjectId);
        $section = $sectionModel->find($sectionId);

        if (!$subject || !$section) {
            return $this->response->setJSON(['success' => false, 'error' => 'Invalid subject or section']);
        }

        // Get all teachers who can teach this subject
        $availableTeachers = $db->query(
            "SELECT DISTINCT t.id, t.first_name, t.last_name, t.specialization
             FROM teachers t
             WHERE t.employment_status = 'active'
             AND (t.specialization LIKE ? OR t.department LIKE ?)",
            ['%' . $subject['subject_name'] . '%', '%' . $subject['subject_name'] . '%']
        )->getResultArray();

        // Get available rooms
        $allRooms = ['Room 101', 'Room 102', 'Room 103', 'Room 104', 'Room 201', 'Room 202', 'Room 203', 'Room 204'];

        // Get occupied time slots for this teacher
        $occupiedSlots = $scheduleModel->where('teacher_id', $teacherId)
            ->where('school_year', get_current_school_year())
            ->findAll();

        // Get occupied rooms for all teachers
        $occupiedRooms = $scheduleModel->where('school_year', get_current_school_year())
            ->findAll();

        // Define time slots
        $timeSlots = [
            ['start' => '07:00', 'end' => '08:00'],
            ['start' => '08:00', 'end' => '09:00'],
            ['start' => '09:00', 'end' => '10:00'],
            ['start' => '10:00', 'end' => '11:00'],
            ['start' => '11:00', 'end' => '12:00'],
            ['start' => '12:00', 'end' => '13:00'],
            ['start' => '13:00', 'end' => '14:00'],
            ['start' => '14:00', 'end' => '15:00'],
            ['start' => '15:00', 'end' => '16:00'],
            ['start' => '16:00', 'end' => '17:00']
        ];

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $suggestions = [];

        foreach ($days as $dayName) {
            if ($day && $day !== $dayName) continue;

            foreach ($timeSlots as $slot) {
                // Check if teacher is available
                $teacherBusy = false;
                foreach ($occupiedSlots as $occupied) {
                    if ($occupied['day_of_week'] === $dayName &&
                        $occupied['start_time'] === $slot['start'] . ':00' &&
                        $occupied['end_time'] === $slot['end'] . ':00') {
                        $teacherBusy = true;
                        break;
                    }
                }

                if ($teacherBusy) continue;

                // Find available rooms
                $availableRooms = [];
                foreach ($allRooms as $room) {
                    $roomBusy = false;
                    foreach ($occupiedRooms as $occupied) {
                        if ($occupied['day_of_week'] === $dayName &&
                            $occupied['start_time'] === $slot['start'] . ':00' &&
                            $occupied['end_time'] === $slot['end'] . ':00' &&
                            $occupied['room'] === $room) {
                            $roomBusy = true;
                            break;
                        }
                    }
                    if (!$roomBusy) {
                        $availableRooms[] = $room;
                    }
                }

                if (!empty($availableRooms)) {
                    $suggestions[] = [
                        'day' => $dayName,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'available_rooms' => $availableRooms
                    ];
                }
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'suggestions' => $suggestions,
            'available_teachers' => $availableTeachers,
            'subject' => $subject,
            'section' => $section
        ]);
    }

    /**
     * Save teacher schedule
     */
    public function saveSchedule($teacherId)
    {
        if (! is_any_admin()) {
            return $this->response->setJSON(['success' => false, 'error' => 'Unauthorized']);
        }

        log_message('info', 'Saving schedule for teacher ID: ' . $teacherId);
        
        $scheduleModel = model(TeacherScheduleModel::class);
        
        // Get JSON data from request body
        $input = $this->request->getJSON(true);
        $schedules = $input['schedules'] ?? [];
        
        log_message('info', 'Schedule data received: ' . json_encode($schedules));

        if (empty($schedules)) {
            return $this->response->setJSON(['success' => false, 'error' => 'No schedule data provided']);
        }

        $db = \Config\Database::connect();

        // This page replaces the teacher's timetable for the current school
        // year, so those rows must not be read as conflicts against the very
        // blocks that are about to replace them. Deleting by teacher_id alone
        // (what this method used to do) also destroyed rows from other school
        // years and the '' (unassigned) placeholder rows other pages read.
        $replacedIds = array_map(
            static fn (array $row): int => (int) $row['id'],
            $db->table('teacher_schedules')
                ->select('id')
                ->where('teacher_id', (int) $teacherId)
                ->where('school_year', get_current_school_year())
                ->whereIn('day_of_week', schedule_conflict_weekdays())
                ->get()->getResultArray()
        );

        $validated = [];
        $errors    = [];

        // Every block is validated twice: on its own shape, then against the
        // stored timetable AND against the blocks submitted alongside it. The
        // sibling pass is what stops one click from writing two overlapping
        // rows - they are not in the database yet, so no query can see them.
        foreach ($schedules as $index => $schedule) {
            $check = schedule_validate_slot(is_array($schedule) ? $schedule : []);

            if (! $check['ok']) {
                $errors[] = 'Block ' . ($index + 1) . ': ' . $check['error'];
                continue;
            }

            $slot = $check['slot'];
            // The grid posts the id of the teacher whose page this is; a
            // crafted payload cannot reassign these blocks to someone else.
            $slot['teacher_id'] = (int) $teacherId;

            $validated[] = $slot;
        }

        if ($errors === []) {
            $validated = $this->labelScheduleSlots($validated);

            $accepted = [];
            foreach ($validated as $index => $slot) {
                $conflicts = $scheduleModel->findConflicts($slot, $accepted, true, $replacedIds);

                if ($conflicts !== []) {
                    foreach ($conflicts as $conflict) {
                        $errors[] = 'Block ' . ($index + 1) . ': ' . $conflict['message'];
                    }
                    continue;
                }

                $accepted[] = $slot;
            }

            $validated = $accepted;
        }

        if ($errors !== []) {
            // Nothing is written. The grid is replaced wholesale, so a partial
            // save would silently drop every block the admin did not submit.
            log_message('info', 'Schedule rejected for teacher ID ' . $teacherId . ': ' . implode(' | ', $errors));

            return $this->response->setJSON([
                'success'   => false,
                'error'     => implode('<br>', $errors),
                'conflicts' => array_map(
                    static fn (string $message): array => ['message' => $message],
                    $errors
                ),
            ], 409);
        }

        $db->transStart();

        try {
            // Delete only the rows this save replaces (current school year,
            // real weekdays). Rows from other years and '' placeholders survive.
            $deleted = $replacedIds === []
                ? 0
                : $db->table('teacher_schedules')->whereIn('id', $replacedIds)->delete();

            log_message('info', 'Deleted existing schedules: ' . ($deleted === false ? 'failed' : (string) $deleted));

            // Insert the validated timetable. Every block already passed the
            // conflict engine, so a failure here is a database problem, and
            // the transaction rolls the whole grid back rather than leaving a
            // half-written week.
            $now      = date('Y-m-d H:i:s');
            $inserted = 0;

            foreach ($validated as $slot) {
                $data = [
                    'teacher_id'  => (int) $teacherId,
                    'section_id'  => (int) $slot['section_id'],
                    'day_of_week' => $slot['day_of_week'],
                    'start_time'  => $slot['start_time'],
                    'end_time'    => $slot['end_time'],
                    // Empty means "no room yet"; NULL keeps the room-overlap
                    // check from matching every other unassigned block.
                    'room'        => $slot['room'] === '' ? null : $slot['room'],
                    'school_year' => get_current_school_year(),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];

                // This page can reach a non-numerical (domain-graded) advisory
                // section, where the item is a developmental domain id. Writing
                // that id into subject_id would break its FOREIGN KEY - or, when
                // the ids happen to collide, silently attach an unrelated
                // subject. schedule_item_column() resolves the right column.
                $itemColumn = schedule_item_column((int) $slot['section_id']);

                $data[$itemColumn] = (int) $slot['item_id'];

                if ($itemColumn === 'domain_id') {
                    $data['subject_id'] = null;
                } elseif (schedule_domain_column_ready()) {
                    $data['domain_id'] = null;
                }

                if (! $db->table('teacher_schedules')->insert($data)) {
                    $failure = $db->error();
                    log_message('error', 'Failed to insert schedule: ' . json_encode($data) . ' DB error: ' . json_encode($failure));

                    $db->transRollback();

                    return $this->response->setJSON([
                        'success' => false,
                        'error'   => 'The schedule could not be saved (database error).',
                    ], 500);
                }

                $inserted++;
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                log_message('error', 'Transaction failed');

                return $this->response->setJSON([
                    'success' => false,
                    'error'   => 'The schedule could not be saved (transaction failed).',
                ], 500);
            }

            log_message('info', 'Inserted ' . $inserted . ' schedule entries out of ' . count($schedules));

            audit_event('teacher.schedule_saved', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'teacher',
                'resource_id'   => (string) $teacherId,
                'description'   => 'Teacher schedule saved (' . $inserted . ' entr' . ($inserted === 1 ? 'y' : 'ies') . ')',
                'metadata'      => ['inserted' => (int) $inserted, 'submitted' => count($schedules)],
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Schedule saved successfully (' . $inserted . ' entries)',
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', 'Schedule save error: ' . $e->getMessage());

            return $this->response->setJSON([
                'success' => false,
                'error'   => 'Database error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Attach the display labels the conflict messages need to validated slots.
     *
     * findConflicts() names a stored blocking row itself, but blocks validated
     * in the same save are still in memory, and without a label their messages
     * would read "item #12". The label follows the section's grading type, the
     * same way the schedule pages do: developmental domains for non-numerical
     * sections, subjects otherwise. Domain ids can collide with subject ids, so
     * the fallback matters when one of the two tables has no matching row.
     *
     * @param  list<array<string, mixed>> $slots Validated slots from schedule_validate_slot().
     * @return list<array<string, mixed>>        The same slots, plus item_name/section_name.
     */
    private function labelScheduleSlots(array $slots): array
    {
        if ($slots === []) {
            return $slots;
        }

        $db = \Config\Database::connect();

        $ids = static fn (string $key): array => array_values(array_unique(
            array_map(static fn (array $slot): int => (int) $slot[$key], $slots)
        ));

        $sectionNames = [];
        foreach ($db->table('sections')->select('id, section_name')->whereIn('id', $ids('section_id'))->get()->getResultArray() as $row) {
            $sectionNames[(int) $row['id']] = (string) $row['section_name'];
        }

        $itemIds = $ids('item_id');

        $subjectNames = [];
        foreach ($db->table('subjects')->select('id, subject_name')->whereIn('id', $itemIds)->get()->getResultArray() as $row) {
            $subjectNames[(int) $row['id']] = (string) $row['subject_name'];
        }

        $domainNames = [];

        if (schedule_domain_column_ready()) {
            foreach ($db->table('sned_categories')->select('id, name')->whereIn('id', $itemIds)->get()->getResultArray() as $row) {
                $domainNames[(int) $row['id']] = (string) $row['name'];
            }
        }

        foreach ($slots as &$slot) {
            $sectionId = (int) $slot['section_id'];
            $itemId    = (int) $slot['item_id'];

            $slot['section_name'] = $sectionNames[$sectionId] ?? ('section #' . $sectionId);
            $slot['item_name']    = is_non_numerical_section($sectionId)
                ? ($domainNames[$itemId] ?? $subjectNames[$itemId] ?? ('item #' . $itemId))
                : ($subjectNames[$itemId] ?? $domainNames[$itemId] ?? ('item #' . $itemId));
        }
        unset($slot);

        return $slots;
    }
}

