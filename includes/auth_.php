<?php
// includes/auth.php - Complete Fixed Version
session_start();

class Auth {
    public static function requireLogin($role = null) {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . self::getBaseUrl() . '/login.php');
            exit;
        }
        
        if ($role && $_SESSION['role'] !== $role) {
            self::redirectToRoleDashboard($_SESSION['role']);
            exit;
        }
        
        return true;
    }
    
    public static function login($username, $password) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function logout() {
        session_unset();
        session_destroy();
        header('Location: ' . self::getBaseUrl() . '/login.php');
        exit;
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public static function getCurrentUser() {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role']
            ];
        }
        return null;
    }
    
    public static function redirectToRoleDashboard($role) {
        $baseUrl = self::getBaseUrl();
        
        switch ($role) {
            case 'admin':
                header('Location: ' . $baseUrl . '/admin/dashboard.php');
                break;
            case 'labeler':
                header('Location: ' . $baseUrl . '/labeler/dashboard.php');
                break;
            case 'reviewer':
                header('Location: ' . $baseUrl . '/reviewer/dashboard.php');
                break;
            default:
                header('Location: ' . $baseUrl . '/login.php');
        }
        exit;
    }
    
    public static function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['SCRIPT_NAME']);
        
        // Remove trailing slashes and normalize path
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '';
        }
        
        return $protocol . '://' . $host . $path;
    }
    
    public static function checkPermission($required_role) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $user_role = $_SESSION['role'];
        $role_hierarchy = ['admin' => 3, 'reviewer' => 2, 'labeler' => 1];
        
        return isset($role_hierarchy[$user_role]) && 
               isset($role_hierarchy[$required_role]) && 
               $role_hierarchy[$user_role] >= $role_hierarchy[$required_role];
    }
}
?>