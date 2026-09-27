<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds users.mascot_hidden - whether this person has sent Tappy away.
 *
 * The dismissal used to live in localStorage, which is scoped to the browser
 * rather than to the account. That breaks the moment someone logs out: the flag
 * either follows them to the next account on a shared computer (the next user
 * inherits a mascot they never hid) or evaporates when they sign back in on
 * another machine. Storing it per user is what makes "hide Tappy" mean the same
 * thing on the next login as it did when the button was pressed.
 *
 * Defaults to 0, so Tappy is visible until somebody chooses otherwise.
 */
class AddMascotHiddenToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'mascot_hidden' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'status',
                'comment'    => '1 = this user has hidden Tappy, 0 = Tappy is shown',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'mascot_hidden');
    }
}