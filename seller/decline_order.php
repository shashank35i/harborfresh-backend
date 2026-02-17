<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (empty($_POST['order_id']) || empty($_POST['seller_id'])) {
    echo json_encode(["success"=>false,"message"=>"Order ID and Seller ID required"]);
    exit;
}

$order_id = (int)$_POST['order_id'];
$seller_id = (int)$_POST['seller_id'];

$conn->query(
    "UPDATE orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     LEFT JOIN seller_products sp ON sp.id = oi.product_id
     SET o.status='Declined',
         o.seller_id=IF(o.seller_id IS NULL OR o.seller_id=0, $seller_id, o.seller_id)
     WHERE o.id=$order_id
       AND (o.seller_id=$seller_id OR sp.seller_id=$seller_id)
       AND o.status='Pending'"
);

if ($conn->affected_rows > 0) {
    $msg = "Order declined by seller";
    $conn->query(
        "INSERT INTO order_tracking (order_id, status, message)
         VALUES ($order_id, 'Declined', '$msg')"
    );
}

echo json_encode([
    "success" => $conn->affected_rows > 0,
    "message" => $conn->affected_rows > 0 ? "Order declined" : "Unable to decline order"
]);
