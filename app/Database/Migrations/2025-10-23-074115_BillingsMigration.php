<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BillingsMigration extends Migration
{
    public function up()
    {
        $this->forge->addField([
            "id" => [
                "type" => "INT",
                "constraint" => 10,
                "unsigned" => true,
                "auto_increment" => true,
            ],
            "appointment_id" => [
                "type" => "INT",
                "constraint" => 10,
                "unsigned" => true,
                "null" => false,
            ],
            "hospital_id" => [
                "type" => "INT",
                "constraint" => 10,
                "unsigned" => true,
                "null" => false,
            ],
            "unique_key" => [
                "type" => "VARCHAR",
                "constraint" => 40,
                "null" => true,
            ],
            "total_amount" => [
                "type" => "DECIMAL" ,
                "constraint" => "10,2",
                "default" => 0.00
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
                "default" => 0
            ]
        ]);

        $this->forge->addPrimaryKey("id");
        $this->forge->addForeignKey("appointment_id" , "appointments" , "id" , "CASCADE", "CASCADE");
        $this->forge->addForeignKey("hospital_id" , "hospitals" , "id" , "CASCADE", "CASCADE");

        $this->forge->createTable("billings");

        $this->db->query("
            ALTER TABLE `billings`
            MODIFY `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ");
    }

    public function down()
    {
        $this->forge->dropTable('billings', true);
    }
}
