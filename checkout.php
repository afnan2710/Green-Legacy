<?php
require_once 'db_connect.php';
require_once 'cart_functions.php';

// Initialize cart
$cart = new Cart($conn);

// Get cart contents
$cart_contents = $cart->getCart();

// Redirect if cart is empty
if (empty($cart_contents['items'])) {
    header('Location: cart.php');
    exit;
}

// Handle coupon application
$coupon_error = null;
$coupon_success = null;
$applied_coupon = null;
$discount_amount = 0;

// Store form data to prevent loss on refresh
$form_data = [
    'firstname' => '',
    'lastname' => '',
    'email' => '',
    'phone' => '',
    'shipping_address' => '',
    'billing_address' => '',
    'notes' => '',
    'payment_method' => 'cod',
    'card_number' => '',
    'card_expiry' => '',
    'card_cvv' => ''
];

// If form was submitted, store the data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store all form data to prevent loss
    $form_fields = ['firstname', 'lastname', 'email', 'phone', 'shipping_address', 'billing_address', 'notes', 'payment_method', 'card_number', 'card_expiry', 'card_cvv'];
    foreach ($form_fields as $field) {
        if (isset($_POST[$field])) {
            $form_data[$field] = $_POST[$field];
        }
    }
    
    // Ensure payment method is set
    if (empty($form_data['payment_method'])) {
        $form_data['payment_method'] = 'cod';
    }

    // Handle coupon operations
    if (isset($_POST['apply_coupon'])) {
        $coupon_code = trim($_POST['coupon_code']);

        // Validate coupon
        $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND start_date <= NOW() AND end_date >= NOW()");
        $stmt->bind_param("s", $coupon_code);
        $stmt->execute();
        $coupon = $stmt->get_result()->fetch_assoc();

        if ($coupon) {
            // Check usage limit
            if ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
                $coupon_error = "This coupon has reached its usage limit";
            } else {
                // Check minimum order amount
                if ($cart_contents['total'] < $coupon['min_order_amount']) {
                    $coupon_error = "Minimum order amount of $" . number_format($coupon['min_order_amount'], 2) . " required (your current order is $" . number_format($cart_contents['total'], 2) . ")";
                } else {
                    // Calculate discount
                    if ($coupon['discount_type'] === 'percentage') {
                        $discount = $cart_contents['total'] * ($coupon['discount_value'] / 100);
                        if ($coupon['max_discount_amount'] && $discount > $coupon['max_discount_amount']) {
                            $discount = $coupon['max_discount_amount'];
                        }
                    } else {
                        $discount = min($coupon['discount_value'], $cart_contents['total']);
                    }

                    $discount_amount = $discount;
                    $applied_coupon = $coupon;
                    $coupon_success = "Coupon applied successfully!";

                    // Store coupon in session to persist after redirect
                    $_SESSION['applied_coupon'] = $applied_coupon;
                    $_SESSION['discount_amount'] = $discount_amount;

                    $coupon_success .= " You saved $" . number_format($discount_amount, 2);
                    if ($coupon['discount_type'] === 'percentage') {
                        $coupon_success .= " (" . $coupon['discount_value'] . "% off)";
                    }
                }
            }
        } else {
            $coupon_error = "Invalid coupon code";
        }
    }

    // Handle coupon removal
    if (isset($_POST['remove_coupon'])) {
        unset($_SESSION['applied_coupon']);
        unset($_SESSION['discount_amount']);
        $applied_coupon = null;
        $discount_amount = 0;
        $coupon_success = "Coupon removed successfully!";
    }

    // Handle checkout form submission
    if (isset($_POST['place_order'])) {
        $payment_method = $form_data['payment_method'];
        $shipping_address = $form_data['shipping_address'];
        $billing_address = !empty($form_data['billing_address']) ? $form_data['billing_address'] : $shipping_address;
        $notes = $form_data['notes'];
        $coupon_id = $applied_coupon['id'] ?? null;

        // For non-logged-in users, capture guest information
        $guest_info = [];
        if (!isset($_SESSION['user_id'])) {
            $guest_info = [
                'firstname' => $form_data['firstname'],
                'lastname' => $form_data['lastname'],
                'email' => $form_data['email'],
                'phone' => $form_data['phone']
            ];
        }

        // Calculate shipping fee
        $shipping_fee = 2.00;

        // Call checkout method with correct parameter order
        $result = $cart->checkout(
            $payment_method, 
            $shipping_address, 
            $billing_address, 
            $notes, 
            $coupon_id, 
            $discount_amount, 
            $shipping_fee, 
            $guest_info
        );

        if ($result['success']) {
            // Clear applied coupon from session
            unset($_SESSION['applied_coupon']);
            unset($_SESSION['discount_amount']);

            // Redirect to order confirmation page
            header('Location: order_confirmation.php?order_id=' . $result['order_id']);
            exit;
        } else {
            $error_message = $result['message'];
        }
    }
}

// Check if coupon is stored in session (for page refresh)
if (isset($_SESSION['applied_coupon']) && !$applied_coupon) {
    $applied_coupon = $_SESSION['applied_coupon'];
    $discount_amount = $_SESSION['discount_amount'] ?? 0;
}

// Get user details if logged in
$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Pre-fill form data for logged-in users
    if ($user) {
        $form_data['firstname'] = $user['firstname'];
        $form_data['lastname'] = $user['lastname'];
        $form_data['email'] = $user['email'];
        $form_data['phone'] = $user['phone'];
        $form_data['shipping_address'] = $user['address'] ?? '';
    }
}

$subtotal = $cart_contents['total'];
$coupon_suggestions = [];

// Get applicable coupons based on order amount
$stmt = $conn->prepare("
    SELECT * FROM coupons 
    WHERE is_active = 1 
    AND start_date <= NOW() 
    AND end_date >= NOW()
    AND min_order_amount <= ?
    AND (usage_limit IS NULL OR used_count < usage_limit)
    ORDER BY discount_value DESC
    LIMIT 3
");
$stmt->bind_param("d", $subtotal);
$stmt->execute();
$coupon_suggestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .payment-method {
            display: none;
        }

        .payment-method.active {
            display: block;
        }

        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body>
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mt-10 mb-6">Checkout</h1>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Checkout Form -->
            <div class="lg:w-2/3">
                <form method="POST" class="space-y-6" id="checkout-form">
                    <!-- Shipping Address -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-xl font-bold mb-4">Shipping Information</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php if ($user): ?>
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 mb-2">Full Name</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                        value="<?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>" readonly>
                                </div>

                                <div>
                                    <label class="block text-gray-700 mb-2">Email</label>
                                    <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                        value="<?= htmlspecialchars($user['email']) ?>" readonly>
                                </div>

                                <div>
                                    <label class="block text-gray-700 mb-2">Phone</label>
                                    <input type="tel" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                        value="<?= htmlspecialchars($user['phone']) ?>" readonly>
                                </div>
                            <?php else: ?>
                                <div>
                                    <label class="block text-gray-700 mb-2">First Name *</label>
                                    <input type="text" name="firstname" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                        value="<?= htmlspecialchars($form_data['firstname']) ?>" required>
                                </div>

                                <div>
                                    <label class="block text-gray-700 mb-2">Last Name *</label>
                                    <input type="text" name="lastname" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                        value="<?= htmlspecialchars($form_data['lastname']) ?>" required>
                                </div>

                                <div>
                                    <label class="block text-gray-700 mb-2">Email *</label>
                                    <input type="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                        value="<?= htmlspecialchars($form_data['email']) ?>" required>
                                </div>

                                <div>
                                    <label class="block text-gray-700 mb-2">Phone *</label>
                                    <input type="tel" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                        value="<?= htmlspecialchars($form_data['phone']) ?>" required>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-4">
                            <label class="block text-gray-700 mb-2">Shipping Address *</label>
                            <textarea name="shipping_address" class="w-full px-3 py-2 border border-gray-300 rounded-md" rows="4" required><?= htmlspecialchars($form_data['shipping_address']) ?></textarea>
                        </div>
                    </div>

                    <!-- Billing Address -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-xl font-bold mb-4">Billing Information</h2>

                        <div class="flex items-center mb-4">
                            <input type="checkbox" id="same_as_shipping" class="mr-2" checked>
                            <label for="same_as_shipping">Same as shipping address</label>
                        </div>

                        <div id="billing_address_fields" class="hidden">
                            <label class="block text-gray-700 mb-2">Billing Address</label>
                            <textarea name="billing_address" class="w-full px-3 py-2 border border-gray-300 rounded-md" rows="4"><?= htmlspecialchars($form_data['billing_address']) ?></textarea>
                        </div>
                    </div>

                    <!-- Coupon Suggestions -->
                    <?php if (!empty($coupon_suggestions) && !$applied_coupon): ?>
                        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                            <h3 class="text-lg font-semibold mb-3">Available Coupons for Your Order</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <?php foreach ($coupon_suggestions as $coupon): ?>
                                    <div class="border border-green-200 rounded-lg p-3 hover:bg-green-50 transition">
                                        <div class="font-bold text-green-700 mb-1"><?= htmlspecialchars($coupon['code']) ?></div>
                                        <div class="text-sm mb-1">
                                            <?php if ($coupon['discount_type'] === 'percentage'): ?>
                                                <?= $coupon['discount_value'] ?>% OFF
                                                <?php if ($coupon['max_discount_amount']): ?>
                                                    (Max $<?= $coupon['max_discount_amount'] ?>)
                                                <?php endif; ?>
                                            <?php else: ?>
                                                $<?= $coupon['discount_value'] ?> OFF
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Min. order $<?= $coupon['min_order_amount'] ?>
                                        </div>
                                        <button
                                            type="button"
                                            onclick="applySuggestedCoupon('<?= htmlspecialchars($coupon['code']) ?>')"
                                            class="mt-2 text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200">
                                            Apply
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Coupon Code -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-xl font-bold mb-4">Coupon Code</h2>

                        <?php if ($coupon_success): ?>
                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                                <?= htmlspecialchars($coupon_success) ?>
                                <?php if ($applied_coupon): ?>
                                    <span class="font-bold">(<?= htmlspecialchars($applied_coupon['code']) ?> applied)</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($coupon_error): ?>
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                                <?= htmlspecialchars($coupon_error) ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex">
                            <input type="text" name="coupon_code" class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md"
                                placeholder="Enter coupon code" value="<?= $applied_coupon ? '' : '' ?>">

                            <?php if ($applied_coupon): ?>
                                <button type="submit" name="remove_coupon" class="bg-red-600 text-white px-4 py-2 rounded-r-md hover:bg-red-700">
                                    Remove Coupon
                                </button>
                            <?php else: ?>
                                <button type="submit" name="apply_coupon" class="bg-green-600 text-white px-4 py-2 rounded-r-md hover:bg-green-700">
                                    Apply
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-xl font-bold mb-4">Payment Method *</h2>

                        <div class="space-y-3">
                            <div class="flex items-center">
                                <input type="radio" id="cod" name="payment_method" value="cod" class="mr-2"
                                    <?= ($form_data['payment_method'] === 'cod' || empty($form_data['payment_method'])) ? 'checked' : '' ?>>
                                <label for="cod">Cash on Delivery</label>
                            </div>

                            <div class="flex items-center">
                                <input type="radio" id="card" name="payment_method" value="card" class="mr-2"
                                    <?= $form_data['payment_method'] === 'card' ? 'checked' : '' ?>>
                                <label for="card">Credit/Debit Card</label>
                            </div>

                            <!-- Card payment details -->
                            <div id="card-details" class="payment-method p-4 border rounded-lg bg-gray-50">
                                <div class="mb-4">
                                    <label class="block text-gray-700 mb-2">Card Number *</label>
                                    <input type="text" name="card_number" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                        placeholder="1234 5678 9012 3456"
                                        value="<?= htmlspecialchars($form_data['card_number']) ?>"
                                        <?= $form_data['payment_method'] === 'card' ? 'required' : '' ?>>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-gray-700 mb-2">Expiry Date *</label>
                                        <input type="text" name="card_expiry" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                            placeholder="MM/YY"
                                            value="<?= htmlspecialchars($form_data['card_expiry']) ?>"
                                            <?= $form_data['payment_method'] === 'card' ? 'required' : '' ?>>
                                    </div>

                                    <div>
                                        <label class="block text-gray-700 mb-2">CVV *</label>
                                        <input type="text" name="card_cvv" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                            placeholder="123"
                                            value="<?= htmlspecialchars($form_data['card_cvv']) ?>"
                                            <?= $form_data['payment_method'] === 'card' ? 'required' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Notes -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-xl font-bold mb-4">Order Notes</h2>
                        <textarea name="notes" class="w-full px-3 py-2 border border-gray-300 rounded-md" rows="4"
                            placeholder="Any special instructions..."><?= htmlspecialchars($form_data['notes']) ?></textarea>
                    </div>

                    <button type="submit" name="place_order" class="w-full bg-green-600 text-white py-3 rounded-lg text-center font-medium hover:bg-green-700 transition">
                        Place Order
                    </button>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="lg:w-1/3">
                <div class="bg-white rounded-lg shadow-sm p-6 sticky top-4">
                    <h2 class="text-xl font-bold mb-4">Order Summary</h2>

                    <div class="space-y-4">
                        <?php foreach ($cart_contents['items'] as $item): ?>
                            <div class="flex justify-between">
                                <div>
                                    <span><?= htmlspecialchars($item['name']) ?></span>
                                    <span class="text-gray-500">× <?= $item['quantity'] ?></span>
                                </div>
                                <span>$<?= number_format($item['total_price'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <div class="border-t pt-4">
                            <div class="flex justify-between mb-2">
                                <span>Subtotal</span>
                                <span>$<?= number_format($cart_contents['total'], 2) ?></span>
                            </div>

                            <div class="flex justify-between mb-2">
                                <span>Shipping</span>
                                <span>$2.00</span>
                            </div>

                            <?php if ($applied_coupon): ?>
                                <div class="flex justify-between mb-2">
                                    <span>Discount (<?= htmlspecialchars($applied_coupon['code']) ?>)</span>
                                    <span class="text-green-600">-$<?= number_format($discount_amount, 2) ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="border-t pt-4 flex justify-between font-bold text-lg">
                                <span>Total</span>
                                <span>$<?= number_format($cart_contents['total'] + 2 - $discount_amount, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Toggle billing address fields
        document.getElementById('same_as_shipping').addEventListener('change', function() {
            const billingFields = document.getElementById('billing_address_fields');
            billingFields.classList.toggle('hidden', this.checked);

            if (this.checked) {
                document.querySelector('textarea[name="billing_address"]').value = '';
            }
        });

        // Toggle payment method details
        function updatePaymentMethod() {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
            const cardDetails = document.getElementById('card-details');
            
            if (selectedMethod === 'card') {
                cardDetails.classList.add('active');
                // Make card fields required
                document.querySelectorAll('#card-details input').forEach(input => {
                    input.required = true;
                });
            } else {
                cardDetails.classList.remove('active');
                // Remove required attribute from card fields
                document.querySelectorAll('#card-details input').forEach(input => {
                    input.required = false;
                });
            }
        }

        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', updatePaymentMethod);
        });

        // Initialize payment method on page load
        document.addEventListener('DOMContentLoaded', function() {
            updatePaymentMethod();
        });

        function applySuggestedCoupon(code) {
            document.querySelector('input[name="coupon_code"]').value = code;
            
            // Create hidden input for apply_coupon
            const applyInput = document.createElement('input');
            applyInput.type = 'hidden';
            applyInput.name = 'apply_coupon';
            applyInput.value = '1';
            
            document.getElementById('checkout-form').appendChild(applyInput);
            document.getElementById('checkout-form').submit();
        }

        // Enhanced form data preservation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('checkout-form');
            
            // Store form data before submission
            form.addEventListener('submit', function(e) {
                // For coupon operations, preserve all form data
                if (e.submitter && (e.submitter.name === 'apply_coupon' || e.submitter.name === 'remove_coupon')) {
                    e.preventDefault();
                    
                    // Create a new form submission
                    const newForm = document.createElement('form');
                    newForm.method = 'POST';
                    newForm.style.display = 'none';
                    
                    // Copy all form data
                    const formData = new FormData(form);
                    for (let [key, value] of formData) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        newForm.appendChild(input);
                    }
                    
                    // Add the specific action
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = e.submitter.name;
                    actionInput.value = '1';
                    newForm.appendChild(actionInput);
                    
                    document.body.appendChild(newForm);
                    newForm.submit();
                }
            });
        });
    </script>
</body>

</html>