<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? null;
$label = trim($data['label'] ?? '');
$full_name = trim($data['full_name'] ?? '');
$phone = trim($data['phone'] ?? '');
$address = trim($data['address'] ?? '');
$city = trim($data['city'] ?? '');
$pincode = trim($data['pincode'] ?? '');
$is_default = isset($data['is_default']) ? (int)$data['is_default'] : 0;

if (empty($user_id) || $label === '' || $full_name === '' || $address === '' || $city === '' || $pincode === '') {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

// Auto default for first address if not provided.
if ($is_default === 0) {
    $countRes = $conn->query("SELECT COUNT(*) c FROM user_addresses WHERE user_id=" . (int)$user_id);
    if ($countRes && (int)$countRes->fetch_assoc()['c'] === 0) {
        $is_default = 1;
    }
}

if ($is_default === 1) {
    $conn->query("UPDATE user_addresses SET is_default=0 WHERE user_id=" . (int)$user_id);
}

$stmt = $conn->prepare(
    "INSERT INTO user_addresses
     (user_id,label,full_name,phone,address,city,pincode,is_default)
     VALUES (?,?,?,?,?,?,?,?)"
);

$stmt->bind_param(
    "issssssi",
    $user_id,
    $label,
    $full_name,
    $phone,
    $address,
    $city,
    $pincode,
    $is_default
);

$stmt->execute();

echo json_encode(["success"=>true,"message"=>"Address added","id"=>$stmt->insert_id]);
