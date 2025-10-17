<?php
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_POST['post_id'])) {
    header('Location: login.php');
    exit;
}

$post_id = (int)$_POST['post_id'];
$user_id = $_SESSION['user_id'];

// Verify the post belongs to the user
$check_query = "SELECT id, image_path FROM forum_posts WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param('ii', $post_id, $user_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if ($post) {
    // Delete associated image if it exists
    if ($post['image_path'] && file_exists($post['image_path'])) {
        unlink($post['image_path']);
    }
    
    // The ON DELETE CASCADE in the database will handle related comments and reactions
    $delete_query = "DELETE FROM forum_posts WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
}

header('Location: my_posts.php');
exit;
?>