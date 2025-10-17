<?php

// Include database connection
require_once 'db_connect.php';

// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_coupon'])) {
        // Add new coupon
        $code = $_POST['code'];
        $discount_type = $_POST['discount_type'];
        $discount_value = $_POST['discount_value'];
        $min_order_amount = $_POST['min_order_amount'];
        $max_discount_amount = $_POST['max_discount_amount'];
        $start_date = date('Y-m-d H:i:s', strtotime($_POST['start_date']));
        $end_date = date('Y-m-d H:i:s', strtotime($_POST['end_date'] . ' 23:59:59'));
        $usage_limit = $_POST['usage_limit'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, max_discount_amount, start_date, end_date, usage_limit, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdddssii", $code, $discount_type, $discount_value, $min_order_amount, $max_discount_amount, $start_date, $end_date, $usage_limit, $is_active);
        $stmt->execute();

        $_SESSION['message'] = "Coupon added successfully!";
        header("Location: admin_coupons.php");
        exit;
    } elseif (isset($_POST['update_coupon'])) {
        // Update existing coupon
        $id = $_POST['coupon_id'];
        $code = $_POST['code'];
        $discount_type = $_POST['discount_type'];
        $discount_value = $_POST['discount_value'];
        $min_order_amount = $_POST['min_order_amount'];
        $max_discount_amount = $_POST['max_discount_amount'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $usage_limit = $_POST['usage_limit'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE coupons SET code=?, discount_type=?, discount_value=?, min_order_amount=?, max_discount_amount=?, start_date=?, end_date=?, usage_limit=?, is_active=? WHERE id=?");
        $stmt->bind_param("ssddddssii", $code, $discount_type, $discount_value, $min_order_amount, $max_discount_amount, $start_date, $end_date, $usage_limit, $is_active, $id);
        $stmt->execute();

        $_SESSION['message'] = "Coupon updated successfully!";
        header("Location: admin_coupons.php");
        exit;
    } elseif (isset($_POST['delete_coupon'])) {
        // Delete coupon
        $id = $_POST['coupon_id'];

        $stmt = $conn->prepare("DELETE FROM coupons WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $_SESSION['message'] = "Coupon deleted successfully!";
        header("Location: admin_coupons.php");
        exit;
    }
}

// Get all coupons
$coupons = [];
$result = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC");
if ($result) {
    $coupons = $result->fetch_all(MYSQLI_ASSOC);
}

// Get coupon details for edit
$edit_coupon = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_coupon = $result->fetch_assoc();
}
?>
<?php include 'admin_sidebar.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupon Management</title>
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
            margin-left: 280px;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-active {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-inactive {
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
        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= $_SESSION['message'] ?></span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                    <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z" />
                    </svg>
                </span>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Coupon/Voucher Management</h1>
            <button onclick="document.getElementById('addCouponModal').classList.remove('hidden')" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                <i class="fas fa-plus mr-2"></i> Add Coupon
            </button>
        </div>

        <!-- Coupons Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Min Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valid From</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valid To</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($coupons)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">No coupons found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coupons as $coupon): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap font-medium">
                                        <?= htmlspecialchars($coupon['code']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= $coupon['discount_type'] === 'percentage' ?
                                            htmlspecialchars($coupon['discount_value']) . '%' :
                                            '$' . htmlspecialchars($coupon['discount_value']) ?>
                                        <?php if ($coupon['max_discount_amount'] && $coupon['discount_type'] === 'percentage'): ?>
                                            <br><span class="text-xs text-gray-500">Max: $<?= $coupon['max_discount_amount'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        $<?= htmlspecialchars($coupon['min_order_amount']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= date('M j, Y', strtotime($coupon['start_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= date('M j, Y', strtotime($coupon['end_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= $coupon['used_count'] ?> / <?= $coupon['usage_limit'] ? $coupon['usage_limit'] : '∞' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="badge <?= $coupon['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= $coupon['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex justify-end space-x-2">
                                            <button onclick="editCoupon(<?= $coupon['id'] ?>)" class="text-blue-500 hover:text-blue-700">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="admin_coupons.php" onsubmit="return confirm('Are you sure you want to delete this coupon?');" class="inline">
                                                <input type="hidden" name="coupon_id" value="<?= $coupon['id'] ?>">
                                                <input type="hidden" name="delete_coupon">
                                                <button type="submit" class="text-red-500 hover:text-red-700">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
    </div>

    <!-- Add Coupon Modal -->
    <div id="addCouponModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-6 overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Add New Coupon</h3>
                    <button onclick="document.getElementById('addCouponModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="admin_coupons.php">
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Coupon Code</label>
                        <input type="text" name="code" required class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Discount Type</label>
                        <select name="discount_type" class="w-full px-3 py-2 border border-gray-300 rounded">
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed Amount</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Discount Value</label>
                        <input type="number" name="discount_value" step="0.01" min="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Minimum Order Amount</label>
                        <input type="number" name="min_order_amount" step="0.01" min="0" value="0" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Maximum Discount Amount (for % only)</label>
                        <input type="number" name="max_discount_amount" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Start Date</label>
                            <input type="date" name="start_date" required class="w-full px-3 py-2 border border-gray-300 rounded">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">End Date</label>
                            <input type="date" name="end_date" required class="w-full px-3 py-2 border border-gray-300 rounded">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Usage Limit (leave empty for unlimited)</label>
                        <input type="number" name="usage_limit" min="1" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>

                    <div class="mb-4 flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" checked class="mr-2">
                        <label for="is_active" class="text-gray-700">Active</label>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('addCouponModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" name="add_coupon" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Add Coupon
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Coupon Modal -->
    <div id="editCouponModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Edit Coupon</h3>
                    <button onclick="document.getElementById('editCouponModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php if ($edit_coupon): ?>
                    <form method="POST" action="admin_coupons.php">
                        <input type="hidden" name="coupon_id" value="<?= $edit_coupon['id'] ?>">

                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2">Coupon Code</label>
                            <input type="text" name="code" value="<?= htmlspecialchars($edit_coupon['code']) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2">Discount Type</label>
                            <select name="discount_type" class="w-full px-3 py-2 border border-gray-300 rounded">
                                <option value="percentage" <?= $edit_coupon['discount_type'] === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                                <option value="fixed" <?= $edit_coupon['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2">Discount Value</label>
                            <input type="number" name="discount_value" step="0.01" min="0.01" value="<?= $edit_coupon['discount_value'] ?>" required class="w-full px-3 py-2 border border-gray-300 rounded">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2">Minimum Order Amount</label>
                            <input type="number" name="min_order_amount" step="0.01" min="0" value="<?= $edit_coupon['min_order_amount'] ?>" class="w-full px-3 py-2 border border-gray-300 rounded">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2">Maximum Discount Amount (for % only)</label>
                            <input type="number" name="max_discount_amount" step="0.01" min="0" value="<?= $edit_coupon['max_discount_amount'] ?>" class="w-full px-3 py-2 border border-gray-300 rounded">
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Start Date</label>
                                <input type="date" name="start_date" value="<?= date('Y-m-d', strtotime($edit_coupon['start_date'])) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">End Date</label>
                                <input type="date" name="end_date" value="<?= date('Y-m-d', strtotime($edit_coupon['end_date'])) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2">Usage Limit (leave empty for unlimited)</label>
                            <input type="number" name="usage_limit" min="1" value="<?= $edit_coupon['usage_limit'] ?>" class="w-full px-3 py-2 border border-gray-300 rounded">
                        </div>

                        <div class="mb-4 flex items-center">
                            <input type="checkbox" name="is_active" id="edit_is_active" <?= $edit_coupon['is_active'] ? 'checked' : '' ?> class="mr-2">
                            <label for="edit_is_active" class="text-gray-700">Active</label>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="document.getElementById('editCouponModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" name="update_coupon" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                Update Coupon
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Show edit modal if there's an edit parameter in URL
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_GET['edit'])): ?>
                document.getElementById('editCouponModal').classList.remove('hidden');
            <?php endif; ?>
        });

        // Function to edit coupon
        function editCoupon(couponId) {
            window.location.href = 'admin_coupons.php?edit=' + couponId;
        }

        // Close modal when clicking outside
        document.getElementById('addCouponModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });

        document.getElementById('editCouponModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
</body>

</html>