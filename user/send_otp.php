<?php
header("Content-Type: application/json");

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mailConfig = require __DIR__ . '/../mail_config.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || empty(trim($data['email']))) {
    echo json_encode(["success" => false, "message" => "Email is required"]);
    exit;
}

$email = trim($data['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email format"]);
    exit;
}

$otp = random_int(100000, 999999);
$expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

$stmt = $conn->prepare("UPDATE users SET otp = ?, otp_expiry = ? WHERE email = ?");
$stmt->bind_param('sss', $otp, $expiry, $email);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    echo json_encode(["success" => false, "message" => "Email not registered"]);
    exit;
}

$mail = new PHPMailer(true);

try {
    $brandName = 'HarborFresh';
    $supportEmail = $mailConfig['from_email'];
    if (($mailConfig['smtp']['enabled'] ?? false) === true) {
        $mail->isSMTP();
        $mail->Host       = $mailConfig['smtp']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailConfig['smtp']['username'];
        $mail->Password   = $mailConfig['smtp']['password'];
        $mail->SMTPSecure = $mailConfig['smtp']['secure'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $mailConfig['smtp']['port'];
    }

    $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "$brandName verification code";
    $mail->Body    = <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; border: 1px solid #e6ecef; border-radius: 16px; padding: 22px; background: #ffffff;">
            <div style="text-align: center; margin-bottom: 14px;">
                <div style="font-size: 22px; font-weight: 700; color: #0F3D4C;">$brandName</div>
                <div style="font-size: 13px; color: #5B6B74; margin-top: 6px;">Dock-to-door verification</div>
            </div>
            <div style="font-size: 16px; color: #0E1B20; margin-bottom: 10px;">Hi,</div>
            <div style="font-size: 15px; color: #5B6B74; line-height: 1.6;">
                Use the code below to verify your account. This code expires in <strong>5 minutes</strong>.
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

    echo json_encode(["success" => true, "message" => "OTP sent to email"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Email failed: " . $mail->ErrorInfo]);
}
?>
