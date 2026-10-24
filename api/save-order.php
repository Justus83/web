<?php
/* ═══════════════════════════════════════════════════════════════════════
   api/save-order.php — Persist a new order and its items to the DB
   Accepts: POST JSON body
   Returns: JSON { success, order_number, order_id } or { error }
   ═══════════════════════════════════════════════════════════════════════ */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: same-origin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

/* ── Read & validate input ─────────────────────────────────────────── */
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

/* Required fields */
$required = ['name', 'phone', 'address', 'city', 'payment', 'shipping', 'total', 'subtotal', 'items'];
foreach ($required as $field) {
    if (empty($data[$field]) && $data[$field] !== 0) {
        http_response_code(422);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

if (!is_array($data['items']) || count($data['items']) === 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Order must contain at least one item']);
    exit;
}

/* ── Sanitise values ───────────────────────────────────────────────── */
$orderNumber  = 'GG-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
$name         = mb_substr(trim($data['name']),         0, 255);
$phone        = mb_substr(trim($data['phone']),        0, 50);
$email        = mb_substr(trim($data['email'] ?? ''),  0, 255);
$address      = mb_substr(trim($data['address']),      0, 500);
$city         = mb_substr(trim($data['city']),         0, 150);
$country      = mb_substr(trim($data['country'] ?? 'UG'), 0, 10);
$district     = mb_substr(trim($data['district'] ?? ''), 0, 150);
$notes        = mb_substr(trim($data['notes'] ?? ''),  0, 2000);
$paymentMethod  = mb_substr(trim($data['payment']),    0, 100);
$shippingMethod = mb_substr(trim($data['shipping']),   0, 100);
$subtotal     = max(0, (float) $data['subtotal']);
$shippingCost = max(0, (float) ($data['shipping_cost'] ?? 0));
$discount     = max(0, (float) ($data['discount']      ?? 0));
$total        = max(0, (float) $data['total']);
$customerId   = isset($data['customer_id']) && is_numeric($data['customer_id'])
                ? (int) $data['customer_id'] : null;

/* ── Server-side price validation ─────────────────────────────────── */
// Recompute each item's price from the DB to prevent client-side tampering.
$validatedSubtotal = 0;
$validatedItems     = [];

foreach ($data['items'] as $item) {
    $qty  = max(1, min(999, (int) ($item['qty'] ?? 1)));
    $pid  = isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : null;
    $stor = trim($item['storage'] ?? '');

    $serverPrice = null;

    if ($pid) {
        // Look up the storage variant price for this product
        if ($stor !== '') {
            $ps = $pdo->prepare("SELECT price FROM product_storages WHERE product_id = ? AND label = ? ORDER BY price ASC LIMIT 1");
            $ps->execute([$pid, $stor]);
            $row = $ps->fetch();
            if ($row) $serverPrice = (float) $row['price'];
        }

        // Fallback to lowest price if storage not matched
        if ($serverPrice === null) {
            $ps = $pdo->prepare("SELECT MIN(price) AS price FROM product_storages WHERE product_id = ?");
            $ps->execute([$pid]);
            $row = $ps->fetch();
            if ($row && $row['price'] !== null) $serverPrice = (float) $row['price'];
        }

        // Apply active discount if one exists
        if ($serverPrice !== null) {
            $disc = getDiscount($pdo, $pid);
            if ($disc) $serverPrice = applyDiscount($serverPrice, $disc);
        }
    }

    // Use server-computed price when available; fall back to client price only if lookup failed
    $finalPrice = $serverPrice !== null ? $serverPrice : max(0, (float) ($item['price'] ?? 0));
    $validatedSubtotal += $finalPrice * $qty;

    $item['price']      = $finalPrice;
    $item['id']         = $pid;
    $validatedItems[]   = $item;
}

// Recompute totals from validated subtotal
$shippingCost = max(0, (float) ($data['shipping_cost'] ?? 0));
if ($validatedSubtotal >= 200000) $shippingCost = 0;
$discount     = max(0, (float) ($data['discount'] ?? 0));
$subtotal     = $validatedSubtotal;
$total        = max(0, $subtotal - $discount + $shippingCost);

// Replace original items with validated ones
$data['items'] = $validatedItems;

/* ── Insert order in a transaction ────────────────────────────────── */
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO `orders`
          (order_number, customer_name, customer_phone, customer_email,
           address, city, country, district, notes,
           payment_method, shipping_method,
           subtotal, shipping_cost, discount, total, status, customer_id)
        VALUES
          (:order_number, :name, :phone, :email,
           :address, :city, :country, :district, :notes,
           :payment, :shipping,
           :subtotal, :shipping_cost, :discount, :total, 'pending', :customer_id)
    ");

    $stmt->execute([
        ':order_number'   => $orderNumber,
        ':name'           => $name,
        ':phone'          => $phone,
        ':email'          => $email ?: null,
        ':address'        => $address,
        ':city'           => $city,
        ':country'        => $country ?: 'UG',
        ':district'       => $district ?: null,
        ':notes'          => $notes   ?: null,
        ':payment'        => $paymentMethod,
        ':shipping'       => $shippingMethod,
        ':subtotal'       => $subtotal,
        ':shipping_cost'  => $shippingCost,
        ':discount'       => $discount,
        ':total'          => $total,
        ':customer_id'    => $customerId,
    ]);

    $orderId = (int) $pdo->lastInsertId();

    /* Increment coupon used_count if one was applied */
    if (!empty($data['coupon_code'])) {
        $couponCode = strtoupper(trim($data['coupon_code']));
        $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ? AND active = 1")
            ->execute([$couponCode]);
    }

    /* Insert each item */
    $itemStmt = $pdo->prepare("
        INSERT INTO `order_items`
          (order_id, product_id, model, storage, color, image, price, qty)
        VALUES
          (:order_id, :product_id, :model, :storage, :color, :image, :price, :qty)
    ");

    foreach ($data['items'] as $item) {
        $model   = mb_substr(trim($item['model']   ?? 'Unknown'), 0, 255);
        $storage = mb_substr(trim($item['storage'] ?? ''),        0, 100);
        $color   = mb_substr(trim($item['color']   ?? ''),        0, 100);
        $price   = max(0, (float) ($item['price'] ?? 0));
        $qty     = max(1, min(999, (int) ($item['qty'] ?? 1)));
        $pid     = isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : null;
        // image may be a base64 data URI or a URL — store as-is (truncated for safety)
        $image   = isset($item['image']) ? mb_substr($item['image'], 0, 500000) : null;

        $itemStmt->execute([
            ':order_id'   => $orderId,
            ':product_id' => $pid,
            ':model'      => $model,
            ':storage'    => $storage ?: null,
            ':color'      => $color   ?: null,
            ':image'      => $image,
            ':price'      => $price,
            ':qty'        => $qty,
        ]);
    }

    $pdo->commit();

    /* ── Send order confirmation email (best-effort, non-blocking) ─── */
    if (!empty($email)) {
        require_once __DIR__ . '/../config/mailer.php';
        $itemsForMail = array_map(fn($item) => [
            'model'   => $item['model']   ?? '',
            'storage' => $item['storage'] ?? '',
            'color'   => $item['color']   ?? '',
            'qty'     => $item['qty']     ?? 1,
            'price'   => $item['price']   ?? 0,
        ], $data['items']);

        sendOrderConfirmation(
            $email,
            $name,
            $orderNumber,
            date('d M Y, g:ia'),
            $paymentMethod,
            $shippingMethod,
            $address,
            $city,
            $subtotal,
            $shippingCost,
            $discount,
            $total,
            $itemsForMail,
            $orderId
        );
    }

    echo json_encode([
        'success'      => true,
        'order_id'     => $orderId,
        'order_number' => $orderNumber,
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save order. Please try again.']);
}
