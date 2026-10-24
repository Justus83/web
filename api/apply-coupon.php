<?php
/* ═══════════════════════════════════════════════════════════════════════
   api/apply-coupon.php — Validate a coupon code against the DB
   POST JSON: { code: "SAVE10" }
   Returns:   { success, code, discount_pct } or { error }
   ═══════════════════════════════════════════════════════════════════════ */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($data['code'] ?? ''));

if ($code === '') {
    echo json_encode(['error' => 'Please enter a coupon code.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, code, discount_pct, max_uses, used_count, expires_at, active
    FROM coupons
    WHERE code = :code
");
$stmt->execute([':code' => $code]);
$coupon = $stmt->fetch();

if (!$coupon) {
    echo json_encode(['error' => 'Invalid coupon code.']);
    exit;
}

if (!$coupon['active']) {
    echo json_encode(['error' => 'This coupon is no longer active.']);
    exit;
}

if ($coupon['expires_at'] !== null && strtotime($coupon['expires_at']) < time()) {
    echo json_encode(['error' => 'This coupon has expired.']);
    exit;
}

if ($coupon['max_uses'] !== null && $coupon['used_count'] >= $coupon['max_uses']) {
    echo json_encode(['error' => 'This coupon has reached its usage limit.']);
    exit;
}

echo json_encode([
    'success'      => true,
    'code'         => $coupon['code'],
    'discount_pct' => (int) $coupon['discount_pct'],
]);
