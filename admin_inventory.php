<?php

require_once 'db_connect.php';

// Check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Pagination settings
$per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $per_page) - $per_page : 0;

// Search and filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
$product_type = isset($_GET['product_type']) ? $_GET['product_type'] : '';

// Get inventory data with filters
try {
    // Base query
    $query = "SELECT SQL_CALC_FOUND_ROWS 
                p.id, p.name, p.sku, p.product_type, 
                pi.quantity, pi.low_stock_threshold, pi.is_in_stock,
                c.name as category_name
              FROM products p
              JOIN product_inventory pi ON p.id = pi.product_id
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE p.is_active = 1";
    
    // Add search condition
    if (!empty($search)) {
        $query .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
        $search_param = "%$search%";
    }
    
    // Add stock status filter
    if (!empty($stock_status)) {
        if ($stock_status === 'low') {
            $query .= " AND pi.quantity <= pi.low_stock_threshold";
        } elseif ($stock_status === 'out') {
            $query .= " AND pi.quantity = 0";
        } elseif ($stock_status === 'in') {
            $query .= " AND pi.quantity > 0";
        }
    }
    
    // Add product type filter
    if (!empty($product_type)) {
        $query .= " AND p.product_type = ?";
    }
    
    // Add sorting and pagination
    $query .= " ORDER BY p.name ASC LIMIT $start, $per_page";
    
    // Prepare and execute query
    $stmt = $conn->prepare($query);
    
    // Bind parameters based on filters
    $param_types = '';
    $param_values = [];
    
    if (!empty($search)) {
        $param_types .= 'ss';
        array_push($param_values, $search_param, $search_param);
    }
    
    if (!empty($product_type)) {
        $param_types .= 's';
        array_push($param_values, $product_type);
    }
    
    if (!empty($param_types)) {
        $stmt->bind_param($param_types, ...$param_values);
    }
    
    $stmt->execute();
    $inventory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get total count for pagination
    $total_result = $conn->query("SELECT FOUND_ROWS() as total");
    $total = $total_result->fetch_assoc()['total'];
    $pages = ceil($total / $per_page);
    
    // Get product types for filter dropdown
    $types_result = $conn->query("SELECT DISTINCT product_type FROM products");
    $product_types = $types_result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Inventory error: " . $e->getMessage());
    $inventory = [];
    $pages = 1;
}

// Handle inventory update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_inventory'])) {
    try {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        $threshold = (int)$_POST['low_stock_threshold'];
        
        $stmt = $conn->prepare("UPDATE product_inventory 
                               SET quantity = ?, low_stock_threshold = ?, is_in_stock = ?
                               WHERE product_id = ?");
        $is_in_stock = $quantity > 0 ? 1 : 0;
        $stmt->bind_param('iiii', $quantity, $threshold, $is_in_stock, $product_id);
        $stmt->execute();
        
        // Update product's in_stock status if needed
        $conn->query("UPDATE products SET is_active = $is_in_stock WHERE id = $product_id");
        
        $_SESSION['success_message'] = "Inventory updated successfully!";
        header("Location: admin_inventory.php");
        exit;
        
    } catch (Exception $e) {
        error_log("Inventory update error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error updating inventory: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 280px;
            padding: 2rem;
        }
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
        }
        .inventory-table th, .inventory-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .inventory-table th {
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }
        .inventory-table tr:hover {
            background-color: #f8fafc;
        }
        .stock-low {
            background-color: #fffaf0;
        }
        .stock-out {
            background-color: #fff5f5;
        }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Inventory Management</h1>
            <div class="text-sm text-gray-500">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <form method="get" action="admin_inventory.php">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Product name or SKU" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stock Status</label>
                        <select name="stock_status" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">All</option>
                            <option value="low" <?php echo $stock_status === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out" <?php echo $stock_status === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                            <option value="in" <?php echo $stock_status === 'in' ? 'selected' : ''; ?>>In Stock</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product Type</label>
                        <select name="product_type" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">All Types</option>
                            <?php foreach ($product_types as $type): ?>
                            <option value="<?php echo $type['product_type']; ?>" <?php echo $product_type === $type['product_type'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst($type['product_type']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">Filter</button>
                        <a href="admin_inventory.php" class="ml-2 w-full bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 text-center">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Inventory Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="p-4 border-b flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">
                    <?php 
                    echo "Inventory List";
                    if (!empty($search) || !empty($stock_status) || !empty($product_type)) {
                        echo " (Filtered)";
                    }
                    ?>
                </h2>
                <div>
                    <span class="text-sm text-gray-500">
                        <?php echo number_format($total); ?> products found
                    </span>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Low Stock Threshold</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): 
                            $stock_class = '';
                            if ($item['quantity'] <= 0) {
                                $stock_class = 'stock-out';
                            } elseif ($item['quantity'] <= $item['low_stock_threshold']) {
                                $stock_class = 'stock-low';
                            }
                        ?>
                        <tr class="<?php echo $stock_class; ?>">
                            <td class="font-medium"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['sku']); ?></td>
                            <td><?php echo ucfirst($item['product_type']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($item['quantity']); ?></td>
                            <td><?php echo number_format($item['low_stock_threshold']); ?></td>
                            <td>
                                <?php if ($item['quantity'] <= 0): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Out of Stock</span>
                                <?php elseif ($item['quantity'] <= $item['low_stock_threshold']): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Low Stock</span>
                                <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="text-blue-500 hover:text-blue-700 edit-inventory" 
                                        data-id="<?php echo $item['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                        data-quantity="<?php echo $item['quantity']; ?>"
                                        data-threshold="<?php echo $item['low_stock_threshold']; ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($inventory)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-gray-500">
                                No inventory items found matching your criteria.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($pages > 1): ?>
            <div class="p-4 border-t flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    Showing page <?php echo $page; ?> of <?php echo $pages; ?>
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php 
                    // Show page numbers
                    $visible_pages = 5;
                    $start_page = max(1, $page - floor($visible_pages / 2));
                    $end_page = min($pages, $start_page + $visible_pages - 1);
                    
                    if ($start_page > 1) {
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="px-3 py-1 text-gray-500">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active = $i == $page ? 'bg-blue-500 text-white' : 'text-gray-700 hover:bg-gray-50';
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium ' . $active . '">' . $i . '</a>';
                    }
                    
                    if ($end_page < $pages) {
                        if ($end_page < $pages - 1) {
                            echo '<span class="px-3 py-1 text-gray-500">...</span>';
                        }
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $pages])) . '" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">' . $pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Inventory Modal -->
    <div id="inventoryModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="inventoryForm" method="post" action="admin_inventory.php">
                    <input type="hidden" name="update_inventory" value="1">
                    <input type="hidden" name="product_id" id="modalProductId" value="">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Update Inventory: <span id="modalProductName"></span>
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Current Quantity</label>
                                <input type="number" name="quantity" id="modalQuantity" min="0" step="1" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Low Stock Threshold</label>
                                <input type="number" name="low_stock_threshold" id="modalThreshold" min="0" step="1" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <p class="text-xs text-gray-500 mt-1">System will alert when stock falls below this number</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-500 text-base font-medium text-white hover:bg-blue-600 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Update Inventory
                        </button>
                        <button type="button" id="cancelInventoryBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('adminSidebar').classList.toggle('open');
        });

        // Inventory modal handling
        const inventoryModal = document.getElementById('inventoryModal');
        const cancelInventoryBtn = document.getElementById('cancelInventoryBtn');
        
        // Show modal when edit button is clicked
        document.querySelectorAll('.edit-inventory').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('modalProductId').value = this.getAttribute('data-id');
                document.getElementById('modalProductName').textContent = this.getAttribute('data-name');
                document.getElementById('modalQuantity').value = this.getAttribute('data-quantity');
                document.getElementById('modalThreshold').value = this.getAttribute('data-threshold');
                inventoryModal.classList.remove('hidden');
            });
        });
        
        // Hide modal
        cancelInventoryBtn.addEventListener('click', function() {
            inventoryModal.classList.add('hidden');
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === inventoryModal) {
                inventoryModal.classList.add('hidden');
            }
        });
    </script>
</body>
</html>