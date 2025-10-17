<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

require_once 'db_connect.php';

// Get filter parameters from request
$status = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build base query
$query = "SELECT o.*, u.firstname, u.lastname 
          FROM orders o
          LEFT JOIN users u ON o.user_id = u.id
          WHERE 1=1";

$params = [];
$types = '';

// Apply filters
if (!empty($status)) {
    $query .= " AND o.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND o.created_at BETWEEN ? AND ?";
    $params[] = $date_from . ' 00:00:00';
    $params[] = $date_to . ' 23:59:59';
    $types .= 'ss';
}

$query .= " ORDER BY o.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);

// Generate CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="orders_report_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// CSV header
fputcsv($output, [
    'Order Number',
    'Customer',
    'Email',
    'Date',
    'Status',
    'Subtotal',
    'Discount',
    'Shipping',
    'Total',
    'Payment Method'
]);

// CSV data
foreach ($orders as $order) {
    fputcsv($output, [
        $order['order_number'],
        $order['firstname'] . ' ' . $order['lastname'],
        $order['email'] ?? '',
        $order['created_at'],
        ucfirst($order['status']),
        $order['subtotal'],
        $order['discount_amount'],
        $order['shipping_fee'],
        $order['total_amount'],
        ucwords(str_replace('_', ' ', $order['payment_method']))
    ]);
}

fclose($output);
exit;