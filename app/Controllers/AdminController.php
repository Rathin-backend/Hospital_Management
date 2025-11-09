<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use App\Models\AppointmentModel;
use App\Models\UserHospitalMappingModel;
use App\Models\HospitalsModel;
use Exception;

class AdminController extends ResourceController
{
    private $userModel;
    private $appointmentModel;
    private $hospitalModel;
    private $userhospitalMapping;
    private $db;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->appointmentModel = new AppointmentModel();
        $this->hospitalModel = new HospitalsModel();
        $this->userhospitalMapping = new UserHospitalMappingModel();
        $this->db = db_connect();
    }


private function validateDoctorOwnership($doctorId, $loggedRole, $hospitalId)
    {
        if ($loggedRole == 3) return true; // SuperAdmin full access

        $doctorMapping = $this->userhospitalMapping
            ->where("user_id", $doctorId)
            ->where("deleted_at", null)
            ->first();

        return $doctorMapping && $doctorMapping['hospital_id'] == $hospitalId;
    }

    private function validatePatientOwnership($patientId, $loggedRole, $hospitalId)
    {
        if ($loggedRole == 3) return true;

        // Check latest appointment
        $appointment = $this->appointmentModel
            ->where("patient_id", $patientId)
            ->orderBy("created_at", "DESC")
            ->first();

        return $appointment && $appointment['hospital_id'] == $hospitalId;
    }


public function addSuperAdmin() // summa - dummy
{
    $data = [
        'name' => 'superadmin200',
        'email' => 'superadmin200@gmail.com',
        'password' => password_hash('123456', PASSWORD_BCRYPT),
        'phone_no' => '9999999999',
        'gender' => 'Male',
    ];

    $superId = $this->userModel->insert($data);



    $this->userhospitalMapping->insert([
        'user_id' => $superId,
        'role' => '3',  // SUPERADMIN
        'created_by' => $superId
    ]);

    return $this->response->setJSON([
        'status' => true,
        'message' => 'SuperAdmin created successfully',
        'superAdminId' => $superId
    ]);

}

 

public function addAdmin()// Done
{
    try {
        $validationRules = [
            "name" => "required",
            "email" => "required|valid_email",
            "password" => "required|min_length[6]",
            "phone_no" => "required",
            "gender" => "permit_empty",
            "hospital_id" => "required|integer",
        ];

        if (!$this->validate($validationRules)) {
            return $this->respond([
                "status" => false,
                "message" => $this->validator->getErrors()
            ]);
        }

        
        $loggedInRole = $this->request->role;
        $loggedInId = $this->request->id; // added by JWTAuthFilter

        if ($loggedInRole != "3") { // 3 = SuperAdmin
            return $this->respond([
                "status" => false,
                "message" => "Only SuperAdmins can add Admins"
            ], ResponseInterface::HTTP_FORBIDDEN);
        }

        $name = $this->request->getVar("name");
        $email = $this->request->getVar("email");
        $password = $this->request->getVar("password");
        $hospital_id = $this->request->getVar("hospital_id");
        $phone_no = $this->request->getVar("phone_no");
        $gender = $this->request->getVar("gender");

        
        $existingUser = $this->userModel->where("email", $email)->first();

    if ($existingUser) {
        $userId = $existingUser['id'];

    // Check if already mapped to this hospital
    $existingMapping = $this->userhospitalMapping
        ->where("user_id", $userId)
        ->where("hospital_id", $hospital_id)
        ->first();

    if ($existingMapping) {
        return $this->respond([
            "status" => false,
            "message" => "This Admin is already assigned to this hospital"
        ]);
    }

    // Only insert mapping
    $mappingData = [
        "user_id" => $userId,
        "hospital_id" => $hospital_id,
        "role" => "0",
        "created_by" => $loggedInId
    ];

    if (!$this->userhospitalMapping->insert($mappingData)) {
        return $this->respond(["status" => false, "message" => "Hospital Mapping Failed"]);
    }

    return $this->respond([
        "status" => true,
        "message" => "Admin successfully assigned to another hospital"
    ]);
     }

        
        $userData = [
            "name" => $name,
            "email" => $email,
            "password" => password_hash($password, PASSWORD_BCRYPT),
            "phone_no" => $phone_no,
            "gender" => $gender,
            "created_by" => $loggedInId
        ];

        $this->db->transBegin();

        $userId = $this->userModel->insert($userData);

        if (!$userId) {
            $this->db->transRollback();
            return $this->respond(["status" => false, "message" => "Failed to Create Admin"]);
        }


        $mappingData = [
            "user_id" => $userId,
            "hospital_id" => $hospital_id,
            "role" => "0",
            "created_by" => $loggedInId
        ];

        if (!$this->userhospitalMapping->insert($mappingData)) {
            $this->db->transRollback();
            return $this->respond(["status" => false, "message" => "Hospital Mapping Failed"]);
        }

        $this->db->transCommit();

        return $this->respond([
            "status" => true,
            "message" => "Admin successfully added"
        ], ResponseInterface::HTTP_CREATED);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "error" => $e->getMessage()
        ], ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
    }
}




public function addHospital()//Done
{
    try {
        $validationRules = [
        "name" => [
            "rules" => "required"
        ],
        "address" => [
            "rules" => "required"
        ],
        "contact_no" => [
            "rules" => "required"
        ],
        "code" => [
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
        

        $name = $this->request->getVar("name");
        $address = $this->request->getVar("address");
        $contact_no = $this->request->getVar("contact_no");
        $code = $this->request->getVar("code");

        $data = [
            "name" => $name,
            "address" => $address,
            "contact_no" => $contact_no,
            "code" => $code
        ];

        $result = $this->hospitalModel->insert($data);

        if($result)
        {
            return $this->respond([
                "status" => true,
                "Mssge" => "Successfully added the hospital"
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


public function addDoctor()//Done
{
    try {
        $loggedUser = $this->request->userData->user;
        $loggedUserId = $loggedUser->id;
        $loggedUserRole = $loggedUser->role;

        
        $validationRules = [
            "name" => "required|min_length[3]",
            "email" => "required|valid_email",
            "password" => "required|min_length[6]",
            "gender" => "required",
            "expertise" => "required"
        ];

        if (!$this->validate($validationRules)) {
            return $this->respond([
                "status" => false,
                "errors" => $this->validator->getErrors()
            ], 400);
        }

        // Inputs
        $name = $this->request->getVar("name");
        $email = $this->request->getVar("email");
        $password = $this->request->getVar("password");
        $gender = $this->request->getVar("gender");
        $expertise = $this->request->getVar("expertise");

        
        if ($loggedUserRole == 0) {  // Admin
            $hospital_id = $loggedUser->hospital_id;  // From token
        } elseif ($loggedUserRole == 3) { // SuperAdmin
            $hospital_id = $this->request->getVar("hospital_id");
            if (!$hospital_id) {
                return $this->respond([
                    "status" => false,
                    "message" => "Hospital ID required for SuperAdmin"
                ], 400);
            }
        } else {
            return $this->respond([
                "status" => false,
                "message" => "Unauthorized: Only Admin/SuperAdmin can add doctors"
            ], 403);
        }

        $existingUser = $this->userModel
            ->where("email", $email)
            ->where("isDeleted", 0)
            ->first();

        if ($existingUser) {
            $userId = $existingUser['id'];

            // Check if already mapped
            $existingMapping = $this->userhospitalMapping
                ->where("user_id", $userId)
                ->where("hospital_id", $hospital_id)
                ->where("role", "1")
                ->where("isDeleted", 0)
                ->first();

            if ($existingMapping) {
                return $this->respond([
                    "status" => false,
                    "message" => "Doctor already exists for this hospital"
                ]);
            }

            
            $mappingData = [
                "user_id" => $userId,
                "hospital_id" => $hospital_id,
                "role" => "1",
                "created_by" => $loggedUserId
            ];
            $this->userhospitalMapping->insert($mappingData);

            return $this->respond([
                "status" => true,
                "message" => "Doctor assigned to hospital successfully"
            ]);
        }

        
        $this->db->transBegin();

        $userData = [
            "name" => $name,
            "email" => $email,
            "gender" => $gender,
            "expertise" => $expertise, 
            "password" => password_hash($password, PASSWORD_BCRYPT),
            "created_by" => $loggedUserId,
            "updated_by" => $loggedUserId
        ];

        $userId = $this->userModel->insert($userData);

        if (!$userId) {
            $this->db->transRollback();
            return $this->respond(["status" => false, "message" => "User creation failed"]);
        }

        $mappingData = [
            "user_id" => $userId,
            "hospital_id" => $hospital_id,
            "role" => "1",
            "created_by" => $loggedUserId
        ];

        if (!$this->userhospitalMapping->insert($mappingData)) {
            $this->db->transRollback();
            return $this->respond(["status" => false, "message" => "Mapping creation failed"]);
        }

        $this->db->transCommit();

        return $this->respond([
            "status" => true,
            "message" => "Doctor added successfully",
            "doctor_id" => $userId
        ], 201);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "error" => $e->getMessage()
        ], 500);
    }
}


public function addPatient()//Done
 {
    $validationRules = [
        "name" => "required",
        "gender" => "required",
        "email" => "required|valid_email",
        "password" => "required|min_length[3]"
    ];

    if (!$this->validate($validationRules)) {
        return $this->respond([
            "status" => false,
            "message" => "Validation error",
            "error" => $this->validator->getErrors(),
        ], 400);
    }

    $name = $this->request->getVar("name");
    $email = $this->request->getVar("email");
    $password = password_hash($this->request->getVar("password"), PASSWORD_DEFAULT);
    $gender = $this->request->getVar("gender");

    // Patient Role
    $role = 2;

    // logged in user ID from JWT
    $createdBy = $this->request->user_id ?? null;

    // Check if user already exists
    $checkUser = $this->userModel->where("email", $email)->first();
    if ($checkUser) {
        return $this->respond([
            "status" => false,
            "message" => "Patient already exists with this email",
        ], 409);
    }

    $this->db->transStart();

    // Insert into Users table
    $userData = [
        "name" => $name,
        "email" => $email,
        "password" => $password,
        "gender" => $gender,
        "created_by" => $createdBy,
    ];

    $userId = $this->userModel->insert($userData);

    if (!$userId) {
        $this->db->transRollback();
        return $this->respond([
            "status" => false,
            "message" => "Failed to create patient",
        ], 500);
    }

    // Insert into User-Hospital Mapping table with no hospital_id yet
    $mapping = [
        "user_id" => $userId,
        "role" => $role,
        "created_by" => $createdBy,
    ];

    $mapResult = $this->userhospitalMapping->insert($mapping);

    if (!$mapResult) {
        $this->db->transRollback();
        return $this->respond([
            "status" => false,
            "message" => "Failed to create patient role mapping",
        ], 500);
    }

    $this->db->transComplete();

    return $this->respond([
        "status" => true,
        "message" => "Patient registered successfully",
        "patientId" => $userId
    ], 201);
}



public function ListAdmins()
{
    try {
        $loggedInUserId = $this->request->id;
        $loggedUserRole = $this->request->role ?? null;
        $loggedHospitalId = $this->request->hospital_id ?? null;

        if (!$loggedInUserId || $loggedUserRole === null) {
            return $this->respond([
                "status" => false,
                "message" => "Unauthorized user"
            ], 401);
        }

        // Role validation → Only Admin (0) & SuperAdmin (3)
        if (!in_array((int)$loggedUserRole, [0, 3])) {
            return $this->respond([
                "status" => false,
                "message" => "Access denied"
            ], 403);
        }

        $filterHospitalId = $this->request->getVar("hospital_id");

        $builder = $this->userModel->select(
            "users.id, users.name, users.email, users.phone_no, users.gender,
             h.name as hospital_name, h.id as hospital_id"
        )
        ->join("user_hospital_mapping as uhm", "uhm.user_id = users.id")
        ->join("hospitals as h", "h.id = uhm.hospital_id")
        ->where("uhm.role", "0") 
        ->where("users.isDeleted", 0)
        ->where("uhm.deleted_at", null);

        if ((int)$loggedUserRole === 0) {
            // Admin → Must have hospital_id in token
            if (!$loggedHospitalId) {
                return $this->respond([
                    "status" => false,
                    "message" => "Please select a hospital first"
                ], 400);
            }
            $builder->where("uhm.hospital_id", $loggedHospitalId);
        } 
        else if ((int)$loggedUserRole === 3) {
            // SuperAdmin → Optional filter
            if (!empty($filterHospitalId)) {
                $builder->where("uhm.hospital_id", $filterHospitalId);
            }
        }

        $admins = $builder->findAll();

        return $this->respond([
            "status" => true,
            "message" => "Successfully fetched admin list",
            "count" => count($admins),
            "data" => $admins
        ]);
    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "error" => $e->getMessage()
        ], 500);
    }
}




public function ListDoctors()
{
    try {
        $loggedInUserId = $this->request->id;
        $loggedUserRole = $this->request->role ?? null;
        $loggedHospitalId = $this->request->hospital_id ?? null;

        // Optional filter (for SA & Patient)
        $filterHospitalId = $this->request->getVar("hospital_id");

        $builder = $this->userModel->select(
            "users.id, users.name, users.email, users.phone_no, users.gender,
             users.expertise , h.name as hospital_name, h.id as hospital_id"
        )
        ->join("user_hospital_mapping as uhm", "uhm.user_id = users.id")
        ->join("hospitals as h", "h.id = uhm.hospital_id")
        ->where("uhm.role", "1") // Doctor
        ->where("users.isDeleted", 0)
        ->where("uhm.deleted_at", null);

        
        if (in_array((int)$loggedUserRole, [0 , 1])) {
            // Admin / Doctor → must have hospital_id in token
            if (!$loggedHospitalId) {
                return $this->respond([
                    "status" => false,
                    "message" => "Please select a hospital first"
                ], 400);
            }

            $builder->where("uhm.hospital_id", $loggedHospitalId);
        } 
        else if (in_array((int)$loggedUserRole, [2, 3])) {
            // Patient / SuperAdmin → Optional filter
            if (!empty($filterHospitalId)) {
                $builder->where("uhm.hospital_id", $filterHospitalId);
            }
        }
        
        else if (!$loggedInUserId || $loggedUserRole === null) {
            if (!empty($filterHospitalId)) {
                $builder->where("uhm.hospital_id", $filterHospitalId);
            }
        }

        $doctors = $builder->findAll();

        return $this->respond([
            "status" => true,
            "message" => "Fetched doctors list successfully",
            "count" => count($doctors),
            "data" => $doctors
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "error" => $e->getMessage()
        ], 500);
    }
}


public function ListPatients()
{
    try {
        $loggedInUserId = $this->request->id;
        $loggedUserRole = $this->request->role ?? null;
        $loggedHospitalId = $this->request->hospital_id ?? null;
        $filterHospitalId = $this->request->getVar("hospital_id");

        if (!$loggedInUserId || $loggedUserRole === null) {
            return $this->respond([
                "status" => false,
                "message" => "Unauthorized access"
            ], 401);
        }

        $builder = $this->userModel->select(
            "users.id, users.name, users.email, users.phone_no,
             users.gender, a.hospital_id, h.name as hospital_name"
        )
        ->join("appointments as a", "a.patient_id = users.id", "left")
        ->join("hospitals as h", "h.id = a.hospital_id", "left")
        ->join("user_hospital_mapping as uhm", "uhm.user_id = users.id")
        ->where("uhm.role", "2")
        ->where("users.isDeleted", 0)
        ->where("(a.deleted_at IS NULL OR a.deleted_at IS NOT NULL)")
        ->groupBy("users.id");

        switch ($loggedUserRole)
        {
            case "3": // SuperAdmin
                if (!empty($filterHospitalId)) {
                    $builder->where("a.hospital_id", $filterHospitalId);
                }
                break;

            case "0": // Admin
            case "1": // Doctor
                if (!$loggedHospitalId) {
                    return $this->respond([
                        "status" => false,
                        "message" => "No hospital assigned for this user"
                    ], 400);
                }
                $builder->where("a.hospital_id", $loggedHospitalId);
                break;

            case "2": // Patient
                $builder->where("users.id", $loggedInUserId);
                break;

            default:
                return $this->respond([
                    "status" => false,
                    "message" => "Access denied"
                ], 403);
        }

        $patients = $builder->findAll();

        return $this->respond([
            "status" => true,
            "message" => "Successfully fetched patient list",
            "count" => count($patients),
            "data" => $patients
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "error" => $e->getMessage()
        ], 500);
    }
}


public function editDoctor()
    {
        try {
            $loggedRole = $this->request->role;
            $hospitalId = $this->request->hospital_id;
            $doctorId = $this->request->getVar("doctorId");

            if (!$this->validateDoctorOwnership($doctorId, $loggedRole, $hospitalId)) {
                return $this->failForbidden("Permission denied to edit this doctor");
            }

            $doctor = $this->userModel->find($doctorId);
            if (!$doctor || $doctor['role'] != "1" || $doctor['isDeleted'] == 1) {
                return $this->failNotFound("Doctor not found");
            }

            $data = array_filter([
                "name"      => $this->request->getVar("name"),
                "email"     => $this->request->getVar("email"),
                "gender"    => $this->request->getVar("gender"),
                "expertise" => $this->request->getVar("expertise"),
                "phone_no"  => $this->request->getVar("phone_no"),
            ]);

            $this->userModel->update($doctorId, $data);

            return $this->respond([
                "status" => true,
                "message" => "Doctor updated successfully"
            ]);

        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

   
public function deleteDoctor()
    {
        try {
            $loggedRole = $this->request->role;
            $hospitalId = $this->request->hospital_id;
            $doctorId = $this->request->getVar("doctorId");

            if (!$this->validateDoctorOwnership($doctorId, $loggedRole, $hospitalId)) {
                return $this->failForbidden("Permission denied to delete this doctor");
            }

            $this->userModel->update($doctorId, ["isDeleted" => 1]);

            return $this->respond([
                "status" => true,
                "message" => "Doctor deleted successfully"
            ]);

        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

   
public function editPatient()
    {
        try {
            $loggedRole = $this->request->role;
            $hospitalId = $this->request->hospital_id;
            $patientId = $this->request->getVar("patientId");

            if (!$this->validatePatientOwnership($patientId, $loggedRole, $hospitalId)) {
                return $this->failForbidden("Permission denied to edit this patient");
            }

            $patient = $this->userModel->find($patientId);

            if (!$patient || $patient['role'] != "2" || $patient['isDeleted'] == 1) {
                return $this->failNotFound("Patient not found");
            }

            $data = array_filter([
                "name"    => $this->request->getVar("name"),
                "email"   => $this->request->getVar("email"),
                "gender"  => $this->request->getVar("gender"),
                "problem" => $this->request->getVar("problem"),
                "phone_no" => $this->request->getVar("phone_no"),
            ]);

            $this->userModel->update($patientId, $data);

            return $this->respond([
                "status" => true,
                "message" => "Patient updated successfully"
            ]);

        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    
public function deletePatient()
    {
        try {
            $loggedRole = $this->request->role;
            $hospitalId = $this->request->hospital_id;
            $patientId = $this->request->getVar("patientId");

            if (!$this->validatePatientOwnership($patientId, $loggedRole, $hospitalId)) {
                return $this->failForbidden("Permission denied to delete this patient");
            }

            $this->userModel->update($patientId, ["isDeleted" => 1]);

            return $this->respond([
                "status" => true,
                "message" => "Patient deleted successfully"
            ]);

        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }



public function getUser()
{
    try {
        $loggedInUserId = $this->request->id;

        if (!$loggedInUserId) {
            return $this->respond([
                "status" => false,
                "message" => "Unauthorized access"
            ], 401);
        }

        $user = $this->userModel
            ->select("id, name, email, phone_no, gender, expertise, created_at, updated_at")
            ->find($loggedInUserId);

        if (!$user) {
            return $this->respond([
                "status" => false,
                "message" => "User profile not found"
            ]);
        }

        return $this->respond([
            "status" => true,
            "message" => "Profile fetched successfully",
            "data" => $user
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "message" => "Error fetching profile",
            "error" => $e->getMessage()
        ], 500);
    }
}

public function updateProfile()
{
    try {
       
        $loggedInUserId = $this->request->id;
        $loggedUserRole = $this->request->role;

        if (!$loggedInUserId) {
            return $this->respond([
                "status" => false,
                "message" => "Unauthorized"
            ], 401);
        }
  
       
        $updateData = [
            "name"       => $this->request->getVar("name") ?? $this->request->userData->user->name,
            "email"      => $this->request->getVar("email") ?? $this->request->userData->user->email,
            "gender"     => $this->request->getVar("gender") ?? $this->request->userData->user->gender,
            "updated_by" => $loggedInUserId
        ];

        // If password is given → encrypt
        if (!empty($this->request->getVar("password"))) {
            $updateData["password"] = password_hash(
                $this->request->getVar("password"),
                PASSWORD_BCRYPT
            );
        }

        
        if ($loggedUserRole == "1" && $this->request->getVar("expertise")) {
            $updateData["expertise"] = $this->request->getVar("expertise") ?? $this->request->userData->user->expertise;
        }

        $this->userModel->update($loggedInUserId, $updateData);

        $updatedUser = $this->userModel
            ->select("id, name, email, phone_no, gender, expertise, updated_at")
            ->find($loggedInUserId);

        return $this->respond([
            "status" => true,
            "message" => "Profile updated successfully",
            "data" => $updatedUser
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "message" => "Error updating profile",
            "error" => $e->getMessage()
        ], 500);
    }
}


public function stats()
{
    try {
        $loggedUserRole = (int)$this->request->role;
        

        // SUPERADMIN → Full system stats
        if ($loggedUserRole === 3) {

            // Count Doctors → role 1, active
            $doctorsCount = $this->userModel
                ->join("user_hospital_mapping uhm", "uhm.user_id = users.id")
                ->where("uhm.role", "1")
                ->where("users.deleted_at", null)
                ->where("uhm.deleted_at", null)
                ->countAllResults();

            // Count Unique Patients → via appointments
            $result = $this->db->table('appointments')
                ->select('COUNT(DISTINCT patient_id) AS total_patients')
                ->where('deleted_at', null)
                ->get()
                ->getRow();

            $patientsCount = (int)$result->total_patients;

            $hospitalsCount = $this->hospitalModel
                ->where("deleted_at", null)
                ->countAllResults();

            $appointmentsCount = $this->appointmentModel
                ->where('status', 'booked')
                ->where("deleted_at", null)
                ->countAllResults();

            return $this->respond([
                'status' => true,
                'message' => 'SuperAdmin statistics fetched successfully',
                'doctors' => $doctorsCount,
                'patients' => $patientsCount,
                'appointments' => $appointmentsCount,
                'hospitals' => $hospitalsCount
            ]);
        }


        // DOCTOR / ADMIN → Hospital-specific stats
        $loggedHospitalId = $this->request->hospital_id;
        if (!$loggedHospitalId) {
            return $this->respond([
                "status" => false,
                "message" => "Hospital not assigned for this user"
            ], 403);
        }

        $doctorsCount = $this->userModel
            ->join("user_hospital_mapping uhm", "uhm.user_id = users.id")
            ->where("uhm.role", "1")
            ->where("uhm.hospital_id", $loggedHospitalId)
            ->where("uhm.deleted_at", null)
            ->where("users.deleted_at", null)
            ->countAllResults();

        $patientsQuery = $this->db->table('appointments')
            ->select('COUNT(DISTINCT patient_id) AS total_patients')
            ->where('hospital_id', $loggedHospitalId)
            ->where('deleted_at', null)
            ->get()
            ->getRow();

        $patientsCount = (int)$patientsQuery->total_patients;

        $appointmentsCount = $this->appointmentModel
            ->where('status', 'booked')
            ->where('hospital_id', $loggedHospitalId)
            ->where("deleted_at", null)
            ->countAllResults();

        return $this->respond([
            'status' => true,
            'message' => 'Hospital wise statistics fetched successfully',
            'hospital_id' => $loggedHospitalId,
            'doctors' => $doctorsCount,
            'patients' => $patientsCount,
            'appointments' => $appointmentsCount
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "message" => "Error fetching stats",
            "error" => $e->getMessage()
        ], 500);
    }
}


// For - displaying patient profile in top left corner
public function getDetailsforPatient()
{
    try{

       $patientId = $this->request->getVar('patientId');

        if (!$patientId) 
        {
            $userData = $this->request->userData;
            $patientId = $userData->user->id;
        }

        $userDetails = $this->userModel->find($patientId);


       $data = [
        "name" => $userDetails['name'],
        "gender" => $userDetails['gender'],
        "DOB" => "12-08-2003",
        "email" => $userDetails['email'],
        "photo" => "/assets/images/dummyProfile.png"
       ];

       return $this->respond([
        "status" => true,
        "Mssge" => "Successfully fetched all the Basic Details",
        "data" => $data,
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


}
