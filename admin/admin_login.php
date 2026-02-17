<?php
header("Content-Type: application/json");
require_once "../db.php";

$data = json_decode(file_get_contents("php://input"), true);

$username = $data['username'] ?? null;
$password = $data['password'] ?? null;

if (!$username || !$password) {
    echo json_encode([
        "success" => false,
        "message" => "Username & password required"
    ]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, password FROM admins WHERE username = ?"
);
$stmt->bind_param("s", $username);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin || !password_verify($password, $admin['password'])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid admin credentials"
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Admin login successful",
    "admin_id" => $admin['id']
]);
