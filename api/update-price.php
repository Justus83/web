<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../admin/auth.php';

header('Content-Type: application/json');

// Must be a logged-in admin — return JSON 401 instead of redirecting
if (!adminId()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$storageId  = (int)   ($body['storage_id'] ?? 0);
$price      = (float) ($body['price']      ?? 0);

if ($storageId <= 0 || $price < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE product_storages SET price = :price WHERE id = :id');
    $stmt->execute([':price' => $price, ':id' => $storageId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
