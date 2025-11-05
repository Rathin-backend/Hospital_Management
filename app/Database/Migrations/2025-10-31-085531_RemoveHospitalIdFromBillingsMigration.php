<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveHospitalIdFromBillingsMigration extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE `billings` DROP FOREIGN KEY `billings_hospital_id_foreign`;');
        $this->forge->dropColumn('billings' , "hospital_id" );
    }

    public function down()
    {
        $this->forge->addColumn('billings' , [
            "hospital_id" => [
                "type" => "INT",
                "constraint" => 10,
                "unsigned" => true,
                "null" => false,
            ]
            ]);
    }
}
