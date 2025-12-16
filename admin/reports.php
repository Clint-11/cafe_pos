<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get report data based on selected period
$period = $_GET['period'] ?? 'today';
$start_date = '';
$end_date = '';

switch ($period) {
    case 'today':
        $start_date = date('Y-m-d') . ' 00:00:00';
        $end_date = date('Y-m-d') . ' 23:59:59';
        break;
    case 'yesterday':
        $start_date = date('Y-m-d', strtotime('-1 day')) . ' 00:00:00';
        $end_date = date('Y-m-d', strtotime('-1 day')) . ' 23:59:59';
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days')) . ' 00:00:00';
        $end_date = date('Y-m-d') . ' 23:59:59';
        break;
    case 'month':
        $start_date = date('Y-m-01') . ' 00:00:00';
        $end_date = date('Y-m-t') . ' 23:59:59';
        break;
}

// Get total sales
$sql = "SELECT SUM(final_amount) as total_sales, COUNT(*) as order_count 
        FROM orders 
        WHERE created_at BETWEEN ? AND ?";
$stmt = executeQuery($sql, array($start_date, $end_date));
$total_report = fetchSingle($stmt);

// Get sales by category
$sql = "SELECT c.name as category, SUM(oi.subtotal) as sales, COUNT(oi.id) as items_sold
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        JOIN categories c ON mi.category_id = c.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY c.name
        ORDER BY sales DESC";
$stmt = executeQuery($sql, array($start_date, $end_date));
$category_sales = fetchAll($stmt);

// Get top selling items
$sql = "SELECT TOP 10 mi.name, SUM(oi.quantity) as quantity_sold, SUM(oi.subtotal) as revenue
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY mi.name
        ORDER BY quantity_sold DESC";
$stmt = executeQuery($sql, array($start_date, $end_date));
$top_items = fetchAll($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Sip Happens</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
            <span class="navbar-text">
                Admin: <?php echo $_SESSION['fullname']; ?>
            </span>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <h2 class="mb-4">Sales Reports</h2>
        
        <!-- Period Selector -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select name="period" class="form-select" onchange="this.form.submit()">
                            <option value="today" <?php echo $period == 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $period == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Sales</h5>
                        <h2>₱<?php echo number_format($total_report['total_sales'] ?? 0, 2); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Orders</h5>
                        <h2><?php echo $total_report['order_count'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body text-center">
                        <h5 class="card-title">Average Order</h5>
                        <h2>₱<?php 
                            if ($total_report['order_count'] > 0) {
                                echo number_format(($total_report['total_sales'] ?? 0) / $total_report['order_count'], 2);
                            } else {
                                echo "0.00";
                            }
                        ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body text-center">
                        <h5 class="card-title">Period</h5>
                        <h2><?php 
                            echo ucfirst($period);
                            if ($period == 'today') echo ' (' . date('M d, Y') . ')';
                        ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Sales by Category -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Sales by Category</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart" height="250"></canvas>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Sales</th>
                                        <th>Items Sold</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($category_sales) > 0): ?>
                                    <?php foreach ($category_sales as $category): ?>
                                    <tr>
                                        <td><?php echo $category['category']; ?></td>
                                        <td>₱<?php echo number_format($category['sales'], 2); ?></td>
                                        <td><?php echo $category['items_sold']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No sales data available.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Selling Items -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Top Selling Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($top_items) > 0): ?>
                                    <?php foreach ($top_items as $item): ?>
                                    <tr>
                                        <td><?php echo $item['name']; ?></td>
                                        <td><?php echo $item['quantity_sold']; ?></td>
                                        <td>₱<?php echo number_format($item['revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No sales data available.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Daily Sales Chart -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Daily Sales Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: [<?php 
                    $labels = array_map(function($cat) { 
                        return "'" . addslashes($cat['category']) . "'"; 
                    }, $category_sales);
                    echo implode(', ', $labels);
                ?>],
                datasets: [{
                    data: [<?php 
                        $data = array_map(function($cat) { 
                            return $cat['sales']; 
                        }, $category_sales);
                        echo implode(', ', $data);
                    ?>],
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                        '#9966FF', '#FF9F40', '#8AC926', '#1982C4'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
        
        // Daily Sales Chart (Sample Data - you can implement actual data)
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales (₱)',
                    data: [12000, 19000, 15000, 25000, 22000, 30000, 28000],
                    borderColor: '#36A2EB',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>