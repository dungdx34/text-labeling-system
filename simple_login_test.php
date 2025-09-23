<?php
/**
 * Simple Login Test - Test FIXED Auth Class
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🧪 Simple Login Test</h1>";

try {
    require_once 'config/database.php';
    require_once 'includes/auth.php';  // This should be the FIXED version
    
    echo "✅ Files loaded<br>";
    
    // Test login directly
    echo "<h2>Testing Login with FIXED Auth Class</h2>";
    
    $result = $auth->login('admin', 'password123');
    
    echo "Result:<br>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if ($result['success']) {
        echo "✅ <strong>SUCCESS!</strong> Login worked!<br>";
        echo "Session data:<br>";
        echo "<pre>";
        print_r($_SESSION);
        echo "</pre>";
        
        $redirect = $auth->getRedirectUrl($result['role']);
        echo "Should redirect to: <strong>$redirect</strong><br>";
        
        echo "<br><a href='$redirect' style='padding: 10px; background: green; color: white; text-decoration: none;'>🚀 Go to Dashboard</a>";
        
    } else {
        echo "❌ Login failed: " . $result['message'] . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>

<br><br>
<a href="login.php" style="padding: 10px; background: #007bff; color: white; text-decoration: none;">🔑 Test Real Login Page</a>