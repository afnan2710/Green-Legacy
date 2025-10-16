<?php
// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// Database connection
require_once 'db_connect.php';

// Get category ID from URL
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($category_id <= 0) {
    $_SESSION['message'] = "Invalid category ID";
    $_SESSION['message_type'] = "error";
    header("Location: admin_categories.php");
    exit;
}

// Check if category exists
$stmt = $conn->prepare("SELECT id, name FROM categories WHERE id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Category not found";
    $_SESSION['message_type'] = "error";
    header("Location: admin_categories.php");
    exit;
}

$category = $result->fetch_assoc();
$stmt->close();

// Check if category has products
$stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE category_id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();
$product_count = $result->fetch_assoc()['product_count'];
$stmt->close();

// Check if category has children
$stmt = $conn->prepare("SELECT COUNT(*) as child_count FROM categories WHERE parent_id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();
$child_count = $result->fetch_assoc()['child_count'];
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user confirmed deletion
    if (isset($_POST['confirm'])) {
        // Check if we can safely delete (no products or children)
        if ($product_count == 0 && $child_count == 0) {
            // Delete category
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $category_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Category deleted successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting category: " . $stmt->error;
                $_SESSION['message_type'] = "error";
            }
            $stmt->close();
        } else {
            // Handle cases where category has products or children
            if (isset($_POST['handle_products']) && isset($_POST['handle_children'])) {
                $new_category_id = !empty($_POST['new_category_id']) ? intval($_POST['new_category_id']) : null;
                
                if ($new_category_id === null || $new_category_id === $category_id) {
                    $_SESSION['message'] = "You must select a different category to move products/children to";
                    $_SESSION['message_type'] = "error";
                    header("Location: admin_delete_category.php?id=" . $category_id);
                    exit;
                }
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Move products to new category
                    if ($product_count > 0) {
                        $stmt = $conn->prepare("UPDATE products SET category_id = ? WHERE category_id = ?");
                        $stmt->bind_param("ii", $new_category_id, $category_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    // Move child categories to new parent
                    if ($child_count > 0) {
                        $stmt = $conn->prepare("UPDATE categories SET parent_id = ? WHERE parent_id = ?");
                        $stmt->bind_param("ii", $new_category_id, $category_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    // Now delete the category
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->bind_param("i", $category_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $_SESSION['message'] = "Category deleted successfully! Products and subcategories were moved.";
                    $_SESSION['message_type'] = "success";
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $_SESSION['message'] = "Error deleting category: " . $e->getMessage();
                    $_SESSION['message_type'] = "error";
                }
            } else {
                $_SESSION['message'] = "You must specify how to handle products and subcategories";
                $_SESSION['message_type'] = "error";
                header("Location: admin_delete_category.php?id=" . $category_id);
                exit;
            }
        }
        
        header("Location: admin_categories.php");
        exit;
    } else {
        // User cancelled deletion
        header("Location: admin_categories.php");
        exit;
    }
}

// Get all categories for dropdown (excluding current category)
$categories = [];
$stmt = $conn->prepare("SELECT id, name FROM categories WHERE id != ? AND parent_id IS NULL ORDER BY name");
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
  <title>Delete Category - Admin Panel</title>
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
    
    /* Alert messages */
    .alert {
      padding: 0.75rem 1rem;
      border-radius: 0.375rem;
      margin-bottom: 1rem;
    }
    
    .alert-warning {
      background-color: #fef3c7;
      color: #92400e;
    }
    
    .alert-error {
      background-color: #fee2e2;
      color: #b91c1c;
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
  </style>
</head>
<body>
  <div class="content-container">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Delete Category</h1>
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
    
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Confirm Deletion</h2>
      
      <div class="mb-6">
        <p class="mb-4">You are about to delete the category: <strong><?php echo htmlspecialchars($category['name']); ?></strong></p>
        
        <?php if ($product_count > 0 || $child_count > 0): ?>
          <div class="alert alert-warning mb-6">
            <p class="font-semibold">Warning!</p>
            <ul class="list-disc pl-5 mt-2">
              <?php if ($product_count > 0): ?>
                <li>This category contains <?php echo $product_count; ?> product(s).</li>
              <?php endif; ?>
              <?php if ($child_count > 0): ?>
                <li>This category has <?php echo $child_count; ?> subcategorie(s).</li>
              <?php endif; ?>
            </ul>
            <p class="mt-2">You must decide what to do with these items before deleting this category.</p>
          </div>
        <?php endif; ?>
      </div>
      
      <form action="admin_delete_category.php?id=<?php echo $category_id; ?>" method="POST">
        <?php if ($product_count > 0 || $child_count > 0): ?>
          <div class="mb-6 p-4 border border-yellow-200 rounded-lg bg-yellow-50">
            <h3 class="font-semibold mb-3">How to handle associated items:</h3>
            
            <?php if ($product_count > 0): ?>
              <div class="form-group mb-4">
                <label class="inline-flex items-center">
                  <input type="checkbox" name="handle_products" class="form-checkbox" checked>
                  <span class="ml-2">Move <?php echo $product_count; ?> product(s) to another category</span>
                </label>
              </div>
            <?php endif; ?>
            
            <?php if ($child_count > 0): ?>
              <div class="form-group mb-4">
                <label class="inline-flex items-center">
                  <input type="checkbox" name="handle_children" class="form-checkbox" checked>
                  <span class="ml-2">Move <?php echo $child_count; ?> subcategorie(s) to another parent category</span>
                </label>
              </div>
            <?php endif; ?>
            
            <div class="form-group">
              <label for="new_category_id" class="form-label">Select target category:</label>
              <select id="new_category_id" name="new_category_id" class="form-control" required>
                <option value="">-- Select a category --</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        <?php endif; ?>
        
        <div class="flex justify-end space-x-4">
          <a href="admin_categories.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-md">
            Cancel
          </a>
          <button type="submit" name="confirm" value="1" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-md">
            Confirm Delete
          </button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>