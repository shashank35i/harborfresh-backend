<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$seasonal = $conn->query(
    "SELECT title, subtitle, category_name
     FROM seasonal_categories
     WHERE is_active=1
     LIMIT 1"
)->fetch_assoc();

echo json_encode([
    "success" => true,
    "seasonal" => $seasonal
]);
