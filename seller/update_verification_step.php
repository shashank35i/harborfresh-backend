<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php";

// Expected JSON: seller_id, step (1-9), status (optional), data (optional)
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['seller_id']) || empty($input['step'])) {
    echo json_encode(["success"=>false,"message"=>"seller_id and step are required"]);
    exit;
}

$seller_id = (int)$input['seller_id'];
$step      = (int)$input['step'];
$status    = isset($input['status']) ? trim($input['status']) : 'completed';
$dataJson  = isset($input['data']) ? json_encode($input['data']) : null;

if ($step < 1 || $step > 9) {
    echo json_encode(["success"=>false,"message"=>"Invalid step"]);
    exit;
}

// Update seller current step
$stmt = $conn->prepare("UPDATE sellers SET verification_step = ?, status = IF(? >= 9, 'submitted', status) WHERE id = ?");
$stmt->bind_param("iii", $step, $step, $seller_id);
$stmt->execute();

// Log entry
$log = $conn->prepare("INSERT INTO seller_verification_logs (seller_id, step, status, data) VALUES (?,?,?,?)");
$log->bind_param("iiss", $seller_id, $step, $status, $dataJson);
$log->execute();

// Touch overall verification table
$conn->query("INSERT INTO seller_verification (seller_id) VALUES ($seller_id) ON DUPLICATE KEY UPDATE verified_at = verified_at");

// Mark identity verification progress if exists
$conn->query("UPDATE seller_identity_verification SET verification_status = 'in_progress' WHERE seller_id=$seller_id");

// Optional: flag completion
if ($step >= 9) {
    $conn->query("UPDATE seller_identity_verification SET verification_status='submitted' WHERE seller_id=$seller_id");
}

echo json_encode(["success"=>true,"message"=>"Step updated","step"=>$step]);
?>
