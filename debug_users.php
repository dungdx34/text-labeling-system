<?php
require_once 'config/database.php';

// Simple debug tool to check users
$database = new Database();
$db = $database->getConnection();

echo "<h2>Debug Users - Text Labeling System</h2>";
echo "<style>table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";

if (!$db) {
    echo "<p style='color: red;'>❌ Database connection failed!</p>";
    exit;
}

echo "<p style='color: green;'>✅ Database connected successfully</p>";

// Check if users table exists
try {
    $result = $db->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() == 0) {
        echo "<p style='color: red;'>❌ Users table does not exist!</p>";
        
        // Create users table
        echo "<h3>Creating users table...</h3>";
        $create_sql = "CREATE TABLE IF NOT EXISTS users (
            id int(11) NOT NULL AUTO_INCREMENT,
            username varchar(50) NOT NULL UNIQUE,
            full_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL UNIQUE,
            password varchar(255) NOT NULL,
            role enum('admin','labeler','reviewer') NOT NULL DEFAULT 'labeler',
            status enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
            last_login timestamp NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($create_sql);
        echo "<p style='color: green;'>✅ Users table created</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error checking users table: " . $e->getMessage() . "</p>";
}

// Show current users
try {
    echo "<h3>Current Users:</h3>";
    $stmt = $db->query("SELECT id, username, full_name, email, role, status, CHAR_LENGTH(password) as pwd_len, created_at FROM users ORDER BY role, username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p style='color: orange;'>⚠️ No users found in database</p>";
        
        // Create default users
        echo "<h3>Creating default users...</h3>";
        $default_users = [
            ['admin', 'Administrator', 'admin@example.com', 'admin123', 'admin'],
            ['label1', 'Labeler One', 'label1@example.com', 'label123', 'labeler'],
            ['review1', 'Reviewer One', 'review1@example.com', 'review123', 'reviewer']
        ];
        
        foreach ($default_users as $user_data) {
            try {
                $stmt = $db->prepare("INSERT INTO users (username, full_name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                $stmt->execute($user_data);
                echo "<p style='color: green;'>✅ Created user: {$user_data[0]}</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Error creating {$user_data[0]}: " . $e->getMessage() . "</p>";
            }
        }
        
        // Refresh users list
        $stmt = $db->query("SELECT id, username, full_name, email, role, status, CHAR_LENGTH(password) as pwd_len, created_at FROM users ORDER BY role, username");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th><th>Pwd Length</th><th>Created</th><th>Actions</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td><strong>{$user['username']}</strong></td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td><span style='color: " . ($user['role'] == 'admin' ? 'red' : ($user['role'] == 'labeler' ? 'blue' : 'green')) . ";'>{$user['role']}</span></td>";
        echo "<td>{$user['status']}</td>";
        echo "<td>{$user['pwd_len']}</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($user['created_at'])) . "</td>";
        echo "<td><a href='?reset_pwd={$user['username']}' onclick='return confirm(\"Reset password for {$user['username']}?\")'>Reset Password</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error fetching users: " . $e->getMessage() . "</p>";
}

// Handle password reset
if (isset($_GET['reset_pwd'])) {
    $username = $_GET['reset_pwd'];
    $new_passwords = [
        'admin' => 'admin123',
        'label1' => 'label123',
        'review1' => 'review123'
    ];
    
    if (isset($new_passwords[$username])) {
        try {
            $stmt = $db->prepare("UPDATE users SET password = ?, status = 'active' WHERE username = ?");
            $stmt->execute([$new_passwords[$username], $username]);
            echo "<p style='color: green;'>✅ Password reset for {$username} to: {$new_passwords[$username]}</p>";
            echo "<script>setTimeout(() => window.location.href = 'debug_users.php', 2000);</script>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error resetting password: " . $e->getMessage() . "</p>";
        }
    }
}

// Test login function
if (isset($_GET['test_login'])) {
    echo "<h3>Testing Login Function:</h3>";
    $test_users = [
        ['admin', 'admin123'],
        ['label1', 'label123'], 
        ['review1', 'review123']
    ];
    
    foreach ($test_users as $test) {
        $username = $test[0];
        $password = $test[1];
        
        try {
            $stmt = $db->prepare("SELECT id, username, password, role, status FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $password_ok = false;
                if (password_verify($password, $user['password'])) {
                    $password_ok = true;
                    echo "<p style='color: green;'>✅ {$username}: Hashed password OK</p>";
                } elseif ($password === $user['password']) {
                    $password_ok = true;
                    echo "<p style='color: orange;'>⚠️ {$username}: Plain text password OK (will be upgraded)</p>";
                } else {
                    echo "<p style='color: red;'>❌ {$username}: Password mismatch. DB: '{$user['password']}' vs Test: '{$password}'</p>";
                }
                
            } else {
                echo "<p style='color: red;'>❌ {$username}: User not found or inactive</p>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ {$username}: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<hr>";
echo "<p><a href='?test_login=1'>🧪 Test Login Function</a> | <a href='login.php'>🔑 Go to Login Page</a> | <a href='debug_users.php'>🔄 Refresh</a></p>";
echo "<p><small>⚠️ Remember to delete this file in production!</small></p>";
?>