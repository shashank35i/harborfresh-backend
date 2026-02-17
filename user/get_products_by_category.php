<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (empty($_GET['category'])) {
    echo json_encode([
        "success" => false,
        "message" => "Category required"
    ]);
    exit;
}

$category = $_GET['category'];

$allowed = ['Fish','Prawns','Crabs','Lobster','Shellfish','Squid','Others'];
if (!in_array($category, $allowed)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid category"
    ]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT 
        id,
        product_name AS name,
        price AS price_per_kg,
        rating,
        freshness,
        image,
        seller_id
     FROM seller_products
     WHERE category = ?"
);

$stmt->bind_param("s", $category);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode([
    "success" => true,
    "category" => $category,
    "products" => $products
]);
