<?php
// Debug Login Issues - Text Labeling System
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Debug Login Issues</h2>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; }
.error { color: red; }
.warning { color: orange; }
.info { color: blue; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>";

// Step 1: Check database connection
echo "<h3>1. Kiểm tra kết nối database</h3>";
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            echo "<span class='success'>✅ Database connection: OK</span><br>";
            
            // Step 2: Check if tables exist
            echo "<h3>2. Kiểm tra bảng database</h3>";
            $tables = ['users', 'documents', 'text_styles', 'labelings'];
            $missing_tables = [];
            
            foreach ($tables as $table) {
                $stmt = $conn->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    echo "<span class='success'>✅ Bảng $table: Tồn tại</span><br>";
                } else {
                    echo "<span class='error'>❌ Bảng $table: Không tồn tại</span><br>";
                    $missing_tables[] = $table;
                }
            }
            
            if (empty($missing_tables)) {
                // Step 3: Check users
                echo "<h3>3. Kiểm tra người dùng</h3>";
                $stmt = $conn->query("SELECT id, username, role, is_active FROM users");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($users)) {
                    echo "<span class='error'>❌ Không có người dùng nào trong database</span><br>";
                    echo "<button onclick='createUsers()' style='background: green; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer;'>Tạo người dùng demo</button>";
                } else {
                    echo "<span class='success'>✅ Tìm thấy " . count($users) . " người dùng:</span><br>";
                    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
                    echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Active</th><th>Password Test</th></tr>";
                    
                    foreach ($users as $user) {
                        $password_test = password_verify('admin123', $user['id'] == 1 ? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' : 'unknown');
                        echo "<tr>";
                        echo "<td>{$user['id']}</td>";
                        echo "<td>{$user['username']}</td>";
                        echo "<td>{$user['role']}</td>";
                        echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
                        echo "<td>" . ($password_test ? '✅' : '❌') . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
                
                // Step 4: Test login
                echo "<h3>4. Test đăng nhập</h3>";
                if ($_POST && isset($_POST['test_login'])) {
                    $test_username = $_POST['username'];
                    $test_password = $_POST['password'];
                    
                    $query = "SELECT id, username, password, role, full_name FROM users WHERE username = :username AND is_active = 1";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':username', $test_username);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo "<span class='info'>👤 Tìm thấy user: {$user['username']}</span><br>";
                        
                        if (password_verify($test_password, $user['password'])) {
                            echo "<span class='success'>✅ Mật khẩu đúng! Login sẽ thành công.</span><br>";
                        } else {
                            echo "<span class='error'>❌ Mật khẩu sai!</span><br>";
                            echo "<span class='info'>Stored hash: " . substr($user['password'], 0, 20) . "...</span><br>";
                        }
                    } else {
                        echo "<span class='error'>❌ Không tìm thấy user '$test_username' hoặc user bị vô hiệu hóa</span><br>";
                    }
                }
                
                echo "<form method='POST'>
                    <input type='hidden' name='test_login' value='1'>
                    <label>Username: </label>
                    <input type='text' name='username' value='admin' required>
                    <label>Password: </label>
                    <input type='password' name='password' value='admin123' required>
                    <button type='submit' style='background: blue; color: white; padding: 5px 10px; border: none; border-radius: 3px;'>Test Login</button>
                </form>";
                
            } else {
                echo "<span class='error'>❌ Thiếu bảng: " . implode(', ', $missing_tables) . "</span><br>";
                echo "<button onclick='createTables()' style='background: orange; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer;'>Tạo bảng thiếu</button>";
            }
            
        } else {
            echo "<span class='error'>❌ Database connection failed</span><br>";
        }
    } else {
        echo "<span class='error'>❌ File config/database.php không tồn tại</span><br>";
        echo "<a href='setup.php' style='background: red; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Chạy Setup</a>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Lỗi: " . $e->getMessage() . "</span><br>";
}

// Handle AJAX requests
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        require_once 'config/database.php';
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($_POST['action'] === 'create_users') {
            // Create demo users
            $users = [
                ['admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrator'],
                ['labeler1', 'labeler1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'labeler', 'Người gán nhãn 1'],
                ['reviewer1', 'reviewer1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'reviewer', 'Người review 1']
            ];
            
            $query = "INSERT IGNORE INTO users (username, email, password, role, full_name) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            
            $created = 0;
            foreach ($users as $user) {
                if ($stmt->execute($user)) {
                    $created++;
                }
            }
            
            echo json_encode(['success' => true, 'message' => "Đã tạo $created người dùng"]);
            exit;
        }
        
        if ($_POST['action'] === 'create_tables') {
            // Create missing tables
            $sql = "
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
            ";
            
            $conn->exec($sql);
            echo json_encode(['success' => true, 'message' => 'Đã tạo tất cả bảng cần thiết']);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>

<script>
function createUsers() {
    if (confirm('Tạo người dùng demo (admin, labeler1, reviewer1)?')) {
        fetch('debug_login.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=create_users'
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            location.reload();
        })
        .catch(error => {
            alert('Lỗi: ' + error);
        });
    }
}

function createTables() {
    if (confirm('Tạo tất cả bảng cần thiết cho database?')) {
        fetch('debug_login.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=create_tables'
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            location.reload();
        })
        .catch(error => {
            alert('Lỗi: ' + error);
        });
    }
}
</script>

<br><br>
<hr>
<h3>🔧 Hướng dẫn khắc phục:</h3>
<ol>
    <li><strong>Nếu thiếu config:</strong> Chạy <a href="setup.php">setup.php</a></li>
    <li><strong>Nếu thiếu bảng:</strong> Click nút "Tạo bảng thiếu"</li>
    <li><strong>Nếu không có user:</strong> Click nút "Tạo người dùng demo"</li>
    <li><strong>Nếu mật khẩu sai:</strong> Sử dụng test login để kiểm tra</li>
</ol>

<h3>📋 Tài khoản demo:</h3>
<ul>
    <li><strong>Admin:</strong> username = <code>admin</code>, password = <code>admin123</code></li>
    <li><strong>Labeler:</strong> username = <code>labeler1</code>, password = <code>admin123</code></li>
    <li><strong>Reviewer:</strong> username = <code>reviewer1</code>, password = <code>admin123</code></li>
</ul>