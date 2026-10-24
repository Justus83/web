<?php
/* ═══════════════════════════════════════════════════════════════════════
   api/newsletter.php — Newsletter email subscription
   POST JSON: { email }
   Returns:   { success } or { error }
   ═══════════════════════════════════════════════════════════════════════ */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: same-origin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$data  = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO newsletter (email) VALUES (?)");
    $stmt->execute([$email]);

    // Check if it was a new insert or existing
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Thanks for subscribing!']);
    } else {
        echo json_encode(['success' => true, 'message' => 'You are already subscribed!']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not subscribe. Please try again.']);
}
