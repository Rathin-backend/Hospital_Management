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

public function register()
{
    $validationRules = [
        "name" => "required|min_length[3]",
        "gender" => "required",
        "email" => "required|min_length[3]|valid_email",
        "password" => "required|min_length[3]",
        "phone_no" => "required"
    ];


    if(!$this->validate($validationRules)) {
        return $this->respond([
            "status" => false,
            "error" => $this->validator->getErrors()
        ]);
    }


    $name = $this->request->getVar("name");
    $email = $this->request->getVar("email");
    $gender = $this->request->getVar("gender");
    $password = password_hash($this->request->getVar("password"), PASSWORD_BCRYPT);
    $phone_no = $this->request->getVar("phone_no");

    
    $this->db->transStart();
                              
    // Insert into users table
    $userData = [
        "name" => $name,
        "email" => $email,
        "gender" => $gender,
        "password" => $password,
        "phone_no" => $phone_no,
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

    // Insert into user_mapping as PATIENT role (role = 2)
    $mappingData = [
        "user_id" => $userId,
        "hospital_id" => null, // Patient may not belong to any hospital
        "role" => 2,
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
        "status" => false,
        "message" => "Registration failed",
        "error" => $this->db->error()
    ], 500);
}

    return $this->respond([
        "status" => true,
        "message" => "Registered Successfully",
        "user_id" => $userId
    ]);
}



public function login()
{
    $validationRules = [
        "email" => "required|valid_email",
        "password" => "required|min_length[3]"
    ];

    if (!$this->validate($validationRules)) {
        return $this->respond([
            "status" => false,
            "message" => "Validation failed",
            "errors" => $this->validator->getErrors()
        ]);
    }

    $email = $this->request->getVar("email");
    $password = $this->request->getVar("password");

    $user = $this->userModel
        ->where("email", $email)
        ->where("isDeleted", 0)
        ->first();

    if (!$user) {
        return $this->respond([
            "status" => false,
            "message" => "Invalid email or password"
        ]);
    }

    
    if (!password_verify($password, $user["password"])) {
        return $this->respond([
            "status" => false,
            "message" => "Invalid email or password"
        ]);
    }

    
    $mappings = $this->userhospitalMapping
                ->select("hospital_id, role")
                ->where("user_id", $user["id"])
                ->findAll();

    // JWT Data
    $payload = [
        "iss" => "localhost",
        "aud" => "localhost",
        "iat" => time(),
        "exp" => time() + 3600,
        "user" => [
            "id" => $user["id"],
            "email" => $user["email"],
            "name" => $user["name"],
            "gender" => $user["gender"]
        ],
        "hospitals" => $mappings 
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
        "hospitals" => $mappings 
    ]);
}



   
}


