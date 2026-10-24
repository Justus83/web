<?php
/* ═══════════════════════════════════════════════════════════════════════
   api/register.php — Create a new customer account
   POST JSON: { name, phone, email?, password, password_confirm }
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

/* ── Validate ──────────────────────────────────────────────────────── */
$name     = trim($data['name']             ?? '');
$phone    = normalisePhone(trim($data['phone'] ?? ''));
$email    = trim($data['email']            ?? '');
$password = $data['password']              ?? '';
$confirm  = $data['password_confirm']      ?? '';

if ($name === '') {
    echo json_encode(['error' => 'Full name is required.']); exit;
}
if (strlen($name) < 2) {
    echo json_encode(['error' => 'Name must be at least 2 characters.']); exit;
}
if ($phone === '') {
    echo json_encode(['error' => 'Phone number is required.']); exit;
}
if (!preg_match('/^\+?[\d]{9,15}$/', $phone)) {
    echo json_encode(['error' => 'Please enter a valid phone number.']); exit;
}
if (strlen($password) < 6) {
    echo json_encode(['error' => 'Password must be at least 6 characters.']); exit;
}
if ($password !== $confirm) {
    echo json_encode(['error' => 'Passwords do not match.']); exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Please enter a valid email address.']); exit;
}

/* ── Check phone uniqueness ───────────────────────────────────────── */
$stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = ?');
$stmt->execute([$phone]);
if ($stmt->fetch()) {
    echo json_encode(['error' => 'That phone number is already registered. Please sign in.']);
    exit;
}

/* ── Check email uniqueness (only if provided) ────────────────────── */
if ($email !== '') {
    $stmt = $pdo->prepare('SELECT id FROM customers WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['error' => 'That email is already registered. Please sign in.']);
        exit;
    }
}

/* ── Insert ────────────────────────────────────────────────────────── */
try {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO customers (name, phone, email, password_hash)
        VALUES (:name, :phone, :email, :hash)
    ");
    $stmt->execute([
        ':name'  => mb_substr($name, 0, 255),
        ':phone' => mb_substr($phone, 0, 50),
        ':email' => $email !== '' ? mb_substr($email, 0, 255) : null,
        ':hash'  => $hash,
    ]);

    $customerId = (int) $pdo->lastInsertId();

    authLogin([
        'id'    => $customerId,
        'name'  => $name,
        'phone' => $phone,
    ]);

    echo json_encode(['success' => true, 'customer_name' => $name]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed. Please try again.']);
}
