<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Records where a transferee came from.
 *
 * Only populated when students.student_type = 'Transferee'; new and old
 * students leave both columns NULL, so the columns stay nullable.
 */
class AddPreviousSchoolToStudents extends Migration
{
    public function up()
    {
        $this->forge->addColumn('students', [
            'previous_school' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'student_type',
            ],
            'previous_school_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 9,
                'null'       => true,
                'after'      => 'previous_school',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('students', [
            'previous_school',
            'previous_school_year',
        ]);
    }
}
