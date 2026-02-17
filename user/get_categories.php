<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$result = $conn->query(
    "SELECT name, icon, total_items
     FROM categories
     ORDER BY name ASC"
);

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        "name" => $row['name'],
        "icon" => $row['icon'],      // can be NULL
        "total_items" => (int)$row['total_items']
    ];
}

echo json_encode([
    "success" => true,
    "categories" => $categories
]);
