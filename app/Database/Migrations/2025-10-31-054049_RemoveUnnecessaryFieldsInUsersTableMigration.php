<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveUnnecessaryFieldsInUsersTableMigration extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE `users` DROP FOREIGN KEY `users_ibfk_1`;');
        $fields = [
            'role',
            'hospital_id',
            'problem'
        ];

        $this->forge->dropColumn('users', $fields);
    }

    public function down()
    {
        $this->forge->addColumn('users', [
            'hospital_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2', '3'],
                'default'    => '0',
                'comment'    => '0 = Admin, 1 = Doctor, 2 = Patient, 3 = SuperAdmin'
            ],
            'problem' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
        ]);
    }
}
