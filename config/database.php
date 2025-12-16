<?php
$serverName = "Clint\\SQLEXPRESS";
$connectionInfo = array(
    "Database" => "SipHappens",
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

function executeQuery($sql, $params = array()) {
    global $conn;
    $stmt = sqlsrv_query($conn, $sql, $params);
    return $stmt;
}

function fetchAll($stmt) {
    $rows = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

function fetchSingle($stmt) {
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}
?>