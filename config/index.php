
<!DOCTYPE html>
<html>
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<style>
			body {
			  font-family: Arial, Helvetica, sans-serif;
			  background-color: white;
			}

			* {
			  box-sizing: border-box;
			}

			/* Add padding to containers */
			.container {
			  padding: 16px;
			  background-color: white;
			}

			/* Full-width input fields */
			input, select {
			  width: 100%;
			  padding: 15px;
			  margin: 5px 0 10px 0;
			  display: inline-block;
			  border: none;
			  background: #f1f1f1;
			}

			input:focus, select:focus {
			  background-color: #ddd;
			  outline: none;
			}

			/* Overwrite default styles of hr */
			hr {
			  border: 1px solid #f1f1f1;
			  margin-bottom: 25px;
			}

			/* Set a style for the submit button */
			.submitbtn {
			  background-color: #4CAF50;
			  color: white;
			  padding: 16px 20px;
			  margin: 8px 0;
			  border: none;
			  cursor: pointer;
			  width: 100%;
			  opacity: 0.9;
			}

			.submitbtn:hover {
			  opacity: 1;
			}

			/* Add a blue text color to links */
			a {
			  color: dodgerblue;
			}

			/* Add a red text color to spans */
			span {
			  color: red;
			}
		</style>
	</head>
	<body>
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

			$valid_passwords = array ("convex" => "ConvexP@ssw0rd", "hulu" => "HuluP@ssw0rd", "afro" => "AfroP@ssw0rd", "walya" => "WalyaP@ssw0rd", "vegas" => "VegasP@ssw0rd", "admin" => "AdminP@ssw0rd");
			$valid_users = array_keys($valid_passwords);

			$user = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
			$pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

			$validated = (in_array($user, $valid_users)) && ($pass == $valid_passwords[$user]);

			if (!$validated) {
			  header('WWW-Authenticate: Basic realm="My Realm"');
			  header('HTTP/1.0 401 Unauthorized');
			  die ("Not authorized");
			}

			include('config.php'); 
			$conn = get_connection(); 
			if(!$conn->query("CREATE DATABASE IF NOT EXISTS $db")){
			  die("Create Database failed: " . $conn->connect_error);
			}
			$conn->query("use $db;");
		?>
		<?php if (!empty($_POST)): ?>
			<?php
				$file_name = "logo.png";
				if(isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
					$errors= array();
					$file_name = $_FILES['image']['name'];
					$file_size = $_FILES['image']['size'];
					$file_tmp = $_FILES['image']['tmp_name'];
					$file_type = $_FILES['image']['type'];
					$file_ext=strtolower(end(explode('.', $_FILES['image']['name'])));
					
					$extensions= array("png");
					
					if(in_array($file_ext, $extensions)=== false){
						$errors[]="extension not allowed, please choose a PNG file.";
					}
					
					if($file_size > 2097152) {
						$errors[]='File size must be excately 2 MB';
					}
					
					if(empty($errors)==true) {
						$file_name = "".time()."_logo.png";
					   	move_uploaded_file($file_tmp, "../print/".$file_name);
					} else{
						echo "Logo not saved"."<br>";
					   	foreach($errors as $e)
					    	echo $e."<br>";
					}
				 }

				$conn->query("DROP TABLE IF EXISTS $table");
				$conn->query("CREATE TABLE $table (id INT, branch_id TEXT, signature TEXT, printer_type TEXT, printer TEXT, printer_ip TEXT, printer_port TEXT, logo_file_name TEXT)");
				$conn->query("INSERT INTO $table(id, branch_id, signature, printer_type, printer, printer_ip, printer_port, logo_file_name) VALUES (1, '".$_POST['branch_id']."', '".$_POST['signature']."', '".$_POST['printer_type']."', '".$_POST['printer']."', '".$_POST['printer_ip']."', '".$_POST['printer_port']."', '".$file_name."')");
				$conn->close();

			?>

			<div class="container">
				<h1> </h1>
				<hr>
				<h1>Branch Configuration Completed!</h1>
				<hr>
			</div>
			<?php unset($_SERVER['PHP_AUTH_USER']); ?>
        <?php else: ?>
        	<?php
				try {
					$stmt = $conn->prepare("SELECT branch_id, signature, printer_type, printer, printer_ip, printer_port, logo_file_name FROM $table WHERE id = 1");
					$stmt->execute();
					$row = $stmt->get_result()->fetch_assoc();
				} catch (\Exception $e) {
				} finally {
					$conn->close();
				}
        	?>
			<form action="" method="post" enctype="multipart/form-data">
			  <div class="container">
			    <h1>Branch Configuration</h1>
			    <hr>
				<label for="image"><b>Company Logo </b><img src="<?php if(isset($row)) echo '../print/'.$row['logo_file_name']; ?>" alt="Choose a logo(.png) to upload"></label>
				<input type="file" accept="png"  placeholder="Choose a logo(.png) to upload" name="image" id="image" autocomplete="off">

			    <label for="branch_id"><b>Branch ID<span>*</span></b></label>
			    <input type="text" placeholder="BRANCH-1" name="branch_id" id="branch_id" value="<?php if(isset($row)) echo $row['branch_id']; ?>" autocomplete="off" required>

			    <label for="signature"><b>Branch Signature<span>*</span></b></label>
			    <input type="password" placeholder="eg: branch-1:YM2vJQPKtH0uaj8dlfFSJlsf7jlfg6X_VG4qJI6nOBg" name="signature" id="signature"  value="<?php if(isset($row)) echo $row['signature']; ?>" autocomplete="off" required>

			    <label for="printer_type"><b>Printer Type<span>*</span></b></label>
				<select name="printer_type" id="printer_type" value="<?php if(isset($row)) echo $row['printer_type']; ?>" required>
					<?php if(isset($row) && $row['printer_type']=='LINUX'): ?>
				    	<option selected value="LINUX">Linux</option>
					<?php else: ?>
						<option value="LINUX">Linux</option>
					<?php endif; ?>

					<?php if(isset($row) && $row['printer_type']=='WINDOWS'): ?>
				    	<option selected value="WINDOWS">Windows</option>
					<?php else: ?>
						<option value="WINDOWS">Windows</option>
					<?php endif; ?>

					<?php if(isset($row) && $row['printer_type']=='NETWORK'): ?>
				    	<option selected value="NETWORK">Network</option>
					<?php else: ?>
						<option value="NETWORK">Network</option>
					<?php endif; ?>
				</select>

			    <label for="printer"><b>Printer</b></label>
				<select name="printer" id="printer" value="<?php if(isset($row)) echo $row['printer']; ?>">
	  				<optgroup label="Linux">
						<?php if(isset($row) && $row['printer']=='usb/lp0'): ?>
							<option selected value="usb/lp0">Linux USB0</option>
						<?php else: ?>
							<option value="usb/lp0">Linux USB0</option>
						<?php endif; ?>
					    
						<?php if(isset($row) && $row['printer']=='usb/lp1'): ?>
							<option selected value="usb/lp1">Linux USB1</option>
						<?php else: ?>
							<option value="usb/lp1">Linux USB1</option>
						<?php endif; ?>
						
						<?php if(isset($row) && $row['printer']=='lp0'): ?>
							<option selected value="lp0">Linux Parallel0</option>
						<?php else: ?>
							<option value="lp0">Linux Parallel0</option>
						<?php endif; ?>
						
						<?php if(isset($row) && $row['printer']=='lp1'): ?>
							<option selected value="lp1">Linux Parallel1</option>
						<?php else: ?>
							<option value="lp1">Linux Parallel1</option>
						<?php endif; ?>
						
						<?php if(isset($row) && $row['printer']=='ttyUSB0'): ?>
							<option selected value="ttyUSB0">Linux USB-Serial0</option>
						<?php else: ?>
							<option value="ttyUSB0">Linux USB-Serial0</option>
						<?php endif; ?>
						
						<?php if(isset($row) && $row['printer']=='ttyUSB1'): ?>
							<option selected value="ttyUSB1">Linux USB-Serial1</option>
						<?php else: ?>
							<option value="ttyUSB1">Linux USB-Serial1</option>
						<?php endif; ?>
						
						<?php if(isset($row) && $row['printer']=='ttyS0'): ?>
							<option selected value="ttyS0">Linux Serial0</option>
						<?php else: ?>
							<option value="ttyS0">Linux Serial0</option>
						<?php endif; ?>
						
						<?php if(isset($row) && $row['printer']=='ttyS1'): ?>
							<option selected value="ttyS1">Linux Serial1</option>
						<?php else: ?>
							<option value="ttyS1">Linux Serial1</option>
						<?php endif; ?>
					</optgroup>
	  				<optgroup label="Windows">
						<?php if(isset($row) && $row['printer']=='POS80 Printer'): ?>
							<option selected value="POS80 Printer">Windows Shared Printer</option>
						<?php else: ?>
							<option value="POS80 Printer">Windows Shared Printer</option>
						<?php endif; ?>
						
						<?php if(isset($row) && $row['printer']=='LPT0'): ?>
							<option selected value="LPT0">Windows LPT0</option>
						<?php else: ?>
							<option value="LPT0">Windows LPT0</option>
						<?php endif; ?>
						
						<?php if(isset($row) && $row['printer']=='LPT1'): ?>
							<option selected value="LPT1">Windows LPT1</option>
						<?php else: ?>
							<option value="LPT1">Windows LPT1</option>
						<?php endif; ?>
					</optgroup>
				</select>

				<p><i>Below fields are Required if Network is selected as Printer Type </i></p> 

			    <label for="printer_ip"><b>Printer IP</b></label>
			    <input type="text" placeholder="192.X.X.X" name="printer_ip" id="printer_ip" value="<?php if(isset($row)) echo $row['printer_ip']; ?>">

			    <label for="printer_port"><b>Printer Port</b></label>
			    <input type="number" placeholder="eg: 9100" name="printer_port" id="printer_port" value="<?php if(isset($row)) echo $row['printer_port']; ?>">
			    <hr>
			    <input type="submit" class="submitbtn" value="Submit"/>
			  </div>
			</form>

		<?php endif; ?>
	</body>
</html>
