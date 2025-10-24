<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ChangeDefaultValueOfTransactionTypeMigration extends Migration
{
    public function up()
    {
        $this->db->query("
            ALTER TABLE `billings` 
            MODIFY `transaction_type` ENUM('UPI','Cash','Card','NetBanking') 
            NULL DEFAULT NULL
        ");
    }

    public function down()
    {
        $this->db->query("
            ALTER TABLE `billings` 
            MODIFY `transaction_type` ENUM('UPI','Cash','Card','NetBanking') 
            NOT NULL DEFAULT 'UPI'
        ");
    }
}
