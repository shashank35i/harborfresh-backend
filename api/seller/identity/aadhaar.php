<?php
require_once __DIR__ . "/../../utils.php";

$seller = require_seller($conn, $_POST);

$aadhaarNumber = $_POST['aadhaar_number'] ?? null;
$aadhaarName   = isset($_POST['aadhaar_name']) ? clean($_POST['aadhaar_name']) : null;

if (!$aadhaarNumber || !$aadhaarName || !isset($_FILES['aadhaar_doc'])) {
    json_response(["success" => false, "message" => "Aadhaar number, name and document are required"], 422);
}

if (!preg_match("/^[0-9]{12}$/", $aadhaarNumber)) {
    json_response(["success" => false, "message" => "Invalid Aadhaar number"], 422);
}

$aadhaarPath = save_uploaded_file($_FILES['aadhaar_doc'], "aadhaar");

// Upsert manually because the table does not enforce UNIQUE(seller_id)
$existing = $conn->query("SELECT id FROM seller_identity_verification WHERE seller_id = {$seller['id']} ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($existing) {
    $stmt = $conn->prepare(
        "UPDATE seller_identity_verification
         SET aadhaar_number = ?,
             aadhaar_name = ?,
             aadhaar_doc = ?,
             aadhaar_verified = 1,
             verification_status = 'in_progress'
         WHERE id = ?"
    );
    $stmt->bind_param("sssi", $aadhaarNumber, $aadhaarName, $aadhaarPath, $existing['id']);
    $stmt->execute();

    // Remove older duplicates so counts and listings stay single-row
    $conn->query("DELETE FROM seller_identity_verification WHERE seller_id = {$seller['id']} AND id <> {$existing['id']}");
} else {
    $stmt = $conn->prepare(
        "INSERT INTO seller_identity_verification
         (seller_id, aadhaar_number, aadhaar_name, aadhaar_doc, aadhaar_verified, verification_status, police_verification_status)
         VALUES (?, ?, ?, ?, 1, 'in_progress', 'pending')"
    );
    $stmt->bind_param("isss", $seller['id'], $aadhaarNumber, $aadhaarName, $aadhaarPath);
    $stmt->execute();
}

record_step($conn, (int)$seller['id'], 5, [
    "aadhaar_number" => $aadhaarNumber,
    "aadhaar_name" => $aadhaarName
]);

json_response([
    "success" => true,
    "message" => "Aadhaar uploaded",
    "verification_step" => 5,
    "aadhaar_doc" => $aadhaarPath
]);
