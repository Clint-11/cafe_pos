<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle user actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $fullname = $_POST['fullname'];
        $role = $_POST['role'];
        
        // Check if username already exists
        $sql_check = "SELECT id FROM users WHERE username = ?";
        $stmt_check = executeQuery($sql_check, array($username));
        $existing_user = fetchSingle($stmt_check);
        
        if ($existing_user) {
            $message = "Username already exists!";
            $message_type = 'danger';
        } else {
            // Insert new user
            $sql = "INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)";
            $stmt = executeQuery($sql, array($username, $password, $fullname, $role));
            
            if ($stmt) {
                // Log the action
                $sql_log = "INSERT INTO security_logs (user_id, action_type, description) VALUES (?, 'user_add', ?)";
                executeQuery($sql_log, array($_SESSION['user_id'], "Added new user: $username ($role)"));
                
                $message = "User added successfully!";
                $message_type = 'success';
            } else {
                $message = "Failed to add user.";
                $message_type = 'danger';
            }
        }
    }
    
    if (isset($_POST['update_user'])) {
        $user_id = $_POST['user_id'];
        $username = $_POST['username'];
        $fullname = $_POST['fullname'];
        $role = $_POST['role'];
        $password = $_POST['password'];
        
        if (!empty($password)) {
            // Update with password
            $sql = "UPDATE users SET username = ?, password = ?, fullname = ?, role = ? WHERE id = ?";
            $stmt = executeQuery($sql, array($username, $password, $fullname, $role, $user_id));
        } else {
            // Update without changing password
            $sql = "UPDATE users SET username = ?, fullname = ?, role = ? WHERE id = ?";
            $stmt = executeQuery($sql, array($username, $fullname, $role, $user_id));
        }
        
        if ($stmt) {
            // Log the action
            $sql_log = "INSERT INTO security_logs (user_id, action_type, description) VALUES (?, 'user_edit', ?)";
            executeQuery($sql_log, array($_SESSION['user_id'], "Updated user: $username ($role)"));
            
            $message = "User updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Failed to update user.";
            $message_type = 'danger';
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        
        // Get user info before deleting
        $sql_info = "SELECT username, role FROM users WHERE id = ?";
        $stmt_info = executeQuery($sql_info, array($user_id));
        $user_info = fetchSingle($stmt_info);
        
        // Prevent deleting yourself
        if ($user_id == $_SESSION['user_id']) {
            $message = "You cannot delete your own account!";
            $message_type = 'danger';
        } else {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = executeQuery($sql, array($user_id));
            
            if ($stmt) {
                // Log the action
                $sql_log = "INSERT INTO security_logs (user_id, action_type, description) VALUES (?, 'user_delete', ?)";
                executeQuery($sql_log, array($_SESSION['user_id'], "Deleted user: " . $user_info['username'] . " (" . $user_info['role'] . ")"));
                
                $message = "User deleted successfully!";
                $message_type = 'success';
            } else {
                $message = "Failed to delete user.";
                $message_type = 'danger';
            }
        }
    }
}

// Get all users
$sql = "SELECT * FROM users ORDER BY role, username";
$stmt = executeQuery($sql);
$users = fetchAll($stmt);

// Get user counts
$sql_counts = "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
                SUM(CASE WHEN role = 'cashier' THEN 1 ELSE 0 END) as cashier_count
               FROM users";
$stmt_counts = executeQuery($sql_counts);
$user_counts = fetchSingle($stmt_counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-card {
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: 100%;
        }
        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .admin-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #6f4e37;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .role-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .table-actions {
            white-space: nowrap;
        }
        .nav-tabs .nav-link.active {
            background: #6f4e37;
            color: white;
            border-color: #6f4e37;
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
        <h2 class="mb-4">Admin Panel - User Management</h2>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Users</h5>
                        <h2><?php echo $user_counts['total_users'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <h5 class="card-title">Admins</h5>
                        <h2><?php echo $user_counts['admin_count'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-info">
                    <div class="card-body text-center">
                        <h5 class="card-title">Cashiers</h5>
                        <h2><?php echo $user_counts['cashier_count'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Management Tabs -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="userTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="view-tab" data-bs-toggle="tab" 
                                data-bs-target="#view-users" type="button">
                            <i class="fas fa-users me-2"></i> View Users
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="add-tab" data-bs-toggle="tab" 
                                data-bs-target="#add-user" type="button">
                            <i class="fas fa-user-plus me-2"></i> Add New User
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body">
                <div class="tab-content" id="userTabsContent">
                    
                    <!-- Tab 1: View Users -->
                    <div class="tab-pane fade show active" id="view-users">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3">
                                                    <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['fullname']); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $user['role'] == 'admin' ? 'bg-danger' : 'bg-primary'; ?> role-badge">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if (isset($user['created_at']) && is_object($user['created_at'])) {
                                                echo date('Y-m-d', strtotime($user['created_at']->format('Y-m-d H:i:s')));
                                            } else {
                                                echo date('Y-m-d', strtotime($user['created_at']));
                                            }
                                            ?>
                                        </td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                                    data-bs-target="#deleteUserModal<?php echo $user['id']; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled>
                                                <i class="fas fa-user"></i> Current
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <!-- Edit User Modal -->
                                    <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit User</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Full Name</label>
                                                            <input type="text" class="form-control" name="fullname" 
                                                                   value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Username</label>
                                                            <input type="text" class="form-control" name="username" 
                                                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">New Password (Leave blank to keep current)</label>
                                                            <input type="password" class="form-control" name="password" 
                                                                   placeholder="Enter new password">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Role</label>
                                                            <select class="form-select" name="role" required>
                                                                <option value="cashier" <?php echo $user['role'] == 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                                                                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_user" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Delete User Modal -->
                                    <div class="modal fade" id="deleteUserModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <p>Are you sure you want to delete user <strong><?php echo htmlspecialchars($user['fullname']); ?></strong>?</p>
                                                        <p class="text-danger">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            This action cannot be undone. All user data will be permanently deleted.
                                                        </p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No users found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Tab 2: Add New User -->
                    <div class="tab-pane fade" id="add-user">
                        <div class="row">
                            <div class="col-md-8 mx-auto">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">Add New User</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" class="form-control" name="fullname" required 
                                                       placeholder="Enter full name">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Username</label>
                                                <input type="text" class="form-control" name="username" required 
                                                       placeholder="Enter username">
                                                <small class="text-muted">This will be used for login</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Password</label>
                                                <input type="password" class="form-control" name="password" required 
                                                       placeholder="Enter password">
                                                <small class="text-muted">Use a strong password</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Confirm Password</label>
                                                <input type="password" class="form-control" id="confirm_password" required 
                                                       placeholder="Confirm password">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Role</label>
                                                <select class="form-select" name="role" required>
                                                    <option value="">Select Role</option>
                                                    <option value="cashier">Cashier</option>
                                                    <option value="admin">Admin</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                                    <label class="form-check-label" for="terms">
                                                        I confirm that I have permission to create this user account
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" name="add_user" class="btn btn-primary w-100">
                                                <i class="fas fa-user-plus me-2"></i> Add User
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Default Users Info -->
                                <div class="card mt-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0">Default Users (For Testing)</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Username</th>
                                                    <th>Password</th>
                                                    <th>Role</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>admin</td>
                                                    <td>admin123</td>
                                                    <td><span class="badge bg-danger">Admin</span></td>
                                                </tr>
                                                <tr>
                                                    <td>cashier1</td>
                                                    <td>cashier123</td>
                                                    <td><span class="badge bg-primary">Cashier</span></td>
                                                </tr>
                                                <tr>
                                                    <td>cashier2</td>
                                                    <td>cashier123</td>
                                                    <td><span class="badge bg-primary">Cashier</span></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Access Cards -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card admin-card text-center p-4">
                    <i class="fas fa-utensils text-primary"></i>
                    <h5>Menu Management</h5>
                    <a href="menu_management.php" class="btn btn-primary btn-sm">Go to Menu</a>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card admin-card text-center p-4">
                    <i class="fas fa-chart-bar text-success"></i>
                    <h5>Sales Reports</h5>
                    <a href="reports.php" class="btn btn-success btn-sm">View Reports</a>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card admin-card text-center p-4">
                    <i class="fas fa-shield-alt text-warning"></i>
                    <h5>Security Logs</h5>
                    <a href="security_logs.php" class="btn btn-warning btn-sm">View Logs</a>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card admin-card text-center p-4">
                    <i class="fas fa-cog text-dark"></i>
                    <h5>System Settings</h5>
                    <button class="btn btn-dark btn-sm" onclick="alert('Coming soon!')">Settings</button>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent User Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get recent user-related logs
                                    $sql_logs = "SELECT TOP 10 sl.*, u.username, u.fullname 
                                                FROM security_logs sl 
                                                JOIN users u ON sl.user_id = u.id 
                                                WHERE sl.action_type LIKE 'user_%' 
                                                ORDER BY sl.created_at DESC";
                                    $stmt_logs = executeQuery($sql_logs);
                                    $logs = fetchAll($stmt_logs);
                                    
                                    if (count($logs) > 0):
                                        foreach ($logs as $log):
                                            $time_ago = '';
                                            $log_time = strtotime($log['created_at']->format('Y-m-d H:i:s'));
                                            $time_diff = time() - $log_time;
                                            
                                            if ($time_diff < 60) {
                                                $time_ago = 'Just now';
                                            } elseif ($time_diff < 3600) {
                                                $time_ago = floor($time_diff / 60) . ' minutes ago';
                                            } elseif ($time_diff < 86400) {
                                                $time_ago = floor($time_diff / 3600) . ' hours ago';
                                            } else {
                                                $time_ago = floor($time_diff / 86400) . ' days ago';
                                            }
                                    ?>
                                    <tr>
                                        <td>
                                            <small class="text-muted"><?php echo $time_ago; ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo $log['username']; ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $action_badge = '';
                                            switch ($log['action_type']) {
                                                case 'user_add': $action_badge = 'success'; break;
                                                case 'user_edit': $action_badge = 'warning'; break;
                                                case 'user_delete': $action_badge = 'danger'; break;
                                                default: $action_badge = 'info';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $action_badge; ?>">
                                                <?php echo str_replace('user_', '', $log['action_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $log['description']; ?></td>
                                    </tr>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No recent activity</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.getElementById('confirm_password');
            const form = document.querySelector('form');
            
            if (password && confirmPassword && form) {
                form.addEventListener('submit', function(e) {
                    if (password.value !== confirmPassword.value) {
                        e.preventDefault();
                        alert('Passwords do not match!');
                        password.focus();
                    }
                });
            }
            
            // Auto-activate tabs from URL hash
            const hash = window.location.hash;
            if (hash) {
                const tabTrigger = document.querySelector(`[data-bs-target="${hash}"]`);
                if (tabTrigger) {
                    new bootstrap.Tab(tabTrigger).show();
                }
            }
        });
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>