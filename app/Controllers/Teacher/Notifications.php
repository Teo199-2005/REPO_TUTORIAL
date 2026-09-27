<?php
namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Models\NotificationModel;

class Notifications extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    public function index()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        $notificationModel = new NotificationModel();
        
        // Get notifications for current teacher
        $notifications = $notificationModel
            ->where('user_id', $this->auth->user()->id)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return view('teacher/notifications', [
            'title' => 'Notifications - CSCS Tap n Track',
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark notification as read (AJAX)
     */
    public function markAsRead()
    {
        if (!$this->request->isAJAX() || !$this->auth->user()->inGroup('teacher')) {
            return $this->response->setStatusCode(403);
        }

        $notificationId = $this->request->getPost('notification_id');
        $notificationModel = new NotificationModel();

        // Verify the notification belongs to the current user
        $notification = $notificationModel->find($notificationId);
        if (!$notification || $notification['user_id'] != $this->auth->user()->id) {
            return $this->response->setJSON(['success' => false, 'message' => 'Notification not found']);
        }

        $updated = $notificationModel->update($notificationId, [
            'is_read' => true,
            'read_at' => date('Y-m-d H:i:s')
        ]);

        return $this->response->setJSON(['success' => $updated]);
    }

    /**
     * Get unread notification count (AJAX)
     */
    public function getUnreadCount()
    {
        if (!$this->request->isAJAX() || !$this->auth->user()->inGroup('teacher')) {
            return $this->response->setStatusCode(403);
        }

        $notificationModel = new NotificationModel();
        $count = $notificationModel
            ->where('user_id', $this->auth->user()->id)
            ->where('is_read', false)
            ->countAllResults();

        return $this->response->setJSON(['count' => $count]);
    }

    /**
     * Send notification to students (for teachers)
     */
    public function sendToStudents()
    {
        if (!$this->auth->user()->inGroup('teacher')) {
            return redirect()->to(base_url('/'));
        }

        $rules = [
            'title' => 'required|max_length[255]',
            'message' => 'required',
            'section_id' => 'permit_empty|integer'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $title = $this->request->getPost('title');
        $message = $this->request->getPost('message');
        $sectionId = $this->request->getPost('section_id');

        $notificationModel = new NotificationModel();
        $userModel = new \CodeIgniter\Shield\Models\UserModel();

        // Get students based on section or all students for this teacher
        $students = [];
        if ($sectionId) {
            // Get students from specific section
            $studentModel = new \App\Models\StudentModel();
            $sectionStudents = $studentModel->where('section_id', $sectionId)->findAll();
            
            foreach ($sectionStudents as $student) {
                $user = $userModel->find($student['user_id']);
                if ($user) {
                    $students[] = $user;
                }
            }
        } else {
            // Get all students (this could be refined based on teacher's sections)
            $students = $userModel->whereHas('groups', function($query) {
                $query->where('group', 'student');
            })->findAll();
        }

        // Send notification to each student
        $count = 0;
        foreach ($students as $student) {
            $result = $notificationModel->insert([
                'user_id' => $student->id,
                'type' => 'teacher_message',
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                $count++;
            }
        }

        if ($count > 0) {
            return redirect()->back()->with('success', "Notification sent to {$count} students successfully.");
        } else {
            return redirect()->back()->with('error', 'Failed to send notifications. Please try again.');
        }
    }
}
