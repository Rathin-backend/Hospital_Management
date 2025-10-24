<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BillingItemsMigration extends Migration
{
    public function up()
    {
        $this->forge->addField([
            "id" => [
                "type" => "INT",
                "unsigned" => true,
                "null" => false,
                "auto_increment" => true
            ],
            "billing_id" => [
                "type" => "INT",
                "unsigned" => true,
                "constraint" => 5,
            ],
            "service_id" => [
                "type" => "INT",
                "unsigned" => true,
                "null" => false,
            ],
            "amount" => [
                "type" => "DECIMAL",
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
                "default"    => 0
            ]
        ]);
      
        
        $this->forge->addPrimaryKey("id");
        $this->forge->addForeignKey("billing_id" , "billings" , "id" , "CASCADE", "CASCADE");
        $this->forge->addForeignKey("service_id" , "hospital_services" , "id" , "CASCADE", "CASCADE");
        $this->forge->createTable("billing_items");
        $this->db->query("
            ALTER TABLE `billing_items`
            MODIFY `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ");
    }

    public function down()
    {
        $this->forge->dropTable("billing_items");
    }
}
