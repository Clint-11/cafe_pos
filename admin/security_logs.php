<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get security logs
$sql = "SELECT TOP 100 sl.*, u.username, u.fullname 
        FROM security_logs sl 
        JOIN users u ON sl.user_id = u.id 
        ORDER BY sl.created_at DESC";
$stmt = executeQuery($sql);
$logs = fetchAll($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Logs - Sip Happens</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        <h2 class="mb-4">Security Logs</h2>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">System Activity Logs</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): 
                                $action_badge = '';
                                switch ($log['action_type']) {
                                    case 'login':
                                        $action_badge = 'success';
                                        break;
                                    case 'logout':
                                        $action_badge = 'secondary';
                                        break;
                                    case 'order_placed':
                                        $action_badge = 'primary';
                                        break;
                                    case 'menu_add':
                                    case 'menu_edit':
                                        $action_badge = 'warning';
                                        break;
                                    case 'menu_delete':
                                        $action_badge = 'danger';
                                        break;
                                    default:
                                        $action_badge = 'info';
                                }
                            ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at']->format('Y-m-d H:i:s'))); ?></td>
                                <td>
                                    <strong><?php echo $log['username']; ?></strong><br>
                                    <small class="text-muted"><?php echo $log['fullname']; ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $action_badge; ?>">
                                        <?php echo str_replace('_', ' ', $log['action_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $log['description']; ?></td>
                                <td><small class="text-muted"><?php echo $log['ip_address'] ?: 'N/A'; ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No security logs found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Activity Summary -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Activity Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get activity counts
                        $sql = "SELECT action_type, COUNT(*) as count 
                                FROM security_logs 
                                WHERE CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)
                                GROUP BY action_type 
                                ORDER BY count DESC";
                        $stmt = executeQuery($sql);
                        $activities = fetchAll($stmt);
                        ?>
                        <ul class="list-group">
                            <?php if (count($activities) > 0): ?>
                            <?php foreach ($activities as $activity): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo ucfirst(str_replace('_', ' ', $activity['action_type'])); ?>
                                <span class="badge bg-primary rounded-pill"><?php echo $activity['count']; ?></span>
                            </li>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <li class="list-group-item text-center text-muted">No activities today.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Most Active Users</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get most active users
                        $sql = "SELECT TOP 5 u.username, u.fullname, COUNT(sl.id) as activity_count 
                                FROM security_logs sl 
                                JOIN users u ON sl.user_id = u.id 
                                WHERE CAST(sl.created_at AS DATE) = CAST(GETDATE() AS DATE)
                                GROUP BY u.username, u.fullname 
                                ORDER BY activity_count DESC";
                        $stmt = executeQuery($sql);
                        $active_users = fetchAll($stmt);
                        ?>
                        <ul class="list-group">
                            <?php if (count($active_users) > 0): ?>
                            <?php foreach ($active_users as $user): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo $user['username']; ?></strong><br>
                                    <small class="text-muted"><?php echo $user['fullname']; ?></small>
                                </div>
                                <span class="badge bg-success rounded-pill"><?php echo $user['activity_count']; ?></span>
                            </li>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <li class="list-group-item text-center text-muted">No user activities today.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>