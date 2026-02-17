<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (empty($_GET['order_id']) || empty($_GET['seller_id'])) {
    echo json_encode(["success"=>false,"message"=>"Order ID and Seller ID required"]);
    exit;
}

$order_id = (int)$_GET['order_id'];
$seller_id = (int)$_GET['seller_id'];

$order = $conn->query(
    "SELECT o.order_code, o.customer_name, o.customer_phone,
            o.delivery_address, o.status
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     LEFT JOIN seller_products sp ON sp.id = oi.product_id
     WHERE o.id = $order_id AND (o.seller_id = $seller_id OR sp.seller_id = $seller_id)
     LIMIT 1"
)->fetch_assoc();

if (!$order) {
    echo json_encode(["success" => false, "message" => "Order not found"]);
    exit;
}

$itemsRes = $conn->query(
    "SELECT oi.product_name, oi.quantity, oi.price
     FROM order_items oi
     INNER JOIN seller_products sp ON sp.id = oi.product_id
     WHERE oi.order_id = $order_id AND sp.seller_id = $seller_id"
);

$items = [];
$total = 0;
while ($row = $itemsRes->fetch_assoc()) {
    $items[] = $row;
    $total += $row['price'];
}

echo json_encode([
    "success" => true,
    "order" => $order,
    "items" => $items,
    "total_amount" => $total
]);
