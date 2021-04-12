<?php
	$db = "branch_db";
	$table = "branch_config";
		
	function get_connection() {
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

		$conn = new mysqli("localhost", "root", "");  // Create connection
		if ($conn->connect_error) {  // Check connection
		  die("Connection failed: " . $conn->connect_error);
		}
		return $conn;	
	}
?>