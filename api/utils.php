<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../seller/helpers.php";

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function get_json_body(): array {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function seller_id_from_request(array $body = []): ?int {
    $id = $_SERVER['HTTP_X_SELLER_ID'] ?? null;
    if ($id === null && isset($_GET['seller_id'])) {
        $id = $_GET['seller_id'];
    }
    if ($id === null && isset($_POST['seller_id'])) {
        $id = $_POST['seller_id'];
    }
    if ($id === null && isset($body['seller_id'])) {
        $id = $body['seller_id'];
    }

    $id = $id !== null ? (int)$id : null;
    return ($id && $id > 0) ? $id : null;
}

function admin_id_from_request(array $body = []): ?int {
    $id = $_SERVER['HTTP_X_ADMIN_ID'] ?? null;
    if ($id === null && isset($_GET['admin_id'])) {
        $id = $_GET['admin_id'];
    }
    if ($id === null && isset($_POST['admin_id'])) {
        $id = $_POST['admin_id'];
    }
    if ($id === null && isset($body['admin_id'])) {
        $id = $body['admin_id'];
    }

    $id = $id !== null ? (int)$id : null;
    return ($id && $id > 0) ? $id : null;
}

function require_seller(mysqli $conn, array $body = []): array {
    $sellerId = seller_id_from_request($body);
    if (!$sellerId) {
        json_response(["success" => false, "message" => "Seller authentication required"], 401);
    }

    $stmt = $conn->prepare(
        "SELECT id, full_name, business_email, phone, status, verification_step, is_verified, business_name, city
         FROM sellers WHERE id = ?"
    );
    $stmt->bind_param("i", $sellerId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        json_response(["success" => false, "message" => "Seller not found"], 404);
    }
    return $res->fetch_assoc();
}

function require_admin(mysqli $conn, array $body = []): array {
    $adminId = admin_id_from_request($body);
    if (!$adminId) {
        json_response(["success" => false, "message" => "Admin authentication required"], 401);
    }

    $stmt = $conn->prepare("SELECT id, username FROM admins WHERE id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        json_response(["success" => false, "message" => "Admin not found"], 404);
    }
    return $res->fetch_assoc();
}

function bump_verification_step(mysqli $conn, int $sellerId, int $step): void {
    $stmt = $conn->prepare(
        "UPDATE sellers
         SET verification_step = GREATEST(verification_step, ?), updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->bind_param("ii", $step, $sellerId);
    $stmt->execute();
}

function log_step(mysqli $conn, int $sellerId, int $step, string $status = 'completed', $data = null): void {
    $payload = $data ? json_encode($data) : null;
    $stmt = $conn->prepare(
        "INSERT INTO seller_verification_logs (seller_id, step, status, data)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("iiss", $sellerId, $step, $status, $payload);
    $stmt->execute();
}

function record_step(mysqli $conn, int $sellerId, int $step, $data = null): void {
    bump_verification_step($conn, $sellerId, $step);
    log_step($conn, $sellerId, $step, 'completed', $data);
}

function save_uploaded_file(array $file, string $folder, array $allowed = ['jpg', 'jpeg', 'png', 'pdf'], int $maxSize = 10485760): string {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        json_response(["success" => false, "message" => "File upload error"], 422);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        json_response(["success" => false, "message" => "Invalid file type"], 422);
    }

    if ($file['size'] > $maxSize) {
        json_response(["success" => false, "message" => "File too large"], 422);
    }

    $dir = __DIR__ . "/../uploads/{$folder}/";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $name = time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    $relative = "uploads/{$folder}/{$name}";
    $absolute = __DIR__ . "/../" . $relative;

    if (!move_uploaded_file($file['tmp_name'], $absolute)) {
        json_response(["success" => false, "message" => "Unable to save file"], 500);
    }

    return $relative;
}

function onboarding_status(mysqli $conn, int $sellerId): array {
    $stmt = $conn->prepare(
        "SELECT id, full_name, business_email, phone, status, verification_step, is_verified, business_name, city
         FROM sellers WHERE id = ?"
    );
    $stmt->bind_param("i", $sellerId);
    $stmt->execute();
    $seller = $stmt->get_result()->fetch_assoc();

    $business = $conn->prepare(
        "SELECT business_name, location, experience_years, specialty FROM seller_business WHERE seller_id = ? LIMIT 1"
    );
    $business->bind_param("i", $sellerId);
    $business->execute();
    $businessRow = $business->get_result()->fetch_assoc();

    $legal = $conn->prepare(
        "SELECT fishing_license, gst_number FROM seller_legal WHERE seller_id = ? LIMIT 1"
    );
    $legal->bind_param("i", $sellerId);
    $legal->execute();
    $legalRow = $legal->get_result()->fetch_assoc();

    $docs = $conn->prepare(
        "SELECT fishing_license_doc, government_id_doc, address_proof_doc FROM seller_documents WHERE seller_id = ? LIMIT 1"
    );
    $docs->bind_param("i", $sellerId);
    $docs->execute();
    $docsRow = $docs->get_result()->fetch_assoc();

    $identity = $conn->prepare(
        "SELECT aadhaar_number, aadhaar_name, aadhaar_doc, selfie_image,
                aadhaar_verified, liveness_verified, face_match_verified, face_match_score,
                police_verification_status, verification_status
         FROM seller_identity_verification WHERE seller_id = ? ORDER BY id DESC LIMIT 1"
    );
    $identity->bind_param("i", $sellerId);
    $identity->execute();
    $identityRow = $identity->get_result()->fetch_assoc();

    return [
        "seller" => [
            "id" => (int)$seller['id'],
            "full_name" => $seller['full_name'],
            "business_email" => $seller['business_email'],
            "phone" => $seller['phone'],
            "status" => $seller['status'],
            "verification_step" => (int)$seller['verification_step'],
            "is_verified" => (int)$seller['is_verified']
        ],
        "personal_done" => !empty($seller['full_name']) && !empty($seller['phone']),
        "business_done" => !empty($businessRow),
        "legal_done" => !empty($legalRow),
        "documents" => [
            "fishing_license" => !empty($docsRow['fishing_license_doc'] ?? null),
            "government_id" => !empty($docsRow['government_id_doc'] ?? null),
            "address_proof" => !empty($docsRow['address_proof_doc'] ?? null)
        ],
        "identity" => [
            "aadhaar_number" => $identityRow['aadhaar_number'] ?? null,
            "aadhaar_name" => $identityRow['aadhaar_name'] ?? null,
            "aadhaar_doc" => $identityRow['aadhaar_doc'] ?? null,
            "selfie_image" => $identityRow['selfie_image'] ?? null,
            "aadhaar_verified" => $identityRow['aadhaar_verified'] ?? 0,
            "liveness_verified" => $identityRow['liveness_verified'] ?? 0,
            "face_match_verified" => $identityRow['face_match_verified'] ?? 0,
            "face_match_score" => isset($identityRow['face_match_score']) ? (float)$identityRow['face_match_score'] : null,
            "police_verification_status" => $identityRow['police_verification_status'] ?? "pending",
            "verification_status" => $identityRow['verification_status'] ?? "in_progress"
        ]
    ];
}
