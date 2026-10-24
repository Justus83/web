<?php
/* ═══════════════════════════════════════════════════════════════════════
   config/mailer.php — Email notification helpers
   Uses PHP's built-in mail() — no external library needed.
   ═══════════════════════════════════════════════════════════════════════ */

defined('BASE_URL')    || define('BASE_URL',    'http://localhost/web');
defined('STORE_NAME')  || define('STORE_NAME',  'Gadget Genesis');
defined('STORE_EMAIL') || define('STORE_EMAIL', 'noreply@gadgetgenesis.ug');
defined('STORE_PHONE') || define('STORE_PHONE', '0759 809061');

/* ── Build a clean HTML email shell ─────────────────────────────────── */
function mailWrap(string $subject, string $bodyHtml): string {
    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title>
<style>
  body{margin:0;padding:0;background:#f1f3f8;font-family:\'Segoe UI\',Arial,sans-serif;}
  .wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;margin-top:24px;margin-bottom:24px;}
  .hdr{background:#0f2044;padding:24px 32px;text-align:center;}
  .hdr h1{color:#fff;font-size:22px;font-weight:800;margin:0;}
  .hdr p{color:rgba(255,255,255,.7);font-size:13px;margin:4px 0 0;}
  .body{padding:28px 32px;}
  .section-title{font-size:15px;font-weight:800;color:#0f2044;margin:20px 0 10px;padding-bottom:6px;border-bottom:2px solid #e8ebf4;}
  table.items{width:100%;border-collapse:collapse;margin:12px 0;}
  table.items th{text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;padding:8px 10px;border-bottom:1px solid #e5e7eb;}
  table.items td{padding:10px 10px;font-size:13px;color:#374151;border-bottom:1px solid #f3f4f6;}
  .total-row td{font-weight:800;color:#0f2044;border-bottom:none;padding-top:14px;}
  .badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;}
  .badge-pending{background:#fef3c7;color:#92400e;}
  .badge-processing{background:#dbeafe;color:#1e40af;}
  .badge-shipped{background:#ede9fe;color:#5b21b6;}
  .badge-delivered{background:#dcfce7;color:#166534;}
  .badge-cancelled{background:#fee2e2;color:#991b1b;}
  .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:12px 0;}
  .info-box{background:#f8f9fc;border-radius:8px;padding:14px 16px;}
  .info-box p{margin:0;font-size:13px;color:#374151;line-height:1.6;}
  .info-box strong{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:4px;}
  .cta{text-align:center;margin:24px 0;}
  .cta a{display:inline-block;padding:13px 28px;background:#0f2044;color:#fff;text-decoration:none;border-radius:9px;font-size:14px;font-weight:700;}
  .footer{background:#f8f9fc;padding:18px 32px;text-align:center;font-size:12px;color:#9ca3af;line-height:1.6;}
  .footer a{color:#9ca3af;}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <h1>' . STORE_NAME . '</h1>
    <p>Your trusted tech store in Kampala</p>
  </div>
  <div class="body">' . $bodyHtml . '</div>
  <div class="footer">
    <p><strong>' . STORE_NAME . '</strong> · Kooki Towers, Buganda Rd, Kampala</p>
    <p>📞 ' . STORE_PHONE . ' · <a href="https://wa.me/256759809061">WhatsApp</a></p>
    <p style="margin-top:8px;font-size:11px;">You received this email because you placed an order with us.</p>
  </div>
</div>
</body>
</html>';
}

/* ── Low-level send wrapper ──────────────────────────────────────────── */
function sendMail(string $to, string $subject, string $html): bool {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . STORE_NAME . " <" . STORE_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . STORE_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
}

/* ── Order confirmation email ────────────────────────────────────────── */
function sendOrderConfirmation(
    string $toEmail,
    string $toName,
    string $orderNumber,
    string $orderDate,
    string $paymentMethod,
    string $shippingMethod,
    string $address,
    string $city,
    float  $subtotal,
    float  $shipping,
    float  $discount,
    float  $total,
    array  $items,    // each: ['model', 'storage', 'color', 'qty', 'price']
    int    $orderId = 0
): bool {
    $subject = "Order Confirmed — {$orderNumber} | " . STORE_NAME;

    $itemRows = '';
    foreach ($items as $item) {
        $meta = array_filter([$item['storage'] ?? '', $item['color'] ?? '']);
        $metaStr = $meta ? ' <span style="color:#9ca3af;font-size:12px;">(' . htmlspecialchars(implode(', ', $meta), ENT_QUOTES, 'UTF-8') . ')</span>' : '';
        $itemRows .= '<tr>
            <td>' . htmlspecialchars($item['model'] ?? '', ENT_QUOTES, 'UTF-8') . $metaStr . '</td>
            <td style="text-align:center;">' . (int)($item['qty'] ?? 1) . '</td>
            <td style="text-align:right;">UGX ' . number_format((float)$item['price'] * (int)($item['qty'] ?? 1)) . '</td>
        </tr>';
    }

    $discountRow = $discount > 0
        ? '<tr><td colspan="2">Discount</td><td style="text-align:right;color:#16a34a;">−UGX ' . number_format($discount) . '</td></tr>'
        : '';

    $trackUrl = $orderId > 0
        ? BASE_URL . '/order-confirm.php?id=' . $orderId
        : BASE_URL . '/orders.php';

    $body = '
    <p style="font-size:16px;font-weight:800;color:#0f2044;margin-top:0;">Hi ' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . ', your order is confirmed! 🎉</p>
    <p style="font-size:14px;color:#374151;margin-bottom:20px;">Thank you for shopping with <strong>' . STORE_NAME . '</strong>. We have received your order and are processing it now.</p>

    <p class="section-title">Order Summary</p>
    <div class="info-grid">
      <div class="info-box"><strong>Order Number</strong><p>' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . '</p></div>
      <div class="info-box"><strong>Order Date</strong><p>' . htmlspecialchars($orderDate, ENT_QUOTES, 'UTF-8') . '</p></div>
      <div class="info-box"><strong>Payment</strong><p>' . htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8') . '</p></div>
      <div class="info-box"><strong>Shipping</strong><p>' . htmlspecialchars($shippingMethod, ENT_QUOTES, 'UTF-8') . '</p></div>
    </div>

    <p class="section-title">Shipping Address</p>
    <div class="info-box" style="margin-bottom:8px;">
      <p>' . htmlspecialchars($address . ', ' . $city, ENT_QUOTES, 'UTF-8') . '</p>
    </div>

    <p class="section-title">Items Ordered</p>
    <table class="items">
      <thead><tr><th>Item</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Price</th></tr></thead>
      <tbody>
        ' . $itemRows . '
        <tr style="border-top:2px solid #e5e7eb;">
          <td colspan="2" style="padding-top:10px;font-size:13px;color:#6b7280;">Subtotal</td>
          <td style="text-align:right;padding-top:10px;font-size:13px;color:#374151;">UGX ' . number_format($subtotal) . '</td>
        </tr>
        <tr>
          <td colspan="2" style="font-size:13px;color:#6b7280;">Shipping</td>
          <td style="text-align:right;font-size:13px;color:#374151;">' . ($shipping > 0 ? 'UGX ' . number_format($shipping) : 'Free') . '</td>
        </tr>
        ' . $discountRow . '
        <tr class="total-row">
          <td colspan="2">Total</td>
          <td style="text-align:right;font-size:16px;">UGX ' . number_format($total) . '</td>
        </tr>
      </tbody>
    </table>

    <div class="cta">
      <a href="' . htmlspecialchars($trackUrl, ENT_QUOTES, 'UTF-8') . '">View Order Details →</a>
    </div>

    <p style="font-size:13px;color:#6b7280;text-align:center;">Questions? Chat with us on <a href="https://wa.me/256759809061" style="color:#0f2044;font-weight:700;">WhatsApp</a> or call <a href="tel:0759809061" style="color:#0f2044;font-weight:700;">' . STORE_PHONE . '</a>.</p>';

    return sendMail($toEmail, $subject, mailWrap($subject, $body));
}

/* ── Order status update email ───────────────────────────────────────── */
function sendStatusUpdate(
    string $toEmail,
    string $toName,
    string $orderNumber,
    string $newStatus,
    string $trackingNumber = '',
    int    $orderId = 0
): bool {
    $statusLabels = [
        'pending'    => 'Pending',
        'processing' => 'Processing',
        'shipped'    => 'Shipped',
        'delivered'  => 'Delivered',
        'cancelled'  => 'Cancelled',
    ];
    $statusMessages = [
        'processing' => 'Great news! Your order is now being prepared for shipment.',
        'shipped'    => 'Your order is on its way! Expect it within the estimated delivery window.',
        'delivered'  => 'Your order has been delivered. We hope you love your new gadget!',
        'cancelled'  => 'Your order has been cancelled. If you have questions, please contact us.',
        'pending'    => 'Your order is pending. We will update you once processing begins.',
    ];

    $label   = $statusLabels[$newStatus]   ?? ucfirst($newStatus);
    $message = $statusMessages[$newStatus] ?? 'Your order status has been updated.';
    $subject = "Order {$label} — {$orderNumber} | " . STORE_NAME;

    $trackUrl = $orderId > 0
        ? BASE_URL . '/order-confirm.php?id=' . $orderId
        : BASE_URL . '/orders.php';

    $trackingBlock = '';
    if ($newStatus === 'shipped' && $trackingNumber !== '') {
        $trackingBlock = '<div class="info-box" style="margin:16px 0;background:#f0fdf4;border:1px solid #bbf7d0;">
            <strong>Tracking Number</strong>
            <p style="font-size:16px;font-weight:800;color:#166534;">' . htmlspecialchars($trackingNumber, ENT_QUOTES, 'UTF-8') . '</p>
        </div>';
    }

    $body = '
    <p style="font-size:16px;font-weight:800;color:#0f2044;margin-top:0;">Hi ' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . ',</p>
    <p style="font-size:14px;color:#374151;margin-bottom:20px;">' . $message . '</p>

    <div class="info-grid">
      <div class="info-box"><strong>Order Number</strong><p>' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . '</p></div>
      <div class="info-box"><strong>New Status</strong><p><span class="badge badge-' . htmlspecialchars($newStatus, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></p></div>
    </div>

    ' . $trackingBlock . '

    <div class="cta">
      <a href="' . htmlspecialchars($trackUrl, ENT_QUOTES, 'UTF-8') . '">View Order →</a>
    </div>

    <p style="font-size:13px;color:#6b7280;text-align:center;">Need help? Contact us on <a href="https://wa.me/256759809061" style="color:#0f2044;font-weight:700;">WhatsApp</a> or call <a href="tel:0759809061" style="color:#0f2044;font-weight:700;">' . STORE_PHONE . '</a>.</p>';

    return sendMail($toEmail, $subject, mailWrap($subject, $body));
}
