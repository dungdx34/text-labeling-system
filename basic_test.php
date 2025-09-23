<?php
// Basic PHP test
echo "PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current time: " . date('Y-m-d H:i:s') . "<br>";

// Test database connection
echo "<hr>";
echo "Testing database connection...<br>";

try {
    $host = 'localhost';
    $dbname = 'text_labeling_system';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    echo "✓ Database connected successfully<br>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "✓ Users table accessible, count: $count<br>";
    
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

// Test session
echo "<hr>";
echo "Testing session...<br>";
session_start();
$_SESSION['test'] = 'session_working';
echo "✓ Session started<br>";
echo "Session ID: " . session_id() . "<br>";

// Test file operations
echo "<hr>";
echo "Testing file operations...<br>";
if (is_writable('.')) {
    echo "✓ Directory is writable<br>";
} else {
    echo "✗ Directory is not writable<br>";
}

// Test PHP configuration
echo "<hr>";
echo "PHP Configuration:<br>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'On' : 'Off') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";

// Test if other files exist
echo "<hr>";
echo "Testing file structure...<br>";
$files = [
    'config/database.php',
    'includes/auth.php', 
    'admin/upload.php',
    'admin/dashboard.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists<br>";
    } else {
        echo "✗ $file missing<br>";
    }
}

// Test form processing
if ($_POST) {
    echo "<hr>";
    echo "POST data received:<br>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
}

if ($_FILES) {
    echo "<hr>";
    echo "FILES data received:<br>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
}
?>

<hr>
<h3>Simple Tests</h3>

<form method="POST" style="margin: 10px 0;">
    <input type="text" name="test_input" placeholder="Type something...">
    <button type="submit">Test POST</button>
</form>

<form method="POST" enctype="multipart/form-data" style="margin: 10px 0;">
    <input type="file" name="test_file">
    <button type="submit">Test File Upload</button>
</form>

<hr>
<div>
    <a href="admin/dashboard.php">Go to Dashboard</a> |
    <a href="admin/upload.php">Go to Upload</a> |
    <a href="login.php">Go to Login</a>
</div>