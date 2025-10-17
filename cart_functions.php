<?php
require_once 'db_connect.php';

class Cart
{
    private $conn;
    private $cart_id;
    private $user_id;
    private $session_id;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->initializeCart();
    }

    private function initializeCart()
    {
        session_start();
        $this->session_id = session_id();

        if (isset($_SESSION['user_id'])) {
            $this->user_id = $_SESSION['user_id'];
            $this->loadUserCart();
        } else {
            $this->loadSessionCart();
        }
    }

    private function loadUserCart()
    {
        $stmt = $this->conn->prepare("SELECT id FROM carts WHERE user_id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $cart = $result->fetch_assoc();
            $this->cart_id = $cart['id'];
        } else {
            if (isset($_SESSION['cart_id'])) {
                $this->mergeSessionCartToUser($_SESSION['cart_id'], $this->user_id);
            } else {
                $this->createNewCart();
            }
        }
    }

    private function loadSessionCart()
    {
        if (isset($_SESSION['cart_id'])) {
            $this->cart_id = $_SESSION['cart_id'];
        } else {
            $this->createNewCart();
        }
    }

    private function createNewCart()
    {
        $stmt = $this->conn->prepare("INSERT INTO carts (user_id, session_id) VALUES (?, ?)");
        $user_id = $this->user_id ?? NULL;
        $stmt->bind_param("is", $user_id, $this->session_id);
        $stmt->execute();
        $this->cart_id = $stmt->insert_id;
        $_SESSION['cart_id'] = $this->cart_id;
    }

    private function mergeSessionCartToUser($session_cart_id, $user_id)
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("INSERT INTO carts (user_id) VALUES (?)");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $new_cart_id = $stmt->insert_id;

            $stmt = $this->conn->prepare("UPDATE cart_items SET cart_id = ? WHERE cart_id = ?");
            $stmt->bind_param("ii", $new_cart_id, $session_cart_id);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM carts WHERE id = ?");
            $stmt->bind_param("i", $session_cart_id);
            $stmt->execute();

            $this->conn->commit();
            $this->cart_id = $new_cart_id;
            $_SESSION['cart_id'] = $this->cart_id;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function addItem($product_id, $quantity = 1)
    {
        $product = $this->getProductDetails($product_id);

        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }

        if ($product['stock'] < $quantity) {
            return ['success' => false, 'message' => 'Not enough stock available'];
        }

        $stmt = $this->conn->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $this->cart_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $item = $result->fetch_assoc();
            $new_quantity = $item['quantity'] + $quantity;

            if ($product['stock'] < $new_quantity) {
                return ['success' => false, 'message' => 'Not enough stock available'];
            }

            $stmt = $this->conn->prepare("UPDATE cart_items SET quantity = ?, price = ?, discount_price = ? WHERE id = ?");
            $stmt->bind_param("iddi", $new_quantity, $product['price'], $product['discount_price'], $item['id']);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, price, discount_price) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiidd", $this->cart_id, $product_id, $quantity, $product['price'], $product['discount_price']);
        }

        if ($stmt->execute()) {
            return ['success' => true, 'cart_count' => $this->getCartCount()];
        } else {
            return ['success' => false, 'message' => 'Failed to add item to cart'];
        }
    }

    public function updateItem($product_id, $quantity)
    {
        $product = $this->getProductDetails($product_id);

        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }

        if ($product['stock'] < $quantity) {
            return ['success' => false, 'message' => 'Not enough stock available'];
        }

        if ($quantity <= 0) {
            return $this->removeItem($product_id);
        }

        $stmt = $this->conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $quantity, $this->cart_id, $product_id);

        if ($stmt->execute()) {
            return ['success' => true, 'cart_count' => $this->getCartCount()];
        } else {
            return ['success' => false, 'message' => 'Failed to update item'];
        }
    }

    public function removeItem($product_id)
    {
        $stmt = $this->conn->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $this->cart_id, $product_id);

        if ($stmt->execute()) {
            return ['success' => true, 'cart_count' => $this->getCartCount()];
        } else {
            return ['success' => false, 'message' => 'Failed to remove item'];
        }
    }

    public function getCart()
    {
        $cart = [
            'items' => [],
            'total' => 0,
            'count' => 0
        ];

        $stmt = $this->conn->prepare("
            SELECT ci.*, p.name, p.slug, pi.quantity as stock, 
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            JOIN product_inventory pi ON p.id = pi.product_id
            WHERE ci.cart_id = ?
        ");
        $stmt->bind_param("i", $this->cart_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $subtotal = 0;
        $item_count = 0;

        while ($row = $result->fetch_assoc()) {
            $price = $row['discount_price'] ?? $row['price'];
            $row['total_price'] = $price * $row['quantity'];
            $subtotal += $row['total_price'];
            $item_count += $row['quantity'];

            $cart['items'][] = $row;
        }

        $cart['total'] = $subtotal;
        $cart['count'] = $item_count;

        return $cart;
    }

    public function getCartCount()
    {
        $stmt = $this->conn->prepare("SELECT SUM(quantity) as count FROM cart_items WHERE cart_id = ?");
        $stmt->bind_param("i", $this->cart_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['count'] ?? 0;
    }

    public function clearCart()
    {
        $stmt = $this->conn->prepare("DELETE FROM cart_items WHERE cart_id = ?");
        $stmt->bind_param("i", $this->cart_id);
        return $stmt->execute();
    }

    private function getProductDetails($product_id)
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, pi.quantity as stock 
            FROM products p
            JOIN product_inventory pi ON p.id = pi.product_id
            WHERE p.id = ? AND p.is_active = 1
        ");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    public function applyCoupon($coupon_code)
    {
        $coupon = $this->validateCoupon($coupon_code);
        if (!$coupon['valid']) {
            return $coupon;
        }

        $cart = $this->getCart();
        $discount_amount = 0;

        if ($coupon['data']['discount_type'] === 'percentage') {
            $discount = $cart['total'] * ($coupon['data']['discount_value'] / 100);
            if ($coupon['data']['max_discount_amount'] > 0) {
                $discount = min($discount, $coupon['data']['max_discount_amount']);
            }
            $discount_amount = $discount;
        } else {
            $discount_amount = min($coupon['data']['discount_value'], $cart['total']);
        }

        return [
            'success' => true,
            'coupon_id' => $coupon['data']['id'],
            'coupon_code' => $coupon_code,
            'discount_amount' => $discount_amount,
            'new_total' => $cart['total'] - $discount_amount
        ];
    }

    private function validateCoupon($coupon_code)
    {
        $current_date = date('Y-m-d H:i:s');
        $cart = $this->getCart();

        $stmt = $this->conn->prepare("
            SELECT * FROM coupons 
            WHERE code = ? 
            AND is_active = 1 
            AND start_date <= ? 
            AND end_date >= ?
        ");
        $stmt->bind_param("sss", $coupon_code, $current_date, $current_date);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['valid' => false, 'message' => 'Invalid or expired coupon code'];
        }

        $coupon = $result->fetch_assoc();

        // Check per-user usage limit
        if ($this->user_id) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as user_usage 
                FROM coupon_usage 
                WHERE coupon_id = ? AND user_id = ?
            ");
            $stmt->bind_param("ii", $coupon['id'], $this->user_id);
            $stmt->execute();
            $usage = $stmt->get_result()->fetch_assoc();

            if ($coupon['usage_limit'] == 1 && $usage['user_usage'] >= 1) {
                return ['valid' => false, 'message' => 'You have already used this coupon'];
            }
        }

        // Check global usage limit
        if ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
            return ['valid' => false, 'message' => 'This coupon has reached its usage limit'];
        }

        // Check minimum order amount
        if ($cart['total'] < $coupon['min_order_amount']) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Minimum order amount of $%s required for this coupon',
                    number_format($coupon['min_order_amount'], 2)
                )
            ];
        }

        return ['valid' => true, 'data' => $coupon];
    }

    public function checkout($payment_method, $shipping_address, $billing_address = null, $notes = '', $coupon_id = null, $discount_amount = 0, $shipping_fee = 2.00, $guest_info = [])
    {
        $this->conn->begin_transaction();
        try {
            $cart = $this->getCart();

            if (empty($cart['items'])) {
                throw new Exception('Cart is empty');
            }

            // Validate stock
            foreach ($cart['items'] as $item) {
                if ($item['quantity'] > $item['stock']) {
                    throw new Exception('Not enough stock for ' . $item['name']);
                }
            }

            $total = $cart['total'] + $shipping_fee - $discount_amount;
            $order_number = 'ORD-' . strtoupper(uniqid());
            $billing_address = $billing_address ?? $shipping_address;

            // Ensure payment_method is valid
            $payment_method = in_array($payment_method, ['cod', 'card']) ? $payment_method : 'cod';

            // Handle guest information
            $guest_notes = $notes;
            if (!empty($guest_info)) {
                $guest_details = "Guest Information:\n";
                $guest_details .= "Name: " . ($guest_info['firstname'] ?? '') . " " . ($guest_info['lastname'] ?? '') . "\n";
                $guest_details .= "Email: " . ($guest_info['email'] ?? '') . "\n";
                $guest_details .= "Phone: " . ($guest_info['phone'] ?? '') . "\n";
                $guest_notes = $guest_details . "\n" . $notes;
            }

            // Fix: Correct parameter binding - removed extra 's'
            $stmt = $this->conn->prepare("
            INSERT INTO orders (user_id, session_id, order_number, status, total_amount, 
                              payment_method, coupon_id, discount_amount, shipping_fee, subtotal,
                              shipping_address, billing_address, notes)
            VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->conn->error);
            }

            $user_id = $this->user_id ?? NULL;

            // Correct bind_param string - fixed the format
            $bind_result = $stmt->bind_param(
                "issdsiddssss", // Fixed: is s d s i d d s s s s
                $user_id,
                $this->session_id,
                $order_number,
                $total,
                $payment_method,
                $coupon_id,
                $discount_amount,
                $shipping_fee,
                $cart['total'],
                $shipping_address,
                $billing_address,
                $guest_notes
            );

            if (!$bind_result) {
                throw new Exception('Bind failed: ' . $stmt->error);
            }

            if (!$stmt->execute()) {
                throw new Exception('Failed to create order: ' . $stmt->error);
            }

            $order_id = $this->conn->insert_id;

            // Add order items and update inventory
            foreach ($cart['items'] as $item) {
                $stmt = $this->conn->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price, discount_price)
                VALUES (?, ?, ?, ?, ?)
            ");
                $stmt->bind_param(
                    "iiidd",
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $item['discount_price']
                );
                if (!$stmt->execute()) {
                    throw new Exception('Failed to add order items: ' . $stmt->error);
                }

                // Update inventory
                $new_quantity = $item['stock'] - $item['quantity'];
                $stmt = $this->conn->prepare("
                UPDATE product_inventory 
                SET quantity = ?, is_in_stock = IF(? > 0, TRUE, FALSE)
                WHERE product_id = ?
            ");
                $stmt->bind_param("iii", $new_quantity, $new_quantity, $item['product_id']);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update inventory: ' . $stmt->error);
                }
            }

            // Update coupon usage if applicable
            if ($coupon_id) {
                $this->updateCouponUsage($coupon_id, $order_id, $discount_amount);
            }

            // Clear cart
            if (!$this->clearCart()) {
                throw new Exception('Failed to clear cart');
            }

            $this->conn->commit();

            // Send confirmation email
            $email_to = !empty($guest_info['email']) ? $guest_info['email'] : null;
            $this->sendOrderConfirmationEmail($order_id, $user_id, $email_to);

            return ['success' => true, 'order_id' => $order_id, 'order_number' => $order_number];
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Checkout failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Checkout failed: ' . $e->getMessage()];
        }
    }

    private function updateCouponUsage($coupon_id, $order_id, $discount_amount)
    {
        // Increment used count
        $stmt = $this->conn->prepare("
            UPDATE coupons 
            SET used_count = used_count + 1 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $coupon_id);
        $stmt->execute();

        // Record coupon usage
        $user_id = $this->user_id ?? 0;
        $stmt = $this->conn->prepare("
            INSERT INTO coupon_usage 
            (coupon_id, user_id, order_id, discount_amount) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiid", $coupon_id, $user_id, $order_id, $discount_amount);
        $stmt->execute();
    }

    private function sendOrderConfirmationEmail($order_id, $user_id, $guest_email = null)
    {
        require_once 'PHPMailer/src/Exception.php';
        require_once 'PHPMailer/src/PHPMailer.php';
        require_once 'PHPMailer/src/SMTP.php';
        require_once 'fpdf/fpdf.php';

        // Get order details
        $stmt = $this->conn->prepare("
        SELECT o.*, 
               u.email, u.firstname, u.lastname, u.phone,
               c.code as coupon_code, c.discount_value, c.discount_type
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN coupons c ON o.coupon_id = c.id
        WHERE o.id = ?
    ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if (!$order) return false;

        // Get order items
        $stmt = $this->conn->prepare("
        SELECT oi.*, p.name, p.product_type 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Generate PDF
        $pdf = $this->generateReceiptPDF($order, $items);
        $pdf_filename = 'receipt_' . $order['order_number'] . '.pdf';
        $pdf_path = sys_get_temp_dir() . '/' . $pdf_filename;
        $pdf->Output($pdf_path, 'F');

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'info.afnan27@gmail.com';
            $mail->Password = 'rokp jusi apxx wjkn';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('orders@greenlegacy.com', 'Green Legacy');

            // Determine recipient email
            if ($guest_email) {
                // Use guest email for non-logged-in users
                $mail->addAddress($guest_email);
            } else if ($order['email']) {
                // Use user email for logged-in users
                $mail->addAddress($order['email'], $order['firstname'] . ' ' . $order['lastname']);
            } else {
                // Fallback - shouldn't happen but just in case
                return false;
            }

            $mail->addReplyTo('support@greenlegacy.com', 'Green Legacy Support');

            // Attach PDF
            $mail->addAttachment($pdf_path, $pdf_filename);

            $mail->isHTML(true);
            $mail->Subject = 'Your Green Legacy Order #' . $order['order_number'];

            $mail->Body = $this->buildOrderEmailBody($order, $items);
            $mail->AltBody = $this->buildOrderEmailTextBody($order, $items);

            $mail->send();

            // Delete temporary PDF file
            unlink($pdf_path);

            return true;
        } catch (Exception $e) {
            error_log("Order confirmation email failed: " . $mail->ErrorInfo);
            return false;
        }
    }

    private function generateReceiptPDF($order, $items)
    {
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
        $pdf->Cell(0, 7, '#' . $order['order_number'], 0, 1);

        $pdf->Cell(40, 7, 'Date:', 0, 0);
        $pdf->Cell(0, 7, date('F j, Y', strtotime($order['created_at'])), 0, 1);

        $pdf->Cell(40, 7, 'Status:', 0, 0);
        $pdf->Cell(0, 7, ucfirst($order['status']), 0, 1);

        $pdf->Cell(40, 7, 'Customer:', 0, 0);
        $pdf->Cell(0, 7, $order['firstname'] . ' ' . $order['lastname'], 0, 1);

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
        foreach ($items as $item) {
            $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
            $pdf->Cell(100, 8, $item['name'], 1, 0, 'L', $fill);
            $pdf->Cell(30, 8, ucfirst($item['product_type']), 1, 0, 'L', $fill);
            $pdf->Cell(20, 8, $item['quantity'], 1, 0, 'C', $fill);
            $pdf->Cell(20, 8, '$' . number_format($item['price'], 2), 1, 0, 'R', $fill);
            $pdf->Cell(20, 8, '$' . number_format($item['price'] * $item['quantity'], 2), 1, 1, 'R', $fill);
            $fill = !$fill;
        }

        $pdf->Ln(5);

        // Order Summary
        $pdf->SetFont('Arial', '', 10);

        $pdf->Cell(150, 7, 'Subtotal:', 0, 0, 'R');
        $pdf->Cell(20, 7, '$' . number_format($order['subtotal'], 2), 0, 1, 'R');

        $pdf->Cell(150, 7, 'Shipping Fee:', 0, 0, 'R');
        $pdf->Cell(20, 7, '$' . number_format($order['shipping_fee'], 2), 0, 1, 'R');

        if ($order['discount_amount'] > 0) {
            $pdf->Cell(150, 7, 'Discount:', 0, 0, 'R');
            $pdf->Cell(20, 7, '- $' . number_format($order['discount_amount'], 2), 0, 1, 'R');
        }

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(150, 10, 'Total:', 0, 0, 'R');
        $pdf->Cell(20, 10, '$' . number_format($order['total_amount'], 2), 0, 1, 'R');

        $pdf->Ln(15);

        // Footer
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 7, 'Thank you for supporting sustainable agriculture!', 0, 1, 'C');
        $pdf->Cell(0, 7, 'For any questions, please contact: support@greenlegacy.com', 0, 1, 'C');
        $pdf->Cell(0, 7, 'www.greenlegacy.com', 0, 1, 'C');

        return $pdf;
    }

    private function buildOrderEmailBody($order, $items)
    {
        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }

                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }

                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }

                .order-details {
                    background: #f9f9f9;
                    padding: 20px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }

                .order-items {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }

                .order-items th,
                .order-items td {
                    padding: 10px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }

                .order-total {
                    background: #f0f0f0;
                    padding: 15px;
                    border-radius: 5px;
                }

                .status-pending {
                    color: #d97706;
                }

                .status-processing {
                    color: #1d4ed8;
                }

                .status-completed {
                    color: #065f46;
                }

                .status-cancelled {
                    color: #b91c1c;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="header">
                    <h1>Thank you for your order!</h1>
                    <p>Your order #<?= $order['order_number'] ?> has been received.</p>
                </div>

                <div class="order-details">
                    <h2>Order Details</h2>
                    <p><strong>Order Date:</strong> <?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></p>
                    <p><strong>Status:</strong> <span class="status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></p>
                    <p><strong>Payment Method:</strong> <?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?></p>
                </div>

                <h2>Order Items</h2>
                <table class="order-items">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>$<?= number_format($item['discount_price'] ?? $item['price'], 2) ?></td>
                                <td>$<?= number_format(($item['discount_price'] ?? $item['price']) * $item['quantity'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="order-total">
                    <p><strong>Subtotal:</strong> $<?= number_format($order['subtotal'], 2) ?></p>
                    <p><strong>Shipping:</strong> $<?= number_format($order['shipping_fee'], 2) ?></p>
                    <?php if ($order['coupon_code']): ?>
                        <p><strong>Discount (<?= htmlspecialchars($order['coupon_code']) ?>):</strong> -$<?= number_format($order['discount_amount'], 2) ?></p>
                    <?php endif; ?>
                    <p><strong>Total:</strong> $<?= number_format($order['total_amount'], 2) ?></p>
                </div>

                <div class="shipping-info">
                    <h2>Shipping Information</h2>
                    <p><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
                    <?php if ($order['billing_address'] && $order['billing_address'] !== $order['shipping_address']): ?>
                        <h3>Billing Address</h3>
                        <p><?= nl2br(htmlspecialchars($order['billing_address'])) ?></p>
                    <?php endif; ?>
                    <?php if ($order['phone']): ?>
                        <p><strong>Contact Phone:</strong> <?= htmlspecialchars($order['phone']) ?></p>
                    <?php endif; ?>
                </div>

                <p>We'll send you another email when your order ships. You can also check your order status anytime in your account.</p>

                <p>Thank you for shopping with Green Legacy!</p>
            </div>
        </body>

        </html>
<?php
        return ob_get_clean();
    }

    private function buildOrderEmailTextBody($order, $items)
    {
        $text = "Thank you for your order!\n\n";
        $text .= "Order #: " . $order['order_number'] . "\n";
        $text .= "Order Date: " . date('F j, Y g:i A', strtotime($order['created_at'])) . "\n";
        $text .= "Status: " . ucfirst($order['status']) . "\n";
        $text .= "Payment Method: " . ucwords(str_replace('_', ' ', $order['payment_method'])) . "\n\n";

        $text .= "Order Items:\n";
        foreach ($items as $item) {
            $text .= "- " . $item['name'] . " x" . $item['quantity'] . " ($" . number_format($item['discount_price'] ?? $item['price'], 2) . " each)\n";
        }

        $text .= "\nSubtotal: $" . number_format($order['subtotal'], 2) . "\n";
        $text .= "Shipping: $" . number_format($order['shipping_fee'], 2) . "\n";
        if ($order['coupon_code']) {
            $text .= "Discount (" . $order['coupon_code'] . "): -$" . number_format($order['discount_amount'], 2) . "\n";
        }
        $text .= "Total: $" . number_format($order['total_amount'], 2) . "\n\n";

        $text .= "Shipping Information:\n";
        $text .= $order['shipping_address'] . "\n";
        if ($order['billing_address'] && $order['billing_address'] !== $order['shipping_address']) {
            $text .= "\nBilling Address:\n";
            $text .= $order['billing_address'] . "\n";
        }
        if ($order['phone']) {
            $text .= "\nContact Phone: " . $order['phone'] . "\n";
        }

        $text .= "\nWe'll notify you when your order ships. Thank you for shopping with Green Legacy!\n";
        $text .= "https://greenlegacy.com\n";

        return $text;
    }
}
