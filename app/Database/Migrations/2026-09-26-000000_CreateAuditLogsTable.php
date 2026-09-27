<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Append-only activity/audit log.
 *
 * One row per recorded event: an explicit domain event (login, password
 * change, role change, CRUD on a record...) or the generic request trail
 * written by App\Filters\AuditTrailFilter for every state-changing request.
 *
 * The table is never updated or deleted from by the application; the only
 * delete path is the explicit `php spark audit:prune` retention command,
 * which removes the oldest prefix (see App\Models\AuditLogModel::purgeOlderThan).
 *
 * Status/category are VARCHAR instead of MySQL ENUM so the same migration
 * also runs on the SQLite in-memory connection used by the test suite.
 */
class CreateAuditLogsTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('audit_logs')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // Correlation id generated once per HTTP request by AuditTrailFilter.
            'request_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
            // Actor. user_id is NULL for anonymous events (failed logins).
            'actor_user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'actor_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ],
            'actor_role' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            // Machine-readable event name, e.g. auth.login, student.updated.
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            // auth | account | role | data | settings | system
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'data',
            ],
            // success | failure | blocked
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'default'    => 'success',
            ],
            'resource_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'resource_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'http_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
            ],
            'route' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ],
            // Whitelisted before/after snapshots (sensitive fields redacted)
            // and any extra structured context. JSON-encoded.
            'before_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'after_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'metadata_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            // Tamper-evidence: hash = HMAC-SHA256(canonical row + prev_hash).
            'prev_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        // Indexes mirror the admin viewer's filters and default sort.
        $this->forge->addKey('created_at');
        $this->forge->addKey(['actor_user_id', 'created_at']);
        $this->forge->addKey(['action', 'created_at']);
        $this->forge->addKey(['resource_type', 'resource_id']);
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->addKey(['category', 'created_at']);
        $this->forge->createTable('audit_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_logs', true);
    }
}
