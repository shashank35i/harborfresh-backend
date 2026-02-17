<?php
header("Content-Type: application/json");

// Safe include
require_once __DIR__ . "/../db.php";

// Validate input
if (empty($_POST['seller_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Seller ID is required"
    ]);
    exit;
}

$seller_id = (int)$_POST['seller_id'];

// Check seller exists
$stmt = $conn->prepare(
    "SELECT status FROM sellers WHERE id=?"
);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Seller not found"
    ]);
    exit;
}

$status = $result->fetch_assoc()['status'];

// Only submitted sellers can be approved
if ($status !== 'submitted') {
    echo json_encode([
        "success" => false,
        "message" => "Seller not ready for approval"
    ]);
    exit;
}

// Approve seller
$stmt = $conn->prepare(
    "UPDATE sellers
     SET status='approved', is_verified=1
     WHERE id=?"
);
$stmt->bind_param("i", $seller_id);
$stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "Seller approved successfully"
]);
