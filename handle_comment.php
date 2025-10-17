<?php
require_once 'db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to perform this action.";
    header('Location: login.php');
    exit;
}

// Check if required parameters are present
if (!isset($_POST['action']) || !isset($_POST['post_id'])) {
    $_SESSION['error'] = "Invalid request parameters.";
    header('Location: forum.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = (int)$_POST['post_id'];
$action = $_POST['action'];

try {
    switch ($action) {
        case 'add':
            // Handle adding a new comment or reply
            if (!isset($_POST['content']) || empty(trim($_POST['content']))) {
                $_SESSION['error'] = "Comment content cannot be empty.";
                break;
            }

            $content = trim($_POST['content']);
            $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

            // Validate parent_id if it's a reply
            if ($parent_id) {
                $stmt = $conn->prepare("SELECT id FROM post_comments WHERE id = ? AND post_id = ?");
                $stmt->bind_param('ii', $parent_id, $post_id);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    $_SESSION['error'] = "Invalid parent comment.";
                    break;
                }
            }

            // Insert the new comment
            $stmt = $conn->prepare("INSERT INTO post_comments (post_id, user_id, parent_id, content) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('iiis', $post_id, $user_id, $parent_id, $content);
            $stmt->execute();

            // After successfully adding a comment
            if ($parent_id === null) { // Only for top-level comments
                $stmt = $conn->prepare("
                    INSERT INTO notifications (recipient_id, sender_id, type, post_id, comment_id)
                    SELECT fp.user_id, ?, 'comment', fp.id, ?
                    FROM forum_posts fp
                    WHERE fp.id = ? AND fp.user_id != ?
                ");
                $stmt->bind_param('iiii', $user_id, $comment_id, $post_id, $user_id);
                $stmt->execute();
            }

            // After successfully adding a reply
            if ($parent_id !== null) {
                $stmt = $conn->prepare("
                    INSERT INTO notifications (recipient_id, sender_id, type, post_id, comment_id)
                    SELECT pc.user_id, ?, 'reply', pc.post_id, ?
                    FROM post_comments pc
                    WHERE pc.id = ? AND pc.user_id != ?
                ");
                $stmt->bind_param('iiii', $user_id, $comment_id, $parent_id, $user_id);
                $stmt->execute();
            }

            // After successfully adding a reaction
            $stmt = $conn->prepare("
                INSERT INTO notifications (recipient_id, sender_id, type, post_id, reaction_id)
                SELECT fp.user_id, ?, 'reaction_post', fp.id, ?
                FROM forum_posts fp
                WHERE fp.id = ? AND fp.user_id != ?
            ");
            $stmt->bind_param('iiii', $user_id, $reaction_id, $post_id, $user_id);
            $stmt->execute();

            // After successfully adding a reaction to a comment
            $stmt = $conn->prepare("
                INSERT INTO notifications (recipient_id, sender_id, type, post_id, comment_id, reaction_id)
                SELECT pc.user_id, ?, 'reaction_comment', pc.post_id, pc.id, ?
                FROM post_comments pc
                WHERE pc.id = ? AND pc.user_id != ?
            ");
            $stmt->bind_param('iiii', $user_id, $reaction_id, $comment_id, $user_id);
            $stmt->execute();

            $_SESSION['success'] = $parent_id ? "Reply posted successfully!" : "Comment posted successfully!";
            break;


        case 'edit':
            // Handle editing an existing comment or reply
            if (!isset($_POST['comment_id']) || !isset($_POST['content']) || empty(trim($_POST['content']))) {
                $_SESSION['error'] = "Invalid edit request.";
                break;
            }

            $comment_id = (int)$_POST['comment_id'];
            $content = trim($_POST['content']);

            // Verify the comment belongs to the user
            $stmt = $conn->prepare("SELECT id, parent_id FROM post_comments WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $comment_id, $user_id);
            $stmt->execute();
            $comment_data = $stmt->get_result()->fetch_assoc();

            if (!$comment_data) {
                $_SESSION['error'] = "You can only edit your own comments.";
                break;
            }

            // Update the comment
            $stmt = $conn->prepare("UPDATE post_comments SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param('si', $content, $comment_id);
            $stmt->execute();

            $_SESSION['success'] = $comment_data['parent_id'] ? "Reply updated successfully!" : "Comment updated successfully!";
            break;

        case 'delete':
            // Handle deleting a comment or reply
            if (!isset($_POST['comment_id'])) {
                $_SESSION['error'] = "Invalid delete request.";
                break;
            }

            $comment_id = (int)$_POST['comment_id'];

            // Verify the comment belongs to the user
            $stmt = $conn->prepare("SELECT id, parent_id FROM post_comments WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $comment_id, $user_id);
            $stmt->execute();
            $comment_data = $stmt->get_result()->fetch_assoc();

            if (!$comment_data) {
                $_SESSION['error'] = "You can only delete your own comments.";
                break;
            }

            // Check if this comment has replies
            $stmt = $conn->prepare("SELECT COUNT(*) as reply_count FROM post_comments WHERE parent_id = ?");
            $stmt->bind_param('i', $comment_id);
            $stmt->execute();
            $has_replies = $stmt->get_result()->fetch_assoc()['reply_count'] > 0;

            if ($has_replies) {
                // Soft delete (mark as deleted but keep in database)
                $stmt = $conn->prepare("UPDATE post_comments SET content = '[deleted]', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param('i', $comment_id);
                $stmt->execute();
                $_SESSION['success'] = $comment_data['parent_id'] ? "Reply deleted (replies preserved)." : "Comment deleted (replies preserved).";
            } else {
                // Hard delete (only if no replies)
                $stmt = $conn->prepare("DELETE FROM post_comments WHERE id = ?");
                $stmt->bind_param('i', $comment_id);
                $stmt->execute();
                $_SESSION['success'] = $comment_data['parent_id'] ? "Reply deleted successfully." : "Comment deleted successfully.";
            }
            break;

        default:
            $_SESSION['error'] = "Invalid action requested.";
            break;
    }
} catch (Exception $e) {
    $_SESSION['error'] = "An error occurred while processing your request. Please try again.";
    error_log("Comment handling error: " . $e->getMessage());
}

// Redirect back to the post page
header("Location: post.php?id=$post_id");
exit;
