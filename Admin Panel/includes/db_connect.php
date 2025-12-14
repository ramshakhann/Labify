<?php
// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Default XAMPP username
define('DB_PASSWORD', '');     // Default XAMPP password is empty
define('DB_NAME', 'labify');

// Attempt to connect to MySQL database
 $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . $conn->connect_error);
}
?>