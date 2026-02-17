<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$user_id = $_GET['user_id'] ?? 1;

$res = $conn->query(
    "SELECT c.id, p.name, c.cut_name, c.quantity, c.price
     FROM cart c
     JOIN products p ON c.product_id = p.id
     WHERE c.user_id = $user_id"
);

$items = [];
$subtotal = 0;

while ($row = $res->fetch_assoc()) {
    $items[] = $row;
    $subtotal += $row['price'];
}

$delivery_fee = 49;
$discount = 100;
$total = $subtotal + $delivery_fee - $discount;

echo json_encode([
    "success" => true,
    "items" => $items,
    "price_details" => [
        "subtotal" => $subtotal,
        "delivery_fee" => $delivery_fee,
        "discount" => $discount,
        "total" => $total
    ]
]);
