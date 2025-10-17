<?php
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_POST['post_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = (int)$_POST['post_id'];

// Check if the post exists and is published
$post_check = "SELECT id FROM forum_posts WHERE id = ? AND is_published = TRUE";
$stmt = $conn->prepare($post_check);
$stmt->bind_param('i', $post_id);
$stmt->execute();
$post_exists = $stmt->get_result()->num_rows > 0;

if (!$post_exists) {
    header('Location: forum.php');
    exit;
}

// Check if the post is already saved
$saved_check = "SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ?";
$stmt = $conn->prepare($saved_check);
$stmt->bind_param('ii', $user_id, $post_id);
$stmt->execute();
$is_saved = $stmt->get_result()->num_rows > 0;

if ($is_saved) {
    // Unsave the post
    $delete_query = "DELETE FROM saved_posts WHERE user_id = ? AND post_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('ii', $user_id, $post_id);
    $stmt->execute();
} else {
    // Save the post
    $insert_query = "INSERT INTO saved_posts (user_id, post_id) VALUES (?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param('ii', $user_id, $post_id);
    $stmt->execute();
}

// Redirect back to the previous page
$referer = $_SERVER['HTTP_REFERER'] ?? 'forum.php';
header("Location: $referer");
exit;
?>