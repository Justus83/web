<?php
/* ═══════════════════════════════════════════════════════════════════════
   api/submit-review.php — Submit a product review
   POST JSON: { product_id, name, rating, body }
   Returns:   { success, review } or { error }
   ═══════════════════════════════════════════════════════════════════════ */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: same-origin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$productId  = (int) ($data['product_id'] ?? 0);
$name       = mb_substr(trim($data['name']   ?? ''), 0, 100);
$rating     = (int) ($data['rating'] ?? 5);
$body       = mb_substr(trim($data['body']   ?? ''), 0, 2000);
$customerId = authId();

if ($productId <= 0)           { http_response_code(422); echo json_encode(['error' => 'Invalid product']); exit; }
if ($name === '')               { http_response_code(422); echo json_encode(['error' => 'Name is required']); exit; }
if ($body === '')               { http_response_code(422); echo json_encode(['error' => 'Review text is required']); exit; }
if ($rating < 1 || $rating > 5){ http_response_code(422); echo json_encode(['error' => 'Rating must be 1–5']); exit; }

// Verify product exists
$chk = $pdo->prepare('SELECT id FROM products WHERE id = ?');
$chk->execute([$productId]);
if (!$chk->fetch()) { http_response_code(404); echo json_encode(['error' => 'Product not found']); exit; }

try {
    $stmt = $pdo->prepare("
        INSERT INTO product_reviews (product_id, customer_id, name, rating, body, approved)
        VALUES (:pid, :cid, :name, :rating, :body, 1)
    ");
    $stmt->execute([
        ':pid'    => $productId,
        ':cid'    => $customerId,
        ':name'   => $name,
        ':rating' => $rating,
        ':body'   => $body,
    ]);

    echo json_encode([
        'success' => true,
        'review'  => [
            'name'       => $name,
            'rating'     => $rating,
            'body'       => $body,
            'created_at' => date('d M Y'),
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save review. Please try again.']);
}
