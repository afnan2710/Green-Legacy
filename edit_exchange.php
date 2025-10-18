<?php
require_once 'db_connect.php';
require_once 'navbar.php';
// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['error_msg'] = "You must be logged in to edit an exchange offer.";
    header("Location: login.php");
    exit();
}

// Check if exchange ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error_msg'] = "No exchange offer specified.";
    header("Location: exchange.php");
    exit();
}

$exchange_id = intval($_GET['id']);

// Get the exchange offer details
$query = "SELECT * FROM exchange_offers WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $exchange_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$offer = $result->fetch_assoc();

if (!$offer) {
    $_SESSION['error_msg'] = "Exchange offer not found or you don't have permission to edit it.";
    header("Location: exchange.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $plant_name = trim($_POST['plant_name']);
    $plant_type = trim($_POST['plant_type']);
    $plant_age = !empty($_POST['plant_age']) ? trim($_POST['plant_age']) : null;
    $plant_health = $_POST['plant_health'];
    $exchange_type = $_POST['exchange_type'];
    $expected_amount = ($exchange_type == 'with_money' && !empty($_POST['expected_amount'])) ? floatval($_POST['expected_amount']) : null;
    $expected_plant = !empty($_POST['expected_plant']) ? trim($_POST['expected_plant']) : null;
    $location = trim($_POST['location']);
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    // Handle image uploads
    $image_paths = [];
    if (!empty($offer['images'])) {
        $image_paths = explode(',', $offer['images']);
    }

    // Process new image uploads
    if (!empty($_FILES['images']['name'][0])) {
        $upload_dir = 'uploads/exchanges/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = basename($_FILES['images']['name'][$key]);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $new_file_name = uniqid('img_', true) . '.' . $file_ext;
                $file_path = $upload_dir . $new_file_name;

                // Validate image
                $valid_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($file_ext, $valid_extensions)) {
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $image_paths[] = $file_path;
                    }
                }
            }
        }
    }

    // Remove selected images
    if (!empty($_POST['remove_images'])) {
        $images_to_remove = $_POST['remove_images'];
        foreach ($images_to_remove as $image_to_remove) {
            if (($key = array_search($image_to_remove, $image_paths)) !== false) {
                if (file_exists($image_to_remove)) {
                    unlink($image_to_remove);
                }
                unset($image_paths[$key]);
            }
        }
        $image_paths = array_values($image_paths); // Reindex array
    }

    $images = !empty($image_paths) ? implode(',', $image_paths) : null;

    // Update the offer in database
    $query = "UPDATE exchange_offers SET 
              title = ?, description = ?, plant_name = ?, plant_type = ?, plant_age = ?, 
              plant_health = ?, images = ?, exchange_type = ?, expected_amount = ?, 
              expected_plant = ?, location = ?, latitude = ?, longitude = ?, 
              updated_at = CURRENT_TIMESTAMP 
              WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssssdssddii", 
        $title, $description, $plant_name, $plant_type, $plant_age, 
        $plant_health, $images, $exchange_type, $expected_amount, 
        $expected_plant, $location, $latitude, $longitude, 
        $exchange_id, $_SESSION['user_id']);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Exchange offer updated successfully!";
        echo '<script>window.location.href = "exchange_detail.php?id='.$exchange_id.'";</script>';
        exit();
    } else {
        $_SESSION['error_msg'] = "Error updating exchange offer. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Exchange Offer | Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .plant-image {
            height: 150px;
            object-fit: cover;
            width: 100%;
        }
        .thumbnail {
            height: 80px;
            width: 80px;
            object-fit: cover;
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>
    
    <main class="container mx-auto mt-8 px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                </div>
            <?php endif; ?>
            
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Edit Exchange Offer</h1>
                <a href="exchange_detail.php?id=<?= $exchange_id ?>" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Offer
                </a>
            </div>
            
            <form method="post" enctype="multipart/form-data" class="bg-white rounded-lg shadow-md p-6">
                <!-- Basic Information -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Basic Information</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="title" class="block text-gray-700 mb-2">Offer Title*</label>
                            <input type="text" id="title" name="title" value="<?= htmlspecialchars($offer['title']) ?>" 
                                   class="w-full px-3 py-2 border rounded" required>
                        </div>
                        
                        <div>
                            <label for="plant_name" class="block text-gray-700 mb-2">Plant Name*</label>
                            <input type="text" id="plant_name" name="plant_name" value="<?= htmlspecialchars($offer['plant_name']) ?>" 
                                   class="w-full px-3 py-2 border rounded" required>
                        </div>
                        
                        <div>
                            <label for="plant_type" class="block text-gray-700 mb-2">Plant Type*</label>
                            <input type="text" id="plant_type" name="plant_type" value="<?= htmlspecialchars($offer['plant_type']) ?>" 
                                   class="w-full px-3 py-2 border rounded" required>
                        </div>
                        
                        <div>
                            <label for="plant_age" class="block text-gray-700 mb-2">Plant Age</label>
                            <input type="text" id="plant_age" name="plant_age" value="<?= htmlspecialchars($offer['plant_age']) ?>" 
                                   class="w-full px-3 py-2 border rounded" placeholder="e.g. 2 years">
                        </div>
                        
                        <div>
                            <label for="plant_health" class="block text-gray-700 mb-2">Plant Health*</label>
                            <select id="plant_health" name="plant_health" class="w-full px-3 py-2 border rounded" required>
                                <option value="excellent" <?= $offer['plant_health'] == 'excellent' ? 'selected' : '' ?>>Excellent</option>
                                <option value="good" <?= $offer['plant_health'] == 'good' ? 'selected' : '' ?>>Good</option>
                                <option value="average" <?= $offer['plant_health'] == 'average' ? 'selected' : '' ?>>Average</option>
                                <option value="poor" <?= $offer['plant_health'] == 'poor' ? 'selected' : '' ?>>Poor</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label for="description" class="block text-gray-700 mb-2">Description*</label>
                        <textarea id="description" name="description" rows="5" 
                                  class="w-full px-3 py-2 border rounded" required><?= htmlspecialchars($offer['description']) ?></textarea>
                    </div>
                </div>
                
                <!-- Images -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Images</h2>
                    
                    <?php if (!empty($offer['images'])): ?>
                        <div class="mb-4">
                            <p class="text-gray-700 mb-2">Current Images (check to remove):</p>
                            <div class="flex flex-wrap gap-4">
                                <?php foreach (explode(',', $offer['images']) as $image): ?>
                                    <div class="relative">
                                        <img src="<?= htmlspecialchars($image) ?>" class="thumbnail rounded">
                                        <div class="absolute top-0 right-0">
                                            <input type="checkbox" name="remove_images[]" value="<?= htmlspecialchars($image) ?>" 
                                                   class="rounded text-green-600">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <label for="images" class="block text-gray-700 mb-2">Add New Images</label>
                        <input type="file" id="images" name="images[]" multiple 
                               class="w-full px-3 py-2 border rounded" accept="image/*">
                        <p class="text-sm text-gray-500 mt-1">You can upload multiple images (JPEG, PNG, GIF)</p>
                    </div>
                </div>
                
                <!-- Exchange Details -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Exchange Details</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="exchange_type" class="block text-gray-700 mb-2">Exchange Type*</label>
                            <select id="exchange_type" name="exchange_type" class="w-full px-3 py-2 border rounded" required
                                    onchange="toggleExchangeFields()">
                                <option value="without_money" <?= $offer['exchange_type'] == 'without_money' ? 'selected' : '' ?>>Free Exchange (Plant for Plant)</option>
                                <option value="with_money" <?= $offer['exchange_type'] == 'with_money' ? 'selected' : '' ?>>Exchange With Money</option>
                            </select>
                        </div>
                        
                        <div id="expectedPlantField">
                            <label for="expected_plant" class="block text-gray-700 mb-2">Looking For (Plant)</label>
                            <input type="text" id="expected_plant" name="expected_plant" 
                                   value="<?= htmlspecialchars($offer['expected_plant']) ?>" 
                                   class="w-full px-3 py-2 border rounded" 
                                   placeholder="e.g. Snake Plant or any succulent">
                        </div>
                        
                        <div id="expectedAmountField" class="<?= $offer['exchange_type'] == 'with_money' ? '' : 'hidden' ?>">
                            <label for="expected_amount" class="block text-gray-700 mb-2">Expected Amount ($)</label>
                            <input type="number" id="expected_amount" name="expected_amount" 
                                   value="<?= $offer['expected_amount'] ?>" step="0.01" min="0"
                                   class="w-full px-3 py-2 border rounded">
                        </div>
                    </div>
                </div>
                
                <!-- Location -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Location</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="location" class="block text-gray-700 mb-2">Location*</label>
                            <input type="text" id="location" name="location" value="<?= htmlspecialchars($offer['location']) ?>" 
                                   class="w-full px-3 py-2 border rounded" required>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="latitude" class="block text-gray-700 mb-2">Latitude</label>
                                <input type="number" id="latitude" name="latitude" step="0.000001" 
                                       value="<?= $offer['latitude'] ?>" class="w-full px-3 py-2 border rounded">
                            </div>
                            
                            <div>
                                <label for="longitude" class="block text-gray-700 mb-2">Longitude</label>
                                <input type="number" id="longitude" name="longitude" step="0.000001" 
                                       value="<?= $offer['longitude'] ?>" class="w-full px-3 py-2 border rounded">
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">Latitude and longitude are optional but help with map display.</p>
                </div>
                
                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
    
    <script>
        function toggleExchangeFields() {
            const exchangeType = document.getElementById('exchange_type').value;
            const amountField = document.getElementById('expectedAmountField');
            
            if (exchangeType === 'with_money') {
                amountField.classList.remove('hidden');
            } else {
                amountField.classList.add('hidden');
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', toggleExchangeFields);
    </script>
</body>
</html>