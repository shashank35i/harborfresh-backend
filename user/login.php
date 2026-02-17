<?php
header("Content-Type: application/json");

/* ✅ CORRECT DB INCLUDE */
require_once __DIR__ . "/../db.php";

/* -----------------------------
   READ JSON INPUT
------------------------------*/
$data = json_decode(file_get_contents("php://input"), true);

/* -----------------------------
   VALIDATION
------------------------------*/
if (empty($data['email']) || empty($data['password'])) {
    echo json_encode([
        "success" => false,
        "message" => "Email and password required"
    ]);
    exit;
}

$email = trim($data['email']);
$password = $data['password'];

/* -----------------------------
   EMAIL FORMAT CHECK
------------------------------*/
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email format"
    ]);
    exit;
}

/* -----------------------------
   FETCH USER
------------------------------*/
$stmt = $conn->prepare(
    "SELECT id, full_name, password, role, is_verified
     FROM users
     WHERE email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit;
}

$user = $result->fetch_assoc();

/* -----------------------------
   CHECK EMAIL VERIFIED
   (customers only; sellers may skip)
------------------------------*/
$role = $user['role'] ?? 'customer';
if ($role === 'customer' && (int)$user['is_verified'] !== 1) {
    echo json_encode([
        "success" => false,
        "message" => "Email not verified"
    ]);
    exit;
}

/* -----------------------------
   VERIFY PASSWORD
------------------------------*/
if (!password_verify($password, $user['password'])) {
    echo json_encode([
        "success" => false,
        "message" => "Incorrect password"
    ]);
    exit;
}

/* -----------------------------
   LOGIN SUCCESS
------------------------------*/
echo json_encode([
    "success" => true,
    "message" => "Login successful",
    "user_id" => $user['id'],
    "name" => $user['full_name'],
    "role" => $user['role']
]);

$stmt->close();
$conn->close();
