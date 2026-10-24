<?php
/* ═══════════════════════════════════════════════════════════════════════
   config/auth.php — PHP session auth helpers
   Include this wherever you need auth awareness.
   Always require db.php first.
   ═══════════════════════════════════════════════════════════════════════ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ── Start a session for a customer ───────────────────────────────── */
function authLogin(array $customer): void {
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['customer_id']   = $customer['id'];
    $_SESSION['customer_name'] = $customer['name'];
    $_SESSION['customer_phone']= $customer['phone'];
}

/* ── Destroy the session (logout) ─────────────────────────────────── */
function authLogout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ── Return the logged-in customer id, or null ────────────────────── */
function authId(): ?int {
    return isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : null;
}

/* ── Return a minimal customer array from session, or null ───────── */
function authCustomer(): ?array {
    if (!isset($_SESSION['customer_id'])) return null;
    return [
        'id'    => (int)   $_SESSION['customer_id'],
        'name'  =>         $_SESSION['customer_name']  ?? '',
        'phone' =>         $_SESSION['customer_phone'] ?? '',
    ];
}

/* ── Fetch full customer row from DB ──────────────────────────────── */
function authCustomerFull(PDO $pdo): ?array {
    $id = authId();
    if (!$id) return null;
    $stmt = $pdo->prepare(
        'SELECT id, name, phone, email, address, city, district, country, created_at
         FROM customers WHERE id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* ── Redirect to account page if not logged in ────────────────────── */
function authGuard(string $redirect = 'account.php'): void {
    if (!authId()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/* ── Normalise a phone number for storage/lookup ──────────────────── */
function normalisePhone(string $phone): string {
    return preg_replace('/[\s\-\(\)]/', '', $phone);
}
