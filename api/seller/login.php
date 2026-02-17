<?php
require_once __DIR__ . "/../utils.php";

$body = get_json_body();

$email    = isset($body['business_email']) ? clean($body['business_email']) : (isset($body['email']) ? clean($body['email']) : null);
$password = $body['password'] ?? null;

if (!$email || !$password) {
    json_response(["success" => false, "message" => "Email and password required"], 422);
}

$stmt = $conn->prepare(
    "SELECT id, password, status, verification_step, full_name, phone, business_email, is_verified
     FROM sellers WHERE business_email = ? LIMIT 1"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    json_response(["success" => false, "message" => "Seller not found"], 200);
}

$seller = $res->fetch_assoc();

if (!password_verify($password, $seller['password'])) {
    json_response(["success" => false, "message" => "Invalid password"], 200);
}

json_response([
    "success" => true,
    "message" => "Login successful",
    "seller_id" => (int)$seller['id'],
    "status" => $seller['status'],
    "verification_step" => (int)$seller['verification_step'],
    "seller" => [
        "full_name" => $seller['full_name'],
        "business_email" => $seller['business_email'],
        "phone" => $seller['phone'],
        "is_verified" => (int)$seller['is_verified']
    ]
]);
