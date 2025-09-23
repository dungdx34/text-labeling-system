<?php
// index.php - Complete Fixed Version
require_once 'config/database.php';
require_once 'includes/auth.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Check if user is logged in
if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    
    // Prevent infinite redirect by checking current path
    $current_path = $_SERVER['REQUEST_URI'];
    $script_name = basename($_SERVER['SCRIPT_NAME']);
    
    // Only redirect if we're specifically on index.php
    if ($script_name === 'index.php' || strpos($current_path, '/index.php') !== false) {
        Auth::redirectToRoleDashboard($user['role']);
    }
} else {
    // Redirect to login if not authenticated
    header('Location: login.php');
    exit;
}
?>