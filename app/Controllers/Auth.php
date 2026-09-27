<?php
namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Models\UserModel;
use App\Models\StudentModel;
use App\Models\ParentModel;
use App\Models\LoginAttemptModel;
use App\Models\PlatformRatingModel;

class Auth extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    public function login()
    {
        // If user is already logged in, redirect to dashboard
        try {
            if ($this->auth->loggedIn()) {
                return redirect()->to($this->getDashboardUrl());
            }
        } catch (\Throwable $e) {
            // Database may not be configured yet; continue to show login form
        }

        // Get registration status
        try {
            $systemSettingModel = new \App\Models\SystemSettingModel();
            $registrationSetting = $systemSettingModel->getSetting('registration_enabled', null);
            if ($registrationSetting === null) {
                $registrationSetting = $systemSettingModel->getSetting('enrollment_enabled', 1); // backward compatibility
            }
            $registrationEnabled = (bool) $registrationSetting;
        } catch (\Throwable $e) {
            $registrationEnabled = true;
        }

        // A fresh arithmetic CAPTCHA for every page load. The expected answer is
        // stored in the session (helper('arithmetic_captcha')); only the
        // question is passed to the view. Regenerating here means every render
        // — including the redirect back from any login attempt — presents a
        // challenge the visitor has not seen before.
        $captchaQuestion = arithmetic_captcha_generate();

        // Use modern login page
        return view('auth/login', [
            'title' => 'Login - CSCS Tap n Track',
            'registrationEnabled' => $registrationEnabled,
            'teacherRegistrationEnabled' => $this->isTeacherRegistrationOpen(),
            'captchaQuestion' => $captchaQuestion,
        ]);
    }

    /**
     * Fresh CSRF token for the login form.
     *
     * When a user waits out a lockout countdown (or restores the login page
     * from the back/forward cache), the token embedded in the rendered form
     * may no longer match the session's hash — the submit then dies with a
     * bare 403 "The action you requested is not allowed" page. The login page
     * refreshes its token from here (see its countdown/pageshow handlers) so
     * the retry always carries a valid one.
     */
    public function csrfToken()
    {
        return $this->response->setJSON([
            'name'  => csrf_token(),
            'token' => csrf_hash(),
        ]);
    }

    /**
     * AJAX endpoint behind the login page's "Refresh CAPTCHA" control.
     *
     * Issues a brand-new challenge in the caller's session and returns ONLY the
     * question text. The expected answer stays server-side, so repeatedly
     * calling this endpoint cannot be used to enumerate answers — and because
     * generate() overwrites the stored answer, the previously displayed
     * question stops being valid the moment this runs.
     *
     * GET, so it needs no CSRF token, matching the existing
     * login/csrf-token endpoint. It reveals nothing confidential and performs
     * no state change beyond rotating this session's own CAPTCHA.
     */
    public function captchaRefresh()
    {
        return $this->response->setJSON([
            'question' => arithmetic_captcha_generate(),
        ]);
    }

    public function attempt()
    {
        $rules = [
            'identifier' => 'required',
            'password' => 'required',
        ] + arithmetic_captcha_rule();

        if (!$this->validate($rules, arithmetic_captcha_validation_messages())) {
            // Burn the challenge even when another field failed, so a
            // half-completed attempt can never be replayed with the same
            // CAPTCHA. login() issues a new one on the redirect.
            arithmetic_captcha_check(null);

            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Server-side CAPTCHA verification — the primary security control. The
        // answer lives only in the session; this consumes it (pop-on-read) and
        // reports whether it matched. Runs BEFORE the lockout and credential
        // work, so a wrong CAPTCHA costs the visitor nothing and cannot be used
        // to probe accounts.
        if (! arithmetic_captcha_check($this->request->getPost('captcha_answer'))) {
            return redirect()->back()->withInput()
                ->with('errors', ['Incorrect CAPTCHA answer.']);
        }

        $identifier = trim((string) $this->request->getPost('identifier'));
        $password = (string) $this->request->getPost('password');
        $remember = (bool) $this->request->getPost('remember');
        $ipAddress = $this->request->getIPAddress();

        try {
            return $this->performLoginAttempt($identifier, $password, $remember, $ipAddress);
        } catch (DatabaseException $e) {
            log_message('critical', 'Login database error: ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'The system cannot connect to the database right now. Please try again in a few minutes or contact your school administrator.');
        }
    }

    /**
     * @return ResponseInterface
     */
    private function performLoginAttempt(string $identifier, string $password, bool $remember, string $ipAddress)
    {
        // Check if user is locked out
        $lockoutInfo = $this->checkLockout($identifier, $ipAddress);
        if ($lockoutInfo['locked']) {
            audit_event('auth.login_blocked', [
                'category'    => 'auth',
                'status'      => 'blocked',
                'description' => 'Sign-in blocked: account temporarily locked',
                'metadata'    => [
                    'reason'       => 'lockout',
                    'identifier'   => $identifier,
                    'locked_until' => $lockoutInfo['locked_until'] ?? null,
                ],
            ]);

            return redirect()->back()->withInput()
                ->with('error', $lockoutInfo['message'])
                ->with('locked_until', $lockoutInfo['locked_until']);
        }

        helper(['auth', 'student_auth']);

        // Find user by PRC license (teacher) or LRN (student)
        $teacherModel = model('TeacherModel');
        $studentModel = model('StudentModel');
        $userModel = model(UserModel::class);
        
        $user = null;
        
        // Check if it's a teacher (PRC license or email)
        $teacher = $teacherModel->where('license_number', $identifier)
                                ->orWhere('email', $identifier)
                                ->first();
        
        // Debug logging
        log_message('info', 'Login attempt - Identifier: ' . $identifier);
        log_message('info', 'Teacher found: ' . ($teacher ? 'Yes (ID: ' . $teacher['id'] . ', User ID: ' . ($teacher['user_id'] ?? 'NULL') . ')' : 'No'));
        
        if ($teacher && $teacher['user_id']) {
            $user = $userModel->find($teacher['user_id']);
            log_message('info', 'User found from teacher: ' . ($user ? 'Yes (ID: ' . $user->id . ')' : 'No'));
        }
        
        // Check if it's a student (LRN or email)
        if (!$user) {
            $student = $studentModel->where('lrn', $identifier)->first();
            if (!$student && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $student = $studentModel->where('email', $identifier)->first();
            }
            log_message('info', 'Student found by LRN/email: ' . ($student ? 'Yes (ID: ' . $student['id'] . ', User ID: ' . ($student['user_id'] ?? 'NULL') . ')' : 'No'));
            if ($student && !empty($student['user_id'])) {
                $user = $userModel->find($student['user_id']);
                log_message('info', 'User found from student: ' . ($user ? 'Yes (ID: ' . $user->id . ')' : 'No'));
            }
        }
        
        // Check by email (fallback for teachers/admin/other users not found via role tables)
        if (!$user && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = $userModel->where('email', $identifier)->first();
        }
        
        if (!$user) {
            audit_event('auth.login_failed', [
                'category'    => 'auth',
                'status'      => 'failure',
                'description' => 'Sign-in failed: unknown identifier',
                'metadata'    => ['reason' => 'unknown_identifier', 'identifier' => $identifier],
            ]);

            log_message('info', 'Login failed - No user found for identifier: ' . $identifier);
            // Also check what teachers exist in database for debugging
            $allTeachers = $teacherModel->select('id, first_name, last_name, license_number, email')->findAll();
            log_message('info', 'All teachers in database: ' . json_encode($allTeachers));
            return redirect()->back()->withInput()->with('error', 'Invalid PRC license number, LRN, email, or password.');
        }
        
        // Get password hash from auth_identities
        $db = \Config\Database::connect();
        $identity = $db->table('auth_identities')
            ->where('user_id', $user->id)
            ->where('type', 'email_password')
            ->get()
            ->getRow();
        
        log_message('info', 'Auth identity found: ' . ($identity ? 'Yes (Name: ' . $identity->name . ')' : 'No'));

        $passwordValid = verify_auth_identity_password($password, $identity);
        log_message('info', 'Password verification: ' . ($passwordValid ? 'Success' : 'Failed'));
        
        if (!$identity || !$passwordValid) {
            audit_event('auth.login_failed', [
                'category'    => 'auth',
                'status'      => 'failure',
                'description' => 'Sign-in failed: invalid credentials',
                'metadata'    => ['reason' => 'invalid_credentials', 'identifier' => $identifier],
            ] + audit_actor_snapshot($user));

            log_message('info', 'Login failed - Invalid credentials for user ID: ' . $user->id);
            $this->recordFailedAttempt($identifier, $ipAddress);
            $lockoutInfo = $this->checkLockout($identifier, $ipAddress);
            $response = redirect()->back()->withInput()->with('error', $lockoutInfo['locked'] ? $lockoutInfo['message'] : 'Invalid PRC license number, LRN, email, or password.');
            if ($lockoutInfo['locked']) {
                $response = $response->with('locked_until', $lockoutInfo['locked_until']);
            }
            return $response;
        }
        
        // Block sign-in for teacher login accounts that are not usable: a
        // pending or rejected self-registration, a `teachers` row that was
        // deleted, or a teacher whose employment is no longer active.
        // teacher_account_state() resolves the teachers row (by user link, then
        // by email for legacy unlinked rows) and is the single source of truth
        // shared with Auth::getDashboardUrl(), Auth::demo() and
        // TeacherAccessFilter - so a rejected applicant can never sign in or
        // reach the teacher portal through any entry point.
        helper('teacher_access');

        $teacherState = teacher_account_state($user);

        if (! teacher_account_allowed($user)) {
            audit_event('auth.login_blocked', [
                'category'    => 'auth',
                'status'      => 'blocked',
                'description' => 'Sign-in blocked: teacher account is not usable (' . $teacherState . ')',
                'metadata'    => ['reason' => 'teacher_account_state', 'teacher_state' => $teacherState],
            ] + audit_actor_snapshot($user));

            log_message('info', 'Login blocked - teacher account state "' . $teacherState . '" for user ID: ' . $user->id);

            $response = redirect()->back()->withInput()
                ->with('error', teacher_account_denial_message($teacherState));

            if ($teacherState === 'pending') {
                $response = $response->with('pending_approval', true);
            } elseif ($teacherState === 'rejected') {
                $response = $response->with('registration_rejected', true);
            }

            return $response;
        }

        // Accounts without any known role: nothing to sign in to.
        if (! $user->inGroup('admin') && ! $user->inGroup('admin_staff')
            && ! $user->inGroup('teacher') && ! $user->inGroup('student') && ! $user->inGroup('parent')) {
            audit_event('auth.login_blocked', [
                'category'    => 'auth',
                'status'      => 'blocked',
                'description' => 'Sign-in blocked: account has no portal role',
                'metadata'    => ['reason' => 'no_role'],
            ] + audit_actor_snapshot($user));

            log_message('info', 'Login blocked - user account has no role for user ID: ' . $user->id);

            return redirect()->back()->withInput()
                ->with('error', 'This account is no longer active. Please contact the school office.');
        }

        // A student login account without a student record is equally broken.
        if ($user->inGroup('student') && ! $user->inGroup('admin')
            && ! model('StudentModel')->where('user_id', $user->id)->countAllResults()) {
            audit_event('auth.login_blocked', [
                'category'    => 'auth',
                'status'      => 'blocked',
                'description' => 'Sign-in blocked: student record missing',
                'metadata'    => ['reason' => 'missing_student_record'],
            ] + audit_actor_snapshot($user));

            log_message('info', 'Login blocked - user account has no student record (deleted?) for user ID: ' . $user->id);

            return redirect()->back()->withInput()
                ->with('error', 'This account is no longer active. Please contact the school office.');
        }

        // Handle remember me functionality
        if ($remember) {
            // Store only the identifier cookie for pre-filling login form
            // Password is intentionally NOT stored in cookies for security
            setcookie('remembered_identifier', $identifier, [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            // Clear any previously stored password cookie
            setcookie('remembered_password', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            // Clear remember me cookies if not checked
            setcookie('remembered_identifier', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            setcookie('remembered_password', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        
        // Ensure user is active
        if ((int) ($user->active ?? 0) !== 1) {
            // Never silently re-activate a teacher login account. Its access is
            // governed by the teachers row (checked above), and an administrator
            // may have disabled the login on purpose - rejecting the
            // registration sets active = 0 - so signing in must not switch the
            // account back on and undo that decision.
            if ($user->inGroup('teacher')) {
                audit_event('auth.login_blocked', [
                    'category'    => 'auth',
                    'status'      => 'blocked',
                    'description' => 'Sign-in blocked: teacher login account is disabled',
                    'metadata'    => ['reason' => 'inactive_teacher_login'],
                ] + audit_actor_snapshot($user));

                log_message('info', 'Login blocked - disabled teacher login account for user ID: ' . $user->id);

                return redirect()->back()->withInput()
                    ->with('error', 'This teacher account is not active. Please contact the school office.');
            }

            $user->active = 1;
            $userModel->save($user);
        }
        
        // Manually log in the user
        $sessionAuth = auth()->getAuthenticator('session');
        $sessionAuth->login($user);

        audit_event('auth.login', [
            'category'    => 'auth',
            'status'      => 'success',
            'description' => 'Signed in',
            'metadata'    => ['identifier' => $identifier, 'remember' => $remember],
        ] + audit_actor_snapshot($user));

        // Clear failed attempts on successful login
        $this->clearFailedAttempts($identifier, $ipAddress);

        helper('admin_access');
        if ($user->inGroup('admin_staff')) {
            $dest = admin_staff_post_login_redirect_url((int) $user->id);
            if ($dest === null) {
                $sessionAuth->logout();

                audit_event('auth.login_blocked', [
                    'category'    => 'auth',
                    'status'      => 'blocked',
                    'description' => 'Sign-in blocked: no admin portal pages assigned',
                    'metadata'    => ['reason' => 'no_admin_pages_assigned'],
                ] + audit_actor_snapshot($user));

                return redirect()->to(base_url('login'))
                    ->with('error', 'No admin portal pages have been assigned to your account. Please contact a master administrator.');
            }

            return redirect()->to($dest);
        }
        
        return redirect()->to($this->getDashboardUrl());
    }

    private function checkLockout(string $identifier, string $ipAddress): array
    {
        try {
            $attemptModel = new LoginAttemptModel();
            $attempt = $attemptModel->where('identifier', $identifier)
                                    ->where('ip_address', $ipAddress)
                                    ->first();
        } catch (\Throwable $e) {
            log_message('error', 'Login lockout check skipped: ' . $e->getMessage());

            return ['locked' => false];
        }

        if (!$attempt) {
            return ['locked' => false];
        }

        if ($attempt['locked_until'] && strtotime($attempt['locked_until']) > time()) {
            $remainingTime = strtotime($attempt['locked_until']) - time();
            $minutes = max(1, (int) ceil($remainingTime / 60));
            return [
                'locked' => true,
                'message' => "Too many failed login attempts. Please try again in {$minutes} minute(s).",
                'locked_until' => $attempt['locked_until']
            ];
        }

        // Lock expired — reset counter so the next attempt is not instantly locked again
        if ($attempt['locked_until'] && strtotime($attempt['locked_until']) <= time()) {
            $attemptModel->update($attempt['id'], [
                'attempts'     => 0,
                'locked_until' => null,
            ]);
        }

        return ['locked' => false];
    }

    private function recordFailedAttempt(string $identifier, string $ipAddress): void
    {
        try {
            $attemptModel = new LoginAttemptModel();
            $attempt = $attemptModel->where('identifier', $identifier)
                                    ->where('ip_address', $ipAddress)
                                    ->first();
        } catch (\Throwable $e) {
            log_message('error', 'Failed to record login attempt: ' . $e->getMessage());

            return;
        }

        if ($attempt) {
            $newAttempts = $attempt['attempts'] + 1;
            $lockoutMinutes = $this->calculateLockoutTime($newAttempts);
            
            $attemptModel->update($attempt['id'], [
                'attempts' => $newAttempts,
                'locked_until' => $lockoutMinutes > 0 ? date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60)) : null
            ]);
        } else {
            $attemptModel->insert([
                'identifier' => $identifier,
                'ip_address' => $ipAddress,
                'attempts' => 1,
                'locked_until' => null
            ]);
        }
    }

    private function calculateLockoutTime(int $attempts): int
    {
        if ($attempts < 3) {
            return 0;
        }
        // Progressive lockout: 1 min, 5 min, 10 min, 30 min, 60 min, etc.
        $lockoutTimes = [1, 5, 10, 30, 60];
        $index = $attempts - 3;
        return $lockoutTimes[$index] ?? 60;
    }

    private function clearFailedAttempts(string $identifier, string $ipAddress): void
    {
        try {
            $attemptModel = new LoginAttemptModel();
            $attemptModel->where('identifier', $identifier)
                         ->where('ip_address', $ipAddress)
                         ->delete();
        } catch (\Throwable $e) {
            log_message('error', 'Failed to clear login attempts: ' . $e->getMessage());
        }
    }

    public function register()
    {
        if (! $this->isRegistrationOpen()) {
            return redirect()->to(base_url('login'))
                ->with('error', 'Student registration is currently closed. Please check back later or contact the school office.');
        }

        return view('auth/register', [
            'title' => 'Student Registration - CSCS Tap n Track',
        ]);
    }

    public function forgot()
    {
        // Delegate to Shield magic link if enabled, otherwise show informational page
        if (setting('Auth.allowMagicLinkLogins')) {
            return redirect()->to(url_to('magic-link'));
        }
        return redirect()->to(base_url('login'))
            ->with('error', 'Password recovery is not enabled. Please contact the administrator.');
    }

    /**
     * Redirect logged-in user to the correct dashboard
     */
    public function dashboard()
    {
        try {
            if ($this->auth->loggedIn()) {
                return redirect()->to($this->getDashboardUrl());
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return redirect()->to(base_url('login'));
    }

    public function store()
    {
        if (! $this->isRegistrationOpen()) {
            return redirect()->to(base_url('login'))
                ->with('error', 'Student registration is currently closed.');
        }

        helper('student_form');

        // Legacy/typed input (+63…, dashes) -> 09XXXXXXXXX before any rule runs.
        phone_normalize_request($this->request, ['contact_number', 'emergency_contact_number']);

        // Religion is a selector of the ten most populated Philippine
        // affiliations plus an "Other" choice: swap that choice for its
        // free-text companion before validation so `religion` always holds the
        // value the applicant actually typed.
        religion_normalize_request($this->request);

        // Nationality works the same way. The list has always ended with "Other",
        // but until the companion box existed that option could only ever store
        // the literal word "Other", so the in_list rule below is replaced by a
        // length rule now that the value can be free text.
        nationality_normalize_request($this->request);

        // Relationship "Other" is resolved to its free-text companion before
        // validation, so the literal word "Other" is never stored.
        emergency_contact_relationship_normalize_request($this->request);

        // Transferees must say where they came from. The rules are only added
        // for that student type, so new and old students are never asked for
        // it and never blocked by it.
        $studentType = (string) $this->request->getPost('student_type');
        $isTransferee = $studentType === 'Transferee';

        $rules = [
            'first_name' => 'required|max_length[100]',
            'last_name' => 'required|max_length[100]',
            // Applicants without a middle name tick the "No middle name" box;
            // otherwise a middle name is required and must be 2+ characters.
            'middle_name' => middle_name_validation_rule(),
            'email' => 'required|valid_email|is_unique[users.email]',
            'password' => password_validation_rule('password'),
            'password_confirm' => 'required|matches[password]',
            'gender' => 'required|in_list[Male,Female]',
            'date_of_birth' => 'required|valid_date',
            'grade_level' => 'required|integer|greater_than_equal_to[0]|less_than[7]',
            'lrn' => 'required|exact_length[12]|numeric|is_unique[students.lrn]',
            'student_type' => 'required|in_list[New Student,Transferee,Old Student]',
            'place_of_birth' => 'required|max_length[255]',
            // Selector value, or the free text typed under the "Other" choice
            // (nationality_normalize_request() has already resolved that choice,
            // so an in_list rule here would reject the very value the applicant
            // was just asked to type).
            'nationality' => 'required|max_length[100]',
            // Selector value, or the free text typed under the "Other" choice
            // (religion_normalize_request() has already resolved that choice).
            'religion' => 'required|max_length[100]',
            'contact_number' => phone_required_validation_rule(),
            'address' => 'required',
            'emergency_contact_name' => 'required|max_length[255]',
            'emergency_contact_number' => phone_required_validation_rule(),
            'emergency_contact_relationship' => emergency_contact_relationship_rule(),
        ];

        if ($isTransferee) {
            $rules['previous_school'] = 'required|max_length[255]';
            $rules['previous_school_year'] = 'required|in_list[' . implode(',', previous_school_year_choices()) . ']';
        }

        if (!$this->validate($rules, array_merge(phone_validation_messages([
            'contact_number' => 'Contact Number',
            'emergency_contact_number' => 'Emergency Contact Number',
        ]), religion_validation_messages(), nationality_validation_messages(), emergency_contact_relationship_validation_messages(), password_validation_messages('password'), $isTransferee ? [
            'previous_school' => 'Previous School',
            'previous_school_year' => 'School Year Last Attended',
        ] : []))) {
            $errors = $this->validator->getErrors();
            $errorStep = $this->detectErrorStep($errors);
            return redirect()->back()->withInput()->with('errors', $errors)->with('error_step', $errorStep);
        }

        $userModel = new UserModel();
        $studentModel = new StudentModel();

        // Start transaction
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $email = $this->request->getPost('email');
            $password = $this->request->getPost('password');
            $firstName = $this->request->getPost('first_name');
            $lastName = $this->request->getPost('last_name');

            if (! $userModel->save([
                'email'    => $email,
                'password' => $password,
                'active'   => 0,
            ])) {
                throw new \Exception('Failed to create user account');
            }

            $userId = (int) $userModel->getInsertID();
            $user = $userModel->findById($userId);

            if (! $user) {
                throw new \Exception('Failed to load new user account');
            }

            $user->fill([
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ]);
            $userModel->save($user);
            $user->addGroup('student');

            $gradeLevel = (int) $this->request->getPost('grade_level');

            $studentData = [
                'user_id' => $userId,
                'lrn' => $this->request->getPost('lrn'),
                'student_type' => $studentType,
                // Only transferees carry a previous school; the columns stay
                // NULL for new and old students.
                'previous_school' => $isTransferee ? $this->request->getPost('previous_school') : null,
                'previous_school_year' => $isTransferee ? $this->request->getPost('previous_school_year') : null,
                'first_name' => $this->request->getPost('first_name'),
                'middle_name' => normalize_middle_name(),
                'last_name' => $this->request->getPost('last_name'),
                'suffix' => $this->request->getPost('suffix'),
                'gender' => $this->request->getPost('gender'),
                'date_of_birth' => $this->request->getPost('date_of_birth'),
                'place_of_birth' => $this->request->getPost('place_of_birth'),
                'nationality' => $this->request->getPost('nationality') ?: 'Filipino',
                'religion' => $this->request->getPost('religion'),
                'contact_number' => $this->request->getPost('contact_number'),
                'email' => $this->request->getPost('email'),
                'address' => $this->request->getPost('address'),
                'emergency_contact_name' => $this->request->getPost('emergency_contact_name'),
                'emergency_contact_number' => $this->request->getPost('emergency_contact_number'),
                'emergency_contact_relationship' => $this->request->getPost('emergency_contact_relationship'),
                'enrollment_status' => 'pending',
                'grade_level' => $gradeLevel,
                'school_year' => get_current_school_year(),
                'temp_password' => $this->request->getPost('password')
            ];
            
            log_message('debug', 'Registration - Student data to save: ' . json_encode($studentData));

            $studentId = $studentModel->skipValidation(true)->insert($studentData);

            if (!$studentId) {
                $errors = $studentModel->errors();
                $errorMsg = !empty($errors) ? implode(', ', $errors) : 'Unknown database error';
                throw new \Exception('Failed to create student record: ' . $errorMsg);
            }

            helper('student_auth');
            if (! sync_student_auth_password($userId, $email, $password)) {
                throw new \Exception('Failed to configure login credentials');
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Transaction failed');
            }

            audit_event('account.registered', [
                'category'      => 'account',
                'status'        => 'success',
                'resource_type' => 'student',
                'resource_id'   => (string) $studentId,
                'description'   => 'Student self-registration submitted (pending approval)',
                'metadata'      => ['email' => $email, 'grade_level' => $gradeLevel],
            ]);

            return redirect()->to(base_url('login'))
                ->with('success', 'Registration submitted successfully! Your application is now pending approval by school administrators. You may log in with your email and password to check its status.');

        } catch (\Exception $e) {
            $db->transRollback();
            return redirect()->back()->withInput()
                ->with('error', 'Registration failed: ' . $e->getMessage())
                ->with('error_step', 4);
        }
    }
    
    /**
     * Public teacher self-registration form.
     *
     * Uses the same look as the student registration page so teachers can sign
     * themselves up from a shared link instead of the administrator creating
     * every teacher account by hand.
     */
    public function teacherRegister()
    {
        if (! $this->isTeacherRegistrationOpen()) {
            return redirect()->to(base_url('login'))
                ->with('error', 'Teacher registration is currently closed. Please contact the school administrator.');
        }

        helper('teacher_form');

        return view('auth/teacher_register', [
            'title' => 'Teacher Registration - CSCS Tap n Track',
        ]);
    }

    /**
     * Whether teacher self-registration is enabled.
     *
     * Defaults to enabled; an administrator can close it with the
     * "teacher_registration_enabled" system setting.
     */
    private function isTeacherRegistrationOpen(): bool
    {
        try {
            $systemSettingModel = new \App\Models\SystemSettingModel();
            $setting = $systemSettingModel->getSetting('teacher_registration_enabled', '1');

            return (bool) $setting;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Store a teacher self-registration.
     *
     * The account is created with a pending registration status and cannot sign
     * in until an administrator approves it from the Pending Registrations page.
     */
    public function teacherStore()
    {
        if (! $this->isTeacherRegistrationOpen()) {
            return redirect()->to(base_url('login'))
                ->with('error', 'Teacher registration is currently closed.');
        }

        helper('teacher_form');

        // Plain 09XXXXXXXXX is the only accepted format; normalise anything
        // legacy (0917-123-4567, +63917…) before the rules evaluate.
        phone_normalize_request($this->request, ['contact_number']);

        // Religion is a selector with an "Other" free-text box: resolve that
        // choice to what was typed before the rules evaluate, so the real value
        // is validated and stored (withInput() also repopulates it on failure).
        religion_normalize_request($this->request);

        // Same treatment for Position, Teaching Area, Designation and Civil
        // Status, which each grew an "Other" box to match the student form.
        // Runs after religion so a shared field would still resolve, and before
        // the rules below, which therefore check the typed text rather than the
        // literal word "Other".
        teacher_other_choices_normalize_request($this->request);

        $rules = [
            'first_name'           => 'required|min_length[2]|max_length[100]|regex_match[/^[a-zA-Z\s\-\']+$/]',
            // The same "No middle name" policy the student form uses: the field is
            // required unless the box is ticked, in which case it is left out of
            // validation entirely. A supplied middle name must be 2+ characters,
            // so a lone letter is rejected.
            'middle_name'          => middle_name_validation_rule() . '|regex_match[/^[a-zA-Z\s\-\']*$/]',
            'last_name'            => 'required|min_length[2]|max_length[100]|regex_match[/^[a-zA-Z\s\-\']+$/]',
            'suffix'               => 'permit_empty|max_length[10]',
            'gender'               => 'required|in_list[Male,Female]',
            // Was a bare `valid_date`, which happily accepted a future date and
            // a date over a century old. Now bounded on both ends.
            'date_of_birth'        => teacher_dob_validation_rule(),
            'religion'             => 'required|max_length[50]',
            // max_length rather than in_list: the "Other" escape hatch means the
            // value is free text by the time these rules run.
            'civil_status'         => 'permit_empty|max_length[30]',
            'email'                => 'required|valid_email|is_unique[users.email]',
            'contact_number'       => phone_required_validation_rule(),
            'address'              => 'required|max_length[500]',
            'philsys_number'       => 'permit_empty|exact_length[12]|numeric',
            'position'             => 'required|max_length[100]',
            'subjects'             => 'required|max_length[100]',
            'designation'          => 'permit_empty|max_length[100]',
            'license_number'       => 'permit_empty|exact_length[7]|numeric',
            'tin'                  => 'permit_empty|regex_match[/^\d{3}-\d{3}-\d{3}$/]',
            'baccalaureate_degree' => 'permit_empty|max_length[255]',
            'prc_specialization'   => 'permit_empty|max_length[100]',
            'masters_degree'       => 'permit_empty|max_length[255]',
            'password'             => password_validation_rule('password'),
            'password_confirm'     => 'required|matches[password]',
        ];

        if (! $this->validate($rules, array_merge(
            phone_validation_messages(['contact_number' => 'Contact Number']),
            religion_validation_messages(50),
            teacher_dob_validation_messages(),
            teacher_other_choices_validation_messages(),
            [
                'middle_name' => [
                    'required'   => 'Please enter your middle name, or tick "No middle name" if you do not have one.',
                    'min_length' => 'Middle Name must be at least 2 characters long. Tick the "No middle name" box if you do not have one.',
                ],
            ],
            password_validation_messages('password')
        ))) {
            return redirect()->back()->withInput()
                ->with('errors', $this->validator->getErrors())
                ->with('error_step', $this->resolveTeacherRegistrationErrorStep());
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');

        $teacherModel = model('TeacherModel');

        if ($teacherModel->where('email', $email)->first()) {
            return redirect()->back()->withInput()
                ->with('error', 'A teacher record already uses this email address. Please contact the school administrator.');
        }

        return $this->createPendingTeacherAccount($email, $password, $teacherModel);
    }

    /**
     * Maps the field that failed server-side validation to the wizard step that
     * contains it, so the registration form reopens on the correct step.
     */
    private function resolveTeacherRegistrationErrorStep(): int
    {
        $errors = $this->validator->getErrors();

        $stepMap = [
            'first_name'           => 1,
            'middle_name'          => 1,
            'no_middle_name'       => 1,
            'last_name'            => 1,
            'suffix'               => 1,
            'gender'               => 1,
            'date_of_birth'        => 1,
            'religion'             => 1,
            'religion_other'       => 1,
            'civil_status'         => 1,
            'civil_status_other'   => 1,
            'philsys_number'       => 1,
            'tin'                  => 1,
            'email'                => 2,
            'contact_number'       => 2,
            'address'              => 2,
            'position'             => 3,
            'position_other'       => 3,
            'subjects'             => 3,
            'subjects_other'       => 3,
            'designation'          => 3,
            'designation_other'    => 3,
            'license_number'       => 3,
            'baccalaureate_degree' => 3,
            'prc_specialization'   => 3,
            'masters_degree'       => 3,
            'password'             => 3,
            'password_confirm'     => 3,
        ];

        foreach ($stepMap as $field => $step) {
            if (isset($errors[$field])) {
                return $step;
            }
        }

        return 1;
    }

    /**
     * Persist a pending teacher registration (user + credentials + teacher row).
     *
     * Login is blocked until an administrator approves the registration, which
     * activates the user record and attaches the 'teacher' group.
     */
    private function createPendingTeacherAccount(string $email, string $password, $teacherModel)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $db->table('users')->insert([
                'email'      => $email,
                'active'     => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $userId = (int) $db->insertID();
            if ($userId <= 0) {
                throw new \Exception('Failed to create user account');
            }

            $db->table('auth_identities')->insert([
                'user_id'      => $userId,
                'type'         => 'email_password',
                'name'         => $email,
                'secret'       => $email,
                'secret2'      => password_hash($password, PASSWORD_DEFAULT),
                'expires'      => null,
                'extra'        => null,
                'force_reset'  => 0,
                'last_used_at' => null,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            // The 'teacher' group is attached only when the administrator
            // approves the registration.

            $teachingArea = trim((string) $this->request->getPost('subjects'));

            $teacherData = [
                'user_id'              => $userId,
                'first_name'           => trim((string) $this->request->getPost('first_name')),
                // Goes through the shared normaliser, so ticking "No middle name"
                // stores NULL. Reading the post directly here would keep a stale
                // value typed before the box was ticked.
                'middle_name'          => normalize_middle_name(),
                'last_name'            => trim((string) $this->request->getPost('last_name')),
                'suffix'               => trim((string) $this->request->getPost('suffix')) ?: null,
                'gender'               => $this->request->getPost('gender'),
                'date_of_birth'        => $this->request->getPost('date_of_birth'),
                'contact_number'       => $this->request->getPost('contact_number') ?: null,
                'email'                => $email,
                'address'              => $this->request->getPost('address') ?: null,
                'position'             => $this->request->getPost('position'),
                'designation'          => $this->request->getPost('designation') ?: null,
                'department'           => $teachingArea ?: null,
                'specialization'       => trim((string) $this->request->getPost('prc_specialization')) ?: ($teachingArea ?: null),
                'prc_specialization'   => trim((string) $this->request->getPost('prc_specialization')) ?: null,
                'license_number'       => $this->request->getPost('license_number') ?: null,
                'tin'                  => teacher_format_tin($this->request->getPost('tin')),
                'philsys_number'       => teacher_normalize_philsys($this->request->getPost('philsys_number')),
                'religion'             => trim((string) $this->request->getPost('religion')) ?: null,
                'civil_status'         => $this->request->getPost('civil_status') ?: null,
                'baccalaureate_degree' => trim((string) $this->request->getPost('baccalaureate_degree')) ?: null,
                'masters_degree'       => trim((string) $this->request->getPost('masters_degree')) ?: null,
                'employment_status'    => 'inactive',
                'registration_status'  => 'pending',
            ];

            $teacherModel->skipValidation(true);
            $teacherId = $teacherModel->insert($teacherData);
            $teacherModel->skipValidation(false);

            if (! $teacherId) {
                throw new \Exception('Failed to create teacher record');
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Transaction failed');
            }

            log_message('info', 'Teacher self-registration submitted: ' . $email . ' (teacher id ' . $teacherId . ')');

            audit_event('account.registered', [
                'category'      => 'account',
                'status'        => 'success',
                'resource_type' => 'teacher',
                'resource_id'   => (string) $teacherId,
                'description'   => 'Teacher self-registration submitted (pending approval)',
                'metadata'      => ['email' => $email],
            ]);

            return redirect()->to(base_url('login'))
                ->with('success', 'Teacher registration submitted! A school administrator will review your account. You can sign in once your registration has been approved.');
        } catch (\Throwable $e) {
            $db->transRollback();

            log_message('error', 'Teacher self-registration failed: ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Registration failed. Please review your details and try again.');
        }
    }

    private function isRegistrationOpen(): bool
    {
        try {
            $systemSettingModel = new \App\Models\SystemSettingModel();
            $registrationSetting = $systemSettingModel->getSetting('registration_enabled', null);
            if ($registrationSetting === null) {
                $registrationSetting = $systemSettingModel->getSetting('enrollment_enabled', 1);
            }

            return (bool) $registrationSetting;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function detectErrorStep(array $errors): int
    {
        $step1Fields = ['first_name', 'last_name', 'middle_name', 'gender', 'date_of_birth', 'grade_level', 'lrn', 'student_type', 'previous_school', 'previous_school_year', 'place_of_birth', 'nationality', 'nationality_other', 'religion', 'religion_other'];
        $step2Fields = ['email', 'contact_number', 'address'];
        $step3Fields = ['emergency_contact_name', 'emergency_contact_number', 'emergency_contact_relationship'];
        $step4Fields = ['password', 'password_confirm'];
        
        foreach ($errors as $field => $error) {
            if (in_array($field, $step1Fields)) return 1;
            if (in_array($field, $step2Fields)) return 2;
            if (in_array($field, $step3Fields)) return 3;
            if (in_array($field, $step4Fields)) return 4;
        }
        
        return 1;
    }

    public function logout(): ResponseInterface
    {
        if ($this->auth->loggedIn()) {
            helper('platform_rating');
            $user = $this->auth->user();
            $role = platform_rating_responder_role($user);

            if ($role !== null) {
                $model = new PlatformRatingModel();
                if (! $model->hasSubmittedForCurrentTerm((int) $user->id)) {
                    // A student whose profile is still incomplete cannot reach
                    // the Dashboard (it is locked until the checklist is done),
                    // so do not bounce them there to rate the platform — that
                    // redirect would otherwise be unreachable and could block
                    // them from ever logging out again.
                    $lockedStudent = false;
                    if ($role === 'student') {
                        try {
                            $ratingStudent = (new StudentModel())->where('user_id', (int) $user->id)->first();
                            $lockedStudent = $ratingStudent !== null && ! student_profile_complete($ratingStudent);
                        } catch (\Throwable $e) {
                            $lockedStudent = false;
                        }
                    }

                    if (! $lockedStudent) {
                        $dashboard = $role === 'teacher'
                            ? base_url('teacher/dashboard')
                            : base_url('student/dashboard');

                        return redirect()->to($dashboard)->with('platform_rating_required', true);
                    }
                }
            }
        }

        audit_event('auth.logout', [
            'category'    => 'auth',
            'status'      => 'success',
            'description' => 'Signed out',
        ]);

        $this->auth->logout();

        return redirect()->to(base_url('/'));
    }

    public function demo($role = null, $subRole = null)
    {
        // Quick access is a review tool only - it must never be reachable on the
        // school's live portal.
        if (! demo_accounts_enabled()) {
            return redirect()->to(base_url('login'))
                ->with('error', 'Quick access is not available.');
        }

        // Build the role key from parameters
        $roleKey = $subRole !== null
            ? strtolower((string) $role) . '/' . strtolower((string) $subRole)
            : strtolower((string) $role);

        if (!$roleKey) {
            return redirect()->to(base_url('login'))
                ->with('error', 'Invalid demo role specified.');
        }

        // Demo credentials come from the shared fixture list so the quick-access
        // panel, this controller and the seeder can never disagree about which
        // accounts exist.
        $fixture = demo_account_fixture($roleKey);

        if ($fixture === null) {
            return redirect()->to(base_url('login'))
                ->with('error', 'Invalid demo role: ' . $roleKey);
        }

        // Repair drift before judging the account. Without this, a demo teacher
        // whose `teachers` row was wiped by a data reset is refused with "This
        // account is no longer active. Please contact the school office." - a
        // dead end for a login that only exists to let a reviewer inside.
        // demo_account_reconcile() only ever touches the demo email whitelist.
        $repair = demo_account_reconcile($roleKey);

        if (! empty($repair['repaired'])) {
            log_message('info', 'Quick access account repaired: ' . $roleKey . ' (user ID ' . $repair['user_id'] . ')');
        }

        $cred = [
            'email'      => (string) $fixture['email'],
            'password'   => (string) $fixture['password'],
            'identifier' => (string) ($fixture['identifier'] ?? ''),
            'redirect'   => base_url((string) $fixture['redirect']),
        ];

        $db = \Config\Database::connect();
        $userModel = model(UserModel::class);

        // Find user by email
        $user = $userModel->where('email', $cred['email'])->first();

        if (!$user) {
            log_message('error', "Demo login failed - User not found: {$cred['email']}");

            return redirect()->to(base_url('login'))
                ->with('error', 'Demo account not found. Please run the demo seeder first.');
        }

        // Verify password
        $identity = $db->table('auth_identities')
            ->where('user_id', $user->id)
            ->where('type', 'email_password')
            ->get()
            ->getRow();

        if (!$identity || !verify_auth_identity_password($cred['password'], $identity)) {
            log_message('error', "Demo login failed - Invalid password for: {$cred['email']}");
            return redirect()->to(base_url('login'))
                ->with('error', 'Demo account password is invalid. Please run: php spark db:seed DemoAccountsSeeder');
        }

        // Teacher demo accounts still obey the approval workflow: a pending,
        // rejected, deleted or closed teacher record must not be usable through
        // the demo shortcut either.
        $demoTeacher = null;

        if (strpos($roleKey, 'teacher') === 0) {
            helper('teacher_access');

            $demoTeacher = find_teacher_record_for_user($user);

            if ($demoTeacher === null && ! empty($cred['email'])) {
                $demoTeacher = model('TeacherModel')->where('email', $cred['email'])->first();
            }

            $demoTeacherState = teacher_record_state($demoTeacher);

            if ($demoTeacherState !== 'approved') {
                log_message('info', 'Demo login blocked - teacher record state "' . $demoTeacherState . '" for user ID: ' . $user->id);

                return redirect()->to(base_url('login'))
                    ->with('error', teacher_account_denial_message($demoTeacherState));
            }
        }

        // Ensure user is active
        if ((int) ($user->active ?? 0) !== 1) {
            $user->active = 1;
            $userModel->save($user);
        }
        // Special handling for teacher demo - log in as teacher
        if (strpos($roleKey, 'teacher') === 0) {
            $teacher = $demoTeacher ?? null;

            if ($teacher) {
                log_message('info', "Demo teacher login successful: {$teacher['first_name']} {$teacher['last_name']}");
            }
        }

        // Special handling for student demo - log in as student
        if (strpos($roleKey, 'student') === 0) {
            $studentModel = model('StudentModel');
            $student = $studentModel->where('user_id', $user->id)->first();
            
            if (!$student) {
                $student = $studentModel->where('email', $user->email)->first();
            }
            
            if ($student) {
                log_message('info', "Demo student login successful: {$student['first_name']} {$student['last_name']} (LRN: {$student['lrn']})");
            }
        }

        // Log in the user
        try {
            $sessionAuth = auth()->getAuthenticator('session');
            $sessionAuth->login($user);
            log_message('info', "Demo login successful - User ID: {$user->id}, Email: {$user->email}, Role: {$roleKey}");
        } catch (\Throwable $e) {
            log_message('error', 'Demo login session error: ' . $e->getMessage());
            return redirect()->to(base_url('login'))
                ->with('error', 'Demo login failed due to session error. Please try again.');
        }

        return redirect()->to($cred['redirect']);
    }

    /**
     * Get dashboard URL based on user role
     */
    private function getDashboardUrl(): string
    {
        // Check if user is logged in before accessing user data
        if (!$this->auth->loggedIn()) {
            return base_url('login');
        }

        try {
            helper('auth');
            $user = $this->auth->user();

            helper('admin_access');
            if ($user->inGroup('admin')) {
                return base_url('admin/dashboard');
            }
            if ($user->inGroup('admin_staff')) {
                return admin_staff_post_login_redirect_url((int) $user->id) ?? base_url('/');
            }
            if ($user->inGroup('teacher')) {
                // Only an approved, still-existing, still-active teacher record
                // may reach the teacher portal. This also closes sessions that
                // were opened before the registration was rejected (or deleted),
                // and it is the same decision teacher_account_state() makes for
                // the login form and TeacherAccessFilter.
                helper('teacher_access');

                if (! teacher_account_allowed($user)) {
                    $teacherState = teacher_account_state($user);

                    log_message('info', 'Dashboard redirect blocked - teacher account state "' . $teacherState . '" for user ID: ' . $user->id);

                    $this->auth->logout();
                    session()->setFlashdata('error', teacher_account_denial_message($teacherState));

                    return base_url('login');
                }

                return base_url('teacher/dashboard');
            } elseif ($user->inGroup('student')) {
                // Check if student is approved before allowing access
                $studentModel = new \App\Models\StudentModel();
                $student = $studentModel->where('user_id', $user->id)->first();

                if (!$student) {
                    // Try to find student by email as fallback
                    $student = $studentModel->where('email', user_login_email($user))->first();
                    
                    if (!$student) {
                        // Student record not found - logout and redirect to login with error
                        $this->auth->logout();
                        session()->setFlashdata('error', 'Student record not found. Please contact the administration.');
                        return base_url('login');
                    } else {
                        // Update student record with correct user_id
                        $studentModel->update($student['id'], ['user_id' => $user->id]);
                    }
                }

                // Check enrollment status - allow enrolled/approved students in.
                // A student who has not completed the profile checklist (BMI +
                // profile picture + 2x2 ID picture) lands on My Profile, which
                // is the only page that stays unlocked meanwhile.
                if ($student['enrollment_status'] === 'enrolled' || $student['enrollment_status'] === 'approved') {
                    return student_profile_complete($student)
                        ? base_url('student/dashboard')
                        : base_url('student/profile');
                } elseif ($student['enrollment_status'] === 'pending') {
                    // Student is pending approval - logout and show message
                    $this->auth->logout();
                    session()->setFlashdata('error', 'Your enrollment is still pending approval. Please wait for admin approval before accessing the system.');
                    return base_url('login');
                } elseif ($student['enrollment_status'] === 'rejected') {
                    // Student was rejected - logout and show message
                    $this->auth->logout();
                    session()->setFlashdata('error', 'Your enrollment application has been rejected. Please contact the administration for more information.');
                    return base_url('login');
                } else {
                    // Student has invalid status - logout and show message
                    $this->auth->logout();
                    session()->setFlashdata('error', 'Your account status is invalid. Please contact the administration.');
                    return base_url('login');
                }
            } elseif ($user->inGroup('parent')) {
                return base_url('parent/dashboard');
            }
        } catch (\Throwable $e) {
            // If there's any error getting user data, redirect to login
            return base_url('login');
        }

        return base_url('/');
    }

}