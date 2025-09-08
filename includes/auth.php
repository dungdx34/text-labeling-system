<?php
// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Determine the correct path to database.php
$config_paths = [
    __DIR__ . '/../config/database.php',
    '../config/database.php',
    'config/database.php',
    dirname(__DIR__) . '/config/database.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    throw new Exception('Database configuration file not found. Please run setup.php first.');
}

class Auth {
    private $conn;
    
    public function __construct() {
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
            if (!$this->conn) {
                throw new Exception('Database connection failed');
            }
        } catch (Exception $e) {
            throw new Exception('Auth initialization failed: ' . $e->getMessage());
        }
    }
    
    public function login($username, $password) {
        try {
            $query = "SELECT id, username, password, role, full_name FROM users WHERE username = :username AND is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $user['password'])) {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['login_time'] = time();
                    
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return false;
        }
    }
    
    public function logout() {
        // Destroy all session data
        $_SESSION = array();
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        // Check basic session variables
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Check session timeout (24 hours)
        if (time() - $_SESSION['login_time'] > 86400) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            // Determine correct path to login.php based on current location
            $current_dir = dirname($_SERVER['PHP_SELF']);
            
            if (strpos($current_dir, '/admin') !== false || 
                strpos($current_dir, '/labeler') !== false || 
                strpos($current_dir, '/reviewer') !== false) {
                header('Location: ../login.php');
            } else {
                header('Location: login.php');
            }
            exit();
        }
    }
    
    public function requireRole($required_role) {
        $this->requireLogin();
        
        if ($_SESSION['role'] !== $required_role) {
            // Redirect to appropriate dashboard instead of index.php to avoid loops
            switch ($_SESSION['role']) {
                case 'admin':
                    if ($required_role !== 'admin') {
                        header('Location: ../admin/dashboard.php');
                        exit();
                    }
                    break;
                case 'labeler':
                    if ($required_role !== 'labeler') {
                        header('Location: ../labeler/dashboard.php');
                        exit();
                    }
                    break;
                case 'reviewer':
                    if ($required_role !== 'reviewer') {
                        header('Location: ../reviewer/dashboard.php');
                        exit();
                    }
                    break;
                default:
                    // Invalid role, logout and redirect to login
                    $this->logout();
                    header('Location: ../login.php');
                    exit();
            }
        }
    }
    
    public function hasRole($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['role'], $roles);
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'full_name' => $_SESSION['full_name']
        ];
    }
    
    public function updateLastActivity() {
        if ($this->isLoggedIn()) {
            $_SESSION['last_activity'] = time();
        }
    }
}
?>