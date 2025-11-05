<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateUserHospitalMappingNullable extends Migration
{
    public function up()
    {
        // First drop foreign key if exists
        $this->db->query("ALTER TABLE `user_hospital_mapping` DROP FOREIGN KEY `user_hospital_mapping_hospital_id_foreign`;");

        // Modify column to allow NULL
        $this->forge->modifyColumn('user_hospital_mapping', [
            'hospital_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
        ]);
    }

    public function down()
    {
        // Revert back to NOT NULL (if needed)
        $this->forge->modifyColumn('user_hospital_mapping', [
            'hospital_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => false,
            ],
        ]);
    }
}
