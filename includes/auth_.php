<?php
// includes/auth.php
session_start();

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
        header('Location: ' . getBasePath() . 'index.php');
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
    session_start();
    session_unset();
    session_destroy();
    header('Location: ' . getBasePath() . 'login.php');
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

// Kiểm tra và khởi tạo session nếu cần
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>