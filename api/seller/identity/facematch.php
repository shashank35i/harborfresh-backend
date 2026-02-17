<?php
require_once __DIR__ . "/../../utils.php";

$body = get_json_body();
$seller = require_seller($conn, $body);

$score = isset($body['face_match_score']) ? (float)$body['face_match_score'] : null;
if ($score === null) {
    json_response(["success" => false, "message" => "Face match score required"], 422);
}

$verified = $score >= 85 ? 1 : 0;

$existing = $conn->query("SELECT id FROM seller_identity_verification WHERE seller_id = {$seller['id']} ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($existing) {
    $stmt = $conn->prepare(
        "UPDATE seller_identity_verification
         SET face_match_score = ?, face_match_verified = ?, verification_status = 'in_progress'
         WHERE id = ?"
    );
    $stmt->bind_param("dii", $score, $verified, $existing['id']);
    $stmt->execute();

    $conn->query("DELETE FROM seller_identity_verification WHERE seller_id = {$seller['id']} AND id <> {$existing['id']}");
} else {
    $stmt = $conn->prepare(
        "INSERT INTO seller_identity_verification
         (seller_id, face_match_score, face_match_verified, verification_status, police_verification_status)
         VALUES (?, ?, ?, 'in_progress', 'pending')"
    );
    $stmt->bind_param("idi", $seller['id'], $score, $verified);
    $stmt->execute();
}

record_step($conn, (int)$seller['id'], 7, ["face_match_score" => $score, "verified" => $verified]);

json_response([
    "success" => true,
    "message" => $verified ? "Face match verified" : "Face match below threshold",
    "verification_step" => 7,
    "face_match_verified" => $verified
]);
