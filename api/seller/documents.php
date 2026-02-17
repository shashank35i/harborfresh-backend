<?php
require_once __DIR__ . "/../utils.php";

$seller = require_seller($conn, $_POST);

if (
    !isset($_FILES['fishing_license_doc']) ||
    !isset($_FILES['government_id_doc']) ||
    !isset($_FILES['address_proof_doc'])
)
{
    json_response(["success" => false, "message" => "All documents are required"], 422);
}

$licensePath  = save_uploaded_file($_FILES['fishing_license_doc'], "licenses");
$govIdPath    = save_uploaded_file($_FILES['government_id_doc'], "ids");
$addressPath  = save_uploaded_file($_FILES['address_proof_doc'], "address_proofs");

$stmt = $conn->prepare(
    "INSERT INTO seller_documents (seller_id, fishing_license_doc, government_id_doc, address_proof_doc)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        fishing_license_doc = VALUES(fishing_license_doc),
        government_id_doc = VALUES(government_id_doc),
        address_proof_doc = VALUES(address_proof_doc)"
);
$stmt->bind_param("isss", $seller['id'], $licensePath, $govIdPath, $addressPath);
$stmt->execute();

record_step($conn, (int)$seller['id'], 4, [
    "fishing_license_doc" => $licensePath,
    "government_id_doc" => $govIdPath,
    "address_proof_doc" => $addressPath
]);

json_response([
    "success" => true,
    "message" => "Documents uploaded successfully",
    "verification_step" => 4
]);
