<?php

namespace App\Controllers;

use App\Models\UserModel as ModelsUserModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use App\Models\UserHospitalMappingModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\TextUI\XmlConfiguration\Validator;

class LoginController extends ResourceController
{
    private $userModel;
    private $userhospitalMapping;
    private $db;

    public function __construct()
    {
        $this->db = db_connect();
        $this->userModel = new UserModel();
        $this->userhospitalMapping = new UserHospitalMappingModel();
    }


public function listHospitals()
{
    try{
      $userId = $this->request->id; // Set from middleware

    $mappings = $this->userhospitalMapping
        ->select("user_hospital_mapping.id as mapping_id, hospitals.id as hospital_id, hospitals.name as hospital_name, user_hospital_mapping.role")
        ->join("hospitals", "hospitals.id = user_hospital_mapping.hospital_id", "left")
        ->where("user_hospital_mapping.user_id", $userId)
        ->where("user_hospital_mapping.isDeleted", 0)
        ->findAll();

    return $this->respond([
        "status" => true,
        "hospitals" => $mappings
    ]);
    }catch(\Exception $e)
    {
        return $this->respond([
            "status" => false,
            "Error" => $e->getMessage(),
        ]);
    }
    
}


public function setActiveHospital($mappingId)
 {
    try{
    $userId = $this->request->id;

    $userDetails = $this->request->userData;
   

    $mapping = $this->userhospitalMapping
        ->where("id", $mappingId)
        ->where("user_id", $userId)
        ->where("isDeleted", 0)
        ->first();

    if (!$mapping) {
        return $this->respond([
            "status" => false,
            "message" => "Invalid mapping selection"
        ], 400);
    }

    // Create new token with selected hospital + role
    $payload = [
        "iss" => "localhost",
        "aud" => "localhost",
        "iat" => time(),
        "exp" => time() + 3600,
        "user" => [
            "id" => $userId,
            "name" => $userDetails->user->name,
            "email" => $userDetails->user->email,
            "hospital_id" => $mapping["hospital_id"],
            "role" => $mapping["role"]
        ]
    ];

    $token = JWT::encode($payload, getenv("JWT_KEY"), 'HS256');

    return $this->respond([
        "status" => true,
        "message" => "Active hospital set successfully",
        "token" => $token,
        "user" => [
            "id" => $userId,
            "name" => $userDetails->user->name,
            "email" => $userDetails->user->email, 
            "hospital_id" => $mapping["hospital_id"],
            "role" => $mapping["role"]
        ]
    ]);
    }catch(\Exception $e)
    {
        return $this->respond([
            "status" => false,
            "Error" => $e->getMessage()
        ]);
    }

}
    


public function register()
{
    try {
        $data = $this->request->getJSON(true); // ✅ Get JSON as array

        $validationRules = [
            "name" => "required|min_length[3]",
            "gender" => "required",
            "email" => "required|min_length[3]|valid_email",
            "password" => "required|min_length[3]",
            "phone_no" => "required"
        ];

        // ✅ Validate JSON input
        if (!$this->validateData($data, $validationRules)) {
            return $this->respond([
                "status" => false,
                "error" => $this->validator->getErrors()
            ]);
        }

        $this->db->transStart();

        $userData = [
            "name" => $data["name"],
            "email" => $data["email"],
            "gender" => $data["gender"],
            "password" => password_hash($data["password"], PASSWORD_BCRYPT),
            "phone_no" => $data["phone_no"],
            "created_by" => null
        ];

        $this->userModel->insert($userData);

        if ($this->db->error()['message']) {
            return $this->respond([
                "status" => false,
                "step" => "user insert",
                "error" => $this->db->error()
            ]);
        }

        $userId = $this->db->insertID();

        // Map as PATIENT
        $mappingData = [
            "user_id" => $userId,
            "hospital_id" => null,
            "role" => 2, // Patient role
            "created_by" => $userId
        ];

        $this->userhospitalMapping->insert($mappingData);

        if ($this->db->error()['message']) {
            return $this->respond([
                "status" => false,
                "step" => "mapping insert",
                "error" => $this->db->error()
            ]);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return $this->respond([
                "status"  => false,
                "message" => "Registration failed",
                "error"   => $this->db->error()
            ], 500);
        }

        return $this->respond([
            "status" => true,
            "message" => "Registered Successfully",
            "user_id" => $userId
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "Error" => $e->getMessage()
        ]);
    }
}


public function login()
{
    try {

        $data = $this->request->getJSON(true); 
        
        $validationRules = [
            "email" => "required|valid_email",
            "password" => "required|min_length[3]"
        ];

        
        if (!$this->validateData($data, $validationRules)) {
            return $this->respond([
                "status" => false,
                "message" => "Validation failed",
                "errors" => $this->validator->getErrors()
            ]);
        }

        $email = $data['email'];
        $password = $data['password'];

        $user = $this->userModel
            ->where("email", $email)
            ->where("isDeleted", 0)
            ->first();

        if (!$user || !password_verify($password, $user["password"])) {
            return $this->respond([
                "status" => false,
                "message" => "Invalid email or password"
            ]);
        }

        $payload = [
            "iss" => "localhost",
            "aud" => "localhost",
            "iat" => time(),
            "exp" => time() + 3600,
            "user" => [
                "id" => $user["id"],
                "email" => $user["email"],
                "name" => $user["name"],
                "gender" => $user["gender"],
            ],
        ];

        $token = JWT::encode($payload, getenv("JWT_KEY"), 'HS256');

        return $this->respond([
            "status" => true,
            "message" => "Login successful",
            "token" => $token,
            "user" => [
                "id" => $user["id"],
                "name" => $user["name"],
                "email" => $user["email"]
            ],
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "Mssge" => $e->getMessage()
        ]);
    }
}


public function PatientandSuperAdminlogin()
{
    try {

        $data = $this->request->getJSON(true); 

        $validationRules = [
            "email" => "required|valid_email",
            "password" => "required|min_length[3]"
        ];

        
        if (!$this->validateData($data, $validationRules)) {
            return $this->respond([
                "status" => false,
                "message" => "Validation failed",
                "errors" => $this->validator->getErrors()
            ]);
        }

        $email = $data["email"];
        $password = $data["password"];

        $user = $this->userModel
            ->where("email", $email)
            ->where("isDeleted", 0)
            ->first();

        if (!$user || !password_verify($password, $user["password"])) {
            return $this->respond([
                "status" => false,
                "message" => "Invalid email or password"
            ]);
        }

        $mappings = $this->userhospitalMapping
            ->select("hospital_id, role")
            ->where("user_id", $user["id"])
            ->findAll();

        
        $role = $mappings[0]["role"] ?? null;
        $hospitalId = $mappings[0]["hospital_id"] ?? null;

        $payload = [
            "iss" => "localhost",
            "aud" => "localhost",
            "iat" => time(),
            "exp" => time() + 3600,
            "user" => [
                "id" => $user["id"],
                "email" => $user["email"],
                "name" => $user["name"],
                "gender" => $user["gender"],
                "role" => $role,
                "hospital_id" => $hospitalId
            ],
        ];

        $token = JWT::encode($payload, getenv("JWT_KEY"), 'HS256');

        return $this->respond([
            "status" => true,
            "message" => "Login successful",
            "token" => $token,
            "user" => [
                "id" => $user["id"],
                "name" => $user["name"],
                "email" => $user["email"],
                "role" => $role,
                "hospital_id" => $hospitalId
            ],
        ]);

    } catch (\Exception $e) {
        return $this->respond([
            "status" => false,
            "Error" => $e->getMessage()
        ]);
    }
}

}


