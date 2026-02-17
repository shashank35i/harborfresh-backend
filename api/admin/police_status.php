<?php
require_once __DIR__ . "/../utils.php";

$body = get_json_body();
$admin = require_admin($conn, $body);

$sellerId = isset($body['seller_id']) ? (int)$body['seller_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : null);
$status   = isset($body['status']) ? $body['status'] : null;

if (!$sellerId || !$status) {
    json_response(["success" => false, "message" => "Seller id and status are required"], 422);
}

$allowed = ['pending', 'verified', 'rejected'];
if (!in_array($status, $allowed, true)) {
    json_response(["success" => false, "message" => "Invalid status value"], 422);
}

$sellerCheck = $conn->prepare("SELECT id FROM sellers WHERE id = ? LIMIT 1");
$sellerCheck->bind_param("i", $sellerId);
$sellerCheck->execute();
if ($sellerCheck->get_result()->num_rows === 0) {
    json_response(["success" => false, "message" => "Seller not found"], 404);
}

$stmt = $conn->prepare(
    "INSERT INTO seller_identity_verification (seller_id, police_verification_status)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE
        police_verification_status = VALUES(police_verification_status)"
);
$stmt->bind_param("is", $sellerId, $status);
$stmt->execute();

log_step($conn, $sellerId, 7, 'admin_update', ["police_verification_status" => $status, "admin_id" => $admin['id']]);

json_response([
    "success" => true,
    "message" => "Police verification status updated",
    "police_verification_status" => $status
]);
