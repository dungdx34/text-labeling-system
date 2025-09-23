<?php
/**
 * Test Direct Login and Redirect - Text Labeling System
 * This will test login without form submission and check redirect paths
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Direct Login Test</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

try {
    // Load required files
    require_once 'config/database.php';
    require_once 'includes/auth.php';
    
    echo "✅ Files loaded successfully<br><br>";
    
    // Test direct login for admin
    echo "<h2>🚀 Testing Direct Admin Login</h2>";
    
    $username = 'admin';
    $password = 'password123';
    
    echo "Attempting login: $username / $password<br>";
    
    // Clear any existing session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    session_start();
    
    echo "Session started, ID: " . session_id() . "<br>";
    
    // Attempt login
    $result = $auth->login($username, $password);
    
    echo "<h3>Login Result:</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if ($result['success']) {
        echo "✅ <strong>Login SUCCESS!</strong><br><br>";
        
        // Check session after login
        echo "<h3>Session After Login:</h3>";
        echo "<pre>";
        print_r($_SESSION);
        echo "</pre>";
        
        // Test redirect URL
        $redirect_url = $auth->getRedirectUrl($result['role']);
        echo "Redirect URL: <strong>$redirect_url</strong><br>";
        
        // Check if dashboard file exists
        $dashboard_path = ltrim($redirect_url, '/');
        echo "Dashboard path: <strong>$dashboard_path</strong><br>";
        
        if (file_exists($dashboard_path)) {
            echo "✅ Dashboard file exists<br>";
            echo "File size: " . filesize($dashboard_path) . " bytes<br>";
            
            // Test if file is readable and executable
            if (is_readable($dashboard_path)) {
                echo "✅ Dashboard file is readable<br>";
            } else {
                echo "❌ Dashboard file is NOT readable<br>";
            }
            
        } else {
            echo "❌ <strong>Dashboard file does NOT exist!</strong><br>";
            echo "Looking for: $dashboard_path<br>";
            
            // Check if directory exists
            $dir = dirname($dashboard_path);
            if (is_dir($dir)) {
                echo "✅ Directory '$dir' exists<br>";
                echo "Directory contents:<br>";
                $files = scandir($dir);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        echo "  - $file<br>";
                    }
                }
            } else {
                echo "❌ Directory '$dir' does NOT exist<br>";
            }
        }
        
        // Test if we can include the dashboard file
        if (file_exists($dashboard_path)) {
            echo "<br><h3>🧪 Testing Dashboard File Load:</h3>";
            
            ob_start();
            try {
                // Try to include the dashboard file
                include $dashboard_path;
                $dashboard_output = ob_get_contents();
                ob_end_clean();
                
                if (strlen($dashboard_output) > 100) {
                    echo "✅ Dashboard loads successfully (" . strlen($dashboard_output) . " bytes)<br>";
                } else {
                    echo "⚠️ Dashboard loads but output is small (" . strlen($dashboard_output) . " bytes)<br>";
                    echo "Output preview:<br>";
                    echo "<pre>" . htmlspecialchars(substr($dashboard_output, 0, 200)) . "</pre>";
                }
                
            } catch (Exception $e) {
                ob_end_clean();
                echo "❌ Dashboard file has errors: " . $e->getMessage() . "<br>";
            }
        }
        
        // Test manual redirect
        echo "<br><h3>🔄 Testing Manual Redirect:</h3>";
        
        if (!headers_sent()) {
            echo "✅ Headers not sent - redirect would work<br>";
            echo "<a href='$redirect_url' target='_blank' style='padding: 10px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>🔗 Test Dashboard Link</a><br>";
        } else {
            echo "❌ Headers already sent - redirect would fail<br>";
        }
        
    } else {
        echo "❌ <strong>Login FAILED:</strong> " . $result['message'] . "<br>";
    }
    
    // Test all dashboard files
    echo "<br><h2>📁 Checking All Dashboard Files</h2>";
    
    $dashboards = [
        'admin' => 'admin/dashboard.php',
        'labeler' => 'labeler/dashboard.php', 
        'reviewer' => 'reviewer/dashboard.php'
    ];
    
    foreach ($dashboards as $role => $path) {
        echo "<strong>$role Dashboard:</strong> $path<br>";
        
        if (file_exists($path)) {
            echo "  ✅ File exists (" . filesize($path) . " bytes)<br>";
            
            if (is_readable($path)) {
                echo "  ✅ File readable<br>";
            } else {
                echo "  ❌ File NOT readable<br>";
            }
            
            // Check for PHP errors in file
            $file_content = file_get_contents($path);
            if (strpos($file_content, '<?php') !== false) {
                echo "  ✅ Valid PHP file<br>";
            } else {
                echo "  ⚠️ May not be a PHP file<br>";
            }
            
        } else {
            echo "  ❌ File does NOT exist<br>";
        }
        echo "<br>";
    }
    
    // Test isLoggedIn function
    echo "<h2>🔐 Authentication State Test</h2>";
    
    if ($auth->isLoggedIn()) {
        echo "✅ isLoggedIn() returns TRUE<br>";
        
        $current_user = $auth->getCurrentUser();
        if ($current_user) {
            echo "✅ getCurrentUser() works:<br>";
            echo "  - Username: " . $current_user['username'] . "<br>";
            echo "  - Role: " . $current_user['role'] . "<br>";
            echo "  - Status: " . $current_user['status'] . "<br>";
        } else {
            echo "❌ getCurrentUser() returns NULL<br>";
        }
        
    } else {
        echo "❌ isLoggedIn() returns FALSE<br>";
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Critical Error:</strong> " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</div>";

// Create simple test dashboard files if they don't exist
if (isset($_POST['create_dashboards'])) {
    echo "<br><div style='background: #e7f3ff; padding: 20px; border-radius: 10px;'>";
    echo "<h2>🏗️ Creating Missing Dashboard Files</h2>";
    
    $dashboard_templates = [
        'admin/dashboard.php' => '
<?php
require_once "../includes/auth.php";
$auth->requireLogin(["admin"]);
$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html>
<head><title>Admin Dashboard</title></head>
<body>
<h1>Admin Dashboard</h1>
<p>Welcome, <?= htmlspecialchars($user["full_name"]) ?>!</p>
<p>Role: <?= $user["role"] ?></p>
<a href="../logout.php">Logout</a>
</body>
</html>',
        
        'labeler/dashboard.php' => '
<?php
require_once "../includes/auth.php";
$auth->requireLogin(["labeler"]);
$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html>
<head><title>Labeler Dashboard</title></head>
<body>
<h1>Labeler Dashboard</h1>
<p>Welcome, <?= htmlspecialchars($user["full_name"]) ?>!</p>
<p>Role: <?= $user["role"] ?></p>
<a href="../logout.php">Logout</a>
</body>
</html>',
        
        'reviewer/dashboard.php' => '
<?php
require_once "../includes/auth.php";
$auth->requireLogin(["reviewer"]);
$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html>
<head><title>Reviewer Dashboard</title></head>
<body>
<h1>Reviewer Dashboard</h1>
<p>Welcome, <?= htmlspecialchars($user["full_name"]) ?>!</p>
<p>Role: <?= $user["role"] ?></p>
<a href="../logout.php">Logout</a>
</body>
</html>'
    ];
    
    foreach ($dashboard_templates as $path => $content) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "✅ Created directory: $dir<br>";
        }
        
        if (!file_exists($path)) {
            file_put_contents($path, $content);
            echo "✅ Created file: $path<br>";
        } else {
            echo "⚠️ File already exists: $path<br>";
        }
    }
    
    echo "<br><strong>🎉 Dashboard files created! Try login again.</strong>";
    echo "</div>";
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Test Login - Text Labeling System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .action-form { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .btn { padding: 12px 24px; margin: 10px 5px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; }
    </style>
</head>
<body>

<div class="action-form">
    <h3>🛠️ Quick Actions</h3>
    
    <form method="POST" style="display: inline;">
        <button type="submit" name="create_dashboards" class="btn btn-warning">
            🏗️ Create Missing Dashboard Files
        </button>
    </form>
    
    <a href="login.php" class="btn btn-success">🔑 Test Real Login Page</a>
    <a href="debug_login.php" class="btn btn-primary">🔍 Back to Debug</a>
    <a href="admin/dashboard.php" class="btn btn-primary" target="_blank">🏠 Test Admin Dashboard</a>
    <a href="labeler/dashboard.php" class="btn btn-primary" target="_blank">📋 Test Labeler Dashboard</a>
    <a href="reviewer/dashboard.php" class="btn btn-primary" target="_blank">✅ Test Reviewer Dashboard</a>
</div>

</body>
</html>