<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIdPhotoPathToStudents extends Migration
{
    public function up()
    {
        $this->forge->addColumn('students', [
            'id_photo_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'photo_path',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('students', ['id_photo_path']);
    }
}
