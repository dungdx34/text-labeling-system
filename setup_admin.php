<?php
// setup_admin.php - Chạy file này để tạo/reset tài khoản admin

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("Không thể kết nối database!");
    }
    
    echo "<h2>Thiết lập tài khoản admin</h2>";
    
    // Xóa tài khoản admin cũ nếu có
    $query = "DELETE FROM users WHERE username = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    echo "<p>✅ Đã xóa tài khoản admin cũ (nếu có)</p>";
    
    // Tạo mật khẩu hash cho các tài khoản mặc định
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $labeler_password = password_hash('labeler123', PASSWORD_DEFAULT);
    $reviewer_password = password_hash('reviewer123', PASSWORD_DEFAULT);
    
    // Tạo tài khoản admin
    $query = "INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    // Admin account
    $stmt->execute([
        'admin',
        $admin_password,
        'Administrator',
        'admin@example.com',
        'admin',
        'active'
    ]);
    echo "<p>✅ Tạo tài khoản Admin: <strong>admin</strong> / <strong>admin123</strong></p>";
    
    // Kiểm tra và tạo labeler nếu chưa có
    $query = "SELECT COUNT(*) FROM users WHERE username = 'labeler1'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $query = "INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        // Labeler account
        $stmt->execute([
            'labeler1',
            $labeler_password,
            'Người gán nhãn 1',
            'labeler1@example.com',
            'labeler',
            'active'
        ]);
        echo "<p>✅ Tạo tài khoản Labeler: <strong>labeler1</strong> / <strong>labeler123</strong></p>";
    } else {
        echo "<p>ℹ️ Tài khoản labeler1 đã tồn tại</p>";
    }
    
    // Kiểm tra và tạo reviewer nếu chưa có
    $query = "SELECT COUNT(*) FROM users WHERE username = 'reviewer1'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $query = "INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        // Reviewer account
        $stmt->execute([
            'reviewer1',
            $reviewer_password,
            'Người đánh giá 1',
            'reviewer1@example.com',
            'reviewer',
            'active'
        ]);
        echo "<p>✅ Tạo tài khoản Reviewer: <strong>reviewer1</strong> / <strong>reviewer123</strong></p>";
    } else {
        echo "<p>ℹ️ Tài khoản reviewer1 đã tồn tại</p>";
    }
    
    // Hiển thị tất cả tài khoản hiện có
    echo "<h3>Danh sách tài khoản trong hệ thống:</h3>";
    $query = "SELECT username, full_name, role, status FROM users ORDER BY role, username";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>Username</th><th>Họ tên</th><th>Vai trò</th><th>Trạng thái</th></tr>";
    foreach ($users as $user) {
        $role_color = '';
        switch($user['role']) {
            case 'admin': $role_color = 'background: #dc3545; color: white;'; break;
            case 'labeler': $role_color = 'background: #007bff; color: white;'; break;
            case 'reviewer': $role_color = 'background: #28a745; color: white;'; break;
        }
        
        echo "<tr>";
        echo "<td><strong>{$user['username']}</strong></td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td style='$role_color'>{$user['role']}</td>";
        echo "<td>{$user['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><div style='background: #d4edda; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
    echo "<h3>🎉 Thiết lập hoàn tất!</h3>";
    echo "<p><strong>Bây giờ bạn có thể đăng nhập với:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin / admin123</li>";
    echo "<li><strong>Labeler:</strong> labeler1 / labeler123</li>";
    echo "<li><strong>Reviewer:</strong> reviewer1 / reviewer123</li>";
    echo "</ul>";
    echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Đi đến trang đăng nhập</a></p>";
    echo "</div>";
    
    // Test kết nối
    echo "<hr>";
    echo "<h3>Test thông tin database:</h3>";
    $info = $database->getDatabaseInfo();
    if ($info) {
        echo "<p>📍 Database: <strong>{$info['db_name']}</strong></p>";
        echo "<p>📍 MySQL Version: <strong>{$info['version']}</strong></p>";
    }
    
    $tables = $database->getTables();
    echo "<p>📍 Bảng trong DB: <strong>" . implode(', ', $tables) . "</strong></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
    echo "<h3>❌ Lỗi:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p><strong>Kiểm tra lại:</strong></p>";
    echo "<ul>";
    echo "<li>Database đã được tạo chưa?</li>";
    echo "<li>File config/database.php có đúng thông tin kết nối?</li>";
    echo "<li>MySQL service có đang chạy?</li>";
    echo "</ul>";
    echo "</div>";
}
?>

<style>
body {
    font-family: 'Segoe UI', sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #f8f9fa;
}
table {
    margin: 10px 0;
}
th, td {
    padding: 8px 12px;
    text-align: left;
}
</style>