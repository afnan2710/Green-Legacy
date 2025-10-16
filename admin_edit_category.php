<?php
// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// Database connection
require_once 'db_connect.php';

// Initialize variables
$errors = [];
$success = false;
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch category data
$categoryData = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'parent_id' => '',
    'product_type' => 'all',
    'is_active' => 1
];

$stmt = $conn->prepare("SELECT id, name, slug, description, parent_id, product_type, is_active FROM categories WHERE id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Category not found";
    $_SESSION['message_type'] = "error";
    header("Location: admin_categories.php");
    exit;
}

$categoryData = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $categoryData['name'] = trim($_POST['name'] ?? '');
    $categoryData['slug'] = trim($_POST['slug'] ?? '');
    $categoryData['description'] = trim($_POST['description'] ?? '');
    $categoryData['parent_id'] = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $categoryData['product_type'] = in_array($_POST['product_type'], ['plant', 'tool', 'pesticide', 'fertilizer', 'accessory', 'all']) 
        ? $_POST['product_type'] 
        : 'all';
    $categoryData['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    if (empty($categoryData['name'])) {
        $errors['name'] = 'Category name is required';
    }
    
    if (empty($categoryData['slug'])) {
        $errors['slug'] = 'Category slug is required';
    } elseif (!preg_match('/^[a-z0-9-]+$/', $categoryData['slug'])) {
        $errors['slug'] = 'Slug can only contain lowercase letters, numbers, and hyphens';
    }
    
    // If no errors, proceed with database update
    if (empty($errors)) {
        // Check if slug already exists (excluding current category)
        $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
        $stmt->bind_param("si", $categoryData['slug'], $category_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['slug'] = 'This slug is already in use by another category';
        } else {
            // Update category
            $stmt = $conn->prepare("UPDATE categories SET 
                name = ?, 
                slug = ?, 
                description = ?, 
                parent_id = ?, 
                product_type = ?, 
                is_active = ?,
                updated_at = NOW()
                WHERE id = ?");
            $stmt->bind_param("sssisii", 
                $categoryData['name'],
                $categoryData['slug'],
                $categoryData['description'],
                $categoryData['parent_id'],
                $categoryData['product_type'],
                $categoryData['is_active'],
                $category_id
            );
            
            if ($stmt->execute()) {
                $success = true;
                $_SESSION['message'] = "Category updated successfully!";
                $_SESSION['message_type'] = "success";
                header("Location: admin_categories.php");
                exit;
            } else {
                $errors[] = "Error updating category: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Get all categories for dropdown (excluding current category and its children)
$categories = [];
$stmt = $conn->prepare("SELECT id, name, slug, parent_id, product_type, is_active FROM categories WHERE id != ? ORDER BY product_type, name");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}
$stmt->close();


?>

<?php include 'admin_sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Category - Admin Panel</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      margin-left: 280px; /* Match sidebar width */
    }
    
    @media (max-width: 1024px) {
      body {
        margin-left: 0;
      }
    }
    
    .content-container {
      padding: 2rem;
    }
    
    /* Form styles */
    .form-group {
      margin-bottom: 1.5rem;
    }
    
    .form-label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
      color: #374151;
    }
    
    .form-control {
      width: 100%;
      padding: 0.5rem 0.75rem;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      transition: border-color 0.2s;
    }
    
    .form-control:focus {
      outline: none;
      border-color: #10b981;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
    
    .form-textarea {
      min-height: 100px;
    }
    
    .form-checkbox {
      width: 1rem;
      height: 1rem;
      border: 1px solid #d1d5db;
      border-radius: 0.25rem;
    }
    
    /* Error styles */
    .is-invalid {
      border-color: #ef4444;
    }
    
    .invalid-feedback {
      color: #ef4444;
      font-size: 0.875rem;
      margin-top: 0.25rem;
    }
    
    /* Alert messages */
    .alert {
      padding: 0.75rem 1rem;
      border-radius: 0.375rem;
      margin-bottom: 1rem;
    }
    
    .alert-success {
      background-color: #d1fae5;
      color: #065f46;
    }
    
    .alert-error {
      background-color: #fee2e2;
      color: #b91c1c;
    }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Edit Category</h1>
      <a href="admin_categories.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md flex items-center">
        <i class="fas fa-arrow-left mr-2"></i> Back to Categories
      </a>
    </div>
    
    <!-- Display messages -->
    <?php if (isset($_SESSION['message'])): ?>
      <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
        <?php 
          echo $_SESSION['message']; 
          unset($_SESSION['message']);
          unset($_SESSION['message_type']);
        ?>
      </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    
    <!-- Edit Category Form -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <form action="admin_edit_category.php?id=<?php echo $category_id; ?>" method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Category Name -->
          <div class="form-group">
            <label for="name" class="form-label">Category Name <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                   value="<?php echo htmlspecialchars($categoryData['name']); ?>" required>
            <?php if (isset($errors['name'])): ?>
              <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
            <?php endif; ?>
          </div>
          
          <!-- Category Slug -->
          <div class="form-group">
            <label for="slug" class="form-label">Category Slug <span class="text-red-500">*</span></label>
            <input type="text" id="slug" name="slug" class="form-control <?php echo isset($errors['slug']) ? 'is-invalid' : ''; ?>" 
                   value="<?php echo htmlspecialchars($categoryData['slug']); ?>" required>
            <?php if (isset($errors['slug'])): ?>
              <div class="invalid-feedback"><?php echo htmlspecialchars($errors['slug']); ?></div>
            <?php endif; ?>
            <p class="text-xs text-gray-500 mt-1">Lowercase letters, numbers, and hyphens only (e.g., indoor-plants)</p>
          </div>
          
          <!-- Parent Category -->
          <div class="form-group">
            <label for="parent_id" class="form-label">Parent Category</label>
            <select id="parent_id" name="parent_id" class="form-control">
              <option value="">No parent (top-level category)</option>
              <?php foreach ($categories as $category): ?>
                <?php if ($category['parent_id'] === null): ?>
                  <option value="<?php echo $category['id']; ?>" <?php echo $categoryData['parent_id'] == $category['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['name']); ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          
          <!-- Product Type -->
          <div class="form-group">
            <label for="product_type" class="form-label">Product Type</label>
            <select id="product_type" name="product_type" class="form-control">
              <option value="all" <?php echo $categoryData['product_type'] == 'all' ? 'selected' : ''; ?>>All Product Types</option>
              <option value="plant" <?php echo $categoryData['product_type'] == 'plant' ? 'selected' : ''; ?>>Plant</option>
              <option value="tool" <?php echo $categoryData['product_type'] == 'tool' ? 'selected' : ''; ?>>Gardening Tool</option>
              <option value="pesticide" <?php echo $categoryData['product_type'] == 'pesticide' ? 'selected' : ''; ?>>Pesticide</option>
              <option value="fertilizer" <?php echo $categoryData['product_type'] == 'fertilizer' ? 'selected' : ''; ?>>Fertilizer</option>
              <option value="accessory" <?php echo $categoryData['product_type'] == 'accessory' ? 'selected' : ''; ?>>Accessory</option>
            </select>
          </div>
        </div>
        
        <!-- Description -->
        <div class="form-group mt-6">
          <label for="description" class="form-label">Description</label>
          <textarea id="description" name="description" class="form-control form-textarea"><?php echo htmlspecialchars($categoryData['description']); ?></textarea>
        </div>
        
        <!-- Status -->
        <div class="form-group mt-6">
          <label class="form-label">Status</label>
          <div class="flex items-center">
            <label class="inline-flex items-center">
              <input type="checkbox" name="is_active" class="form-checkbox" <?php echo $categoryData['is_active'] ? 'checked' : ''; ?>>
              <span class="ml-2">Active</span>
            </label>
          </div>
        </div>
        
        <!-- Form Actions -->
        <div class="flex justify-end mt-6 space-x-4">
          <a href="admin_categories.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-md">
            Cancel
          </a>
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md">
            Update Category
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
    // Auto-generate slug from name
    document.getElementById('name').addEventListener('input', function() {
      const nameInput = this;
      const slugInput = document.getElementById('slug');
      
      if (slugInput.value === '' || slugInput.dataset.manual !== 'true') {
        const slug = nameInput.value
          .toLowerCase()
          .replace(/[^\w\s-]/g, '') // Remove non-word chars
          .replace(/\s+/g, '-')      // Replace spaces with -
          .replace(/--+/g, '-');     // Replace multiple - with single -
        
        slugInput.value = slug;
      }
    });
    
    // Allow manual slug editing
    document.getElementById('slug').addEventListener('input', function() {
      this.dataset.manual = 'true';
    });
    
    // Filter parent categories based on product type
    document.getElementById('product_type').addEventListener('change', function() {
      const selectedType = this.value;
      const parentSelect = document.getElementById('parent_id');
      
      // If "All" is selected, show all parent categories
      if (selectedType === 'all') {
        for (let i = 0; i < parentSelect.options.length; i++) {
          parentSelect.options[i].style.display = 'block';
        }
        return;
      }
      
      // Otherwise, show only matching parent categories
      for (let i = 0; i < parentSelect.options.length; i++) {
        const option = parentSelect.options[i];
        if (option.value === "") {
          option.style.display = 'block'; // Always show the default option
          continue;
        }
        
        // This would need server-side data about each category's type
        // For now, we'll just show all parent categories
        option.style.display = 'block';
      }
    });
  </script>
</body>
</html>