<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (
    empty($_POST['seller_id']) ||
    empty($_POST['product_name']) ||
    empty($_POST['quantity']) ||
    empty($_POST['price'])
) {
    echo json_encode(["success"=>false,"message"=>"All fields required"]);
    exit;
}

$seller_id = (int)$_POST['seller_id'];
$name = trim($_POST['product_name']);
$qty = trim($_POST['quantity']);
$price = (float)$_POST['price'];
$category = isset($_POST['category']) ? trim($_POST['category']) : null;
$freshness = isset($_POST['freshness']) ? trim($_POST['freshness']) : null;
$rating = isset($_POST['rating']) ? (float)$_POST['rating'] : 5.0;
$location_name = isset($_POST['location_name']) ? trim($_POST['location_name']) : null;
$lat = isset($_POST['latitude']) ? trim($_POST['latitude']) : null;
$lng = isset($_POST['longitude']) ? trim($_POST['longitude']) : null;
$productsAllowed = ['Fish','Prawns','Crabs','Lobster','Shellfish','Squid'];
$categoryForProducts = in_array($category, $productsAllowed) ? $category : 'Fish';
$freshnessForProducts = !empty($freshness) ? $freshness : 'Fresh';

// Optional image
$imagePath = null;
if (!empty($_FILES['image'])) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext,['jpg','jpeg','png'])) {
        echo json_encode(["success"=>false,"message"=>"Invalid image"]);
        exit;
    }

    $dir = __DIR__ . "/../uploads/products/";
    if (!is_dir($dir)) mkdir($dir,0777,true);

    $imagePath = "uploads/products/" . time()."_".uniqid().".".$ext;
    move_uploaded_file($_FILES['image']['tmp_name'], "../".$imagePath);
}

$stmt = $conn->prepare(
    "INSERT INTO seller_products (seller_id, product_name, quantity, price, image, category, freshness, location_name, latitude, longitude, rating)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)"
);
$stmt->bind_param("issdssssssd",
    $seller_id,
    $name,
    $qty,
    $price,
    $imagePath,
    $category,
    $freshness,
    $location_name,
    $lat,
    $lng,
    $rating
);
$stmt->execute();

// Also persist into public products table for customer browsing (rating fixed to 5.0 by request)
$stmt2 = $conn->prepare(
    "INSERT INTO products (seller_id, name, category, price_per_kg, freshness, rating, image, is_active) 
     VALUES (?,?,?,?,?,?,?,1)"
);
$ratingFixed = 5.0;
$stmt2->bind_param(
    "issdsss",
    $seller_id,
    $name,
    $categoryForProducts,
    $price,
    $freshnessForProducts,
    $ratingFixed,
    $imagePath
);
$stmt2->execute();

echo json_encode([
    "success"=>true,
    "message"=>"Product added successfully"
]);
