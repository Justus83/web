<?php
/* ═══════════════════════════════════════════════════════════════════════
   api/logout.php — Destroy the session and redirect
   Accepts GET or POST
   ═══════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../config/auth.php';

authLogout();

$redirect = $_GET['redirect'] ?? 'index.php';

// Only allow relative redirects to prevent open-redirect attacks
if (strpos($redirect, '//') !== false || strpos($redirect, ':') !== false) {
    $redirect = 'index.php';
}

header('Location: ' . $redirect);
exit;
