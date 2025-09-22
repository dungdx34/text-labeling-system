<?php
// test_login_api.php - API để test login

require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die('Chỉ chấp nhận POST request');
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

echo "<h2>🧪 Kết quả test login</h2>";
echo "<p><strong>Username:</strong> $username</p>";
echo "<p><strong>Password:</strong> " . str_repeat('*', strlen($password)) . "</p>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Không thể kết nối database");
    }
    
    // Tìm user
    $query = "SELECT id, username, password, role, full_name, status FROM users WHERE username = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
        echo "<h3>❌ Kết quả: THẤT BẠI</h3>";
        echo "<p><strong>Lý do:</strong> Không tìm thấy username '$username'</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>✅ Tìm thấy user:</h3>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> {$user['id']}</li>";
        echo "<li><strong>Username:</strong> {$user['username']}</li>";
        echo "<li><strong>Họ tên:</strong> {$user['full_name']}</li>";
        echo "<li><strong>Vai trò:</strong> {$user['role']}</li>";
        echo "<li><strong>Trạng thái:</strong> {$user['status']}</li>";
        echo "</ul>";
        echo "</div>";
        
        if ($user['status'] != 'active') {
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
            echo "<h3>⚠️ Tài khoản không hoạt động</h3>";
            echo "<p>Tài khoản có trạng thái: <strong>{$user['status']}</strong></p>";
            echo "</div>";
        }
        
        // Test password
        $password_match = password_verify($password, $user['password']);
        
        if ($password_match) {
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
            echo "<h3>🎉 TEST LOGIN: THÀNH CÔNG!</h3>";
            echo "<p>✅ Mật khẩu chính xác</p>";
            echo "<p>✅ Tài khoản hoạt động</p>";
            echo "<p><strong>Có thể đăng nhập bình thường</strong></p>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
            echo "<h3>❌ TEST LOGIN: THẤT BẠI!</h3>";
            echo "<p><strong>Lý do:</strong> Mật khẩu không đúng</p>";
            echo "<p><strong>Hash trong DB:</strong></p>";
            echo "<code style='word-break: break-all; background: #f8f9fa; padding: 5px; display: block;'>{$user['password']}</code>";
            echo "</div>";
            
            // Thử các mật khẩu phổ biến
            echo "<h4>🔍 Test các mật khẩu phổ biến:</h4>";
            $common_passwords = ['admin123', 'password', 'admin', '123456', 'labeler123', 'reviewer123'];
            
            foreach ($common_passwords as $test_pass) {
                $test_result = password_verify($test_pass, $user['password']);
                $status = $test_result ? "✅ KHỚP!" : "❌ không khớp";
                $color = $test_result ? "color: green; font-weight: bold;" : "color: #666;";
                echo "<p style='$color'>$test_pass → $status</p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h3>❌ Lỗi hệ thống:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<br>";
echo "<p><a href='check_accounts.php' style='background: #6c757d; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>← Quay lại kiểm tra accounts</a></p>";
echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>🚀 Đi đến trang login</a></p>";
?>

<style>
body {
    font-family: 'Segoe UI', sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #f8f9fa;
}
code {
    font-family: 'Courier New', monospace;
    font-size: 12px;
}
</style>