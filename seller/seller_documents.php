<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";

if (
    empty($_POST['seller_id']) ||
    !isset($_FILES['license']) ||
    !isset($_FILES['id']) ||
    !isset($_FILES['address'])
) {
    echo json_encode([
        "success" => false,
        "message" => "Seller ID and all documents required"
    ]);
    exit;
}

$seller_id = (int)$_POST['seller_id'];

// Allowed file types
$allowed = ['jpg', 'jpeg', 'png', 'pdf'];
$maxSize = 2 * 1024 * 1024; // 2MB

function uploadFile($file, $folder) {
    global $allowed, $maxSize;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        return false;
    }

    if ($file['size'] > $maxSize) {
        return false;
    }

    $uploadDir = __DIR__ . "/../uploads/$folder/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = time() . "_" . uniqid() . "." . $ext;
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return "uploads/$folder/" . $fileName;
    }

    return false;
}

// Upload each document
$licensePath = uploadFile($_FILES['license'], "licenses");
$idPath      = uploadFile($_FILES['id'], "ids");
$addressPath = uploadFile($_FILES['address'], "address_proofs");

if (!$licensePath || !$idPath || !$addressPath) {
    echo json_encode([
        "success" => false,
        "message" => "File upload failed or invalid file type"
    ]);
    exit;
}

// Save paths in DB
$stmt = $conn->prepare(
    "INSERT INTO seller_documents
     (seller_id, fishing_license_doc, government_id_doc, address_proof_doc)
     VALUES (?, ?, ?, ?)"
);

$stmt->bind_param(
    "isss",
    $seller_id,
    $licensePath,
    $idPath,
    $addressPath
);

$stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "Documents uploaded successfully"
]);
