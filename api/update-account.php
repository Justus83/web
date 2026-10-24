<?php
/* ═══════════════════════════════════════════════════════════════════════
   api/update-account.php — Update address or account details
   POST JSON: { action: 'address'|'details', ...fields }
   Returns:   { success } or { error }
   ═══════════════════════════════════════════════════════════════════════ */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$customerId = authId();
if (!$customerId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$action = $data['action'] ?? '';

/* ── Update address ────────────────────────────────────────────────── */
if ($action === 'address') {
    $address  = mb_substr(trim($data['address']  ?? ''), 0, 500);
    $city     = mb_substr(trim($data['city']     ?? ''), 0, 150);
    $district = mb_substr(trim($data['district'] ?? ''), 0, 150);

    $pdo->prepare("UPDATE customers SET address=:a, city=:c, district=:d WHERE id=:id")
        ->execute([':a' => $address ?: null, ':c' => $city ?: null, ':d' => $district ?: null, ':id' => $customerId]);

    echo json_encode(['success' => true]);
    exit;
}

/* ── Update details (name / phone / email / optional password) ─────── */
if ($action === 'details') {
    $name    = mb_substr(trim($data['name']  ?? ''), 0, 255);
    $phone   = normalisePhone(trim($data['phone'] ?? ''));
    $email   = mb_substr(trim($data['email'] ?? ''), 0, 255);
    $curPass = $data['current_password']      ?? '';
    $newPass = $data['new_password']           ?? '';
    $newConf = $data['new_password_confirm']   ?? '';

    if ($name === '') { echo json_encode(['error' => 'Name is required.']); exit; }
    if ($phone === '') { echo json_encode(['error' => 'Phone is required.']); exit; }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Invalid email address.']); exit;
    }

    /* Check phone uniqueness (excluding self) */
    $s = $pdo->prepare('SELECT id FROM customers WHERE phone = ? AND id != ?');
    $s->execute([$phone, $customerId]);
    if ($s->fetch()) { echo json_encode(['error' => 'That phone number is already used by another account.']); exit; }

    /* Check email uniqueness (excluding self, only if provided) */
    if ($email !== '') {
        $s = $pdo->prepare('SELECT id FROM customers WHERE email = ? AND id != ?');
        $s->execute([$email, $customerId]);
        if ($s->fetch()) { echo json_encode(['error' => 'That email is already used by another account.']); exit; }
    }

    /* Password change requested */
    $newHash = null;
    if ($newPass !== '') {
        if (strlen($newPass) < 6) { echo json_encode(['error' => 'New password must be at least 6 characters.']); exit; }
        if ($newPass !== $newConf) { echo json_encode(['error' => 'New passwords do not match.']); exit; }

        /* Verify current password */
        $s = $pdo->prepare('SELECT password_hash FROM customers WHERE id = ?');
        $s->execute([$customerId]);
        $row = $s->fetch();
        if (!$row || !password_verify($curPass, $row['password_hash'])) {
            echo json_encode(['error' => 'Current password is incorrect.']); exit;
        }
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    }

    /* Build update query */
    if ($newHash) {
        $pdo->prepare("UPDATE customers SET name=:n, phone=:p, email=:e, password_hash=:h WHERE id=:id")
            ->execute([':n' => $name, ':p' => $phone, ':e' => $email ?: null, ':h' => $newHash, ':id' => $customerId]);
    } else {
        $pdo->prepare("UPDATE customers SET name=:n, phone=:p, email=:e WHERE id=:id")
            ->execute([':n' => $name, ':p' => $phone, ':e' => $email ?: null, ':id' => $customerId]);
    }

    /* Refresh session name */
    $_SESSION['customer_name']  = $name;
    $_SESSION['customer_phone'] = $phone;

    echo json_encode(['success' => true, 'reload' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action.']);
