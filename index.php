<?php
session_start();
require_once 'config/database.php';

// Check if system is set up by testing database connection
function isSystemSetup() {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            return false;
        }
        
        // Check if users table exists and has admin user
        $query = "SELECT COUNT(*) FROM users WHERE role = 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $admin_count = $stmt->fetchColumn();
        
        return $admin_count > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'labeler':
            header('Location: labeler/dashboard.php');
            break;
        case 'reviewer':
            header('Location: reviewer/dashboard.php');
            break;
        default:
            // Invalid role, clear session and redirect to login
            session_destroy();
            header('Location: login.php');
    }
    exit();
}

// Check if system needs setup
if (!isSystemSetup()) {
    header('Location: setup.php');
    exit();
}

// If not logged in and system is set up, redirect to login
header('Location: login.php');
exit();
?>