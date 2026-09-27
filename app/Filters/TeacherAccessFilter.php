<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restricts teacher portal routes to teachers or admins (admins need export-pdf with teacher_id).
 */
class TeacherAccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // The teacher self-registration form (and its POST target) is public.
        // It lives under teacher/*, so the URI-pattern filter config would
        // otherwise lock it behind the login wall.
        $path = strtolower(trim(str_replace('/index.php', '', $request->getUri()->getPath()), '/ '));

        foreach (['teacher/register', 'teachers/register'] as $publicPath) {
            if ($path === $publicPath || str_ends_with($path, '/' . $publicPath)) {
                return null;
            }
        }

        $auth = service('auth');

        if (! $auth->loggedIn()) {
            return redirect()->to(base_url('login'));
        }

        helper(['admin_access', 'teacher_access']);

        $user = $auth->user();

        // A teacher login account is only usable while it still has a teachers
        // row whose registration is approved and whose employment is active.
        // The database is re-checked on every request, so a session created
        // before the administrator rejected the registration - or deleted the
        // teacher entirely - is closed immediately.
        //
        // This runs BEFORE the admin bypass: an account that was rejected must
        // never reach the teacher portal, even if it also holds an admin group.
        $teacherState = teacher_account_state($user);

        if (! teacher_account_allowed($user)) {
            log_message('info', 'Teacher portal access denied (' . $teacherState . ') for user ID: ' . (int) $user->id);

            $auth->logout();

            return redirect()->to(base_url('login'))
                ->with('error', teacher_account_denial_message($teacherState));
        }

        // Admins keep access to every teacher route (used for exports, etc.).
        if (is_any_admin()) {
            return null;
        }

        // The account is a teacher with a usable record.
        if ($user->inGroup('teacher')) {
            return null;
        }

        return redirect()->to(base_url('/'))->with('error', 'Access denied.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
