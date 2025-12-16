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
        $period_label = 'Today';
        break;
    case 'yesterday':
        $start_date = date('Y-m-d', strtotime('-1 day')) . ' 00:00:00';
        $end_date = date('Y-m-d', strtotime('-1 day')) . ' 23:59:59';
        $period_label = 'Yesterday';
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days')) . ' 00:00:00';
        $end_date = date('Y-m-d') . ' 23:59:59';
        $period_label = 'Last 7 Days';
        break;
    case 'month':
        $start_date = date('Y-m-01') . ' 00:00:00';
        $end_date = date('Y-m-t') . ' 23:59:59';
        $period_label = 'This Month';
        break;
}

// Get total sales
$sql = "SELECT 
        COUNT(*) as order_count, 
        SUM(final_amount) as total_sales,
        SUM(discount_amount) as total_discount
        FROM orders 
        WHERE created_at BETWEEN ? AND ?";
$stmt = executeQuery($sql, array($start_date, $end_date));
$total_report = fetchSingle($stmt);

// Get orders by status
$sql_status = "SELECT 
                order_status,
                COUNT(*) as count,
                SUM(final_amount) as amount
               FROM orders 
               WHERE created_at BETWEEN ? AND ?
               GROUP BY order_status";
$stmt_status = executeQuery($sql_status, array($start_date, $end_date));
$orders_by_status = fetchAll($stmt_status);

// Get recent orders
$sql_recent = "SELECT TOP 10 
                order_number, 
                customer_name, 
                final_amount, 
                order_status,
                created_at
               FROM orders 
               WHERE created_at BETWEEN ? AND ?
               ORDER BY created_at DESC";
$stmt_recent = executeQuery($sql_recent, array($start_date, $end_date));
$recent_orders = fetchAll($stmt_recent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Sip Happens</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            text-align: center;
        }
        .stat-card h2 {
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
        .stat-card p {
            margin: 0;
            font-size: 1rem;
        }
        .period-selector {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .order-status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(111, 78, 55, 0.1);
        }
    </style>
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
        <h2 class="mb-4"><i class="fas fa-chart-bar me-2"></i> Sales Reports</h2>
        
        <!-- Period Selector -->
        <div class="period-selector">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-3">
                    <label class="form-label"><strong>Select Period:</strong></label>
                    <select name="period" class="form-select" onchange="this.form.submit()">
                        <option value="today" <?php echo $period == 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $period == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                </div>
                <div class="col-md-9">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-calendar me-2"></i>
                        <strong>Showing:</strong> <?php echo $period_label; ?> 
                        (<?php echo date('M d, Y', strtotime($start_date)); ?> 
                        to <?php echo date('M d, Y', strtotime($end_date)); ?>)
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card bg-primary">
                    <h2><?php echo $total_report['order_count'] ?? 0; ?></h2>
                    <p><i class="fas fa-receipt me-2"></i> Total Orders</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-success">
                    <h2>₱<?php echo number_format($total_report['total_sales'] ?? 0, 2); ?></h2>
                    <p><i class="fas fa-money-bill-wave me-2"></i> Total Sales</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-warning">
                    <h2>₱<?php echo number_format($total_report['total_discount'] ?? 0, 2); ?></h2>
                    <p><i class="fas fa-tag me-2"></i> Total Discounts</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-info">
                    <h2>
                        <?php 
                        if ($total_report['order_count'] > 0) {
                            echo "₱" . number_format(($total_report['total_sales'] ?? 0) / $total_report['order_count'], 2);
                        } else {
                            echo "₱0.00";
                        }
                        ?>
                    </h2>
                    <p><i class="fas fa-calculator me-2"></i> Average Order</p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Orders by Status -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Orders by Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Orders</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($orders_by_status) > 0): ?>
                                    <?php 
                                    $total_orders = 0;
                                    $total_amount = 0;
                                    foreach ($orders_by_status as $status):
                                        $total_orders += $status['count'];
                                        $total_amount += $status['amount'];
                                    ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $badge_color = '';
                                            switch ($status['order_status']) {
                                                case 'completed': $badge_color = 'success'; break;
                                                case 'preparing': $badge_color = 'warning'; break;
                                                case 'serving': $badge_color = 'info'; break;
                                                case 'pending': $badge_color = 'secondary'; break;
                                                case 'cancelled': $badge_color = 'danger'; break;
                                                default: $badge_color = 'secondary';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badge_color; ?> order-status-badge">
                                                <?php echo ucfirst($status['order_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo $status['count']; ?></strong>
                                            <?php if ($total_orders > 0): ?>
                                            <small class="text-muted">
                                                (<?php echo round(($status['count'] / $total_orders) * 100, 1); ?>%)
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>₱<?php echo number_format($status['amount'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-active">
                                        <td><strong>Total</strong></td>
                                        <td><strong><?php echo $total_orders; ?></strong></td>
                                        <td><strong>₱<?php echo number_format($total_amount, 2); ?></strong></td>
                                    </tr>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No orders in this period</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i> Recent Orders</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recent_orders) > 0): ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo $order['order_number']; ?></small><br>
                                            <small class="text-muted">
                                                <?php 
                                                if (isset($order['created_at']) && is_object($order['created_at'])) {
                                                    echo date('h:i A', strtotime($order['created_at']->format('Y-m-d H:i:s')));
                                                } else {
                                                    echo date('h:i A', strtotime($order['created_at']));
                                                }
                                                ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td>₱<?php echo number_format($order['final_amount'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $badge_color = '';
                                            switch ($order['order_status']) {
                                                case 'completed': $badge_color = 'success'; break;
                                                case 'preparing': $badge_color = 'warning'; break;
                                                case 'serving': $badge_color = 'info'; break;
                                                case 'pending': $badge_color = 'secondary'; break;
                                                case 'cancelled': $badge_color = 'danger'; break;
                                                default: $badge_color = 'secondary';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badge_color; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No recent orders</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Simple Date Range Stats -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Date Range Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="p-3">
                                    <h6>Start Date</h6>
                                    <h4 class="text-primary"><?php echo date('M d, Y', strtotime($start_date)); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3">
                                    <h6>End Date</h6>
                                    <h4 class="text-primary"><?php echo date('M d, Y', strtotime($end_date)); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3">
                                    <h6>Period</h6>
                                    <h4 class="text-success"><?php echo $period_label; ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3">
                                    <h6>Report Generated</h6>
                                    <h4 class="text-warning"><?php echo date('M d, Y h:i A'); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-tachometer-alt me-2"></i> Back to Admin Panel
                        </a>
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="fas fa-print me-2"></i> Print Report
                        </button>
                        <a href="../dashboard.php" class="btn btn-info">
                            <i class="fas fa-home me-2"></i> Go to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 5 minutes for today's report
        <?php if ($period === 'today'): ?>
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 5 minutes
        <?php endif; ?>
    </script>
</body>
</html>