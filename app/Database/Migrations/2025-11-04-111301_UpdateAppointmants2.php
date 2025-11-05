<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateAppointmants2 extends Migration
{
    public function up()
    {
        $this->forge->addColumn("appointments" , [
            "cancel_reason" => [
                "type" => "TEXT",
                "null" => true,
                "default"=> null,
            ]
            ]);
    }

    public function down()
    {
        $this->forge->dropColumn('appointments', 'cancel_reason');
    }
}
