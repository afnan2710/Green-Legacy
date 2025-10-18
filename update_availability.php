<?php
require_once 'db_connect.php';

// Check consultant authentication
session_start();
if (!isset($_SESSION['consultant_logged_in']) || $_SESSION['consultant_logged_in'] !== true) {
    header('Location: consultant_login.php');
    exit;
}

$consultant_id = $_SESSION['consultant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $days = isset($_POST['days']) ? $_POST['days'] : [];
    $slots = isset($_POST['slots']) ? $_POST['slots'] : [];
    $notes = filter_input(INPUT_POST, 'availability_notes', FILTER_SANITIZE_STRING);
    
    try {
        // Convert arrays to JSON for storage
        $days_json = json_encode($days);
        $slots_json = json_encode($slots);
        
        // Check if availability record already exists
        $stmt = $conn->prepare("SELECT id FROM consultant_availability WHERE consultant_id = ?");
        $stmt->bind_param("i", $consultant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE consultant_availability SET days = ?, time_slots = ?, notes = ? WHERE consultant_id = ?");
            $stmt->bind_param("sssi", $days_json, $slots_json, $notes, $consultant_id);
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO consultant_availability (consultant_id, days, time_slots, notes) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $consultant_id, $days_json, $slots_json, $notes);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Availability updated successfully!";
        } else {
            throw new Exception("Failed to update availability");
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error updating availability: " . $e->getMessage());
        $_SESSION['error_message'] = "Error updating availability. Please try again.";
    }
    
    header('Location: consultant_schedule.php#availabilityContent');
    exit;
} else {
    header('Location: consultant_schedule.php');
    exit;
}