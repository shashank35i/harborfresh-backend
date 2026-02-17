<?php
header("Content-Type: application/json");
require_once "../db.php";

$result = $conn->query(
    "SELECT id, day, time_range, is_express
     FROM delivery_slots
     WHERE is_available = 1
     ORDER BY id ASC"
);

$slots = [];

while ($row = $result->fetch_assoc()) {
    $slots[] = [
        "slot_id" => $row['id'],
        "day" => $row['day'],
        "time" => $row['time_range'],
        "is_express" => (bool)$row['is_express']
    ];
}

echo json_encode([
    "success" => true,
    "slots" => $slots
]);
