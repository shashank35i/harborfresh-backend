<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$user_id = $_GET['user_id'] ?? 1;

// Location
$location = $conn->query(
    "SELECT address, city
     FROM user_locations
     WHERE user_id=$user_id AND is_default=1"
)->fetch_assoc();

// Categories
$categories = [];
$res = $conn->query("SELECT name, total_items FROM categories");
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}

// Popular products
$products = [];
// THE FIX IS ON THE NEXT LINE: Added 'id,' to the SELECT statement.
$res = $conn->query(
    "SELECT id, product_name AS name, price AS price_per_kg, rating, freshness, image, seller_id
     FROM seller_products
     ORDER BY rating DESC
     LIMIT 4"
);
while ($row = $res->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode([
    "success" => true,
    "location" => $location,
    "today_banner" => [
        "title" => "Today's Fresh Catch",
        "subtitle" => "Caught at 4:30 AM · 12 varieties"
    ],
    "categories" => $categories,
    "popular_today" => $products
]);
