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
   $visitRecords , $hospitalModel , $servicesModel , $hospitalservicesModel , 
   $billingsModel , $billingsitemModel;


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


public function listPayments()
{
        $role = $this->request->role; // From JWTAuthFilter
        $userId = $this->request->id;
        $hospitalId = $this->request->hospital_id ?? null;

      
        $builder = $this->db->table('billings as b')
            ->select('
                b.id as billing_id,
                b.unique_key,
                b.total_amount,
                b.status,
                b.transaction_type,
                b.created_at,
                b.hospital_id,
                h.name as HospitalName,
                h.address as HospitalAddress,
                h.contact_no as Hospital_Contact_no,
                a.id as appointment_id,
                a.Appointment_date,
                a.Appointment_startTime,
                a.Appointment_endTime,
                a.doctor_id,
                a.patient_id,
                u.name as patient_name,
                u.email as patient_email
            ')
            ->join('appointments as a', 'a.id = b.appointment_id', 'left')
            ->join('users as u', 'u.id = a.patient_id', 'left')
            ->join('hospitals as h' , 'h.id = b.hospital_id')
            ->where('b.isDeleted', 0);

        
        if ($role === '2') {
            // 2 = Patient → fetch only his payments
            $builder->where('a.patient_id', $userId);
        } elseif ($role === '0') {
            // 0 = Admin → fetch only hospital payments
            if (!$hospitalId) {
                return $this->fail('Hospital ID missing in token for admin', 400);
            }
            $builder->where('b.hospital_id', $hospitalId);
        }

        
        $billings = $builder->orderBy('b.created_at', 'DESC')->get()->getResultArray();

        if (empty($billings)) {
            return $this->respond([
                'status' => true,
                'message' => 'No billing records found',
                'data' => []
            ]);
        }

       
        $billingIds = array_column($billings, 'billing_id');
        $billingItems = $this->db->table('billing_items as bi')
            ->select('bi.billing_id, s.service_name, bi.amount')
            ->join('services as s', 's.id = bi.service_id', 'left')
            ->whereIn('bi.billing_id', $billingIds)
            ->where('bi.isDeleted', 0)
            ->get()
            ->getResultArray();

        // Group billing items by billing_id
        $itemsByBilling = [];
        foreach ($billingItems as $item) {
            $itemsByBilling[$item['billing_id']][] = [
                'service_name' => $item['service_name'],
                'amount' => $item['amount']
            ];
        }

       
        $data = [];
        foreach ($billings as $bill) {
            $data[] = [
                'billing_id'       => $bill['billing_id'],
                'appointment_id'   => $bill['appointment_id'],
                'hospital_id'      => $bill['hospital_id'],
                'HospitalName'     => $bill['HospitalName'],
                'HospitalAddress'  => $bill['HospitalAddress'],
                'Hospital_Contact_no' => $bill['Hospital_Contact_no'],
                'unique_key'       => $bill['unique_key'],
                'total_amount'     => $bill['total_amount'],
                'status'           => $bill['status'],
                'transaction_type' => $bill['transaction_type'],
                'appointment_date' => $bill['Appointment_date'],
                'start_time'       => $bill['Appointment_startTime'],
                'end_time'         => $bill['Appointment_endTime'],
                'patient_name'     => $bill['patient_name'],
                'patient_email'    => $bill['patient_email'],
                'extra_services'   => $itemsByBilling[$bill['billing_id']] ?? [],
                'created_at'       => $bill['created_at']
            ];
        }

        return $this->respond([
            'status' => true,
            'message' => 'Billing records fetched successfully',
            'data' => $data
        ]);
}


public function cancelPayment()
{
    try{
        $validationRules = [
            "billing_id" => [
                "rules" => "required"
            ]
            ];

        if(!$this->validate($validationRules))
        {
            return $this->respond([
                "status" => "false",
                "Mssge" => $this->validator->getErrors()
            ]);
        }

        $billing_id = $this->request->getVar("billing_id");

        $billingDetails = $this->request->getVar("billingDetails");

        if($billingDetails['status'] != 'pending')
        {
            return $this->respond([
                "status" => false,
                "Mssge" => "Can oly cancel the payment whose status is pending" 
            ]);
        }

        $result = $this->billingsModel->where("id" , $billing_id)
                                      ->set('status' , 'cancelled')
                                      ->update();

        if($result)
        {
            return $this->respond([
                "status" => true,
                "Mssge" => "Cancelled the payment successfully",
                "data" => $result
            ]);
        }
    }catch(\Exception $e){
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
