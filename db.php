<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost","root","","harborfresh");

if ($conn->connect_error) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}
?>
