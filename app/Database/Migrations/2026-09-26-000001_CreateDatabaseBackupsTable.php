<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Registry of the database dumps stored by App\Libraries\DatabaseBackupService.
 *
 * One row per successfully created dump. Rows are never written for failed
 * dumps (a failure is recorded in the activity log and the partial file is
 * deleted), so every row points at a complete, checksummed dump. The dump
 * directory itself stays outside the web root (writable/backups by default).
 *
 * A sidecar `<filename>.meta.json` file next to every dump carries the same
 * checksum, which is what makes a restore verifiable even after the database
 * (and therefore this table) has been lost.
 *
 * Column types are plain VARCHAR/INT/TEXT so the migration also runs on the
 * SQLite in-memory connection used by the test suite.
 */
class CreateDatabaseBackupsTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('database_backups')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // db-<kind>-<Ymd>-<His>-<8 hex>.sql — validated before every file operation.
            'filename' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            // auto | manual | pre (pre = automatic safety backup taken before a restore)
            'kind' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'default'    => 'manual',
            ],
            'size_bytes' => [
                'type'       => 'BIGINT',
                'unsigned'   => true,
                'default'    => 0,
            ],
            'tables_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'checksum_sha256' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            // Which mysqldump option profile produced the file (full|compatible|minimal|plain).
            'dump_profile' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => true,
            ],
            'server_version' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_by_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ],
            'verified_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            // ok | checksum_mismatch | file_missing | invalid_file
            'verify_result' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => true,
            ],
            'restored_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'restore_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('filename');
        $this->forge->addKey('created_at');
        $this->forge->addKey(['kind', 'created_at']);
        $this->forge->createTable('database_backups', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('database_backups', true);
    }
}
