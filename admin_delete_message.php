<?php
require_once 'db_connect.php';
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_messages.php");
    exit();
}
$message_id = $_GET['id'];

$stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
$stmt->bind_param("i", $message_id);
$stmt->execute();

$_SESSION['message'] = "Message deleted successfully";
header("Location: admin_messages.php");
exit();