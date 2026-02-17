<?php
header("Content-Type: application/json");
require_once "../db.php";

/* ------------------------------------------------
   READ INPUT (FORM + JSON – GUARANTEED)
-------------------------------------------------*/

// Always read raw input first
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

// Read values from ALL possible sources
$user_id = $_POST['user_id']
        ?? $_GET['user_id']
        ?? ($data['user_id'] ?? null);

$address_id = $_POST['address_id']
           ?? $_GET['address_id']
           ?? ($data['address_id'] ?? null);

// Final validation
if (empty($user_id) || empty($address_id)) {
    echo json_encode([
        "success" => false,
        "message" => "User ID & Address required",
        "debug" => [
            "POST" => $_POST,
            "RAW" => $rawInput
        ]
    ]);
    exit;
}

/* ------------------------------------------------
   START TRANSACTION
-------------------------------------------------*/
$conn->begin_transaction();

try {

    /* ------------------------------------------------
       Ensure orders has user_id column (one-time)
    -------------------------------------------------*/
    @ $conn->query("ALTER TABLE orders ADD COLUMN user_id int(11) NULL DEFAULT NULL");

    /* ------------------------------------------------
       CHECK ADDRESS
    -------------------------------------------------*/
    $addrStmt = $conn->prepare(
        "SELECT address FROM user_addresses WHERE id = ?"
    );
    $addrStmt->bind_param("i", $address_id);
    $addrStmt->execute();
    $addr = $addrStmt->get_result()->fetch_assoc();

    if (!$addr) {
        throw new Exception("Invalid address");
    }

    /* ------------------------------------------------
       GET CART ITEMS
    -------------------------------------------------*/
    $cart = $conn->query(
        "SELECT * FROM cart WHERE user_id = $user_id"
    );

    if ($cart->num_rows === 0) {
        throw new Exception("Cart is empty");
    }

    /* ------------------------------------------------
       CALCULATE TOTAL & SELLER
    -------------------------------------------------*/
    $total = 0;
    $total_items = 0;
    $seller_id = null;

    while ($c = $cart->fetch_assoc()) {
        $total += (float)$c['price'];
        $total_items += (int)$c['quantity'];

        if ($seller_id === null) {
            $s = $conn->query(
                "SELECT seller_id FROM products WHERE id = {$c['product_id']}"
            )->fetch_assoc();

            if (!$s || !$s['seller_id']) {
                throw new Exception("Seller not found for product");
            }

            $seller_id = $s['seller_id'];
        }
    }

    /* ------------------------------------------------
       USER INFO
    -------------------------------------------------*/
    $userInfo = $conn->prepare(
        "SELECT full_name, phone FROM users WHERE id = ? LIMIT 1"
    );
    $userInfo->bind_param("i", $user_id);
    $userInfo->execute();
    $userRow = $userInfo->get_result()->fetch_assoc();

    $customer_name = $userRow['name'] ?? "Customer";
    $customer_phone = $userRow['phone'] ?? null;

    /* ------------------------------------------------
       CREATE ORDER
    -------------------------------------------------*/
    $order_code = "ORD-" . rand(1000, 9999);

    $orderStmt = $conn->prepare(
        "INSERT INTO orders
        (order_code, seller_id, user_id, customer_name, customer_phone, delivery_address, status)
        VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
    );

    $orderStmt->bind_param(
        "siisss",
        $order_code,
        $seller_id,
        $user_id,
        $customer_name,
        $customer_phone,
        $addr['address']
    );
    $orderStmt->execute();

    $order_id = $orderStmt->insert_id;

    // Store totals
    $updateTotals = $conn->prepare(
        "UPDATE orders SET total_amount = ?, total_items = ? WHERE id = ?"
    );
    $updateTotals->bind_param("dii", $total, $total_items, $order_id);
    $updateTotals->execute();

    /* ------------------------------------------------
       INSERT ORDER ITEMS
    -------------------------------------------------*/
    $cart->data_seek(0);

    while ($c = $cart->fetch_assoc()) {

        // Product name
        $p = $conn->query(
            "SELECT name FROM products WHERE id = {$c['product_id']}"
        )->fetch_assoc();

        if (!$p) {
            throw new Exception("Product not found");
        }

        // Cut name (optional)
        $cut_name = null;
        if (!empty($c['cut_id'])) {
            $cut = $conn->query(
                "SELECT cut_name FROM product_cuts WHERE id = {$c['cut_id']}"
            )->fetch_assoc();
            $cut_name = $cut['cut_name'] ?? null;
        }

        $itemStmt = $conn->prepare(
            "INSERT INTO order_items
            (order_id, product_id, product_name, cut_name, quantity, price)
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        $itemStmt->bind_param(
            "iisssd",
            $order_id,
            $c['product_id'],
            $p['name'],
            $cut_name,
            $c['quantity'],
            $c['price']
        );

        $itemStmt->execute();
    }

    /* ------------------------------------------------
       CLEAR CART
    -------------------------------------------------*/
    $conn->query(
        "DELETE FROM cart WHERE user_id = $user_id"
    );

    /* ------------------------------------------------
       COMMIT
    -------------------------------------------------*/
    $conn->commit();

    echo json_encode([
        "success" => true,
        "order_code" => $order_code,
        "total" => $total
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
