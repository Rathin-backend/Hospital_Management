<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatingAppointmentsLogTableMigration extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'auto_increment' => true,
            ],

            'appointment_id' => [
                'type'       => 'INT',
                'constraint' => 5,
                'unsigned'   => true,
            ],

            'action' => [
                'type' => "ENUM('booked','pending','confirmed','rescheduled','cancelled','completed')",
                'default' => 'pending'
            ],

            'old_date'       => ['type'=>'DATE','null'=>true],
            'old_start_time' => ['type'=>'TIME','null'=>true],
            'new_date'       => ['type'=>'DATE','null'=>true],
            'new_start_time' => ['type'=>'TIME','null'=>true],
            'reason'         => ['type'=>'TEXT','null'=>true],

            'action_by' => [
                'type'       => 'INT',
                'constraint' => 5,
                'unsigned'   => true,
            ],

            
            'created_at datetime default current_timestamp',
            'updated_at datetime null',
            'deleted_at datetime null',

            'created_by' => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>true],
            'updated_by' => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>true],
            'deleted_by' => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>true],

            'isDeleted' => ['type'=>'TINYINT','default'=>0],
        ]);

        
        $this->forge->addKey('id', true);

        $this->forge->addForeignKey('appointment_id','appointments','id','CASCADE','CASCADE');

        $this->forge->createTable('appointments_log', true);
    }

    public function down()
    {
        $this->forge->dropTable('appointments_log', true);
    }
}
