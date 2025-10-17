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

// Get user's saved posts
$query = "SELECT fp.*, u.firstname, u.lastname, u.profile_pic,
          (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = fp.id) as comment_count,
          (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id = fp.id) as reaction_count
          FROM forum_posts fp
          JOIN users u ON fp.user_id = u.id
          JOIN saved_posts sp ON fp.id = sp.post_id
          WHERE sp.user_id = ? AND fp.is_published = TRUE
          ORDER BY sp.saved_at DESC
          LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('iii', $_SESSION['user_id'], $offset, $per_page);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM saved_posts WHERE user_id = ?";
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
    <title>Saved Posts | Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .post-card {
            transition: all 0.3s ease;
        }
        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .post-image {
            height: 200px;
            object-fit: cover;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            object-fit: cover;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>
    
    <div class="container mx-auto mt-8 px-4 py-8">
        <div class="max-w-5xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-6">Saved Posts</h1>
                
                <?php if (empty($posts)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-bookmark text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">You haven't saved any posts yet.</p>
                        <p class="text-gray-500 mt-1">When you find interesting posts, click the <i class="fas fa-bookmark text-green-500"></i> icon to save them here.</p>
                        <a href="forum.php" class="text-green-600 hover:underline mt-2 inline-block">
                            Browse posts
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($posts as $post): ?>
                            <div class="post-card bg-white rounded-lg border border-gray-100 overflow-hidden">
                                <!-- Post Header -->
                                <div class="p-4 border-b border-gray-100">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <img src="<?= htmlspecialchars($post['profile_pic'] ?? 'default-user.png') ?>" 
                                                 alt="<?= htmlspecialchars($post['firstname']) ?>" 
                                                 class="user-avatar rounded-full mr-3">
                                            <div>
                                                <h3 class="font-medium"><?= htmlspecialchars($post['firstname'] . ' ' . $post['lastname']) ?></h3>
                                                <p class="text-xs text-gray-500">
                                                    <?= date('M j, Y \a\t g:i a', strtotime($post['created_at'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm text-gray-500 flex items-center">
                                                <i class="fas fa-comment mr-1"></i> <?= $post['comment_count'] ?>
                                            </span>
                                            <span class="text-sm text-gray-500 flex items-center">
                                                <i class="fas fa-heart mr-1"></i> <?= $post['reaction_count'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Post Content -->
                                <div class="p-4">
                                    <a href="post.php?id=<?= $post['id'] ?>" class="block">
                                        <h2 class="text-xl font-semibold text-gray-800 mb-2 hover:text-green-600"><?= htmlspecialchars($post['title']) ?></h2>
                                        <p class="text-gray-600 mb-3 line-clamp-2"><?= htmlspecialchars(substr($post['content'], 0, 200)) ?>...</p>
                                    </a>
                                    
                                    <?php if ($post['image_path']): ?>
                                        <div class="mt-3 rounded-lg overflow-hidden">
                                            <img src="<?= htmlspecialchars($post['image_path']) ?>" 
                                                 alt="<?= htmlspecialchars($post['title']) ?>" 
                                                 class="post-image w-full">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Post Footer -->
                                <div class="px-4 py-3 bg-gray-50 border-t border-gray-100">
                                    <div class="flex justify-between items-center">
                                        <a href="post.php?id=<?= $post['id'] ?>" 
                                           class="text-green-600 hover:text-green-800 text-sm font-medium">
                                            Read more &rarr;
                                        </a>
                                        
                                        <form method="POST" action="unsave_post.php">
                                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                            <button type="submit" class="text-gray-500 hover:text-yellow-500" title="Remove from saved">
                                                <i class="fas fa-bookmark"></i>
                                            </button>
                                        </form>
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