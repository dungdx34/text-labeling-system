<?php
session_start();

// Simple logout without any class dependencies
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Unknown';
    
    // Log activity if database is available
    try {
        require_once 'config/database.php';
        require_once 'includes/functions.php';
        
        // Log logout activity
        logActivity($user_id, 'logout', 'user', $user_id, 'User logged out');
        
    } catch (Exception $e) {
        // If logging fails, continue with logout anyway
        error_log("Logout logging failed: " . $e->getMessage());
    }
}

// Clear all session data
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php?message=logged_out');
exit();
?>