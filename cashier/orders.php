<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    $sql = "UPDATE orders SET order_status = ? WHERE id = ?";
    executeQuery($sql, array($new_status, $order_id));
    
    // Record security log
    $sql = "INSERT INTO security_logs (user_id, action_type, description) 
            VALUES (?, 'order_status_update', ?)";
    executeQuery($sql, array($_SESSION['user_id'], "Updated order #$order_id to $new_status"));
    
    $success = "Order status updated successfully!";
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Build query based on filters
$sql = "SELECT o.*, u.fullname as cashier_name 
        FROM orders o 
        JOIN users u ON o.cashier_id = u.id 
        WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND o.order_status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $sql .= " AND CAST(o.created_at AS DATE) = ?";
    $params[] = $date_filter;
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = executeQuery($sql, $params);
$orders = fetchAll($stmt);

// Get counts for status badges
$sql_counts = "SELECT 
                SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN order_status = 'preparing' THEN 1 ELSE 0 END) as preparing_count,
                SUM(CASE WHEN order_status = 'serving' THEN 1 ELSE 0 END) as serving_count,
                SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
               FROM orders 
               WHERE CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)";
$stmt_counts = executeQuery($sql_counts);
$counts = fetchSingle($stmt_counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Sip Happens</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.85rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .order-card {
            transition: transform 0.2s;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-btn-group .btn {
            font-size: 0.8rem;
            padding: 5px 10px;
        }
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .count-badge {
            font-size: 0.7rem;
            margin-left: 5px;
        }
        .order-items-list {
            max-height: 200px;
            overflow-y: auto;
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
                <i class="fas fa-user me-1"></i> Cashier: <?php echo $_SESSION['fullname']; ?>
            </span>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <h2 class="mb-4"><i class="fas fa-receipt me-2"></i> Orders Management</h2>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Status Filter Tabs -->
        <div class="card mb-4">
            <div class="card-body">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter == 'all' ? 'active' : ''; ?>" 
                           href="?status=all&date=<?php echo $date_filter; ?>">
                            All Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter == 'pending' ? 'active' : ''; ?>" 
                           href="?status=pending&date=<?php echo $date_filter; ?>">
                            <span class="badge bg-warning count-badge"><?php echo $counts['pending_count'] ?? 0; ?></span>
                            Pending
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter == 'preparing' ? 'active' : ''; ?>" 
                           href="?status=preparing&date=<?php echo $date_filter; ?>">
                            <span class="badge bg-info count-badge"><?php echo $counts['preparing_count'] ?? 0; ?></span>
                            Preparing
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter == 'serving' ? 'active' : ''; ?>" 
                           href="?status=serving&date=<?php echo $date_filter; ?>">
                            <span class="badge bg-primary count-badge"><?php echo $counts['serving_count'] ?? 0; ?></span>
                            Serving
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter == 'completed' ? 'active' : ''; ?>" 
                           href="?status=completed&date=<?php echo $date_filter; ?>">
                            <span class="badge bg-success count-badge"><?php echo $counts['completed_count'] ?? 0; ?></span>
                            Completed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>" 
                           href="?status=cancelled&date=<?php echo $date_filter; ?>">
                            <span class="badge bg-danger count-badge"><?php echo $counts['cancelled_count'] ?? 0; ?></span>
                            Cancelled
                        </a>
                    </li>
                </ul>
                
                <!-- Date Filter -->
                <form method="GET" class="row g-3 mt-3">
                    <div class="col-md-3">
                        <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                        <input type="date" class="form-control" name="date" 
                               value="<?php echo $date_filter; ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="orders.php" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Orders List -->
        <div class="row">
            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order): 
                    // Get order items
                    $sql_items = "SELECT oi.*, mi.name as item_name 
                                  FROM order_items oi 
                                  JOIN menu_items mi ON oi.menu_item_id = mi.id 
                                  WHERE oi.order_id = ?";
                    $stmt_items = executeQuery($sql_items, array($order['id']));
                    $order_items = fetchAll($stmt_items);
                    
                    // Status badge color
                    $status_color = '';
                    switch ($order['order_status']) {
                        case 'pending': $status_color = 'warning'; break;
                        case 'preparing': $status_color = 'info'; break;
                        case 'serving': $status_color = 'primary'; break;
                        case 'completed': $status_color = 'success'; break;
                        case 'cancelled': $status_color = 'danger'; break;
                        default: $status_color = 'secondary';
                    }
                ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="order-card p-3">
                        <!-- Order Header -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1">Order #<?php echo $order['order_number']; ?></h6>
                                <small class="text-muted">
                                    <?php echo date('M d, Y h:i A', strtotime($order['created_at']->format('Y-m-d H:i:s'))); ?>
                                </small>
                            </div>
                            <span class="badge bg-<?php echo $status_color; ?> status-badge">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </div>
                        
                        <!-- Order Info -->
                        <div class="mb-3">
                            <p class="mb-1">
                                <i class="fas fa-user me-1"></i>
                                <strong>Customer:</strong> <?php echo $order['customer_name']; ?>
                            </p>
                            <p class="mb-1">
                                <i class="fas fa-user-tie me-1"></i>
                                <strong>Cashier:</strong> <?php echo $order['cashier_name']; ?>
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-money-bill me-1"></i>
                                <strong>Total:</strong> ₱<?php echo number_format($order['final_amount'], 2); ?>
                            </p>
                            <?php if ($order['discount_amount'] > 0): ?>
                            <p class="mb-0">
                                <i class="fas fa-tag me-1"></i>
                                <strong>Discount:</strong> -₱<?php echo number_format($order['discount_amount'], 2); ?>
                                <small class="text-muted">(<?php echo ucfirst($order['discount_type']); ?>)</small>
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Order Items -->
                        <div class="mb-3">
                            <h6 class="mb-2"><i class="fas fa-list me-1"></i> Items:</h6>
                            <div class="order-items-list">
                                <?php foreach ($order_items as $item): ?>
                                <div class="d-flex justify-content-between border-bottom py-1">
                                    <span><?php echo $item['item_name']; ?> x<?php echo $item['quantity']; ?></span>
                                    <span>₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Status Update Form -->
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Update Status:</small>
                                </div>
                                <div class="status-btn-group">
                                    <?php if ($order['order_status'] == 'pending'): ?>
                                        <button type="submit" name="update_status" value="preparing" 
                                                class="btn btn-info btn-sm">
                                            <i class="fas fa-play"></i> Start Prep
                                        </button>
                                        <button type="submit" name="update_status" value="cancelled" 
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Cancel this order?')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php elseif ($order['order_status'] == 'preparing'): ?>
                                        <button type="submit" name="update_status" value="serving" 
                                                class="btn btn-primary btn-sm">
                                            <i class="fas fa-check"></i> Done Preparing
                                        </button>
                                        <button type="submit" name="update_status" value="cancelled" 
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Cancel this order?')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php elseif ($order['order_status'] == 'serving'): ?>
                                        <button type="submit" name="update_status" value="completed" 
                                                class="btn btn-success btn-sm">
                                            <i class="fas fa-check-circle"></i> Done Serving
                                        </button>
                                    <?php elseif ($order['order_status'] == 'completed'): ?>
                                        <button type="button" class="btn btn-success btn-sm" disabled>
                                            <i class="fas fa-check-circle"></i> Order Completed
                                        </button>
                                        <a href="receipt.php?order_id=<?php echo $order['id']; ?>" 
                                           target="_blank" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-print"></i> Reprint
                                        </a>
                                    <?php elseif ($order['order_status'] == 'cancelled'): ?>
                                        <button type="button" class="btn btn-danger btn-sm" disabled>
                                            <i class="fas fa-times-circle"></i> Order Cancelled
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="hidden" name="status" id="status_<?php echo $order['id']; ?>">
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-2x mb-3"></i>
                        <h4>No orders found</h4>
                        <p class="mb-0">There are no orders matching your filter criteria.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle status button clicks
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('button[name="update_status"]').forEach(button => {
                button.addEventListener('click', function(e) {
                    const form = this.closest('form');
                    const statusInput = form.querySelector('input[name="status"]');
                    statusInput.value = this.value;
                    
                    // For cancel buttons, confirm first
                    if (this.value === 'cancelled') {
                        if (!confirm('Are you sure you want to cancel this order?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                });
            });
        });
        
        // Auto-refresh every 30 seconds for pending/preparing orders
        setTimeout(function() {
            if (window.location.href.includes('status=pending') || 
                window.location.href.includes('status=preparing') ||
                window.location.href.includes('status=serving')) {
                window.location.reload();
            }
        }, 30000); // 30 seconds
    </script>
</body>
</html>