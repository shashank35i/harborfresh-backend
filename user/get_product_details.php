<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../db.php"; // Use a robust include path

// --- Main Logic in a Try/Catch Block for Graceful Error Handling ---
try {

    // 1. Validate Product ID
    if (empty($_GET['product_id'])) {
        throw new Exception("Product ID required");
    }
    $product_id = intval($_GET['product_id']);
    if ($product_id <= 0) {
        throw new Exception("Invalid Product ID");
    }

    // 2. Get Main Product and Seller Details with a Prepared Statement
    $productStmt = $conn->prepare(
        "SELECT p.id, p.name, p.price_per_kg, p.freshness, p.rating, p.image,
                s.id AS seller_id, s.full_name, s.business_name, s.city, s.rating AS seller_rating, s.is_verified
         FROM products p
         LEFT JOIN sellers s ON p.seller_id = s.id
         WHERE p.id = ? AND p.is_active = 1"
    );
    $productStmt->bind_param("i", $product_id);
    $productStmt->execute();
    $productResult = $productStmt->get_result();
    $product = $productResult->fetch_assoc();

    if (!$product) {
        throw new Exception("Product not found");
    }

    // 3. Get Cleaning Options with a Prepared Statement
    $cutsStmt = $conn->prepare(
        "SELECT id, cut_name, description, extra_price
         FROM product_cuts
         WHERE product_id = ?"
    );
    $cutsStmt->bind_param("i", $product_id);
    $cutsStmt->execute();
    $cutsResult = $cutsStmt->get_result();

    $options = [];
    while ($row = $cutsResult->fetch_assoc()) {
        $options[] = $row;
    }

    // 4. Assemble the final JSON response
    echo json_encode([
        "success" => true,
        "product" => [
            "id" => (int)$product['id'],
            "name" => $product['name'],
            "price_per_kg" => $product['price_per_kg'],
            "freshness" => $product['freshness'],
            "rating" => $product['rating'],
            "image" => $product['image'],
            // It's better to fetch 'about' from the DB, but using a placeholder as per your original file
            "about" => "Pre-cleaned squid rings and tentacles. Ready to cook for calamari or stir-fry."
        ],
        "seller" => [
            "id" => isset($product['seller_id']) ? (int)$product['seller_id'] : null,
            "name" => $product['full_name'],
            "business" => $product['business_name'],
            "city" => $product['city'],
            "rating" => $product['seller_rating'],
            "verified" => (bool)$product['is_verified']
        ],
        "cuts" => $options
    ]);

} catch (Exception $e) {
    // If ANY error occurs, send a clean JSON failure message instead of hanging
    http_response_code(400); // Set a bad request status code
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} finally {
    // Ensure the database connection is always closed
    if (isset($conn)) {
        $conn->close();
    }
}
?>
