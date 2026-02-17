<?php
require_once __DIR__ . "/../../utils.php";

$seller = require_seller($conn, $_POST);

if (!isset($_FILES['selfie_image'])) {
    json_response(["success" => false, "message" => "Selfie image required"], 422);
}

$selfiePath = save_uploaded_file($_FILES['selfie_image'], "selfie", ['jpg', 'jpeg', 'png']);

$existing = $conn->query("SELECT id FROM seller_identity_verification WHERE seller_id = {$seller['id']} ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($existing) {
    $stmt = $conn->prepare(
        "UPDATE seller_identity_verification
         SET selfie_image = ?, liveness_verified = 1, verification_status = 'in_progress'
         WHERE id = ?"
    );
    $stmt->bind_param("si", $selfiePath, $existing['id']);
    $stmt->execute();

    $conn->query("DELETE FROM seller_identity_verification WHERE seller_id = {$seller['id']} AND id <> {$existing['id']}");
} else {
    $stmt = $conn->prepare(
        "INSERT INTO seller_identity_verification
         (seller_id, selfie_image, liveness_verified, verification_status, police_verification_status)
         VALUES (?, ?, 1, 'in_progress', 'pending')"
    );
    $stmt->bind_param("is", $seller['id'], $selfiePath);
    $stmt->execute();
}

record_step($conn, (int)$seller['id'], 6, ["selfie_image" => $selfiePath]);

json_response([
    "success" => true,
    "message" => "Selfie uploaded",
    "verification_step" => 6,
    "selfie_image" => $selfiePath
]);
