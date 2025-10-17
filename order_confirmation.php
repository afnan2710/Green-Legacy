<?php
require_once 'db_connect.php';
require_once 'helpers.php';

// Check if order ID is provided
if (!isset($_GET['order_id'])) {
    header('Location: shop.php');
    exit;
}

$order_id = (int)$_GET['order_id'];

// Get order details
$stmt = $conn->prepare("
    SELECT o.*, 
           u.firstname, u.lastname, u.email, u.phone,
           c.code as coupon_code, c.discount_type, c.discount_value
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN coupons c ON o.coupon_id = c.id
    WHERE o.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: shop.php');
    exit;
}

$order = $result->fetch_assoc();

$order['payment_method'] = $order['payment_method'] == '0' ? 'cod' : ($order['payment_method'] == '1' ? 'card' : $order['payment_method']);

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name, p.slug, p.product_type,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .order-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-processing {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>

<body>
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-10">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-check-circle text-green-600 text-3xl"></i>
                </div>
                <h1 class="text-3xl font-bold mb-4">Order Confirmed!</h1>
                <p class="text-lg text-gray-600 mb-2">Thank you for your purchase. Your order has been received and is being processed.</p>
                <p class="text-gray-500">Order #<?= htmlspecialchars($order['order_number']) ?></p>
            </div>

            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 border-b">
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Order Information</h3>
                        <div class="space-y-2">
                            <p><span class="text-gray-600">Order Date:</span> <?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></p>
                            <p><span class="text-gray-600">Order Status:</span>
                                <span class="order-status status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </p>
                            <p><span class="text-gray-600">Payment Method:</span>
                                <span class="<?= getPaymentMethodBadge($order['payment_method']) ?> px-2 py-1 rounded-full text-sm">
                                    <?= getPaymentMethodDisplay($order['payment_method']) ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-3">Customer Information</h3>
                        <div class="space-y-2">
                            <p><?= htmlspecialchars($order['firstname'] . ' ' . $order['lastname']) ?></p>
                            <p><?= htmlspecialchars($order['email']) ?></p>
                            <p><?= htmlspecialchars($order['phone']) ?></p>
                        </div>
                    </div>
                </div>

                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold mb-3">Shipping Information</h3>
                    <p class="whitespace-pre-line"><?= htmlspecialchars($order['shipping_address']) ?></p>
                </div>

                <?php if (!empty($order['notes'])): ?>
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-semibold mb-3">Order Notes</h3>
                        <p class="whitespace-pre-line"><?= htmlspecialchars($order['notes']) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8">
                <h3 class="text-xl font-bold p-6 border-b">Order Items</h3>

                <div class="divide-y">
                    <?php foreach ($items as $item): ?>
                        <div class="p-6">
                            <div class="flex flex-col md:flex-row md:items-center">
                                <a href="product.php?id=<?= $item['product_id'] ?>" class="w-20 h-20 flex-shrink-0 mb-4 md:mb-0">
                                    <img src="<?= !empty($item['image']) ? 'uploads/products/' . htmlspecialchars($item['image']) : 'images/placeholder-product.jpg' ?>"
                                        alt="<?= htmlspecialchars($item['name']) ?>"
                                        class="w-full h-full object-cover rounded">
                                </a>

                                <div class="md:ml-6 flex-1">
                                    <div class="flex flex-col md:flex-row md:justify-between">
                                        <div class="mb-4 md:mb-0">
                                            <a href="product.php?id=<?= $item['product_id'] ?>" class="text-lg font-medium hover:text-green-600">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                            <div class="text-sm text-gray-500 mt-1">
                                                <span class="badge badge-<?= $item['product_type'] ?>">
                                                    <?= ucfirst($item['product_type']) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="text-right">
                                            <div class="text-lg font-medium">
                                                $<?= number_format(($item['discount_price'] ?? $item['price']) * $item['quantity'], 2) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                $<?= number_format($item['discount_price'] ?? $item['price'], 2) ?> × <?= $item['quantity'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="p-6 border-t">
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span>$<?= number_format($order['subtotal'], 2) ?></span>
                        </div>

                        <div class="flex justify-between">
                            <span>Shipping</span>
                            <span>$<?= number_format($order['shipping_fee'], 2) ?></span>
                        </div>

                        <?php if ($order['coupon_code']): ?>
                            <div class="flex justify-between">
                                <span>Discount (<?= htmlspecialchars($order['coupon_code']) ?>)</span>
                                <span class="text-green-600">-$<?= number_format($order['discount_amount'], 2) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="flex justify-between font-bold text-lg pt-3 border-t">
                            <span>Total</span>
                            <span>$<?= number_format($order['total_amount'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <p class="text-gray-600 mb-6">We've sent an order confirmation to your email. You'll receive another email when your order ships.</p>

                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="shop.php" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        Continue Shopping
                    </a>
                    <a href="orders.php" class="px-6 py-3 border border-green-600 text-green-600 rounded-lg hover:bg-green-50 transition">
                        View Order History
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>

</html>