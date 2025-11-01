<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DiagnosisMigration extends Migration
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
            "diagnosis_id" => [
                "type" => "INT",
                "unsigned" => true,
                "comment" => "FK to master_diagnoses.id"
            ],
            "notes" => [
                "type" => "TEXT",
                "null" => true,
                "comment" => "Doctor-specific notes"
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
        $this->forge->addForeignKey("diagnosis_id", "master_diagnoses", "id", "CASCADE", "CASCADE");

        $this->forge->createTable("diagnosis");
    }

    public function down()
    {
        $this->forge->dropTable("diagnosis");
    }
}
