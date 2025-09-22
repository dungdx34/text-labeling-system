<?php
// check_accounts.php - Kiểm tra và debug tài khoản

require_once 'config/database.php';

echo "<h2>🔍 Kiểm tra tài khoản trong hệ thống</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Không thể kết nối database!");
    }
    
    echo "<p>✅ Kết nối database thành công!</p>";
    
    // Kiểm tra bảng users có tồn tại không
    $tables = $database->getTables();
    if (!in_array('users', $tables)) {
        echo "<p>❌ Bảng 'users' không tồn tại! Cần chạy file database.sql</p>";
        echo "<p><strong>Các bảng hiện có:</strong> " . implode(', ', $tables) . "</p>";
        exit;
    }
    
    echo "<p>✅ Bảng users đã tồn tại</p>";
    
    // Lấy tất cả tài khoản
    $query = "SELECT id, username, full_name, role, status, created_at FROM users ORDER BY id";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>⚠️ Không có tài khoản nào trong hệ thống!</h3>";
        echo "<p>Cần tạo tài khoản admin mặc định.</p>";
        echo "<p><a href='setup_admin.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 Chạy setup admin</a></p>";
        echo "</div>";
    } else {
        echo "<h3>📋 Danh sách tài khoản ({count($users)} tài khoản):</h3>";
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #e9ecef;'>";
        echo "<th>ID</th><th>Username</th><th>Họ tên</th><th>Vai trò</th><th>Trạng thái</th><th>Ngày tạo</th><th>Test Login</th>";
        echo "</tr>";
        
        foreach ($users as $user) {
            $role_color = '';
            switch($user['role']) {
                case 'admin': $role_color = 'background: #dc3545; color: white;'; break;
                case 'labeler': $role_color = 'background: #007bff; color: white;'; break;
                case 'reviewer': $role_color = 'background: #28a745; color: white;'; break;
            }
            
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td><strong>{$user['username']}</strong></td>";
            echo "<td>{$user['full_name']}</td>";
            echo "<td style='$role_color; text-align: center;'>{$user['role']}</td>";
            echo "<td>{$user['status']}</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($user['created_at'])) . "</td>";
            echo "<td><button onclick=\"testLogin('{$user['username']}')\">Test</button></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test password cho tài khoản admin
    $query = "SELECT password FROM users WHERE username = 'admin' LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "<h3>🔐 Test mật khẩu admin:</h3>";
        
        $passwords_to_test = ['admin123', 'password', 'admin', '123456'];
        
        foreach ($passwords_to_test as $test_password) {
            $is_valid = password_verify($test_password, $admin['password']);
            $status = $is_valid ? "✅ ĐÚNG" : "❌ SAI";
            $color = $is_valid ? "color: green;" : "color: red;";
            echo "<p style='$color'><strong>$test_password</strong> → $status</p>";
        }
        
        echo "<div style='background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "<p><strong>Hash hiện tại của admin:</strong></p>";
        echo "<code style='word-break: break-all;'>{$admin['password']}</code>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h3>❌ Lỗi:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

?>

<script>
function testLogin(username) {
    const password = prompt(`Nhập mật khẩu để test cho user: ${username}`);
    if (password) {
        // Tạo form ẩn để test login
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'test_login_api.php';
        form.style.display = 'none';
        
        const usernameInput = document.createElement('input');
        usernameInput.name = 'username';
        usernameInput.value = username;
        
        const passwordInput = document.createElement('input');
        passwordInput.name = 'password';
        passwordInput.value = password;
        
        form.appendChild(usernameInput);
        form.appendChild(passwordInput);
        document.body.appendChild(form);
        
        form.submit();
    }
}
</script>

<style>
body {
    font-family: 'Segoe UI', sans-serif;
    max-width: 900px;
    margin: 20px auto;
    padding: 20px;
    background: #f8f9fa;
}
table {
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
th, td {
    padding: 10px;
    text-align: left;
    border: 1px solid #dee2e6;
}
th {
    background: #e9ecef;
    font-weight: 600;
}
button {
    background: #007bff;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
}
button:hover {
    background: #0056b3;
}
code {
    background: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
}
</style>