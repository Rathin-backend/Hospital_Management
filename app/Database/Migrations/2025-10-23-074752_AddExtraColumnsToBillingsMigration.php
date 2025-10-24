<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExtraColumnsToBillingsMigration extends Migration
{
    public function up()
    {
         $fields = [
            'status' => [
                'type' => "ENUM('pending','completed','cancelled')",
                'default' => 'pending',
                'null' => false,
                'after' => 'total_amount'
            ],
            'transaction_type' => [
                'type' => "ENUM('UPI','Cash','Card','NetBanking')",
                'default' => 'UPI',
                'null' => false,
                'after' => 'status'
            ]
        ];

        $this->forge->addColumn('billings', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('billings', 'status');
        $this->forge->dropColumn('billings', 'transaction_type');
    }
}
