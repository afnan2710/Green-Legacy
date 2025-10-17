<?php
require_once 'db_connect.php';
require_once 'navbar.php';

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page > 1) ? ($page - 1) * $per_page : 0;

// Get search query if set
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the base query
$query = "SELECT fp.*, u.firstname, u.lastname, u.profile_pic,
          (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = fp.id) as comment_count,
          (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id = fp.id) as reaction_count
          FROM forum_posts fp
          JOIN users u ON fp.user_id = u.id
          WHERE fp.is_published = TRUE";

$params = [];
$types = '';

// Add search filter if specified
if (!empty($search)) {
    $query .= " AND (fp.title LIKE ? OR fp.content LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM ($query) as total_query";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total = $total_result->fetch_assoc()['total'];
$pages = ceil($total / $per_page);

// Add sorting and pagination
$query .= " ORDER BY fp.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= 'ii';

// Get forum posts
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's reactions to these posts if logged in
$user_reactions = [];
if (isset($_SESSION['user_id'])) {
    $post_ids = array_column($posts, 'id');
    if (!empty($post_ids)) {
        $placeholders = implode(',', array_fill(0, count($post_ids), '?'));
        $reaction_query = "SELECT post_id, reaction_type FROM post_reactions 
                          WHERE user_id = ? AND post_id IN ($placeholders)";
        $stmt = $conn->prepare($reaction_query);
        $types = str_repeat('i', count($post_ids));
        $stmt->bind_param('i' . $types, $_SESSION['user_id'], ...$post_ids);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $user_reactions[$row['post_id']] = $row['reaction_type'];
        }
    }
}

// Get popular posts for sidebar
$popular_posts = [];
$popular_query = "SELECT fp.id, fp.title, 
                 (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id = fp.id) as reaction_count
                 FROM forum_posts fp
                 WHERE fp.is_published = TRUE
                 ORDER BY reaction_count DESC, fp.created_at DESC
                 LIMIT 5";
$popular_result = $conn->query($popular_query);
if ($popular_result) {
    $popular_posts = $popular_result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plant Lovers Community | Green Legacy</title>
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

        .reaction-btn:hover .reaction-icon {
            transform: scale(1.2);
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>

    <div class="container mx-auto mt-8 px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Main Content -->
            <main class="lg:w-2/3">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">Plant Lovers Community</h1>
                        <a href="create_post.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center">
                            <i class="fas fa-plus mr-2"></i> New Post
                        </a>
                    </div>

                    <!-- Search Form -->
                    <form action="forum.php" method="GET" class="mb-6">
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="Search posts...">
                            <div class="absolute left-3 top-2.5 text-gray-400">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </form>

                    <!-- Posts List -->
                    <?php if (empty($posts)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-seedling text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No posts found.</p>
                            <?php if (!empty($search)): ?>
                                <p class="mt-2">Try a different search term</p>
                            <?php else: ?>
                                <a href="create_post.php" class="text-green-600 hover:underline mt-2 inline-block">
                                    Be the first to create a post!
                                </a>
                            <?php endif; ?>
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

                                            <?php if (isset($_SESSION['user_id'])): ?>
                                                <div class="flex space-x-2">
                                                    <form method="post" action="handle_reaction.php" class="reaction-btn">
                                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                                        <input type="hidden" name="reaction_type" value="like">
                                                        <button type="submit" class="<?= isset($user_reactions[$post['id']]) ? 'text-red-500' : 'text-gray-500' ?> hover:text-red-500">
                                                            <i class="fas fa-heart reaction-icon"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
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
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                                            class="px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                            class="<?= $i == $page ? 'bg-green-50 border-green-500 text-green-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> px-3 py-2 border-t border-b text-sm font-medium">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($page < $pages): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                                            class="px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>

            <!-- Sidebar -->
            <aside class="lg:w-1/3">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Popular Discussions</h2>
                    <div class="space-y-3">
                        <?php if (empty($popular_posts)): ?>
                            <p class="text-gray-500">No popular posts yet</p>
                        <?php else: ?>
                            <?php foreach ($popular_posts as $popular): ?>
                                <a href="post.php?id=<?= $popular['id'] ?>" class="block hover:bg-gray-50 p-2 rounded">
                                    <h3 class="font-medium text-gray-800"><?= htmlspecialchars($popular['title']) ?></h3>
                                    <div class="flex items-center text-sm text-gray-500 mt-1">
                                        <i class="fas fa-heart mr-1 text-red-400"></i>
                                        <span><?= $popular['reaction_count'] ?> likes</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Your Activity</h2>
                        <div class="space-y-4">
                            <a href="my_posts.php" class="flex items-center text-green-600 hover:text-green-800">
                                <i class="fas fa-newspaper mr-2"></i> Your Posts
                            </a>
                            <a href="my_comments.php" class="flex items-center text-green-600 hover:text-green-800">
                                <i class="fas fa-comments mr-2"></i> Your Comments
                            </a>
                            <a href="saved_posts.php" class="flex items-center text-green-600 hover:text-green-800">
                                <i class="fas fa-bookmark mr-2"></i> Saved Posts
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Forum Guidelines</h2>
                    <div class="prose prose-sm text-gray-600">
                        <ul class="list-disc pl-5 space-y-2">
                            <li>Be kind and respectful to all members</li>
                            <li>Share your plant knowledge and experiences</li>
                            <li>No spam or self-promotion</li>
                            <li>Keep discussions plant-related</li>
                            <li>Report any inappropriate content</li>
                        </ul>
                        <p class="mt-4 text-sm">Happy planting! 🌱</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>

</html>