<?php
// Authentication functions

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Check if user has specific role
function hasRole($required_role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $required_role;
}

// Redirect if not authenticated
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

// Redirect if not specific role
function requireRole($required_role) {
    requireAuth();
    if (!hasRole($required_role)) {
        header('Location: ../login.php?error=insufficient_privileges');
        exit();
    }
}

// Get current user info
function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

// Check if database connection exists
function checkDatabaseConnection($pdo) {
    if (!$pdo) {
        die("Database connection not established. Please check config/database.php");
    }
}
?>