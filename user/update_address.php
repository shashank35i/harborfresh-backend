<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$user_id = $data['user_id'] ?? null;
$label = trim($data['label'] ?? '');
$full_name = trim($data['full_name'] ?? '');
$phone = trim($data['phone'] ?? '');
$address = trim($data['address'] ?? '');
$city = trim($data['city'] ?? '');
$pincode = trim($data['pincode'] ?? '');
$is_default = isset($data['is_default']) ? (int)$data['is_default'] : 0;

if (empty($id) || empty($user_id)) {
    echo json_encode(["success"=>false,"message"=>"id and user_id required"]);
    exit;
}

if ($is_default === 1) {
    $conn->query("UPDATE user_addresses SET is_default=0 WHERE user_id=" . (int)$user_id);
}

$stmt = $conn->prepare(
    "UPDATE user_addresses
     SET label=?, full_name=?, phone=?, address=?, city=?, pincode=?, is_default=?
     WHERE id=? AND user_id=?"
);

$stmt->bind_param(
    "ssssssiii",
    $label,
    $full_name,
    $phone,
    $address,
    $city,
    $pincode,
    $is_default,
    $id,
    $user_id
);

$stmt->execute();

echo json_encode(["success"=>true,"message"=>"Address updated"]);
