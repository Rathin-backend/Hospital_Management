<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveHospitalIdFromVisitRecordsMigration extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE `visit_records` DROP FOREIGN KEY `visit_records_ibfk_1`');
        $this->forge->dropColumn("visit_records" ,"hospital_id");
    }

    public function down()
    {
        $this->forge->addColumn("visit_records" , [
            "hospital_id" => [
                "type" => "INT",
                "unsigned" => true,
            ]
            ]);
    }
}
