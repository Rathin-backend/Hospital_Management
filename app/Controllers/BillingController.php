<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use App\Models\AppointmentModel;
use App\Models\VisitRecordsModel;
use App\Models\HospitalsModel;
use App\Models\ServicesModel;
use App\Models\HospitalServicesModel;
use App\Models\BillingsModel;
use App\Models\BillingItemsModel;

class BillingController extends ResourceController
{
   private $appointmentModel, $userModel , $db , 
   $visitRecords , $hospitalModel , $servicesModel , $hospitalservicesModel , $billingsModel , $billingsitemModel;


   public function __construct()
{
    $this->db = db_connect();
    $this->appointmentModel = new AppointmentModel();
    $this->userModel = new UserModel();
    $this->visitRecords = new VisitRecordsModel();
    $this->hospitalModel = new HospitalsModel();
    $this->servicesModel = new ServicesModel();
    $this->hospitalservicesModel = new HospitalServicesModel();
    $this->billingsModel = new BillingsModel();
    $this->billingsitemModel = new BillingItemsModel();
}



public function listServiceswithPriceHospitalWise()
{
    try{
       
    $hospital_id = $this->request->hospital_id;


    $result = $this->hospitalservicesModel
                    ->select('hospital_services.*, s.service_name AS serviceName, s.service_type AS serviceType')
                    ->join('services AS s', 's.id = hospital_services.service_id')
                    ->where('hospital_services.hospital_id', $hospital_id)
                    ->whereIn('s.service_type', ['lab_test', 'other'])
                    ->findAll();


    return $this->respond([
        "status" => true,
        "Mssge" => "Successfully fetched the data",
        "data" => $result
    ]);

    }catch(\Exception $e)
    {
        return $this->respond([
            "status" => false,
            "Error" => $e->getMessage()
        ]);
    }
}



public function getConsultationFee()
{
    try
    {
       $hospital_id = $this->request->hospital_id;

       $consultationDetails = $this->hospitalservicesModel
                                ->select('hospital_services.service_id,hospital_services.unit_price,s.service_name')
                                ->join('services as s' , 's.id = hospital_services.service_id')
                                ->where("hospital_services.hospital_id" , $hospital_id)
                                ->where("s.service_type" , 'consultation')
                                ->find();

        if($consultationDetails)
        {
            return $this->respond([
                "status" => true,
                "Msgge" => "Successfully fetched the consultation price",
                "data" => $consultationDetails
            ]);
        }
    }
    catch(\Exception $e)
    {
        return $this->respond([
            "status" => false,
            "Error" => $e->getMessage()
        ]);
    }
}



public function generateBill()
{
    try{
       $userData = $this->request->userData;
       $userRole = $this->request->role;
       $hospital_id = $this->request->hospital_id;

       $validationRules = [
        "services" => [
            "rules" => "required"
         ]
        ];

        if(!$this->validate($validationRules))
        {
            return $this->respond([
                "status" => false,
                "Mssge" => $this->validator->getErrors()
            ]);
        }

        $appointmentId = $this->request->getVar("appointment_id");
        $services = $this->request->getVar('services'); //array od service ID's


        //validation - checking if appointment is completed
        $appointmentDetails = $this->appointmentModel->find($appointmentId);

    
        if($appointmentDetails['status'] !== 'completed')
        {
            return $this->respond([
                "status" => false,
                "Mssge" => "Can oly generate bill , for appointment whose status is completed"
            ]);
        }

        if (!is_array($services)) 
        {
            $services = [$services];
        }

        //$result = 0;
        
      
        
        $builder = $this->hospitalservicesModel
                ->select('hospital_services.service_id, hospital_services.unit_price, s.service_name')
                ->join('services as s', 's.id = hospital_services.service_id')
                ->where('hospital_services.hospital_id', $hospital_id)
                ->groupStart();


        if(!empty($services)) 
        {
            $builder->whereIn('hospital_services.service_id', $services);
        }

        // Always include consultation
        $builder->orWhere('s.service_type', 'consultation')
                ->groupEnd();

        $serviceDetails = $builder->findAll();
        
                

        if(empty($serviceDetails))
        {
            return $this->respond([
                "status" => false,
                "message" => "No valid services found for this hospital"
            ]);
        }


        $totalAmount = 0;
        $billingItemsData = [];

        foreach($serviceDetails as $srv)
        {
            $totalAmount += $srv['unit_price'];

            $billingItemsData[] = [
                'billing_id' => null,
                'service_id' => $srv['service_id'],
                'amount' => $srv['unit_price']
            ];
        }

       
        //Start DB transaction
        $this->db->transStart();
        
        $billData = [
           'appointment_id' => $appointmentId,
           'hospital_id' => $hospital_id,
           'unique_key' => $this->generateUniqueTxnId(),
           'total_amount' => $totalAmount,
        ];

        $billingId = $this->billingsModel->insert($billData);
        


        //Insert each service as billing item
        foreach($billingItemsData as &$item)
        {
            $item['billing_id'] = $billingId; 
        }
        
        

        $result = $this->billingsitemModel->insertBatch($billingItemsData);


        //commit transaction
        $this->db->transComplete();
        //  print_r("HI");
        //         die;
        if($this->db->transStatus() === false)
        {
            $this->db->transRollback();
            throw new \Exception("Transaction failed");
        }
        else{
            $this->db->transCommit();
        }

        return $this->respond([
            "status" => true,
            "message" => "Bill generated successfully",
            "bill_id" => $billingId,
            "total_amount" => $totalAmount,
            "items" => $serviceDetails
        ]);
    }
    catch(\Exception $e)
    {
       return $this->respond([
                "status" => false,
                "Error" => $e->getMessage()
            ]);
    }
}



public function makePayment()
{
    try{
      $validationRules = [
        "bill_id" => [
            "rules" => "required"
        ],
        "transaction_type" => [
            "rules" => "required"
        ]
        ];

        if(!$this->validate($validationRules))
        {
            return $this->respond([
                "status" => false,
                "mssge" => $this->validator->getErrors()
            ]);
        }


        $bill_id = $this->request->getVar("bill_id");
        $transaction_type = $this->request->getVar("transaction_type");

        $billDetails = $this->billingsModel->find($bill_id);
        $appointmentId = $billDetails['appointment_id'];
    

        $patientDetails = $this->appointmentModel
                            ->select('appointments.patient_id , u.name , u.email')
                            ->where('appointments.id' , $appointmentId)
                            ->join('users as u' , 'u.id = appointments.patient_id')
                            ->find();

       

        $result = $this->billingsModel->where("id" , $bill_id)
                                      ->set("transaction_type" , $transaction_type)
                                      ->set("status" , 'completed')
                                      ->update();



        // $email = \Config\Services::email();
        // $email->setFrom('hospital@example.com', 'Your Hospital');
        // $email->setTo($patientDetails['email']);
        // $email->setSubject('Payment Confirmation');
        // $email->setMessage(
        //     "Dear {$patientDetails['name']},<br><br>" .
        //     "Your payment for appointment #{$appointmentId} has been successfully processed.<br>" .
        //     "Payment Amount: <strong>₹{$billDetails['total_amount']}</strong><br>" .
        //     "Payment Mode: <strong>{$transaction_type}</strong><br><br>" .
        //     "Thank you for choosing our hospital.<br><br>" .
        //     "Regards,<br>Your Hospital Team"
        // );

        // if (!$email->send()) {
        //     log_message('error', $email->printDebugger());
        // }

        if($result)
        {
            return $this->respond([
                "status" => true,
                "Mssge" => "Payment done successfully,confirmation email sent to patient",
                "data" => $result
            ]);
        }

    }
    catch(\Exception $e)
    {
       return $this->respond([
        "status" => false,
        "Error" => $e->getMessage()
       ]);
    }
}


private function generateUniqueTxnId()
{
    do {
        $unique = "TXN" . mt_rand(10000000, 99999999);
        $exists = $this->billingsModel->where('unique_key', $unique)->first();
    } while ($exists);

    return $unique;
}



}
