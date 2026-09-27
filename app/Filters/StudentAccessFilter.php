<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class StudentAccessFilter implements FilterInterface
{
    /**
     * Student URIs that stay reachable while the profile is incomplete:
     * My Profile (the only unlocked page — it is where the BMI fields and
     * both photos are provided) and logout. The dashboard joins this list
     * with the other portal pages once the profile is complete.
     */
    private const OPEN_WHILE_INCOMPLETE = [
        'student/profile',
        'logout',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        // Check if user is logged in
        if (!$auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        $user = $auth->user();

        // Check if user is a student
        if (!$user->inGroup('student')) {
            return redirect()->to(base_url('login'))->with('error', 'Access denied.');
        }

        // Check student enrollment status
        try {
            $studentModel = new \App\Models\StudentModel();
            $student = $studentModel->where('user_id', $user->id)->first();

            if (!$student) {
                // Student record not found - logout and redirect
                $auth->logout();
                return redirect()->to(base_url('login'))
                    ->with('error', 'Student record not found. Please contact the administration.');
            }

            // Check enrollment status
            if ($student['enrollment_status'] === 'pending') {
                // Student is pending approval
                $auth->logout();
                return redirect()->to(base_url('login'))
                    ->with('error', 'Your enrollment is still pending approval. Please wait for admin approval before accessing the system.');
            } elseif ($student['enrollment_status'] === 'rejected') {
                // Student was rejected
                $auth->logout();
                return redirect()->to(base_url('login'))
                    ->with('error', 'Your enrollment application has been rejected. Please contact the administration for more information.');
            } elseif ($student['enrollment_status'] !== 'approved' && $student['enrollment_status'] !== 'enrolled') {
                // Student has invalid status
                $auth->logout();
                return redirect()->to(base_url('login'))
                    ->with('error', 'Your account status is invalid. Please contact the administration.');
            }

            // Profile-completion gate: BMI fields + profile picture + 2x2 ID
            // picture. Everything outside the open list stays locked until
            // the student complies with all three requirements — the
            // Dashboard included, so a locked student lands on My Profile.
            if (! student_profile_complete($student) && ! $this->uriIsOpenWhileIncomplete($request)) {
                return redirect()->to(base_url('student/profile'))
                    ->with('info', 'Your portal is locked until your profile is complete. Finish the checklist on My Profile: BMI fields, profile picture and 2x2 ID picture.');
            }

            // Student is approved or enrolled - allow access
            return null;

        } catch (\Throwable $e) {
            // Error checking student status
            $auth->logout();
            return redirect()->to(base_url('login'))
                ->with('error', 'Unable to verify your enrollment status. Please try again later.');
        }
    }

    /**
     * True when the requested student URI stays reachable while the
     * profile is incomplete (the open list, or a nested child of one).
     */
    private function uriIsOpenWhileIncomplete(RequestInterface $request): bool
    {
        $path = trim($request->getUri()->getPath(), '/');

        // Strip a leading base path (app deployed in a subdirectory).
        $basePath = trim((string) (parse_url((string) config('App')->baseURL, PHP_URL_PATH) ?? ''), '/');
        if ($basePath !== '' && strpos($path, $basePath . '/') === 0) {
            $path = substr($path, strlen($basePath) + 1);
        }

        $path = strtolower($path);

        foreach (self::OPEN_WHILE_INCOMPLETE as $open) {
            if ($path === $open || strpos($path, $open . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after request
    }
}
