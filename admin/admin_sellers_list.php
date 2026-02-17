<?php
header("Content-Type: application/json");
require_once "../db.php";

$result = $conn->query("
    SELECT 
        id,
        full_name,
        business_email,
        phone,
        status,
        is_verified,
        created_at
    FROM sellers
    ORDER BY created_at DESC
");

$sellers = [];

while ($row = $result->fetch_assoc()) {
    $sellers[] = [
        "seller_id" => $row['id'],
        "name" => $row['full_name'],
        "email" => $row['business_email'],
        "phone" => $row['phone'],
        "status" => $row['status'],
        "verified" => (bool)$row['is_verified'],
        "created_at" => $row['created_at']
    ];
}

echo json_encode([
    "success" => true,
    "total" => count($sellers),
    "sellers" => $sellers
]);
