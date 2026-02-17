<?php
require_once __DIR__ . "/../utils.php";

$body = get_json_body();
$seller = require_seller($conn, $body);

$businessName = isset($body['business_name']) ? clean($body['business_name']) : null;
$location     = isset($body['location']) ? clean($body['location']) : null;
$experience   = isset($body['experience_years']) ? (int)$body['experience_years'] : null;
$specialty    = isset($body['specialty']) ? clean($body['specialty']) : null;
$city         = isset($body['city']) ? clean($body['city']) : null;

if (!$businessName || !$location || $experience === null || $specialty === null) {
    json_response(["success" => false, "message" => "Business name, location, experience, specialty are required"], 422);
}

$stmt = $conn->prepare(
    "INSERT INTO seller_business (seller_id, business_name, location, experience_years, specialty)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        business_name = VALUES(business_name),
        location = VALUES(location),
        experience_years = VALUES(experience_years),
        specialty = VALUES(specialty)"
);
$stmt->bind_param("issis", $seller['id'], $businessName, $location, $experience, $specialty);
$stmt->execute();

if ($city) {
    $updateSeller = $conn->prepare(
        "UPDATE sellers SET business_name = ?, city = ?, updated_at = NOW() WHERE id = ?"
    );
    $updateSeller->bind_param("ssi", $businessName, $city, $seller['id']);
    $updateSeller->execute();
} else {
    $updateSeller = $conn->prepare(
        "UPDATE sellers SET business_name = ?, updated_at = NOW() WHERE id = ?"
    );
    $updateSeller->bind_param("si", $businessName, $seller['id']);
    $updateSeller->execute();
}

record_step($conn, (int)$seller['id'], 2, [
    "business_name" => $businessName,
    "location" => $location,
    "experience_years" => $experience,
    "specialty" => $specialty
]);

json_response([
    "success" => true,
    "message" => "Business details saved",
    "verification_step" => 2
]);
