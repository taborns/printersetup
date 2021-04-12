<?php
// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}

try {
    include('../config/config.php');
    
    $conn = get_connection();
    $conn->query("use $db;");
    
    $stmt = $conn->prepare("SELECT signature FROM $table WHERE id = 1");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $conn->close();
    
    echo $row['signature'];
} catch (\Exception $e) {
    echo "Branch Signature not configured goto localhost/config";
}

?>
