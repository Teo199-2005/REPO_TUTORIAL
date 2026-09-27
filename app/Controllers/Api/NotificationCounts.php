<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;

class NotificationCounts extends BaseController
{
    public function index()
    {
        if (!auth()->loggedIn()) {
            return $this->response->setJSON(['error' => 'Unauthorized'])->setStatusCode(401);
        }

        $user = auth()->user();
        $db = \Config\Database::connect();
        $counts = [];

        helper('admin_access');
        if (is_master_admin()) {
            $counts['pending_applications'] = $db->table('students')->where('enrollment_status', 'pending')->countAllResults();
            $counts['pending_teacher_registrations'] = $db->table('teachers')->where('registration_status', 'pending')->where('deleted_at', null)->countAllResults();
            $counts['password_resets'] = $db->table('password_reset_requests')->where('status', 'pending')->countAllResults();
            $counts['announcements'] = 0;
        } elseif (is_admin_staff()) {
            $uid = (int) $user->id;
            if (admin_staff_has_page($uid, 'students')) {
                $counts['pending_applications'] = $db->table('students')->where('enrollment_status', 'pending')->countAllResults();
            }
            if (admin_staff_has_page($uid, 'teachers')) {
                $counts['pending_teacher_registrations'] = $db->table('teachers')->where('registration_status', 'pending')->where('deleted_at', null)->countAllResults();
            }
            if (admin_staff_has_page($uid, 'password_resets')) {
                $counts['password_resets'] = $db->table('password_reset_requests')->where('status', 'pending')->countAllResults();
            }
            if (admin_staff_has_page($uid, 'announcements')) {
                $counts['announcements'] = 0;
            }
        } elseif ($user->inGroup('teacher')) {
            // Count unread announcements
            $unreadCount = $db->query("
                SELECT COUNT(*) as count
                FROM announcements a
                LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
                WHERE a.deleted_at IS NULL AND a.target_roles IN ('teacher', 'all', 'admin') AND ar.id IS NULL
            ", [$user->id])->getRow()->count;
            $counts['teacher_announcements'] = (int)$unreadCount;
            $counts['schedule_updates'] = $db->table('notifications')->where('user_id', $user->id)->where('is_read', 0)->where('type', 'schedule_update')->countAllResults();

        } elseif ($user->inGroup('student')) {
            $student = $db->table('students')->where('user_id', $user->id)->get()->getRow();
            if ($student) {
                $counts['new_grades'] = $db->table('grades')->where('student_id', $student->id)->where('created_at >=', date('Y-m-d H:i:s', strtotime('-7 days')))->countAllResults();
            } else {
                $counts['new_grades'] = 0;
            }
            // Count unread announcements for students
            $unreadCount = $db->query("
                SELECT COUNT(*) as count
                FROM announcements a
                LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
                WHERE a.deleted_at IS NULL AND a.target_roles IN ('student', 'all') AND ar.id IS NULL
            ", [$user->id])->getRow()->count;
            $counts['student_announcements'] = (int)$unreadCount;
        }

        return $this->response->setJSON($counts);
    }
}
