<?php
/* ═══════════════════════════════════════════════════════════════════════
   api/quick-view.php — Return product data for the quick-view modal
   GET ?id=<product_id>
   Returns: JSON with id, model, image, description, collections,
            storages, colors, discount, avg_rating, review_count, price
   ═══════════════════════════════════════════════════════════════════════ */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: same-origin');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$product = getProduct($pdo, $id);
if (!$product) {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
    exit;
}

/* Compute prices with discount applied */
$discount   = $product['discount'];
$storages   = array_map(function ($s) use ($discount) {
    $salePrice = applyDiscount((float)$s['price'], $discount);
    return [
        'id'        => $s['id'],
        'label'     => $s['label'],
        'price'     => (float)$s['price'],
        'sale_price'=> $salePrice,
        'has_disc'  => $discount && $salePrice < (float)$s['price'],
    ];
}, $product['storages']);

$lowestP    = lowestPrice($product['storages']);
$lowestSale = applyDiscount($lowestP, $discount);

/* Rating stats */
$stats       = reviewStats($product['reviews']);
$avgRating   = $stats['avg'];
$reviewCount = $stats['count'];

echo json_encode([
    'id'           => $product['id'],
    'model'        => $product['model'],
    'image'        => $product['image'],
    'description'  => $product['description'],
    'collections'  => $product['collections'],
    'storages'     => $storages,
    'colors'       => array_map(fn($c) => ['name' => $c['name'], 'image' => $c['image']], $product['colors']),
    'lowest_price' => $lowestP,
    'lowest_sale'  => $lowestSale,
    'has_discount' => (bool)($discount && $lowestSale < $lowestP),
    'avg_rating'   => $avgRating,
    'review_count' => $reviewCount,
    'product_url'  => '../product.php?id=' . $product['id'],
]);
