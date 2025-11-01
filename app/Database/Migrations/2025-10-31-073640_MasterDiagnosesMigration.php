<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MasterDiagnosesMigration extends Migration
{
    public function up()
    {
        $this->forge->addField([
            "id" => [
                "type" => "INT",
                "unsigned" => true,
                "auto_increment" => true
            ],
            "name" => [
                "type" => "VARCHAR",
                "constraint" => 100,
                "comment" => "Standardized diagnosis name"
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
        $this->forge->createTable("master_diagnoses");
    }

    public function down()
    {
        $this->forge->dropTable("master_diagnoses");
    }
}
