<?php
require_once __DIR__ . "/../../utils.php";

$body = get_json_body();
$seller = require_seller($conn, $body);
$sellerId = (int)$seller['id'];

$check = $conn->prepare(
    "SELECT aadhaar_verified, liveness_verified, face_match_verified, police_verification_status
     FROM seller_identity_verification WHERE seller_id = ? LIMIT 1"
);
$check->bind_param("i", $sellerId);
$check->execute();
$row = $check->get_result()->fetch_assoc();

if (
    empty($row) ||
    !$row['aadhaar_verified'] ||
    !$row['liveness_verified'] ||
    !$row['face_match_verified']
) {
    json_response(["success" => false, "message" => "Complete Aadhaar, selfie and face match first"], 422);
}

$conn->query(
    "UPDATE seller_identity_verification
     SET verification_status = 'submitted'
     WHERE seller_id = {$sellerId}"
);

$conn->query(
    "UPDATE sellers
     SET status = 'submitted', verification_step = GREATEST(verification_step, 8), updated_at = NOW()
     WHERE id = {$sellerId}"
);

record_step($conn, $sellerId, 8, ["status" => "submitted"]);

json_response([
    "success" => true,
    "message" => "Verification submitted",
    "verification_step" => 8,
    "status" => "submitted"
]);
