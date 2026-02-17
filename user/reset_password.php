<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (
    empty($data['email']) ||
    empty($data['otp']) ||
    empty($data['password'])
) {
    echo json_encode(["success" => false, "message" => "Email, OTP, and password are required"]);
    exit;
}

$email = trim($data['email']);
$otp = trim($data['otp']);
$newPassword = $data['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email"]);
    exit;
}

if (strlen($otp) !== 6) {
    echo json_encode(["success" => false, "message" => "Invalid OTP"]);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(["success" => false, "message" => "Password must be at least 6 characters"]);
    exit;
}

$stmt = $conn->prepare("SELECT otp, otp_expiry FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "User not found"]);
    exit;
}

$row = $result->fetch_assoc();

if ($row['otp'] !== $otp) {
    echo json_encode(["success" => false, "message" => "Invalid OTP"]);
    exit;
}

if (strtotime($row['otp_expiry']) < time()) {
    echo json_encode(["success" => false, "message" => "OTP expired"]);
    exit;
}

$hashed = password_hash($newPassword, PASSWORD_DEFAULT);
$update = $conn->prepare("UPDATE users SET password = ?, otp = NULL, otp_expiry = NULL, is_verified = 1 WHERE email = ?");
$update->bind_param("ss", $hashed, $email);

if ($update->execute()) {
    echo json_encode(["success" => true, "message" => "Password reset successful"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to reset password"]);
}
?>
