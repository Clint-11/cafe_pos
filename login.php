<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = executeQuery($sql, array($username));
    $user = fetchSingle($stmt);
    
    // SIMPLE PASSWORD CHECK - NO HASHING
    if ($user && $password === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['fullname'] = $user['fullname'];
        
        // Record login time for cashier
        if ($user['role'] === 'cashier') {
            $sql = "INSERT INTO cashier_shifts (cashier_id) VALUES (?)";
            executeQuery($sql, array($user['id']));
        }
        
        // Record security log
        $sql = "INSERT INTO security_logs (user_id, action_type, description) VALUES (?, ?, ?)";
        executeQuery($sql, array($user['id'], 'login', 'User logged in'));
        
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sip Happens POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
                        url('https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo-container img {
            height: 80px;
        }
        .home-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        .tagline {
            font-style: italic;
            color: #6f4e37;
            margin-bottom: 20px;
        }
        .login-header {
            background: #6f4e37;
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
            margin: -20px -20px 20px -20px;
        }
    </style>
</head>
<body>
    <!-- Back to Home Button -->
    <a href="index.php" class="home-btn btn btn-outline-light">
        <i class="fas fa-home me-2"></i> Back to Home
    </a>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card p-4">
                    <!-- Login Header -->
                    <div class="login-header text-center">
                        <div class="logo-container">
                            <img src="assets/images/logo.png" alt="Logo" onerror="this.src='https://via.placeholder.com/150x50/6f4e37/ffffff?text=Sip+Happens'">
                            <h3 class="mt-3 mb-0">Sip Happens POS</h3>
                            <p class="tagline mb-0">When life spills, we refill</p>
                        </div>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="fas fa-user me-1"></i> Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   placeholder="Enter your username">
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-1"></i> Password
                            </label>
                            <input type="password" class="form-control" id="password" name="password" required 
                                   placeholder="Enter your password">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-3">
                            <i class="fas fa-sign-in-alt me-2"></i> Login to POS System
                        </button>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <hr>
                        <h6 class="text-muted mb-3">Demo Credentials</h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <div class="card border-primary">
                                    <div class="card-body p-2">
                                        <small class="text-primary">
                                            <i class="fas fa-user-shield me-1"></i> <strong>Admin</strong><br>
                                            Username: admin<br>
                                            Password: admin123
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="card border-info">
                                    <div class="card-body p-2">
                                        <small class="text-info">
                                            <i class="fas fa-user-tie me-1"></i> <strong>Cashier</strong><br>
                                            Username: cashier1<br>
                                            Password: cashier123
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Return to Homepage
                        </a>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="mt-3 text-center text-white">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        Coffee Shop Point of Sale System v1.0
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
            
            // Enter key submits form
            document.getElementById('password').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.querySelector('form').submit();
                }
            });
        });
    </script>
</body>
</html>