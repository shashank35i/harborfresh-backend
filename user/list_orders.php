<?php
header('Content-Type: application/json');
require_once "../db.php";

$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(["success" => false, "message" => "user_id required"]);
    exit;
}
$user_id = (int)$user_id;

// Ensure user_id column exists (no error if already there)
@ $conn->query("ALTER TABLE orders ADD COLUMN user_id int(11) NULL DEFAULT NULL");

$sql = $conn->prepare(
    "SELECT o.id, o.order_code, o.status, o.total_amount, o.created_at,
            COALESCE(o.total_items, COUNT(oi.id)) AS items,
            o.delivery_fee, o.discount
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.user_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC"
);
$sql->bind_param("i", $user_id);
$sql->execute();
$res = $sql->get_result();

$orders = [];
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}

echo json_encode([
    "success" => true,
    "orders" => $orders
]);
?>
