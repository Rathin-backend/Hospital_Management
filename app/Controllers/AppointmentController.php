<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use App\Models\AppointmentModel;
use App\Models\VisitRecordsModel; 
use App\Models\UserHospitalMappingModel;
use App\Models\HospitalsModel;
use App\Models\ComplaintsModel;
use App\Models\MasterDiagnosesModel;
use App\Models\DiagnosisModel;
use App\Models\PrescriptionsModel;
use App\Models\AppointmentsLogModel;

use PHPUnit\TextUI\XmlConfiguration\Validator;
helper('time_helper');
helper('time_helper2');
helper('validateFutureAppointment_helper');


class AppointmentController extends ResourceController
{
   
   private $appointmentModel, $userModel , $db , $visitRecords , $hospitalModel , 
           $userhospitalMapping , $complaintsModel , $masterDaignosesModel , 
           $diagnosisModel , $prescriptionModel , $appointmentslogModel;

  //  private function convertToDatabaseTime($time12Hour)
  // {   
  //    $dateTime = DateTime::createFromFormat('h:i A', $time12Hour);
      
  //    return $dateTime->format('H:i:s');
  // }

public function __construct()
{
    $this->db = db_connect();
    $this->appointmentModel = new AppointmentModel();
    $this->userModel = new UserModel();
    $this->visitRecords = new VisitRecordsModel();
    $this->hospitalModel = new HospitalsModel();
    $this->userhospitalMapping = new UserHospitalMappingModel();
    $this->complaintsModel = new ComplaintsModel();
    $this->diagnosisModel = new DiagnosisModel();
    $this->masterDaignosesModel = new MasterDiagnosesModel();   
    $this->prescriptionModel = new PrescriptionsModel();
    $this->appointmentslogModel = new AppointmentsLogModel();
}




public function ListAppointments()
{
    try {
        $userRole = $this->request->role;
        $userId   = $this->request->id;
        $hospitalIdFromToken = $this->request->hospital_id ?? null;

        // Filters
        $appointmentId = $this->request->getVar("appointmentId");
        $doctorName    = $this->request->getVar("doctorName");
        $patientName   = $this->request->getVar("patientName");
        $doctorId      = $this->request->getVar("doctorId");
        $patientId     = $this->request->getVar("patientId");
        $status        = $this->request->getVar("status");
        $date          = $this->request->getVar("date");
        $dateFilter    = $this->request->getVar("dateFilter");
        $filterHospitalId = $this->request->getVar("hospital_id"); // only for SA

        $builder = $this->appointmentModel
            ->select("appointments.*, 
                      doctor.name as doctor_name, 
                      patient.name as patient_name, 
                      hospital.name as hospital_name")
            ->join("users as doctor", "doctor.id = appointments.doctor_id", "left")
            ->join("users as patient", "patient.id = appointments.patient_id", "left")
            ->join("hospitals as hospital", "hospital.id = appointments.hospital_id", "left");

        // ROLE BASED FILTERS 

        if ($userRole === "2") { 
            // PATIENT → Their appointments across hospitals
            $builder->groupStart()
                ->where("appointments.patient_id", $userId)
                
            ->groupEnd();
        } 
        elseif ($userRole === "1") { 
            // DOCTOR → Only their appointments in selected hospital
            if (!$hospitalIdFromToken) {
                return $this->respond(["status" => false, "message" => "Select hospital first"], 400);
            }

            $builder->groupStart()
                ->where("appointments.doctor_id", $userId)
            ->groupEnd()
            ->where("appointments.hospital_id", $hospitalIdFromToken);
        } 
        elseif ($userRole === "0") { 
            // ADMIN → All appointments for hospital
            if (!$hospitalIdFromToken) {
                return $this->respond(["status" => false, "message" => "Select hospital first"], 400);
            }

            $builder->where("appointments.hospital_id", $hospitalIdFromToken);
        } 
        elseif ($userRole === "3") {  
            // SUPERADMIN → All hospitals, BUT can filter
            if (!empty($filterHospitalId)) {
                $builder->where("appointments.hospital_id", $filterHospitalId);
            }
        }

        // FILTERS 

        if ($appointmentId) {
            $builder->groupStart()
                ->where("appointments.id", $appointmentId)
               
            ->groupEnd();
        }

        if ($doctorName)   $builder->where("doctor.name", $doctorName);
        if ($doctorId)     $builder->where("appointments.doctor_id", $doctorId);
        if ($patientName)  $builder->where("patient.name", $patientName);
        if ($patientId)    $builder->where("appointments.patient_id", $patientId);
        if ($status)       $builder->where("appointments.status", $status);
        if ($date)         $builder->where("appointments.Appointment_date", $date);

        if ($dateFilter) {
            $today = date('Y-m-d');

            if ($dateFilter == 'today') {
                $builder->where("appointments.Appointment_date", $today);
            }
            elseif ($dateFilter == 'this_week') {
                $builder->where("YEARWEEK(appointments.Appointment_date, 1) =", date('oW'));
            }
            elseif ($dateFilter == 'last_month') {
                $builder->where("DATE_FORMAT(appointments.Appointment_date,'%Y-%m') =", date('Y-m', strtotime("-1 month")));
            }
        }


        $sortBy    = $this->request->getVar("sortBy") ?? "appointments.id";
        $sortOrder = $this->request->getVar("sortOrder") ?? "DESC";
        $perPage   = 30;
        $page      = $this->request->getVar("page") ?? 1;

        $data  = $builder->orderBy($sortBy, $sortOrder)->paginate($perPage, 'default', $page);
        $pager = $builder->pager;

        return $this->respond([
            "status" => true,
            "message" => "Appointments fetched successfully",
            "data"   => $data,
            "pager"  => [
                "total_pages"   => $pager->getPageCount(),
                "current_page"  => $pager->getCurrentPage(),
                "next_page"     => $pager->getNextPageURI(),
                "prev_page"     => $pager->getPreviousPageURI()
            ]
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "error"  => $e->getMessage()
        ], 500);
    }
}



public function BookAppointment()
{
    try {

        $validationRules = [
            "doctorId" => "required|integer",
            "hospital_id" => "required|integer",
            "appointment_date" => "required",
            "appointment_startTime" => "required"
        ];

        if (!$this->validate($validationRules)) {
            return $this->respond([
                "status" => false,
                "error" => $this->validator->getErrors(),
            ], 400);
        }

        
        $patientId = $this->request->id;
        $userRole  = (int)$this->request->role;

        if ($userRole !== 2) {
            return $this->respond([
                "status" => false,
                "message" => "Only patients can book appointments"
            ], 403);
        }

        $doctorId   = $this->request->getVar("doctorId");
        $hospitalId = $this->request->getVar("hospital_id");
        $date       = $this->request->getVar("appointment_date");

        
        $startFormatted = convertToDatabaseTime($this->request->getVar("appointment_startTime"));
        $endFormatted   = addHoursToTime($startFormatted);

        // Validate future date/time
        $validationResult = validateFutureAppointment($date, $startFormatted);
        if (!$validationResult['status']) {
            return $this->respond([
                "status" => false,
                "message" => $validationResult['message']
            ], 400);
        }

        // Check conflict
        $conflict = $this->appointmentModel
            ->where("doctor_id", $doctorId)
            ->where("Appointment_date", $date)
            ->groupStart()
                ->groupStart()
                    ->where("Appointment_startTime <=", $startFormatted)
                    ->where("Appointment_endTime >", $startFormatted)
                ->groupEnd()
                ->orGroupStart()
                    ->where("Appointment_startTime <", $endFormatted)
                    ->where("Appointment_endTime >=", $endFormatted)
                ->groupEnd()
                ->orGroupStart()
                    ->where("Appointment_startTime >=", $startFormatted)
                    ->where("Appointment_endTime <=", $endFormatted)
                ->groupEnd()
            ->groupEnd()
            ->first();

        if ($conflict) {
            return $this->respond([
                "status" => false,
                "message" => "Doctor already has an appointment in this time slot"
            ], 409);
        }

        // Insert appointment
        $data = [
            "doctor_id" => $doctorId,
            "patient_id" => $patientId,
            "hospital_id" => $hospitalId,
            "Appointment_date" => $date,
            "Appointment_startTime" => $startFormatted,
            "Appointment_endTime" => $endFormatted,
            "status" => "pending", // default
            "created_by" => $patientId
        ];

        $appointmentId = $this->appointmentModel->insert($data);

        
        $logData = [
            "appointment_id" => $appointmentId,
            "action" => "booked",
            "old_date" => null,
            "old_start_time" => null,
            "new_date" => $date,
            "new_start_time" => $startFormatted,
            "reason" => null,
            "action_by" => $patientId,
            "created_by" => $patientId
        ];

        $this->appointmentslogModel->insert($logData);

        return $this->respond([
            "status" => true,
            "message" => "Appointment booked successfully",
            "appointment_id" => $appointmentId,
        ], 200);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "error" => $e->getMessage()
        ], 500);
    }
}


public function rescheduleAppointment()
{
    try {
        $validationRules = [
            "appointment_id"           => "required|integer",
            "newAppointmentstartTime"  => "required",
            "reschedule_reason"        => "required"
        ];

        if (!$this->validate($validationRules)) {
            return $this->respond([
                "status" => false,
                "message" => "All required fields must be provided",
                "error" => $this->validator->getErrors(),
            ], 400);
        }

        $userId     = $this->request->id;
        $userRole   = $this->request->role;
        $apptId     = (int)$this->request->getVar("appointment_id");
        $reason     = $this->request->getVar("reschedule_reason");

        $appointment = $this->appointmentModel->find($apptId);

        if (!$appointment) {
            return $this->respond([
                "status" => false,
                "message" => "Appointment not found"
            ]);
        }

        // Role validations
        if ($userRole === "2" && $appointment['patient_id'] != $userId) {
            return $this->respond(["status" => false, "message" => "Unauthorized"]);
        }
        if ($userRole === "1" && $appointment['doctor_id'] != $userId) {
            return $this->respond(["status" => false, "message" => "Unauthorized"]);
        }
        if ($userRole === "0" && $appointment['hospital_id'] != $this->request->hospital_id) {
            return $this->respond(["status" => false, "message" => "Unauthorized"]);
        }

        
        $oldDate  = $appointment['Appointment_date'];
        $oldTime  = $appointment['Appointment_startTime'];

        // New details
        $newDate  = $this->request->getVar("newAppointmentDate") ?? $oldDate;
        $newStart = convertToDatabaseTime($this->request->getVar("newAppointmentstartTime"));
        $newEnd   = addHoursToTime($newStart);

        // Validate time is future
        $valid = validateFutureAppointment($newDate, $newStart);
        if (!$valid['status']) {
            return $this->respond(["status" => false, "message" => $valid['message']]);
        }

        // Doctor conflict check
        $conflict = $this->appointmentModel
            ->where("doctor_id", $appointment['doctor_id'])
            ->where("Appointment_date", $newDate)
            ->groupStart()
                ->where("Appointment_startTime <=", $newStart)
                ->where("Appointment_endTime >", $newStart)
                ->orGroupStart()
                    ->where("Appointment_startTime <", $newEnd)
                    ->where("Appointment_endTime >=", $newEnd)
                ->groupEnd()
                ->orGroupStart()
                    ->where("Appointment_startTime >=", $newStart)
                    ->where("Appointment_endTime <=", $newEnd)
                ->groupEnd()
            ->groupEnd()
            ->where("id !=", $apptId) // exclude same appt
            ->first();

        if ($conflict) {
            return $this->respond([
                "status" => false,
                "message" => "Doctor already has an appointment at this time"
            ]);
        }

        $this->db->transStart();

        
        $this->appointmentModel->update($apptId, [
            "Appointment_date"       => $newDate,
            "Appointment_startTime"  => $newStart,
            "Appointment_endTime"    => $newEnd,
            "status"                 => "pending",
            "updated_by"             => $userId
        ]);

        
        $this->appointmentslogModel->insert([
            "appointment_id" => $apptId,
            "action"         => "rescheduled",
            "old_date"       => $oldDate,
            "old_start_time" => $oldTime,
            "new_date"       => $newDate,
            "new_start_time" => $newStart,
            "reason"         => $reason,
            "action_by"      => $userId,
            "created_by"     => $userId
        ]);

        $this->db->transComplete();

        return $this->respond([
            "status" => true,
            "message" => "Appointment rescheduled successfully"
        ]);

    } catch (\Exception $e) {
        $this->db->transRollback();
        return $this->respond([
            "status" => false,
            "error" => $e->getMessage()
        ], 500);
    }
}


public function confirmAppointment()
{
    try {
        $doctorId       = $this->request->id;
        $userRole       = ($this->request->role ?? -1);
        $activeHospital = $this->request->hospital_id ?? null;

       

        // Only doctors can confirm
        if ($userRole != "1") {
            return $this->respond([
                "status" => false,
                "Mssge"  => "Only doctors can confirm appointments"
            ], 403);
        }

        if (!$activeHospital) {
            return $this->respond([
                "status" => false,
                "Mssge" => "Select a hospital first"
            ], 400);
        }

        $appointmentId = $this->request->getVar("appointment_id");

        if (!$appointmentId) {
            return $this->respond([
                "status" => false,
                "Mssge" => "appointment_id is required"
            ], 422);
        }

        // Get appointment
        $appointment = $this->appointmentModel->find($appointmentId);

        if (!$appointment) {
            return $this->respond([
                "status" => false,
                "Mssge" => "Appointment not found"
            ], 404);
        }

        // Appointment must belong to the hospital selected doctor
        if ((int)$appointment['hospital_id'] !== (int)$activeHospital) {
            return $this->respond([
                "status" => false,
                "Mssge" => "You cannot confirm this appointment (Different Hospital)"
            ], 403);
        }

        // Must be assigned to doctor
        if ((int)$appointment['doctor_id'] !== (int)$doctorId) {
            return $this->respond([
                "status" => false,
                "Mssge" => "You are not authorized to confirm this appointment"
            ], 403);
        }

        // Only pending or rescheduled appointments can be confirmed
        if (!($appointment['status'] == "pending" || $appointment['status'] == "rescheduled")) {
            return $this->respond([
                "status" => false,
                "Mssge"  => "Only appointments with status 'pending' or 'rescheduled'can be confirmed"
            ], 400);
        }

        
        $oldDate  = $appointment['Appointment_date'];
        $oldTime  = $appointment['Appointment_startTime'];

        $this->db->transStart();

        
        $this->appointmentModel->update($appointmentId, [
            "status"     => "booked",
            "updated_by" => $doctorId
        ]);

        
        $this->appointmentslogModel->insert([
            "appointment_id" => $appointmentId,
            "action"         => "confirmed",
            "old_date"       => $oldDate,
            "old_start_time" => $oldTime,
            "new_date"       => $oldDate,   // same date
            "new_start_time" => $oldTime,   // same time
            "reason"         => null,
            "action_by"      => $doctorId,
            "created_by"     => $doctorId
        ]);

        $this->db->transComplete();

        $updatedAppointment = $this->appointmentModel->find($appointmentId);

        return $this->respond([
            "status" => true,
            "Mssge"  => "Appointment confirmed successfully",
            "data"   => $updatedAppointment
        ]);

    } catch (\Exception $e) {
        $this->db->transRollback();
        return $this->respond([
            "status" => false,
            "Error"  => $e->getMessage()
        ], 500);
    }
}


public function completeAppointment()
{
    try {
        $doctorId       = $this->request->id;
        $userRole       = ($this->request->role ?? -1);
        $activeHospital = $this->request->hospital_id ?? null;

        if ($userRole != "1") {
            return $this->respond([
                "status" => false,
                "Error_Mssge" => "Only doctors can complete an appointment"
            ], 403);
        }

        if (!$activeHospital) {
            return $this->respond([
                "status" => false,
                "Error_Mssge" => "Select a hospital first"
            ], 400);
        }

        $validationRules = [
            "appointment_id"   => "required|integer",
            "weight"           => "required",
            "bp_systolic"      => "required|integer",
            "bp_diastolic"     => "required|integer",
            "doctor_comment"   => "required"
        ];

        if (!$this->validate($validationRules)) {
            return $this->respond([
                "status" => false,
                "Mssge"  => "Validation failed",
                "Error"  => $this->validator->getErrors()
            ], 422);
        }

        $appointmentId  = (int) $this->request->getVar("appointment_id");
        $weight         = $this->request->getVar("weight");
        $bpSystolic     = (int) $this->request->getVar("bp_systolic");
        $bpDiastolic    = (int) $this->request->getVar("bp_diastolic");
        $doctorComment  = $this->request->getVar("doctor_comment");

        $complaints     = $this->request->getVar("complaints") ?? [];
        $diagnoses      = $this->request->getVar("diagnoses") ?? [];
        $prescriptions  = $this->request->getVar("prescriptions") ?? [];

        $complaints    = json_decode(json_encode($complaints), true);
        $diagnoses     = json_decode(json_encode($diagnoses), true);
        $prescriptions = json_decode(json_encode($prescriptions), true);

        $appt = $this->appointmentModel->find($appointmentId);
        if (!$appt || ($appt['status'] ?? '') !== 'booked') {
            return $this->respond([
                "status" => false,
                "Mssge"  => "Appointment invalid or already processed"
            ], 400);
        }

        if ((int)$appt['doctor_id'] !== (int)$doctorId) {
            return $this->respond([
                "status" => false,
                "Mssge"  => "Doctor can complete only own appointment"
            ], 403);
        }

        if ((int)$appt['hospital_id'] !== (int)$activeHospital) {
            return $this->respond([
                "status" => false,
                "Mssge"  => "Active hospital mismatch"
            ], 403);
        }

        $oldDate = $appt['Appointment_date'];
        $oldTime = $appt['Appointment_startTime'];

        $this->db->transStart();

        
        $visitRecordId = $this->visitRecords->insert([
            "appointment_id" => $appointmentId,
            "weight" => $weight,
            "bp_systolic" => $bpSystolic,
            "bp_diastolic" => $bpDiastolic,
            "doctor_comment" => $doctorComment,
            "created_by" => $doctorId
        ]);

        if (!$visitRecordId) {
            throw new \Exception("Failed to insert visit record");
        }

        
        $complaintsBatch = [];
        foreach ($complaints as $c) {
            if (!is_array($c) || empty(trim($c['complaint'] ?? ''))) continue;

            $complaintsBatch[] = [
                "visit_record_id" => $visitRecordId,
                "complaint"       => trim($c['complaint']),
                "description"     => $c['description'] ?? null,
                "severity"        => $c['severity'] ?? 'low',
                "days"            => isset($c['days']) ? (int)$c['days'] : null,
                "created_by"      => $doctorId
            ];
        }
        if (!empty($complaintsBatch)) $this->complaintsModel->insertBatch($complaintsBatch);

        
        $diagnosisBatch = [];
        $diagnosisCache = [];

        foreach ($diagnoses as $d) {
            if (!is_array($d)) continue;

            $notes = $d['notes'] ?? null;
            $diagnosisId = null;

            if (!empty($d['diagnosis_id'])) {
                $diagnosisId = (int)$d['diagnosis_id'];
            } else {
                $name = trim($d['name'] ?? '');
                if ($name === '') continue;

                if (isset($diagnosisCache[$name])) {
                    $diagnosisId = $diagnosisCache[$name];
                } else {
                    $existing = $this->masterDaignosesModel
                        ->where('name', $name)
                        ->where('isDeleted', 0)
                        ->first();

                    if ($existing) {
                        $diagnosisId = $existing['id'];
                    } else {
                        $diagnosisId = $this->masterDaignosesModel->insert([
                            "name" => $name,
                            "created_by" => $doctorId
                        ]);
                    }
                    $diagnosisCache[$name] = $diagnosisId;
                }
            }

            if ($diagnosisId) {
                $diagnosisBatch[] = [
                    "visit_record_id" => $visitRecordId,
                    "diagnosis_id"    => $diagnosisId,
                    "notes"           => $notes,
                    "created_by"      => $doctorId
                ];
            }
        }
        if (!empty($diagnosisBatch)) $this->diagnosisModel->insertBatch($diagnosisBatch);

        
        $presBatch = [];
        foreach ($prescriptions as $p) {
            if (!is_array($p)) continue;

            $m = trim($p['medicine_name'] ?? '');
            $dos = trim($p['dosage'] ?? '');
            $freq = trim($p['frequency'] ?? '');
            $dur = trim($p['duration'] ?? '');

            if ($m === '' || $dos === '' || $freq === '' || $dur === '') continue;

            $presBatch[] = [
                "visit_record_id" => $visitRecordId,
                "medicine_name"   => $m,
                "dosage"          => $dos,
                "frequency"       => $freq,
                "duration"        => $dur,
                "instructions"    => $p['instructions'] ?? null,
                "created_by"      => $doctorId
            ];
        }
        if (!empty($presBatch)) $this->prescriptionModel->insertBatch($presBatch);

        
        $this->appointmentModel->update($appointmentId, [
            "status"     => "completed",
            "updated_by" => $doctorId
        ]);

        
        $this->appointmentslogModel->insert([
            "appointment_id" => $appointmentId,
            "action"         => "completed",
            "old_date"       => $oldDate,
            "old_start_time" => $oldTime,
            "new_date"       => $oldDate,
            "new_start_time" => $oldTime,
            "reason"         => null,
            "action_by"      => $doctorId,
            "created_by"     => $doctorId
        ]);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \Exception("Transaction failed");
        }

        return $this->respond([
            "status" => true,
            "Mssge"  => "Appointment completed",
            "visit_record_id" => $visitRecordId
        ]);

    } catch (\Exception $e) {
        $this->db->transRollback();
        return $this->respond([
            "status" => false,
            "Error"  => $e->getMessage()
        ], 500);
    }
}



public function ExportAppointmentsCSV()
{
    try {
        $userId        = $this->request->id;
        $userRole      = $this->request->role;
        $activeHospital = $this->request->hospital_id ?? null;

        // filters
        $appointmentId = $this->request->getVar("appointmentId");
        $search        = $this->request->getVar("search");
        $doctorName    = $this->request->getVar("doctorName");
        $patientName   = $this->request->getVar("patientName");
        $doctorId      = $this->request->getVar("doctorId");
        $patientId     = $this->request->getVar("patientId");
        $status        = $this->request->getVar("status");
        $date          = $this->request->getVar("date");
        $dateFilter    = $this->request->getVar("dateFilter");
        $filterHospitalId = $this->request->getVar("hospital_id");

        $builder = $this->appointmentModel
            ->select("appointments.id, appointments.status, appointments.rescheduled_from,
                      appointments.reschedule_reason, appointments.Appointment_date,
                      appointments.Appointment_startTime, appointments.Appointment_endTime,
                      doctor.name as DoctorName, patient.name as PatientName,
                      hospital.name as HospitalName, appointments.created_at, appointments.updated_at")
            ->join("users as doctor", "doctor.id = appointments.doctor_id", "left")
            ->join("users as patient", "patient.id = appointments.patient_id", "left")
            ->join("hospitals as hospital", "hospital.id = appointments.hospital_id", "left");

        // ==== ROLE BASED ACCESS ====

        if ($userRole == "2") { // patient
            $builder->groupStart()
                    ->where("appointments.patient_id", $userId)
                    ->orWhere("appointments.parent_id IN (SELECT id FROM appointments WHERE patient_id={$userId})")
                    ->groupEnd();
        }
        elseif ($userRole == "1") { // doctor
            if (!$activeHospital) {
                return $this->respond(["status"=>false,"Mssge"=>"Select hospital first"],400);
            }

            $builder->groupStart()
                    ->where("appointments.doctor_id", $userId)
                    ->orWhere("appointments.parent_id IN (SELECT id FROM appointments WHERE doctor_id={$userId})")
                    ->groupEnd()
                    ->where("appointments.hospital_id", $activeHospital);
        }
        elseif ($userRole == "0") { // admin
            if (!$activeHospital) {
                return $this->respond(["status"=>false,"Mssge"=>"Select hospital first"],400);
            }

            $builder->where("appointments.hospital_id", $activeHospital);
        }
        elseif ($userRole == "3") { // superadmin
            if (!empty($filterHospitalId)) {
                $builder->where("appointments.hospital_id", $filterHospitalId);
            }
        }

        // ==== Filters ====

        if (!empty($appointmentId)) {
            $builder->groupStart()
                    ->where("appointments.id", $appointmentId)
                    ->orWhere("appointments.parent_id", $appointmentId)
                    ->groupEnd();
        }

        if (!empty($search)) {
            $builder->groupStart()
                    ->like("doctor.name", $search)
                    ->orLike("patient.name", $search)
                    ->groupEnd();
        }

        if (!empty($doctorName))  $builder->where("doctor.name", $doctorName);
        if (!empty($doctorId))    $builder->where("appointments.doctor_id", $doctorId);
        if (!empty($patientName)) $builder->where("patient.name", $patientName);
        if (!empty($patientId))   $builder->where("appointments.patient_id", $patientId);
        if (!empty($status))      $builder->where("appointments.status", $status);
        if (!empty($date))        $builder->where("appointments.Appointment_date", $date);

        // superadmin hospital filter
        if (!empty($filterHospitalId)) {
            $builder->where("appointments.hospital_id", $filterHospitalId);
        }

        // ==== Date filters ====
        if (!empty($dateFilter)) {
            $today = date('Y-m-d');

            if ($dateFilter === 'today') {
                $builder->where("appointments.Appointment_date", $today);
            } elseif ($dateFilter === 'this_week') {
                $monday = date('Y-m-d', strtotime('monday this week'));
                $sunday = date('Y-m-d', strtotime('sunday this week'));
                $builder->where("appointments.Appointment_date >=", $monday)
                        ->where("appointments.Appointment_date <=", $sunday);
            } elseif ($dateFilter === 'last_month') {
                $firstDayLastMonth = date('Y-m-01', strtotime('first day of last month'));
                $lastDayLastMonth  = date('Y-m-t', strtotime('last month'));
                $builder->where("appointments.Appointment_date >=", $firstDayLastMonth)
                        ->where("appointments.Appointment_date <=", $lastDayLastMonth);
            }
        }

        $appointments = $builder->orderBy('appointments.id', 'DESC')->findAll();

        // CSV filename
        $filename = "appointments_export_" . date("Ymd_His") . ".csv";

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=$filename");

        $output = fopen("php://output", "w");

        fputcsv($output, [
            "ID","Status","Rescheduled From","Reschedule Reason","Date","Start Time",
            "End Time","Doctor","Patient","Hospital","Created","Updated"
        ]);

        foreach ($appointments as $row) {
            fputcsv($output, [
                $row['id'], $row['status'], $row['rescheduled_from'],
                $row['reschedule_reason'], $row['Appointment_date'],
                !empty($row['Appointment_startTime']) ? date("g:i A", strtotime($row['Appointment_startTime'])) : '',
                !empty($row['Appointment_endTime']) ? date("g:i A", strtotime($row['Appointment_endTime'])) : '',
                $row['DoctorName'], $row['PatientName'], $row['HospitalName'],
                $row['created_at'], $row['updated_at']
            ]);
        }

        fclose($output);
        exit;

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "Error" => $e->getMessage(),
        ]);
    }
}


public function showHistory()
{
    try {
        $userId  = $this->request->id;
        $userRole = $this->request->role;

        // patientId input OR self for patient portal
        $patientId = $this->request->getVar('patientId') ?? $userId;

        // Hospital filter (optional)
        $hospital_id = $this->request->getVar("hospital_id");

        
        $builder = $this->db->table('appointments a')
            ->select("
                a.id as appointment_id,
                a.doctor_id, a.patient_id,
                h.id as hospital_id, h.name as hospital_name, h.contact_no as hospital_phone, h.address as hospital_address,
                d.name as doctor_name, p.name as patient_name,
                a.Appointment_date, a.Appointment_startTime, a.Appointment_endTime,
                v.id as visit_id, 
                v.weight, v.bp_systolic, v.bp_diastolic, 
                v.doctor_comment, v.created_at
            ")
            ->join('visit_records v', 'v.appointment_id = a.id')
            ->join('users d', 'd.id = a.doctor_id')
            ->join('users p', 'p.id = a.patient_id')
            ->join('hospitals h', 'h.id = a.hospital_id')
            ->where("a.status", "completed");

        // Patient Portal → only his own
        if ($userRole == "2") {
            $builder->where("a.patient_id", $patientId);
        }
        // Doctor → only patients in his hospital
        elseif ($userRole == "1") {
            $builder->where("a.doctor_id", $userId);
        }
        // Admin → only their hospital
        elseif ($userRole == "0") {
            $activeHospital = $this->request->hospital_id ?? null;
            if (!$activeHospital) {
                return $this->respond(["status"=>false,"message"=>"Select hospital first"],400);
            }
            $builder->where("a.hospital_id", $activeHospital);
        }
        // SuperAdmin → optional hospital filter
        elseif ($userRole == "3" && !empty($hospital_id)) {
            $builder->where("a.hospital_id", $hospital_id);
        }

        $appointments = $builder
            ->orderBy('a.Appointment_date', 'DESC')
            ->get()
            ->getResultArray();

        $result = [];

        foreach ($appointments as $row) {
            $visitId = $row['visit_id'];

            // Fetch all complaints for this visit
            $complaints = $this->complaintsModel
                ->select("complaint, description, severity, days")
                ->where("visit_record_id", $visitId)
                ->where("isDeleted", 0)
                ->findAll();

            // Fetch all diagnoses
            $diagnoses = $this->diagnosisModel
                ->select("m.name as diagnosis_name, notes")
                ->join("master_diagnoses m", "m.id = diagnosis_id", "left")
                ->where("visit_record_id", $visitId)
                ->where("diagnosis.isDeleted", 0)
                ->findAll();

            // Fetch prescriptions
            $prescriptions = $this->prescriptionModel
                ->select("medicine_name, dosage, frequency, duration, instructions")
                ->where("visit_record_id", $visitId)
                ->where("isDeleted", 0)
                ->findAll();

            $result[] = [
                "appointment_id"        => $row['appointment_id'],
                "hospital" => [
                    "id"        => $row['hospital_id'],
                    "name"      => $row['hospital_name'],
                    "contact"   => $row['hospital_phone'],
                    "address"   => $row['hospital_address'],
                ],
                "doctor" => [
                    "id"        => $row['doctor_id'],
                    "name"      => $row['doctor_name'],
                ],
                "patient" => [
                    "id"        => $row['patient_id'],
                    "name"      => $row['patient_name'],
                ],
                "appointment_date"      => $row['Appointment_date'],
                "appointment_startTime" => $row['Appointment_startTime'],
                "appointment_endTime"   => $row['Appointment_endTime'],
                "visit_details" => [
                    "visit_id"        => $visitId,
                    "date"            => $row['created_at'],
                    "weight"          => $row['weight'],
                    "bp_systolic"     => $row['bp_systolic'],
                    "bp_diastolic"    => $row['bp_diastolic'],
                    "doctor_comment"  => $row['doctor_comment'],
                    "complaints"      => $complaints,
                    "diagnoses"       => $diagnoses,
                    "prescriptions"   => $prescriptions,
                ]
            ];
        }

        return $this->respond([
            "status" => true,
            "message" => "Patient history fetched successfully",
            "data" => $result,
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "error" => $e->getMessage(),
        ]);
    }
}


public function getPatientStats()
{
    try {
        $userId = $this->request->id;
        $userRole = $this->request->role;

        // Patient ID param OR logged-in user
        $patientId = $this->request->getVar('patientId') ?? $userId;

        // Access control: only patient sees own stats unless admin/doctor provided patientId
        if ($userRole == "2" && $patientId != $userId) {
            return $this->respond([
                "status" => false,
                "message" => "Unauthorized to view other patient's stats"
            ], 403);
        }

        // Fetch weight & BP history
        $stats = $this->db->table('visit_records v')
            ->select("
                v.created_at AS date,
                v.weight,
                v.bp_systolic,
                v.bp_diastolic
            ")
            ->join("appointments a", "a.id = v.appointment_id")
            ->where("a.patient_id", $patientId)
            ->where("v.isDeleted", 0)
            ->orderBy("v.created_at", "ASC")
            ->get()
            ->getResultArray();

        return $this->respond([
            "status" => true,
            "message" => "Fetched patient stats successfully",
            "data" => $stats
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "error" => $e->getMessage()
        ]);
    }
}


public function cancelAppointment()
{
    try {
        $userId   = $this->request->id;
        $userRole = $this->request->role;
        $activeHospital = $this->request->hospital_id ?? null;

        $appointmentId = $this->request->getVar("appointmentId");
        $cancelReason  = $this->request->getVar("cancel_reason");

        if (!$appointmentId || !$cancelReason) {
            return $this->respond([
                "status" => false,
                "message" => "appointmentId and cancel_reason are required"
            ], 400);
        }

        // Fetch appointment
        $appointment = $this->appointmentModel->find($appointmentId);

        if (!$appointment) {
            return $this->respond([
                "status" => false,
                "message" => "Appointment not found"
            ], 404);
        }

        // Only pending/booked appointments can be cancelled
        if (!in_array($appointment['status'], ['booked', 'pending'])) {
            return $this->respond([
                "status" => false,
                "message" => "Only booked or pending appointments can be cancelled"
            ], 400);
        }

        // Authorization rules
        $appointmentDoctor   = $appointment["doctor_id"];
        $appointmentPatient  = $appointment["patient_id"];
        $appointmentHospital = $appointment["hospital_id"];

        if ($userRole === "1") { // Doctor
            if ($appointmentDoctor != $userId) {
                return $this->respond([
                    "status" => false,
                    "message" => "Unauthorized: doctor can cancel only own appointments"
                ], 403);
            }
        } 
        elseif ($userRole === "0") { // Admin
            if ($activeHospital != $appointmentHospital) {
                return $this->respond([
                    "status" => false,
                    "message" => "Unauthorized: admin can cancel only same hospital appointments"
                ], 403);
            }
        } 
        elseif ($userRole === "2") { // Patient
            if ($appointmentPatient != $userId) {
                return $this->respond([
                    "status" => false,
                    "message" => "Unauthorized: patient can cancel only own appointment"
                ], 403);
            }
        }

        
        $this->db->transStart();

        
        $this->appointmentModel->update($appointmentId, [
            "status"     => "cancelled",
            "updated_by" => $userId
        ]);

        
        $this->appointmentslogModel->insert([
            "appointment_id" => $appointmentId,
            "action"         => "cancelled",
            "old_date"       => $appointment['Appointment_date'],
            "old_start_time" => $appointment['Appointment_startTime'],
            "new_date"       => null,
            "new_start_time" => null,
            "reason"         => $cancelReason,
            "action_by"      => $userId,
            "created_by"     => $userId
        ]);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \Exception("Failed to cancel appointment");
        }

        return $this->respond([
            "status" => true,
            "message" => "Appointment cancelled successfully"
        ]);

    } catch (\Exception $e) {
        $this->db->transRollback();
        return $this->respond([
            "status" => false,
            "error" => $e->getMessage()
        ], 500);
    }
}


public function diagnosisList()
{
    try{
       $userRole = $this->request->role;

       if($userRole != "1")
       {
        return $this->respond([
            "status" => false,
            "Mssge" => "Oly doctors can access this route"
        ]);
       }


       $data = $this->masterDaignosesModel->findAll();

       if($data)
       {
        return $this->respond([
            "status" => true,
            "Mssge" => "Fetched all the diagonsis list",
            "data" => $data
        ]);
       }

    }catch(\Exception $e)
    {
        return $this->respond([
            "status" => false,
            "Error" => $e->getMessage()
        ]);
    }
}


// public function cancelPendingAppointments()
// {
//     try {
//         // This is a manual trigger for the CRON job
//         $thresholdTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
//         // Find pending appointments older than 24 hours
//         $pendingAppointments = $this->appointmentModel
//                ->where('status', 'pending')
//                ->where('created_at <', $thresholdTime)
//                ->findAll();

//         if(empty($pendingAppointments))
//         {
//             return $this->respond([
//                 "status" => true,
//                 "Mssge" => "No pending appointments found older than 24 hours",
//                 "count" => 0
//             ]);
//         }

//         $ids = array_column($pendingAppointments, 'id');

//         $result = $this->appointmentModel
//             ->whereIn('id', $ids)
//             ->set(['status' => 'cancelled'])
//             ->update();

//         if($result)
//         {
//             return $this->respond([
//                 "status" => true,
//                 "Mssge" => "Successfully cancelled " . count($ids) . " pending appointment(s)",
//                 "count" => count($ids),
//                 "cancelled_ids" => $ids
//             ]);
//         }
        
//         return $this->respond([
//             "status" => false,
//             "Mssge" => "Failed to cancel appointments"
//         ]);
        
//     } catch(\Exception $e)
//     {
//         return $this->respond([
//             "status" => false,
//             "Error" => $e->getMessage()
//         ]);
//     }
// }




public function DoctorAvailability()
{
    try {
        $validationRules = [
            "hospital_id" => "required",
            "DoctorId" => "required",
            "Date" => "required"
        ];

        if (!$this->validate($validationRules)) {
            return $this->respond([
                "status" => false,
                "message" => $this->validator->getErrors()
            ], 400);
        }

        $hospital_id = $this->request->getVar("hospital_id");
        $doctorId    = $this->request->getVar("DoctorId");
        $date        = $this->request->getVar("Date");

        
        $allSlots = ["10:00:00","11:00:00","12:00:00","14:00:00","15:00:00","16:00:00","17:00:00"];

        
        $DoctorDetails = $this->userhospitalMapping
            ->where("user_id", $doctorId)
            ->where("hospital_id", $hospital_id)
            ->where("role", "1") // role = doctor
            ->where("isDeleted", 0)
            ->first();

        if (!$DoctorDetails) {
            return $this->respond([
                "status" => false,
                "message" => "Doctor does not belong to the selected hospital"
            ], 404);
        }

        
        $FilledSlots = $this->appointmentModel
            ->where("doctor_id", $doctorId)
            ->where("Appointment_date", $date)
            //->where("hospital_id", $hospital_id)
            ->groupStart()
                ->where("status", "booked")
                ->orWhere("status", "pending")
            ->groupEnd()
            ->findAll();

        $bookedTimes = array_map(fn($slot) => date("H:i:s", strtotime($slot["Appointment_startTime"])), $FilledSlots);

        
        $availableSlots = array_values(array_filter($allSlots, fn($slot) => !in_array($slot, $bookedTimes)));

        return $this->respond([
            "status" => true,
            "message" => "Available slots fetched successfully",
            "data" => $availableSlots
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "Error" => $e->getMessage()
        ]);
    }
}



public function Get_Appointment_Log()
{
  try{
     $validationRules = [
        
            "appointment_id" =>[
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

        $appointment_id = $this->request->getVar("appointment_id");

        $appointmentDetails = $this->appointmentModel->find($appointment_id);


        $userRole = $this->request->role;
        $userId = $this->request->id;

        if ($userRole === "1" && $appointmentDetails['doctor_id'] != $userId) {
            return $this->respond([
                "status" => false,
                "message" => "Doctors can view only their appointment logs"
            ], 403);
        }

        if ($userRole === "2" && $appointmentDetails['patient_id'] != $userId) {
            return $this->respond([
                "status" => false,
                "message" => "Patients can view only their appointment logs"
            ], 403);
        }

        $data = $this->appointmentslogModel
                    ->where("appointment_id", $appointment_id)
                    ->orderBy("id", "ASC")
                    ->findAll();


        return $this->respond([
            "status" => true,
            "Mssge" => "Fetched the appointments log successfully",
            "data" => $data
        ]);
  }catch(\Exception $e)
  {
    return $this->respond([
        "status" => true,
        "Error" => $e->getMessage()
    ]);
  }
}


}

