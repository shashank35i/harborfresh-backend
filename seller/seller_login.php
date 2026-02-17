<?php
include "../db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['email']) || empty($data['password'])) {
    echo json_encode(["success"=>false,"message"=>"Email & password required"]);
    exit;
}

$stmt = $conn->prepare(
 "SELECT id,password,status FROM sellers WHERE business_email=?"
);
$stmt->bind_param("s",$data['email']);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode(["success"=>false,"message"=>"Seller not found"]);
    exit;
}

$seller = $res->fetch_assoc();

if (!password_verify($data['password'],$seller['password'])) {
    echo json_encode(["success"=>false,"message"=>"Invalid password"]);
    exit;
}

if ($seller['status'] != 'approved') {
    echo json_encode(["success"=>false,"message"=>"Account not approved"]);
    exit;
}

echo json_encode([
 "success"=>true,
 "seller_id"=>$seller['id'],
 "message"=>"Login successful"
]);
?>
