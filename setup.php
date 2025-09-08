<?php
// Text Labeling System Setup & Diagnostic Tool
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if setup is already completed
$setup_complete = false;
if (file_exists('config/database.php')) {
    try {
        require_once 'config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        if ($conn) {
            // Check if tables exist
            $stmt = $conn->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() > 0) {
                $setup_complete = true;
            }
        }
    } catch (Exception $e) {
        // Database connection failed
    }
}

// Handle form submission
$message = '';
$error = '';

if ($_POST && !$setup_complete) {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? 'text_labeling_system';
    $db_user = $_POST['db_user'] ?? 'root';
    $db_pass = $_POST['db_pass'] ?? '';
    
    try {
        // Test database connection
        $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");
        
        // Create config file
        $config_content = "<?php
class Database {
    private \$host = '$db_host';
    private \$db_name = '$db_name';
    private \$username = '$db_user';
    private \$password = '$db_pass';
    private \$conn;
    
    public function getConnection() {
        \$this->conn = null;
        try {
            \$this->conn = new PDO(\"mysql:host=\" . \$this->host . \";dbname=\" . \$this->db_name . \";charset=utf8mb4\", 
                                \$this->username, \$this->password);
            \$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException \$exception) {
            echo \"Connection error: \" . \$exception->getMessage();
        }
        return \$this->conn;
    }
}
?>";
        
        if (!is_dir('config')) {
            mkdir('config', 0755, true);
        }
        file_put_contents('config/database.php', $config_content);
        
        // Create tables
        $sql = "
        -- Users table
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

        -- Documents table
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

        -- Text styles table
        CREATE TABLE IF NOT EXISTS text_styles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT
        );

        -- Labelings table
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

        -- Insert default text styles
        INSERT IGNORE INTO text_styles (id, name, description) VALUES
        (1, 'Tường thuật', 'Văn bản mô tả sự kiện, hiện tượng theo thời gian'),
        (2, 'Nghị luận', 'Văn bản trình bày quan điểm, lập luận về một vấn đề'),
        (3, 'Miêu tả', 'Văn bản tả lại hình ảnh, đặc điểm của sự vật, hiện tượng'),
        (4, 'Biểu cảm', 'Văn bản thể hiện cảm xúc, tâm trạng của tác giả'),
        (5, 'Thuyết minh', 'Văn bản giải thích, làm rõ về một sự vật, hiện tượng');

        -- Insert default admin user (password: admin123)
        INSERT IGNORE INTO users (id, username, email, password, role, full_name) VALUES
        (1, 'admin', 'admin@example.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrator');

        -- Insert demo users
        INSERT IGNORE INTO users (id, username, email, password, role, full_name) VALUES
        (2, 'labeler1', 'labeler1@example.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'labeler', 'Người gán nhãn 1'),
        (3, 'reviewer1', 'reviewer1@example.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'reviewer', 'Người review 1');
        ";
        
        $pdo->exec($sql);
        
        $message = 'Cài đặt thành công! Hệ thống đã sẵn sàng sử dụng.';
        $setup_complete = true;
        
    } catch (Exception $e) {
        $error = 'Lỗi cài đặt: ' . $e->getMessage();
    }
}

// System diagnostics
function checkSystemRequirements() {
    $checks = [];
    
    // PHP Version
    $checks['php_version'] = [
        'name' => 'PHP Version',
        'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'message' => 'PHP ' . PHP_VERSION . (version_compare(PHP_VERSION, '7.4.0', '>=') ? ' ✅' : ' ❌ (Yêu cầu PHP 7.4+)')
    ];
    
    // PDO Extension
    $checks['pdo'] = [
        'name' => 'PDO Extension',
        'status' => extension_loaded('pdo'),
        'message' => extension_loaded('pdo') ? 'PDO available ✅' : 'PDO not available ❌'
    ];
    
    // PDO MySQL
    $checks['pdo_mysql'] = [
        'name' => 'PDO MySQL',
        'status' => extension_loaded('pdo_mysql'),
        'message' => extension_loaded('pdo_mysql') ? 'PDO MySQL available ✅' : 'PDO MySQL not available ❌'
    ];
    
    // File permissions
    $checks['file_permissions'] = [
        'name' => 'File Permissions',
        'status' => is_writable('.'),
        'message' => is_writable('.') ? 'Directory writable ✅' : 'Directory not writable ❌'
    ];
    
    return $checks;
}

$system_checks = checkSystemRequirements();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Text Labeling System - Setup</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .setup-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .setup-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 600px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .requirement-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .requirement-item.success {
            background: #d1edff;
            border-left: 4px solid #28a745;
        }
        .requirement-item.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .logo {
            background: linear-gradient(135deg, #0d6efd 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="text-center mb-4">
                <h1 class="logo mb-3">
                    <i class="fas fa-tags"></i>
                    Text Labeling System
                </h1>
                <p class="text-muted">
                    <?php echo $setup_complete ? 'Hệ thống đã được cài đặt' : 'Trình cài đặt hệ thống'; ?>
                </p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- System Requirements Check -->
            <div class="mb-4">
                <h5><i class="fas fa-cog me-2"></i>Kiểm tra hệ thống</h5>
                <?php foreach ($system_checks as $check): ?>
                    <div class="requirement-item <?php echo $check['status'] ? 'success' : 'error'; ?>">
                        <strong><?php echo $check['name']; ?>:</strong> <?php echo $check['message']; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($setup_complete): ?>
                <!-- Setup Complete -->
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        <h3 class="text-success mt-3">Cài đặt hoàn tất!</h3>
                        <p class="text-muted">Hệ thống Text Labeling đã sẵn sàng sử dụng.</p>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-shield text-danger fa-2x mb-2"></i>
                                    <h6>Admin</h6>
                                    <small class="text-muted">admin / admin123</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-tags text-primary fa-2x mb-2"></i>
                                    <h6>Labeler</h6>
                                    <small class="text-muted">labeler1 / admin123</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-double text-success fa-2x mb-2"></i>
                                    <h6>Reviewer</h6>
                                    <small class="text-muted">reviewer1 / admin123</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="login.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập hệ thống
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>Về trang chủ
                        </a>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded">
                        <h6><i class="fas fa-info-circle me-2"></i>Thông tin quan trọng:</h6>
                        <ul class="text-start mb-0">
                            <li>Đổi mật khẩu mặc định sau khi đăng nhập</li>
                            <li>Xóa file setup.php sau khi cài đặt xong</li>
                            <li>Backup database thường xuyên</li>
                            <li>Cấu hình HTTPS cho production</li>
                        </ul>
                    </div>
                </div>

            <?php else: ?>
                <!-- Setup Form -->
                <form method="POST">
                    <h5><i class="fas fa-database me-2"></i>Cấu hình cơ sở dữ liệu</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="db_host" class="form-label">Database Host</label>
                            <input type="text" class="form-control" id="db_host" name="db_host" 
                                   value="<?php echo $_POST['db_host'] ?? 'localhost'; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="db_name" class="form-label">Database Name</label>
                            <input type="text" class="form-control" id="db_name" name="db_name" 
                                   value="<?php echo $_POST['db_name'] ?? 'text_labeling_system'; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="db_user" class="form-label">Database Username</label>
                            <input type="text" class="form-control" id="db_user" name="db_user" 
                                   value="<?php echo $_POST['db_user'] ?? 'root'; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="db_pass" class="form-label">Database Password</label>
                            <input type="password" class="form-control" id="db_pass" name="db_pass" 
                                   value="<?php echo $_POST['db_pass'] ?? ''; ?>">
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Lưu ý:</strong> Database sẽ được tạo tự động nếu chưa tồn tại. 
                        Đảm bảo MySQL/MariaDB đang chạy và thông tin đăng nhập chính xác.
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg" 
                                <?php echo !array_reduce($system_checks, function($carry, $check) { return $carry && $check['status']; }, true) ? 'disabled' : ''; ?>>
                            <i class="fas fa-rocket me-2"></i>Cài đặt hệ thống
                        </button>
                    </div>
                </form>

                <?php if (!array_reduce($system_checks, function($carry, $check) { return $carry && $check['status']; }, true)): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Cảnh báo:</strong> Một số yêu cầu hệ thống chưa được đáp ứng. 
                        Vui lòng khắc phục các vấn đề trên trước khi tiếp tục.
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <h6><i class="fas fa-question-circle me-2"></i>Cần hỗ trợ?</h6>
                    <p class="text-muted small mb-2">
                        Nếu gặp vấn đề trong quá trình cài đặt:
                    </p>
                    <ul class="small text-muted">
                        <li>Kiểm tra MySQL/MariaDB đã khởi động</li>
                        <li>Xác minh thông tin đăng nhập database</li>
                        <li>Đảm bảo PHP extensions được cài đặt</li>
                        <li>Kiểm tra quyền ghi file/thư mục</li>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <small class="text-muted">
                    Text Labeling System v1.0.0 | 
                    <a href="https://github.com/your-repo" target="_blank" class="text-decoration-none">
                        <i class="fab fa-github"></i> GitHub
                    </a>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Test database connection button
        function testConnection() {
            const formData = new FormData();
            formData.append('test_connection', '1');
            formData.append('db_host', document.getElementById('db_host').value);
            formData.append('db_user', document.getElementById('db_user').value);
            formData.append('db_pass', document.getElementById('db_pass').value);
            
            fetch('setup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Handle test connection response
                console.log('Connection test result:', data);
            })
            .catch(error => {
                console.error('Connection test error:', error);
            });
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);

        // Form validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const dbHost = document.getElementById('db_host').value.trim();
            const dbName = document.getElementById('db_name').value.trim();
            const dbUser = document.getElementById('db_user').value.trim();
            
            if (!dbHost || !dbName || !dbUser) {
                e.preventDefault();
                alert('Vui lòng điền đầy đủ thông tin database.');
                return false;
            }
            
            // Show loading state
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang cài đặt...';
            button.disabled = true;
        });
    </script>
</body>
</html>
                