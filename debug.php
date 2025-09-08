<?php
// Debug Script for Text Labeling System
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Text Labeling System Debug Tool</h2>";
echo "<style>body{font-family:Arial;} .ok{color:green;} .error{color:red;} .warning{color:orange;}</style>";

// 1. Check file structure
echo "<h3>📁 File Structure Check</h3>";
$required_files = [
    'config/database.php',
    'includes/auth.php', 
    'includes/functions.php',
    'includes/header.php',
    'admin/dashboard.php',
    'css/style.css',
    'js/script.js'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<span class='ok'>✅ $file</span><br>";
    } else {
        echo "<span class='error'>❌ $file - MISSING</span><br>";
    }
}

// 2. Test database connection
echo "<h3>🗄️ Database Connection Test</h3>";
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            echo "<span class='ok'>✅ Database connected successfully</span><br>";
            
            // Check tables
            $tables = ['users', 'documents', 'text_styles', 'labelings'];
            foreach ($tables as $table) {
                $stmt = $conn->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    echo "<span class='ok'>✅ Table '$table' exists</span><br>";
                } else {
                    echo "<span class='error'>❌ Table '$table' missing</span><br>";
                }
            }
            
            // Check data
            $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<span class='ok'>✅ Users table has {$result['count']} records</span><br>";
            
        } else {
            echo "<span class='error'>❌ Database connection failed</span><br>";
        }
    } else {
        echo "<span class='error'>❌ Database config file missing</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Database error: " . $e->getMessage() . "</span><br>";
}

// 3. Test session
echo "<h3>🔐 Session Test</h3>";
session_start();
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<span class='ok'>✅ Session working</span><br>";
    echo "Session ID: " . session_id() . "<br>";
} else {
    echo "<span class='error'>❌ Session not working</span><br>";
}

// 4. Test auth system
echo "<h3>👤 Auth System Test</h3>";
try {
    require_once 'includes/auth.php';
    $auth = new Auth();
    echo "<span class='ok'>✅ Auth class loaded</span><br>";
    
    if ($auth->isLoggedIn()) {
        echo "<span class='ok'>✅ User is logged in: " . ($_SESSION['username'] ?? 'Unknown') . "</span><br>";
        echo "Role: " . ($_SESSION['role'] ?? 'Unknown') . "<br>";
    } else {
        echo "<span class='warning'>⚠️ User not logged in</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Auth error: " . $e->getMessage() . "</span><br>";
}

// 5. Test functions
echo "<h3>⚙️ Functions Test</h3>";
try {
    require_once 'includes/functions.php';
    $functions = new Functions();
    echo "<span class='ok'>✅ Functions class loaded</span><br>";
    
    $users = $functions->getUsers();
    echo "<span class='ok'>✅ getUsers() returned " . count($users) . " users</span><br>";
    
    $documents = $functions->getDocuments();
    echo "<span class='ok'>✅ getDocuments() returned " . count($documents) . " documents</span><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Functions error: " . $e->getMessage() . "</span><br>";
}

// 6. PHP info
echo "<h3>🐘 PHP Information</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Extensions: ";
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'session'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span class='ok'>$ext</span> ";
    } else {
        echo "<span class='error'>$ext</span> ";
    }
}
echo "<br>";

// 7. Quick login link
echo "<h3>🚀 Quick Actions</h3>";
echo "<a href='login.php' style='background:#007bff;color:white;padding:10px;text-decoration:none;border-radius:5px;'>Go to Login</a> ";
echo "<a href='setup.php' style='background:#28a745;color:white;padding:10px;text-decoration:none;border-radius:5px;margin-left:10px;'>Run Setup</a>";

echo "<br><br><strong>🔧 If you see errors above, run setup.php first!</strong>";
?>