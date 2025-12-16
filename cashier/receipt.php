<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

// Get receipt data from session
$receipt_data = $_SESSION['receipt_data'] ?? null;

if (!$receipt_data) {
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - No Receipt Data</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="alert alert-danger text-center">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i> No Receipt Data Found</h4>
                        <p class="mb-3">Please place an order first before trying to print a receipt.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i> Go to POS
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>';
    exit();
}

// Try to get order from database if we have an order_id
if (isset($receipt_data['order_id']) && $receipt_data['order_id'] > 0) {
    $sql = "SELECT o.*, u.fullname as cashier_name 
            FROM orders o 
            JOIN users u ON o.cashier_id = u.id 
            WHERE o.id = ?";
    $stmt = executeQuery($sql, array($receipt_data['order_id']));
    $db_order = fetchSingle($stmt);
    
    if ($db_order) {
        // Merge database data with session data
        $receipt_data = array_merge($receipt_data, $db_order);
        
        // Get items from database
        $sql_items = "SELECT oi.*, mi.name as item_name 
                      FROM order_items oi 
                      JOIN menu_items mi ON oi.menu_item_id = mi.id 
                      WHERE oi.order_id = ?";
        $stmt_items = executeQuery($sql_items, array($receipt_data['order_id']));
        $db_items = fetchAll($stmt_items);
        
        if ($db_items) {
            $receipt_data['items'] = $db_items;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo $receipt_data['order_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; padding: 0; margin: 0; }
            .receipt { border: none !important; box-shadow: none !important; }
            .container { max-width: 100% !important; padding: 0 !important; }
        }
        body {
            background: #f8f9fa;
            font-family: 'Courier New', monospace;
        }
        .receipt {
            width: 300px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border: 1px solid #000;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .receipt-header h5 {
            font-weight: bold;
            margin-bottom: 5px;
            color: #6f4e37;
        }
        .receipt-item {
            border-bottom: 1px dashed #ccc;
            padding: 5px 0;
        }
        .receipt-total {
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 15px;
            font-weight: bold;
        }
        .barcode {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            font-family: monospace;
            font-size: 1.2rem;
            letter-spacing: 2px;
        }
        .warning-alert {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <!-- Database Warning (if applicable) -->
                <?php if (isset($receipt_data['db_error'])): ?>
                <div class="warning-alert no-print">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> Order saved locally. Database error: <?php echo $receipt_data['db_error']; ?>
                </div>
                <?php endif; ?>
                
                <!-- Print Controls -->
                <div class="no-print text-center mb-3">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print me-2"></i> Print Receipt
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> New Order
                    </a>
                    <a href="../dashboard.php" class="btn btn-info">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </div>
                
                <!-- Receipt -->
                <div class="receipt">
                    <div class="receipt-header">
                        <h5>SIP HAPPENS</h5>
                        <p>When life spills, we refill</p>
                        <p>--------------------------------</p>
                        <p>Order #: <?php echo htmlspecialchars($receipt_data['order_number']); ?></p>
                        <p>Date: <?php echo $receipt_data['date']; ?></p>
                        <p>--------------------------------</p>
                    </div>
                    
                    <div class="receipt-body">
                        <p><strong>Cashier:</strong> <?php echo htmlspecialchars($receipt_data['cashier_name']); ?></p>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($receipt_data['customer_name']); ?></p>
                        
                        <hr style="border-top: 1px dashed #000;">
                        
                        <div class="receipt-items">
                            <?php foreach ($receipt_data['items'] as $item): ?>
                            <div class="receipt-item">
                                <div class="d-flex justify-content-between">
                                    <span>
                                        <?php echo htmlspecialchars($item['name'] ?? $item['item_name']); ?> 
                                        x<?php echo $item['quantity']; ?>
                                    </span>
                                    <span>₱<?php echo number_format($item['subtotal'] ?? ($item['price'] * $item['quantity']), 2); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <hr style="border-top: 1px dashed #000;">
                        
                        <div class="receipt-total">
                            <div class="d-flex justify-content-between">
                                <span>Subtotal:</span>
                                <span>₱<?php echo number_format($receipt_data['total_amount'], 2); ?></span>
                            </div>
                            <?php if ($receipt_data['discount_amount'] > 0): ?>
                            <div class="d-flex justify-content-between">
                                <span>Discount (<?php echo ucfirst($receipt_data['discount_type']); ?>):</span>
                                <span class="text-danger">-₱<?php echo number_format($receipt_data['discount_amount'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>TOTAL:</span>
                                <span>₱<?php echo number_format($receipt_data['final_amount'], 2); ?></span>
                            </div>
                        </div>
                        
                        <hr style="border-top: 2px solid #000;">
                        
                        <!-- Barcode -->
                        <div class="barcode">
                            <?php echo $receipt_data['order_number']; ?>
                        </div>
                        
                        <div class="text-center mt-3">
                            <p>THANK YOU FOR YOUR ORDER!</p>
                            <p>Please come again</p>
                        </div>
                    </div>
                </div>
                
                <!-- Order Status Info -->
                <div class="no-print alert alert-info mt-3">
                    <h6><i class="fas fa-info-circle me-2"></i> Order Status:</h6>
                    <p class="mb-2">Order #<?php echo $receipt_data['order_number']; ?> has been placed successfully.</p>
                    <?php if (isset($receipt_data['order_status'])): ?>
                    <p>Current Status: <span class="badge bg-info"><?php echo ucfirst($receipt_data['order_status']); ?></span></p>
                    <?php endif; ?>
                    <a href="orders.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-receipt me-1"></i> View All Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto print after 1 second
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
        
        // After print, optionally redirect
        window.onafterprint = function() {
            // Uncomment to redirect after printing
            // setTimeout(function() {
            //     window.location.href = 'index.php';
            // }, 1000);
        };
    </script>
</body>
</html>