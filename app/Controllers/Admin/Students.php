<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\StudentNutritionClassifier;
use App\Models\StudentModel;
use CodeIgniter\Shield\Models\UserModel;
use App\Models\SectionModel;
use App\Libraries\SupabaseEmailService;
use CodeIgniter\Shield\Entities\User;

class Students extends BaseController
{
    public function index()
    {
        $studentModel = model(StudentModel::class);
        $sectionModel = model('SectionModel');

        // Get filter parameters (every set filter narrows the list further - AND)
        $gradeLevel = $this->request->getGet('grade');
        $section = $this->request->getGet('section');
        $search = $this->request->getGet('search');
        $assignment = $this->request->getGet('assignment');
        $status = (string) ($this->request->getGet('status') ?: 'all');
        $gender = trim((string) $this->request->getGet('gender'));
        $studentType = trim((string) $this->request->getGet('student_type'));
        $religion = trim((string) $this->request->getGet('religion'));
        $page = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage = 30;

        // 'pending' lives on the Pending Students page; the remaining enum
        // values stay selectable here so graduated/dropped records are reachable.
        $statusOptions = ['all', 'enrolled', 'approved', 'graduated', 'dropped'];
        if (! in_array($status, $statusOptions, true)) {
            $status = 'all';
        }

        // Nutritional status is filtered on the dedicated Nutrition / BMI page.
        $db = \Config\Database::connect();
        


        // Build query for students - exclude pending by default
        $builder = $studentModel->select('students.id, students.lrn, students.first_name, students.last_name, students.grade_level, sections.section_name, students.enrollment_status, students.school_year, students.created_at, students.emergency_contact_name, students.emergency_contact_number, students.emergency_contact_relationship')
                                ->join('sections', 'sections.id = students.section_id', 'left')
                                ->where('students.deleted_at IS NULL')
                                ->where('students.enrollment_status !=', 'pending');
        
        // Apply status filter only if not 'all'
        if ($status !== 'all') {
            $builder->where('students.enrollment_status', $status);
        }

        // Apply filters
        if ($gradeLevel && $gradeLevel !== '') {
            $builder->where('students.grade_level', (string)$gradeLevel);
        }

        if ($section && $section !== '') {
            $builder->where('students.section_id', $section);
        }

        if ($assignment && $assignment !== '') {
            if ($assignment === 'assigned') {
                $builder->where('students.section_id IS NOT NULL');
            } elseif ($assignment === 'unassigned') {
                $builder->where('students.section_id IS NULL');
            }
        }

        if ($search) {
            $builder->groupStart()
                   ->like('students.first_name', $search)
                   ->orLike('students.last_name', $search)
                   ->orLike('students.lrn', $search)
                   ->groupEnd();
        }

        if ($gender === 'Male' || $gender === 'Female') {
            $builder->where('students.gender', $gender);
        }

        if ($studentType !== '') {
            $builder->where('students.student_type', $studentType);
        }

        if ($religion !== '') {
            $builder->where('students.religion', $religion);
        }

        // Get paginated results - order by enrollment date (newest first), then by last name
        $students = $builder->orderBy('students.created_at', 'DESC')
                           ->orderBy('students.last_name', 'ASC')
                           ->limit($perPage, ($page - 1) * $perPage)
                           ->findAll();
        
        // Get total count by running the same query without limit
        $countBuilder = $studentModel->select('students.id')
                                     ->join('sections', 'sections.id = students.section_id', 'left')
                                     ->where('students.deleted_at IS NULL')
                                     ->where('students.enrollment_status !=', 'pending');
        
        // Apply status filter only if not 'all'
        if ($status !== 'all') {
            $countBuilder->where('students.enrollment_status', $status);
        }
        
        if ($gradeLevel && $gradeLevel !== '') {
            $countBuilder->where('students.grade_level', (string)$gradeLevel);
        }
        if ($section && $section !== '') {
            $countBuilder->where('students.section_id', $section);
        }
        if ($assignment && $assignment !== '') {
            if ($assignment === 'assigned') {
                $countBuilder->where('students.section_id IS NOT NULL');
            } elseif ($assignment === 'unassigned') {
                $countBuilder->where('students.section_id IS NULL');
            }
        }
        if ($search) {
            $countBuilder->groupStart()
                        ->like('students.first_name', $search)
                        ->orLike('students.last_name', $search)
                        ->orLike('students.lrn', $search)
                        ->groupEnd();
        }

        if ($gender === 'Male' || $gender === 'Female') {
            $countBuilder->where('students.gender', $gender);
        }

        if ($studentType !== '') {
            $countBuilder->where('students.student_type', $studentType);
        }

        if ($religion !== '') {
            $countBuilder->where('students.religion', $religion);
        }
        
        $totalStudents = $countBuilder->countAllResults(false);
        
        $totalPages = max(1, ceil($totalStudents / $perPage));
        
        // Get all sections for filter dropdown
        $sectionModel = model('SectionModel');
        $allSections = $sectionModel->select('id, section_name, grade_level')
                                   ->where('is_active', true)
                                   ->orderBy('grade_level', 'ASC')
                                   ->orderBy('section_name', 'ASC')
                                   ->findAll();
        
        // Get pending applications count for badge
        $pendingCount = $studentModel->where('enrollment_status', 'pending')->countAllResults();
        
        // Disable caching for this page
        $this->response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->response->setHeader('Pragma', 'no-cache');
        $this->response->setHeader('Expires', '0');
        
        // Filter dropdown values come from the data actually recorded so new
        // religions / student types appear without any code change.
        $studentTypeOptions = array_column($db->query(
            "SELECT DISTINCT student_type AS v FROM students WHERE deleted_at IS NULL AND student_type IS NOT NULL AND student_type <> '' ORDER BY student_type ASC"
        )->getResultArray(), 'v');
        $religionOptions = array_column($db->query(
            "SELECT DISTINCT religion AS v FROM students WHERE deleted_at IS NULL AND religion IS NOT NULL AND religion <> '' ORDER BY religion ASC"
        )->getResultArray(), 'v');

        return view('admin/students', [
            'title' => 'Manage Students - CSCS Tap n Track',
            'students' => $students,
            'allSections' => $allSections,
            'gradeLevel' => $gradeLevel,
            'section' => $section,
            'search' => $search,
            'assignment' => $assignment,
            'status' => $status,
            'statusOptions' => $statusOptions,
            'gender' => $gender,
            'studentType' => $studentType,
            'religion' => $religion,
            'studentTypeOptions' => $studentTypeOptions,
            'religionOptions' => $religionOptions,
            'pendingCount' => $pendingCount,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalStudents' => $totalStudents,
            'perPage' => $perPage
        ]);
    }

    /**
     * Export the filtered master list as a PDF. Mirrors the list page: every
     * row matching the active filters (no pagination limit).
     */
    public function exportPdf()
    {
        if (! auth()->user() || ! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $data = [
            'students'       => $this->exportRows(),
            'reportDate'     => date('F j, Y'),
            'schoolYear'     => get_current_school_year(),
            'filtersSummary' => $this->filtersSummary(),
        ];

        $html = view('admin/students_pdf', $data);

        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Times');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $this->sendPdfInline($dompdf, 'CSCS_Students_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Human-readable filter summary printed under the PDF letterhead.
     */
    private function filtersSummary(): string
    {
        $parts = [];

        $grade = $this->request->getGet('grade');
        if ($grade !== null && $grade !== '' && ctype_digit((string) $grade)) {
            $parts[] = grade_level_label((int) $grade);
        }

        $sectionId = $this->request->getGet('section');
        if ($sectionId !== null && $sectionId !== '') {
            $section = model('SectionModel')->select('section_name')->find((int) $sectionId);
            $parts[] = 'Section: ' . (is_array($section) && ! empty($section['section_name'])
                ? $section['section_name']
                : '#' . (int) $sectionId);
        }

        $assignment = $this->request->getGet('assignment');
        if ($assignment === 'assigned') {
            $parts[] = 'Assigned only';
        } elseif ($assignment === 'unassigned') {
            $parts[] = 'Unassigned only';
        }

        $status = (string) ($this->request->getGet('status') ?: 'all');
        if ($status !== 'all') {
            $parts[] = 'Status: ' . ucfirst($status);
        }

        $gender = trim((string) $this->request->getGet('gender'));
        if ($gender === 'Male' || $gender === 'Female') {
            $parts[] = 'Sex: ' . $gender;
        }

        $studentType = trim((string) $this->request->getGet('student_type'));
        if ($studentType !== '') {
            $parts[] = 'Type: ' . ucfirst($studentType);
        }

        $religion = trim((string) $this->request->getGet('religion'));
        if ($religion !== '') {
            $parts[] = 'Religion: ' . $religion;
        }

        $search = trim((string) $this->request->getGet('search'));
        if ($search !== '') {
            $parts[] = 'Search: ' . $search;
        }

        return $parts === [] ? 'All students (pending / archived excluded)' : implode(' | ', $parts);
    }

    /**
     * Every row matching the current filters - same WHERE chain as index()
     * but without the pagination limit. Sex / type / religion are selected so
     * the filtered values are visible in the export.
     *
     * @return list<array<string, mixed>>
     */
    private function exportRows(): array
    {
        $studentModel = model(StudentModel::class);

        $gradeLevel  = $this->request->getGet('grade');
        $section     = $this->request->getGet('section');
        $search      = $this->request->getGet('search');
        $assignment  = $this->request->getGet('assignment');
        $status      = (string) ($this->request->getGet('status') ?: 'all');
        $gender      = trim((string) $this->request->getGet('gender'));
        $studentType = trim((string) $this->request->getGet('student_type'));
        $religion    = trim((string) $this->request->getGet('religion'));

        if (! in_array($status, ['all', 'enrolled', 'approved', 'graduated', 'dropped'], true)) {
            $status = 'all';
        }

        $builder = $studentModel->select('students.id, students.lrn, students.first_name, students.middle_name, students.last_name, students.grade_level, sections.section_name, students.gender, students.student_type, students.religion, students.enrollment_status, students.created_at')
                                ->join('sections', 'sections.id = students.section_id', 'left')
                                ->where('students.deleted_at IS NULL')
                                ->where('students.enrollment_status !=', 'pending');

        if ($status !== 'all') {
            $builder->where('students.enrollment_status', $status);
        }

        if ($gradeLevel && $gradeLevel !== '') {
            $builder->where('students.grade_level', (string) $gradeLevel);
        }

        if ($section && $section !== '') {
            $builder->where('students.section_id', $section);
        }

        if ($assignment && $assignment !== '') {
            if ($assignment === 'assigned') {
                $builder->where('students.section_id IS NOT NULL');
            } elseif ($assignment === 'unassigned') {
                $builder->where('students.section_id IS NULL');
            }
        }

        if ($search) {
            $builder->groupStart()
                    ->like('students.first_name', $search)
                    ->orLike('students.last_name', $search)
                    ->orLike('students.lrn', $search)
                    ->groupEnd();
        }

        if ($gender === 'Male' || $gender === 'Female') {
            $builder->where('students.gender', $gender);
        }

        if ($studentType !== '') {
            $builder->where('students.student_type', $studentType);
        }

        if ($religion !== '') {
            $builder->where('students.religion', $religion);
        }

        // Same ordering as the list page (newest enrolments first, then name).
        return $builder->orderBy('students.created_at', 'DESC')
                       ->orderBy('students.last_name', 'ASC')
                       ->findAll();
    }

    /**
     * View student details as full page
     */
    public function viewStudent($studentId)
    {
        $studentModel = model(StudentModel::class);

        // Get student details with section and user info
        $student = $studentModel->select('students.*, sections.section_name, users.email as user_email')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->join('users', 'users.id = students.user_id', 'left')
            ->find($studentId);
            
        // Ensure email is properly set - prioritize students table email
        if ($student) {
            $student['email'] = !empty($student['email']) ? $student['email'] : ($student['user_email'] ?? '');
        }

        if (!$student) {
            return redirect()->to('admin/students/pending')->with('error', 'Student not found');
        }

        // Enrollment document uploads were removed; keep empty for legacy view compatibility
        $documentsByType = [];

        // Check if this is a password reset request
        $passwordReset = $this->request->getGet('password_reset') === 'true';

        // Prevent caching
        $this->response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->response->setHeader('Pragma', 'no-cache');
        $this->response->setHeader('Expires', '0');

        return view('admin/student_view', [
            'title' => 'Student Details - CSCS Tap n Track',
            'student' => $student,
            'documents' => $documentsByType,
            'passwordReset' => $passwordReset
        ]);
    }

    /**
     * Update student password
     */
    public function updatePassword($id)
    {
        $studentModel = model(StudentModel::class);
        $student = $studentModel->find($id);
        
        if (!$student) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Student not found']);
        }

        $input = $this->request->getJSON(true);
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        if (empty($password) || empty($confirmPassword)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Password and confirmation are required']);
        }

        if ($password !== $confirmPassword) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Passwords do not match']);
        }

        $policyError = password_meets_policy($password);
        if ($policyError !== null) {
            return $this->response->setStatusCode(400)->setJSON(['error' => $policyError]);
        }

        // Update password in auth_identities table
        $db = \Config\Database::connect();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $result = $db->table('auth_identities')
            ->where('user_id', $student['user_id'])
            ->where('type', 'email_password')
            ->update(['secret2' => $hashedPassword]);

        if ($result) {
            audit_event('account.password_reset_by_admin', [
                'category'      => 'account',
                'status'        => 'success',
                'resource_type' => 'student',
                'resource_id'   => (string) $student['id'],
                'description'   => 'Student password reset by an administrator',
                'metadata'      => ['lrn' => $student['lrn'] ?? null, 'method' => 'admin_portal'],
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Password updated successfully'
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON([
            'error' => 'Failed to update password'
        ]);
    }

    /**
     * Get student details with documents for modal display
     */
    public function getStudentDetails($studentId)
    {
        $studentModel = model(StudentModel::class);

        // Get student details with section and user info
        $student = $studentModel->select('students.*, sections.section_name, users.email as user_email')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->join('users', 'users.id = students.user_id', 'left')
            ->find($studentId);
            
        // Ensure email is properly set - prioritize students table email
        if ($student) {
            $student['email'] = !empty($student['email']) ? $student['email'] : ($student['user_email'] ?? '');
        }
            
        // Debug: Log the student data to check email
        log_message('debug', 'Student details for ID ' . $studentId . ': ' . json_encode($student));

        if (!$student) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Student not found']);
        }

        // Enrollment document uploads were removed; keep empty for legacy view compatibility
        $documentsByType = [];

        return view('admin/partials/student_details_modal', [
            'student' => $student,
            'documents' => $documentsByType
        ]);
    }

    /**
     * Get pending applications count (API)
     */
    public function getPendingCount()
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $studentModel = model(StudentModel::class);
        $count = $studentModel->where('enrollment_status', 'pending')->countAllResults();
        
        return $this->response->setJSON(['count' => $count]);
    }

    /**
     * Show pending applications for approval
     */
    public function pending()
    {
        $studentModel = model(StudentModel::class);
        
        $pendingStudents = $studentModel->select('students.*, users.email as user_email')
                                       ->join('users', 'users.id = students.user_id', 'left')
                                       ->where('students.enrollment_status', 'pending')
                                       ->orderBy('students.created_at', 'DESC')
                                       ->findAll();

        // Ensure email is properly set - prioritize students table email
        foreach ($pendingStudents as &$pendingStudent) {
            if (empty($pendingStudent['email'])) {
                $pendingStudent['email'] = $pendingStudent['user_email'] ?? '';
            }
        }
        unset($pendingStudent);

        // Next school year promotion applications are reviewed on this same page
        // (rendered below the new student applicants table) so the admin
        // processes everything from a single screen.
        $nextYear = $this->fetchNextYearApplications();

        return view('admin/students_pending', [
            'title' => 'Pending Applications - CSCS Tap n Track',
            'pendingStudents' => $pendingStudents,
            'nextYearPending' => $nextYear['pending'],
            'nextYearProcessed' => $nextYear['processed'],
        ]);
    }

    /**
     * Load next_year_applications joined with student/section info, split into
     * pending and processed. Returns empty arrays when the table is missing so
     * the applications page still renders on a database that predates the
     * promotion feature.
     */
    private function fetchNextYearApplications(): array
    {
        $empty = ['pending' => [], 'processed' => []];

        try {
            $db = \Config\Database::connect();

            if (! in_array('next_year_applications', $db->listTables(), true)) {
                return $empty;
            }

            $rows = $db->table('next_year_applications a')
                ->select('a.*, students.first_name, students.middle_name, students.last_name, students.lrn, students.enrollment_status AS student_status, sections.section_name')
                ->join('students', 'students.id = a.student_id', 'left')
                ->join('sections', 'sections.id = students.section_id', 'left')
                ->orderBy('a.status', 'ASC')
                ->orderBy('a.applied_at', 'DESC')
                ->get()
                ->getResultArray();

            return [
                'pending'   => array_values(array_filter($rows, static fn ($r) => ($r['status'] ?? '') === 'pending')),
                'processed' => array_values(array_filter($rows, static fn ($r) => ($r['status'] ?? '') !== 'pending')),
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Failed to load next year applications: ' . $e->getMessage());

            return $empty;
        }
    }

    /**
     * Show history of processed applications
     */
    public function pendingHistory()
    {
        $studentModel = model(StudentModel::class);
        
        $perPage = 15;
        $currentPage = max(1, (int) ($this->request->getGet('page') ?? 1));
        
        // Get total count
        $totalRecords = $studentModel->whereIn('students.enrollment_status', ['enrolled', 'rejected'])
                                   ->countAllResults();
        $totalPages = max(1, (int) ceil($totalRecords / $perPage));
        $currentPage = min($currentPage, $totalPages);
        $offset = ($currentPage - 1) * $perPage;
        
        // Get paginated results
        $processedStudents = $studentModel->select('students.*, users.email as user_email')
                                         ->join('users', 'users.id = students.user_id', 'left')
                                         ->whereIn('students.enrollment_status', ['enrolled', 'rejected'])
                                         ->orderBy('students.updated_at', 'DESC')
                                         ->limit($perPage, $offset)
                                         ->findAll();

        // Ensure email is properly set - prioritize students table email
        foreach ($processedStudents as &$processedStudent) {
            if (empty($processedStudent['email'])) {
                $processedStudent['email'] = $processedStudent['user_email'] ?? '';
            }
        }
        unset($processedStudent);
        
        return view('admin/students_pending_history', [
            'title' => 'Application History - CSCS Tap n Track',
            'processedStudents' => $processedStudents,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'perPage' => $perPage,
        ]);
    }
    


    /**
     * Next school year promotion applications (next_year_applications table).
     * Students apply from the student portal; the admin approves (promotes
     * the student via Admin\Students::promote) or rejects.
     */
    /**
     * Next school year promotion applications now render inline on the Pending
     * Applications page (admin/students/pending) so the admin only ever visits
     * one screen. This legacy URL is kept as a redirect for bookmarks.
     */
    public function nextYearApplications()
    {
        return redirect()->to(base_url('admin/students/pending') . '#next-year-applications');
    }

    /**
     * Bulk-approve next school year applications: promotes every selected
     * student. Reuses performPromotion() so the single and bulk paths can never
     * drift apart, and reports per-application outcomes.
     */
    public function bulkPromoteApplications()
    {
        return $this->response->setJSON($this->runBulkApplications('promote'));
    }

    /**
     * Bulk-reject next school year applications. Reuses performRejection() so
     * the single and bulk paths can never drift apart.
     */
    public function bulkRejectApplications()
    {
        return $this->response->setJSON($this->runBulkApplications('reject'));
    }

    /**
     * Shared bulk processor for next_year_applications rows.
     *
     * The stored next_grade_level is used as the server-side source of truth
     * (never a client-supplied value), each row is re-checked for `pending`
     * status, and one failing row never aborts the rest of the batch.
     */
    private function runBulkApplications(string $action): array
    {
        $input = $this->request->getJSON(true);
        if (! is_array($input)) {
            $input = $this->request->getPost() ?? [];
        }

        $ids = $input['ids'] ?? $input['application_ids'] ?? [];
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));

        if ($ids === []) {
            return ['success' => false, 'error' => 'No applications were selected.'];
        }

        $db = \Config\Database::connect();
        $processed = 0;
        $failures = [];

        foreach ($ids as $applicationId) {
            $application = $db->table('next_year_applications')->where('id', $applicationId)->get()->getRowArray();

            if (! $application) {
                $failures[] = '#' . $applicationId . ' not found.';
                continue;
            }

            if (($application['status'] ?? '') !== 'pending') {
                $failures[] = '#' . $applicationId . ' is already ' . ($application['status'] ?? 'processed') . '.';
                continue;
            }

            if ($action === 'promote') {
                $result = $this->performPromotion((int) $application['student_id'], (int) $application['next_grade_level']);
            } else {
                $result = $this->performRejection($applicationId);
            }

            if (! empty($result['success'])) {
                $processed++;
            } else {
                $failures[] = '#' . $applicationId . ': ' . ($result['error'] ?? 'action failed.');
            }
        }

        return $this->buildBulkApplicationResult($processed, $failures, $action === 'promote' ? 'promoted' : 'rejected');
    }

    /**
     * Shape the response of a bulk next-year action, surfacing the first few
     * per-row reasons when anything failed.
     */
    private function buildBulkApplicationResult(int $processed, array $failures, string $verb): array
    {
        $failureCount = count($failures);

        if ($processed === 0) {
            $detail = $failures === []
                ? 'Nothing was processed.'
                : ($failureCount === 1 ? $failures[0] : implode(' ', array_slice($failures, 0, 3)));

            return ['success' => false, 'error' => 'No applications were ' . $verb . '. ' . $detail, 'failures' => $failures];
        }

        $message = $processed . ' application' . ($processed === 1 ? '' : 's') . ' ' . $verb . ' successfully.';
        if ($failureCount > 0) {
            $message .= ' ' . $failureCount . ' failed: ' . implode(' ', array_slice($failures, 0, 3));
        }

        return [
            'success'   => true,
            'message'   => $message,
            'processed' => $processed,
            'failures'  => $failures,
        ];
    }

    /**
     * Show create student form
     */
    public function create()
    {
        // Check if user is admin
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $sectionModel = model(SectionModel::class);
        $sections = $sectionModel->findAll();

        return view('admin/students_create', [
            'title' => 'Add New Student - CSCS Tap n Track',
            'sections' => $sections
        ]);
    }

    /**
     * Show enroll student page
     */
    public function enroll()
    {
        // Check if user is admin
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $sectionModel = model(SectionModel::class);
        $sections = $sectionModel->findAll();

        return view('admin/students_enroll', [
            'title' => 'Enroll New Student - CSCS Tap n Track',
            'sections' => $sections
        ]);
    }

    /**
     * Store new student
     */
    public function store()
    {
        // Check if user is admin
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        // Normalise legacy phone formats once, up front: validation, the
        // getPost() writes below, and withInput() all see 09XXXXXXXXX.
        phone_normalize_request($this->request, ['contact_number', 'emergency_contact_number']);

        // Relationship "Other" is resolved to its free-text companion before
        // validation, so the literal word "Other" is never stored.
        emergency_contact_relationship_normalize_request($this->request);

        $rules = [
            'first_name' => 'required|min_length[2]|max_length[50]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            // "No middle name" ticked -> nothing to validate; otherwise a middle
            // name is required and must be 2+ characters (no lone letter).
            'middle_name' => middle_name_validation_rule() . '|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'last_name' => 'required|min_length[2]|max_length[50]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'suffix' => 'permit_empty|max_length[10]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'password' => password_validation_rule('password'),
            'confirm_password' => 'required|matches[password]',
            'lrn' => 'permit_empty|max_length[20]',
            'grade_level' => 'required|' . grade_level_in_list_rule(),
            'student_type' => 'required|in_list[New Student,Transferee,Old Student]',
            'section_id' => 'permit_empty|integer',
            'gender' => 'required|in_list[Male,Female]',
            'date_of_birth' => 'required|valid_date',
            'place_of_birth' => 'permit_empty|max_length[255]',
            'nationality' => 'permit_empty|max_length[50]',
            'religion' => 'permit_empty|max_length[50]',
            'contact_number' => phone_validation_rule(),
            'address' => 'permit_empty|max_length[255]',
            'emergency_contact_name' => 'permit_empty|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'emergency_contact_number' => phone_validation_rule(),
            'emergency_contact_relationship' => 'permit_empty|max_length[50]',
            'birth_certificate' => 'permit_empty|max_size[birth_certificate,5120]',
            'report_card' => 'permit_empty|max_size[report_card,5120]',
            'good_moral' => 'permit_empty|max_size[good_moral,5120]',
            'photo' => 'permit_empty|max_size[photo,2048]|is_image[photo]'
        ];

        if (!$this->validate($rules, phone_validation_messages([
            'contact_number' => 'Contact Number',
            'emergency_contact_number' => 'Emergency Contact Number',
        ]))) {
            $sectionModel = model(SectionModel::class);
            $sections = $sectionModel->findAll();

            return view('admin/students_create', [
                'title' => 'Add New Student - CSCS Tap n Track',
                'sections' => $sections,
                'validation' => $this->validator
            ]);
        }

        $userModel = model(UserModel::class);
        $studentModel = model(StudentModel::class);

        $userId = null;
        
        // Check if user with this email already exists
        $existingUser = $userModel->where('email', $this->request->getPost('email'))->first();
        if ($existingUser) {
            return redirect()->back()->withInput()->with('error', 'A user with this email already exists.');
        }

        // Create user account manually to ensure proper password handling
        $db = \Config\Database::connect();
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');
        
        // Insert user record
        $userData = [
            'email' => $email,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $db->table('users')->insert($userData);
        $userId = $db->insertID();
        
        if (!$userId) {
            return redirect()->back()->withInput()->with('error', 'Failed to create user account.');
        }
        
        helper('student_auth');
        if (! sync_student_auth_password((int) $userId, $email, $password)) {
            $db->table('users')->where('id', $userId)->delete();

            return redirect()->back()->withInput()->with('error', 'Failed to create login credentials.');
        }
        
        // Add user to student group
        $db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            'group' => 'student',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Handle LRN - use provided or auto-generate
        $lrn = $this->request->getPost('lrn');
        if (empty($lrn)) {
            // Check for existing LRN to avoid duplicates
            do {
                $lastStudent = $studentModel->select('lrn')
                    ->where('lrn REGEXP', '^[0-9]+$') // Only numeric LRNs
                    ->orderBy('CAST(lrn AS UNSIGNED)', 'DESC')
                    ->first();
                
                if ($lastStudent && is_numeric($lastStudent['lrn'])) {
                    $nextNumber = intval($lastStudent['lrn']) + 1;
                } else {
                    $nextNumber = 100000000001; // Start with 12-digit LRN
                }
                
                $lrn = (string)$nextNumber;
                
                // Check if this LRN already exists
                $existingLrn = $studentModel->where('lrn', $lrn)->first();
            } while ($existingLrn);
        }
        
        // Validate LRN uniqueness
        $existingStudent = $studentModel->where('lrn', $lrn)->first();
        if ($existingStudent) {
            return redirect()->back()->withInput()->with('error', 'LRN already exists. Please use a different LRN.');
        }

        // Check section capacity if section is selected
        $sectionId = $this->request->getPost('section_id');
        if ($sectionId) {
            $sectionModel = model(SectionModel::class);
            $section = $sectionModel->find($sectionId);
            
            if (!$section) {
                return redirect()->back()->withInput()->with('error', 'Selected section not found.');
            }
            
            // Count current enrolled students in section
            $currentEnrollment = $studentModel->where('section_id', $sectionId)
                ->where('enrollment_status', 'enrolled')
                ->countAllResults();
            
            $maxCapacity = $section['max_capacity'] ?? 40;
            
            if ($currentEnrollment >= $maxCapacity) {
                return redirect()->back()->withInput()->with('error', "Section {$section['section_name']} is at full capacity ({$maxCapacity} students). Please select a different section.");
            }
        }

        // Create student record (like registration process)
        $gradeLevel = $this->request->getPost('grade_level');
        $studentData = [
            'user_id' => $userId,
            'lrn' => $lrn,
            'first_name' => $this->request->getPost('first_name'),
            'middle_name' => normalize_middle_name(),
            'last_name' => $this->request->getPost('last_name'),
            'suffix' => $this->request->getPost('suffix'),
            'student_type' => $this->request->getPost('student_type'),
            'grade_level' => $gradeLevel,
            'section_id' => $sectionId,
            'gender' => $this->request->getPost('gender'),
            'date_of_birth' => $this->request->getPost('date_of_birth'),
            'place_of_birth' => $this->request->getPost('place_of_birth'),
            'nationality' => $this->request->getPost('nationality') ?: 'Filipino',
            'religion' => $this->request->getPost('religion'),
            'contact_number' => $this->request->getPost('contact_number'),
            'email' => $this->request->getPost('email'), // Store email in student record too
            'address' => $this->request->getPost('address'),
            'emergency_contact_name' => $this->request->getPost('emergency_contact_name'),
            'emergency_contact_number' => $this->request->getPost('emergency_contact_number'),
            'emergency_contact_relationship' => $this->request->getPost('emergency_contact_relationship'),
            'enrollment_status' => 'enrolled', // Direct enrollment by admin
            'school_year' => get_current_school_year(),
            'temp_password' => $this->request->getPost('password') // Store password for email
        ];

        // Debug: Log the student data being saved
        log_message('debug', 'Attempting to save student data: ' . json_encode($studentData));
        
        if ($studentModel->save($studentData)) {
            $studentId = $studentModel->getInsertID();
            log_message('debug', 'Student created successfully with ID: ' . $studentId);
            
            // Clear any cache that might prevent the student from appearing
            cache()->clean();
            
            // Send enrollment email with login credentials
            try {
                $emailService = new SupabaseEmailService();
                $studentName = $this->request->getPost('first_name') . ' ' . $this->request->getPost('last_name');
                $studentEmail = $this->request->getPost('email');
                $password = $this->request->getPost('password');
                
                $emailService->sendVerificationEmail(
                    $studentEmail,
                    $studentName,
                    $lrn,
                    $password
                );
            } catch (\Exception $e) {
                log_message('error', 'Failed to send enrollment email: ' . $e->getMessage());
            }
            
            audit_event('student.created', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'student',
                'resource_id'   => (string) $studentId,
                'description'   => 'Student enrolled from the admin portal',
                'after'         => [
                    'lrn'         => $lrn,
                    'name'        => trim((string) $this->request->getPost('first_name') . ' ' . (string) $this->request->getPost('last_name')),
                    'grade_level' => $this->request->getPost('grade_level'),
                    'section_id'  => $this->request->getPost('section_id'),
                ],
            ]);

            return redirect()->to('admin/students')->with('success', 'Student enrolled successfully.');
        } else {
            // If student creation fails, delete the user account
            $errors = $studentModel->errors();
            log_message('error', 'Failed to create student record. Errors: ' . json_encode($errors));
            log_message('error', 'Student data that failed: ' . json_encode($studentData));
            
            // Clean up created user account
            if ($userId) {
                $db->table('auth_identities')->where('user_id', $userId)->delete();
                $db->table('auth_groups_users')->where('user_id', $userId)->delete();
                $db->table('users')->where('id', $userId)->delete();
            }
            
            $errorMessage = !empty($errors) ? implode(', ', $errors) : 'Unknown database error';
            return redirect()->back()->withInput()->with('error', 'Failed to create student record: ' . $errorMessage);
        }
    }

    /**
     * Show edit student form
     */
    public function edit($studentId)
    {
        // Check if user is admin
        if (!auth()->user() || ! is_any_admin()) {
            return redirect()->to(base_url('login'))->with('error', 'Admin access required.');
        }

        $studentModel = model(StudentModel::class);
        $sectionModel = model(SectionModel::class);
        $userModel = model(UserModel::class);

        // Get student with user details
        $student = $studentModel->select('students.*, users.email as user_email')
            ->join('users', 'users.id = students.user_id', 'left')
            ->find($studentId);
            
        // Ensure email field is properly set
        if ($student) {
            $student['email'] = $student['email'] ?: ($student['user_email'] ?: '');
        }

        if (!$student) {
            return redirect()->to('admin/students')
                ->with('error', 'Student not found.');
        }

        $sections = $sectionModel->findAll();

        // Prevent caching
        $this->response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->response->setHeader('Pragma', 'no-cache');
        $this->response->setHeader('Expires', '0');

        return view('admin/students_edit', [
            'title' => 'Edit Student - CSCS Tap n Track',
            'student' => $student,
            'sections' => $sections
        ]);
    }

    /**
     * Update student
     */
    public function update($studentId)
    {
        if (! is_any_admin()) {
            return redirect()->back()->with('error', 'Unauthorized');
        }

        $studentModel = model(StudentModel::class);
        $currentStudent = $studentModel->find($studentId);
        
        if (!$currentStudent) {
            return redirect()->back()->with('error', 'Student not found');
        }

        // Normalise legacy phone formats once, up front: validation, the
        // getPost() writes below, and withInput() all see 09XXXXXXXXX.
        phone_normalize_request($this->request, ['contact_number', 'emergency_contact_number']);

        // Same idea for the religion selector: the literal "Other" choice is
        // resolved to whatever was typed into its companion box (religion_other)
        // before validation, getPost() and withInput() ever read religion.
        religion_normalize_request($this->request);

        // Relationship "Other" is resolved to its free-text companion before
        // validation, so the literal word "Other" is never stored.
        emergency_contact_relationship_normalize_request($this->request);

        // Transferees must say where they came from. The rules are only added
        // for that student type, so every other type is never asked for it and
        // never blocked by it.
        $studentType = (string) $this->request->getPost('student_type');
        $isTransferee = $studentType === 'Transferee';

        // Custom validation rules with proper LRN uniqueness check
        $rules = [
            'lrn' => "required|max_length[20]|is_unique[students.lrn,id,{$studentId}]",
            'first_name' => 'required|min_length[2]|max_length[50]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            // "No middle name" ticked -> nothing to validate; otherwise a middle
            // name is required and must be 2+ characters (no lone letter).
            'middle_name' => middle_name_validation_rule() . '|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'last_name' => 'required|min_length[2]|max_length[50]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'email' => 'permit_empty|valid_email',
            'grade_level' => 'required',
            'gender' => 'required',
            'contact_number' => phone_validation_rule(),
            'religion' => 'permit_empty|max_length[50]',
            'emergency_contact_name' => 'permit_empty|max_length[100]|regex_match[/^[\p{L}\p{M}\s.\x27\-]+$/u]',
            'emergency_contact_number' => phone_validation_rule(),
            'emergency_contact_relationship' => 'permit_empty|max_length[50]',
            'enrollment_status' => 'required|in_list[pending,approved,rejected,enrolled,graduated,dropped,transferred]'
        ];

        if ($isTransferee) {
            $rules['previous_school'] = 'required|max_length[255]';
            $rules['previous_school_year'] = 'required|in_list[' . implode(',', previous_school_year_choices()) . ']';
        }

        if (!$this->validate($rules, array_merge(phone_validation_messages([
            'contact_number' => 'Contact Number',
            'emergency_contact_number' => 'Emergency Contact Number',
        ]), $isTransferee ? [
            'previous_school' => 'Previous School',
            'previous_school_year' => 'School Year Last Attended',
        ] : []))) {
            log_message('error', 'Validation failed: ' . json_encode($this->validator->getErrors()));
            log_message('error', 'Form data: ' . json_encode($this->request->getPost()));
            return redirect()->back()->withInput()->with('error', 'Validation failed: ' . implode(', ', $this->validator->getErrors()));
        }
        
        // Log the form data for debugging
        log_message('debug', 'Form data received: ' . json_encode($this->request->getPost()));
        log_message('debug', 'Enrollment status from form: ' . $this->request->getPost('enrollment_status'));

        try {
            // Check section capacity if section is being changed
            $newSectionId = $this->request->getPost('section_id') ?: null;
            if ($newSectionId && $newSectionId != $currentStudent['section_id']) {
                $sectionModel = model(SectionModel::class);
                $section = $sectionModel->find($newSectionId);
                
                if (!$section) {
                    return redirect()->back()->withInput()->with('error', 'Selected section not found.');
                }
                
                // Count current enrolled students in the new section
                $currentEnrollment = $studentModel->where('section_id', $newSectionId)
                    ->where('enrollment_status', 'enrolled')
                    ->countAllResults();
                
                $maxCapacity = $section['max_capacity'] ?? 40;
                
                if ($currentEnrollment >= $maxCapacity) {
                    return redirect()->back()->withInput()->with('error', "Section {$section['section_name']} is at full capacity ({$maxCapacity} students). Please select a different section.");
                }
            }

            $gradeLevel = $this->request->getPost('grade_level');
            $data = [
                'lrn' => $this->request->getPost('lrn'),
                'student_type' => $studentType,
                // Only transferees carry a previous school; the columns are
                // cleared for every other type so no stale value survives.
                'previous_school' => $isTransferee ? $this->request->getPost('previous_school') : null,
                'previous_school_year' => $isTransferee ? $this->request->getPost('previous_school_year') : null,
                'first_name' => $this->request->getPost('first_name'),
                'middle_name' => normalize_middle_name(),
                'last_name' => $this->request->getPost('last_name'),
                'suffix' => $this->request->getPost('suffix'),
                'grade_level' => $gradeLevel,
                'section_id' => $newSectionId,
                'gender' => $this->request->getPost('gender'),
                'date_of_birth' => $this->request->getPost('date_of_birth'),
                'place_of_birth' => $this->request->getPost('place_of_birth'),
                'nationality' => $this->request->getPost('nationality'),
                'religion' => $this->request->getPost('religion'),
                'contact_number' => $this->request->getPost('contact_number'),
                'address' => $this->request->getPost('address'),
                'emergency_contact_name' => $this->request->getPost('emergency_contact_name'),
                'emergency_contact_number' => $this->request->getPost('emergency_contact_number'),
                'emergency_contact_relationship' => $this->request->getPost('emergency_contact_relationship'),
                'enrollment_status' => $this->request->getPost('enrollment_status'),
                'school_year' => $this->request->getPost('school_year'),
                'email' => $this->request->getPost('email')
            ];

            $hRaw = $this->request->getPost('height_cm');
            $wRaw = $this->request->getPost('weight_kg');
            $eRaw = $this->request->getPost('ethnicity');
            $h    = ($hRaw === null || $hRaw === '') ? null : (float) $hRaw;
            $w    = ($wRaw === null || $wRaw === '') ? null : (float) $wRaw;
            $e    = ($eRaw === null || trim((string) $eRaw) === '') ? null : trim((string) $eRaw);
            $data['height_cm'] = $h;
            $data['weight_kg'] = $w;
            $data['ethnicity'] = $e;
            if ($h !== null && $w !== null && $e !== null) {
                $classified = StudentNutritionClassifier::classify(
                    $h,
                    $w,
                    $this->request->getPost('gender'),
                    $this->request->getPost('date_of_birth')
                );
                $data['bmi']                = $classified['bmi'];
                $data['nutrition_status'] = $classified['nutrition_status'];
            } else {
                $data['bmi']                = null;
                $data['nutrition_status'] = null;
            }

            // Handle password update if provided
            $password = $this->request->getPost('password');
            if (!empty($password) && ! empty($currentStudent['user_id'])) {
                $db = \Config\Database::connect();
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $db->table('auth_identities')
                    ->where('user_id', $currentStudent['user_id'])
                    ->where('type', 'email_password')
                    ->update(['secret' => $hashedPassword]);
            }

            // Force database update with raw SQL
            $db = \Config\Database::connect();
            $enrollmentStatus = $this->request->getPost('enrollment_status');
            
            // Check current status before update
            $currentStudent = $db->table('students')->where('id', $studentId)->get()->getRow();
            log_message('debug', 'Current student status before update: ' . ($currentStudent ? $currentStudent->enrollment_status : 'not found'));
            
            $sql = "UPDATE students SET enrollment_status = ?, updated_at = NOW() WHERE id = ?";
            $directUpdate = $db->query($sql, [$enrollmentStatus, $studentId]);
            
            // Check status after update
            $updatedStudent = $db->table('students')->where('id', $studentId)->get()->getRow();
            log_message('debug', 'Student status after update: ' . ($updatedStudent ? $updatedStudent->enrollment_status : 'not found'));
            
            log_message('debug', 'Raw SQL update result: ' . ($directUpdate ? 'success' : 'failed'));
            log_message('debug', 'SQL executed: ' . $sql . ' with values: ' . $enrollmentStatus . ', ' . $studentId);
            log_message('debug', 'Affected rows: ' . $db->affectedRows());
            
            // Check if there's a database constraint issue
            $columnInfo = $db->query("SHOW COLUMNS FROM students LIKE 'enrollment_status'")->getRow();
            log_message('debug', 'Column info: ' . json_encode($columnInfo));
            
            // Try a direct test update
            $testUpdate = $db->query("UPDATE students SET enrollment_status = 'transferred' WHERE id = ?", [$studentId]);
            log_message('debug', 'Direct test update result: ' . ($testUpdate ? 'success' : 'failed'));
            
            // Check final status
            $finalStudent = $db->table('students')->where('id', $studentId)->get()->getRow();
            log_message('debug', 'Final student status: ' . ($finalStudent ? $finalStudent->enrollment_status : 'not found'));
            
            // Update using the model but skip validation since we already validated
            $studentModel->skipValidation(true);
            $updateResult = $studentModel->update($studentId, $data);
            $studentModel->skipValidation(false);
            
            if ($updateResult) {
                // Verify the update actually happened
                $updatedStudent = $studentModel->find($studentId);
                log_message('debug', 'Student update - Data sent: ' . json_encode($data));
                log_message('debug', 'Student update - Result: ' . json_encode($updatedStudent));

                $trackedFields = [
                    'first_name', 'last_name', 'middle_name', 'email', 'contact_number', 'address',
                    'grade_level', 'section_id', 'student_type', 'enrollment_status', 'gender',
                    'date_of_birth', 'previous_school', 'student_type',
                ];
                $beforeSnapshot = array_intersect_key((array) $currentStudent, array_flip($trackedFields));
                $afterSnapshot  = array_intersect_key((array) ($updatedStudent ?? $data), array_flip($trackedFields));

                audit_event('student.updated', [
                    'category'      => 'data',
                    'status'        => 'success',
                    'resource_type' => 'student',
                    'resource_id'   => (string) $studentId,
                    'description'   => 'Student record updated',
                ] + audit_diff($beforeSnapshot, $afterSnapshot, $trackedFields));

                return redirect()->to('admin/students/edit/' . $studentId)->with('success', 'Student updated successfully.');
            } else {
                $errors = $studentModel->errors();
                log_message('error', 'Student update failed: ' . json_encode($errors));
                log_message('error', 'Student update data: ' . json_encode($data));
                return redirect()->back()->with('error', 'Failed to update student: ' . implode(', ', $errors));
            }
        } catch (\Exception $e) {
            log_message('error', 'Student update error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update student.');
        }
    }

    /**
     * Approve student enrollment and send email verification
     */
    public function approve($studentId)
    {
        if (! auth()->loggedIn() || ! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        helper('student_auth');

        $studentModel = model(StudentModel::class);
        $userModel    = model(UserModel::class);

        $student = $studentModel->find($studentId);
        if (! $student) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Student not found']);
        }

        $status       = (string) ($student['enrollment_status'] ?? '');
        $isPending    = $status === 'pending';
        $isRepairSync = $status === 'enrolled' && trim((string) ($student['temp_password'] ?? '')) !== '';

        if (! $isPending && ! $isRepairSync) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'This application is not pending approval (current status: ' . ($status !== '' ? $status : 'unknown') . ').',
            ]);
        }

        $userId = (int) ($student['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Student has no linked login account. They may need to register again.',
            ]);
        }

        $user = $userModel->find($userId);
        if (! $user) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Login account not found for this student.']);
        }

        $studentEmail = resolve_student_account_email($student, $user);
        if ($studentEmail === null) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Student has no valid email on file. Update their email before approving.',
            ]);
        }

        $plainPassword = trim((string) ($student['temp_password'] ?? ''));
        if ($plainPassword === '') {
            log_message('warning', 'Student approve: no temp_password for student id ' . $studentId . '; login password was not changed.');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            if ($isPending) {
                $studentModel->update($studentId, ['enrollment_status' => 'enrolled']);
            }

            $user->active = 1;
            $userModel->save($user);

            $passwordSyncWarning = false;
            if ($plainPassword !== '') {
                if (! sync_student_auth_password($userId, $studentEmail, $plainPassword)) {
                    log_message('error', 'Student approve: password sync failed for student id ' . $studentId . '. Approval will continue; set the password manually via Records.');
                    $passwordSyncWarning = true;
                }
            } else {
                $identity = $db->table('auth_identities')
                    ->where('user_id', $userId)
                    ->where('type', 'email_password')
                    ->get()
                    ->getRow();

                if (! $identity) {
                    throw new \RuntimeException('No login credentials found. Ask the student to register again or reset their password.');
                }

                $db->table('auth_identities')
                    ->where('user_id', $userId)
                    ->where('type', 'email_password')
                    ->update([
                        'name'       => $studentEmail,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database error while approving student.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Student approval failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Approval failed: ' . $e->getMessage(),
            ]);
        }

        $loginPassword = $plainPassword !== '' ? $plainPassword : '(use the password you chose when registering)';

        $studentName  = trim($student['first_name'] . ' ' . $student['last_name']);
        $emailSent    = false;

        try {
            if ($plainPassword !== '') {
                $emailService = new SupabaseEmailService();
                log_message('info', 'Attempting to send approval email to: ' . $studentEmail);
                $emailSent = $emailService->sendVerificationEmail(
                    $studentEmail,
                    $studentName,
                    (string) $student['lrn'],
                    $plainPassword
                );
                log_message('info', 'Email send result: ' . ($emailSent ? 'SUCCESS' : 'FAILED'));
            }
        } catch (\Throwable $e) {
            log_message('error', 'Student approval email error: ' . $e->getMessage());
        }

        $message = $isRepairSync
            ? 'Login credentials updated. The student can sign in with their LRN and registration password.'
            : 'Enrollment approved. The student can log in with their LRN and registration password.';
        if (! empty($passwordSyncWarning)) {
            $message .= ' WARNING: the login password could not be saved automatically. Open the student record and use Update Password to set it manually.';
        }

        audit_event('student.approved', [
            'category'      => 'data',
            'status'        => 'success',
            'resource_type' => 'student',
            'resource_id'   => (string) $studentId,
            'description'   => 'Student enrollment approved',
            'metadata'      => [
                'lrn'                 => $student['lrn'] ?? null,
                'email'               => $studentEmail,
                'password_sync_warning' => (bool) ($passwordSyncWarning ?? false),
            ],
        ]);

        return $this->response->setJSON([
            'success'     => true,
            'message'     => $message,
            'credentials' => [
                'lrn'      => $student['lrn'],
                'email'    => $studentEmail,
                'password' => $loginPassword,
            ],
        ]);
    }

    /**
     * Reject student enrollment
     */
    public function reject($studentId)
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $studentModel = model(StudentModel::class);
        $userModel = model(UserModel::class);
        
        $student = $studentModel->find($studentId);
        if (!$student) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Student not found']);
        }

        // Update student status to rejected
        $studentModel->update($studentId, ['enrollment_status' => 'rejected']);
        
        // Get student email for notification
        $user = $userModel->find($student['user_id']);
        $studentEmail = null;
        
        // Prioritize student table email first, then fallback to user table
        if (isset($student['email']) && !empty($student['email']) && filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
            $studentEmail = $student['email'];
        } elseif ($user && isset($user->email) && !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            $studentEmail = $user->email;
        }
        
        // Send rejection email if email exists
        $emailSent = false;
        if ($studentEmail) {
            $emailService = new SupabaseEmailService();
            $studentName = $student['first_name'] . ' ' . $student['last_name'];
            log_message('info', 'Attempting to send rejection email to: ' . $studentEmail);
            $emailSent = $emailService->sendRejectionEmail($studentEmail, $studentName);
            log_message('info', 'Rejection email send result: ' . ($emailSent ? 'SUCCESS' : 'FAILED'));
        }
        
        $message = 'Student application rejected.';

        audit_event('student.rejected', [
            'category'      => 'data',
            'status'        => 'success',
            'resource_type' => 'student',
            'resource_id'   => (string) $student['id'],
            'description'   => 'Student application rejected',
            'metadata'      => [
                'lrn'        => $student['lrn'] ?? null,
                'email'      => $studentEmail,
                'email_sent' => (bool) $emailSent,
            ],
        ]);
        
        return $this->response->setJSON([
            'success' => true,
            'message' => $message
        ]);
    }

    /**
     * Bulk approve pending student applications
     * Expects JSON: { student_ids: [id, id, ...] }
     */
    public function bulkApprove()
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $input = $this->request->getJSON(true);
        $studentIds = $input['student_ids'] ?? [];

        if (empty($studentIds) || ! is_array($studentIds)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'No students selected']);
        }

        $approved = 0;
        $failed   = 0;
        $errors   = [];

        foreach ($studentIds as $studentId) {
            $studentId = (int) $studentId;
            if ($studentId <= 0) {
                continue;
            }

            $resp    = $this->approve($studentId);
            $payload = json_decode($resp->getJSON(), true);

            // NOTE: do not trust getStatusCode() here — $this->response is a
            // shared object and child approve() calls set persistent status
            // codes (400/500). Judge success by the JSON payload only.
            if ($payload['success'] ?? false) {
                $approved++;
            } else {
                $failed++;
                $errors[] = 'Student #' . $studentId . ': ' . ($payload['error'] ?? 'Failed to approve');
            }
        }

        // Reset the shared response status before answering (child calls may
        // have left it at 400/500).
        $this->response->setStatusCode(200);

        if ($approved === 0) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'No applications were approved. ' . implode(' ', $errors),
            ]);
        }

        $message = $approved . ' application(s) approved successfully.';
        if ($failed > 0) {
            $message .= ' ' . $failed . ' failed: ' . implode(' ', $errors);
        }

        return $this->response->setJSON([
            'success'  => true,
            'message'  => $message,
            'approved' => $approved,
            'failed'   => $failed,
        ]);
    }

    /**
     * Bulk reject pending student applications
     * Expects JSON: { student_ids: [id, id, ...] }
     */
    public function bulkReject()
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $input = $this->request->getJSON(true);
        $studentIds = $input['student_ids'] ?? [];

        if (empty($studentIds) || ! is_array($studentIds)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'No students selected']);
        }

        $rejected = 0;
        $failed   = 0;
        $errors   = [];

        foreach ($studentIds as $studentId) {
            $studentId = (int) $studentId;
            if ($studentId <= 0) {
                continue;
            }

            $resp    = $this->reject($studentId);
            $payload = json_decode($resp->getJSON(), true);

            // NOTE: do not trust getStatusCode() here — $this->response is a
            // shared object and child reject() calls set persistent status
            // codes (400/500). Judge success by the JSON payload only.
            if ($payload['success'] ?? false) {
                $rejected++;
            } else {
                $failed++;
                $errors[] = 'Student #' . $studentId . ': ' . ($payload['error'] ?? 'Failed to reject');
            }
        }

        // Reset the shared response status before answering (child calls may
        // have left it at 400/500).
        $this->response->setStatusCode(200);

        if ($rejected === 0) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'No applications were rejected. ' . implode(' ', $errors),
            ]);
        }

        $message = $rejected . ' application(s) rejected successfully.';
        if ($failed > 0) {
            $message .= ' ' . $failed . ' failed: ' . implode(' ', $errors);
        }

        return $this->response->setJSON([
            'success'  => true,
            'message'  => $message,
            'rejected' => $rejected,
            'failed'   => $failed,
        ]);
    }

    /**
     * Archive student (soft delete)
     */
    public function archive($studentId)
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $studentModel = model(StudentModel::class);
        $student = $studentModel->find($studentId);
        
        if (!$student) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Student not found']);
        }

        if ($studentModel->delete($studentId)) {
            audit_event('student.archived', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'student',
                'resource_id'   => (string) $studentId,
                'description'   => 'Student archived',
                'before'        => [
                    'lrn'  => $student['lrn'] ?? null,
                    'name' => trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')),
                ],
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Student archived successfully.'
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON([
            'error' => 'Failed to archive student.'
        ]);
    }

    /**
     * Bulk archive students
     */
    public function bulkArchive()
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $input = $this->request->getJSON(true);
        $studentIds = $input['student_ids'] ?? [];

        if (empty($studentIds) || !is_array($studentIds)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'No students selected']);
        }

        $studentModel = model(StudentModel::class);
        $archived = 0;

        foreach ($studentIds as $studentId) {
            if ($studentModel->delete($studentId)) {
                $archived++;
            }
        }

        if ($archived > 0) {
            return $this->response->setJSON([
                'success' => true,
                'message' => "Successfully archived {$archived} student(s)."
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON([
            'error' => 'Failed to archive students.'
        ]);
    }

    /**
     * Bulk restore students
     */
    public function bulkRestore()
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $input = $this->request->getJSON(true);
        $studentIds = $input['student_ids'] ?? [];

        if (empty($studentIds) || !is_array($studentIds)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'No students selected']);
        }

        $db = \Config\Database::connect();
        $restored = 0;

        foreach ($studentIds as $studentId) {
            if ($db->table('students')->where('id', $studentId)->update(['deleted_at' => null])) {
                $restored++;
            }
        }

        if ($restored > 0) {
            return $this->response->setJSON([
                'success' => true,
                'message' => "Successfully restored {$restored} student(s)."
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to restore students.']);
    }

    /**
     * Bulk delete permanently
     */
    public function bulkDeletePermanently()
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $input = $this->request->getJSON(true);
        $studentIds = $input['student_ids'] ?? [];

        if (empty($studentIds) || !is_array($studentIds)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'No students selected']);
        }

        $studentModel = model(StudentModel::class);
        $userModel = model(UserModel::class);
        $db = \Config\Database::connect();
        $deleted = 0;

        $db->transStart();
        
        foreach ($studentIds as $studentId) {
            $student = $studentModel->onlyDeleted()->find($studentId);
            if ($student && $student['user_id']) {
                $db->table('auth_identities')->where('user_id', $student['user_id'])->delete();
                $db->table('auth_groups_users')->where('user_id', $student['user_id'])->delete();
                $userModel->delete($student['user_id'], true);
            }
            if ($studentModel->delete($studentId, true)) {
                $deleted++;
            }
        }
        
        $db->transComplete();

        if ($db->transStatus() !== false && $deleted > 0) {
            return $this->response->setJSON([
                'success' => true,
                'message' => "Successfully deleted {$deleted} student(s) permanently."
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to delete students.']);
    }

    /**
     * Show archived students
     */
    public function archived()
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $studentModel = model(StudentModel::class);
        $archivedStudents = $studentModel->onlyDeleted()->findAll();

        return view('admin/students_archived', [
            'title' => 'Archived Students - CSCS Tap n Track',
            'archivedStudents' => $archivedStudents
        ]);
    }

    /**
     * View archived student details
     */
    public function viewArchived($studentId)
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('/'));
        }

        $studentModel = model(StudentModel::class);
        $documentModel = null;

        $student = $studentModel->select('students.*, sections.section_name, users.email as user_email')
            ->join('sections', 'sections.id = students.section_id', 'left')
            ->join('users', 'users.id = students.user_id', 'left')
            ->onlyDeleted()
            ->find($studentId);

        if (!$student) {
            return redirect()->to('admin/students/archived')->with('error', 'Archived student not found');
        }

        $student['email'] = !empty($student['email']) ? $student['email'] : ($student['user_email'] ?? '');

        $documentsByType = [];

        return view('admin/student_view', [
            'title' => 'Archived Student Details - CSCS Tap n Track',
            'student' => $student,
            'documents' => $documentsByType,
            'isArchived' => true
        ]);
    }

    /**
     * Restore archived student
     */
    public function restore($studentId)
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $studentModel = model(StudentModel::class);
        $student = $studentModel->onlyDeleted()->find($studentId);
        
        if (!$student) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Archived student not found']);
        }

        // Restore the student by updating deleted_at to NULL
        $db = \Config\Database::connect();
        $result = $db->table('students')
            ->where('id', $studentId)
            ->update(['deleted_at' => null]);

        if ($result) {
            audit_event('student.restored', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'student',
                'resource_id'   => (string) $studentId,
                'description'   => 'Archived student restored',
                'metadata'      => ['lrn' => $student['lrn'] ?? null],
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Student restored successfully.'
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON([
            'error' => 'Failed to restore student.'
        ]);
    }

    /**
     * View document as a separate page
     */
    public function viewDocument($filename)
    {
        if (! is_any_admin()) {
            return redirect()->to(base_url('login'));
        }

        return redirect()->back()->with('error', 'Document viewing is no longer available.');
    }

    /**
     * Permanently delete student
     */
    public function deletePermanently($studentId)
    {
        if (! is_any_admin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $studentModel = model(StudentModel::class);
        $userModel = model(UserModel::class);

        $student = $studentModel->find($studentId);
        if (!$student) {
            $student = $studentModel->onlyDeleted()->find($studentId);
        }
        if (!$student) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Student not found']);
        }

        // Delete all related records permanently
        $db = \Config\Database::connect();
        $db->transStart();
        
        try {
            // Delete auth_identities first
            if ($student['user_id']) {
                $db->table('auth_identities')->where('user_id', $student['user_id'])->delete();
                $db->table('auth_groups_users')->where('user_id', $student['user_id'])->delete();
                $userModel->delete($student['user_id'], true); // Force delete
            }
            
            // Permanently delete student record
            $studentModel->delete($studentId, true); // Force delete
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                throw new \Exception('Transaction failed');
            }

            audit_event('student.deleted', [
                'category'      => 'data',
                'status'        => 'success',
                'resource_type' => 'student',
                'resource_id'   => (string) $studentId,
                'description'   => 'Student permanently deleted',
                'before'        => [
                    'lrn'   => $student['lrn'] ?? null,
                    'name'  => trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')),
                    'email' => $student['email'] ?? null,
                ],
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Student permanently deleted.'
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Failed to permanently delete student: ' . $e->getMessage()
            ]);
        }
    }

    public function viewGrades($studentId)
    {
        $studentModel = new \App\Models\StudentModel();
        $gradeModel = new \App\Models\GradeModel();
        $subjectModel = new \App\Models\SubjectModel();
        
        $student = $studentModel->find($studentId);
        if (!$student) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Student not found');
        }
        
        $schoolYear = get_current_school_year();
        $subjects = $student['section_id'] ? $subjectModel->getSectionSubjects($student['section_id']) : [];
        
        $grades = [];
        $allTermGrades = [];

        for ($term = 1; $term <= 3; $term++) {
            $termGrades = [];
            $termTotal = 0;
            $termCount = 0;

            foreach ($subjects as $subject) {
                $grade = $gradeModel->where('student_id', $studentId)
                    ->where('subject_id', $subject['id'])
                    ->where('school_year', $schoolYear)
                    ->where('term', $term)
                    ->first();

                $termGrades[] = [
                    'subject' => $subject,
                    'grade' => $grade
                ];

                if ($grade && $grade['grade']) {
                    $termTotal += $grade['grade'];
                    $termCount++;
                }
            }

            $grades[$term] = $termGrades;
            $allTermGrades[$term] = $termCount > 0 ? $termTotal / $termCount : null;
        }

        $gwa = $gradeModel->getFinalAverage($studentId, $schoolYear);

        return view('admin/student_grades', [
            'title' => 'Student Grades - ' . $student['first_name'] . ' ' . $student['last_name'],
            'student' => $student,
            'grades' => $grades,
            'allTermGrades' => $allTermGrades,
            'gwa' => $gwa,
            'schoolYear' => $schoolYear
        ]);
    }

    public function rejectApplication($applicationId)
    {
        return $this->response->setJSON($this->performRejection((int) $applicationId));
    }

    /**
     * Reject one application and return a plain result array so bulk actions can
     * aggregate per-application outcomes. The original body lives in
     * buildRejectionResponse().
     */
    private function performRejection(int $applicationId): array
    {
        $decoded = json_decode((string) $this->buildRejectionResponse($applicationId)->getBody(), true);

        return is_array($decoded)
            ? $decoded
            : ['success' => false, 'error' => 'Unexpected server response while rejecting the application.'];
    }

    private function buildRejectionResponse($applicationId)
    {
        $db = \Config\Database::connect();
        
        $application = $db->table('next_year_applications')->where('id', $applicationId)->get()->getRow();
        if (!$application) {
            return $this->response->setJSON(['success' => false, 'error' => 'Application not found.']);
        }
        
        $result = $db->table('next_year_applications')
            ->where('id', $applicationId)
            ->update([
                'status'       => 'rejected',
                'processed_at' => date('Y-m-d H:i:s'),
                'processed_by' => auth()->id() ?: null,
                'remarks'      => 'Application not approved. Please talk to your adviser.',
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        
        if ($result) {
            $studentModel = new \App\Models\StudentModel();
            $student = $studentModel->find($application->student_id);
            $emailSent = false;
            
            if ($student) {
                // Notify the student inside the portal (in addition to the email).
                if (!empty($student['user_id'])) {
                    try {
                        model(\App\Models\NotificationModel::class)->insert([
                            'user_id'    => (int) $student['user_id'],
                            'type'       => 'enrollment_rejected',
                            'title'      => 'Enrollment Application Update',
                            'message'    => 'Your application for S.Y. ' . $application->school_year . ' was not approved. Please come and talk to your adviser to discuss your options.',
                            'is_read'    => 0,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    } catch (\Exception $e) {
                        log_message('error', 'Failed to create rejection notification: ' . $e->getMessage());
                    }
                }

                $userModel = model(UserModel::class);
                $user = $userModel->find($student['user_id']);
                $studentEmail = null;
                
                // Prioritize student table email first, then fallback to user table
                if (isset($student['email']) && !empty($student['email']) && filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
                    $studentEmail = $student['email'];
                } elseif ($user && isset($user->email) && !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                    $studentEmail = $user->email;
                }
                
                if ($studentEmail && filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $emailService = new SupabaseEmailService();
                        $studentName = $student['first_name'] . ' ' . $student['last_name'];
                        $emailSent = $emailService->sendRejectionEmail($studentEmail, $studentName);
                    } catch (\Exception $e) {
                        log_message('error', 'Failed to send rejection email: ' . $e->getMessage());
                    }
                }
            }
            
            $message = 'Application rejected successfully.';
            
            return $this->response->setJSON(['success' => true, 'message' => $message]);
        }
        
        return $this->response->setJSON(['success' => false, 'error' => 'Failed to reject application.']);
    }

    public function promote($studentId)
    {
        $input = $this->request->getJSON(true);
        $nextGradeLevel = $input['next_grade_level'] ?? null;

        if (!$nextGradeLevel) {
            return $this->response->setJSON(['success' => false, 'error' => 'Next grade level is required.']);
        }

        return $this->response->setJSON($this->performPromotion((int) $studentId, (int) $nextGradeLevel));
    }

    /**
     * Promote one student and return a plain result array so bulk actions can
     * aggregate per-student outcomes. The actual work (validation, DB update,
     * application stamping, portal notification, email) lives in
     * buildPromotionResponse() — the original promote() body — so the single
     * and bulk paths can never drift apart.
     */
    private function performPromotion(int $studentId, int $nextGradeLevel): array
    {
        $decoded = json_decode((string) $this->buildPromotionResponse($studentId, $nextGradeLevel)->getBody(), true);

        return is_array($decoded)
            ? $decoded
            : ['success' => false, 'error' => 'Unexpected server response while promoting the student.'];
    }

    private function buildPromotionResponse($studentId, $nextGradeLevel)
    {
        
        if (!$nextGradeLevel) {
            return $this->response->setJSON(['success' => false, 'error' => 'Next grade level is required.']);
        }
        
        $studentModel = new \App\Models\StudentModel();
        $student = $studentModel->find($studentId);
        
        if (!$student) {
            return $this->response->setJSON(['success' => false, 'error' => 'Student not found.']);
        }
        
        log_message('debug', '=== PROMOTE FUNCTION DEBUG ===');
        log_message('debug', 'Student ID: ' . $studentId);
        log_message('debug', 'Student data: ' . json_encode($student));
        
        $currentGrade = (int) $student['grade_level'];

        // Grade levels with no next level in this school:
        // 6 = Grade 6 (elementary exit - graduates instead),
        // 7 = SNED (terminal), 99 = custom class.
        // NOTE: is_graduating_grade() only returns true for grade 7 because
        // grade_level_max() is 7, which would have let Grade 6 students be
        // promoted into SNED — so the levels are listed explicitly here.
        if (in_array($currentGrade, [6, 7, 99], true)) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => $currentGrade === 6
                    ? 'Grade 6 students cannot be promoted. They should graduate instead.'
                    : 'Students in this program cannot be promoted to a higher grade level.'
            ]);
        }

        $expectedNext = $currentGrade + 1;
        if ((int) $nextGradeLevel !== $expectedNext) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Invalid grade level. Expected ' . grade_level_label($expectedNext) . '.'
            ]);
        }
        
        try {
            $db = \Config\Database::connect();
            
            // Update student grade level and clear section assignment
            $result1 = $studentModel->update($studentId, [
                'grade_level' => $nextGradeLevel,
                'section_id' => null
            ]);
            
            if (!$result1) {
                return $this->response->setJSON(['success' => false, 'error' => 'Failed to update student records.']);
            }
            
            $nextSchoolYear = $this->getNextSchoolYear();

            // Mark the next-year application (if any) as approved and processed.
            // The row is optional: admins may also promote students directly, so
            // a missing application row must not fail the promotion.
            $db->table('next_year_applications')
                ->where('student_id', $studentId)
                ->where('school_year', $nextSchoolYear)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'approved',
                    'processed_at' => date('Y-m-d H:i:s'),
                    'processed_by' => auth()->id() ?: null,
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
            
            // Notify the student inside the portal (in addition to the email).
            if (!empty($student['user_id'])) {
                model(\App\Models\NotificationModel::class)->insert([
                    'user_id'    => (int) $student['user_id'],
                    'type'       => 'enrollment_approved',
                    'title'      => 'Enrollment Approved',
                    'message'    => 'Congratulations! Your application to enroll in ' . grade_level_label((int) $nextGradeLevel) . ' for S.Y. ' . $nextSchoolYear . ' has been approved. You will be assigned to a section when classes open.',
                    'is_read'    => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            
            // Send email notification to student
            $userModel = model(UserModel::class);
            $user = $userModel->find($student['user_id']);
            
            $studentEmail = null;
            
            // Prioritize student table email first (more up-to-date), then fallback to user table
            if (isset($student['email']) && !empty($student['email']) && filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
                $studentEmail = $student['email'];
            } elseif ($user && isset($user->email) && !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                $studentEmail = $user->email;
            }
            
            log_message('debug', 'Final email to send to: ' . ($studentEmail ?? 'NULL'));
            log_message('debug', '=== END PROMOTE DEBUG ===');
            
            $emailSent = false;
            if ($studentEmail) {
                try {
                    $emailService = new SupabaseEmailService();
                    $studentName = $student['first_name'] . ' ' . $student['last_name'];
                    log_message('info', 'Attempting to send promotion email to: ' . $studentEmail);
                    $emailSent = $emailService->sendPromotionEmail($studentEmail, $studentName, $nextGradeLevel);
                    log_message('info', 'Promotion email send result: ' . ($emailSent ? 'SUCCESS' : 'FAILED'));
                } catch (\Exception $e) {
                    log_message('error', 'Failed to send promotion email: ' . $e->getMessage());
                }
            }
            
            $message = 'Student promoted to ' . grade_level_label((int) $nextGradeLevel) . ' successfully!';
            
            return $this->response->setJSON([
                'success' => true,
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'error' => 'Failed to promote student: ' . $e->getMessage()]);
        }
    }


    private function getNextSchoolYear()
    {
        $current = get_current_school_year();
        $years = explode('-', $current);
        return ($years[0] + 1) . '-' . ($years[1] + 1);
    }
    
    public function unassignSubjects($studentId)
    {
        log_message('info', 'unassignSubjects called for student ID: ' . $studentId);
        
        if (! is_any_admin()) {
            log_message('error', 'Unauthorized access attempt');
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }
        
        $input = $this->request->getJSON(true);
        log_message('info', 'Input received: ' . json_encode($input));
        
        $subjectIds = $input['subject_ids'] ?? [];
        log_message('info', 'Subject IDs: ' . json_encode($subjectIds));
        
        if (empty($subjectIds)) {
            log_message('error', 'No subjects selected');
            return $this->response->setJSON(['success' => false, 'error' => 'No subjects selected']);
        }
        
        $studentModel = model(StudentModel::class);
        $student = $studentModel->find($studentId);
        
        if (!$student) {
            log_message('error', 'Student not found: ' . $studentId);
            return $this->response->setJSON(['success' => false, 'error' => 'Student not found']);
        }
        
        // Get current excluded subjects
        $currentExcluded = !empty($student['excluded_subjects']) ? explode(',', $student['excluded_subjects']) : [];
        
        // Add new excluded subjects
        $newExcluded = array_unique(array_merge($currentExcluded, $subjectIds));
        
        // Use direct database update to avoid model validation issues
        $db = \Config\Database::connect();
        $result = $db->table('students')
            ->where('id', $studentId)
            ->update(['excluded_subjects' => implode(',', $newExcluded)]);
        
        if ($result !== false) {
            return $this->response->setJSON([
                'success' => true,
                'message' => "Successfully unassigned " . count($subjectIds) . " subject(s)"
            ]);
        }
        
        return $this->response->setJSON([
            'success' => false,
            'error' => 'Failed to update student record'
        ]);
    }
}
