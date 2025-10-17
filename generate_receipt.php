<?php
require_once 'db_connect.php';
require_once('fpdf/fpdf.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['order_id'])) {
    header("Location: orders.php");
    exit();
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// Verify the order belongs to the user
$stmt = $conn->prepare("SELECT o.*, u.firstname, u.lastname, u.email, u.phone 
                       FROM orders o 
                       JOIN users u ON o.user_id = u.id 
                       WHERE o.id = ? AND o.user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Get order items
$stmt = $conn->prepare("SELECT oi.*, p.name, p.product_type 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();

// Set colors and styles
$primaryColor = array(76, 175, 80); // Green
$secondaryColor = array(240, 240, 240);

// Header with logo
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(0, 10, 'Green Legacy', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'Order Receipt', 0, 1, 'C');
$pdf->Ln(10);

// Order Information section
$pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Order Information', 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(40, 7, 'Order Number:', 0, 0);
$pdf->Cell(0, 7, '#'.$order['order_number'], 0, 1);

$pdf->Cell(40, 7, 'Date:', 0, 0);
$pdf->Cell(0, 7, date('F j, Y', strtotime($order['created_at'])), 0, 1);

$pdf->Cell(40, 7, 'Status:', 0, 0);
$pdf->Cell(0, 7, ucfirst($order['status']), 0, 1);

$pdf->Cell(40, 7, 'Customer:', 0, 0);
$pdf->Cell(0, 7, $order['firstname'].' '.$order['lastname'], 0, 1);

$pdf->Cell(40, 7, 'Email:', 0, 0);
$pdf->Cell(0, 7, $order['email'], 0, 1);

if (!empty($order['phone'])) {
    $pdf->Cell(40, 7, 'Phone:', 0, 0);
    $pdf->Cell(0, 7, $order['phone'], 0, 1);
}

$pdf->Ln(10);

// Shipping Information
$pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Shipping Information', 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 7, $order['shipping_address'], 0, 'L');

if ($order['billing_address'] && $order['billing_address'] !== $order['shipping_address']) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, 'Billing Address:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 7, $order['billing_address'], 0, 'L');
}

$pdf->Ln(10);

// Order Items
$pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Order Items', 1, 1, 'L', true);

// Table header
$pdf->SetFillColor(220, 220, 220);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(100, 8, 'Product', 1, 0, 'L', true);
$pdf->Cell(30, 8, 'Type', 1, 0, 'L', true);
$pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Price', 1, 0, 'R', true);
$pdf->Cell(20, 8, 'Total', 1, 1, 'R', true);

$pdf->SetFont('Arial', '', 10);
$fill = false;

// Table rows
foreach ($order_items as $item) {
    $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
    $pdf->Cell(100, 8, $item['name'], 1, 0, 'L', $fill);
    $pdf->Cell(30, 8, ucfirst($item['product_type']), 1, 0, 'L', $fill);
    $pdf->Cell(20, 8, $item['quantity'], 1, 0, 'C', $fill);
    $pdf->Cell(20, 8, '$'.number_format($item['price'], 2), 1, 0, 'R', $fill);
    $pdf->Cell(20, 8, '$'.number_format($item['price'] * $item['quantity'], 2), 1, 1, 'R', $fill);
    $fill = !$fill;
}

$pdf->Ln(5);

// Order Summary
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(150, 7, 'Subtotal:', 0, 0, 'R');
$pdf->Cell(20, 7, '$'.number_format($order['subtotal'], 2), 0, 1, 'R');

$pdf->Cell(150, 7, 'Shipping Fee:', 0, 0, 'R');
$pdf->Cell(20, 7, '$'.number_format($order['shipping_fee'], 2), 0, 1, 'R');

if ($order['discount_amount'] > 0) {
    $pdf->Cell(150, 7, 'Discount:', 0, 0, 'R');
    $pdf->Cell(20, 7, '- $'.number_format($order['discount_amount'], 2), 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(150, 10, 'Total:', 0, 0, 'R');
$pdf->Cell(20, 10, '$'.number_format($order['total_amount'], 2), 0, 1, 'R');

$pdf->Ln(15);

// Footer
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 7, 'Thank you for supporting sustainable agriculture!', 0, 1, 'C');
$pdf->Cell(0, 7, 'For any questions, please contact: support@greenlegacy.com', 0, 1, 'C');
$pdf->Cell(0, 7, 'www.greenlegacy.com', 0, 1, 'C');

// Output the PDF
$pdf->Output('D', 'receipt_'.$order['order_number'].'.pdf');
exit();
?>