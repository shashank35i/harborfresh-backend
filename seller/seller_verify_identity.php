<?php
include "../db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['seller_id'])) {
    echo json_encode(["success"=>false,"message"=>"Seller ID required"]);
    exit;
}

$conn->query(
 "INSERT INTO seller_verification
 (seller_id,aadhaar_verified,face_match_verified,live_selfie_verified)
 VALUES ({$data['seller_id']},1,1,1)"
);

$conn->query(
 "UPDATE sellers SET status='submitted' WHERE id={$data['seller_id']}"
);

echo json_encode([
 "success"=>true,
 "message"=>"Verification submitted for approval"
]);
?>
