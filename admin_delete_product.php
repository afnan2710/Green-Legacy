<?php
// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// Database connection
require_once 'db_connect.php';

// Get product ID
$productId = $_GET['id'] ?? 0;

if ($productId) {
    try {
        $conn->begin_transaction();
        
        // Get product images first
        $stmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $images = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Delete from product_attributes
        $stmt = $conn->prepare("DELETE FROM product_attributes WHERE product_id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $stmt->close();
        
        // Delete from product_inventory
        $stmt = $conn->prepare("DELETE FROM product_inventory WHERE product_id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $stmt->close();
        
        // Delete from product_images
        $stmt = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $stmt->close();
        
        // Delete from products
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $stmt->close();
        
        // Delete image files
        foreach ($images as $image) {
            $imagePath = '../uploads/products/' . $image['image_path'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        $conn->commit();
        $_SESSION['message'] = "Product deleted successfully";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "Error deleting product: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
} else {
    $_SESSION['message'] = "No product specified";
    $_SESSION['message_type'] = "error";
}


header("Location: admin_products.php");
exit;
?>