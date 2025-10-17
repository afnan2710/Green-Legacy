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
$customer = [];

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
    
    // Get customer info if available
    if ($order && $order['user_id']) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param('i', $order['user_id']);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
    }
}

// Status options
$statusOptions = [
    'pending' => 'Pending',
    'processing' => 'Processing',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
];

include 'admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Details</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root {
      --primary-green: #2E8B57;
      --light-green: #a8e6cf;
      --sidebar-width: 280px;
    }
    
    body {
      font-family: 'Poppins', sans-serif;
      margin-left: 280px;
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
    
    @media (max-width: 1024px) {
      body {
        margin-left: 0;
        padding-top: 60px;
      }
    }
  </style>
</head>
<body>

<div class="p-8">
  <div class="flex justify-between items-center mb-6">
    <div>
      <h1 class="text-2xl font-bold">Order Details</h1>
      <p class="text-gray-600">Order #<?= $order['order_number'] ?? '' ?></p>
    </div>
    <div class="flex items-center space-x-4">
      <a href="admin_order_print.php?id=<?= $order_id ?>" target="_blank" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
        <i class="fas fa-print mr-2"></i> Print
      </a>
      <a href="admin_orders.php" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
        <i class="fas fa-arrow-left mr-2"></i> Back to Orders
      </a>
    </div>
  </div>

  <?php if (empty($order)): ?>
    <div class="bg-white rounded-lg shadow-sm p-6 text-center">
      <p class="text-gray-500">Order not found</p>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
      <!-- Order Summary -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-gray-600">Order Number:</span>
            <span class="font-medium"><?= $order['order_number'] ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Date:</span>
            <span><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Status:</span>
            <span class="badge badge-<?= $order['status'] ?>">
              <?= ucfirst($order['status']) ?>
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Payment Method:</span>
            <span><?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Subtotal:</span>
            <span>$<?= number_format($order['subtotal'], 2) ?></span>
          </div>
          <?php if ($order['discount_amount'] > 0): ?>
            <div class="flex justify-between">
              <span class="text-gray-600">Discount (<?= $order['coupon_code'] ?>):</span>
              <span class="text-green-600">-$<?= number_format($order['discount_amount'], 2) ?></span>
            </div>
          <?php endif; ?>
          <div class="flex justify-between">
            <span class="text-gray-600">Shipping Fee:</span>
            <span>$<?= number_format($order['shipping_fee'], 2) ?></span>
          </div>
          <div class="flex justify-between border-t border-gray-200 pt-3 mt-3">
            <span class="text-gray-900 font-semibold">Total:</span>
            <span class="text-gray-900 font-semibold">$<?= number_format($order['total_amount'], 2) ?></span>
          </div>
        </div>
      </div>

      <!-- Customer Information -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold mb-4">Customer Information</h2>
        <div class="space-y-3">
          <div>
            <p class="font-medium"><?= $order['firstname'] . ' ' . $order['lastname'] ?></p>
            <p class="text-gray-600"><?= $order['email'] ?></p>
            <?php if ($order['phone']): ?>
              <p class="text-gray-600"><?= $order['phone'] ?></p>
            <?php endif; ?>
          </div>
          
          <div class="mt-4">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Shipping Address</h3>
            <address class="text-gray-600 mt-1 not-italic">
              <?= nl2br($order['shipping_address']) ?>
            </address>
          </div>
          
          <div class="mt-4">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Billing Address</h3>
            <address class="text-gray-600 mt-1 not-italic">
              <?= nl2br($order['billing_address']) ?>
            </address>
          </div>
        </div>
      </div>

      <!-- Order Actions -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold mb-4">Order Actions</h2>
        <form action="admin_update_order_status.php" method="POST" class="space-y-4">
          <input type="hidden" name="order_id" value="<?= $order_id ?>">
          
          <div>
            <label class="block text-gray-700 mb-2">Update Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded">
              <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= $value ?>" <?= $order['status'] == $value ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div>
            <label class="block text-gray-700 mb-2">Add Note</label>
            <textarea name="notes" class="w-full px-3 py-2 border border-gray-300 rounded" rows="3"></textarea>
          </div>
          
          <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
            Update Order
          </button>
        </form>
      </div>
    </div>

    <!-- Order Items -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
      <div class="p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold">Order Items</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($order_items as $item): ?>
              <tr>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900"><?= $item['name'] ?></div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">
                    <?php if ($item['discount_price']): ?>
                      <span class="line-through text-gray-400 mr-2">$<?= number_format($item['price'], 2) ?></span>
                      <span>$<?= number_format($item['discount_price'], 2) ?></span>
                    <?php else: ?>
                      $<?= number_format($item['price'], 2) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <?= $item['quantity'] ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  $<?= number_format(($item['discount_price'] ?: $item['price']) * $item['quantity'], 2) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="px-6 py-4 text-right font-medium">Subtotal</td>
              <td class="px-6 py-4 font-medium">$<?= number_format($order['subtotal'], 2) ?></td>
            </tr>
            <?php if ($order['discount_amount'] > 0): ?>
              <tr>
                <td colspan="3" class="px-6 py-4 text-right font-medium">Discount</td>
                <td class="px-6 py-4 font-medium text-green-600">-$<?= number_format($order['discount_amount'], 2) ?></td>
              </tr>
            <?php endif; ?>
            <tr>
              <td colspan="3" class="px-6 py-4 text-right font-medium">Shipping</td>
              <td class="px-6 py-4 font-medium">$<?= number_format($order['shipping_fee'], 2) ?></td>
            </tr>
            <tr class="bg-gray-50">
              <td colspan="3" class="px-6 py-4 text-right font-semibold">Total</td>
              <td class="px-6 py-4 font-semibold">$<?= number_format($order['total_amount'], 2) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Order Notes -->
    <?php if (!empty($order['notes'])): ?>
      <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Order Notes</h2>
        <div class="bg-gray-50 p-4 rounded">
          <p class="text-gray-700"><?= nl2br($order['notes']) ?></p>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

</body>
</html>