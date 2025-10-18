<?php
require_once 'db_connect.php';
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_msg'] = "You must be logged in to manage exchange interests.";
    header("Location: login.php");
    exit();
}

// Check required parameters
if (!isset($_POST['interest_id']) || !isset($_POST['action'])) {
    $_SESSION['error_msg'] = "Invalid request.";
    header("Location: exchange.php");
    exit();
}

$interest_id = intval($_POST['interest_id']);
$action = $_POST['action'];

// Get the interest details to verify ownership
$query = "SELECT ei.*, e.user_id 
          FROM exchange_interests ei
          JOIN exchange_offers e ON ei.exchange_id = e.id
          WHERE ei.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $interest_id);
$stmt->execute();
$result = $stmt->get_result();
$interest = $result->fetch_assoc();

// Verify the current user owns the exchange offer
if (!$interest || $interest['user_id'] != $_SESSION['user_id']) {
    $_SESSION['error_msg'] = "You don't have permission to manage this interest.";
    header("Location: exchange.php");
    exit();
}

// Process the action
if ($action == 'accept') {
    // Simply accept this interest without rejecting others or marking exchange as completed
    $query = "UPDATE exchange_interests 
              SET status = 'accepted' 
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $interest_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Interest accepted successfully!";
    } else {
        $_SESSION['error_msg'] = "Error accepting interest. Please try again.";
    }
} elseif ($action == 'reject') {
    $query = "UPDATE exchange_interests 
              SET status = 'rejected' 
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $interest_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Interest rejected successfully.";
    } else {
        $_SESSION['error_msg'] = "Error rejecting interest. Please try again.";
    }
} else {
    $_SESSION['error_msg'] = "Invalid action.";
}

header("Location: exchange_detail.php?id=" . $interest['exchange_id']);
exit();
?>