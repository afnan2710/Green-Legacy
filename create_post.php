<?php
require_once 'db_connect.php';
require_once 'navbar.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "<script>window.location.href='login.php';</script>";
    exit();
}

$errors = [];
$title = '';
$content = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $image_path = '';
    
    // Validate inputs
    if (empty($title)) {
        $errors['title'] = 'Title is required';
    } elseif (strlen($title) > 255) {
        $errors['title'] = 'Title must be less than 255 characters';
    }
    
    if (empty($content)) {
        $errors['content'] = 'Content is required';
    }
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['image']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = 'uploads/posts/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('post_') . '.' . $file_ext;
            $destination = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image_path = $destination;
            } else {
                $errors['image'] = 'Failed to upload image';
            }
        } else {
            $errors['image'] = 'Only JPG, PNG, and GIF images are allowed';
        }
    }
    
    // Create post if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO forum_posts (user_id, title, content, image_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $_SESSION['user_id'], $title, $content, $image_path);
        
        if ($stmt->execute()) {
            $post_id = $stmt->insert_id;
            echo "<script>window.location.href = 'post.php?id=$post_id';</script>";
            exit;
        } else {
            $errors['general'] = 'Failed to create post. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Post | Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .editor-toolbar {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem 0.375rem 0 0;
            padding: 0.5rem;
        }
        .editor-content {
            min-height: 200px;
            border: 1px solid #e2e8f0;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
            padding: 1rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>
    
    <div class="container mx-auto mt-8 px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-6">Create New Post</h1>
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle h-5 w-5 text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?= htmlspecialchars($errors['general']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-6">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" 
                               class="w-full px-3 py-2 border <?= isset($errors['title']) ? 'border-red-500' : 'border-gray-300' ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <?php if (isset($errors['title'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($errors['title']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-6">
                        <label for="content" class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                        <div class="editor-toolbar">
                            <button type="button" class="editor-btn" data-command="bold" title="Bold">
                                <i class="fas fa-bold"></i>
                            </button>
                            <button type="button" class="editor-btn" data-command="italic" title="Italic">
                                <i class="fas fa-italic"></i>
                            </button>
                            <button type="button" class="editor-btn" data-command="insertUnorderedList" title="Bullet List">
                                <i class="fas fa-list-ul"></i>
                            </button>
                            <button type="button" class="editor-btn" data-command="insertOrderedList" title="Numbered List">
                                <i class="fas fa-list-ol"></i>
                            </button>
                            <button type="button" class="editor-btn" data-command="createLink" title="Insert Link">
                                <i class="fas fa-link"></i>
                            </button>
                        </div>
                        <div id="content" class="editor-content" contenteditable="true"><?= htmlspecialchars($content) ?></div>
                        <textarea name="content" id="hidden-content" class="hidden"><?= htmlspecialchars($content) ?></textarea>
                        <?php if (isset($errors['content'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($errors['content']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-6">
                        <label for="image" class="block text-sm font-medium text-gray-700 mb-1">Upload Image (Optional)</label>
                        <div class="mt-1 flex items-center">
                            <label for="image" class="cursor-pointer bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-upload mr-2"></i> Choose File
                            </label>
                            <input id="image" name="image" type="file" class="sr-only" accept="image/*">
                            <span id="file-name" class="ml-3 text-sm text-gray-500">No file chosen</span>
                        </div>
                        <?php if (isset($errors['image'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($errors['image']) ?></p>
                        <?php endif; ?>
                        <p class="mt-1 text-xs text-gray-500">JPG, PNG or GIF (Max 5MB)</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <a href="forum.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg">
                            Cancel
                        </a>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                            Publish Post
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        // Update file name display when file is selected
        document.getElementById('image').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
            document.getElementById('file-name').textContent = fileName;
        });
        
        // Simple rich text editor functionality
        document.querySelectorAll('.editor-btn').forEach(button => {
            button.addEventListener('click', function() {
                const command = this.getAttribute('data-command');
                if (command === 'createLink') {
                    const url = prompt('Enter the URL:');
                    if (url) document.execCommand(command, false, url);
                } else {
                    document.execCommand(command, false, null);
                }
                document.getElementById('content').focus();
            });
        });
        
        // Sync content with hidden textarea
        document.getElementById('content').addEventListener('input', function() {
            document.getElementById('hidden-content').value = this.innerHTML;
        });
    </script>
</body>
</html>