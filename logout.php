<?php
session_start();

// Log logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'config/database.php';
        require_once 'includes/auth.php';
        
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            logActivity($db, $_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id']);
        }
    } catch (Exception $e) {
        // Ignore logging errors during logout
    }
}

// Clear all session data
session_unset();
session_destroy();

// Start new session for flash message
session_start();
$_SESSION['logout_message'] = 'Bạn đã đăng xuất thành công!';

// Redirect to login page
header('Location: login.php');
exit();
?>