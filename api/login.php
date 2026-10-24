<?php
/* ═══════════════════════════════════════════════════════════════════════
   api/login.php — Authenticate a customer
   POST JSON: { phone, password }
   Returns:   { success, customer_name } or { error }
   ═══════════════════════════════════════════════════════════════════════ */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$phone    = normalisePhone(trim($data['phone']    ?? ''));
$password = $data['password'] ?? '';

if ($phone === '' || $password === '') {
    echo json_encode(['error' => 'Phone number and password are required.']);
    exit;
}

/* ── Look up by phone ─────────────────────────────────────────────── */
$stmt = $pdo->prepare(
    'SELECT id, name, phone, password_hash FROM customers WHERE phone = ?'
);
$stmt->execute([$phone]);
$customer = $stmt->fetch();

/* ── Use a timing-safe comparison even on miss (prevents enumeration) */
$dummyHash = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234';
$hashToCheck = $customer ? $customer['password_hash'] : $dummyHash;

if (!$customer || !password_verify($password, $hashToCheck)) {
    /* Generic message — don't reveal whether the phone exists */
    echo json_encode(['error' => 'Incorrect phone number or password.']);
    exit;
}

/* ── Upgrade hash if needed (e.g. cost factor changed) ───────────── */
if (password_needs_rehash($customer['password_hash'], PASSWORD_DEFAULT)) {
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE customers SET password_hash = ? WHERE id = ?')
        ->execute([$newHash, $customer['id']]);
}

authLogin($customer);

echo json_encode(['success' => true, 'customer_name' => $customer['name']]);
