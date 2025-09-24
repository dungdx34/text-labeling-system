<?php
/**
 * Authentication Helper Functions
 * Text Labeling System
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role']
    ];
}

/**
 * Check if user has specific role
 */
function hasRole($required_role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return $_SESSION['role'] === $required_role;
}

/**
 * Require user to be logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
    
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ../unauthorized.php');
        exit();
    }
}

/**
 * Require labeler role
 */
function requireLabeler() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
    
    if ($_SESSION['role'] !== 'labeler') {
        header('Location: ../unauthorized.php');
        exit();
    }
}

/**
 * Require reviewer role
 */
function requireReviewer() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
    
    if ($_SESSION['role'] !== 'reviewer') {
        header('Location: ../unauthorized.php');
        exit();
    }
}

/**
 * Require admin or specific role
 */
function requireAdminOr($role) {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
    
    if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== $role) {
        header('Location: ../unauthorized.php');
        exit();
    }
}

/**
 * Logout user
 */
function logoutUser() {
    // Clear session data
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    // Redirect to login
    header('Location: login.php');
    exit();
}

/**
 * Get dashboard URL based on role
 */
function getDashboardUrl($role = null) {
    if (!$role && isLoggedIn()) {
        $role = $_SESSION['role'];
    }
    
    switch ($role) {
        case 'admin':
            return 'admin/dashboard.php';
        case 'labeler':
            return 'labeler/dashboard.php';
        case 'reviewer':
            return 'reviewer/dashboard.php';
        default:
            return 'login.php';
    }
}

/**
 * Log user activity (if logging is enabled)
 */
function logActivity($db, $user_id, $action, $target_type = null, $target_id = null, $details = null) {
    try {
        // Check if activity_logs table exists
        $check_table = $db->query("SHOW TABLES LIKE 'activity_logs'");
        if ($check_table->rowCount() == 0) {
            // Create table if not exists
            $create_table = "CREATE TABLE IF NOT EXISTS activity_logs (
                id int(11) NOT NULL AUTO_INCREMENT,
                user_id int(11) NOT NULL,
                action varchar(100) NOT NULL,
                target_type varchar(50),
                target_id int(11),
                details text,
                ip_address varchar(45),
                user_agent text,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->exec($create_table);
        }
        
        // Insert activity log
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, target_type, target_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $action,
            $target_type,
            $target_id,
            is_array($details) ? json_encode($details) : $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Silently fail if logging doesn't work
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Create default admin user if not exists
 */
function createDefaultAdmin($db) {
    try {
        // Check if admin user exists
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            // Create default admin
            $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, full_name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                'admin',
                'Administrator',
                'admin@example.com',
                $admin_password,
                'admin',
                'active'
            ]);
            
            return true; // Admin created
        }
        
        return false; // Admin already exists
    } catch (Exception $e) {
        error_log("Failed to create default admin: " . $e->getMessage());
        return false;
    }
}

/**
 * Create default demo users if not exists
 */
function createDefaultUsers($db) {
    try {
        $default_users = [
            [
                'username' => 'admin',
                'full_name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin'
            ],
            [
                'username' => 'label1',
                'full_name' => 'Labeler One',
                'email' => 'labeler1@example.com',
                'password' => password_hash('label123', PASSWORD_DEFAULT),
                'role' => 'labeler'
            ],
            [
                'username' => 'review1',
                'full_name' => 'Reviewer One',
                'email' => 'reviewer1@example.com',
                'password' => password_hash('review123', PASSWORD_DEFAULT),
                'role' => 'reviewer'
            ]
        ];
        
        foreach ($default_users as $user_data) {
            // Check if user already exists
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
            $stmt->execute([$user_data['username']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                // Create user
                $stmt = $db->prepare("INSERT INTO users (username, full_name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                $stmt->execute([
                    $user_data['username'],
                    $user_data['full_name'],
                    $user_data['email'],
                    $user_data['password'],
                    $user_data['role']
                ]);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to create default users: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and create users table if not exists
 */
function ensureUsersTable($db) {
    try {
        // Check if users table exists
        $check_table = $db->query("SHOW TABLES LIKE 'users'");
        if ($check_table->rowCount() == 0) {
            // Create users table
            $create_table = "CREATE TABLE IF NOT EXISTS users (
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
                PRIMARY KEY (id),
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_role (role),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->exec($create_table);
            
            // Create default users
            createDefaultUsers($db);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to ensure users table: " . $e->getMessage());
        return false;
    }
}

/**
 * Auto-setup: Create tables and default users if needed
 */
function autoSetup() {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            ensureUsersTable($db);
        }
    } catch (Exception $e) {
        error_log("Auto-setup failed: " . $e->getMessage());
    }
}

// Auto-run setup when auth.php is included
autoSetup();
?>