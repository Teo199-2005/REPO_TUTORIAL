<?php
namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'title' => 'Welcome to CSCS Tap n Track',
                'slug' => 'welcome-lphs-sms',
                'body' => 'Our School Management System is now live. Students can register online.',
                'target_roles' => 'all',
                'published_at' => date('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Enrollment Schedule',
                'slug' => 'enrollment-schedule',
                'body' => 'Enrollment opens on Sept 1 and closes on Sept 15. Prepare your documents.',
                'target_roles' => 'student,parent',
                'published_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('announcements')->insertBatch($data);
    }
}


