<?php
/* ═══════════════════════════════════════════════════════════════════════
   admin/auth.php — Admin session guard
   Include at the TOP of every admin page before any output.
   Requires $pdo to already be available (include db.php first).
   ═══════════════════════════════════════════════════════════════════════ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ── Is admin logged in? ───────────────────────────────────────────── */
function adminId(): ?int {
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
}

function adminUsername(): string {
    return $_SESSION['admin_username'] ?? 'Admin';
}

function adminMustChangePw(): bool {
    return !empty($_SESSION['admin_must_change_pw']);
}

/* ── Start admin session ───────────────────────────────────────────── */
function adminLogin(array $admin): void {
    session_regenerate_id(true);
    $_SESSION['admin_id']            = $admin['id'];
    $_SESSION['admin_username']      = $admin['username'];
    $_SESSION['admin_must_change_pw']= (bool) $admin['must_change_pw'];
}

/* ── Destroy admin session ─────────────────────────────────────────── */
function adminLogout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ── Guard — redirect to login if not authenticated ───────────────── */
function adminGuard(): void {
    if (!adminId()) {
        header('Location: login.php');
        exit;
    }

    // Force password change if flag is set — skip when already on that page
    if (adminMustChangePw() && basename($_SERVER['PHP_SELF']) !== 'change-password.php') {
        header('Location: change-password.php');
        exit;
    }
}
