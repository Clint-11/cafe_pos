<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sip Happens POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 15px 20px;
            border-bottom: 1px solid #34495e;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
            color: #fff;
        }
        .sidebar .nav-link.active {
            background: #6f4e37;
        }
        .main-content {
            padding: 20px;
            background: #f8f9fa;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stat-card i {
            font-size: 2rem;
            color: #6f4e37;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar">
                <div class="text-center py-4">
                    <img src="assets/images/logo.png" alt="Logo" height="50" onerror="this.src='https://via.placeholder.com/150x50/6f4e37/ffffff?text=Sip+Happens'">
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    
                    <?php if ($role === 'admin'): ?>
                        <a class="nav-link" href="admin/index.php">
                            <i class="fas fa-cog me-2"></i> Admin Panel
                        </a>
                        <a class="nav-link" href="admin/menu_management.php">
                            <i class="fas fa-utensils me-2"></i> Menu Management
                        </a>
                        <a class="nav-link" href="admin/reports.php">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                        <a class="nav-link" href="admin/security_logs.php">
                            <i class="fas fa-shield-alt me-2"></i> Security Logs
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($role === 'cashier'): ?>
                        <a class="nav-link" href="cashier/index.php">
                            <i class="fas fa-cash-register me-2"></i> POS System
                        </a>
                        <a class="nav-link" href="cashier/orders.php">
                            <i class="fas fa-receipt me-2"></i> Orders
                        </a>
                    <?php endif; ?>
                    
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Welcome, <?php echo $_SESSION['fullname']; ?>!</h2>
                    <span class="badge bg-primary"><?php echo ucfirst($role); ?></span>
                </div>
                
                <div class="row">
                    <!-- Stats Cards -->
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <i class="fas fa-coffee mb-3"></i>
                            <h4>Today's Orders</h4>
                            <?php
                            $sql = "SELECT COUNT(*) as count FROM orders 
                                    WHERE CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)";
                            if ($role === 'cashier') {
                                $sql .= " AND cashier_id = ?";
                                $stmt = executeQuery($sql, array($user_id));
                            } else {
                                $stmt = executeQuery($sql);
                            }
                            $result = fetchSingle($stmt);
                            ?>
                            <h2><?php echo $result['count']; ?></h2>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <i class="fas fa-dollar-sign mb-3"></i>
                            <h4>Today's Revenue</h4>
                            <?php
                            $sql = "SELECT SUM(final_amount) as total FROM orders 
                                    WHERE CAST(created_at AS DATE) = CAST(GETDATE() AS DATE) 
                                    AND payment_status = 'paid'";
                            if ($role === 'cashier') {
                                $sql .= " AND cashier_id = ?";
                                $stmt = executeQuery($sql, array($user_id));
                            } else {
                                $stmt = executeQuery($sql);
                            }
                            $result = fetchSingle($stmt);
                            ?>
                            <h2>₱<?php echo number_format($result['total'] ?? 0, 2); ?></h2>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <i class="fas fa-clock mb-3"></i>
                            <h4>Active Shifts</h4>
                            <?php
                            $sql = "SELECT COUNT(*) as count FROM cashier_shifts 
                                    WHERE time_out IS NULL";
                            $stmt = executeQuery($sql);
                            $result = fetchSingle($stmt);
                            ?>
                            <h2><?php echo $result['count']; ?></h2>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <i class="fas fa-bell mb-3"></i>
                            <h4>Pending Orders</h4>
                            <?php
                            $sql = "SELECT COUNT(*) as count FROM orders 
                                    WHERE order_status IN ('preparing', 'pending')";
                            $stmt = executeQuery($sql);
                            $result = fetchSingle($stmt);
                            ?>
                            <h2><?php echo $result['count']; ?></h2>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Orders -->
                <div class="mt-4">
                    <h4>Recent Orders</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT TOP 10 o.*, u.fullname as cashier_name 
                                        FROM orders o 
                                        JOIN users u ON o.cashier_id = u.id 
                                        ORDER BY o.created_at DESC";
                                $stmt = executeQuery($sql);
                                $orders = fetchAll($stmt);
                                
                                foreach ($orders as $order):
                                ?>
                                <tr>
                                    <td><?php echo $order['order_number']; ?></td>
                                    <td><?php echo $order['customer_name'] ?: 'Walk-in'; ?></td>
                                    <td>₱<?php echo number_format($order['final_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $order['order_status'] === 'completed' ? 'success' : 
                                                 ($order['order_status'] === 'preparing' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('h:i A', strtotime($order['created_at']->format('Y-m-d H:i:s'))); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>