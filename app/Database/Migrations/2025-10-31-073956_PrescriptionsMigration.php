<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PrescriptionsMigration extends Migration
{
    public function up()
    {
        $this->forge->addField([
            "id" => [
                "type" => "INT",
                "unsigned" => true,
                "auto_increment" => true
            ],
            "visit_record_id" => [
                "type" => "INT",
                "unsigned" => true,
                "comment" => "FK to visit_records.id"
            ],
            "medicine_name" => [
                "type" => "VARCHAR",
                "constraint" => 100,
                "comment" => "Name of medicine prescribed"
            ],
            "dosage" => [
                "type" => "VARCHAR",
                "constraint" => 50,
                "comment" => "Mg or ml, e.g., 500mg"
            ],
            "frequency" => [
                "type" => "VARCHAR",
                "constraint" => 50,
                "comment" => "How many times a day"
            ],
            "duration" => [
                "type" => "VARCHAR",
                "constraint" => 30,
                "comment" => "e.g., 3 days / 1 week"
            ],
            "instructions" => [
                "type" => "TEXT",
                "null" => true,
                "comment" => "Before food / After food / Special note"
            ],
            "created_at DATETIME DEFAULT CURRENT_TIMESTAMP",
            "updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP",
            "created_by" => [
                "type" => "INT",
                "unsigned" => true,
                "null" => true,
            ],
            "deleted_at" => [
                "type" => "DATETIME",
                "null" => true,
            ],
            "deleted_by" => [
                "type" => "INT",
                "unsigned" => true,
                "null" => true,
            ],
            "isDeleted" => [
                "type" => "TINYINT",
                "unsigned" => true,
                "default" => 0
            ],
        ]);

        $this->forge->addPrimaryKey("id");
        $this->forge->addForeignKey("visit_record_id", "visit_records", "id", "CASCADE", "CASCADE");

        $this->forge->createTable("prescriptions");
    }

    

    public function down()
    {
        $this->forge->dropTable("prescriptions");
    }
}
