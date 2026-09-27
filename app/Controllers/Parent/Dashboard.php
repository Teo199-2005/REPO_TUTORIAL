<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Models\AnnouncementModel;

class Dashboard extends BaseController
{
    protected $auth;

    public function __construct()
    {
        $this->auth = auth();
    }

    public function index()
    {
        if (!$this->auth->user()->inGroup('parent')) {
            return redirect()->to(base_url('/'));
        }
        return view('parent/dashboard', ['title' => 'Parent Dashboard - CSCS Tap n Track']);
    }

    public function children()
    {
        if (!$this->auth->user()->inGroup('parent')) {
            return redirect()->to(base_url('/'));
        }
        return view('parent/children', ['title' => 'My Children - CSCS Tap n Track']);
    }

    public function childGrades($studentId)
    {
        if (!$this->auth->user()->inGroup('parent')) {
            return redirect()->to(base_url('/'));
        }
        return view('parent/grades', ['title' => 'Child Grades - CSCS Tap n Track', 'studentId' => $studentId]);
    }

    public function announcements()
    {
        if (!$this->auth->user()->inGroup('parent')) {
            return redirect()->to(base_url('/'));
        }
        
        $db = \Config\Database::connect();
        $userId = $this->auth->user()->id;
        
        $announcements = $db->query("
            SELECT a.*, 
                   CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END as is_read
            FROM announcements a
            LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
            WHERE a.deleted_at IS NULL AND a.target_roles IN ('parent', 'all')
            ORDER BY a.created_at DESC
        ", [$userId])->getResultArray();
        
        return view('parent/announcements', ['title' => 'Announcements - CSCS Tap n Track', 'announcements' => $announcements]);
    }

    public function viewAnnouncement($id)
    {
        if (!$this->auth->user()->inGroup('parent')) {
            return redirect()->to(base_url('/'));
        }

        $model = new AnnouncementModel();
        $announcement = $model->find($id);

        if (!$announcement) {
            return redirect()->to('parent/announcements')->with('error', 'Announcement not found.');
        }

        $db = \Config\Database::connect();
        $userId = $this->auth->user()->id;
        $exists = $db->table('announcement_reads')->where(['announcement_id' => $id, 'user_id' => $userId])->get()->getRow();
        if (!$exists) {
            $db->table('announcement_reads')->insert(['announcement_id' => $id, 'user_id' => $userId, 'read_at' => date('Y-m-d H:i:s')]);
        }

        return redirect()->to('parent/announcements');
    }
} 
