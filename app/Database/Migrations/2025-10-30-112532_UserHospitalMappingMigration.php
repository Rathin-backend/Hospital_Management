<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UserHospitalMappingMigration extends Migration
{
    public function up()
    {
        $this->forge->addField([
            "id" => [
                "type" => "INT",
                "unsigned" => true,
                "auto_increment" => true,
            ],
            "user_id" => [
                "type" => "INT",
                "unsigned" => true,
                "constraint" => 5
            ],
            "hospital_id" => [
                "type" => "INT",
                "unsigned" => true,
                "constraint" => 5,
                
            ],
            "role" => [ 
                "type" => "ENUM",
                "constraint" => [ "0" , "1" , "2" , "3"],  // 0 - Admin 
                "null" => false                            // 1 - doctor
            ],                                             // 2 - patient
                                                           // 3 - superAdmin
            "created_at datetime default current_timestamp",
            "updated_at" => [
                "type" => "DATETIME",
                "null" => true,
                "on update" => "CURRENT_TIMESTAMP",
            ],
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
                "default" => 0,
                "comment" => "0: Active, 1: Deleted",
            ],
        ]);

        $this->forge->addPrimaryKey("id");
        $this->forge->addForeignKey("user_id", "users", "id", "CASCADE", "CASCADE");
        $this->forge->addForeignKey("hospital_id", "hospitals", "id", "CASCADE", "CASCADE");

        $this->forge->createTable("user_hospital_mapping", true);
    }

    public function down()
    {
        $this->forge->dropTable("user_hospital_mapping", true);
    }
}
