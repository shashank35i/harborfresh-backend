<?php
require_once __DIR__ . "/../../utils.php";

$body = get_json_body();
$seller = require_seller($conn, $body);

$status = onboarding_status($conn, (int)$seller['id']);

json_response([
    "success" => true,
    "data" => $status
]);
