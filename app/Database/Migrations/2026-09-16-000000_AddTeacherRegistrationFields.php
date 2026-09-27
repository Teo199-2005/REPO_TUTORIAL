<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tracks teachers who signed up through the public teacher registration link.
 *
 * Records created by an administrator (or seeded) default to "approved" so
 * nothing changes for the existing teacher population.
 */
class AddTeacherRegistrationFields extends Migration
{
    public function up()
    {
        $fields = [
            'registration_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'approved',
                'after'      => 'employment_status',
            ],
            'registration_notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'registration_status',
            ],
            'registration_reviewed_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'registration_notes',
            ],
            'registration_reviewed_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'registration_reviewed_at',
            ],
        ];

        $this->forge->addColumn('teachers', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('teachers', [
            'registration_status',
            'registration_notes',
            'registration_reviewed_at',
            'registration_reviewed_by',
        ]);
    }
}