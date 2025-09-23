<?php
/**
 * Debug Login Issues - Text Labeling System
 * Use this script to diagnose login problems step by step
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering to prevent header issues
ob_start();

echo "<h1>🔍 Login Debug Tool</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

// Test 1: Database Connection
echo "<h2>1. 🗄️ Database Connection Test</h2>";
try {
    require_once 'config/database.php';
    $conn = $database->getConnection();
    echo "✅ Database connection successful<br>";
    
    // Test specific query
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $admin_count = $stmt->fetch()['count'];
    echo "✅ Admin users found: $admin_count<br>";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    die("Cannot proceed without database connection.");
}

// Test 2: Auth Class Loading
echo "<h2>2. 🔐 Authentication Class Test</h2>";
try {
    require_once 'includes/auth.php';
    echo "✅ Auth file loaded successfully<br>";
    
    if (class_exists('Auth')) {
        echo "✅ Auth class exists<br>";
        $auth = new Auth();
        echo "✅ Auth object created<br>";
    } else {
        echo "❌ Auth class not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Auth loading error: " . $e->getMessage() . "<br>";
}

// Test 3: Session Test
echo "<h2>3. 🔄 Session Test</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✅ Session started<br>";
} else {
    echo "✅ Session already active<br>";
}

echo "Session ID: " . session_id() . "<br>";
echo "Session status: " . session_status() . "<br>";

// Test session write
$_SESSION['test'] = 'debug_value';
if (isset($_SESSION['test'])) {
    echo "✅ Session write/read works<br>";
    unset($_SESSION['test']);
} else {
    echo "❌ Session write/read failed<br>";
}

// Test 4: Password Verification
echo "<h2>4. 🔑 Password Test</h2>";
$test_password = 'password123';
$stored_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

if (password_verify($test_password, $stored_hash)) {
    echo "✅ Password verification works correctly<br>";
} else {
    echo "❌ Password verification failed<br>";
}

// Test 5: User Data Test
echo "<h2>5. 👤 User Data Test</h2>";
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $admin_user = $stmt->fetch();
    
    if ($admin_user) {
        echo "✅ Admin user found<br>";
        echo "   - ID: " . $admin_user['id'] . "<br>";
        echo "   - Username: " . $admin_user['username'] . "<br>";
        echo "   - Role: " . $admin_user['role'] . "<br>";
        echo "   - Status: " . $admin_user['status'] . "<br>";
        echo "   - Password hash length: " . strlen($admin_user['password']) . "<br>";
        
        // Test password for admin
        if (password_verify('password123', $admin_user['password'])) {
            echo "✅ Admin password matches 'password123'<br>";
        } else {
            echo "❌ Admin password does NOT match 'password123'<br>";
            echo "   Stored hash: " . substr($admin_user['password'], 0, 20) . "...<br>";
        }
    } else {
        echo "❌ Admin user not found<br>";
    }
} catch (Exception $e) {
    echo "❌ User query error: " . $e->getMessage() . "<br>";
}

// Test 6: Manual Login Test
echo "<h2>6. 🚀 Manual Login Test</h2>";

if (isset($_POST['test_login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<h3>Login attempt for: " . htmlspecialchars($username) . "</h3>";
    
    try {
        if (isset($auth)) {
            $result = $auth->login($username, $password);
            
            echo "<pre>";
            print_r($result);
            echo "</pre>";
            
            if ($result['success']) {
                echo "✅ Login successful!<br>";
                echo "Current session data:<br>";
                echo "<pre>";
                print_r($_SESSION);
                echo "</pre>";
                
                // Test redirect URL
                $redirect_url = $auth->getRedirectUrl($result['role']);
                echo "Redirect URL would be: <strong>$redirect_url</strong><br>";
                
            } else {
                echo "❌ Login failed: " . $result['message'] . "<br>";
            }
        } else {
            echo "❌ Auth object not available<br>";
        }
    } catch (Exception $e) {
        echo "❌ Login test error: " . $e->getMessage() . "<br>";
    }
}

// Test 7: File Structure Check
echo "<h2>7. 📁 File Structure Check</h2>";
$required_files = [
    'config/database.php' => 'Database config',
    'includes/auth.php' => 'Authentication system',
    'login.php' => 'Login page',
    'index.php' => 'Main index',
    'logout.php' => 'Logout script',
    'admin/dashboard.php' => 'Admin dashboard',
    'labeler/dashboard.php' => 'Labeler dashboard',
    'reviewer/dashboard.php' => 'Reviewer dashboard'
];

foreach ($required_files as $file => $desc) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✅ $desc: $file ($size bytes)<br>";
    } else {
        echo "❌ $desc: $file (MISSING)<br>";
    }
}

// Test 8: PHP Configuration
echo "<h2>8. ⚙️ PHP Configuration</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Session save path: " . session_save_path() . "<br>";
echo "Session cookie lifetime: " . ini_get('session.cookie_lifetime') . "<br>";
echo "Memory limit: " . ini_get('memory_limit') . "<br>";
echo "Max execution time: " . ini_get('max_execution_time') . "<br>";

// Check required extensions
$required_ext = ['pdo', 'pdo_mysql', 'json', 'session'];
echo "Required extensions:<br>";
foreach ($required_ext as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext<br>";
    } else {
        echo "❌ $ext (MISSING)<br>";
    }
}

// End debugging output
echo "</div>";

// Interactive test form
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Debug Login - Text Labeling System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .test-form { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .test-form h3 { color: #333; margin-top: 0; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 200px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .quick-test { background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .quick-test button { margin: 5px; }
    </style>
</head>
<body>

<div class="test-form">
    <h3>🧪 Interactive Login Test</h3>
    <form method="POST">
        <div class="form-group">
            <label>Username:</label>
            <input type="text" name="username" value="admin" required>
        </div>
        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" value="password123" required>
        </div>
        <button type="submit" name="test_login" class="btn">Test Login</button>
    </form>
    
    <div class="quick-test">
        <strong>Quick Tests:</strong><br>
        <button onclick="testUser('admin', 'password123')" class="btn">Test Admin</button>
        <button onclick="testUser('labeler1', 'password123')" class="btn">Test Labeler1</button>
        <button onclick="testUser('reviewer1', 'password123')" class="btn">Test Reviewer1</button>
    </div>
</div>

<div class="test-form">
    <h3>🔍 Additional Checks</h3>
    <a href="login.php" target="_blank" style="margin: 5px; padding: 10px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;">Open Login Page</a>
    <a href="index.php" target="_blank" style="margin: 5px; padding: 10px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;">Open Index Page</a>
    <a href="check_database.php" target="_blank" style="margin: 5px; padding: 10px; background: #ffc107; color: black; text-decoration: none; border-radius: 4px;">Database Check</a>
</div>

<div class="test-form">
    <h3>📋 Next Steps Based on Results</h3>
    <ul>
        <li><strong>If Database Connection Failed:</strong> Check config/database.php settings</li>
        <li><strong>If Auth Class Failed:</strong> Verify includes/auth.php exists and is readable</li>
        <li><strong>If Session Failed:</strong> Check session directory permissions</li>
        <li><strong>If Password Test Failed:</strong> Database might have different password hashes</li>
        <li><strong>If Manual Login Failed:</strong> Check specific error message above</li>
        <li><strong>If Files Missing:</strong> Copy missing files from artifacts</li>
    </ul>
</div>

<script>
function testUser(username, password) {
    document.querySelector('input[name="username"]').value = username;
    document.querySelector('input[name="password"]').value = password;
    document.querySelector('form').submit();
}
</script>

</body>
</html>

<?php
// Flush output buffer
ob_end_flush();
?>