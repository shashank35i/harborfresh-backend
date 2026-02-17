<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (
    empty($data['seller_id']) ||
    empty($data['match_score'])
) {
    echo json_encode(["success"=>false,"message"=>"Face match data required"]);
    exit;
}

$seller_id = (int)$data['seller_id'];
$score = (float)$data['match_score'];

$verified = ($score >= 80) ? 1 : 0;

$row = $conn->query(
    "SELECT id FROM seller_identity_verification WHERE seller_id=$seller_id ORDER BY id DESC LIMIT 1"
)->fetch_assoc();

if ($row) {
    $conn->query(
        "UPDATE seller_identity_verification
         SET face_match_score=$score,
             face_match_verified=$verified
         WHERE id={$row['id']}"
    );
} else {
    $conn->query(
        "INSERT INTO seller_identity_verification (seller_id, face_match_score, face_match_verified, police_verification_status)
         VALUES ($seller_id, $score, $verified, 'pending')"
    );
}

echo json_encode([
    "success"=>true,
    "verified"=>$verified,
    "message"=>$verified ? "Face match successful" : "Face match failed"
]);
