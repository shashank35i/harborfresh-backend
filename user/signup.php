<?php
// Set the content type to JSON for all responses
header("Content-Type: application/json");

// ✅ Correct database include (FIXED)
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../PHPMailer-master/src/Exception.php";
require_once __DIR__ . "/../PHPMailer-master/src/PHPMailer.php";
require_once __DIR__ . "/../PHPMailer-master/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get the raw POST data from the request
$data = json_decode(file_get_contents("php://input"), true);

/* -----------------------------
   VALIDATION
------------------------------*/
if (
    empty($data['full_name']) ||
    empty($data['email']) ||
    empty($data['phone']) ||
    empty($data['password'])
) {
    echo json_encode([
        "success" => false,
        "message" => "All fields are required"
    ]);
    exit;
}

/* -----------------------------
   ASSIGN & SANITIZE
------------------------------*/
$name = trim($data['full_name']);
$email = trim($data['email']);
$phone = trim($data['phone']);
$password = $data['password'];
$role = isset($data['role']) ? strtolower(trim($data['role'])) : 'customer';
$allowedRoles = ['customer', 'admin'];
if (!in_array($role, $allowedRoles, true)) {
    $role = 'customer';
}

/* -----------------------------
   SERVER-SIDE VALIDATIONS
------------------------------*/
// Email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email format"
    ]);
    exit;
}

// Phone validation (must be 10 digits)
if (!preg_match("/^[0-9]{10}$/", $phone)) {
    echo json_encode([
        "success" => false,
        "message" => "Phone number must be 10 digits"
    ]);
    exit;
}

// Password strength
if (strlen($password) < 6) {
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 6 characters"
    ]);
    exit;
}

/* -----------------------------
   CHECK IF EMAIL ALREADY EXISTS
------------------------------*/
$stmt = $conn->prepare(
    "SELECT id FROM users WHERE email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "Email already registered"
    ]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

/* -----------------------------
   ENSURE pending_users TABLE
------------------------------*/
$conn->query("
    CREATE TABLE IF NOT EXISTS pending_users (
        email VARCHAR(255) PRIMARY KEY,
        full_name VARCHAR(255),
        phone VARCHAR(20),
        password VARCHAR(255),
        role VARCHAR(20) DEFAULT 'customer',
        otp VARCHAR(6),
        otp_expiry DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
// Backfill role column if table already exists without it
@ $conn->query("ALTER TABLE pending_users ADD COLUMN role VARCHAR(20) DEFAULT 'customer'");
// Ensure users table has role column with default
@ $conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'customer'");
@ $conn->query("UPDATE users SET role = 'customer' WHERE role IS NULL");

/* -----------------------------
   HASH PASSWORD & CREATE OTP
------------------------------*/
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$otp = random_int(100000, 999999);
$otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

/* -----------------------------
   UPSERT PENDING USER
------------------------------*/
$pending = $conn->prepare("
    INSERT INTO pending_users (email, full_name, phone, password, role, otp, otp_expiry)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      full_name = VALUES(full_name),
      phone = VALUES(phone),
      password = VALUES(password),
      role = VALUES(role),
      otp = VALUES(otp),
      otp_expiry = VALUES(otp_expiry)
");
$pending->bind_param("sssssss", $email, $name, $phone, $hashedPassword, $role, $otp, $otpExpiry);
if (!$pending->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $pending->error
    ]);
    exit;
}
$pending->close();

/* -----------------------------
   SEND OTP EMAIL
------------------------------*/
$mailConfig = require __DIR__ . '/../mail_config.php';
$mail = new PHPMailer(true);

try {
    if (($mailConfig['smtp']['enabled'] ?? false) === true) {
        $mail->isSMTP();
        $mail->Host       = $mailConfig['smtp']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailConfig['smtp']['username'];
        $mail->Password   = $mailConfig['smtp']['password'];
        $mail->SMTPSecure = $mailConfig['smtp']['secure'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $mailConfig['smtp']['port'];
    }

    $brandName = 'HarborFresh';
    $supportEmail = $mailConfig['from_email'];
    $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
    $mail->addAddress($email, $name);
    $mail->isHTML(true);
    $mail->Subject = "$brandName verification code";
    $mail->Body    = <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; border: 1px solid #e6ecef; border-radius: 16px; padding: 22px; background: #ffffff;">
            <div style="text-align: center; margin-bottom: 14px;">
                <div style="font-size: 22px; font-weight: 700; color: #0F3D4C;">$brandName</div>
                <div style="font-size: 13px; color: #5B6B74; margin-top: 6px;">Dock-to-door verification</div>
            </div>
            <div style="font-size: 16px; color: #0E1B20; margin-bottom: 10px;">Hi $name,</div>
            <div style="font-size: 15px; color: #5B6B74; line-height: 1.6;">
                Use the code below to verify your account. This code expires in <strong>10 minutes</strong>.
            </div>
            <div style="text-align: center; margin: 20px 0;">
                <div style="display: inline-block; padding: 14px 26px; border: 1px dashed #0F3D4C; border-radius: 12px; font-size: 32px; font-weight: 700; color: #0F3D4C; letter-spacing: 4px; background: #F4F7F9;">
                    $otp
                </div>
            </div>
            <div style="font-size: 14px; color: #5B6B74; line-height: 1.6;">
                If you did not request this code, you can safely ignore this email. For help, contact us at $supportEmail.
            </div>
            <div style="margin-top: 18px; font-size: 12px; color: #9AA7AE; text-align: center;">
                Please do not share this code with anyone.
            </div>
        </div>
HTML;
    $mail->send();
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to send OTP: " . $mail->ErrorInfo
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "OTP sent to email. Verify to complete signup."
]);

// Clean up
$conn->close();
