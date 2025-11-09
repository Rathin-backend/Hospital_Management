<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdatingAppointmetsForAppointmentsLogTableMigration extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE appointments DROP FOREIGN KEY fk_rescheduled_from");
        $this->db->query("ALTER TABLE appointments DROP FOREIGN KEY parent_id");
        $this->forge->dropColumn("appointments" , [
            "rescheduled_from",
            "reschedule_reason",
            "parent_id",
            "cancel_reason"
        ]);
    }

    public function down()
    {
        //
    }
}
