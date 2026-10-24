<?php
/* ═══════════════════════════════════════════════════════════════════════
   admin/login.php — Admin login page
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

// Already logged in — go to dashboard
if (adminId()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']      ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, must_change_pw FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // Timing-safe: always run password_verify
        $dummy = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234';
        $hash  = $admin ? $admin['password_hash'] : $dummy;

        if (!$admin || !password_verify($password, $hash)) {
            $error = 'Incorrect username or password.';
        } else {
            adminLogin($admin);
            header('Location: ' . (adminMustChangePw() ? 'change-password.php' : 'index.php'));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Admin Login — Gadget Genesis</title>
  <link rel="stylesheet" href="../style.css"/>
  <style>
    body {
      background: #f1f3f8;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
    }

    .login-wrap {
      width: 100%;
      max-width: 400px;
      padding: 24px;
    }

    .login-logo {
      text-align: center;
      margin-bottom: 28px;
    }
    .login-logo img { height: 48px; width: auto; }
    .login-logo span {
      display: block;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: var(--gray);
      margin-top: 8px;
    }

    .login-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 36px 32px;
    }
    .login-card h1 {
      font-size: 22px;
      font-weight: 800;
      color: var(--navy);
      margin-bottom: 4px;
    }
    .login-card p {
      font-size: 13px;
      color: var(--gray);
      margin-bottom: 28px;
    }

    .auth-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
    .auth-field label { font-size: 13px; font-weight: 600; color: var(--navy); }
    .auth-field input {
      padding: 12px 14px;
      border: 1.5px solid var(--border);
      border-radius: 9px;
      font-size: 14px;
      font-family: inherit;
      color: var(--navy);
      outline: none;
      transition: border-color .15s, box-shadow .15s;
    }
    .auth-field input:focus {
      border-color: var(--navy);
      box-shadow: 0 0 0 3px rgba(10,25,49,.08);
    }

    .btn-login {
      width: 100%;
      padding: 13px;
      background: var(--navy);
      color: #fff;
      border: none;
      border-radius: 9px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      transition: opacity .15s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .btn-login:hover { opacity: .88; }
    .btn-login [data-lucide] { width: 16px; height: 16px; }

    .login-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 9px;
      padding: 12px 14px;
      font-size: 13px;
      color: #b91c1c;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .login-error [data-lucide] { width: 16px; height: 16px; flex-shrink: 0; }

    .login-footer {
      text-align: center;
      margin-top: 20px;
      font-size: 12px;
      color: var(--gray);
    }
    .login-footer a {
      color: var(--navy);
      text-decoration: none;
      font-weight: 600;
    }
    .login-footer a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<div class="login-wrap">

  <div class="login-logo">
    <img src="../logo.jpg" alt="Gadget Genesis"/>
    <span>Admin Panel</span>
  </div>

  <div class="login-card">
    <h1>Sign In</h1>
    <p>Enter your admin credentials to continue.</p>

    <?php if ($error): ?>
      <div class="login-error">
        <i data-lucide="circle-alert"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="auth-field">
        <label for="username">Username</label>
        <input
          type="text"
          id="username"
          name="username"
          placeholder="admin"
          value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          autocomplete="username"
          required
          autofocus
        />
      </div>
      <div class="auth-field">
        <label for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="Your password"
          autocomplete="current-password"
          required
        />
      </div>
      <button type="submit" class="btn-login">
        <i data-lucide="log-in"></i>
        Sign In to Admin
      </button>
    </form>
  </div>

  <div class="login-footer">
    <a href="../index.php">← Back to Store</a>
  </div>

</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>
</body>
</html>
