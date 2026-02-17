<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../utils.php';

$body = get_json_body();
$sellerId = isset($body['seller_id']) ? (int)$body['seller_id'] : (isset($_POST['seller_id']) ? (int)$_POST['seller_id'] : null);
$status = isset($body['status']) ? strtolower($body['status']) : (isset($_POST['status']) ? strtolower($_POST['status']) : null);

if (!$sellerId || !$status) {
    json_response(['success' => false, 'message' => 'seller_id and status are required'], 422);
}

if (!in_array($status, ['approved','rejected'], true)) {
    json_response(['success' => false, 'message' => 'Status must be approved or rejected'], 422);
}

$isVerified = $status === 'approved' ? 1 : 0;

$updateSeller = $conn->prepare("UPDATE sellers SET status=?, is_verified=?, updated_at=NOW() WHERE id=?");
$updateSeller->bind_param('sii', $status, $isVerified, $sellerId);
$updateSeller->execute();

// Upsert latest identity row and drop duplicates
$existing = $conn->query("SELECT id FROM seller_identity_verification WHERE seller_id=$sellerId ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($status === 'approved') {
    if ($existing) {
        $conn->query("UPDATE seller_identity_verification 
            SET verification_status='$status',
                aadhaar_verified=1,
                liveness_verified=1,
                face_match_verified=1,
                police_verification_status='verified'
            WHERE id={$existing['id']}");
        $conn->query("DELETE FROM seller_identity_verification WHERE seller_id=$sellerId AND id <> {$existing['id']}");
    } else {
        $conn->query("INSERT INTO seller_identity_verification (seller_id, verification_status, aadhaar_verified, liveness_verified, face_match_verified, police_verification_status) VALUES ($sellerId, '$status', 1, 1, 1, 'verified')");
    }
} else { // rejected
    if ($existing) {
        $conn->query("UPDATE seller_identity_verification SET verification_status='$status' WHERE id={$existing['id']}");
        $conn->query("DELETE FROM seller_identity_verification WHERE seller_id=$sellerId AND id <> {$existing['id']}");
    } else {
        $conn->query("INSERT INTO seller_identity_verification (seller_id, verification_status) VALUES ($sellerId, '$status')");
    }
}

log_step($conn, $sellerId, 9, 'admin_update', ['status' => $status]);

json_response(['success' => true, 'message' => "Seller $status", 'is_verified' => $isVerified]);
?>
