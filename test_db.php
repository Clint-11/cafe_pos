<?php
// test_db.php - Test database connection
echo "<h2>Testing Database Connection</h2>";

$serverName = "Clint\\SQLEXPRESS";
$connectionInfo = array(
    "Database" => "SipHappens",
    "CharacterSet" => "UTF-8"
);

echo "Server: " . $serverName . "<br>";
echo "Database: SipHappens<br>";

$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn === false) {
    echo "<h3 style='color: red;'>Connection Failed!</h3>";
    echo "Errors:<br>";
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    
    // Try with Windows Authentication
    echo "<h3>Trying with Windows Authentication...</h3>";
    $connectionInfo2 = array(
        "Database" => "SipHappens",
        "CharacterSet" => "UTF-8",
        "TrustServerCertificate" => true,
        "Authentication" => "SqlPassword"
    );
    
    $conn2 = sqlsrv_connect($serverName, $connectionInfo2);
    if ($conn2 === false) {
        echo "Windows Auth also failed:<br>";
        echo "<pre>";
        print_r(sqlsrv_errors());
        echo "</pre>";
    } else {
        echo "<h3 style='color: green;'>Connected with Windows Auth!</h3>";
    }
} else {
    echo "<h3 style='color: green;'>Connection Successful!</h3>";
    
    // Test a simple query
    $sql = "SELECT COUNT(*) as table_count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        echo "Query failed:<br>";
        echo "<pre>";
        print_r(sqlsrv_errors());
        echo "</pre>";
    } else {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        echo "Number of tables in database: " . $row['table_count'] . "<br>";
        
        // List tables
        $sql2 = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME";
        $stmt2 = sqlsrv_query($conn, $sql2);
        
        echo "<h4>Tables in database:</h4>";
        echo "<ul>";
        while ($row2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
            echo "<li>" . $row2['TABLE_NAME'] . "</li>";
        }
        echo "</ul>";
    }
    
    sqlsrv_close($conn);
}
?>