<?php
// check_php.php - Debug PHP and login issues

echo "<h2>PHP Debug Information</h2>";

// 1. PHP Version
echo "<h3>1. PHP Version:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Password functions available: " . (function_exists('password_verify') ? 'YES' : 'NO') . "<br>";
echo "PDO available: " . (class_exists('PDO') ? 'YES' : 'NO') . "<br>";
echo "MySQLi available: " . (class_exists('mysqli') ? 'YES' : 'NO') . "<br>";

// 2. Test password hashing
echo "<h3>2. Password Hash Test:</h3>";
$test_password = 'admin123';
$hash = password_hash($test_password, PASSWORD_DEFAULT);
echo "Original password: $test_password<br>";
echo "Generated hash: $hash<br>";
echo "Verification test: " . (password_verify($test_password, $hash) ? 'PASS' : 'FAIL') . "<br>";

// 3. Database connection test
echo "<h3>3. Database Connection Test:</h3>";
try {
    $mysqli = new mysqli('localhost', 'root', '', 'text_labeling_system');
    if ($mysqli->connect_error) {
        throw new Exception("MySQLi connection failed: " . $mysqli->connect_error);
    }
    echo "MySQLi connection: SUCCESS<br>";
    
    // Test query
    $result = $mysqli->query("SELECT COUNT(*) as count FROM users");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Users count: " . $row['count'] . "<br>";
    }
    
    $mysqli->close();
} catch (Exception $e) {
    echo "MySQLi connection: FAILED - " . $e->getMessage() . "<br>";
}

// Try PDO
try {
    $pdo = new PDO("mysql:host=localhost;dbname=text_labeling_system", 'root', '');
    echo "PDO connection: SUCCESS<br>";
} catch (Exception $e) {
    echo "PDO connection: FAILED - " . $e->getMessage() . "<br>";
}

// 4. Check users table structure
echo "<h3>4. Users Table Structure:</h3>";
try {
    $mysqli = new mysqli('localhost', 'root', '', 'text_labeling_system');
    $result = $mysqli->query("DESCRIBE users");
    if ($result) {
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
        }
        echo "</table>";
    }
    $mysqli->close();
} catch (Exception $e) {
    echo "Error checking table: " . $e->getMessage() . "<br>";
}

// 5. Check actual user data
echo "<h3>5. Current User Data:</h3>";
try {
    $mysqli = new mysqli('localhost', 'root', '', 'text_labeling_system');
    $result = $mysqli->query("SELECT username, role, status, is_active, SUBSTRING(password, 1, 30) as password_preview FROM users");
    if ($result) {
        echo "<table border='1'><tr><th>Username</th><th>Role</th><th>Status</th><th>is_active</th><th>Password Preview</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['username']}</td><td>{$row['role']}</td><td>{$row['status']}</td><td>{$row['is_active']}</td><td>{$row['password_preview']}...</td></tr>";
        }
        echo "</table>";
    }
    $mysqli->close();
} catch (Exception $e) {
    echo "Error checking users: " . $e->getMessage() . "<br>";
}

// 6. Test login manually
echo "<h3>6. Manual Login Test:</h3>";
if ($_POST) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        $mysqli = new mysqli('localhost', 'root', '', 'text_labeling_system');
        $stmt = $mysqli->prepare("SELECT id, username, password, role, full_name, is_active FROM users WHERE username = ? AND is_active = 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo "<strong>User found:</strong><br>";
            echo "Username: {$user['username']}<br>";
            echo "Role: {$user['role']}<br>";
            echo "is_active: {$user['is_active']}<br>";
            echo "Password hash: " . substr($user['password'], 0, 50) . "...<br>";
            
            // Test different password methods
            echo "<br><strong>Password verification tests:</strong><br>";
            
            // Test 1: Plain text
            if ($password === $user['password']) {
                echo "✅ Plain text match<br>";
                $success = true;
            } else {
                echo "❌ Plain text: no match<br>";
            }
            
            // Test 2: MD5
            if (md5($password) === $user['password']) {
                echo "✅ MD5 match<br>";
                $success = true;
            } else {
                echo "❌ MD5: no match<br>";
            }
            
            // Test 3: Bcrypt
            if (function_exists('password_verify') && password_verify($password, $user['password'])) {
                echo "✅ Bcrypt match<br>";
                $success = true;
            } else {
                echo "❌ Bcrypt: no match<br>";
            }
            
            // Test 4: SHA1  
            if (sha1($password) === $user['password']) {
                echo "✅ SHA1 match<br>";
                $success = true;
            } else {
                echo "❌ SHA1: no match<br>";
            }
            
        } else {
            echo "❌ User not found or not active<br>";
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        echo "❌ Login test error: " . $e->getMessage() . "<br>";
    }
}
?>

<h3>7. Test Login Form:</h3>
<form method="POST">
    <table>
        <tr>
            <td>Username:</td>
            <td><input type="text" name="username" value="admin"></td>
        </tr>
        <tr>
            <td>Password:</td>
            <td><input type="password" name="password" value="admin123"></td>
        </tr>
        <tr>
            <td></td>
            <td><button type="submit">Test Login</button></td>
        </tr>
    </table>
</form>

<h3>8. Quick Fixes:</h3>
<p><a href="?fix=plain">Set Plain Text Passwords</a></p>
<p><a href="?fix=md5">Set MD5 Passwords</a></p>
<p><a href="?fix=bcrypt">Set Bcrypt Passwords</a></p>

<?php
// Quick fixes
if (isset($_GET['fix'])) {
    $mysqli = new mysqli('localhost', 'root', '', 'text_labeling_system');
    
    switch ($_GET['fix']) {
        case 'plain':
            $mysqli->query("UPDATE users SET password = 'admin123' WHERE username = 'admin'");
            $mysqli->query("UPDATE users SET password = 'labeler123' WHERE username LIKE 'labeler%'");
            echo "<div style='color: green; font-weight: bold;'>✅ Set plain text passwords: admin123, labeler123</div>";
            break;
            
        case 'md5':
            $mysqli->query("UPDATE users SET password = MD5('admin123') WHERE username = 'admin'");
            $mysqli->query("UPDATE users SET password = MD5('labeler123') WHERE username LIKE 'labeler%'");
            echo "<div style='color: green; font-weight: bold;'>✅ Set MD5 passwords: admin123, labeler123</div>";
            break;
            
        case 'bcrypt':
            $admin_hash = password_hash('admin123', PASSWORD_DEFAULT);
            $labeler_hash = password_hash('labeler123', PASSWORD_DEFAULT);
            $mysqli->query("UPDATE users SET password = '$admin_hash' WHERE username = 'admin'");
            $mysqli->query("UPDATE users SET password = '$labeler_hash' WHERE username LIKE 'labeler%'");
            echo "<div style='color: green; font-weight: bold;'>✅ Set bcrypt passwords: admin123, labeler123</div>";
            break;
    }
    
    $mysqli->close();
    echo "<script>setTimeout(function(){ window.location.href = 'check_php.php'; }, 2000);</script>";
}
?>
