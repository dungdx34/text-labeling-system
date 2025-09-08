<?php
// Simple test page
echo "<h1>✅ PHP is working!</h1>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Current directory: " . __DIR__ . "</p>";

// Test database
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
    try {
        $db = new Database();
        $conn = $db->getConnection();
        echo "<p>✅ Database connection: SUCCESS</p>";
    } catch (Exception $e) {
        echo "<p>❌ Database connection: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ config/database.php not found</p>";
}

// Test session
session_start();
echo "<p>✅ Session working, ID: " . session_id() . "</p>";

echo "<h3>Quick Links:</h3>";
echo "<a href='setup.php'>Setup</a> | ";
echo "<a href='debug.php'>Debug</a> | ";
echo "<a href='login.php'>Login</a>";
?>