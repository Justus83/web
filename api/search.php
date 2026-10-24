<?php
/* ═══════════════════════════════════════════════════════════════════════
   api/search.php — Live product search
   GET  ?q=<query>&limit=<n>
   Returns: JSON array of matching products (id, model, image, price, collection)
   ═══════════════════════════════════════════════════════════════════════ */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: same-origin');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$q     = trim($_GET['q']     ?? '');
$limit = min(10, max(1, (int) ($_GET['limit'] ?? 6)));

if (mb_strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$search = '%' . $q . '%';

try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.model, p.image,
               (SELECT MIN(ps.price) FROM product_storages ps WHERE ps.product_id = p.id) AS price,
               (SELECT pc.collection FROM product_collections pc WHERE pc.product_id = p.id ORDER BY pc.id LIMIT 1) AS collection
        FROM products p
        WHERE p.model LIKE :q1 OR p.description LIKE :q2
        ORDER BY
            CASE WHEN p.model LIKE :q3 THEN 0 ELSE 1 END,
            p.created_at DESC
        LIMIT :limit
    ");
    $stmt->execute([':q1' => $search, ':q2' => $search, ':q3' => $q . '%', ':limit' => $limit]);
    $results = $stmt->fetchAll();

    // Format prices
    foreach ($results as &$r) {
        $r['price_fmt'] = $r['price'] !== null ? formatPrice((float) $r['price']) : null;
        $r['price']     = $r['price'] !== null ? (float) $r['price'] : null;
    }
    unset($r);

    echo json_encode($results);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
