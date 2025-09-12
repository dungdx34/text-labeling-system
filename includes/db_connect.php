<?php
// includes/db_connect.php - Database connection file

// Database configuration
$db_host = 'localhost';        // Database host
$db_username = 'root';         // Database username (default for XAMPP)
$db_password = '';             // Database password (empty for XAMPP)
$db_name = 'text_labeling_db'; // Database name

// Create connection
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8 for Vietnamese support
$conn->set_charset("utf8mb4");

// Optional: Set timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Database connection success (for debugging - remove in production)
// echo "Connected successfully to database: " . $db_name;
?>
