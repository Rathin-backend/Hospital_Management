<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Database;

class InitSetupSeeder extends Seeder
{
    //private $db;
    public function run()
    {
        $db = Database::connect();

        $db->table('users')->insert([
            'name' => 'Superadmin200',
            'email' => 'superadmin200@gmail.com',
            'password' => password_hash('123456', PASSWORD_BCRYPT),
            'phone_no' => '9999999999',
            'gender' => 'Male',
        ]);
        $superId = $db->insertID();

        $db->table('user_hospital_mapping')->insert([
            'user_id' => $superId,
            'role' => '3',  // SUPERADMIN
            'created_by' => $superId
        ]);
    }
}
