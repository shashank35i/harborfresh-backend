<?php
header("Content-Type: application/json");

/* ✅ CORRECT DB INCLUDE */
require_once __DIR__ . "/../db.php";

/* -----------------------------
   READ JSON INPUT
------------------------------*/
$data = json_decode(file_get_contents("php://input"), true);

if (
    empty($data['email']) ||
    empty($data['otp'])
) {
    echo json_encode([
        "success" => false,
        "message" => "Email & OTP required"
    ]);
    exit;
}

$email = trim($data['email']);
$otp   = trim($data['otp']);

/* -----------------------------
   FETCH OTP DATA
------------------------------*/
$stmt = $conn->prepare(
    "SELECT otp, otp_expiry FROM users WHERE email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$row = $result->fetch_assoc();
$isPending = false;

if (!$row) {
    // Fallback to pending_users
    $pstmt = $conn->prepare(
        "SELECT full_name, phone, password, role, otp, otp_expiry FROM pending_users WHERE email = ?"
    );
    $pstmt->bind_param("s", $email);
    $pstmt->execute();
    $pendingRes = $pstmt->get_result();
    $row = $pendingRes->fetch_assoc();
    $isPending = $row ? true : false;
}

if (!$row) {
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit;
}

/* -----------------------------
   OTP VALIDATION
------------------------------*/
if ($row['otp'] !== $otp) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid OTP"
    ]);
    exit;
}

if (strtotime($row['otp_expiry']) < time()) {
    echo json_encode([
        "success" => false,
        "message" => "OTP expired"
    ]);
    exit;
}

/* -----------------------------
   CREATE OR MARK VERIFIED
------------------------------*/
if ($isPending) {
    // Create user from pending
    $role = $row['role'] ?? 'customer';
    $insert = $conn->prepare("
        INSERT INTO users (full_name, email, phone, password, role, is_verified, otp, otp_expiry)
        VALUES (?, ?, ?, ?, ?, 1, NULL, NULL)
        ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name),
            phone = VALUES(phone),
            password = VALUES(password),
            role = VALUES(role),
            is_verified = 1,
            otp = NULL,
            otp_expiry = NULL
    ");
    $insert->bind_param("sssss", $row['full_name'], $email, $row['phone'], $row['password'], $role);
    $insert->execute();

    // Remove pending record
    $del = $conn->prepare("DELETE FROM pending_users WHERE email = ?");
    $del->bind_param("s", $email);
    $del->execute();
} else {
    $update = $conn->prepare(
        "UPDATE users 
         SET is_verified = 1,
             otp = NULL,
             otp_expiry = NULL
         WHERE email = ?"
    );
    $update->bind_param("s", $email);
    $update->execute();
}

/* -----------------------------
   RESPONSE
------------------------------*/
echo json_encode([
    "success" => true,
    "message" => "Email verified successfully"
]);
