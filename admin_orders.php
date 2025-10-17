<?php
// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Include database connection
require_once 'db_connect.php';

// Pagination configuration
$perPage = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $perPage) - $perPage : 0;

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build base query
$query = "SELECT SQL_CALC_FOUND_ROWS o.*, u.firstname, u.lastname 
          FROM orders o
          LEFT JOIN users u ON o.user_id = u.id
          WHERE 1=1";

$params = [];
$types = '';

// Apply filters
if (!empty($status)) {
    $query .= " AND o.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($search)) {
    $query .= " AND (o.order_number LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= 'ssss';
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND o.created_at BETWEEN ? AND ?";
    $params[] = $date_from . ' 00:00:00';
    $params[] = $date_to . ' 23:59:59';
    $types .= 'ss';
}

// Complete query with sorting and pagination
$query .= " ORDER BY o.created_at DESC LIMIT ?, ?";
$params[] = $start;
$params[] = $perPage;
$types .= 'ii';

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$totalResult = $conn->query("SELECT FOUND_ROWS() as total");
$total = $totalResult->fetch_assoc()['total'];
$pages = ceil($total / $perPage);

// Status options for filter
$statusOptions = [
    '' => 'All Statuses',
    'pending' => 'Pending',
    'processing' => 'Processing',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
];
?>
<?php include 'admin_sidebar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Management</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root {
      --primary-green: #2E8B57;
      --light-green: #a8e6cf;
      --sidebar-width: 280px;
    }
    
    body {
      font-family: 'Poppins', sans-serif;
      margin-left: 280px;;
    }
    
    .badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .badge-pending {
      background-color: #fef3c7;
      color: #92400e;
    }
    
    .badge-processing {
      background-color: #dbeafe;
      color: #1e40af;
    }
    
    .badge-completed {
      background-color: #dcfce7;
      color: #166534;
    }
    
    .badge-cancelled {
      background-color: #fee2e2;
      color: #991b1b;
    }
    
    @media (max-width: 1024px) {
      body {
        margin-left: 0;
        padding-top: 60px;
      }
    }
  </style>
</head>
<body>

  <div class="p-8">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Order Management</h1>
      <div class="flex items-center space-x-4">
        <a href="admin_orders_report.php" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
          <i class="fas fa-file-export mr-2"></i> Export
        </a>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
      <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-gray-700 mb-2">Status</label>
          <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded">
            <?php foreach ($statusOptions as $value => $label): ?>
              <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div>
          <label class="block text-gray-700 mb-2">Date From</label>
          <input type="date" name="date_from" value="<?= $date_from ?>" class="w-full px-3 py-2 border border-gray-300 rounded">
        </div>
        
        <div>
          <label class="block text-gray-700 mb-2">Date To</label>
          <input type="date" name="date_to" value="<?= $date_to ?>" class="w-full px-3 py-2 border border-gray-300 rounded">
        </div>
        
        <div>
          <label class="block text-gray-700 mb-2">Search</label>
          <div class="flex">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Order # or Customer" class="flex-1 px-3 py-2 border border-gray-300 rounded-l">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-r hover:bg-green-700">
              <i class="fas fa-search"></i>
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($orders)): ?>
              <tr>
                <td colspan="7" class="px-6 py-4 text-center text-gray-500">No orders found</td>
              </tr>
            <?php else: ?>
              <?php foreach ($orders as $order): ?>
                <tr class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <a href="admin_order_details.php?id=<?= $order['id'] ?>" class="text-green-600 hover:text-green-800 font-medium">
                      <?= htmlspecialchars($order['order_number']) ?>
                    </a>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?= htmlspecialchars($order['firstname'] . ' ' . $order['lastname']) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?= date('M j, Y', strtotime($order['created_at'])) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="badge badge-<?= $order['status'] ?>">
                      <?= ucfirst($order['status']) ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    $<?= number_format($order['total_amount'], 2) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <div class="flex justify-end space-x-2">
                      <a href="admin_order_details.php?id=<?= $order['id'] ?>" class="text-blue-500 hover:text-blue-700">
                        <i class="fas fa-eye"></i>
                      </a>
                      <a href="#" class="text-green-500 hover:text-green-700" onclick="updateStatus(<?= $order['id'] ?>)">
                        <i class="fas fa-edit"></i>
                      </a>
                      <a href="admin_order_print.php?id=<?= $order['id'] ?>" target="_blank" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-print"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700">
                Showing <span class="font-medium"><?= $start + 1 ?></span> to 
                <span class="font-medium"><?= min($start + $perPage, $total) ?></span> of 
                <span class="font-medium"><?= $total ?></span> results
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">Previous</span>
                    <i class="fas fa-chevron-left"></i>
                  </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $pages; $i++): ?>
                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="<?= $i == $page ? 'bg-green-50 border-green-500 text-green-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                    <?= $i ?>
                  </a>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">Next</span>
                    <i class="fas fa-chevron-right"></i>
                  </a>
                <?php endif; ?>
              </nav>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Status Update Modal -->
  <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Update Order Status</h3>
        <form id="statusForm" method="POST" action="admin_update_order_status.php">
          <input type="hidden" name="order_id" id="modalOrderId">
          <div class="mb-4">
            <label class="block text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded">
              <option value="pending">Pending</option>
              <option value="processing">Processing</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="mb-4">
            <label class="block text-gray-700 mb-2">Notes (Optional)</label>
            <textarea name="notes" class="w-full px-3 py-2 border border-gray-300 rounded" rows="3"></textarea>
          </div>
          <div class="flex justify-end space-x-3">
            <button type="button" onclick="document.getElementById('statusModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
              Cancel
            </button>
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
              Update Status
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Update status modal
    function updateStatus(orderId) {
      document.getElementById('modalOrderId').value = orderId;
      document.getElementById('statusModal').classList.remove('hidden');
    }

    // Close modal when clicking outside
    document.getElementById('statusModal').addEventListener('click', function(e) {
      if (e.target === this) {
        this.classList.add('hidden');
      }
    });

    // Toggle submenus in sidebar
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('productsMenu').addEventListener('click', function() {
        document.getElementById('productsSubmenu').classList.toggle('show');
        this.querySelector('.fa-chevron-down').classList.toggle('rotate-180');
      });
      
      document.getElementById('contentMenu').addEventListener('click', function() {
        document.getElementById('contentSubmenu').classList.toggle('show');
        this.querySelector('.fa-chevron-down').classList.toggle('rotate-180');
      });
    });
  </script>
</body>
</html>