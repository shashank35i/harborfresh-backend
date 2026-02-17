<?php
require_once __DIR__ . "/../utils.php";

$body = get_json_body();
$seller = require_seller($conn, $body);

$fullName = isset($body['full_name']) ? clean($body['full_name']) : null;
$phone    = isset($body['phone']) ? clean($body['phone']) : null;
$email    = isset($body['business_email']) ? clean($body['business_email']) : null;
$password = $body['password'] ?? null;

if (!$fullName || !$phone) {
    json_response(["success" => false, "message" => "Full name and phone are required"], 422);
}

if (!valid_phone($phone)) {
    json_response(["success" => false, "message" => "Phone must be 10 digits"], 422);
}

$emailToUse = $seller['business_email'];
if ($email) {
    if (!valid_email($email)) {
        json_response(["success" => false, "message" => "Invalid email"], 422);
    }
    if ($email !== $seller['business_email']) {
        $check = $conn->prepare("SELECT id FROM sellers WHERE business_email = ? AND id <> ?");
        $check->bind_param("si", $email, $seller['id']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            json_response(["success" => false, "message" => "Email already in use"], 409);
        }
        $emailToUse = $email;
    }
}

$hashed = null;
if ($password) {
    if (!valid_password($password)) {
        json_response(["success" => false, "message" => "Password must be at least 8 characters and include numbers"], 422);
    }
    $hashed = password_hash($password, PASSWORD_DEFAULT);
}

$stmt = $conn->prepare(
    "UPDATE sellers
     SET full_name = ?, phone = ?, business_email = ?, " .
    ($hashed ? "password = ?, " : "") .
    "updated_at = NOW()
     WHERE id = ?"
);

if ($hashed) {
    $stmt->bind_param("ssssi", $fullName, $phone, $emailToUse, $hashed, $seller['id']);
} else {
    $stmt->bind_param("sssi", $fullName, $phone, $emailToUse, $seller['id']);
}

$stmt->execute();

record_step($conn, (int)$seller['id'], 1, ["full_name" => $fullName, "phone" => $phone]);

json_response([
    "success" => true,
    "message" => "Personal information saved",
    "verification_step" => 1
]);
