<?php
require_once __DIR__ . "/../utils.php";

$body = get_json_body();

$fullName = isset($body['full_name']) ? clean($body['full_name']) : null;
$email    = isset($body['business_email']) ? clean($body['business_email']) : (isset($body['email']) ? clean($body['email']) : null);
$phone    = isset($body['phone']) ? clean($body['phone']) : null;
$password = $body['password'] ?? null;

if (!$fullName || !$email || !$phone || !$password) {
    json_response(["success" => false, "message" => "Full name, email, phone, password are required"], 422);
}

if (!valid_email($email)) {
    json_response(["success" => false, "message" => "Invalid email"], 422);
}

if (!valid_phone($phone)) {
    json_response(["success" => false, "message" => "Phone must be 10 digits"], 422);
}

if (!valid_password($password)) {
    json_response(["success" => false, "message" => "Password must be at least 8 characters and include numbers"], 422);
}

$check = $conn->prepare("SELECT id FROM sellers WHERE business_email = ?");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    json_response(["success" => false, "message" => "Email already registered"], 409);
}

$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO sellers (full_name, business_email, phone, password, status, verification_step)
     VALUES (?, ?, ?, ?, 'draft', 0)"
);
$stmt->bind_param("ssss", $fullName, $email, $phone, $hashed);

if ($stmt->execute()) {
    json_response([
        "success" => true,
        "message" => "Seller registered successfully",
        "seller_id" => $conn->insert_id,
        "verification_step" => 0,
        "status" => "draft"
    ]);
}

json_response(["success" => false, "message" => "Registration failed"], 500);
