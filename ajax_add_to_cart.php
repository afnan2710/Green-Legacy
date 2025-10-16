<?php
require_once 'db_connect.php';
require_once 'cart_functions.php';

header('Content-Type: application/json');

// Check if product ID is provided
if (!isset($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

$product_id = (int)$_POST['product_id'];
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Initialize cart
$cart = new Cart($conn);

// Add item to cart
$result = $cart->addItem($product_id, $quantity);

// Get updated cart count
$cart_count = $cart->getCartCount();

// Return response with cart count
echo json_encode([
    'success' => $result['success'],
    'message' => $result['message'] ?? '',
    'cart_count' => $cart_count
]);
