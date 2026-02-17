<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$res = $conn->query(
    "SELECT id,
            seller_id,
            product_name AS name,
            price AS price_per_kg,
            rating,
            freshness,
            image
     FROM seller_products
     ORDER BY rating DESC"
);

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(["success"=>true,"products"=>$data]);
