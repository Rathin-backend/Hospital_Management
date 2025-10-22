<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\AppointmentModel;

class CheckAppointments extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Appointments';
    protected $name = 'appointments:check';
    protected $description = 'Checks and updates pending appointments older than 24 hours to expired';


    public function run(array $params)
    {
        $appointmentModel = new AppointmentModel();

        $thresholdTime = date('Y-m-d H:i:s' , strtotime('-24 hours'));


        //Find pending appointments older than 24 hours
        $peningAppointments = $appointmentModel
               ->where('status' , 'pending')
               ->where('created_at <' , $thresholdTime)
               ->findAll();

        if(empty($peningAppointments))
        {
            CLI::write('No pending appointments found older than 24 hours','green');
            return;
        }

        $ids = array_column($peningAppointments, 'id');

        $appointmentModel
            ->whereIn('id' , $ids)
            ->set(['status' => 'cancelled'])
            ->update();

        CLI::write(" " . count($ids) . " pending appointments have been marked as cancelled" , 'yellow');
    }
}
