<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../utils.php';

// Optional admin check
$body = get_json_body();
$adminId = admin_id_from_request($body);
if ($adminId) {
    require_admin($conn, $body);
}

$sql = "
    SELECT s.id, s.full_name, s.business_email, s.phone, s.status,
           siv.verification_status, siv.police_verification_status,
           siv.face_match_verified, siv.liveness_verified, siv.aadhaar_verified,
           siv.created_at
    FROM sellers s
    LEFT JOIN (
        SELECT t.* FROM seller_identity_verification t
        INNER JOIN (
            SELECT seller_id, MAX(id) AS max_id FROM seller_identity_verification GROUP BY seller_id
        ) latest ON latest.max_id = t.id
    ) siv ON siv.seller_id = s.id
    ORDER BY (siv.created_at IS NULL), siv.created_at DESC, s.id DESC";

$result = $conn->query($sql);
$sellers = [];
while ($row = $result->fetch_assoc()) {
    $sellers[] = $row;
}

echo json_encode([
    'success' => true,
    'sellers' => $sellers
]);
?>

