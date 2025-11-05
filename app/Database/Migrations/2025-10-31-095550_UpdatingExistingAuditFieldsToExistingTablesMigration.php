<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdatingExistingAuditFieldsToExistingTablesMigration extends Migration
{
    public function up()
    {
        $auditFields = [
            'created_by' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
            'updated_by' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
            'deleted_by' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ];

        /**
         * Users table
         */
        $this->forge->addColumn('users', $auditFields);

        /**
         * Appointments table
         */
        $appointmentFields = $auditFields;
        $appointmentFields['isDeleted'] = [
            'type'       => 'BOOLEAN',
            'default'    => false,
        ];
        $this->forge->addColumn('appointments', $appointmentFields);

        /**
         * Visit records table — only created_by & updated_by
         */
        $this->forge->addColumn('visit_records', [
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'updated_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
        ]);

        /**
         * Hospitals table
         */
        $this->forge->addColumn('hospitals', $auditFields);

        /**
         * Services table
         */
        $this->forge->addColumn('services', $auditFields);

        /**
         * Hospital Services mapping table — no deleted_at
         */
        $hospitalServiceFields = [
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'updated_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'deleted_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
        ];
        $this->forge->addColumn('hospital_services', $hospitalServiceFields);

        /**
         * Billings table
         */
        $this->forge->addColumn('billings', [
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'updated_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'deleted_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
        ]);

        /**
         * Billing Items table
         */
        $this->forge->addColumn('billing_items', [
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'updated_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'deleted_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
        ]);
    }

    public function down()
    {
        $fieldsToDrop = ['created_by', 'updated_by', 'deleted_by', 'deleted_at', 'isDeleted'];

        foreach ($fieldsToDrop as $field) {
            @ $this->forge->dropColumn('users', $field);
            @ $this->forge->dropColumn('appointments', $field);
            @ $this->forge->dropColumn('visit_records', $field);
            @ $this->forge->dropColumn('hospitals', $field);
            @ $this->forge->dropColumn('services', $field);
            @ $this->forge->dropColumn('hospital_services', $field);
            @ $this->forge->dropColumn('billings', $field);
            @ $this->forge->dropColumn('billing_items', $field);
        }
    
    }
}
