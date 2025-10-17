<?php
require_once 'db_connect.php';
require_once 'helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit();
}

$order_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch order details
$stmt = $conn->prepare("SELECT o.*, u.firstname, u.lastname, u.email, u.phone, 
                       u.address as user_address, c.code as coupon_code
                       FROM orders o 
                       JOIN users u ON o.user_id = u.id 
                       LEFT JOIN coupons c ON o.coupon_id = c.id
                       WHERE o.id = ? AND o.user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Fetch order items
$stmt = $conn->prepare("SELECT oi.*, p.name, p.product_type, pi.image_path 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                       WHERE oi.order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$order['payment_method'] = $order['payment_method'] == '0' ? 'cod' : ($order['payment_method'] == '1' ? 'card' : $order['payment_method']);
?>

<?php include 'navbar.php'; ?>

<div class="container mx-auto mt-10 px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Order #<?php echo $order['order_number']; ?></h1>
            <div>
                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full 
                    <?php echo $order['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($order['status'] === 'processing' ? 'bg-blue-100 text-blue-800' : ($order['status'] === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800')); ?>">
                    <?php echo ucfirst($order['status']); ?>
                </span>
                <a href="generate_receipt.php?order_id=<?php echo $order_id; ?>" class="ml-4 inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Download Receipt
                </a>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h2 class="text-lg font-semibold text-gray-700 mb-4">Shipping Information</h2>
                    <p class="text-gray-600">
                        <?php echo $order['firstname'] . ' ' . $order['lastname']; ?><br>
                        <?php echo $order['shipping_address'] ? nl2br(htmlspecialchars($order['shipping_address'])) : nl2br(htmlspecialchars($order['user_address'])); ?><br>
                        Phone: <?php echo $order['phone']; ?>
                    </p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-700 mb-4">Order Details</h2>
                    <div class="space-y-2">
                        <p class="text-gray-600"><span class="font-medium">Order Date:</span> <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></p>
                        <p class="text-gray-600"><span class="font-medium">Payment Method:</span>
                            <span class="<?= getPaymentMethodBadge($order['payment_method']) ?> px-2 py-1 rounded-full text-sm">
                                <?= getPaymentMethodDisplay($order['payment_method']) ?>
                            </span>
                        </p>
                        <?php if ($order['coupon_code']): ?>
                            <p class="text-gray-600"><span class="font-medium">Coupon Code:</span> <?php echo $order['coupon_code']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Order Items</h2>

            <div class="space-y-4">
                <?php foreach ($order_items as $item): ?>
                    <div class="flex items-start border-b border-gray-200 pb-4">
                        <?php if ($item['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-20 h-20 object-cover rounded mr-4">
                        <?php else: ?>
                            <div class="w-20 h-20 bg-gray-100 rounded mr-4 flex items-center justify-center">
                                <svg class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        <?php endif; ?>

                        <div class="flex-1">
                            <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p class="text-sm text-gray-500"><?php echo ucfirst($item['product_type']); ?></p>
                            <p class="text-sm text-gray-500">Quantity: <?php echo $item['quantity']; ?></p>
                        </div>

                        <div class="text-right">
                            <p class="font-medium text-gray-800">$<?php echo number_format($item['price'], 2); ?></p>
                            <p class="text-sm text-gray-500">Total: $<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 border-t border-gray-200 pt-4">
                <div class="flex justify-between py-2">
                    <span class="text-gray-600">Subtotal:</span>
                    <span class="text-gray-800">$<?php echo number_format($order['total_amount'] - $order['shipping_fee'], 2); ?></span>
                </div>

                <div class="flex justify-between py-2">
                    <span class="text-gray-600">Shipping Fee:</span>
                    <span class="text-gray-800">$<?php echo number_format($order['shipping_fee'], 2); ?></span>
                </div>

                <?php if ($order['discount_amount'] > 0): ?>
                    <div class="flex justify-between py-2">
                        <span class="text-gray-600">Discount:</span>
                        <span class="text-red-600">-$<?php echo number_format($order['discount_amount'], 2); ?></span>
                    </div>
                <?php endif; ?>

                <div class="flex justify-between py-2 font-bold text-lg">
                    <span>Total:</span>
                    <span>$<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>