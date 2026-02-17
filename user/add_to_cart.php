<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (
    empty($data['user_id']) ||
    empty($data['product_id']) ||
    empty($data['quantity'])
) {
    echo json_encode(["success"=>false,"message"=>"Required fields missing"]);
    exit;
}

$user_id    = $data['user_id'];
$product_id = $data['product_id'];
$cut_id     = $data['cut_id'] ?? null;
$quantity   = $data['quantity'];

/* Base price */
$p = $conn->query(
    "SELECT price_per_kg FROM products WHERE id=$product_id"
)->fetch_assoc();

$price = $p['price_per_kg'] * $quantity;

/* Extra cut price */
if ($cut_id) {
    $cut = $conn->query(
        "SELECT extra_price FROM product_cuts WHERE id=$cut_id"
    )->fetch_assoc();
    $price += $cut['extra_price'];
}

$stmt = $conn->prepare(
    "INSERT INTO cart (user_id, product_id, cut_id, quantity, price)
     VALUES (?,?,?,?,?)"
);
$stmt->bind_param("iiidd",$user_id,$product_id,$cut_id,$quantity,$price);
$stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "Added to cart",
    "total_price" => $price
]);
