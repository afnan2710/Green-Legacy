<?php
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_POST['post_id']) || !isset($_POST['reaction_type'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = (int)$_POST['post_id'];
$reaction_type = $_POST['reaction_type'];

// Check if the user already reacted to this post
$check_query = "SELECT id, reaction_type FROM post_reactions WHERE user_id = ? AND post_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param('ii', $user_id, $post_id);
$stmt->execute();
$existing_reaction = $stmt->get_result()->fetch_assoc();

if ($existing_reaction) {
    // If same reaction type, remove the reaction
    if ($existing_reaction['reaction_type'] === $reaction_type) {
        $delete_query = "DELETE FROM post_reactions WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $existing_reaction['id']);
        $stmt->execute();
    } else {
        // Update to new reaction type
        $update_query = "UPDATE post_reactions SET reaction_type = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('si', $reaction_type, $existing_reaction['id']);
        $stmt->execute();
    }
} else {
    // Add new reaction
    $insert_query = "INSERT INTO post_reactions (post_id, user_id, reaction_type) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param('iis', $post_id, $user_id, $reaction_type);
    $stmt->execute();
}

// Redirect back to the previous page
$referer = $_SERVER['HTTP_REFERER'] ?? 'forum.php';
header("Location: $referer");
exit;
?>