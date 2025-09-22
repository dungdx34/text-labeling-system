<?php
// fix_all_accounts.php - Sửa tất cả tài khoản login

require_once 'config/database.php';

echo "<h1>🔧 Sửa chữa tất cả tài khoản</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Không thể kết nối database!");
    }
    
    echo "<p style='color: green;'>✅ Kết nối database thành công</p>";
    
    // Xóa tất cả tài khoản cũ
    echo "<h2>🗑️ Xóa tài khoản cũ</h2>";
    $db->exec("DELETE FROM users");
    echo "<p>✅ Đã xóa tất cả tài khoản cũ</p>";
    
    // Reset AUTO_INCREMENT
    $db->exec("ALTER TABLE users AUTO_INCREMENT = 1");
    echo "<p>✅ Reset ID counter</p>";
    
    // Tạo tài khoản mới với mật khẩu rõ ràng
    echo "<h2>👥 Tạo tài khoản mới</h2>";
    
    $accounts = [
        [
            'username' => 'admin',
            'password' => 'admin123',
            'full_name' => 'Administrator',
            'email' => 'admin@example.com',
            'role' => 'admin'
        ],
        [
            'username' => 'labeler1',
            'password' => 'labeler123',
            'full_name' => 'Người gán nhãn 1',
            'email' => 'labeler1@example.com',
            'role' => 'labeler'
        ],
        [
            'username' => 'reviewer1',
            'password' => 'reviewer123',
            'full_name' => 'Người đánh giá 1',
            'email' => 'reviewer1@example.com',
            'role' => 'reviewer'
        ]
    ];
    
    $query = "INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, 'active')";
    $stmt = $db->prepare($query);
    
    foreach ($accounts as $account) {
        // Hash mật khẩu
        $hashed_password = password_hash($account['password'], PASSWORD_DEFAULT);
        
        $stmt->execute([
            $account['username'],
            $hashed_password,
            $account['full_name'],
            $account['email'],
            $account['role']
        ]);
        
        $user_id = $db->lastInsertId();
        
        echo "<div style='background: #d4edda; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
        echo "<p><strong>✅ Tạo thành công:</strong></p>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> $user_id</li>";
        echo "<li><strong>Username:</strong> {$account['username']}</li>";
        echo "<li><strong>Password:</strong> {$account['password']}</li>";
        echo "<li><strong>Role:</strong> {$account['role']}</li>";
        echo "<li><strong>Hash:</strong> <code style='font-size: 10px; word-break: break-all;'>$hashed_password</code></li>";
        echo "</ul>";
        echo "</div>";
    }
    
    // Kiểm tra lại tất cả tài khoản
    echo "<h2>🔍 Kiểm tra tài khoản đã tạo</h2>";
    
    $query = "SELECT id, username, full_name, role, status, created_at FROM users ORDER BY id";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; background: white;'>";
    echo "<tr style='background: #e9ecef;'>";
    echo "<th style='padding: 10px;'>ID</th>";
    echo "<th style='padding: 10px;'>Username</th>";
    echo "<th style='padding: 10px;'>Họ tên</th>";
    echo "<th style='padding: 10px;'>Role</th>";
    echo "<th style='padding: 10px;'>Status</th>";
    echo "<th style='padding: 10px;'>Test Login</th>";
    echo "</tr>";
    
    foreach ($users as $user) {
        $role_color = '';
        switch($user['role']) {
            case 'admin': $role_color = 'background: #dc3545; color: white;'; break;
            case 'labeler': $role_color = 'background: #007bff; color: white;'; break;
            case 'reviewer': $role_color = 'background: #28a745; color: white;'; break;
        }
        
        echo "<tr>";
        echo "<td style='padding: 10px; text-align: center;'>{$user['id']}</td>";
        echo "<td style='padding: 10px;'><strong>{$user['username']}</strong></td>";
        echo "<td style='padding: 10px;'>{$user['full_name']}</td>";
        echo "<td style='padding: 10px; text-align: center; $role_color'>{$user['role']}</td>";
        echo "<td style='padding: 10px; text-align: center;'>{$user['status']}</td>";
        echo "<td style='padding: 10px; text-align: center;'>";
        echo "<button onclick=\"testLogin('{$user['username']}')\">Test Login</button>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test login cho tất cả accounts
    echo "<h2>🧪 Test Login tự động</h2>";
    
    foreach ($accounts as $account) {
        echo "<h4>Test: {$account['username']}</h4>";
        
        // Lấy thông tin user từ DB
        $query = "SELECT password FROM users WHERE username = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$account['username']]);
        $stored_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stored_user) {
            // Test password
            $password_correct = password_verify($account['password'], $stored_user['password']);
            
            if ($password_correct) {
                echo "<p style='color: green; background: #d4edda; padding: 8px; border-radius: 4px;'>";
                echo "✅ <strong>{$account['username']}</strong> / <strong>{$account['password']}</strong> → LOGIN THÀNH CÔNG!";
                echo "</p>";
            } else {
                echo "<p style='color: red; background: #f8d7da; padding: 8px; border-radius: 4px;'>";
                echo "❌ <strong>{$account['username']}</strong> / <strong>{$account['password']}</strong> → LOGIN THẤT BẠI!";
                echo "</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ Không tìm thấy user {$account['username']}</p>";
        }
    }
    
    // Tổng kết
    echo "<div style='background: #d1ecf1; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h2>🎉 HOÀN TẤT!</h2>";
    echo "<h3>📋 Danh sách tài khoản LOGIN:</h3>";
    echo "<div style='display: flex; gap: 20px; flex-wrap: wrap;'>";
    
    foreach ($accounts as $account) {
        $bg_color = '';
        switch($account['role']) {
            case 'admin': $bg_color = '#dc3545'; break;
            case 'labeler': $bg_color = '#007bff'; break;
            case 'reviewer': $bg_color = '#28a745'; break;
        }
        
        echo "<div style='background: $bg_color; color: white; padding: 15px; border-radius: 8px; text-align: center; min-width: 200px;'>";
        echo "<h4 style='margin: 0 0 10px 0;'>" . strtoupper($account['role']) . "</h4>";
        echo "<p style='margin: 5px 0;'><strong>User:</strong> {$account['username']}</p>";
        echo "<p style='margin: 5px 0;'><strong>Pass:</strong> {$account['password']}</p>";
        echo "</div>";
    }
    
    echo "</div>";
    echo "<p style='text-align: center; margin: 20px 0;'>";
    echo "<a href='login.php' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-size: 18px;'>";
    echo "🚀 ĐI ĐẾN TRANG LOGIN";
    echo "</a>";
    echo "</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h3>❌ Lỗi:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

?>

<script>
function testLogin(username) {
    const passwords = {
        'admin': 'admin123',
        'labeler1': 'labeler123',
        'reviewer1': 'reviewer123'
    };
    
    const password = passwords[username];
    
    if (password) {
        // Tạo form để test
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'quick_login_test.php';
        form.target = '_blank';
        
        const userInput = document.createElement('input');
        userInput.type = 'hidden';
        userInput.name = 'username';
        userInput.value = username;
        
        const passInput = document.createElement('input');
        passInput.type = 'hidden';
        passInput.name = 'password';
        passInput.value = password;
        
        form.appendChild(userInput);
        form.appendChild(passInput);
        document.body.appendChild(form);
        
        form.submit();
        document.body.removeChild(form);
    } else {
        alert('Không tìm thấy mật khẩu cho user: ' + username);
    }
}
</script>

<style>
body {
    font-family: 'Segoe UI', sans-serif;
    max-width: 1000px;
    margin: 20px auto;
    padding: 20px;
    background: #f8f9fa;
}
button {
    background: #007bff;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}
button:hover {
    background: #0056b3;
}
table {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
code {
    background: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
}
</style>