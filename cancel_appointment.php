<?php
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header("Location: appointments.php");
    exit();
}

$appointment_id = intval($_GET['id']);

// Verify the appointment belongs to the user and can be cancelled
$stmt = $conn->prepare("SELECT id FROM appointments 
                       WHERE id = ? AND user_id = ? AND status IN ('pending', 'confirmed')");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: appointments.php");
    exit();
}

// Cancel the appointment
$stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();

header("Location: appointment_details.php?id=$appointment_id");
exit();
?>