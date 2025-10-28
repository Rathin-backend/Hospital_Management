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
        try {
            CLI::write('Starting appointment check...', 'cyan');
            
            $appointmentModel = new AppointmentModel();

            $thresholdTime = date('Y-m-d H:i:s', strtotime('-24 hours'));

            CLI::write("Checking for pending appointments older than: {$thresholdTime}", 'white');

            //Find pending appointments older than 24 hours
            $pendingAppointments = $appointmentModel
                   ->where('status', 'pending')
                   ->where('created_at <', $thresholdTime)
                   ->findAll();

            if(empty($pendingAppointments))
            {
                CLI::write('No pending appointments found older than 24 hours', 'green');
                return;
            }

            $ids = array_column($pendingAppointments, 'id');

            CLI::write("Found " . count($ids) . " pending appointment(s) to cancel", 'yellow');

            $result = $appointmentModel
                ->whereIn('id', $ids)
                ->set(['status' => 'cancelled'])
                ->update();

            if($result)
            {
                CLI::write("✓ Successfully cancelled " . count($ids) . " pending appointment(s)", 'green');
            } else {
                CLI::write("✗ Failed to update appointments", 'red');
            }

            CLI::write('Appointment check completed', 'cyan');
        } catch (\Exception $e) {
            CLI::write('Error: ' . $e->getMessage(), 'red');
        }
    }
}
