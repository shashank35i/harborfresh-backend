<?php
header("Content-Type: application/json");

// ✅ CORRECT INCLUDES (MATCH YOUR STRUCTURE)
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/helpers.php";

$data = json_decode(file_get_contents("php://input"), true);

// Required fields check
if (
    empty($data['full_name']) ||
    empty($data['email']) ||
    empty($data['phone']) ||
    empty($data['password'])
) {
    echo json_encode([
        "success" => false,
        "message" => "All fields are required"
    ]);
    exit;
}

$full_name = clean($data['full_name']);
$email     = clean($data['email']);
$phone     = clean($data['phone']);
$password  = $data['password'];

// Email validation
if (!valid_email($email)) {
    echo json_encode(["success"=>false,"message"=>"Invalid email"]);
    exit;
}

// Phone validation
if (!valid_phone($phone)) {
    echo json_encode(["success"=>false,"message"=>"Phone must be 10 digits"]);
    exit;
}

// Password validation
if (!valid_password($password)) {
    echo json_encode([
        "success"=>false,
        "message"=>"Password must be at least 8 characters and include numbers"
    ]);
    exit;
}

// Check duplicate seller email
$stmt = $conn->prepare(
    "SELECT id FROM sellers WHERE business_email=?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode([
        "success"=>false,
        "message"=>"Email already registered"
    ]);
    exit;
}
$stmt->close();

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert seller
$stmt = $conn->prepare(
    "INSERT INTO sellers (full_name, business_email, phone, password)
     VALUES (?, ?, ?, ?)"
);
$stmt->bind_param(
    "ssss",
    $full_name,
    $email,
    $phone,
    $hashedPassword
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "seller_id" => $conn->insert_id,
        "message" => "Seller registered successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Registration failed"
    ]);
}
