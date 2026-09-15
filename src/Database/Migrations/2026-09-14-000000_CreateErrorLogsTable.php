<?php

namespace Ephraitech\ErrorLogger\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateErrorLogsTable extends Migration
{
    public function up()
    {
        // Pulled from config at *run time* rather than hardcoded, so a host
        // app that already overrode ErrorLogger::$table gets the right
        // table name without us touching this file.
        $table = config('ErrorLogger')->table;

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'category' => [
                'type'       => 'ENUM',
                'constraint' => ['application', 'framework', 'database', 'php'],
                'null'       => false,
            ],
            'severity' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => 'error',
            ],
            'message' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'exception_class' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'file' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'line' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'trace' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'context' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'method' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'user_identifier' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'environment' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'resolved' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'resolved_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('category');
        $this->forge->addKey('severity');
        $this->forge->addKey('resolved');
        $this->forge->addKey('created_at');

        $this->forge->createTable($table, true);
    }

    public function down()
    {
        $table = config('ErrorLogger')->table;

        $this->forge->dropTable($table, true);
    }
}
