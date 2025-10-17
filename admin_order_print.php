<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

require_once 'db_connect.php';

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch order details
$order = [];
$order_items = [];

if ($order_id) {
    // Get order info
    $stmt = $conn->prepare("
        SELECT o.*, u.firstname, u.lastname, u.email, u.phone, u.address, 
               c.code AS coupon_code
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN coupons c ON o.coupon_id = c.id
        WHERE o.id = ?
    ");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    
    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.*, p.name, p.slug, pi.image_path
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Set headers for download
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order #<?= $order['order_number'] ?? '' ?> - Print</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
  <style>
    @media print {
      body {
        font-size: 12px;
      }
      .no-print {
        display: none;
      }
    }
    .badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    .badge-pending {
      background-color: #fef3c7;
      color: #92400e;
    }
    .badge-processing {
      background-color: #dbeafe;
      color: #1e40af;
    }
    .badge-completed {
      background-color: #dcfce7;
      color: #166534;
    }
    .badge-cancelled {
      background-color: #fee2e2;
      color: #991b1b;
    }
  </style>
</head>
<body class="p-8">
  <div class="no-print mb-6 flex justify-between">
    <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
      <i class="fas fa-print mr-2"></i> Print
    </button>
    <button onclick="window.close()" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
      <i class="fas fa-times mr-2"></i> Close
    </button>
  </div>

  <div class="max-w-4xl mx-auto bg-white p-8 shadow-sm">
    <div class="flex justify-between items-start mb-8">
      <div>
        <h1 class="text-2xl font-bold">Green Legacy</h1>
        <p class="text-gray-600">United City, Madani Avenue, Dhaka - 1212</p>
        <p class="text-gray-600">Phone: +8801601701444</p>
      </div>
      <div class="text-right">
        <h2 class="text-xl font-semibold">Order #<?= $order['order_number'] ?? '' ?></h2>
        <p class="text-gray-600">Date: <?= date('M j, Y', strtotime($order['created_at'])) ?></p>
        <span class="badge badge-<?= $order['status'] ?> mt-2">
          <?= ucfirst($order['status']) ?>
        </span>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
      <div>
        <h3 class="text-lg font-semibold mb-2">Customer Information</h3>
        <p class="font-medium"><?= $order['firstname'] . ' ' . $order['lastname'] ?></p>
        <p class="text-gray-600"><?= $order['email'] ?></p>
        <?php if ($order['phone']): ?>
          <p class="text-gray-600"><?= $order['phone'] ?></p>
        <?php endif; ?>
      </div>
      <div>
        <h3 class="text-lg font-semibold mb-2">Shipping Address</h3>
        <address class="text-gray-600 not-italic">
          <?= nl2br($order['shipping_address']) ?>
        </address>
      </div>
    </div>

    <div class="mb-8">
      <h3 class="text-lg font-semibold mb-4">Order Summary</h3>
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php foreach ($order_items as $item): ?>
            <tr>
              <td class="px-4 py-2 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900"><?= $item['name'] ?></div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <div class="text-sm text-gray-900">
                  <?php if ($item['discount_price']): ?>
                    <span class="line-through text-gray-400 mr-1">$<?= number_format($item['price'], 2) ?></span>
                    <span>$<?= number_format($item['discount_price'], 2) ?></span>
                  <?php else: ?>
                    $<?= number_format($item['price'], 2) ?>
                  <?php endif; ?>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                <?= $item['quantity'] ?>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                $<?= number_format(($item['discount_price'] ?: $item['price']) * $item['quantity'], 2) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="px-4 py-2 text-right font-medium">Subtotal</td>
            <td class="px-4 py-2 font-medium">$<?= number_format($order['subtotal'], 2) ?></td>
          </tr>
          <?php if ($order['discount_amount'] > 0): ?>
            <tr>
              <td colspan="3" class="px-4 py-2 text-right font-medium">Discount (<?= $order['coupon_code'] ?>)</td>
              <td class="px-4 py-2 font-medium text-green-600">-$<?= number_format($order['discount_amount'], 2) ?></td>
            </tr>
          <?php endif; ?>
          <tr>
            <td colspan="3" class="px-4 py-2 text-right font-medium">Shipping</td>
            <td class="px-4 py-2 font-medium">$<?= number_format($order['shipping_fee'], 2) ?></td>
          </tr>
          <tr class="bg-gray-50">
            <td colspan="3" class="px-4 py-2 text-right font-semibold">Total</td>
            <td class="px-4 py-2 font-semibold">$<?= number_format($order['total_amount'], 2) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="pt-4 border-t border-gray-200">
      <p class="text-sm text-gray-500">Thank you for your order!</p>
      <p class="text-sm text-gray-500">For any questions, please contact info.afnan27@gmail.com</p>
    </div>
  </div>

  <script>
    window.onload = function() {
      window.print();
    }
  </script>
</body>
</html>