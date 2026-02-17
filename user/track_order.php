<?php
header("Content-Type: application/json");
require_once "../db.php";
ini_set('display_errors', 0);
error_reporting(0);

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    echo json_encode([
        "success" => false,
        "message" => "Order ID required"
    ]);
    exit;
}

/* ---------------------------
   ORDER + DELIVERY PARTNER
----------------------------*/
$order_sql = "
    SELECT 
        o.order_code,
        o.status,
        o.current_lat,
        o.current_lng,
        o.customer_lat,
        o.customer_lng,
        o.is_live,
        s.city AS seller_city,
        s.business_name AS seller_name,
        dp.name AS partner_name,
        dp.phone,
        dp.rating
    FROM orders o
    LEFT JOIN sellers s
        ON o.seller_id = s.id
    LEFT JOIN delivery_partners dp 
        ON o.delivery_partner_id = dp.id
    WHERE o.id = ?
";

$stmt = $conn->prepare($order_sql);
if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Order query failed"
    ]);
    exit;
}
$stmt->bind_param("i", $order_id);
$stmt->execute();
$stmt->bind_result(
    $order_code,
    $order_status,
    $current_lat,
    $current_lng,
    $customer_lat,
    $customer_lng,
    $is_live,
    $seller_city,
    $seller_name,
    $partner_name,
    $partner_phone,
    $partner_rating
);
$hasOrder = $stmt->fetch();

if (!$hasOrder) {
    echo json_encode([
        "success" => false,
        "message" => "Order not found"
    ]);
    exit;
}
// Use the actual status column (first status in select).
$order = [
    "order_code" => $order_code,
    "status" => $order_status,
    "current_lat" => $current_lat,
    "current_lng" => $current_lng,
    "customer_lat" => $customer_lat,
    "customer_lng" => $customer_lng,
    "is_live" => $is_live,
    "seller_city" => $seller_city,
    "seller_name" => $seller_name,
    "partner_name" => $partner_name,
    "phone" => $partner_phone,
    "rating" => $partner_rating
];

/* ---------------------------
   SELLER LOCATION (PRODUCT)
----------------------------*/
$loc_sql = "
    SELECT sp.location_name, sp.latitude, sp.longitude
    FROM order_items oi
    INNER JOIN seller_products sp ON sp.id = oi.product_id
    WHERE oi.order_id = ?
    LIMIT 1
";
$loc_stmt = $conn->prepare($loc_sql);
$seller_location_name = null;
$seller_lat = null;
$seller_lng = null;
if ($loc_stmt) {
    $loc_stmt->bind_param("i", $order_id);
    $loc_stmt->execute();
    $loc_stmt->bind_result($seller_location_name, $seller_lat, $seller_lng);
    $loc_stmt->fetch();
    $loc_stmt->close();
}

/* ---------------------------
   ORDER TIMELINE
----------------------------*/
$timeline_sql = "
    SELECT status, message, created_at
    FROM order_tracking
    WHERE order_id = ?
    ORDER BY created_at ASC
";

$tstmt = $conn->prepare($timeline_sql);
if ($tstmt) {
    $tstmt->bind_param("i", $order_id);
    $tstmt->execute();
}

$timeline = [];
if ($tstmt) {
    $tstmt->bind_result($t_status, $t_message, $t_created);
    while ($tstmt->fetch()) {
        $timeline[] = [
            "status" => $t_status,
            "message" => $t_message,
            "created_at" => $t_created
        ];
    }
}

/* ---------------------------
   RESPONSE
----------------------------*/
echo json_encode([
    "success" => true,
    "order_code" => $order['order_code'],
    "status" => $order['status'],
    "is_live" => (bool)$order['is_live'],
    "map" => [
        "delivery_lat" => $order['current_lat'],
        "delivery_lng" => $order['current_lng'],
        "customer_lat" => $order['customer_lat'],
        "customer_lng" => $order['customer_lng'],
        "seller_lat" => $seller_lat,
        "seller_lng" => $seller_lng,
        "seller_location" => $seller_location_name
    ],
    "delivery_partner" => [
        "name" => $order['partner_name'],
        "phone" => $order['phone'],
        "rating" => $order['rating']
    ],
    "seller" => [
        "name" => $order['seller_name'],
        "city" => $order['seller_city']
    ],
    "timeline" => $timeline
]);
