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
$productId = $_GET['id'] ?? 0;
$productData = [];
$attributes = [];
$images = [];

// Get product details
if ($productId) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $productData = $result->fetch_assoc();
    $stmt->close();

    if (!$productData) {
        $_SESSION['message'] = "Product not found";
        $_SESSION['message_type'] = "error";
        header("Location: admin_products.php");
        exit;
    }

    // Get product attributes
    $stmt = $conn->prepare("SELECT * FROM product_attributes WHERE product_id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attributes[$row['attribute_key']] = $row['attribute_value'];
    }
    $stmt->close();

    // Get product inventory
    $stmt = $conn->prepare("SELECT * FROM product_inventory WHERE product_id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $inventory = $result->fetch_assoc();
    $stmt->close();

    // Merge inventory data
    if ($inventory) {
        $productData['quantity'] = $inventory['quantity'];
        $productData['low_stock_threshold'] = $inventory['low_stock_threshold'];
    }

    // Get product images
    $stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get all categories for dropdown
$categories = [];
$stmt = $conn->prepare("SELECT id, name, parent_id, product_type FROM categories ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $categories[$row['id']] = $row;
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $productData['name'] = trim($_POST['name'] ?? '');
    $productData['category_id'] = intval($_POST['category_id'] ?? 0);
    $productData['description'] = trim($_POST['description'] ?? '');
    $productData['price'] = floatval($_POST['price'] ?? 0);
    $productData['discount_price'] = !empty($_POST['discount_price']) ? floatval($_POST['discount_price']) : null;
    $productData['sku'] = trim($_POST['sku'] ?? '');
    $productData['product_type'] = in_array($_POST['product_type'], ['plant', 'tool', 'pesticide', 'fertilizer', 'accessory']) 
        ? $_POST['product_type'] 
        : 'plant';
    $productData['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
    $productData['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $productData['quantity'] = intval($_POST['quantity'] ?? 0);
    $productData['low_stock_threshold'] = intval($_POST['low_stock_threshold'] ?? 5);
    
    // Validate inputs
    if (empty($productData['name'])) {
        $errors['name'] = 'Product name is required';
    }
    
    if ($productData['category_id'] <= 0) {
        $errors['category_id'] = 'Please select a category';
    }
    
    if ($productData['price'] <= 0) {
        $errors['price'] = 'Price must be greater than 0';
    }
    
    if ($productData['discount_price'] !== null && $productData['discount_price'] >= $productData['price']) {
        $errors['discount_price'] = 'Discount price must be less than regular price';
    }
    
    if (empty($productData['sku'])) {
        $errors['sku'] = 'SKU is required';
    }
    
    // If no errors, proceed with database update
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Update product
            $stmt = $conn->prepare("UPDATE products SET 
                category_id = ?, 
                name = ?, 
                description = ?, 
                price = ?, 
                discount_price = ?, 
                sku = ?, 
                product_type = ?, 
                is_featured = ?, 
                is_active = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?");
            $stmt->bind_param("isssdssiii", 
                $productData['category_id'],
                $productData['name'],
                $productData['description'],
                $productData['price'],
                $productData['discount_price'],
                $productData['sku'],
                $productData['product_type'],
                $productData['is_featured'],
                $productData['is_active'],
                $productId
            );
            $stmt->execute();
            $stmt->close();
            
            // Update inventory
            $isInStock = $productData['quantity'] > 0;
            $stmt = $conn->prepare("UPDATE product_inventory SET 
                quantity = ?, 
                low_stock_threshold = ?, 
                is_in_stock = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE product_id = ?");
            $stmt->bind_param("iiii", 
                $productData['quantity'],
                $productData['low_stock_threshold'],
                $isInStock,
                $productId
            );
            $stmt->execute();
            $stmt->close();
            
            // Handle product attributes based on type
            $newAttributes = [];
            switch ($productData['product_type']) {
                case 'plant':
                    $newAttributes = [
                        'plant_type' => $_POST['plant_type'] ?? '',
                        'sunlight_requirements' => $_POST['sunlight_requirements'] ?? '',
                        'watering_needs' => $_POST['watering_needs'] ?? '',
                        'mature_height' => $_POST['mature_height'] ?? '',
                        'bloom_time' => $_POST['bloom_time'] ?? ''
                    ];
                    break;
                    
                case 'tool':
                    $newAttributes = [
                        'material' => $_POST['material'] ?? '',
                        'dimensions' => $_POST['dimensions'] ?? '',
                        'weight' => $_POST['weight'] ?? '',
                        'warranty' => $_POST['warranty'] ?? ''
                    ];
                    break;
                    
                case 'pesticide':
                    $newAttributes = [
                        'active_ingredient' => $_POST['active_ingredient'] ?? '',
                        'target_pests' => $_POST['target_pests'] ?? '',
                        'application_method' => $_POST['application_method'] ?? '',
                        'frequency' => $_POST['frequency'] ?? ''
                    ];
                    break;
                    
                case 'fertilizer':
                    $newAttributes = [
                        'npk_ratio' => $_POST['npk_ratio'] ?? '',
                        'application_method' => $_POST['application_method'] ?? '',
                        'frequency' => $_POST['frequency'] ?? ''
                    ];
                    break;
            }
            
            // Delete old attributes
            $stmt = $conn->prepare("DELETE FROM product_attributes WHERE product_id = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $stmt->close();
            
            // Insert new attributes
            foreach ($newAttributes as $key => $value) {
                if (!empty($value)) {
                    $stmt = $conn->prepare("INSERT INTO product_attributes 
                        (product_id, attribute_key, attribute_value) 
                        VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $productId, $key, $value);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            // Handle image upload
            if (!empty($_FILES['images']['name'][0])) {
                $uploadDir = 'uploads/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
                    if ($_FILES['images']['error'][$index] === UPLOAD_ERR_OK) {
                        $fileName = uniqid() . '_' . basename($_FILES['images']['name'][$index]);
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $isPrimary = ($index === 0 && empty($images)) ? 1 : 0;
                            $stmt = $conn->prepare("INSERT INTO product_images 
                                (product_id, image_path, is_primary) 
                                VALUES (?, ?, ?)");
                            $stmt->bind_param("isi", $productId, $fileName, $isPrimary);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
            
            // Handle image deletions
            if (!empty($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $imageId) {
                    // Get image path first
                    $stmt = $conn->prepare("SELECT image_path FROM product_images WHERE id = ?");
                    $stmt->bind_param("i", $imageId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $image = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($image) {
                        // Delete from filesystem
                        $imagePath = 'uploads/products/' . $image['image_path'];
                        if (file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                        
                        // Delete from database
                        $stmt = $conn->prepare("DELETE FROM product_images WHERE id = ?");
                        $stmt->bind_param("i", $imageId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            
            // Handle primary image change
            if (!empty($_POST['primary_image'])) {
                // First reset all to not primary
                $stmt = $conn->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
                $stmt->bind_param("i", $productId);
                $stmt->execute();
                $stmt->close();
                
                // Set the selected one as primary
                $stmt = $conn->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?");
                $stmt->bind_param("ii", $_POST['primary_image'], $productId);
                $stmt->execute();
                $stmt->close();
            }
            
            $conn->commit();
            $_SESSION['message'] = "Product updated successfully!";
            $_SESSION['message_type'] = "success";
            header("Location: admin_products.php");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error updating product: " . $e->getMessage();
        }
    }
}

?>

<?php include 'admin_sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Product - Admin Panel</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    /* Same styles as admin_add_product.php */
    body {
      font-family: 'Poppins', sans-serif;
      margin-left: 280px;
    }
    
    @media (max-width: 1024px) {
      body {
        margin-left: 0;
      }
    }
    
    .content-container {
      padding: 2rem;
    }
    
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
      min-height: 120px;
    }
    
    .form-checkbox {
      width: 1rem;
      height: 1rem;
      border: 1px solid #d1d5db;
      border-radius: 0.25rem;
    }
    
    .is-invalid {
      border-color: #ef4444;
    }
    
    .invalid-feedback {
      color: #ef4444;
      font-size: 0.875rem;
      margin-top: 0.25rem;
    }
    
    .attribute-section {
      display: none;
    }
    
    .image-upload-container {
      border: 2px dashed #d1d5db;
      border-radius: 0.5rem;
      padding: 1.5rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .image-upload-container:hover {
      border-color: #10b981;
    }
    
    .preview-container {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-top: 1rem;
    }
    
    .preview-item {
      position: relative;
      width: 120px;
      height: 120px;
      border-radius: 0.5rem;
      overflow: hidden;
    }
    
    .preview-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .preview-item .remove-btn {
      position: absolute;
      top: 0.25rem;
      right: 0.25rem;
      background-color: rgba(239, 68, 68, 0.8);
      color: white;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }
    
    .primary-badge {
      position: absolute;
      bottom: 0.25rem;
      left: 0.25rem;
      background-color: rgba(16, 185, 129, 0.8);
      color: white;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.75rem;
    }
    
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
      <h1 class="text-2xl font-bold text-gray-800">Edit Product</h1>
      <a href="admin_products.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md flex items-center">
        <i class="fas fa-arrow-left mr-2"></i> Back to Products
      </a>
    </div>
    
    <!-- Display errors -->
    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    
    <!-- Product Form -->
    <form action="admin_edit_product.php?id=<?php echo $productId; ?>" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6">
      <!-- Basic Information -->
      <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Basic Information</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Product Name -->
          <div class="form-group">
            <label for="name" class="form-label">Product Name <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                   value="<?php echo htmlspecialchars($productData['name']); ?>" required>
            <?php if (isset($errors['name'])): ?>
              <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
            <?php endif; ?>
          </div>
          
          <!-- Category -->
          <div class="form-group">
            <label for="category_id" class="form-label">Category <span class="text-red-500">*</span></label>
            <select id="category_id" name="category_id" class="form-control <?php echo isset($errors['category_id']) ? 'is-invalid' : ''; ?>" required>
              <option value="">Select a category</option>
              <?php 
              // Group categories by product type
              $groupedCategories = [];
              foreach ($categories as $category) {
                  $groupedCategories[$category['product_type']][] = $category;
              }
              
              // Display categories organized by product type
              foreach ($groupedCategories as $type => $cats): 
                  $typeName = ucfirst($type) . 's';
                  if ($type === 'all') $typeName = 'General';
              ?>
                  <optgroup label="<?php echo htmlspecialchars($typeName); ?>">
                      <?php foreach ($cats as $category): ?>
                          <?php if ($category['parent_id'] === null): ?>
                              <option value="<?php echo $category['id']; ?>" 
                                  <?php echo $productData['category_id'] == $category['id'] ? 'selected' : ''; ?>
                                  data-product-type="<?php echo $category['product_type']; ?>">
                                  <?php echo htmlspecialchars($category['name']); ?>
                              </option>
                              <?php // Show child categories indented
                              foreach ($cats as $subcat): 
                                  if ($subcat['parent_id'] == $category['id']): ?>
                                      <option value="<?php echo $subcat['id']; ?>" 
                                          <?php echo $productData['category_id'] == $subcat['id'] ? 'selected' : ''; ?>
                                          data-product-type="<?php echo $subcat['product_type']; ?>">
                                          &nbsp;&nbsp;↳ <?php echo htmlspecialchars($subcat['name']); ?>
                                      </option>
                                  <?php endif; 
                              endforeach; ?>
                          <?php endif; ?>
                      <?php endforeach; ?>
                  </optgroup>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category_id'])): ?>
              <div class="invalid-feedback"><?php echo htmlspecialchars($errors['category_id']); ?></div>
            <?php endif; ?>
          </div>
          
          <!-- Product Type -->
          <div class="form-group">
            <label for="product_type" class="form-label">Product Type <span class="text-red-500">*</span></label>
            <select id="product_type" name="product_type" class="form-control" required>
              <option value="plant" <?php echo $productData['product_type'] == 'plant' ? 'selected' : ''; ?>>Plant</option>
              <option value="tool" <?php echo $productData['product_type'] == 'tool' ? 'selected' : ''; ?>>Gardening Tool</option>
              <option value="pesticide" <?php echo $productData['product_type'] == 'pesticide' ? 'selected' : ''; ?>>Pesticide</option>
              <option value="fertilizer" <?php echo $productData['product_type'] == 'fertilizer' ? 'selected' : ''; ?>>Fertilizer</option>
              <option value="accessory" <?php echo $productData['product_type'] == 'accessory' ? 'selected' : ''; ?>>Accessory</option>
            </select>
          </div>
          
          <!-- SKU -->
          <div class="form-group">
            <label for="sku" class="form-label">SKU (Stock Keeping Unit) <span class="text-red-500">*</span></label>
            <input type="text" id="sku" name="sku" class="form-control <?php echo isset($errors['sku']) ? 'is-invalid' : ''; ?>" 
                   value="<?php echo htmlspecialchars($productData['sku']); ?>" required>
            <?php if (isset($errors['sku'])): ?>
              <div class="invalid-feedback"><?php echo htmlspecialchars($errors['sku']); ?></div>
            <?php endif; ?>
          </div>
          
          <!-- Price -->
          <div class="form-group">
            <label for="price" class="form-label">Price ($) <span class="text-red-500">*</span></label>
            <input type="number" id="price" name="price" min="0" step="0.01" class="form-control <?php echo isset($errors['price']) ? 'is-invalid' : ''; ?>" 
                   value="<?php echo htmlspecialchars($productData['price']); ?>" required>
            <?php if (isset($errors['price'])): ?>
              <div class="invalid-feedback"><?php echo htmlspecialchars($errors['price']); ?></div>
            <?php endif; ?>
          </div>
          
          <!-- Discount Price -->
          <div class="form-group">
            <label for="discount_price" class="form-label">Discount Price ($)</label>
            <input type="number" id="discount_price" name="discount_price" min="0" step="0.01" class="form-control <?php echo isset($errors['discount_price']) ? 'is-invalid' : ''; ?>" 
                   value="<?php echo htmlspecialchars($productData['discount_price']); ?>">
            <?php if (isset($errors['discount_price'])): ?>
              <div class="invalid-feedback"><?php echo htmlspecialchars($errors['discount_price']); ?></div>
            <?php endif; ?>
          </div>
          
          <!-- Status -->
          <div class="form-group">
            <label class="form-label">Status</label>
            <div class="flex items-center space-x-4">
              <label class="inline-flex items-center">
                <input type="checkbox" name="is_active" class="form-checkbox" <?php echo $productData['is_active'] ? 'checked' : ''; ?>>
                <span class="ml-2">Active</span>
              </label>
              <label class="inline-flex items-center">
                <input type="checkbox" name="is_featured" class="form-checkbox" <?php echo $productData['is_featured'] ? 'checked' : ''; ?>>
                <span class="ml-2">Featured</span>
              </label>
            </div>
          </div>
        </div>
        
        <!-- Description -->
        <div class="form-group mt-6">
          <label for="description" class="form-label">Description</label>
          <textarea id="description" name="description" class="form-control form-textarea"><?php echo htmlspecialchars($productData['description']); ?></textarea>
        </div>
      </div>
      
      <!-- Product Attributes -->
      <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Product Attributes</h2>
        
        <!-- Plant Attributes -->
        <div id="plant-attributes" class="attribute-section <?php echo $productData['product_type'] == 'plant' ? 'block' : 'hidden'; ?>">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="form-group">
              <label for="plant_type" class="form-label">Plant Type</label>
              <input type="text" id="plant_type" name="plant_type" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['plant_type'] ?? ''); ?>">
            </div>
            <div class="form-group">
              <label for="sunlight_requirements" class="form-label">Sunlight Requirements</label>
              <select id="sunlight_requirements" name="sunlight_requirements" class="form-control">
                <option value="">Select sunlight requirement</option>
                <option value="full sun" <?php echo ($attributes['sunlight_requirements'] ?? '') == 'full sun' ? 'selected' : ''; ?>>Full Sun</option>
                <option value="partial sun" <?php echo ($attributes['sunlight_requirements'] ?? '') == 'partial sun' ? 'selected' : ''; ?>>Partial Sun</option>
                <option value="shade" <?php echo ($attributes['sunlight_requirements'] ?? '') == 'shade' ? 'selected' : ''; ?>>Shade</option>
              </select>
            </div>
            <div class="form-group">
              <label for="watering_needs" class="form-label">Watering Needs</label>
              <select id="watering_needs" name="watering_needs" class="form-control">
                <option value="">Select watering needs</option>
                <option value="low" <?php echo ($attributes['watering_needs'] ?? '') == 'low' ? 'selected' : ''; ?>>Low</option>
                <option value="moderate" <?php echo ($attributes['watering_needs'] ?? '') == 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                <option value="high" <?php echo ($attributes['watering_needs'] ?? '') == 'high' ? 'selected' : ''; ?>>High</option>
              </select>
            </div>
            <div class="form-group">
              <label for="mature_height" class="form-label">Mature Height</label>
              <input type="text" id="mature_height" name="mature_height" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['mature_height'] ?? ''); ?>" placeholder="e.g., 12 inches">
            </div>
            <div class="form-group">
              <label for="bloom_time" class="form-label">Bloom Time</label>
              <input type="text" id="bloom_time" name="bloom_time" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['bloom_time'] ?? ''); ?>" placeholder="e.g., Spring to Fall">
            </div>
          </div>
        </div>
        
        <!-- Tool Attributes -->
        <div id="tool-attributes" class="attribute-section <?php echo $productData['product_type'] == 'tool' ? 'block' : 'hidden'; ?>">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="form-group">
              <label for="material" class="form-label">Material</label>
              <input type="text" id="material" name="material" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['material'] ?? ''); ?>">
            </div>
            <div class="form-group">
              <label for="dimensions" class="form-label">Dimensions</label>
              <input type="text" id="dimensions" name="dimensions" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['dimensions'] ?? ''); ?>" placeholder="e.g., 10 x 5 x 2 inches">
            </div>
            <div class="form-group">
              <label for="weight" class="form-label">Weight</label>
              <input type="text" id="weight" name="weight" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['weight'] ?? ''); ?>" placeholder="e.g., 1.5 lbs">
            </div>
            <div class="form-group">
              <label for="warranty" class="form-label">Warranty</label>
              <input type="text" id="warranty" name="warranty" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['warranty'] ?? ''); ?>" placeholder="e.g., 1 year">
            </div>
          </div>
        </div>
        
        <!-- Pesticide Attributes -->
        <div id="pesticide-attributes" class="attribute-section <?php echo $productData['product_type'] == 'pesticide' ? 'block' : 'hidden'; ?>">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="form-group">
              <label for="active_ingredient" class="form-label">Active Ingredient</label>
              <input type="text" id="active_ingredient" name="active_ingredient" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['active_ingredient'] ?? ''); ?>">
            </div>
            <div class="form-group">
              <label for="target_pests" class="form-label">Target Pests</label>
              <input type="text" id="target_pests" name="target_pests" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['target_pests'] ?? ''); ?>" placeholder="e.g., Aphids, Spider Mites">
            </div>
            <div class="form-group">
              <label for="application_method" class="form-label">Application Method</label>
              <select id="application_method" name="application_method" class="form-control">
                <option value="">Select application method</option>
                <option value="spray" <?php echo ($attributes['application_method'] ?? '') == 'spray' ? 'selected' : ''; ?>>Spray</option>
                <option value="granular" <?php echo ($attributes['application_method'] ?? '') == 'granular' ? 'selected' : ''; ?>>Granular</option>
                <option value="systemic" <?php echo ($attributes['application_method'] ?? '') == 'systemic' ? 'selected' : ''; ?>>Systemic</option>
              </select>
            </div>
            <div class="form-group">
              <label for="frequency" class="form-label">Application Frequency</label>
              <input type="text" id="frequency" name="frequency" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['frequency'] ?? ''); ?>" placeholder="e.g., Every 7-10 days">
            </div>
          </div>
        </div>
        
        <!-- Fertilizer Attributes -->
        <div id="fertilizer-attributes" class="attribute-section <?php echo $productData['product_type'] == 'fertilizer' ? 'block' : 'hidden'; ?>">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="form-group">
              <label for="npk_ratio" class="form-label">NPK Ratio</label>
              <input type="text" id="npk_ratio" name="npk_ratio" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['npk_ratio'] ?? ''); ?>" placeholder="e.g., 10-10-10">
            </div>
            <div class="form-group">
              <label for="application_method" class="form-label">Application Method</label>
              <select id="application_method" name="application_method" class="form-control">
                <option value="">Select application method</option>
                <option value="top dressing" <?php echo ($attributes['application_method'] ?? '') == 'top dressing' ? 'selected' : ''; ?>>Top Dressing</option>
                <option value="soil mix" <?php echo ($attributes['application_method'] ?? '') == 'soil mix' ? 'selected' : ''; ?>>Soil Mix</option>
                <option value="foliar spray" <?php echo ($attributes['application_method'] ?? '') == 'foliar spray' ? 'selected' : ''; ?>>Foliar Spray</option>
              </select>
            </div>
            <div class="form-group">
              <label for="frequency" class="form-label">Application Frequency</label>
              <input type="text" id="frequency" name="frequency" class="form-control" 
                     value="<?php echo htmlspecialchars($attributes['frequency'] ?? ''); ?>" placeholder="e.g., Every 2 weeks">
            </div>
          </div>
        </div>
      </div>
      
      <!-- Inventory -->
      <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Inventory</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="form-group">
            <label for="quantity" class="form-label">Quantity in Stock</label>
            <input type="number" id="quantity" name="quantity" min="0" class="form-control" 
                   value="<?php echo htmlspecialchars($productData['quantity']); ?>">
          </div>
          <div class="form-group">
            <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
            <input type="number" id="low_stock_threshold" name="low_stock_threshold" min="0" class="form-control" 
                   value="<?php echo htmlspecialchars($productData['low_stock_threshold']); ?>">
          </div>
        </div>
      </div>
      
      <!-- Images -->
      <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Product Images</h2>
        
        <!-- Existing Images -->
        <?php if (!empty($images)): ?>
          <div class="mb-4">
            <h3 class="text-lg font-medium text-gray-700 mb-2">Current Images</h3>
            <div class="preview-container">
              <?php foreach ($images as $image): ?>
                <div class="preview-item">
                  <img src="uploads/products/<?php echo htmlspecialchars($image['image_path']); ?>" alt="Product Image">
                  <?php if ($image['is_primary']): ?>
                    <div class="primary-badge">Primary</div>
                  <?php endif; ?>
                  <div class="remove-btn" onclick="markImageForDeletion(<?php echo $image['id']; ?>, this)">
                    <i class="fas fa-times"></i>
                  </div>
                  <input type="hidden" name="delete_images[]" id="delete_image_<?php echo $image['id']; ?>" value="">
                  <input type="radio" name="primary_image" value="<?php echo $image['id']; ?>" 
                         <?php echo $image['is_primary'] ? 'checked' : ''; ?> 
                         class="hidden" id="primary_<?php echo $image['id']; ?>">
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        
        <!-- New Images Upload -->
        <div class="image-upload-container" id="imageUploadContainer">
          <input type="file" id="images" name="images[]" multiple accept="image/*" class="hidden">
          <div class="flex flex-col items-center justify-center py-8">
            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
            <p class="text-lg font-medium text-gray-700">Drag & drop images here or click to browse</p>
            <p class="text-sm text-gray-500 mt-1">Upload high-quality product images (JPEG, PNG)</p>
          </div>
        </div>
        
        <div class="preview-container" id="imagePreviewContainer">
          <!-- New preview images will be added here -->
        </div>
      </div>
      
      <!-- Form Actions -->
      <div class="flex justify-end space-x-4">
        <button type="reset" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-md">
          Reset Changes
        </button>
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md">
          Update Product
        </button>
      </div>
    </form>
  </div>
  
  <script>
    // Show/hide attribute sections based on product type
    document.getElementById('product_type').addEventListener('change', function() {
      // Hide all attribute sections
      document.querySelectorAll('.attribute-section').forEach(section => {
        section.classList.add('hidden');
      });
      
      // Show the selected attribute section
      const selectedSection = document.getElementById(this.value + '-attributes');
      if (selectedSection) {
        selectedSection.classList.remove('hidden');
      }
    });
    
    // Image upload handling
    const imageUploadContainer = document.getElementById('imageUploadContainer');
    const fileInput = document.getElementById('images');
    const previewContainer = document.getElementById('imagePreviewContainer');
    
    // Click on container triggers file input
    imageUploadContainer.addEventListener('click', () => fileInput.click());
    
    // Handle drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      imageUploadContainer.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
      imageUploadContainer.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
      imageUploadContainer.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
      imageUploadContainer.classList.add('border-green-500', 'bg-green-50');
    }
    
    function unhighlight() {
      imageUploadContainer.classList.remove('border-green-500', 'bg-green-50');
    }
    
    // Handle dropped files
    imageUploadContainer.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
      const dt = e.dataTransfer;
      const files = dt.files;
      handleFiles(files);
    }
    
    // Handle selected files
    fileInput.addEventListener('change', function() {
      handleFiles(this.files);
    });
    
    function handleFiles(files) {
      [...files].forEach(file => {
        if (!file.type.match('image.*')) return;
        
        const reader = new FileReader();
        reader.onload = function(e) {
          const previewItem = document.createElement('div');
          previewItem.className = 'preview-item';
          
          const img = document.createElement('img');
          img.src = e.target.result;
          
          const removeBtn = document.createElement('div');
          removeBtn.className = 'remove-btn';
          removeBtn.innerHTML = '<i class="fas fa-times"></i>';
          removeBtn.addEventListener('click', () => previewItem.remove());
          
          previewItem.appendChild(img);
          previewItem.appendChild(removeBtn);
          previewContainer.appendChild(previewItem);
        };
        reader.readAsDataURL(file);
      });
    }
    
    // Mark image for deletion
    function markImageForDeletion(imageId, element) {
      const deleteInput = document.getElementById(`delete_image_${imageId}`);
      const previewItem = element.closest('.preview-item');
      
      if (deleteInput.value === '') {
        deleteInput.value = imageId;
        previewItem.style.opacity = '0.5';
        previewItem.style.border = '2px solid red';
      } else {
        deleteInput.value = '';
        previewItem.style.opacity = '1';
        previewItem.style.border = 'none';
      }
    }
    
    // Set primary image
    document.querySelectorAll('.preview-item').forEach(item => {
      item.addEventListener('click', function(e) {
        if (!e.target.closest('.remove-btn')) {
          const radio = this.querySelector('input[type="radio"]');
          if (radio) {
            radio.checked = true;
            // Update UI to show which is primary
            document.querySelectorAll('.primary-badge').forEach(badge => {
              badge.style.display = 'none';
            });
            const primaryBadge = this.querySelector('.primary-badge');
            if (primaryBadge) {
              primaryBadge.style.display = 'block';
            } else {
              const badge = document.createElement('div');
              badge.className = 'primary-badge';
              badge.textContent = 'Primary';
              this.appendChild(badge);
            }
          }
        }
      });
    });
    
    // Filter categories based on product type
    document.getElementById('product_type').addEventListener('change', function() {
      const selectedType = this.value;
      const categoryOptions = document.querySelectorAll('#category_id option');
      
      categoryOptions.forEach(option => {
        if (option.value === "") {
          option.style.display = 'block'; // Always show the default option
          return;
        }
        
        const optionType = option.dataset.productType;
        if (optionType === 'all' || optionType === selectedType) {
          option.style.display = 'block';
          if (option.parentElement.tagName === 'OPTGROUP') {
            option.parentElement.style.display = 'block';
          }
        } else {
          option.style.display = 'none';
          // Hide optgroup if all its options are hidden
          if (option.parentElement.tagName === 'OPTGROUP') {
            const group = option.parentElement;
            const visibleOptions = group.querySelectorAll('option[style="display: block;"]');
            if (visibleOptions.length === 0) {
              group.style.display = 'none';
            }
          }
        }
      });
    });
    
    // Initialize the view based on current product type
    document.getElementById('product_type').dispatchEvent(new Event('change'));
  </script>
</body>
</html>