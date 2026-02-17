<?php
header("Content-Type: application/json");
require_once "../db.php";
ini_set('display_errors', 0);
error_reporting(0);

/* ---------------------------------
   READ INPUT (JSON + FORM)
----------------------------------*/
$data = json_decode(file_get_contents("php://input"), true);

$order_id = $_POST['order_id'] ?? $data['order_id'] ?? null;
$method   = $_POST['payment_method'] ?? $data['payment_method'] ?? null;

if (!$order_id || !$method) {
    echo json_encode([
        "success" => false,
        "message" => "Order ID & Payment method required"
    ]);
    exit;
}

if (!in_array($method, ['COD', 'UPI', 'CARD', 'WALLET'])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid payment method"
    ]);
    exit;
}

/* ---------------------------------
   CHECK ORDER EXISTS
----------------------------------*/
$orderCheck = $conn->query(
    "SELECT id FROM orders WHERE id = $order_id"
);

if (!$orderCheck) {
    echo json_encode([
        "success" => false,
        "message" => "Order lookup failed"
    ]);
    exit;
}

if ($orderCheck->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Order not found"
    ]);
    exit;
}

/* ---------------------------------
   CALCULATE TOTAL FROM ORDER ITEMS
----------------------------------*/
$totalResult = $conn->query(
    "SELECT SUM(price) AS total 
     FROM order_items 
     WHERE order_id = $order_id"
);

if (!$totalResult) {
    echo json_encode([
        "success" => false,
        "message" => "Order items lookup failed"
    ]);
    exit;
}

$totalRow = $totalResult->fetch_assoc();
$amount = $totalRow['total'] ?? 0;

if ($amount <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid order amount"
    ]);
    exit;
}

/* ---------------------------------
   PAYMENT LOGIC
----------------------------------*/
$payment_status = ($method === 'COD') ? 'Pending' : 'Paid';
if ($method === 'UPI') {
    $transaction_id = "UPI" . rand(100000, 999999);
} elseif ($method === 'CARD') {
    $transaction_id = "CARD" . rand(100000, 999999);
} elseif ($method === 'WALLET') {
    $transaction_id = "WAL" . rand(100000, 999999);
} else {
    $transaction_id = null;
}

/* ---------------------------------
   INSERT PAYMENT
----------------------------------*/
$stmt = $conn->prepare(
    "INSERT INTO payments
    (order_id, payment_method, payment_status, transaction_id, amount)
    VALUES (?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "isssd",
    $order_id,
    $method,
    $payment_status,
    $transaction_id,
    $amount
);
$stmt->execute();

/* ---------------------------------
   UPDATE ORDER PAYMENT STATUS
----------------------------------*/
$update = $conn->prepare(
    "UPDATE orders
     SET payment_method = ?, payment_status = ?
     WHERE id = ?"
);

$update->bind_param(
    "ssi",
    $method,
    $payment_status,
    $order_id
);
$update->execute();

echo json_encode([
    "success" => true,
    "order_id" => $order_id,
    "payment_method" => $method,
    "payment_status" => $payment_status,
    "transaction_id" => $transaction_id,
    "amount" => $amount
]);
