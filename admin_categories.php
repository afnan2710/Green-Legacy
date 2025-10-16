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
$categoryData = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'parent_id' => '',
    'product_type' => 'all',
    'is_active' => 1
];

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
    
    // If no errors, proceed with database insertion
    if (empty($errors)) {
        // Check if slug already exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->bind_param("s", $categoryData['slug']);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['slug'] = 'This slug is already in use';
        } else {
            // Insert category
            $stmt = $conn->prepare("INSERT INTO categories 
                (name, slug, description, parent_id, product_type, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssisi", 
                $categoryData['name'],
                $categoryData['slug'],
                $categoryData['description'],
                $categoryData['parent_id'],
                $categoryData['product_type'],
                $categoryData['is_active']
            );
            
            if ($stmt->execute()) {
                $success = true;
                $_SESSION['message'] = "Category added successfully!";
                $_SESSION['message_type'] = "success";
                header("Location: admin_categories.php");
                exit;
            } else {
                $errors[] = "Error adding category: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Get all categories for dropdown
$categories = [];
$stmt = $conn->prepare("SELECT id, name, slug, parent_id, product_type, is_active FROM categories ORDER BY product_type, name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}
$stmt->close();

// Group categories by product type
$groupedCategories = [];
foreach ($categories as $category) {
    $groupedCategories[$category['product_type']][] = $category;
}

?>

<?php include 'admin_sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Category Management - Admin Panel</title>
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
    
    /* Table styles */
    .table-container {
      overflow-x: auto;
    }
    
    .category-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .category-table th, .category-table td {
      padding: 0.75rem 1rem;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .category-table th {
      background-color: #f9fafb;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
      color: #6b7280;
    }
    
    .category-table tr:hover {
      background-color: #f9fafb;
    }
    
    /* Status badges */
    .badge-active {
      background-color: #d1fae5;
      color: #065f46;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .badge-inactive {
      background-color: #fee2e2;
      color: #b91c1c;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    /* Type badges */
    .badge-type {
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .badge-plant {
      background-color: #dcfce7;
      color: #166534;
    }
    
    .badge-tool {
      background-color: #e0f2fe;
      color: #1e40af;
    }
    
    .badge-pesticide {
      background-color: #fef3c7;
      color: #92400e;
    }
    
    .badge-fertilizer {
      background-color: #ede9fe;
      color: #5b21b6;
    }
    
    .badge-accessory {
      background-color: #fce7f3;
      color: #9d174d;
    }
    
    .badge-all {
      background-color: #e5e7eb;
      color: #4b5563;
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
    
    /* Nested categories */
    .nested-category {
      padding-left: 1.5rem;
      position: relative;
    }
    
    .nested-category:before {
      content: "";
      position: absolute;
      left: 0.75rem;
      top: 0;
      bottom: 0;
      width: 1px;
      background-color: #e5e7eb;
    }
    
    .nested-category td:first-child {
      position: relative;
    }
    
    .nested-category td:first-child:before {
      content: "↳";
      position: absolute;
      left: -1.25rem;
    }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Category Management</h1>
      <a href="admin_products.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md flex items-center">
        <i class="fas fa-arrow-left mr-2"></i> Back to Products
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
    
    <!-- Add Category Form -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Add New Category</h2>
      
      <form action="admin_categories.php" method="POST">
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
        <div class="flex justify-end mt-6">
          <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md">
            Add Category
          </button>
        </div>
      </form>
    </div>
    
    <!-- Categories List -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div class="p-6 border-b">
        <h2 class="text-xl font-semibold text-gray-800">Existing Categories</h2>
      </div>
      
      <div class="table-container">
        <table class="category-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Slug</th>
              <th>Type</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($categories)): ?>
              <tr>
                <td colspan="5" class="text-center py-4 text-gray-500">No categories found</td>
              </tr>
            <?php else: ?>
              <?php 
              // Function to display categories recursively
              function displayCategories($categories, $parentId = null, $level = 0) {
                  $output = '';
                  foreach ($categories as $category) {
                      if ($category['parent_id'] == $parentId) {
                          $typeClass = 'badge-' . $category['product_type'];
                          $statusClass = $category['is_active'] ? 'badge-active' : 'badge-inactive';
                          $statusText = $category['is_active'] ? 'Active' : 'Inactive';
                          
                          $output .= '<tr class="' . ($level > 0 ? 'nested-category' : '') . '">';
                          $output .= '<td>' . htmlspecialchars($category['name']) . '</td>';
                          $output .= '<td>' . htmlspecialchars($category['slug']) . '</td>';
                          $output .= '<td><span class="badge-type ' . $typeClass . '">' . ucfirst($category['product_type']) . '</span></td>';
                          $output .= '<td><span class="' . $statusClass . '">' . $statusText . '</span></td>';
                          $output .= '<td>';
                          $output .= '<div class="flex space-x-2">';
                          $output .= '<a href="admin_edit_category.php?id=' . $category['id'] . '" class="text-blue-600 hover:text-blue-800">';
                          $output .= '<i class="fas fa-edit"></i></a>';
                          $output .= '<a href="admin_delete_category.php?id=' . $category['id'] . '" class="text-red-600 hover:text-red-800" onclick="return confirm(\'Are you sure you want to delete this category?\')">';
                          $output .= '<i class="fas fa-trash-alt"></i></a>';
                          $output .= '</div>';
                          $output .= '</td>';
                          $output .= '</tr>';
                          
                          // Display child categories
                          $output .= displayCategories($categories, $category['id'], $level + 1);
                      }
                  }
                  return $output;
              }
              
              echo displayCategories($categories);
              ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
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