<?php
// Database connection configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'text_labeling_system'; // ← Sửa tên database ở đây

try {
    // Create MySQLi connection
    $mysqli = new mysqli($host, $username, $password, $database);
    
    // Check connection
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    // Set charset to utf8mb4
    $mysqli->set_charset("utf8mb4");
    
    // For backward compatibility, also create PDO connection
    $pdo = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    // Success - database connected
    // echo "Database connected successfully"; // Uncomment for testing
    
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}
?>