<?php
header("Content-Type: application/json");
require_once "../db.php";
ini_set('display_errors', 0);
error_reporting(0);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? null;
$address = $data['delivery_address'] ?? null;
$slot_id = $data['delivery_slot_id'] ?? null;
$items = $data['items'] ?? [];

if (empty($user_id) || empty($address) || empty($items) || !is_array($items)) {
    echo json_encode([
        "success" => false,
        "message" => "User, address, and items required"
    ]);
    exit;
}

try {
    $conn->begin_transaction();
    $userInfo = $conn->prepare(
        "SELECT full_name, phone FROM users WHERE id = ? LIMIT 1"
    );
    if (!$userInfo) {
        throw new Exception("User lookup failed");
    }
    $userInfo->bind_param("i", $user_id);
    $userInfo->execute();
    $userInfo->bind_result($u_name, $u_phone);
    $userInfo->fetch();
    $userInfo->close();

    $customer_name = $u_name ?: "Customer";
    $customer_phone = $u_phone ?: null;

    $total = 0;
    $total_items = 0;
    $seller_id = null;
    $parsed = [];

    $productStmt = $conn->prepare(
        "SELECT seller_id, product_name, price FROM seller_products WHERE id = ?"
    );
    if (!$productStmt) {
        throw new Exception("Product lookup failed");
    }

    foreach ($items as $item) {
        $product_id = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        if ($product_id <= 0 || $qty <= 0) {
            throw new Exception("Invalid product or quantity");
        }

        $productStmt->bind_param("i", $product_id);
        $productStmt->execute();
        $productStmt->bind_result($p_seller_id, $p_name, $p_price);
        if (!$productStmt->fetch()) {
            throw new Exception("Product not found");
        }
        $productStmt->free_result();

        if ($seller_id === null) {
            $seller_id = $p_seller_id;
        } elseif ($seller_id !== $p_seller_id) {
            throw new Exception("Mixed sellers not supported");
        }

        $unit_price = (float)$p_price;
        $line_price = $unit_price * $qty;
        $total += $line_price;
        $total_items += $qty;

        $parsed[] = [
            "product_id" => $product_id,
            "name" => $p_name,
            "quantity" => $qty,
            "price" => $line_price
        ];
    }
    $productStmt->close();

    if ($seller_id === null) {
        throw new Exception("Seller not found");
    }

    $order_code = "ORD-" . rand(1000, 9999);
    $orderStmt = $conn->prepare(
        "INSERT INTO orders
        (order_code, seller_id, user_id, customer_name, customer_phone, delivery_address, status, delivery_slot_id)
        VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)"
    );

    $slot_param = $slot_id ? (int)$slot_id : 0;
    $orderStmt->bind_param(
        "siisssi",
        $order_code,
        $seller_id,
        $user_id,
        $customer_name,
        $customer_phone,
        $address,
        $slot_param
    );
    $orderStmt->execute();
    $order_id = $orderStmt->insert_id;

    $trackStmt = $conn->prepare(
        "INSERT INTO order_tracking (order_id, status, message) VALUES (?, 'Order Placed', 'Your order has been confirmed')"
    );
    if ($trackStmt) {
        $trackStmt->bind_param("i", $order_id);
        $trackStmt->execute();
        $trackStmt->close();
    }

    $updateTotals = $conn->prepare(
        "UPDATE orders SET subtotal = ?, total_amount = ?, total_items = ? WHERE id = ?"
    );
    $updateTotals->bind_param("ddii", $total, $total, $total_items, $order_id);
    $updateTotals->execute();

    $itemStmt = $conn->prepare(
        "INSERT INTO order_items
        (order_id, product_id, product_name, quantity, price)
        VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($parsed as $p) {
        $itemStmt->bind_param(
            "iisid",
            $order_id,
            $p['product_id'],
            $p['name'],
            $p['quantity'],
            $p['price']
        );
        $itemStmt->execute();
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "order_id" => $order_id,
        "order_code" => $order_code,
        "total_amount" => $total,
        "total_items" => $total_items
    ]);
} catch (Exception $e) {
    try {
        $conn->rollback();
    } catch (Exception $rollbackError) {
        // Ignore rollback errors to preserve JSON response.
    }
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
