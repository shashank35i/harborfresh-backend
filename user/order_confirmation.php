<?php
header("Content-Type: application/json");
require_once "../db.php";

/* -----------------------------
   INPUT
------------------------------*/
$data = json_decode(file_get_contents("php://input"), true);
$order_id = $_POST['order_id'] ?? $data['order_id'] ?? null;

if (!$order_id) {
    echo json_encode([
        "success" => false,
        "message" => "Order ID required"
    ]);
    exit;
}

/* -----------------------------
   SQL (ONLY VERIFIED COLUMNS)
------------------------------*/
$sql = "
    SELECT 
        o.id,
        o.order_code,
        o.delivery_address,
        o.status,
        ds.day,
        ds.time_range
    FROM orders o
    LEFT JOIN delivery_slots ds 
        ON ds.id = o.delivery_slot_id
    WHERE o.id = ?
";

/* -----------------------------
   PREPARE (CRITICAL CHECK)
------------------------------*/
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode([
        "success" => false,
        "message" => "Prepare failed",
        "sql_error" => $conn->error
    ]);
    exit;
}

/* -----------------------------
   EXECUTE
------------------------------*/
$stmt->bind_param("i", $order_id);
$stmt->execute();

$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    echo json_encode([
        "success" => false,
        "message" => "Order not found"
    ]);
    exit;
}

/* -----------------------------
   ITEM COUNT
------------------------------*/
$count_sql = "SELECT COUNT(*) AS total FROM order_items WHERE order_id = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $order_id);
$count_stmt->execute();
$count_res = $count_stmt->get_result()->fetch_assoc();

/* -----------------------------
   RESPONSE
------------------------------*/
echo json_encode([
    "success" => true,
    "order_code" => $order['order_code'],
    "delivery_address" => $order['delivery_address'],
    "delivery_slot" => ($order['day'] && $order['time_range'])
        ? $order['day'] . " " . $order['time_range']
        : null,
    "items" => $count_res['total'],
    "status" => $order['status']
]);
