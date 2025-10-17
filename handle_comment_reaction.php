<?php
require_once 'db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to react to comments.";
    header('Location: login.php');
    exit;
}

// Check required parameters
if (!isset($_POST['comment_id']) || !isset($_POST['reaction_type'])) {
    $_SESSION['error'] = "Invalid reaction request.";
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'forum.php'));
    exit;
}

$user_id = $_SESSION['user_id'];
$comment_id = (int)$_POST['comment_id'];
$reaction_type = $_POST['reaction_type'];

// Validate reaction type
$valid_reactions = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];
if (!in_array($reaction_type, $valid_reactions)) {
    $_SESSION['error'] = "Invalid reaction type.";
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'forum.php'));
    exit;
}

try {
    // Check if the comment exists
    $stmt = $conn->prepare("SELECT id FROM post_comments WHERE id = ?");
    $stmt->bind_param('i', $comment_id);
    $stmt->execute();
    
    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION['error'] = "Comment not found.";
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'forum.php'));
        exit;
    }

    // Check if user already reacted to this comment
    $stmt = $conn->prepare("SELECT id, reaction_type FROM comment_reactions WHERE user_id = ? AND comment_id = ?");
    $stmt->bind_param('ii', $user_id, $comment_id);
    $stmt->execute();
    $existing_reaction = $stmt->get_result()->fetch_assoc();

    if ($existing_reaction) {
        // If same reaction type, remove the reaction
        if ($existing_reaction['reaction_type'] === $reaction_type) {
            $delete_stmt = $conn->prepare("DELETE FROM comment_reactions WHERE id = ?");
            $delete_stmt->bind_param('i', $existing_reaction['id']);
            $delete_stmt->execute();
            $_SESSION['success'] = "Reaction removed.";
        } else {
            // Update to new reaction type
            $update_stmt = $conn->prepare("UPDATE comment_reactions SET reaction_type = ? WHERE id = ?");
            $update_stmt->bind_param('si', $reaction_type, $existing_reaction['id']);
            $update_stmt->execute();
            $_SESSION['success'] = "Reaction updated.";
        }
    } else {
        // Add new reaction
        $insert_stmt = $conn->prepare("INSERT INTO comment_reactions (comment_id, user_id, reaction_type) VALUES (?, ?, ?)");
        $insert_stmt->bind_param('iis', $comment_id, $user_id, $reaction_type);
        $insert_stmt->execute();
        $_SESSION['success'] = "Reaction added.";
    }
} catch (Exception $e) {
    $_SESSION['error'] = "An error occurred while processing your reaction.";
    error_log("Comment reaction error: " . $e->getMessage());
}

// Redirect back to the previous page
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'forum.php'));
exit;
?>