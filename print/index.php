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

date_default_timezone_set ("Africa/Addis_Ababa");

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ERROR | E_PARSE);

require __DIR__ . '/vendor/autoload.php';

use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

try {
    include('../config/config.php');

    $conn = get_connection();
    $conn->query("use $db;");
    
    $stmt = $conn->prepare("SELECT branch_id, printer_type, printer, printer_ip, printer_port, logo_file_name, company_name, domain_name FROM $table WHERE id = 1");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $conn->close();
    
    define("BRANCHID", $row['branch_id']);
    define("PRINTERTYPE", $row['printer_type']);
    define("PRINTER", $row['printer']);
    define("PRINTERIP", $row['printer_ip']);
    define("PRINTERPORT", $row['printer_port']);
    define("LOGO", $row['logo_file_name']);
    define("COMPANY_NAME", $row['company_name']);
    define("DOMAIN_NAME", $row['domain_name']);
} catch (\Exception $e) {
    header('Content-Type: application/json');
    header('Status: 400 Bad Request');
    $response = array("success" => false, "message" => "ERROR: " . $e -> getMessage() . " Complete configuration @ localhost/config first");
    exit(json_encode($response));
}

$data = file_get_contents('php://input');

$json = json_decode($data);

if(isset($json->game_picks)) {
    $response = do_print_legacy($json);
} else {
    $MAX_LEN = $json->meta->paper_size;
    $SPACING = $json->meta->linespace;
    $FONT = $json->meta->font;
    $COLOR = $json->meta->color;
    
    $response = do_print($json);
}

header('Content-Type: application/json');
header('Status: 200 OK');
exit(json_encode($response));

function do_print_legacy($bet) {
    $response = array("success" => true, "message" => "Successfully Printed");

    $MAX_LEN = 48;
    $double_line = "";
    for ($i=0;$i<$MAX_LEN;$i++) {
        $double_line .= "=";
    }
    $single_line = "";
    for ($i=0;$i<$MAX_LEN;$i++) {
        $single_line .= "-";
    }

    try {
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

        try {
            $printer->setColor(Printer::COLOR_2);
            $printer->setFont(Printer::FONT_C);
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);

            $printer->setJustification(Printer::JUSTIFY_CENTER);

            try {
                $logo = (null !== LOGO) ? LOGO : "logo.png";
                $img = EscposImage::load($logo);
                $printer->bitImage($img);
            } catch (\Exception $e) {
                $printer -> text("********\n");
            }
            $printer->feed(1);

            $printer->text("".COMPANY_NAME."\n");
            $printer->text("Receipt - ".$bet->ticketID."\n");
            $printer->text("Coupon - ".$bet->couponID."\n");
            
            $today = date('d-m-y H:i');
            $printer->text($today.get_white_space($MAX_LEN - strlen($today.BRANCHID)).BRANCHID."\n");
            $printer->setLineSpacing(1);
            $printer->text("$double_line\n");
            
            foreach($bet->game_picks as $detail) {
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->text($detail->league."\n");
                $printer->text($detail->match."\n");

                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $market = $detail->game;
                $pick = $detail->gamepick;
                $printer->text($market.get_white_space($MAX_LEN - strlen($market.$pick)).$pick."\n");

                $game_date = date('d-m-y H:i', strtotime($detail->schedule));
                $odd_line = number_format($detail->odd, 2);
                $printer->text($game_date.get_white_space($MAX_LEN - strlen($game_date.$odd_line)).$odd_line."\n");
                $printer->text("$single_line\n");
            }

            $bets_line = "BETS: ".count($bet->game_picks);
            $odd_line = "ODD: ".number_format($bet->total_odds, 2);
            $printer->text($bets_line.get_white_space($MAX_LEN - strlen($bets_line.$odd_line)).$odd_line."\n");

            $amount_line = "STAKE: ".number_format($bet->stake, 2);
            $tot_line = "TOT: ".number_format($bet->tot, 2);
            $printer->text($amount_line.get_white_space($MAX_LEN - strlen($amount_line.$tot_line)).$tot_line."\n");

            $win_line = "WINNING: ".number_format($bet->max_win, 2);
            $tax_line = "TAX: ".number_format($bet->win_tax, 2);
            $printer->text($win_line.get_white_space($MAX_LEN - strlen($win_line.$tax_line)).$tax_line."\n");

            $printer->feed(1);

            $net_pay_line = "NET PAY ";
            $net_pay_amount_line = number_format($bet->net_pay, 2)." ".CURRENCY;
            $printer->text($net_pay_line.get_white_space($MAX_LEN - strlen($net_pay_line.$net_pay_amount_line)).$net_pay_amount_line."\n");

            $printer->text("$double_line\n");
            $printer->text("*** All bets after kikk-off are Invalid *** \n");
            $printer->feed(1);

            // $tel_line = "Telephone";
            // $tel = "+251-118-932131";
            // $printer->text($tel_line.get_white_space($MAX_LEN - strlen($tel_line.$tel)).$tel."\n");

            // $mobile_line = "Mobile";
            // $mobile = "+251-964-858585";
            // $printer->text($mobile_line.get_white_space($MAX_LEN - strlen($mobile_line.$mobile)).$mobile."\n");

            $printer->text("".DOMAIN_NAME."\n");
            $printer->text("$single_line\n");
            $printer->text("BY ETHIOPIANS FOR ETHIOPIANS\n");
            $printer->text("$single_line\n");
            $printer->setBarcodeHeight(30);
            $printer->setBarcodeWidth(2);
            if(isset($bet->ticketID))
                $printer->barcode($bet->ticketID, Printer::BARCODE_CODE39);
        } catch (\Exception $e) {
            $printer -> text($e -> getMessage() . "\n");
            $response = array("success" => false, "message" => "ERROR: " . $e -> getMessage());
        } finally {
            $printer->feed(1);
            $printer->cut();
            $printer->close();
        } 
    } catch (\Exception $e) {
        $response = array("success" => false, "message" => "ERROR: " . $e -> getMessage());
    }

    return $response;
}

function do_print($json) {
    $response = array("success" => true, "message" => "Successfully Printed");

    try {
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

        try {
            $printer->setColor(Printer::COLOR_1);
            $printer->setFont(Printer::FONT_A);
            $printer->selectPrintMode(Printer::MODE_FONT_A);
            $printer->setLineSpacing(1);

            $spacing = $GLOBALS['SPACING'];
            if ($spacing > 1)
                $printer->setLineSpacing($spacing);

            $color = $GLOBALS['COLOR'];
            if ($color == 2)
                $printer->setColor(Printer::COLOR_2);

            $font = $GLOBALS['FONT'];
            if ($font == 'B') {
                $printer->setFont(Printer::FONT_B);
                $printer->selectPrintMode(Printer::MODE_FONT_B);
            }
            else if ($font == 'C')
                $printer->setFont(Printer::FONT_C);
                
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            try {
                $logo = (null !== LOGO) ? LOGO : "logo.png";
                $img = EscposImage::load($logo);
                $printer->bitImage($img);
            } catch (\Exception $e) {
                try {
                    $img = EscposImage::load("logo.png");
                    $printer->bitImage($img);
                } catch (\Exception $e) {
                    $printer -> text("********\n");
                }
            }
            $printer->feed(1);

            $data = $json->data;

            escpos_print($data->header, $printer);
            escpos_print($data->body, $printer);
            
            $MAX_LEN = $GLOBALS['MAX_LEN'];
            $i = 0;
            if (isset($data->body->gamepicks)) {
                foreach($data->body->gamepicks as $detail) {
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer->text($detail->league."\n");
                    $printer->text($detail->match."\n");

                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                    $market = $detail->game;
                    $pick = $detail->gamepick;
                    $printer->text($market.get_white_space($MAX_LEN - strlen($market.$pick)).$pick."\n");

                    $game_date = date('d-m-y H:i', strtotime($detail->schedule));
                    $odd_line = number_format($detail->odd, 2);
                    $printer->text($game_date.get_white_space($MAX_LEN - strlen($game_date.$odd_line)).$odd_line."\n");

                    if (++$i < count($data->body->gamepicks)) {
                        $text = get_single_line()."\n";
                        $printer->text($text);
                    }
                }
            }
            
            escpos_print($data->footer, $printer);
            
        } catch (\Exception $e) {
            $response = array("success" => false, "message" => "ERROR: " . $e -> getMessage());
        } finally {
            $printer->feed(1);
            $printer->cut();
            $printer->close();
        } 
    } catch (\Exception $e) {
        $response = array("success" => false, "message" => "ERROR: " . $e -> getMessage());
    }

    return $response;
}

function escpos_print($data, $printer) {
    foreach ($data->rows as $row) {
        $MAX_LEN = $GLOBALS['MAX_LEN'];
        $printer->selectPrintMode(Printer::MODE_FONT_A);
        $font = $GLOBALS['FONT'];
        if ($font == 'B')
            $printer->selectPrintMode(Printer::MODE_FONT_B);

        $type = $row->type;
        if ($type == "data") {
            if ($row->meta->emphasis == "yes")
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
    
            if ($row->meta->mode == "doubleheight")
                $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
            else if ($row->meta->mode == "doublewidth" || $row->meta->mode == "doublewidthheight") {
                $MAX_LEN = $GLOBALS['MAX_LEN'] / 2;
                $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
                if ($row->meta->mode == "doublewidthheight") {
                    $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
                }
            }

            $columns = $row->columns;
            $line = "";
            $size = count($columns);

            foreach ($columns as $c) {
                $line .= $c->value;
            }
            $space = $MAX_LEN - strlen($line);
            $spacing = $size == 1 ? $space : $space/($size - 1);
            $text = "";
            for ($i=0; $i<$size; $i++) {
                $text .= $columns[$i]->value;
                if ($i < $size - 1)
                    $text .= get_white_space($spacing);
                else 
                    $text .= "\n";
            }

            $printer->text($text);
        }
        elseif ($type == "whitespacebreak")
            $printer->feed(1);
        elseif ($type == "linebreak") {
            $text = get_single_line()."\n";
            $printer->text($text);
        }
        elseif ($type == "doublelinebreak") {
            $text = get_double_line()."\n";
            $printer->text($text);
        }
        elseif ($type == "branchreceipt") {
            $text = BRANCHID.": ".$row->receipt."\n";
            $printer->text($text);
        }
        elseif ($type == "datebranch") {
            $today = date('d-m-y H:i');
            $text = $today.get_white_space($MAX_LEN - strlen($today.BRANCHID)).BRANCHID."\n";
            $printer->text($text);
        }
        elseif ($type == "date") {
            $today = date('d-m-y');
            $printer->text($today."\n");
        }
        elseif ($type == "dateminute") {
            $today = date('d-m-y H:i');
            $printer->text($today."\n");
        }
        elseif ($type == "datesecond") {
            $today = date('d-m-y H:i:s');
            $printer->text($today."\n");
        }
        elseif ($type == "barcode") {
            $printer->setBarcodeHeight($row->meta->height);
            $printer->setBarcodeWidth($row->meta->width);

            if (isset($row->meta->value))
                $printer->barcode($row->meta->value, Printer::BARCODE_CODE39);
        }
    }
}

function get_single_line() {
    $MAX_LEN = $GLOBALS['MAX_LEN'];
    $single_line = "";
    for ($i = 0; $i < $MAX_LEN; $i++) {
        $single_line .= "-";
    }
    return $single_line;
}

function get_double_line() {
    $MAX_LEN = $GLOBALS['MAX_LEN'];
    $double_line = "";
    for ($i = 0; $i < $MAX_LEN; $i++) {
        $double_line .= "=";
    }
    return $double_line;
}

function get_white_space($len) {
    $space="";
    for($i=0; $i<$len; $i++) {
        $space .= " ";
    }
    return $space;
}
