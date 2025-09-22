<?php
// includes/auth.php

// Kiểm tra và khởi tạo session an toàn
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra xem user đã đăng nhập chưa
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Kiểm tra role của user
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['role'] === $role;
}

// Yêu cầu đăng nhập
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . getBasePath() . 'login.php');
        exit();
    }
}

// Yêu cầu role cụ thể
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        // Log error for debugging
        error_log("Access denied: User role '" . ($_SESSION['role'] ?? 'none') . "' trying to access '$role' area");
        header('Location: ' . getBasePath() . 'login.php?error=access_denied');
        exit();
    }
}

// Lấy base path tùy thuộc vào thư mục hiện tại
function getBasePath() {
    $currentDir = basename(dirname($_SERVER['SCRIPT_NAME']));
    if (in_array($currentDir, ['admin', 'labeler', 'reviewer'])) {
        return '../';
    }
    return '';
}

// Logout user
function logout() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    session_unset();
    session_destroy();
    header('Location: ' . getBasePath() . 'login.php?message=logout_success');
    exit();
}

// Lấy thông tin user hiện tại
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'],
        'full_name' => $_SESSION['full_name'] ?? ''
    ];
}

// Test database connection với error handling tốt hơn
function testDatabaseConnection() {
    try {
        $config_path = __DIR__ . '/../config/database.php';
        
        if (!file_exists($config_path)) {
            error_log("Database config file not found: $config_path");
            return false;
        }
        
        require_once $config_path;
        
        if (!class_exists('Database')) {
            error_log("Database class not found");
            return false;
        }
        
        $database = new Database();
        $connection = $database->getConnection();
        
        if (!$connection) {
            error_log("Database connection returned null");
            return false;
        }
        
        // Test simple query
        $stmt = $connection->prepare("SELECT 1");
        $result = $stmt->execute();
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        return false;
    }
}

// Debug function để kiểm tra session
function debugSession() {
    if (session_status() == PHP_SESSION_NONE) {
        error_log("Session not started");
        return false;
    }
    
    error_log("Session data: " . json_encode($_SESSION));
    return true;
}

// Redirect an toàn dựa trên role
function redirectToDashboard($role = null) {
    if (!$role && isLoggedIn()) {
        $role = $_SESSION['role'];
    }
    
    $basePath = getBasePath();
    
    switch ($role) {
        case 'admin':
            $url = $basePath . 'admin/dashboard.php';
            break;
        case 'labeler':
            $url = $basePath . 'labeler/dashboard.php';
            break;
        case 'reviewer':
            $url = $basePath . 'reviewer/dashboard.php';
            break;
        default:
            $url = $basePath . 'login.php?error=invalid_role';
            break;
    }
    
    header("Location: $url");
    exit();
}

// Kiểm tra quyền truy cập file
function checkFileAccess() {
    $script_name = basename($_SERVER['SCRIPT_NAME']);
    $current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
    
    // Danh sách file không cần auth
    $public_files = ['login.php', 'logout.php', 'setup_admin.php', 'check_accounts.php', 'fix_all_accounts.php'];
    
    if (in_array($script_name, $public_files)) {
        return true;
    }
    
    // Kiểm tra quyền truy cập theo thư mục
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_role = $_SESSION['role'];
    
    // Admin có thể truy cập mọi nơi
    if ($user_role === 'admin') {
        return true;
    }
    
    // Kiểm tra quyền theo thư mục
    if ($current_dir === 'labeler' && $user_role !== 'labeler') {
        return false;
    }
    
    if ($current_dir === 'reviewer' && $user_role !== 'reviewer') {
        return false;
    }
    
    return true;
}
?>