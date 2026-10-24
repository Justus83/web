<?php

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET /api/storages.php?q=64 ──────────────────────────────────────────────
// Search existing storage labels that match the query string
if ($method === 'GET') {
    $q = trim($_GET['q'] ?? '');

    if ($q === '') {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, label FROM storages
         WHERE label LIKE :q
         ORDER BY label ASC
         LIMIT 10'
    );
    $stmt->execute([':q' => '%' . $q . '%']);
    $results = $stmt->fetchAll();

    echo json_encode($results);
    exit;
}

// ── POST /api/storages.php ───────────────────────────────────────────────────
// Save a new storage label (ignored if it already exists — INSERT IGNORE)
if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $label = trim($body['label'] ?? '');

    if ($label === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Label is required.']);
        exit;
    }

    // INSERT IGNORE so duplicate labels don't throw an error
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO storages (label) VALUES (:label)'
    );
    $stmt->execute([':label' => $label]);

    // Fetch the record (whether just inserted or already existed)
    $fetch = $pdo->prepare('SELECT id, label FROM storages WHERE label = :label');
    $fetch->execute([':label' => $label]);
    $record = $fetch->fetch();

    http_response_code(201);
    echo json_encode($record);
    exit;
}

// ── Method not allowed ───────────────────────────────────────────────────────
http_response_code(405);
echo json_encode(['error' => 'Method not allowed.']);
