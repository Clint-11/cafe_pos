<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

$cashier_id = $_SESSION['user_id'];

// Get current shift
$sql = "SELECT TOP 1 * FROM cashier_shifts 
        WHERE cashier_id = ? AND time_out IS NOT NULL 
        ORDER BY time_out DESC";
$stmt = executeQuery($sql, array($cashier_id));
$last_shift = fetchSingle($stmt);

// Get current shift sales
$current_shift_sales = 0;
$current_shift_orders = 0;

if ($last_shift) {
    $sql = "SELECT COUNT(*) as order_count, SUM(final_amount) as total_sales 
            FROM orders 
            WHERE cashier_id = ? 
            AND created_at BETWEEN ? AND ?";
    $stmt = executeQuery($sql, array($cashier_id, $last_shift['time_in'], $last_shift['time_out']));
    $result = fetchSingle($stmt);
    $current_shift_sales = $result['total_sales'] ?? 0;
    $current_shift_orders = $result['order_count'] ?? 0;
}

// Get shift history
$sql = "SELECT TOP 10 * FROM cashier_shifts 
        WHERE cashier_id = ? AND time_out IS NOT NULL 
        ORDER BY time_out DESC";
$stmt = executeQuery($sql, array($cashier_id));
$shifts = fetchAll($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Report - Sip Happens</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Shift Report</li>
            </ol>
        </nav>
        
        <h2>Shift Report</h2>
        <p class="text-muted">Cashier: <?php echo $_SESSION['fullname']; ?></p>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Last Shift Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($last_shift): ?>
                        <div class="row">
                            <div class="col-6">
                                <p><strong>Time In:</strong></p>
                                <p><strong>Time Out:</strong></p>
                                <p><strong>Duration:</strong></p>
                            </div>
                            <div class="col-6 text-end">
                                <p><?php echo date('Y-m-d h:i A', strtotime($last_shift['time_in']->format('Y-m-d H:i:s'))); ?></p>
                                <p><?php echo date('Y-m-d h:i A', strtotime($last_shift['time_out']->format('Y-m-d H:i:s'))); ?></p>
                                <p>
                                    <?php 
                                    $time_in = strtotime($last_shift['time_in']->format('Y-m-d H:i:s'));
                                    $time_out = strtotime($last_shift['time_out']->format('Y-m-d H:i:s'));
                                    $hours = floor(($time_out - $time_in) / 3600);
                                    $minutes = floor((($time_out - $time_in) % 3600) / 60);
                                    echo $hours . "h " . $minutes . "m";
                                    ?>
                                </p>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <p><strong>Total Orders:</strong></p>
                                <p><strong>Total Sales:</strong></p>
                            </div>
                            <div class="col-6 text-end">
                                <p><?php echo $current_shift_orders; ?></p>
                                <p class="fw-bold">₱<?php echo number_format($current_shift_sales, 2); ?></p>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="text-center text-muted">No previous shift found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Shift History (Last 10)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($shifts) > 0): ?>
                                    <?php foreach ($shifts as $shift): 
                                    $sql = "SELECT SUM(final_amount) as total_sales 
                                            FROM orders 
                                            WHERE cashier_id = ? 
                                            AND created_at BETWEEN ? AND ?";
                                    $stmt = executeQuery($sql, array($cashier_id, $shift['time_in'], $shift['time_out']));
                                    $sales_result = fetchSingle($stmt);
                                    ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($shift['time_in']->format('Y-m-d H:i:s'))); ?></td>
                                        <td><?php echo date('h:i A', strtotime($shift['time_in']->format('Y-m-d H:i:s'))); ?></td>
                                        <td><?php echo date('h:i A', strtotime($shift['time_out']->format('Y-m-d H:i:s'))); ?></td>
                                        <td>₱<?php echo number_format($sales_result['total_sales'] ?? 0, 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No shift history available.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="../dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>