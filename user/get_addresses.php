<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (empty($_GET['user_id'])) {
    echo json_encode(["success"=>false,"message"=>"user_id required"]);
    exit;
}
$user_id = (int)$_GET['user_id'];

$res = $conn->query(
    "SELECT * FROM user_addresses WHERE user_id=$user_id ORDER BY is_default DESC, id DESC"
);

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(["success"=>true,"addresses"=>$data]);
