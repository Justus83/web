<?php
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json');

$stmt = $pdo->query(
    'SELECT p.id, p.model, p.image FROM products p
     ORDER BY p.created_at DESC LIMIT 6'
);
$products = $stmt->fetchAll();

foreach ($products as &$p) {
    $storages   = getStorages($pdo, $p['id']);
    $p['price'] = lowestPrice($storages);
}
unset($p);

echo json_encode($products);
