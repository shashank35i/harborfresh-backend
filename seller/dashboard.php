<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

if (empty($_GET['seller_id'])) {
    echo json_encode(["success"=>false,"message"=>"Seller ID required"]);
    exit;
}

$seller_id = (int)$_GET['seller_id'];

// Seller info
$seller = $conn->query(
    "SELECT full_name, business_name, city, profile_image
     FROM sellers WHERE id=$seller_id"
)->fetch_assoc();

// Stats
$totalProducts = $conn->query(
    "SELECT COUNT(*) c FROM seller_products WHERE seller_id=$seller_id"
)->fetch_assoc()['c'];

$totalOrders = $conn->query(
    "SELECT COUNT(*) c FROM orders WHERE seller_id=$seller_id"
)->fetch_assoc()['c'];

$totalRevenue = $conn->query(
    "SELECT COALESCE(SUM(total_amount), SUM(subtotal), 0) total
     FROM orders WHERE seller_id=$seller_id"
)->fetch_assoc()['total'];

$pendingOrders = $conn->query(
    "SELECT COUNT(*) c FROM orders
     WHERE seller_id=$seller_id AND status='Pending'"
)->fetch_assoc()['c'];

echo json_encode([
    "success" => true,
    "seller" => $seller,
    "stats" => [
        "products" => $totalProducts,
        "orders" => $totalOrders,
        "pending" => $pendingOrders,
        "revenue" => $totalRevenue
    ]
]);
