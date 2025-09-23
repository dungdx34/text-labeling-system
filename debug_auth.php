<?php
/**
 * Debug Auth Class Internal Errors
 * This will reveal the actual error hidden by the Auth class
 */

// Enable ALL error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>🔍 Auth Class Debug</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

try {
    // Load database first
    require_once 'config/database.php';
    echo "✅ Database config loaded<br>";
    
    // Test database connection directly
    $conn = $database->getConnection();
    echo "✅ Database connection successful<br>";
    
    // Test a simple query
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch()['count'];
    echo "✅ Users in database: $count<br><br>";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
    die();
}

// Now debug Auth class step by step
echo "<h2>🔐 Auth Class Step-by-Step Debug</h2>";

try {
    require_once 'includes/auth.php';
    echo "✅ Auth file loaded<br>";
} catch (Exception $e) {
    echo "❌ Auth file error: " . $e->getMessage() . "<br>";
    die();
}

// Create a custom Auth class with detailed debugging
class DebugAuth {
    private $db;
    private $max_login_attempts = 5;
    private $lockout_time = 900;
    
    public function __construct() {
        try {
            global $database;
            $this->db = $database->getConnection();
            echo "✅ DebugAuth: Database connection established<br>";
        } catch (Exception $e) {
            echo "❌ DebugAuth constructor error: " . $e->getMessage() . "<br>";
            throw $e;
        }
    }
    
    public function debugLogin($username, $password) {
        echo "<h3>🚀 Starting Debug Login for: " . htmlspecialchars($username) . "</h3>";
        
        try {
            // Step 1: Input validation
            echo "Step 1: Input validation<br>";
            $username = trim($username);
            if (empty($username) || empty($password)) {
                echo "❌ Empty credentials<br>";
                return false;
            }
            echo "✅ Input validation passed<br>";
            
            // Step 2: Check lockout
            echo "<br>Step 2: Lockout check<br>";
            if ($this->isLockedOut($username)) {
                echo "❌ User is locked out<br>";
                return false;
            }
            echo "✅ User not locked out<br>";
            
            // Step 3: Database query
            echo "<br>Step 3: Database user lookup<br>";
            $query = "SELECT id, username, email, password, full_name, role, status, login_attempts 
                     FROM users 
                     WHERE (username = :username OR email = :username) AND status = 'active'";
            
            echo "Query: " . $query . "<br>";
            echo "Parameter: " . $username . "<br>";
            
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                echo "❌ Failed to prepare statement<br>";
                $error = $this->db->errorInfo();
                echo "Database error: " . print_r($error, true) . "<br>";
                return false;
            }
            
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $result = $stmt->execute();
            
            if (!$result) {
                echo "❌ Failed to execute query<br>";
                $error = $stmt->errorInfo();
                echo "Query error: " . print_r($error, true) . "<br>";
                return false;
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                echo "❌ User not found in database<br>";
                return false;
            }
            
            echo "✅ User found in database<br>";
            echo "User ID: " . $user['id'] . "<br>";
            echo "Username: " . $user['username'] . "<br>";
            echo "Role: " . $user['role'] . "<br>";
            echo "Status: " . $user['status'] . "<br>";
            echo "Password hash length: " . strlen($user['password']) . "<br>";
            
            // Step 4: Password verification
            echo "<br>Step 4: Password verification<br>";
            echo "Input password: " . $password . "<br>";
            echo "Stored hash: " . substr($user['password'], 0, 20) . "...<br>";
            
            if (!password_verify($password, $user['password'])) {
                echo "❌ Password verification failed<br>";
                
                // Test with manual verification
                echo "<br>Manual password test:<br>";
                $test_hash = password_hash($password, PASSWORD_DEFAULT);
                echo "New hash for same password: " . substr($test_hash, 0, 20) . "...<br>";
                $test_verify = password_verify($password, $test_hash);
                echo "Test verification: " . ($test_verify ? 'SUCCESS' : 'FAILED') . "<br>";
                
                return false;
            }
            
            echo "✅ Password verification successful<br>";
            
            // Step 5: Session creation
            echo "<br>Step 5: Session creation<br>";
            
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
                echo "✅ Session started<br>";
            } else {
                echo "✅ Session already active<br>";
            }
            
            echo "Session ID: " . session_id() . "<br>";
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            
            echo "✅ Session variables set<br>";
            echo "Session data:<br>";
            echo "<pre>";
            print_r($_SESSION);
            echo "</pre>";
            
            // Step 6: Update database
            echo "<br>Step 6: Update last login<br>";
            try {
                $update_query = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->bindParam(':user_id', $user['id']);
                $update_result = $update_stmt->execute();
                
                if ($update_result) {
                    echo "✅ Last login updated<br>";
                } else {
                    echo "⚠️ Failed to update last login (non-critical)<br>";
                }
            } catch (Exception $e) {
                echo "⚠️ Last login update error: " . $e->getMessage() . "<br>";
            }
            
            echo "<br>🎉 <strong>DEBUG LOGIN SUCCESSFUL!</strong><br>";
            return [
                'success' => true,
                'message' => 'Login successful',
                'role' => $user['role'],
                'user' => $user
            ];
            
        } catch (Exception $e) {
            echo "❌ <strong>Exception in debugLogin:</strong><br>";
            echo "Error: " . $e->getMessage() . "<br>";
            echo "File: " . $e->getFile() . "<br>";
            echo "Line: " . $e->getLine() . "<br>";
            echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
            return false;
        }
    }
    
    private function isLockedOut($username) {
        try {
            $query = "SELECT login_attempts, locked_until FROM users WHERE username = :username OR email = :username";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) return false;
            
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            echo "❌ Lock check error: " . $e->getMessage() . "<br>";
            return false;
        }
    }
}

// Test with DebugAuth
try {
    $debugAuth = new DebugAuth();
    echo "✅ DebugAuth created successfully<br><br>";
    
    // Test login
    $result = $debugAuth->debugLogin('admin', 'password123');
    
    echo "<h2>🏁 Final Result:</h2>";
    if ($result) {
        echo "✅ <strong>LOGIN SUCCESS!</strong><br>";
        if (is_array($result)) {
            echo "<pre>";
            print_r($result);
            echo "</pre>";
        }
    } else {
        echo "❌ <strong>LOGIN FAILED</strong><br>";
    }
    
} catch (Exception $e) {
    echo "❌ <strong>DebugAuth creation failed:</strong><br>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

// Also test the original Auth class with error capture
echo "<br><h2>🔍 Original Auth Class Error Capture</h2>";
try {
    $originalAuth = new Auth();
    
    // Capture any output/errors during login
    ob_start();
    $result = $originalAuth->login('admin', 'password123');
    $output = ob_get_clean();
    
    echo "Original Auth result:<br>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if ($output) {
        echo "Captured output during login:<br>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "❌ Original Auth error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</div>";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Debug Auth - Text Labeling System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .action-form { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .btn { padding: 10px 20px; margin: 5px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; display: inline-block; }
    </style>
</head>
<body>
    <div class="action-form">
        <h3>🔗 Quick Links</h3>
        <a href="test_login.php" class="btn">🔙 Back to Test Login</a>
        <a href="debug_login.php" class="btn">🔍 Main Debug</a>
        <a href="fix_passwords.php" class="btn">🔧 Fix Passwords</a>
    </div>
</body>
</html>