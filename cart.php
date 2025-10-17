<?php
require_once 'db_connect.php';
require_once 'cart_functions.php';

// Initialize cart
$cart = new Cart($conn);

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update':
                if (isset($_POST['product_id'], $_POST['quantity'])) {
                    $result = $cart->updateItem($_POST['product_id'], $_POST['quantity']);
                }
                break;
            case 'remove':
                if (isset($_POST['product_id'])) {
                    $result = $cart->removeItem($_POST['product_id']);
                }
                break;
            case 'clear':
                $result = $cart->clearCart();
                break;
        }

        // Redirect to prevent form resubmission
        header('Location: cart.php');
        exit;
    }
}

// Get cart contents
$cart_contents = $cart->getCart();

include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-green: #2E8B57;
            --light-green: #a8e6cf;
        }

        body {
            font-family: 'Poppins', sans-serif;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            cursor: pointer;
            user-select: none;
        }

        .quantity-btn:hover {
            background-color: #e2e8f0;
        }

        .quantity-input {
            width: 50px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            border-left: none;
            border-right: none;
            -moz-appearance: textfield;
        }

        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mt-10 mb-6">Your Shopping Cart</h1>

        <?php if (empty($cart_contents['items'])): ?>
            <div class="text-center py-12">
                <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-600">Your cart is empty</h3>
                <p class="text-gray-500 mt-2">Start shopping to add items to your cart</p>
                <a href="shop.php" class="inline-block mt-4 px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    Continue Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Cart Items -->
                <div class="lg:w-2/3">
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="hidden md:grid grid-cols-12 bg-gray-50 p-4 border-b">
                            <div class="col-span-5 font-medium text-gray-600">Product</div>
                            <div class="col-span-2 font-medium text-gray-600 text-center">Price</div>
                            <div class="col-span-3 font-medium text-gray-600 text-center">Quantity</div>
                            <div class="col-span-2 font-medium text-gray-600 text-right">Total</div>
                        </div>

                        <?php foreach ($cart_contents['items'] as $item): ?>
                            <div class="p-4 border-b last:border-b-0">
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                                    <!-- Product Info -->
                                    <div class="md:col-span-5 flex items-center">
                                        <a href="product.php?id=<?= $item['product_id'] ?>" class="w-20 h-20 flex-shrink-0">
                                            <img src="<?= !empty($item['image']) ? 'uploads/products/' . htmlspecialchars($item['image']) : 'images/placeholder-product.jpg' ?>"
                                                alt="<?= htmlspecialchars($item['name']) ?>"
                                                class="w-full h-full object-cover rounded">
                                        </a>
                                        <div class="ml-4">
                                            <a href="product.php?id=<?= $item['product_id'] ?>" class="font-medium hover:text-green-600">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                            <div class="text-sm text-gray-500 mt-1">
                                                <?php if ($item['stock'] > 5): ?>
                                                    <span class="text-green-600">In Stock</span>
                                                <?php elseif ($item['stock'] > 0): ?>
                                                    <span class="text-yellow-600">Only <?= $item['stock'] ?> left</span>
                                                <?php else: ?>
                                                    <span class="text-red-600">Out of Stock</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Price -->
                                    <div class="md:col-span-2 text-center">
                                        <?php if ($item['discount_price']): ?>
                                            <span class="text-green-600 font-medium">$<?= number_format($item['discount_price'], 2) ?></span>
                                            <div class="text-sm text-gray-400 line-through">$<?= number_format($item['price'], 2) ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-700">$<?= number_format($item['price'], 2) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Quantity -->
                                    <div class="md:col-span-3">
                                        <form method="POST" class="flex items-center justify-center" id="quantity-form-<?= $item['product_id'] ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">

                                            <button type="button" class="quantity-btn minus"
                                                onclick="updateQuantity(<?= $item['product_id'] ?>, -1)">-</button>
                                            <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock'] ?>"
                                                class="quantity-input" id="quantity-<?= $item['product_id'] ?>"
                                                onchange="submitQuantityForm(<?= $item['product_id'] ?>)">
                                            <button type="button" class="quantity-btn plus"
                                                onclick="updateQuantity(<?= $item['product_id'] ?>, 1)">+</button>
                                        </form>
                                    </div>

                                    <!-- Total -->
                                    <div class="md:col-span-2 flex items-center justify-end">
                                        <span class="font-medium">$<?= number_format($item['total_price'], 2) ?></span>
                                        <form method="POST" class="ml-4">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Clear Cart -->
                        <div class="p-4 border-t flex justify-end">
                            <form method="POST">
                                <input type="hidden" name="action" value="clear">
                                <button type="submit" class="text-red-500 hover:text-red-700 flex items-center">
                                    <i class="fas fa-trash mr-2"></i> Clear Cart
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="lg:w-1/3">
                    <div class="bg-white rounded-lg shadow-sm p-6 sticky top-4">
                        <h2 class="text-xl font-bold mb-4">Order Summary</h2>

                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="font-medium">$<?= number_format($cart_contents['total'], 2) ?></span>
                            </div>

                            <div class="flex justify-between">
                                <span class="text-gray-600">Shipping</span>
                                <span class="font-medium">$2.00</span>
                            </div>

                            <div class="border-t pt-4 flex justify-between">
                                <span class="font-bold">Total</span>
                                <span class="font-bold text-green-600">$<?= number_format($cart_contents['total'] + 2, 2) ?></span>
                            </div>

                            <a href="checkout.php" class="block w-full bg-green-600 text-white py-3 rounded-lg text-center font-medium hover:bg-green-700 transition">
                                Proceed to Checkout
                            </a>

                            <a href="shop.php" class="block w-full border border-green-600 text-green-600 py-3 rounded-lg text-center font-medium hover:bg-green-50 transition">
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to all quantity buttons
            document.querySelectorAll('.quantity-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const form = this.closest('form');
                    const quantityInput = form.querySelector('input[name="quantity"]');
                    const productId = form.querySelector('input[name="product_id"]').value;
                    const isMinus = this.classList.contains('minus');

                    let newQuantity = parseInt(quantityInput.value);

                    if (isMinus) {
                        newQuantity--;
                        if (newQuantity < 1) {
                            showRemoveConfirmation(productId);
                            return;
                        }
                    } else {
                        newQuantity++;
                        if (newQuantity > parseInt(quantityInput.max)) {
                            alert('Cannot exceed available stock');
                            return;
                        }
                    }

                    quantityInput.value = newQuantity;
                    form.submit();
                });
            });

            // Add event listener to quantity inputs
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value < 1) {
                        this.value = 1;
                        alert('Quantity cannot be less than 1');
                        return;
                    }
                    this.closest('form').submit();
                });
            });
        });

        function showRemoveConfirmation(productId) {
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                const removeForm = document.createElement('form');
                removeForm.method = 'POST';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'remove';

                const productInput = document.createElement('input');
                productInput.type = 'hidden';
                productInput.name = 'product_id';
                productInput.value = productId;

                removeForm.appendChild(actionInput);
                removeForm.appendChild(productInput);
                document.body.appendChild(removeForm);
                removeForm.submit();
            }
        }
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>