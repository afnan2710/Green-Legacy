<?php
require_once 'db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $per_page) - $per_page : 0;

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$query = "SELECT e.*, u.firstname, u.lastname, u.profile_pic as user_image,
          (SELECT username FROM admins WHERE admin_id = e.admin_id) as admin_name
          FROM exchange_offers e
          JOIN users u ON e.user_id = u.id
          WHERE 1=1";

$params = [];
$types = '';

if (!empty($status_filter)) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
//search filter
if (!empty($search_query)) {
    $query .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.plant_name LIKE ? OR e.plant_type LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM ($query) as total_query";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total = $total_result->fetch_assoc()['total'];
$pages = ceil($total / $per_page);

// Add sorting and pagination
$query .= " ORDER BY 
            CASE e.status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
                WHEN 'completed' THEN 4
            END,
            e.created_at DESC 
            LIMIT ?, ?";
$params[] = $start;
$params[] = $per_page;
$types .= 'ii';

// Get exchange offers
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$offers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle status change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_status'])) {
    $offer_id = (int)$_POST['offer_id'];
    $new_status = $_POST['new_status'];
    $admin_id = $_SESSION['admin_id'];

    // Validate status
    $valid_statuses = ['pending', 'approved', 'rejected', 'completed'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error_msg'] = "Invalid status selected.";
        header("Location: admin_exchanges.php");
        exit();
    }

    // Update status
    $query = "UPDATE exchange_offers 
              SET status = ?, admin_id = ?, updated_at = CURRENT_TIMESTAMP 
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $new_status, $admin_id, $offer_id);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Exchange offer status updated successfully!";

        // If marking as completed, notify users
        if ($new_status == 'completed') {
            // Here you would typically send notifications to involved users
            // This is a placeholder for that functionality
        }
    } else {
        $_SESSION['error_msg'] = "Error updating exchange offer status.";
    }

    header("Location: admin_exchanges.php");
    exit();
}
?>

<?php include 'admin_sidebar.php'; ?>
<style>
    .main-content {
        margin-left: 280px;
    }

    .thumbnail {
        height: 60px;
        width: 60px;
        object-fit: cover;
        border-radius: 0.375rem;
    }
</style>
<div class="main-content">
    <div class="ml-0 lg:ml-[280px] min-h-screen bg-gray-50">
        <div class="p-6">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Exchange Offers Management</h1>
                <div class="text-sm text-gray-500">
                    <?php echo date('l, F j, Y'); ?>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <form action="admin_exchanges.php" method="GET">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- Search -->
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_query) ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                                placeholder="Title, plant name or type">
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status" name="status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>

                        <!-- Filter Button -->
                        <div class="flex items-end">
                            <button type="submit"
                                class="w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= $_SESSION['success_msg'];
                    unset($_SESSION['success_msg']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= $_SESSION['error_msg'];
                    unset($_SESSION['error_msg']); ?>
                </div>
            <?php endif; ?>

            <!-- Exchange Offers Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Offer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plant Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exchange For</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($offers)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No exchange offers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($offers as $offer): ?>
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <?php if ($offer['images']): ?>
                                                    <?php
                                                    $images = explode(',', $offer['images']);
                                                    $firstImage = $images[0];
                                                    ?>
                                                    <div class="flex-shrink-0">
                                                        <img class="thumbnail" src="<?= htmlspecialchars($firstImage) ?>" alt="<?= htmlspecialchars($offer['plant_name']) ?>">
                                                    </div>
                                                <?php endif; ?>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($offer['title']) ?></div>
                                                    <div class="text-sm text-gray-500"><?= date('M j, Y', strtotime($offer['created_at'])) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <img class="h-10 w-10 rounded-full object-cover" src="<?= htmlspecialchars($offer['user_image'] ?? 'default-user.png') ?>" alt="<?= htmlspecialchars($offer['firstname']) ?>">
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($offer['firstname'] . ' ' . $offer['lastname']) ?></div>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($offer['location']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900"><?= htmlspecialchars($offer['plant_name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($offer['plant_type']) ?></div>
                                            <div class="text-xs text-gray-400 mt-1">
                                                <?= ucfirst($offer['plant_health']) ?> condition
                                                <?php if ($offer['plant_age']): ?>
                                                    • <?= htmlspecialchars($offer['plant_age']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($offer['exchange_type'] == 'with_money'): ?>
                                                <div class="text-sm text-gray-900">$<?= number_format($offer['expected_amount'], 2) ?></div>
                                                <?php if ($offer['expected_plant']): ?>
                                                    <div class="text-sm text-gray-500">or <?= htmlspecialchars($offer['expected_plant']) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($offer['expected_plant'] ?? 'Any plant') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $offer['status'] == 'approved' ? 'bg-green-100 text-green-800' : ($offer['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : ($offer['status'] == 'completed' ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800')) ?>">
                                                <?= ucfirst($offer['status']) ?>
                                            </span>
                                            <?php if ($offer['admin_name'] && $offer['status'] != 'pending'): ?>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <?= $offer['status'] ?> by <?= htmlspecialchars($offer['admin_name']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="space-y-2">
                                                <a href="admin_view_exchange.php?id=<?= $offer['id'] ?>" class="block text-blue-600 hover:text-blue-900">View</a>

                                                <!-- Status Change Form -->
                                                <form method="post" class="mt-1">
                                                    <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                                    <select name="new_status" class="text-xs p-1 border rounded focus:outline-none focus:ring-1 focus:ring-green-500">
                                                        <option value="">Change Status</option>
                                                        <option value="approved" <?= $offer['status'] == 'approved' ? 'selected disabled' : '' ?>>Approve</option>
                                                        <option value="rejected" <?= $offer['status'] == 'rejected' ? 'selected disabled' : '' ?>>Reject</option>
                                                        <option value="completed" <?= $offer['status'] == 'completed' ? 'selected disabled' : '' ?>>Mark as Completed</option>
                                                    </select>
                                                    <button type="submit" name="change_status" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded ml-1">Update</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-4">
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?= $start + 1 ?></span> to <span class="font-medium"><?= min($start + $per_page, $total) ?></span> of <span class="font-medium"><?= $total ?></span> results
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
</div>
</body>

</html>