<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ComplaintsMigration extends Migration
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
                "comment" => "Foreign key to visit_records.id"
            ],
            "complaint" => [
                "type" => "VARCHAR",
                "constraint" => 255,
                "comment" => "Main complaint from patient"
            ],
            "description" => [
                "type" => "TEXT",
                "null" => true,
                "comment" => "Additional symptoms or notes"
            ],
            "severity" => [
                "type" => "ENUM",
                "constraint" => ['low','medium','high'],
                "default" => "low"
            ],
            "days" => [
                "type" => "INT",
                "null" => true,
                "comment" => "Duration of symptoms in days"
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

        $this->forge->createTable("complaints");
    }

    public function down()
    {
        $this->forge->dropTable("complaints");
    }
}
