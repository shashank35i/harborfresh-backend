<?php
header("Content-Type: application/json");
require_once "../db.php";

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([
        "success" => false,
        "message" => "User ID required"
    ]);
    exit;
}

/* ======================
   USER DETAILS
====================== */
$user_q = $conn->prepare("
    SELECT id, full_name, email, phone, points
    FROM users
    WHERE id = ?
");
$user_q->bind_param("i", $user_id);
$user_q->execute();
$user = $user_q->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit;
}

/* ======================
   ORDERS COUNT
====================== */
$orders_q = $conn->prepare("
    SELECT COUNT(*) AS total_orders
    FROM orders
    WHERE user_id = ?
");
$orders_q->bind_param("i", $user_id);
$orders_q->execute();
$orders = $orders_q->get_result()->fetch_assoc();

/* ======================
   SAVED AMOUNT
====================== */
$saved_q = $conn->prepare("
    SELECT IFNULL(SUM(discount),0) AS saved
    FROM orders
    WHERE user_id = ?
");
$saved_q->bind_param("i", $user_id);
$saved_q->execute();
$saved = $saved_q->get_result()->fetch_assoc();

/* ======================
   ADDRESSES
====================== */
$addresses = [];
$addr_q = $conn->prepare("
    SELECT id, label, full_name, phone, address, city, pincode, is_default
    FROM user_addresses
    WHERE user_id = ?
");
$addr_q->bind_param("i", $user_id);
$addr_q->execute();
$res = $addr_q->get_result();

while ($row = $res->fetch_assoc()) {
    $addresses[] = $row;
}

/* ======================
   RESPONSE
====================== */
echo json_encode([
    "success" => true,
    "profile" => [
        "name" => $user['full_name'],
        "email" => $user['email'],
        "phone" => $user['phone']
    ],
    "stats" => [
        "orders" => (int)$orders['total_orders'],
        "saved" => (float)$saved['saved'],
        "points" => (int)$user['points']
    ],
    "addresses" => $addresses
]);
