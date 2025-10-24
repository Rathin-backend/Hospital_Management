<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ServicesMigration extends Migration
{
    public function up()
    {
        $this->forge->addField([
            "id" => [
                "type" => "INT",
                "unsigned" => true,
                "constraint" => 5,
                "auto_increment" => true,
            ],
            "service_name" => [
                "type" => "VARCHAR",
                "constraint" => 30,
                "null" => false,
            ],
            "service_type" => [
                "type" => "ENUM",
                "constraint" => ['consultation' , 'lab_test', 'other']
            ],
            "description" => [
                "type" => "TEXT",
                "null" => true 
            ],
            "created_at datetime default current_timestamp",
            "updated_at" => [
                "type" => "DATETIME",
                "after" => "created_at"
            ],
            "isDeleted" => [
                "type" => "TINYINT",
                "default" => 0
            ]
        ]);

        $this->forge->addPrimaryKey("id");
        $this->forge->createTable("services");

        $this->db->query("
            ALTER TABLE `services`
            MODIFY `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ");


    }

    public function down()
    {
        $this->forge->dropTable("services",true);
    }
}
