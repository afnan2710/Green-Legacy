<?php
require_once 'db_connect.php';
require_once 'navbar.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 6;
$offset = ($page > 1) ? ($page - 1) * $per_page : 0;

// Get total count of published blogs
$total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM blogs WHERE status = 'published'");
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_blogs = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_blogs / $per_page);

// Get published blogs with pagination
$stmt = $conn->prepare("
    SELECT b.*, u.firstname, u.lastname, u.profile_pic 
    FROM blogs b
    JOIN users u ON b.author_id = u.id
    WHERE b.status = 'published'
    ORDER BY b.published_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$blogs = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blogs - Green Legacy</title>
    <style>
        .blog-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .blog-image {
            height: 200px;
            object-fit: cover;
        }
        .tag {
            display: inline-block;
            background-color: #f0fdf4;
            color: #166534;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <main class="container mx-auto mt-10 px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Latest Blog Posts</h1>
        
        <!-- Blog Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($blogs as $blog): ?>
                <a href="blog_details.php?id=<?= $blog['id'] ?>" class="blog-card block bg-white rounded-lg overflow-hidden shadow-md">
                    <?php if ($blog['featured_image']): ?>
                        <img src="<?= htmlspecialchars($blog['featured_image']) ?>" alt="<?= htmlspecialchars($blog['title']) ?>" class="blog-image w-full">
                    <?php else: ?>
                        <div class="blog-image w-full bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-500">No Image</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <img src="<?= htmlspecialchars($blog['profile_pic'] ?? 'default-user.png') ?>" 
                                 alt="<?= htmlspecialchars($blog['firstname'] . ' ' . $blog['lastname']) ?>" 
                                 class="w-10 h-10 rounded-full mr-3">
                            <div>
                                <p class="font-medium"><?= htmlspecialchars($blog['firstname'] . ' ' . $blog['lastname']) ?></p>
                                <p class="text-sm text-gray-500">
                                    <?= date('M j, Y', strtotime($blog['published_at'])) ?>
                                </p>
                            </div>
                        </div>
                        
                        <h2 class="text-xl font-bold mb-2"><?= htmlspecialchars($blog['title']) ?></h2>
                        <p class="text-gray-600 mb-4 line-clamp-2">
                            <?= htmlspecialchars(substr(strip_tags($blog['content']), 0, 150)) ?>...
                        </p>
                        
                        <?php if ($blog['tags']): ?>
                            <div class="flex flex-wrap gap-2 mb-2">
                                <?php foreach (explode(',', $blog['tags']) as $tag): ?>
                                    <span class="tag"><?= htmlspecialchars(trim($tag)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <button class="mt-4 text-green-600 font-medium hover:text-green-800 transition">
                            Read More →
                        </button>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-12">
                <nav class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 border rounded-l-lg hover:bg-gray-100">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="px-4 py-2 border <?= $i == $page ? 'bg-green-100 text-green-800' : 'hover:bg-gray-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 border rounded-r-lg hover:bg-gray-100">
                            Next
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </main>
    
    <?php include 'footer.php'; ?>
</body>
</html>