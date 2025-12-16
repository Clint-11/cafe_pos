<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

// Handle adding to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $item_id = $_POST['item_id'];
    $quantity = $_POST['quantity'] ?? 1;
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Get item details
    $sql = "SELECT * FROM menu_items WHERE id = ?";
    $stmt = executeQuery($sql, array($item_id));
    $item = fetchSingle($stmt);
    
    if ($item) {
        $found = false;
        foreach ($_SESSION['cart'] as &$cart_item) {
            if ($cart_item['id'] == $item_id) {
                $cart_item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $_SESSION['cart'][] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'image_url' => $item['image_url'],
                'quantity' => $quantity
            ];
        }
    }
}

// Handle removing from cart
if (isset($_GET['remove_item'])) {
    $index = $_GET['remove_item'];
    if (isset($_SESSION['cart'][$index])) {
        array_splice($_SESSION['cart'], $index, 1);
    }
    header("Location: index.php");
    exit();
}

// Handle placing order - WORKING VERSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $customer_name = $_POST['customer_name'] ?: 'Walk-in Customer';
    $discount_type = $_POST['discount_type'] ?? 'none';
    
    // Calculate totals
    $subtotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    $discount_amount = 0;
    if ($discount_type === 'senior' || $discount_type === 'pwd') {
        $discount_amount = $subtotal * 0.20; // 20% discount
    }
    
    $final_amount = $subtotal - $discount_amount;
    
    // Generate unique order number
    $order_number = 'ORD-' . date('YmdHis') . '-' . rand(1000, 9999);
    
    // Store receipt data in session FIRST (in case database fails)
    $_SESSION['receipt_data'] = [
        'order_number' => $order_number,
        'customer_name' => $customer_name,
        'cashier_name' => $_SESSION['fullname'],
        'total_amount' => $subtotal,
        'discount_type' => $discount_type,
        'discount_amount' => $discount_amount,
        'final_amount' => $final_amount,
        'items' => $_SESSION['cart'],
        'date' => date('Y-m-d H:i:s')
    ];
    
    // Try to save to database
    try {
        // Insert order
        $sql = "INSERT INTO orders (order_number, cashier_id, customer_name, total_amount, 
                discount_type, discount_amount, final_amount, payment_method, payment_status, order_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'cash', 'paid', 'pending')";
        
        $params = array($order_number, $_SESSION['user_id'], $customer_name, $subtotal, 
                       $discount_type, $discount_amount, $final_amount);
        
        $stmt = executeQuery($sql, $params);
        
        if ($stmt === false) {
            throw new Exception("Failed to insert order");
        }
        
        // Get the inserted order ID
        $sql2 = "SELECT id FROM orders WHERE order_number = ?";
        $stmt2 = executeQuery($sql2, array($order_number));
        $result = fetchSingle($stmt2);
        
        if ($result && isset($result['id'])) {
            $order_id = $result['id'];
            
            // Insert order items
            foreach ($_SESSION['cart'] as $item) {
                $sql3 = "INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, subtotal) 
                        VALUES (?, ?, ?, ?, ?)";
                $subtotal_item = $item['price'] * $item['quantity'];
                $stmt3 = executeQuery($sql3, array($order_id, $item['id'], $item['quantity'], $item['price'], $subtotal_item));
                
                if ($stmt3 === false) {
                    throw new Exception("Failed to insert order item: " . $item['name']);
                }
            }
            
            // Store database order ID
            $_SESSION['receipt_data']['order_id'] = $order_id;
            
            // Record security log
            $sql4 = "INSERT INTO security_logs (user_id, action_type, description) 
                    VALUES (?, 'order_placed', ?)";
            executeQuery($sql4, array($_SESSION['user_id'], "Order #$order_number placed for ₱$final_amount"));
            
        } else {
            // If we can't get ID, still proceed with receipt
            $_SESSION['receipt_data']['order_id'] = 0;
        }
        
    } catch (Exception $e) {
        // Database failed, but we still have session data for receipt
        error_log("Order save error: " . $e->getMessage());
        $_SESSION['receipt_data']['order_id'] = 0;
        $_SESSION['receipt_data']['db_error'] = $e->getMessage();
    }
    
    // Clear cart
    $_SESSION['cart'] = [];
    
    // Redirect to receipt
    header("Location: receipt.php");
    exit();
}

// Get menu categories and items
$sql = "SELECT c.*, m.id as item_id, m.name as item_name, m.price, m.description, m.image_url 
        FROM categories c 
        LEFT JOIN menu_items m ON c.id = m.category_id 
        WHERE m.is_available = 1 
        ORDER BY c.display_order, m.name";
$stmt = executeQuery($sql);
$menu_items = fetchAll($stmt);

// Group by category
$categories = [];
foreach ($menu_items as $item) {
    if ($item['item_id']) {
        $cat_id = $item['id'];
        if (!isset($categories[$cat_id])) {
            $categories[$cat_id] = [
                'name' => $item['name'],
                'items' => []
            ];
        }
        $categories[$cat_id]['items'][] = $item;
    }
}

// Initialize subtotal for display
$subtotal = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - Sip Happens</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .pos-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        .menu-section {
            flex: 3;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }
        .cart-section {
            flex: 2;
            padding: 20px;
            background: white;
            border-left: 3px solid #dee2e6;
            display: flex;
            flex-direction: column;
        }
        .category-tabs {
            margin-bottom: 20px;
        }
        .menu-item-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            text-align: center;
            height: 100%;
        }
        .menu-item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-color: #6f4e37;
        }
        .cart-item {
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
        }
        .cart-total {
            background: #6f4e37;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-top: auto;
        }
        .item-image {
            width: 140px;
            height: 140px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #dee2e6;
            margin: 0 auto 15px auto;
            display: block;
        }
        .cart-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            margin-right: 10px;
        }
        .empty-cart-icon {
            font-size: 4rem;
            color: #adb5bd;
            margin-bottom: 15px;
        }
        .category-badge {
            background: #6f4e37;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            display: inline-block;
            margin-top: 8px;
        }
        .quantity-badge {
            background: #198754;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            position: absolute;
            top: -8px;
            right: -8px;
        }
        .btn-check:checked + .btn-outline-warning {
            background-color: #ffc107;
            color: #000;
        }
        .btn-check:checked + .btn-outline-info {
            background-color: #0dcaf0;
            color: #000;
        }
        .btn-check:checked + .btn-outline-secondary {
            background-color: #6c757d;
            color: #fff;
        }
        .search-box {
            width: 300px;
        }
        .item-name {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .item-price {
            font-size: 1.1rem;
            font-weight: bold;
            color: #6f4e37;
            margin-bottom: 10px;
        }
        .item-description {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 10px;
            min-height: 40px;
        }
        .add-to-cart-btn {
            background: #6f4e37;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: background 0.3s;
        }
        .add-to-cart-btn:hover {
            background: #5a3f2e;
        }
        .in-cart-badge {
            background: #198754;
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            margin-top: 5px;
            display: inline-block;
        }
        .category-header {
            background: #6f4e37;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
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
                <?php if (!empty($_SESSION['cart'])): ?>
                <span class="badge bg-warning ms-2"><?php echo count($_SESSION['cart']); ?> items</span>
                <?php endif; ?>
            </span>
        </div>
    </nav>
    
    <div class="pos-container">
        <!-- Left: Menu Items -->
        <div class="menu-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-utensils me-2"></i> Menu</h3>
                <div class="search-box">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="searchMenu" placeholder="Search menu items...">
                    </div>
                </div>
            </div>
            
            <!-- Category Tabs -->
            <ul class="nav nav-tabs category-tabs" id="categoryTabs">
                <?php
                $first = true;
                foreach ($categories as $cat_id => $category):
                ?>
                <li class="nav-item">
                    <button class="nav-link <?php echo $first ? 'active' : ''; ?>" 
                            data-bs-toggle="tab" 
                            data-bs-target="#cat-<?php echo $cat_id; ?>">
                        <?php echo $category['name']; ?>
                    </button>
                </li>
                <?php $first = false; endforeach; ?>
            </ul>
            
            <!-- Menu Items by Category -->
            <div class="tab-content mt-3">
                <?php
                $first = true;
                foreach ($categories as $cat_id => $category):
                ?>
                <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" 
                     id="cat-<?php echo $cat_id; ?>">
                    <div class="category-header">
                        <i class="fas fa-tag me-2"></i><?php echo $category['name']; ?>
                    </div>
                    <div class="grid-view">
                        <?php foreach ($category['items'] as $item): ?>
                        <div class="menu-item-card">
                            <!-- Item Image (Bigger) -->
                            <?php 
                            $image_url = !empty($item['image_url']) ? '../' . $item['image_url'] : 'https://via.placeholder.com/140x140/6f4e37/ffffff?text=' . urlencode(substr($item['item_name'], 0, 10));
                            ?>
                            <img src="<?php echo $image_url; ?>" alt="<?php echo $item['item_name']; ?>" 
                                 class="item-image"
                                 onerror="this.src='https://via.placeholder.com/140x140/6f4e37/ffffff?text=No+Image'">
                            
                            <!-- Item Name (Below Image) -->
                            <div class="item-name">
                                <?php echo $item['item_name']; ?>
                            </div>
                            
                            <!-- Item Description -->
                            <?php if ($item['description']): ?>
                            <div class="item-description">
                                <?php echo substr($item['description'], 0, 60); ?><?php echo strlen($item['description']) > 60 ? '...' : ''; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Item Price -->
                            <div class="item-price">
                                ₱<?php echo number_format($item['price'], 2); ?>
                            </div>
                            
                            <!-- Category Badge -->
                            <div class="category-badge">
                                <?php echo $category['name']; ?>
                            </div>
                            
                            <!-- Check if item is in cart -->
                            <?php
                            $cart_quantity = 0;
                            if (isset($_SESSION['cart'])) {
                                foreach ($_SESSION['cart'] as $cart_item) {
                                    if ($cart_item['id'] == $item['item_id']) {
                                        $cart_quantity = $cart_item['quantity'];
                                        break;
                                    }
                                }
                            }
                            if ($cart_quantity > 0): ?>
                            <div class="in-cart-badge">
                                <i class="fas fa-check-circle me-1"></i> In cart: <?php echo $cart_quantity; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Add to Cart Form -->
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light">Qty</span>
                                    <input type="number" name="quantity" value="1" min="1" max="99" 
                                           class="form-control text-center" style="width: 60px;">
                                    <button type="submit" name="add_to_cart" class="add-to-cart-btn">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $first = false; endforeach; ?>
            </div>
        </div>
        
        <!-- Right: Cart -->
        <div class="cart-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-shopping-cart me-2"></i> Order Cart</h3>
                <?php if (!empty($_SESSION['cart'])): ?>
                <span class="badge bg-primary rounded-pill">
                    <?php echo count($_SESSION['cart']); ?> item(s)
                </span>
                <?php endif; ?>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <strong>Error:</strong> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Customer Info Form -->
            <form method="POST" id="orderForm">
                <div class="mb-3">
                    <label for="customer_name" class="form-label">
                        <i class="fas fa-user me-1"></i> Customer Name (Optional)
                    </label>
                    <input type="text" class="form-control" id="customer_name" name="customer_name" 
                           placeholder="Walk-in Customer">
                </div>
                
                <!-- Discount Options -->
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-tag me-1"></i> Discount (20%)
                    </label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="discount_type" 
                               id="no_discount" value="none" autocomplete="off" 
                               <?php echo (!isset($_POST['discount_type']) || $_POST['discount_type'] == 'none') ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-secondary" for="no_discount">None</label>
                        
                        <input type="radio" class="btn-check" name="discount_type" 
                               id="senior" value="senior" autocomplete="off"
                               <?php echo (isset($_POST['discount_type']) && $_POST['discount_type'] == 'senior') ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-warning" for="senior">
                            <i class="fas fa-user-tie me-1"></i> Senior
                        </label>
                        
                        <input type="radio" class="btn-check" name="discount_type" 
                               id="pwd" value="pwd" autocomplete="off"
                               <?php echo (isset($_POST['discount_type']) && $_POST['discount_type'] == 'pwd') ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-info" for="pwd">
                            <i class="fas fa-wheelchair me-1"></i> PWD
                        </label>
                    </div>
                </div>
            
            <!-- Cart Items -->
            <div class="flex-grow-1 overflow-auto">
                <?php if (empty($_SESSION['cart'])): ?>
                    <div class="text-center text-muted py-5">
                        <div class="empty-cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h5>Your cart is empty</h5>
                        <p class="mb-0">Select items from the menu to get started</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $cart_subtotal = 0;
                    foreach ($_SESSION['cart'] as $index => $item):
                        $item_total = $item['price'] * $item['quantity'];
                        $cart_subtotal += $item_total;
                    ?>
                    <div class="cart-item">
                        <div class="d-flex align-items-center">
                            <div class="position-relative">
                                <?php 
                                $image_url = !empty($item['image_url']) ? '../' . $item['image_url'] : 'https://via.placeholder.com/60x60/6f4e37/ffffff?text=' . urlencode(substr($item['name'], 0, 5));
                                ?>
                                <img src="<?php echo $image_url; ?>" alt="<?php echo $item['name']; ?>" 
                                     class="cart-item-image"
                                     onerror="this.src='https://via.placeholder.com/60x60/6f4e37/ffffff?text=No+Image'">
                                <span class="quantity-badge"><?php echo $item['quantity']; ?></span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo $item['name']; ?></h6>
                                        <small class="text-muted">
                                            ₱<?php echo number_format($item['price'], 2); ?> each
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="fw-bold">₱<?php echo number_format($item_total, 2); ?></span>
                                        <div class="mt-1">
                                            <a href="?remove_item=<?php echo $index; ?>" class="text-danger btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i> Remove
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Cart Total -->
            <div class="cart-total">
                <?php
                $display_subtotal = isset($cart_subtotal) ? $cart_subtotal : $subtotal;
                $discount = 0;
                $discount_type = $_POST['discount_type'] ?? 'none';
                
                if (($discount_type === 'senior' || $discount_type === 'pwd') && $display_subtotal > 0) {
                    $discount = $display_subtotal * 0.20;
                }
                $total = $display_subtotal - $discount;
                ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span>₱<?php echo number_format($display_subtotal, 2); ?></span>
                </div>
                <?php if ($discount > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>
                        <i class="fas fa-tag me-1"></i>
                        Discount (20% <?php echo strtoupper($discount_type); ?>):
                    </span>
                    <span class="text-warning fw-bold">-₱<?php echo number_format($discount, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between fw-bold fs-5 mt-3 pt-3 border-top">
                    <span>Total Amount:</span>
                    <span>₱<?php echo number_format($total, 2); ?></span>
                </div>
                
                <div class="mt-4">
                    <button type="submit" name="place_order" class="btn btn-success w-100 py-3" 
                            <?php echo empty($_SESSION['cart']) ? 'disabled' : ''; ?>>
                        <i class="fas fa-check-circle me-2"></i> 
                        <strong>PLACE ORDER & PRINT RECEIPT</strong>
                    </button>
                    
                    <?php if (!empty($_SESSION['cart'])): ?>
                    <div class="text-center mt-2">
                        <a href="?clear_cart=true" class="text-danger btn btn-sm btn-link" onclick="return confirm('Clear all items from cart?')">
                            <i class="fas fa-trash me-1"></i> Clear Cart
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            </form>
            
            <!-- Quick Actions -->
            <div class="mt-3">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="../dashboard.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="orders.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-receipt me-1"></i> Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update total when discount changes
        document.querySelectorAll('input[name="discount_type"]').forEach(radio => {
            radio.addEventListener('change', () => {
                document.getElementById('orderForm').submit();
            });
        });
        
        // Search functionality
        document.getElementById('searchMenu').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const menuCards = document.querySelectorAll('.menu-item-card');
            
            menuCards.forEach(card => {
                const itemName = card.querySelector('.item-name').textContent.toLowerCase();
                if (itemName.includes(searchTerm)) {
                    card.parentElement.style.display = 'block';
                } else {
                    card.parentElement.style.display = 'none';
                }
            });
        });
        
        // Clear cart confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const clearCartLinks = document.querySelectorAll('a[href*="clear_cart"]');
            clearCartLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to clear all items from the cart?')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Auto-focus on customer name field when cart has items
            <?php if (!empty($_SESSION['cart'])): ?>
            document.getElementById('customer_name').focus();
            <?php endif; ?>
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Add hover effects to menu items
        document.querySelectorAll('.menu-item-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
    
    <!-- Handle clear cart -->
    <?php
    if (isset($_GET['clear_cart']) && $_GET['clear_cart'] === 'true') {
        $_SESSION['cart'] = [];
        echo '<script>window.location.href = "index.php";</script>';
        exit();
    }
    ?>
</body>
</html>