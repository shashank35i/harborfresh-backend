<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../utils.php";

$pending = [];
// Latest identity row per seller; anything not approved/rejected is considered pending
$result = $conn->query("
    SELECT s.id,
           s.full_name,
           s.business_email,
           s.phone,
           siv.police_verification_status,
           siv.verification_status,
           siv.face_match_verified,
           siv.liveness_verified,
           siv.aadhaar_verified
    FROM seller_identity_verification siv
    INNER JOIN (
        SELECT seller_id, MAX(id) AS max_id
        FROM seller_identity_verification
        GROUP BY seller_id
    ) latest ON latest.max_id = siv.id
    INNER JOIN sellers s ON s.id = siv.seller_id
    WHERE COALESCE(siv.verification_status, 'pending') NOT IN ('approved','rejected')
    ORDER BY siv.created_at DESC
");

while ($row = $result->fetch_assoc()) {
    $pending[] = $row;
}

// Counts based on latest rows per seller
$countsQuery = $conn->query("
    SELECT CASE WHEN verification_status='approved' THEN 'approved'
                WHEN verification_status='rejected' THEN 'rejected'
                ELSE 'pending' END AS bucket, COUNT(*) c
    FROM (
        SELECT t.verification_status
        FROM seller_identity_verification t
        INNER JOIN (SELECT seller_id, MAX(id) AS max_id FROM seller_identity_verification GROUP BY seller_id) latest
            ON latest.max_id = t.id
    ) latest_rows
    GROUP BY bucket
");
$counts = ["pending"=>0,"approved"=>0,"rejected"=>0];
while ($row = $countsQuery->fetch_assoc()) {
    $counts[$row['bucket']] = (int)$row['c'];
}

echo json_encode([
    "success" => true,
    "pending" => $pending,
    "counts" => $counts
]);
