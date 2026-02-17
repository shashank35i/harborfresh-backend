<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

/* =======================
   VALIDATION
======================= */

if (
    empty($_POST['seller_id']) ||
    empty($_POST['product_name']) ||
    empty($_POST['price_per_kg']) ||
    empty($_POST['available_weight']) ||
    empty($_POST['category']) ||
    empty($_POST['freshness'])
) {
    echo json_encode([
        "success" => false,
        "message" => "All required fields must be filled"
    ]);
    exit;
}

$seller_id        = (int)$_POST['seller_id'];
$product_name     = trim($_POST['product_name']);
$price_per_kg     = (float)$_POST['price_per_kg'];
$available_weight = trim($_POST['available_weight']);
$category         = trim($_POST['category']);
$freshness        = trim($_POST['freshness']);
$catch_time       = isset($_POST['catch_time']) ? trim($_POST['catch_time']) : null;
$description      = isset($_POST['description']) ? trim($_POST['description']) : null;

/* =======================
   ENUM VALIDATION
======================= */

$allowedCategories = ['Fish','Prawns','Crabs','Lobster','Shellfish','Squid'];
$allowedFreshness  = ['Just Caught',"Today's Catch",'Fresh'];

if (!in_array($category, $allowedCategories)) {
    echo json_encode(["success"=>false,"message"=>"Invalid category"]);
    exit;
}

if (!in_array($freshness, $allowedFreshness)) {
    echo json_encode(["success"=>false,"message"=>"Invalid freshness level"]);
    exit;
}

/* =======================
   IMAGE UPLOAD (OPTIONAL)
======================= */

$imagePath = null;

if (!empty($_FILES['product_image']['name'])) {

    $ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg','jpeg','png'])) {
        echo json_encode(["success"=>false,"message"=>"Invalid image format"]);
        exit;
    }

    $uploadDir = __DIR__ . "/../uploads/products/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $imagePath = "uploads/products/" . time() . "_" . uniqid() . "." . $ext;

    move_uploaded_file(
        $_FILES['product_image']['tmp_name'],
        __DIR__ . "/../" . $imagePath
    );
}

/* =======================
   INSERT INTO DB
======================= */

$stmt = $conn->prepare(
    "INSERT INTO seller_inventory
     (seller_id, product_name, category, price_per_kg, available_weight,
      freshness, catch_time, description, product_image)
     VALUES (?,?,?,?,?,?,?,?,?)"
);

$stmt->bind_param(
    "issdsssss",
    $seller_id,
    $product_name,
    $category,
    $price_per_kg,
    $available_weight,
    $freshness,
    $catch_time,
    $description,
    $imagePath
);

$stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "Product added to inventory successfully"
]);
