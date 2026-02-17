<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../utils.php';

$body = get_json_body();
$sellerId = isset($body['seller_id']) ? (int)$body['seller_id'] : (isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : null);
if (!$sellerId) {
    json_response(['success' => false, 'message' => 'seller_id required'], 422);
}

$adminId = admin_id_from_request($body);
if ($adminId) {
    require_admin($conn, $body);
}

// Reuse onboarding_status aggregator for detail
$detail = onboarding_status($conn, $sellerId);
json_response(['success' => true, 'data' => $detail]);
?>
