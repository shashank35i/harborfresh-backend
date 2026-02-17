<?php
require_once __DIR__ . "/../utils.php";

$body = get_json_body();
$admin = require_admin($conn, $body);

$sellerId = isset($body['seller_id']) ? (int)$body['seller_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : null);
$status   = isset($body['status']) ? $body['status'] : null;

if (!$sellerId || !$status) {
    json_response(["success" => false, "message" => "Seller id and status are required"], 422);
}

$allowed = ['approved', 'rejected'];
if (!in_array($status, $allowed, true)) {
    json_response(["success" => false, "message" => "Status must be approved or rejected"], 422);
}

$sellerCheck = $conn->prepare("SELECT id FROM sellers WHERE id = ? LIMIT 1");
$sellerCheck->bind_param("i", $sellerId);
$sellerCheck->execute();
if ($sellerCheck->get_result()->num_rows === 0) {
    json_response(["success" => false, "message" => "Seller not found"], 404);
}

$isVerified = $status === 'approved' ? 1 : 0;

$updateSeller = $conn->prepare(
    "UPDATE sellers
     SET status = ?, is_verified = ?, updated_at = NOW()
     WHERE id = ?"
);
$updateSeller->bind_param("sii", $status, $isVerified, $sellerId);
$updateSeller->execute();

$stmt = $conn->prepare(
    "INSERT INTO seller_identity_verification (seller_id, verification_status)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE verification_status = VALUES(verification_status)"
);
$stmt->bind_param("is", $sellerId, $status);
$stmt->execute();

log_step($conn, $sellerId, 9, 'admin_final', ["status" => $status, "admin_id" => $admin['id']]);

json_response([
    "success" => true,
    "message" => "Seller {$status}",
    "is_verified" => $isVerified
]);
