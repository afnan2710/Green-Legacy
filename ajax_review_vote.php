<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to vote on reviews']);
    exit;
}

// Validate input
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['review_id']) || !isset($_POST['is_helpful'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$review_id = (int)$_POST['review_id'];
$user_id = (int)$_SESSION['user_id'];
$is_helpful = $_POST['is_helpful'] === '1';

// Check if user already voted on this review
$stmt = $conn->prepare("SELECT id, is_helpful FROM review_votes WHERE review_id = ? AND user_id = ?");
$stmt->bind_param('ii', $review_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$existing_vote = $result->fetch_assoc();
$stmt->close();

if ($existing_vote) {
    // User already voted - update their vote if it's different
    if ($existing_vote['is_helpful'] != $is_helpful) {
        $stmt = $conn->prepare("UPDATE review_votes SET is_helpful = ? WHERE id = ?");
        $stmt->bind_param('ii', $is_helpful, $existing_vote['id']);
        $stmt->execute();
        $stmt->close();
    }
} else {
    // New vote
    $stmt = $conn->prepare("INSERT INTO review_votes (review_id, user_id, is_helpful) VALUES (?, ?, ?)");
    $stmt->bind_param('iii', $review_id, $user_id, $is_helpful);
    $stmt->execute();
    $stmt->close();
}

// Get updated vote counts
$stmt = $conn->prepare("
    SELECT 
        SUM(is_helpful = 1) as helpful_count,
        SUM(is_helpful = 0) as not_helpful_count
    FROM review_votes 
    WHERE review_id = ?
");
$stmt->bind_param('i', $review_id);
$stmt->execute();
$votes = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'success' => true,
    'helpful_count' => $votes['helpful_count'] ?? 0,
    'not_helpful_count' => $votes['not_helpful_count'] ?? 0
]);