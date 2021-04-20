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

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ERROR | E_PARSE);

require '../print/vendor/autoload.php';

use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

$response = array("success" => false, "message" => "Printer Test Failed");

try {
    include('../config/config.php');

    $conn = get_connection();
    $conn->query("use $db;");
    
    $stmt = $conn->prepare("SELECT printer_type, printer, printer_ip, printer_port FROM $table WHERE id = 1");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $conn->close();
    
    define("PRINTERTYPE", $row['printer_type']);
    define("PRINTER", $row['printer']);
    define("PRINTERIP", $row['printer_ip']);
    define("PRINTERPORT", $row['printer_port']);
    
    if(null !== PRINTERIP && null !== PRINTERPORT && PRINTERTYPE === 'NETWORK')
        $connector = new NetworkPrintConnector(PRINTERIP, PRINTERPORT); // Network
    elseif (null !== PRINTER && (PRINTERTYPE === 'WINDOWS'))    
        $connector = new WindowsPrintConnector(PRINTER); // Windows USB(Shared)/LPT printer
    elseif(PRINTERTYPE === 'LINUX')
        if  (null !== PRINTER)
            $connector = new FilePrintConnector("/dev/".PRINTER);  // Linux USB, Parallel,USB-Serial & Serial printer
        else
            $connector = new FilePrintConnector("/dev/usb/lp0");  // Linux USB printer

    $printer = new Printer($connector);
    $printer->close();

    $response = array("success" => true, "message" => "Printer Test Successful");
} catch (\Exception $e) {
    $response = array("success" => false, "message" => "ERROR: " . $e -> getMessage());
}

header('Content-Type: application/json');
header('Status: 200 OK');
exit(json_encode($response));
