<?php
require_once __DIR__ . "/../utils.php";

$body = get_json_body();
$seller = require_seller($conn, $body);

$license = isset($body['fishing_license']) ? clean($body['fishing_license']) : null;
$gst     = isset($body['gst_number']) ? clean($body['gst_number']) : null;

if (!$license) {
    json_response(["success" => false, "message" => "Fishing license is required"], 422);
}

if (strlen($license) < 6) {
    json_response(["success" => false, "message" => "Invalid fishing license number"], 422);
}

$stmt = $conn->prepare(
    "INSERT INTO seller_legal (seller_id, fishing_license, gst_number)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE
        fishing_license = VALUES(fishing_license),
        gst_number = VALUES(gst_number)"
);
$stmt->bind_param("iss", $seller['id'], $license, $gst);
$stmt->execute();

record_step($conn, (int)$seller['id'], 3, [
    "fishing_license" => $license,
    "gst_number" => $gst
]);

json_response([
    "success" => true,
    "message" => "Legal information saved",
    "verification_step" => 3
]);
