<?php
session_start();

// Log hoạt động logout nếu user đã đăng nhập
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $query = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                      VALUES (?, 'user_logout', 'User đăng xuất khỏi hệ thống', ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
    } catch (Exception $e) {
        // Không làm gì nếu log thất bại, vẫn tiếp tục logout
        error_log("Logout logging failed: " . $e->getMessage());
    }
}

// Xóa tất cả session variables
$_SESSION = array();

// Nếu có session cookie, xóa nó
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy session
session_destroy();

// Redirect về trang login với thông báo
header('Location: login.php?message=logout_success');
exit();
?>