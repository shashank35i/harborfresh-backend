<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/helpers.php";

$data = json_decode(file_get_contents("php://input"), true);

// Required validation
if (
    empty($data['seller_id']) ||
    empty($data['license'])
) {
    echo json_encode([
        "success" => false,
        "message" => "Seller ID and license number are required"
    ]);
    exit;
}

// Assign to VARIABLES (IMPORTANT FIX)
$seller_id = (int)$data['seller_id'];
$license   = clean($data['license']);
$gst       = isset($data['gst']) ? clean($data['gst']) : null;

// Basic license validation
if (strlen($license) < 6) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid fishing license number"
    ]);
    exit;
}

// Insert legal info
$stmt = $conn->prepare(
    "INSERT INTO seller_legal
     (seller_id, fishing_license, gst_number)
     VALUES (?, ?, ?)"
);

$stmt->bind_param(
    "iss",
    $seller_id,
    $license,
    $gst
);

$stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "Legal info saved"
]);
