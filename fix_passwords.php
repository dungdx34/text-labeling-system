<?php
/**
 * Fix Password Hashes - Text Labeling System
 * This script will regenerate correct password hashes for all users
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Password Hash Fix Tool</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

try {
    // Connect to database
    require_once 'config/database.php';
    $conn = $database->getConnection();
    echo "✅ Database connected<br><br>";
    
    // Test current password hash
    echo "<h2>🔍 Current Password Analysis</h2>";
    $stmt = $conn->query("SELECT username, password FROM users");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        echo "User: <strong>{$user['username']}</strong><br>";
        echo "Current hash: " . substr($user['password'], 0, 30) . "...<br>";
        
        // Test if current hash works with 'password123'
        if (password_verify('password123', $user['password'])) {
            echo "✅ Current hash works with 'password123'<br>";
        } else {
            echo "❌ Current hash does NOT work with 'password123'<br>";
        }
        echo "<br>";
    }
    
    // Generate new correct password hash
    echo "<h2>🔧 Generating New Password Hashes</h2>";
    $new_password = 'password123';
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    echo "New password: <strong>$new_password</strong><br>";
    echo "New hash: <strong>$new_hash</strong><br>";
    
    // Verify the new hash works
    if (password_verify($new_password, $new_hash)) {
        echo "✅ New hash verification: SUCCESS<br><br>";
    } else {
        echo "❌ New hash verification: FAILED<br><br>";
        die("Cannot proceed with faulty hash generation.");
    }
    
    // Option to update all users
    if (isset($_POST['fix_passwords'])) {
        echo "<h2>🚀 Updating All User Passwords</h2>";
        
        $users_to_update = [
            'admin' => 'password123',
            'labeler1' => 'password123', 
            'labeler2' => 'password123',
            'labeler3' => 'password123',
            'reviewer1' => 'password123',
            'reviewer2' => 'password123'
        ];
        
        foreach ($users_to_update as $username => $password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
            $result = $stmt->execute([$hash, $username]);
            
            if ($result) {
                echo "✅ Updated password for: <strong>$username</strong><br>";
                
                // Verify the update worked
                $check_stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
                $check_stmt->execute([$username]);
                $stored_hash = $check_stmt->fetch()['password'];
                
                if (password_verify($password, $stored_hash)) {
                    echo "   ✅ Verification successful<br>";
                } else {
                    echo "   ❌ Verification failed<br>";
                }
            } else {
                echo "❌ Failed to update: <strong>$username</strong><br>";
            }
        }
        
        echo "<br><h2>🎉 Password Update Complete!</h2>";
        echo "All users now have password: <strong>password123</strong><br>";
        echo "<a href='login.php' style='padding: 10px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin: 10px;'>Test Login Now</a><br>";
    }
    
    // Option to test specific password
    if (isset($_POST['test_custom'])) {
        $test_username = $_POST['username'] ?? '';
        $test_password = $_POST['password'] ?? '';
        
        echo "<h2>🧪 Testing Custom Password</h2>";
        echo "Username: <strong>$test_username</strong><br>";
        echo "Password: <strong>$test_password</strong><br>";
        
        if ($test_username && $test_password) {
            $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
            $stmt->execute([$test_username]);
            $user = $stmt->fetch();
            
            if ($user) {
                if (password_verify($test_password, $user['password'])) {
                    echo "✅ Password match: SUCCESS<br>";
                } else {
                    echo "❌ Password match: FAILED<br>";
                }
            } else {
                echo "❌ User not found<br>";
            }
        }
    }
    
    // Show current user status
    echo "<h2>📊 Current User Status</h2>";
    $stmt = $conn->query("SELECT username, role, status, created_at FROM users ORDER BY role, username");
    $users = $stmt->fetchAll();
    
    echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #e9ecef;'><th style='border: 1px solid #ddd; padding: 8px;'>Username</th><th style='border: 1px solid #ddd; padding: 8px;'>Role</th><th style='border: 1px solid #ddd; padding: 8px;'>Status</th><th style='border: 1px solid #ddd; padding: 8px;'>Created</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['username']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['role']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['status']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</div>";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Fix Passwords - Text Labeling System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .action-form { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .action-form h3 { color: #333; margin-top: 0; }
        .btn { padding: 12px 24px; margin: 10px 5px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover { opacity: 0.8; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 200px; }
    </style>
</head>
<body>

<div class="action-form">
    <h3>🔧 Fix All User Passwords</h3>
    <div class="warning">
        <strong>⚠️ Warning:</strong> This will reset ALL user passwords to "password123"
    </div>
    <form method="POST">
        <button type="submit" name="fix_passwords" class="btn btn-danger" 
                onclick="return confirm('Are you sure you want to reset all passwords to password123?')">
            🔧 Fix All Passwords
        </button>
    </form>
</div>

<div class="action-form">
    <h3>🧪 Test Specific Password</h3>
    <form method="POST">
        <div class="form-group">
            <label>Username:</label>
            <input type="text" name="username" value="admin" required>
        </div>
        <div class="form-group">
            <label>Password to test:</label>
            <input type="password" name="password" value="password123" required>
        </div>
        <button type="submit" name="test_custom" class="btn btn-primary">🧪 Test Password</button>
    </form>
</div>

<div class="action-form">
    <h3>🔗 Quick Actions</h3>
    <a href="debug_login.php" class="btn btn-primary">🔍 Back to Debug</a>
    <a href="login.php" class="btn btn-success">🔑 Test Login Page</a>
    <a href="check_database.php" class="btn btn-primary">🗄️ Database Check</a>
</div>

<div class="action-form">
    <h3>📝 Manual Password Hash Generation</h3>
    <p>If you want to generate password hashes manually:</p>
    <code>
        <?php
        echo "Password: 'password123'<br>";
        echo "Hash: " . password_hash('password123', PASSWORD_DEFAULT) . "<br>";
        echo "Verification: " . (password_verify('password123', password_hash('password123', PASSWORD_DEFAULT)) ? 'TRUE' : 'FALSE') . "<br>";
        ?>
    </code>
</div>

</body>
</html>