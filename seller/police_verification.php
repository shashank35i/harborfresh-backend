<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (empty($_POST['seller_id'])) {
    echo json_encode(["success"=>false,"message"=>"Seller ID required"]);
    exit;
}

$seller_id = (int)$_POST['seller_id'];

$conn->query(
    "UPDATE seller_identity_verification
     SET police_verification_status='verified'
     WHERE seller_id=$seller_id"
);

echo json_encode([
    "success"=>true,
    "message"=>"Police verification completed"
]);
