<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class HospitalServicesMigration extends Migration
{
    public function up()
    {
       $this->forge->addField([
        "id" => [
            "type" => "INT",
            "unsigned" => true,
            "constraint" => 5,
            "auto_increment" => true
        ],
        "service_id" => [
            "type" => "INT",
            "unsigned" => true,
            "constraint" => 5,
            "null" => false
        ],
        "unit_price" => [
            "type" => "DECIMAL",
            "constraint" => "10,2",
            "null" => false
        ],
        "hospital_id" => [
            "type" => "INT",    
            "unsigned" => true,
            "constraint" => 5,
            "null" => false
        ],
        "created_at datetime default current_timestamp",
        "updated_at" => [
                "type" => "DATETIME",
                "after" => "created_at"
        ],
        "deleted_at" => [
            "type" => "DATETIME",
            "null" => true,
        ],
        "isDeleted" => [
            "type" => "TINYINT",
            "default"    => 0
        ]

        ]);

        $this->forge->addPrimaryKey("id");
        $this->forge->addForeignKey("service_id" , "services" , "id", "CASCADE", "CASCADE");
        $this->forge->addForeignKey("hospital_id" , "hospitals" , "id" , "CASCADE", "CASCADE");
        $this->forge->createTable("hospital_services");

        $this->db->query("
            ALTER TABLE `hospital_services`
            MODIFY `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ");
    }

    public function down()
    {
      $this->forge->dropTable("hospital_services");   
    }
}
