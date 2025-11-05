<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateAppointments extends Migration
{
    public function up()
    {
        //  $this->db->query("ALTER TABLE visit_records DROP FOREIGN KEY visit_records_patient_id_foreign;"); 

        //  $this->db->query("ALTER TABLE visit_records DROP FOREIGN KEY visit_records_doctor_id_foreign;");

         $this->forge->dropColumn('visit_records', ['patient_id', 'doctor_id', 'reason']);
    }

    public function down()
    {
        //
    }
}
