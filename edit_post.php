<?php
require_once 'db_connect.php';
require_once 'navbar.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: login.php');
    exit;
}

$post_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get the post to edit
$post_query = "SELECT * FROM forum_posts WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($post_query);
$stmt->bind_param('ii', $post_id, $user_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    header('Location: my_posts.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    // Handle image upload and removal
    $image_path = $post['image_path'];
    
    // Check if remove image checkbox is checked
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == 'on') {
        // Delete the old image file if it exists
        if ($image_path && file_exists($image_path)) {
            unlink($image_path);
        }
        $image_path = null; // Set image_path to NULL in database
    }
    
    // Handle new image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/posts/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('post_') . '.' . $file_ext;
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            // Delete old image if it exists
            if ($image_path && file_exists($image_path)) {
                unlink($image_path);
            }
            $image_path = $target_path;
        }
    }
    
    // Update post in database
    $update_query = "UPDATE forum_posts SET title = ?, content = ?, image_path = ?, is_published = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('sssii', $title, $content, $image_path, $is_published, $post_id);
    $stmt->execute();
    
    echo "<script>window.location.href = 'post.php?id=$post_id';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post | Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>
    
    <div class="container mx-auto mt-8 px-4 py-8">
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Post</h1>
            
            <form method="POST" action="edit_post.php?id=<?= $post_id ?>" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="title" class="block text-gray-700 font-medium mb-2">Title</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($post['title']) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required>
                </div>
                
                <div class="mb-4">
                    <label for="content" class="block text-gray-700 font-medium mb-2">Content</label>
                    <textarea id="content" name="content" rows="8" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required><?= htmlspecialchars($post['content']) ?></textarea>
                </div>
                
                <div class="mb-4">
                    <label for="image" class="block text-gray-700 font-medium mb-2">Image (optional)</label>
                    <?php if ($post['image_path']): ?>
                        <div class="mb-2">
                            <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="Current post image" class="max-w-xs">
                            <div class="mt-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="remove_image" class="form-checkbox" value="1">
                                    <span class="ml-2 text-gray-700">Remove current image</span>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept="image/*" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                
                <div class="mb-6">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_published" class="form-checkbox" <?= $post['is_published'] ? 'checked' : '' ?>>
                        <span class="ml-2 text-gray-700">Publish this post</span>
                    </label>
                </div>
                
                <div class="flex justify-between">
                    <a href="post.php?id=<?= $post_id ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg">
                        Cancel
                    </a>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        Update Post
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>