<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

/* ── Format price ──────────────────────────────────────────────────── */
function formatPrice(float $p): string {
    return 'UGX ' . number_format($p, 0, '.', ',');
}

/* ── Convert uploaded file to base64 ──────────────────────────────── */
function fileToBase64(array $file): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_IMAGE_SIZE) return null;
    if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) return null;

    $data = file_get_contents($file['tmp_name']);
    if ($data === false) return null;

    return 'data:' . $file['type'] . ';base64,' . base64_encode($data);
}

/* ── Get all collections for a product ────────────────────────────── */
function getCollections(PDO $pdo, int $productId): array {
    $stmt = $pdo->prepare('SELECT collection FROM product_collections WHERE product_id = ?');
    $stmt->execute([$productId]);
    return array_column($stmt->fetchAll(), 'collection');
}

/* ── Get all storage variants for a product ───────────────────────── */
function getStorages(PDO $pdo, int $productId): array {
    $stmt = $pdo->prepare('SELECT id, label, price FROM product_storages WHERE product_id = ? ORDER BY price ASC');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/* ── Get all color variants for a product ─────────────────────────── */
function getColors(PDO $pdo, int $productId): array {
    $stmt = $pdo->prepare('SELECT id, name, image FROM product_colors WHERE product_id = ?');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/* ── Get extra gallery images for a product ───────────────────────── */
function getProductImages(PDO $pdo, int $productId): array {
    try {
        $stmt = $pdo->prepare('SELECT id, image, caption, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/* ── Get all specs for a product ──────────────────────────────────── */
function getSpecs(PDO $pdo, int $productId): array {
    try {
        $stmt = $pdo->prepare('SELECT spec_key, spec_value FROM product_specs WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/* ── Get approved reviews for a product ───────────────────────────── */
function getReviews(PDO $pdo, int $productId): array {
    try {
        $stmt = $pdo->prepare('SELECT id, name, rating, body, created_at FROM product_reviews WHERE product_id = ? AND approved = 1 ORDER BY created_at DESC');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/* ── Compute average rating and count from reviews array ──────────── */
function reviewStats(array $reviews): array {
    $count = count($reviews);
    if ($count === 0) return ['avg' => 0, 'count' => 0, 'bars' => []];
    $sum  = array_sum(array_column($reviews, 'rating'));
    $avg  = round($sum / $count, 1);
    $bars = [];
    for ($s = 5; $s >= 1; $s--) {
        $n       = count(array_filter($reviews, fn($r) => (int)$r['rating'] === $s));
        $bars[$s] = $count > 0 ? round($n / $count * 100) : 0;
    }
    return ['avg' => $avg, 'count' => $count, 'bars' => $bars];
}

/* ── Get a single product with all relations ──────────────────────── */
function getProduct(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT id, model, image, description, created_at FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) return null;

    $product['collections'] = getCollections($pdo, $id);
    $product['storages']    = getStorages($pdo, $id);
    $product['colors']      = getColors($pdo, $id);
    $product['discount']    = getDiscount($pdo, $id);
    $product['specs']       = getSpecs($pdo, $id);
    $product['reviews']     = getReviews($pdo, $id);
    $product['extra_images']= getProductImages($pdo, $id);

    return $product;
}

/* ── Get products by collection (or all) ──────────────────────────── */
function getProductsByCollection(PDO $pdo, string $collection = 'All', string $sort = 'newest', int $limit = 100): array {
    $ratingSubq = '(SELECT ROUND(AVG(r.rating),1) FROM product_reviews r WHERE r.product_id = p.id AND r.approved = 1)';
    $countSubq  = '(SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.approved = 1)';

    if ($collection === 'All') {
        $sql = "SELECT DISTINCT p.id, p.model, p.image, p.description, p.created_at,
                       $ratingSubq AS avg_rating, $countSubq AS review_count
                FROM products p
                ORDER BY " . sortClause($sort) . "
                LIMIT :limit";
        $stmt = $pdo->prepare($sql);
    } else {
        $sql = "SELECT DISTINCT p.id, p.model, p.image, p.description, p.created_at,
                       $ratingSubq AS avg_rating, $countSubq AS review_count
                FROM products p
                JOIN product_collections pc ON pc.product_id = p.id
                WHERE pc.collection = :collection
                ORDER BY " . sortClause($sort) . "
                LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':collection', $collection);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    // Attach relations to each product
    foreach ($products as &$p) {
        $p['collections'] = getCollections($pdo, $p['id']);
        $p['storages']    = getStorages($pdo, $p['id']);
        $p['colors']      = getColors($pdo, $p['id']);
        $p['discount']    = getDiscount($pdo, $p['id']);
        // avg_rating and review_count already set by subquery; cast for safety
        $p['avg_rating']    = $p['avg_rating']    !== null ? (float) $p['avg_rating']    : 0;
        $p['review_count']  = (int) ($p['review_count'] ?? 0);
    }
    unset($p);

    return $products;
}

/* ── Sort clause helper ────────────────────────────────────────────── */
function sortClause(string $sort): string {
    return match($sort) {
        'price_asc'  => 'p.id ASC',   // price sorting handled after storages are loaded
        'price_desc' => 'p.id DESC',
        'oldest'     => 'p.created_at ASC',
        default      => 'p.created_at DESC', // newest
    };
}

/* ── Get lowest price for a product ───────────────────────────────── */
function lowestPrice(array $storages): float {
    if (empty($storages)) return 0;
    return min(array_column($storages, 'price'));
}

/* ── Discount percentage ───────────────────────────────────────────── */
function discountPct(float $price, ?float $oldPrice): int {
    if (!$oldPrice || $oldPrice <= $price) return 0;
    return (int) round(($oldPrice - $price) / $oldPrice * 100);
}

/* ── Get active discount for a product ────────────────────────────── */
function getDiscount(PDO $pdo, int $productId): ?array {
    try {
        $stmt = $pdo->prepare("
            SELECT id, type, value, expires_at
            FROM product_discounts
            WHERE product_id = ?
              AND active = 1
              AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$productId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        // Table may not exist yet — return null gracefully
        return null;
    }
}

/* ── Calculate discounted price given a base price + discount row ─── */
function applyDiscount(float $basePrice, ?array $discount): float {
    if (!$discount) return $basePrice;
    if ($discount['type'] === 'pct') {
        return round($basePrice * (1 - (float)$discount['value'] / 100));
    }
    // fixed = new flat price
    return max(0, (float)$discount['value']);
}

/* ── Sanitize HTML output ──────────────────────────────────────────── */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
