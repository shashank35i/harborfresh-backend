<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(["success" => false, "message" => "Query required"]);
    exit;
}

$like = "%" . $conn->real_escape_string($q) . "%";

$sql = "
    SELECT id, seller_id, product_name, price, freshness, location_name, rating, image, category
    FROM seller_products
    WHERE product_name LIKE '$like' OR category LIKE '$like'
    ORDER BY created_at DESC
    LIMIT 100
";

$res = $conn->query($sql);
$products = [];
while ($row = $res->fetch_assoc()) {
    $products[] = [
        "id" => (int)$row["id"],
        "seller_id" => (int)$row["seller_id"],
        "name" => $row["product_name"],
        "price" => $row["price"],
        "freshness" => $row["freshness"],
        "location" => $row["location_name"],
        "rating" => $row["rating"],
        "image" => $row["image"],
        "category" => $row["category"]
    ];
}

echo json_encode([
    "success" => true,
    "products" => $products
]);
