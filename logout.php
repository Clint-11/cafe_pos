<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    // Record logout time for cashier
    if ($_SESSION['role'] === 'cashier') {
        $sql = "UPDATE cashier_shifts SET time_out = GETDATE() 
                WHERE cashier_id = ? AND time_out IS NULL";
        executeQuery($sql, array($_SESSION['user_id']));
    }
    
    // Record security log
    $sql = "INSERT INTO security_logs (user_id, action_type, description) VALUES (?, ?, ?)";
    executeQuery($sql, array($_SESSION['user_id'], 'logout', 'User logged out'));
}

session_destroy();
header("Location: login.php");
exit();
?>