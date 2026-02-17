<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (empty($_GET['product_id'])) {
    echo json_encode(["success" => false, "message" => "Product ID required"]);
    exit;
}

$product_id = (int)$_GET['product_id'];
if ($product_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid Product ID"]);
    exit;
}

// Get product from seller_products
$stmt = $conn->prepare(
    "SELECT id, seller_id, product_name AS name, price AS price_per_kg, freshness, rating, image, location_name
     FROM seller_products
     WHERE id = ?"
);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo json_encode(["success" => false, "message" => "Product not found"]);
    exit;
}

// Seller details
$seller = null;
$sellerStmt = $conn->prepare(
    "SELECT id, full_name, business_name, city, rating, profile_image
     FROM sellers
     WHERE id = ?"
);
$sellerStmt->bind_param("i", $product['seller_id']);
$sellerStmt->execute();
$sellerRow = $sellerStmt->get_result()->fetch_assoc();
if ($sellerRow) {
    $seller = [
        "id" => (int)$sellerRow['id'],
        "name" => $sellerRow['full_name'],
        "business" => $sellerRow['business_name'],
        "city" => $sellerRow['city'],
        "rating" => $sellerRow['rating'],
        "image" => $sellerRow['profile_image']
    ];
}

// Other products from same seller
$others = [];
$otherStmt = $conn->prepare(
    "SELECT id, seller_id, product_name AS name, price AS price_per_kg, freshness, rating, image, location_name
     FROM seller_products
     WHERE seller_id = ? AND id != ?
     ORDER BY id DESC
     LIMIT 10"
);
$otherStmt->bind_param("ii", $product['seller_id'], $product_id);
$otherStmt->execute();
$res = $otherStmt->get_result();
while ($row = $res->fetch_assoc()) {
    $others[] = $row;
}

echo json_encode([
    "success" => true,
    "product" => $product,
    "seller" => $seller,
    "other_products" => $others
]);
