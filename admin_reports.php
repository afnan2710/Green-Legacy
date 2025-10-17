<?php
require_once 'admin_sidebar.php';
require_once 'db_connect.php';


// Default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';

// Get report data
try {
    // Sales Report
    if ($report_type === 'sales') {
        $stmt = $conn->prepare("SELECT 
            DATE(created_at) as date,
            COUNT(*) as order_count,
            SUM(total_amount) as revenue,
            AVG(total_amount) as avg_order_value
            FROM orders
            WHERE created_at BETWEEN ? AND ? + INTERVAL 1 DAY
            GROUP BY DATE(created_at)
            ORDER BY date ASC");
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Summary stats
        $stmt = $conn->prepare("SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value,
            MIN(total_amount) as min_order,
            MAX(total_amount) as max_order
            FROM orders
            WHERE created_at BETWEEN ? AND ? + INTERVAL 1 DAY");
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $summary_stats = $stmt->get_result()->fetch_assoc();
    }
    
    // Product Report
    elseif ($report_type === 'products') {
        $stmt = $conn->prepare("SELECT 
            p.id, p.name, p.price,
            COUNT(oi.id) as units_sold,
            SUM(oi.price * oi.quantity) as revenue,
            c.name as category
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE o.created_at BETWEEN ? AND ? + INTERVAL 1 DAY OR o.id IS NULL
            GROUP BY p.id
            ORDER BY units_sold DESC");
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // User Report
    elseif ($report_type === 'users') {
        $stmt = $conn->prepare("SELECT 
            u.id, u.firstname, u.lastname, u.email, u.created_at,
            COUNT(o.id) as order_count,
            SUM(o.total_amount) as total_spent
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id AND o.created_at BETWEEN ? AND ? + INTERVAL 1 DAY
            GROUP BY u.id
            ORDER BY total_spent DESC");
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.css" rel="stylesheet">
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
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        .report-table th, .report-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .report-table th {
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }
        .report-table tr:hover {
            background-color: #f8fafc;
        }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Reports & Analytics</h1>
            <div class="text-sm text-gray-500">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <!-- Report Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <form method="get" action="admin_reports.php">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                        <select name="report_type" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="sales" <?php echo $report_type === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                            <option value="products" <?php echo $report_type === 'products' ? 'selected' : ''; ?>>Product Report</option>
                            <option value="users" <?php echo $report_type === 'users' ? 'selected' : ''; ?>>Customer Report</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">Generate Report</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Report Summary -->
        <?php if ($report_type === 'sales'): ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <p class="text-gray-500">Total Orders</p>
                <h3 class="text-2xl font-bold"><?php echo number_format($summary_stats['total_orders']); ?></h3>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                <p class="text-gray-500">Total Revenue</p>
                <h3 class="text-2xl font-bold">$<?php echo number_format($summary_stats['total_revenue'], 2); ?></h3>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
                <p class="text-gray-500">Avg. Order Value</p>
                <h3 class="text-2xl font-bold">$<?php echo number_format($summary_stats['avg_order_value'], 2); ?></h3>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-yellow-500">
                <p class="text-gray-500">Order Range</p>
                <h3 class="text-2xl font-bold">$<?php echo number_format($summary_stats['min_order'], 2); ?> - $<?php echo number_format($summary_stats['max_order'], 2); ?></h3>
            </div>
        </div>
        <?php endif; ?>

        <!-- Report Data -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="p-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800">
                    <?php echo ucfirst($report_type); ?> Report 
                    (<?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?>)
                </h2>
            </div>
            
            <div class="overflow-x-auto">
                <?php if ($report_type === 'sales'): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                            <th>Avg. Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($row['date'])); ?></td>
                            <td><?php echo $row['order_count']; ?></td>
                            <td>$<?php echo number_format($row['revenue'], 2); ?></td>
                            <td>$<?php echo number_format($row['avg_order_value'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php elseif ($report_type === 'products'): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $product): ?>
                        <tr>
                            <td class="font-medium"><?php echo $product['name']; ?></td>
                            <td><?php echo $product['category']; ?></td>
                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo $product['units_sold'] ?: 0; ?></td>
                            <td>$<?php echo number_format($product['revenue'] ?: 0, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php elseif ($report_type === 'users'): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Member Since</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $user): ?>
                        <tr>
                            <td class="font-medium"><?php echo $user['firstname'] . ' ' . $user['lastname']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td><?php echo $user['order_count'] ?: 0; ?></td>
                            <td>$<?php echo number_format($user['total_spent'] ?: 0, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <div class="p-4 border-t text-right">
                <button class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    <i class="fas fa-download mr-2"></i>Export as CSV
                </button>
            </div>
        </div>

        <!-- Charts Section -->
        <?php if ($report_type === 'sales' && !empty($report_data)): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Daily Revenue</h3>
                <div class="h-64">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Daily Orders</h3>
                <div class="h-64">
                    <canvas id="ordersChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.js"></script>
    <script>
        // Mobile sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('adminSidebar').classList.toggle('open');
        });

        <?php if ($report_type === 'sales' && !empty($report_data)): ?>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { 
                    return "'" . date('M j', strtotime($item['date'])) . "'"; 
                }, $report_data)); ?>],
                datasets: [{
                    label: 'Daily Revenue',
                    data: [<?php echo implode(',', array_column($report_data, 'revenue')); ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }]
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem) {
                            return '$' + tooltipItem.yLabel.toFixed(2);
                        }
                    }
                }
            }
        });

        // Orders Chart
        const ordersCtx = document.getElementById('ordersChart').getContext('2d');
        const ordersChart = new Chart(ordersCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { 
                    return "'" . date('M j', strtotime($item['date'])) . "'"; 
                }, $report_data)); ?>],
                datasets: [{
                    label: 'Daily Orders',
                    data: [<?php echo implode(',', array_column($report_data, 'order_count')); ?>],
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }]
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>