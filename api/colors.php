<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── GET ?q=bla — search existing colors ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') { echo json_encode([]); exit; }

    $stmt = $pdo->prepare(
        'SELECT id, name FROM colors WHERE name LIKE :q ORDER BY name ASC LIMIT 10'
    );
    $stmt->execute([':q' => '%' . $q . '%']);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── POST {name} — save new color (INSERT IGNORE) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $name = trim($body['name'] ?? '');

    if ($name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Name is required.']);
        exit;
    }

    $pdo->prepare('INSERT IGNORE INTO colors (name) VALUES (:name)')
        ->execute([':name' => $name]);

    $row = $pdo->prepare('SELECT id, name FROM colors WHERE name = :name');
    $row->execute([':name' => $name]);

    http_response_code(201);
    echo json_encode($row->fetch());
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed.']);
