<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds teachers.personnel_edit_enabled — lets the school administrator
 * allow or lock the teacher's own editing of their Personnel Record
 * (teacher/profile -> Personnel Record tab). Defaults to enabled.
 */
class AddPersonnelEditFlagToTeachers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('teachers', [
            'personnel_edit_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
                'after'      => 'employment_status',
                'comment'    => '1 = teacher may edit their personnel record, 0 = locked by admin',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('teachers', 'personnel_edit_enabled');
    }
}
