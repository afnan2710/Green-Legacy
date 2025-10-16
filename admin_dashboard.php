<?php
require_once 'db_connect.php';

// Check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$time_period = isset($_GET['period']) ? $_GET['period'] : 'monthly';

// Get statistics
$stats = [];
try {
    // User counts
    $stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->get_result()->fetch_assoc()['total_users'];

    // Product counts
    $stmt = $conn->prepare("SELECT COUNT(*) as total_products FROM products WHERE is_active = 1");
    $stmt->execute();
    $stats['total_products'] = $stmt->get_result()->fetch_assoc()['total_products'];

    // Order counts
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(total_amount) as total_revenue
        FROM orders");
    $stmt->execute();
    $order_stats = $stmt->get_result()->fetch_assoc();
    $stats = array_merge($stats, $order_stats);

    // Recent orders
    $stmt = $conn->prepare("SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at, 
        u.firstname, u.lastname 
        FROM orders o LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC LIMIT 5");
    $stmt->execute();
    $recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Sales data for chart (last 30 days)
    $sales_query = "";
    switch ($time_period) {
        case 'weekly':
            $sales_query = "SELECT 
            YEARWEEK(created_at) as period,
            COUNT(*) as order_count, 
            SUM(total_amount) as daily_revenue 
            FROM orders 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK) 
            GROUP BY YEARWEEK(created_at) 
            ORDER BY period ASC";
            break;
        case 'daily':
            $sales_query = "SELECT 
            DATE(created_at) as period,
            COUNT(*) as order_count, 
            SUM(total_amount) as daily_revenue 
            FROM orders 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
            GROUP BY DATE(created_at) 
            ORDER BY period ASC";
            break;
        default: // monthly
            $sales_query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as period,
            COUNT(*) as order_count, 
            SUM(total_amount) as daily_revenue 
            FROM orders 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) 
            GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
            ORDER BY period ASC";
    }
    $stmt = $conn->prepare($sales_query);
    $stmt->execute();
    $sales_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Low stock products
    $stmt = $conn->prepare("SELECT p.id, p.name, pi.quantity, pi.low_stock_threshold 
        FROM products p 
        JOIN product_inventory pi ON p.id = pi.product_id 
        WHERE pi.quantity <= pi.low_stock_threshold AND p.is_active = 1 
        ORDER BY pi.quantity ASC LIMIT 5");
    $stmt->execute();
    $low_stock = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Recent messages
    $stmt = $conn->prepare("SELECT id, name, email, subject, created_at, is_read 
        FROM messages ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $recent_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Dashboard Overview</h1>
            <div class="text-sm text-gray-500">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500">Total Users</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($stats['total_users']); ?></h3>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-users text-blue-500 text-xl"></i>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2"><?php echo round(($stats['total_users'] / ($stats['total_users'] + 1000)) * 100); ?>% of target</p>
            </div>

            <div class="stat-card bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500">Total Products</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($stats['total_products']); ?></h3>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-leaf text-green-500 text-xl"></i>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2"><?php echo round($stats['total_products'] / 50); ?> categories</p>
            </div>

            <div class="stat-card bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500">Total Orders</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($stats['total_orders']); ?></h3>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-shopping-bag text-purple-500 text-xl"></i>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2"><?php echo $stats['completed_orders']; ?> completed</p>
            </div>

            <div class="stat-card bg-white rounded-lg shadow p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500">Total Revenue</p>
                        <h3 class="text-2xl font-bold">$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                    </div>
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <i class="fas fa-dollar-sign text-yellow-500 text-xl"></i>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2"><?php echo $stats['pending_orders']; ?> pending orders</p>
            </div>
        </div>

        <!-- Sales Chart -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-800">Sales Overview</h2>
                <div class="flex space-x-2">
                    <a href="?period=monthly" class="px-3 py-1 text-sm <?php echo $time_period == 'monthly' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded">Monthly</a>
                    <a href="?period=weekly" class="px-3 py-1 text-sm <?php echo $time_period == 'weekly' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded">Weekly</a>
                    <a href="?period=daily" class="px-3 py-1 text-sm <?php echo $time_period == 'daily' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded">Daily</a>
                </div>
            </div>
            <div class="h-80">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <!-- Recent Orders & Low Stock -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Recent Orders -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-semibold text-gray-800">Recent Orders</h2>
                </div>
                <div class="divide-y">
                    <?php foreach ($recent_orders as $order): ?>
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium">#<?php echo $order['order_number']; ?></p>
                                    <p class="text-sm text-gray-500"><?php echo $order['firstname'] . ' ' . $order['lastname']; ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">$<?php echo number_format($order['total_amount'], 2); ?></p>
                                    <span class="px-2 py-1 text-xs rounded-full 
                                    <?php echo $order['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($order['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="p-4 border-t text-center">
                    <a href="admin_orders.php" class="text-blue-500 hover:underline">View all orders</a>
                </div>
            </div>

            <!-- Low Stock Products -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-semibold text-gray-800">Low Stock Products</h2>
                </div>
                <div class="divide-y">
                    <?php foreach ($low_stock as $product): ?>
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium"><?php echo $product['name']; ?></p>
                                    <p class="text-sm text-gray-500">ID: <?php echo $product['id']; ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-red-500 font-medium"><?php echo $product['quantity']; ?> left</p>
                                    <p class="text-xs text-gray-500">Threshold: <?php echo $product['low_stock_threshold']; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($low_stock)): ?>
                        <div class="p-4 text-center text-gray-500">
                            No low stock products at this time
                        </div>
                    <?php endif; ?>
                </div>
                <div class="p-4 border-t text-center">
                    <a href="admin_inventory.php" class="text-blue-500 hover:underline">Manage inventory</a>
                </div>
            </div>
        </div>

        <!-- Recent Messages -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800">Recent Messages</h2>
            </div>
            <div class="divide-y">
                <?php foreach ($recent_messages as $message): ?>
                    <div class="p-4 hover:bg-gray-50 <?php echo $message['is_read'] ? '' : 'bg-blue-50'; ?>">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-medium"><?php echo $message['subject']; ?></p>
                                <p class="text-sm text-gray-500">From: <?php echo $message['name']; ?> &lt;<?php echo $message['email']; ?>&gt;</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500"><?php echo date('M j, g:i a', strtotime($message['created_at'])); ?></p>
                                <?php if (!$message['is_read']): ?>
                                    <span class="inline-block mt-1 px-2 py-1 text-xs bg-blue-500 text-white rounded-full">New</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="p-4 border-t text-center">
                <a href="admin_messages.php" class="text-blue-500 hover:underline">View all messages</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.js"></script>
    <script>
        // Format labels based on time period
        function formatLabel(dateString, period) {
            const date = new Date(dateString);
            switch (period) {
                case 'weekly':
                    // Get the week number and year
                    const oneJan = new Date(date.getFullYear(), 0, 1);
                    const weekNum = Math.ceil((((date - oneJan) / 86400000) + oneJan.getDay() + 1) / 7);
                    return `Week ${weekNum}, ${date.getFullYear()}`;
                case 'monthly':
                    return date.toLocaleString('default', {
                        month: 'short'
                    }) + ' ' + date.getFullYear();
                case 'daily':
                default:
                    return date.toLocaleString('default', {
                        month: 'short',
                        day: 'numeric'
                    });
            }
        }

        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: [<?php
                            echo implode(',', array_map(function ($item) use ($time_period) {
                                $date = $time_period === 'weekly' ?
                                    // For weekly, we need to convert YEARWEEK to a date
                                    // This is a simplified approach - you might need to adjust
                                    substr($item['period'], 0, 4) + '-01-01' :
                                    $item['period'];
                                // Format label for PHP (not using JS function)
                                if ($time_period === 'weekly' && strlen($item['period']) === 6) {
                                    $year = substr($item['period'], 0, 4);
                                    $week = substr($item['period'], 4, 2);
                                    return "'Week {$week}, {$year}'";
                                } elseif ($time_period === 'monthly') {
                                    $dt = DateTime::createFromFormat('Y-m', $item['period']);
                                    return "'" . ($dt ? $dt->format('M Y') : $item['period']) . "'";
                                } elseif ($time_period === 'daily') {
                                    $dt = DateTime::createFromFormat('Y-m-d', $item['period']);
                                    return "'" . ($dt ? $dt->format('M j') : $item['period']) . "'";
                                } else {
                                    return "'" . $item['period'] . "'";
                                }
                            }, $sales_data));
                            ?>],
                datasets: [{
                    label: 'Revenue',
                    data: [<?php echo implode(',', array_column($sales_data, 'daily_revenue')); ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.05)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    fill: true
                }, {
                    label: 'Orders',
                    data: [<?php echo implode(',', array_column($sales_data, 'order_count')); ?>],
                    backgroundColor: 'rgba(16, 185, 129, 0.05)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    fill: true,
                    yAxisID: 'y-axis-1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        type: 'linear',
                        display: true,
                        position: 'left',
                        id: 'y-axis-0',
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }, {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        id: 'y-axis-1',
                        gridLines: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(tooltipItem, data) {
                            if (tooltipItem.datasetIndex === 0) {
                                return 'Revenue: $' + tooltipItem.yLabel.toFixed(2);
                            } else {
                                return 'Orders: ' + tooltipItem.yLabel;
                            }
                        }
                    }
                }
            }
        });

        // Helper function to format dates for display
        function formatLabel(dateString, period) {
            if (!dateString) return '';

            // For weekly period, we might have just a year and week number
            if (period === 'weekly' && dateString.length === 6) {
                const year = dateString.substring(0, 4);
                const week = dateString.substring(4, 6);
                return `Week ${week}, ${year}`;
            }

            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) {
                    // If date is invalid, try parsing differently for weekly
                    if (period === 'weekly') {
                        const year = dateString.substring(0, 4);
                        const week = dateString.substring(4, 6);
                        return `Week ${week}, ${year}`;
                    }
                    return dateString;
                }

                switch (period) {
                    case 'weekly':
                        // Get the week number
                        const oneJan = new Date(date.getFullYear(), 0, 1);
                        const weekNum = Math.ceil((((date - oneJan) / 86400000) + oneJan.getDay() + 1) / 7);
                        return `Week ${weekNum}, ${date.getFullYear()}`;
                    case 'monthly':
                        return date.toLocaleString('default', {
                            month: 'short'
                        }) + ' ' + date.getFullYear();
                    case 'daily':
                    default:
                        return date.toLocaleString('default', {
                            month: 'short',
                            day: 'numeric'
                        });
                }
            } catch (e) {
                console.error('Error formatting date:', e);
                return dateString;
            }
        }

        // Mobile sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('adminSidebar').classList.toggle('open');
        });
    </script>
</body>

</html>