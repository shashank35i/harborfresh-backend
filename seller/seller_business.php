<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/helpers.php";

$data = json_decode(file_get_contents("php://input"), true);

// Required validation
if (
    empty($data['seller_id']) ||
    empty($data['business_name']) ||
    empty($data['location']) ||
    empty($data['experience']) ||
    empty($data['specialty'])
) {
    echo json_encode([
        "success" => false,
        "message" => "All fields are required"
    ]);
    exit;
}

// Assign to VARIABLES (FIX)
$seller_id     = (int)$data['seller_id'];
$business_name = clean($data['business_name']);
$location      = clean($data['location']);
$experience    = (int)$data['experience'];
$specialty     = clean($data['specialty']);

// Step order validation
$check = $conn->prepare(
    "SELECT status FROM sellers WHERE id=?"
);
$check->bind_param("i", $seller_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid seller ID"
    ]);
    exit;
}

$status = $result->fetch_assoc()['status'];

if ($status !== 'draft') {
    echo json_encode([
        "success" => false,
        "message" => "Invalid step order"
    ]);
    exit;
}

// Insert business details
$stmt = $conn->prepare(
    "INSERT INTO seller_business
     (seller_id, business_name, location, experience_years, specialty)
     VALUES (?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "issis",
    $seller_id,
    $business_name,
    $location,
    $experience,
    $specialty
);

$stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "Business details saved"
]);
