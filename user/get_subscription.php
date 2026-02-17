<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

$sub = $conn->query("SELECT * FROM subscriptions LIMIT 1")
            ->fetch_assoc();

echo json_encode([
    "success"=>true,
    "subscription"=>$sub
]);
