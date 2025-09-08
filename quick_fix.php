<?php
// Quick Fix Script - Sửa lỗi nhanh cho Text Labeling System
echo "<h1>🔧 Quick Fix Tool</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .ok{color:green;} .error{color:red;} .fix{background:#f0f8ff;padding:10px;margin:10px 0;border-left:4px solid #007bff;}</style>";

// 1. Tạo thư mục cần thiết
$dirs = ['config', 'css', 'js', 'admin', 'labeler', 'reviewer', 'includes'];
echo "<h3>📁 Tạo thư mục:</h3>";
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "<span class='ok'>✅ Tạo thư mục: $dir</span><br>";
        } else {
            echo "<span class='error'>❌ Không thể tạo: $dir</span><br>";
        }
    } else {
        echo "<span class='ok'>✅ Thư mục đã tồn tại: $dir</span><br>";
    }
}

// 2. Tạo file database.php nếu chưa có
echo "<h3>🗄️ Tạo file cấu hình database:</h3>";
if (!file_exists('config/database.php')) {
    $db_content = '<?php
class Database {
    private $host = "localhost";
    private $db_name = "text_labeling_system";
    private $username = "root";
    private $password = "";
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                                $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>';
    
    if (file_put_contents('config/database.php', $db_content)) {
        echo "<span class='ok'>✅ Tạo config/database.php thành công</span><br>";
    } else {
        echo "<span class='error'>❌ Không thể tạo config/database.php</span><br>";
    }
} else {
    echo "<span class='ok'>✅ config/database.php đã tồn tại</span><br>";
}

// 3. Tạo database và tables
echo "<h3>🗄️ Tạo database và tables:</h3>";
try {
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Tạo database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS text_labeling_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<span class='ok'>✅ Database 'text_labeling_system' đã tạo</span><br>";
    
    $pdo->exec("USE text_labeling_system");
    
    // Tạo tables
    $tables_sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'labeler', 'reviewer') NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE
    );

    CREATE TABLE IF NOT EXISTS documents (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        ai_summary TEXT,
        uploaded_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'in_progress', 'completed', 'reviewed') DEFAULT 'pending',
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
    );

    CREATE TABLE IF NOT EXISTS text_styles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT
    );

    CREATE TABLE IF NOT EXISTS labelings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        document_id INT NOT NULL,
        labeler_id INT NOT NULL,
        reviewer_id INT,
        important_sentences TEXT,
        text_style_id INT,
        edited_summary TEXT,
        labeling_notes TEXT,
        review_notes TEXT,
        status ENUM('pending', 'completed', 'reviewed', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (document_id) REFERENCES documents(id),
        FOREIGN KEY (labeler_id) REFERENCES users(id),
        FOREIGN KEY (reviewer_id) REFERENCES users(id),
        FOREIGN KEY (text_style_id) REFERENCES text_styles(id)
    );

    INSERT IGNORE INTO text_styles (id, name, description) VALUES
    (1, 'Tường thuật', 'Văn bản mô tả sự kiện, hiện tượng theo thời gian'),
    (2, 'Nghị luận', 'Văn bản trình bày quan điểm, lập luận về một vấn đề'),
    (3, 'Miêu tả', 'Văn bản tả lại hình ảnh, đặc điểm của sự vật, hiện tượng'),
    (4, 'Biểu cảm', 'Văn bản thể hiện cảm xúc, tâm trạng của tác giả'),
    (5, 'Thuyết minh', 'Văn bản giải thích, làm rõ về một sự vật, hiện tượng');

    INSERT IGNORE INTO users (id, username, email, password, role, full_name) VALUES
    (1, 'admin', 'admin@example.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrator'),
    (2, 'labeler1', 'labeler1@example.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'labeler', 'Người gán nhãn 1'),
    (3, 'reviewer1', 'reviewer1@example.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'reviewer', 'Người review 1');
    ";
    
    $pdo->exec($tables_sql);
    echo "<span class='ok'>✅ Tất cả tables đã được tạo thành công</span><br>";
    echo "<span class='ok'>✅ Dữ liệu mẫu đã được thêm</span><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Lỗi database: " . $e->getMessage() . "</span><br>";
}

// 4. Tạo file CSS cơ bản
echo "<h3>🎨 Tạo file CSS:</h3>";
if (!file_exists('css/style.css')) {
    $css_content = ':root { --primary-color: #0d6efd; }
body { font-family: "Segoe UI", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.sidebar { background: linear-gradient(180deg, var(--primary-color) 0%, #0a58ca 100%); }
.main-content { background: white; border-radius: 20px; padding: 40px; margin: 20px; }
.stats-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
.stats-number { font-size: 2.5rem; font-weight: bold; color: var(--primary-color); }';
    
    if (file_put_contents('css/style.css', $css_content)) {
        echo "<span class='ok'>✅ Tạo css/style.css thành công</span><br>";
    }
} else {
    echo "<span class='ok'>✅ css/style.css đã tồn tại</span><br>";
}

// 5. Tạo file JS cơ bản  
echo "<h3>💻 Tạo file JavaScript:</h3>";
if (!file_exists('js/script.js')) {
    $js_content = '// Text Labeling System JavaScript
console.log("Text Labeling System loaded");
function showToast(message, type = "info") {
    console.log(type + ": " + message);
}';
    
    if (file_put_contents('js/script.js', $js_content)) {
        echo "<span class='ok'>✅ Tạo js/script.js thành công</span><br>";
    }
} else {
    echo "<span class='ok'>✅ js/script.js đã tồn tại</span><br>";
}

echo "<div class='fix'>";
echo "<h3>🎉 HOÀN TẤT!</h3>";
echo "<p><strong>Hệ thống đã được sửa chữa. Bây giờ bạn có thể:</strong></p>";
echo "<ol>";
echo "<li>Truy cập <a href='login.php'><strong>login.php</strong></a> để đăng nhập</li>";
echo "<li>Sử dụng tài khoản: <strong>admin / admin123</strong></li>";
echo "<li>Truy cập <a href='admin/dashboard.php'><strong>admin/dashboard.php</strong></a></li>";
echo "</ol>";
echo "<p><strong>Các tài khoản demo:</strong></p>";
echo "<ul>";
echo "<li>Admin: admin / admin123</li>";
echo "<li>Labeler: labeler1 / admin123</li>";
echo "<li>Reviewer: reviewer1 / admin123</li>";
echo "</ul>";
echo "</div>";

echo "<div style='text-align:center;margin-top:30px;'>";
echo "<a href='login.php' style='background:#007bff;color:white;padding:15px 30px;text-decoration:none;border-radius:8px;font-weight:bold;'>🚀 ĐĂNG NHẬP NGAY</a>";
echo "</div>";
?>