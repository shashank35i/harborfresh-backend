<?php
header("Content-Type: application/json");
require_once "../db.php";

/* Read input */
$data = json_decode(file_get_contents("php://input"), true);

$order_id = $_POST['order_id'] ?? $data['order_id'] ?? null;
$slot_id  = $_POST['slot_id']  ?? $data['slot_id']  ?? null;

if (!$order_id || !$slot_id) {
    echo json_encode([
        "success" => false,
        "message" => "Order ID & Slot ID required"
    ]);
    exit;
}

/* Check slot availability */
$slot = $conn->query(
    "SELECT id FROM delivery_slots
     WHERE id = $slot_id AND is_available = 1"
);

if ($slot->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Delivery slot not available"
    ]);
    exit;
}

/* Update order */
$stmt = $conn->prepare(
    "UPDATE orders SET delivery_slot_id = ? WHERE id = ?"
);
$stmt->bind_param("ii", $slot_id, $order_id);
$stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "Delivery slot selected"
]);
