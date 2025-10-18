<?php
require_once 'db_connect.php';
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_msg'] = "You must be logged in to delete an exchange offer.";
    header("Location: login.php");
    exit();
}

// Check if exchange ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error_msg'] = "No exchange offer specified.";
    header("Location: exchange.php");
    exit();
}

$exchange_id = intval($_GET['id']);

// Verify the user owns this offer
$query = "SELECT id, images FROM exchange_offers WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $exchange_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$offer = $result->fetch_assoc();

if (!$offer) {
    $_SESSION['error_msg'] = "Exchange offer not found or you don't have permission to delete it.";
    header("Location: exchange.php");
    exit();
}

// Delete associated images
if (!empty($offer['images'])) {
    $images = explode(',', $offer['images']);
    foreach ($images as $image) {
        if (file_exists($image)) {
            unlink($image);
        }
    }
}

// Delete from database
$query = "DELETE FROM exchange_offers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $exchange_id);

if ($stmt->execute()) {
    $_SESSION['success_msg'] = "Exchange offer deleted successfully.";
} else {
    $_SESSION['error_msg'] = "Error deleting exchange offer. Please try again.";
}

header("Location: exchange.php");
exit();
?>