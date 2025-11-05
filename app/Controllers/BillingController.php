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
use App\Models\UserHospitalMappingModel;
use App\Models\ComplaintsModel;
use App\Models\MasterDiagnosesModel;
use App\Models\DiagnosisModel;
use App\Models\PrescriptionsModel;


class BillingController extends ResourceController
{
   private $appointmentModel, $userModel , $db , 
   $visitRecords , $hospitalModel , $servicesModel , $hospitalservicesModel , 
   $billingsModel , $billingsitemModel , $userhospitalMapping , $complaintsModel , $masterDaignosesModel , $diagnosisModel , $prescriptionModel;


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
    $this->userhospitalMapping = new UserHospitalMappingModel();
    $this->complaintsModel = new ComplaintsModel();
    $this->diagnosisModel = new DiagnosisModel();
    $this->masterDaignosesModel = new MasterDiagnosesModel();   
    $this->prescriptionModel = new PrescriptionsModel();
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
    try {
        $validationRules = [
            "bill_id" => "required",
            "transaction_type" => "required"
        ];

        if (!$this->validate($validationRules)) {
            return $this->respond([
                "status" => false,
                "mssge" => $this->validator->getErrors()
            ]);
        }

        $bill_id = $this->request->getVar("bill_id");
        $transaction_type = $this->request->getVar("transaction_type");
        $userId = $this->request->id;

        $billDetails = $this->billingsModel->find($bill_id);
        if (!$billDetails) {
            return $this->respond([
                "status" => false,
                "mssge" => "Invalid Bill ID"
            ]);
        }

        // if already paid prevent double payment
        if ($billDetails['status'] === "completed") {
            return $this->respond([
                "status" => false,
                "mssge" => "Bill already paid"
            ]);
        }

        $appointmentId = $billDetails['appointment_id'];

        $patientDetails = $this->appointmentModel
                    ->select('appointments.patient_id, u.name, u.email')
                    ->join('users as u', 'u.id = appointments.patient_id')
                    ->where('appointments.id', $appointmentId)
                    ->first(); // ✅ FIX

        $result = $this->billingsModel
            ->where("id", $bill_id)
            ->set([
                "transaction_type" => $transaction_type,
                "status" => 'completed',
                "updated_by" => $userId // ✅ audit field
            ])
            ->update();

        if ($result) {
            return $this->respond([
                "status" => true,
                "mssge"  => "Payment successful",
                "bill_id" => $bill_id,
                "amount" => $billDetails['total_amount'],
                "transaction_type" => $transaction_type
            ]);
        }

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "Error" => $e->getMessage()
        ]);
    }
}


public function listPayments()
{
    try {
        $role       = $this->request->role;
        $userId     = $this->request->id;
        $hospitalId = $this->request->hospital_id ?? null;

        $builder = $this->db->table('billings as b')
            ->select('
                b.id as billing_id,
                b.unique_key,
                b.total_amount,
                b.status,
                b.transaction_type,
                b.created_at,
                a.hospital_id,
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
            ->join('hospitals as h', 'h.id = a.hospital_id', 'left')
            ->where('b.isDeleted', 0);

       
        if ($role == '2') {
            $builder->where('a.patient_id', $userId);
        }

        
        elseif ($role == '1') {
            if (!$hospitalId) {
                return $this->fail('Hospital ID missing for doctor', 400);
            }
            $builder->where('a.hospital_id', $hospitalId);
            $builder->where('a.doctor_id', $userId);
        }

        
        elseif ($role == '0') {
            if (!$hospitalId) {
                return $this->fail('Hospital ID missing for admin', 400);
            }
            $builder->where('a.hospital_id', $hospitalId);
        }

       
        $billings = $builder->orderBy('b.created_at', 'DESC')->get()->getResultArray();

        if (empty($billings)) {
            return $this->respond([
                'status'  => true,
                'message' => 'No billing records found',
                'data'    => []
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

        // Group by bill
        $itemsByBilling = [];
        foreach ($billingItems as $item) {
            $itemsByBilling[$item['billing_id']][] = [
                'service_name' => $item['service_name'],
                'amount'       => $item['amount']
            ];
        }

        $data = [];
        foreach ($billings as $bill) {
            $data[] = [
                'billing_id'          => $bill['billing_id'],
                'appointment_id'      => $bill['appointment_id'],
                'hospital_id'         => $bill['hospital_id'],
                'HospitalName'        => $bill['HospitalName'],
                'HospitalAddress'     => $bill['HospitalAddress'],
                'Hospital_Contact_no' => $bill['Hospital_Contact_no'],
                'unique_key'          => $bill['unique_key'],
                'total_amount'        => $bill['total_amount'],
                'status'              => $bill['status'],
                'transaction_type'    => $bill['transaction_type'],
                'appointment_date'    => $bill['Appointment_date'],
                'start_time'          => $bill['Appointment_startTime'],
                'end_time'            => $bill['Appointment_endTime'],
                'patient_name'        => $bill['patient_name'],
                'patient_email'       => $bill['patient_email'],
                'extra_services'      => $itemsByBilling[$bill['billing_id']] ?? [],
                'created_at'          => $bill['created_at']
            ];
        }

        return $this->respond([
            'status'  => true,
            'message' => 'Billing records fetched successfully',
            'data'    => $data
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "Error"  => $e->getMessage()
        ]);
    }
}




public function cancelPayment()
{
    try {
        $role       = (int)($this->request->role ?? -1);     // 0=Admin, 1=Doctor, 2=Patient, 3=SuperAdmin
        $userId     = (int)$this->request->id;
        $activeHosp = $this->request->hospital_id ?? null;

        if (!$this->validate(["billing_id" => "required|integer"])) {
            return $this->respond([
                "status" => false,
                "Mssge"  => $this->validator->getErrors()
            ]);
        }

        $billingId = (int)$this->request->getVar("billing_id");

        // Pull bill + its appointment context (where hospital_id actually lives)
        $billing = $this->billingsModel
            ->select("billings.id, billings.status, billings.appointment_id, a.hospital_id, a.doctor_id")
            ->join("appointments as a", "a.id = billings.appointment_id", "left")
            ->where("billings.id", $billingId)
            ->where("billings.isDeleted", 0)
            ->first();

        if (!$billing) {
            return $this->respond([
                "status" => false,
                "Mssge"  => "Invalid billing ID"
            ], 404);
        }

        if ($billing['status'] !== 'pending') {
            return $this->respond([
                "status" => false,
                "Mssge"  => "Only pending payments can be cancelled"
            ], 400);
        }

        // Role-based auth:
        // Patients cannot cancel
        if ($role === 2) {
            return $this->respond([
                "status" => false,
                "Mssge"  => "Patients cannot cancel payments"
            ], 403);
        }

        // SuperAdmin can cancel anything
        if ($role === 3) {
            // allowed
        }
        // Doctor: must be the assigned doctor AND in the active hospital
        else if ($role === 1) {
            if ((int)$billing['doctor_id'] !== $userId) {
                return $this->respond([
                    "status" => false,
                    "Mssge"  => "Unauthorized: only the assigned doctor can cancel this payment"
                ], 403);
            }
            if (!$activeHosp || (int)$billing['hospital_id'] !== (int)$activeHosp) {
                return $this->respond([
                    "status" => false,
                    "Mssge"  => "Unauthorized: active hospital mismatch"
                ], 403);
            }
        }
        // Admin: must match active hospital
        else if ($role === 0) {
            if (!$activeHosp || (int)$billing['hospital_id'] !== (int)$activeHosp) {
                return $this->respond([
                    "status" => false,
                    "Mssge"  => "Unauthorized: admin can cancel only bills of the active hospital"
                ], 403);
            }
        }
        // Any other role → deny
        else {
            return $this->respond([
                "status" => false,
                "Mssge"  => "Access denied"
            ], 403);
        }

        // Perform cancel
        $this->billingsModel
            ->set("status", "cancelled")
            ->set("updated_by", $userId)
            ->where("id", $billingId)
            ->update();

        return $this->respond([
            "status" => true,
            "Mssge"  => "Payment cancelled successfully"
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "Error"  => $e->getMessage()
        ], 500);
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
