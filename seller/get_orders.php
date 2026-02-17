<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (empty($_GET['seller_id'])) {
    echo json_encode(["success"=>false,"message"=>"Seller ID required"]);
    exit;
}

$seller_id = (int)$_GET['seller_id'];

$result = $conn->query(
    "SELECT
        o.id,
        o.order_code,
        o.customer_name,
        o.customer_phone,
        MIN(oi.product_name) AS product_name,
        COALESCE(o.total_items, SUM(oi.quantity)) AS quantity,
        COALESCE(o.total_amount, SUM(oi.price)) AS total_price,
        o.status,
        o.created_at,
        ds.day AS slot_day,
        ds.time_range AS slot_time_range
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     LEFT JOIN seller_products sp ON sp.id = oi.product_id
     LEFT JOIN delivery_slots ds ON ds.id = o.delivery_slot_id
     WHERE (o.seller_id = $seller_id OR sp.seller_id = $seller_id)
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 50"
);

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

echo json_encode([
    "success"=>true,
    "orders"=>$orders
]);
