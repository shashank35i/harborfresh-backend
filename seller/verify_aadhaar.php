<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (
    empty($_POST['seller_id']) ||
    empty($_POST['aadhaar_number']) ||
    empty($_POST['aadhaar_name']) ||
    !isset($_FILES['aadhaar_doc'])
) {
    echo json_encode(["success"=>false,"message"=>"All Aadhaar fields required"]);
    exit;
}

$seller_id = (int)$_POST['seller_id'];
$aadhaar_number = $_POST['aadhaar_number'];
$aadhaar_name = trim($_POST['aadhaar_name']);

if (!preg_match("/^[0-9]{12}$/", $aadhaar_number)) {
    echo json_encode(["success"=>false,"message"=>"Invalid Aadhaar number"]);
    exit;
}

$ext = strtolower(pathinfo($_FILES['aadhaar_doc']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','pdf'])) {
    echo json_encode(["success"=>false,"message"=>"Invalid Aadhaar file"]);
    exit;
}

$dir = __DIR__ . "/../uploads/aadhaar/";
if (!is_dir($dir)) mkdir($dir, 0777, true);

$filePath = "uploads/aadhaar/" . time() . "_" . uniqid() . "." . $ext;
move_uploaded_file($_FILES['aadhaar_doc']['tmp_name'], "../".$filePath);

// Reuse or create a single identity row for this seller
$existing = $conn->query(
    "SELECT id FROM seller_identity_verification WHERE seller_id=$seller_id ORDER BY id DESC LIMIT 1"
)->fetch_assoc();

if ($existing) {
    $stmt = $conn->prepare(
        "UPDATE seller_identity_verification
         SET aadhaar_number = ?,
             aadhaar_name = ?,
             aadhaar_doc = ?,
             aadhaar_verified = 1,
             police_verification_status = IFNULL(police_verification_status, 'pending')
         WHERE id = ?"
    );
    $stmt->bind_param("sssi", $aadhaar_number, $aadhaar_name, $filePath, $existing['id']);
    $stmt->execute();

    // Cleanup stray duplicates so later checks always read the latest row
    $conn->query("DELETE FROM seller_identity_verification WHERE seller_id=$seller_id AND id <> {$existing['id']}");
} else {
    $stmt = $conn->prepare(
        "INSERT INTO seller_identity_verification
         (seller_id,aadhaar_number,aadhaar_name,aadhaar_doc,aadhaar_verified,police_verification_status)
         VALUES (?,?,?,?,1,'pending')"
    );
    $stmt->bind_param("isss", $seller_id, $aadhaar_number, $aadhaar_name, $filePath);
    $stmt->execute();
}

echo json_encode(["success"=>true,"message"=>"Aadhaar verified"]);
