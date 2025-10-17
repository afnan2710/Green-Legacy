<?php
// helpers.php
function getPaymentMethodDisplay($payment_method) {
    $methods = [
        'cod' => 'Cash on Delivery',
        'card' => 'Credit/Debit Card',
        'paypal' => 'PayPal',
        'stripe' => 'Stripe',
        '0' => 'Cash on Delivery', // Fallback for existing 0 values
        '1' => 'Credit/Debit Card' // Fallback for existing 1 values
    ];
    
    return $methods[$payment_method] ?? ucwords(str_replace('_', ' ', $payment_method));
}

function getPaymentMethodBadge($payment_method) {
    $badges = [
        'cod' => 'bg-blue-100 text-blue-800',
        'card' => 'bg-green-100 text-green-800',
        'paypal' => 'bg-blue-100 text-blue-800',
        'stripe' => 'bg-purple-100 text-purple-800',
        '0' => 'bg-blue-100 text-blue-800',
        '1' => 'bg-green-100 text-green-800'
    ];
    
    return $badges[$payment_method] ?? 'bg-gray-100 text-gray-800';
}
?>