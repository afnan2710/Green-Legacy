<?php
require_once 'db_connect.php';
require_once 'navbar.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page > 1) ? ($page - 1) * $per_page : 0;

// Get user's comments with post information
$query = "SELECT pc.*, fp.title as post_title, fp.id as post_id,
          (SELECT COUNT(*) FROM comment_reactions cr WHERE cr.comment_id = pc.id) as reaction_count
          FROM post_comments pc
          JOIN forum_posts fp ON pc.post_id = fp.id
          WHERE pc.user_id = ?
          ORDER BY pc.created_at DESC
          LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('iii', $_SESSION['user_id'], $offset, $per_page);
$stmt->execute();
$comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM post_comments WHERE user_id = ?";
$stmt = $conn->prepare($count_query);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$total_result = $stmt->get_result();
$total = $total_result->fetch_assoc()['total'];
$pages = ceil($total / $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Comments | Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .comment-card {
            transition: all 0.3s ease;
        }
        .comment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>
    
    <div class="container mx-auto mt-8 px-4 py-8">
        <div class="max-w-5xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-6">My Comments</h1>
                
                <?php if (empty($comments)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-comment-slash text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">You haven't posted any comments yet.</p>
                        <a href="forum.php" class="text-green-600 hover:underline mt-2 inline-block">
                            Join a discussion!
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-card bg-white rounded-lg border border-gray-100 overflow-hidden">
                                <div class="p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <a href="post.php?id=<?= $comment['post_id'] ?>#comment-<?= $comment['id'] ?>" class="font-medium text-green-600 hover:underline">
                                            On: <?= htmlspecialchars($comment['post_title']) ?>
                                        </a>
                                        <span class="text-xs text-gray-500">
                                            <?= date('M j, Y \a\t g:i a', strtotime($comment['created_at'])) ?>
                                        </span>
                                    </div>
                                    <div class="prose prose-sm max-w-none text-gray-600 mb-3">
                                        <?= nl2br(htmlspecialchars($comment['content'])) ?>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center space-x-4">
                                            <span class="text-sm text-gray-500 flex items-center">
                                                <i class="fas fa-heart mr-1"></i> <?= $comment['reaction_count'] ?>
                                            </span>
                                        </div>
                                        <div class="flex space-x-2">
                                            <a href="post.php?id=<?= $comment['post_id'] ?>#comment-<?= $comment['id'] ?>" class="text-green-600 hover:text-green-800 text-sm" title="View">
                                                <i class="fas fa-eye mr-1"></i> View
                                            </a>
                                            <form method="POST" action="delete_comment.php" class="inline" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                                <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm" title="Delete">
                                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($pages > 1): ?>
                        <div class="mt-6 flex justify-center">
                            <nav class="inline-flex rounded-md shadow">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>" 
                                       class="px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $pages; $i++): ?>
                                    <a href="?page=<?= $i ?>" 
                                       class="<?= $i == $page ? 'bg-green-50 border-green-500 text-green-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> px-3 py-2 border-t border-b text-sm font-medium">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $pages): ?>
                                    <a href="?page=<?= $page + 1 ?>" 
                                       class="px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>