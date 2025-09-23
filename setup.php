<?php
// Setup script to initialize the database and create required tables
require_once 'config/database.php';

$database = new Database();
$setup_messages = [];
$errors = [];

try {
    // Try to create database first
    $db = $database->createDatabase();
    
    if ($db) {
        $setup_messages[] = "✓ Database connection successful";
        
        // Read and execute SQL file
        $sql_file = 'database.sql';
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
            
            // Split SQL into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql_content)));
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $db->exec($statement);
                    } catch (PDOException $e) {
                        // Ignore "table exists" errors
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            throw $e;
                        }
                    }
                }
            }
            
            $setup_messages[] = "✓ Database tables created successfully";
            
            // Check if admin user exists
            $query = "SELECT COUNT(*) FROM users WHERE role = 'admin'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $admin_count = $stmt->fetchColumn();
            
            if ($admin_count == 0) {
                // Create default admin user
                $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, password, email, full_name, role) VALUES 
                         ('admin', :password, 'admin@textlabeling.com', 'System Administrator', 'admin')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $admin_password);
                $stmt->execute();
                
                $setup_messages[] = "✓ Default admin user created (admin/admin123)";
            } else {
                $setup_messages[] = "✓ Admin user already exists";
            }
            
            // Create sample users if they don't exist
            $sample_users = [
                ['labeler1', 'password123', 'labeler1@test.com', 'Labeler User 1', 'labeler'],
                ['reviewer1', 'password123', 'reviewer1@test.com', 'Reviewer User 1', 'reviewer']
            ];
            
            foreach ($sample_users as $user) {
                $query = "SELECT COUNT(*) FROM users WHERE username = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user[0]]);
                
                if ($stmt->fetchColumn() == 0) {
                    $hashed_password = password_hash($user[1], PASSWORD_DEFAULT);
                    $query = "INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$user[0], $hashed_password, $user[2], $user[3], $user[4]]);
                    $setup_messages[] = "✓ Created user: {$user[0]} ({$user[4]})";
                }
            }
            
            // Create uploads directory if it doesn't exist
            if (!is_dir('uploads')) {
                mkdir('uploads', 0755, true);
                $setup_messages[] = "✓ Created uploads directory";
            }
            
            // Create samples directory and sample JSONL file
            if (!is_dir('samples')) {
                mkdir('samples', 0755, true);
                $setup_messages[] = "✓ Created samples directory";
            }
            
            // Create sample.jsonl file if it doesn't exist
            $sample_file = 'samples/sample.jsonl';
            if (!file_exists($sample_file)) {
                $sample_content = '{"type": "single", "title": "Tác động của AI trong giáo dục", "content": "Trí tuệ nhân tạo (AI) đang thay đổi cách chúng ta học và dạy. AI có thể cá nhân hóa quá trình học tập, tự động hóa việc chấm điểm và cung cấp phản hồi ngay lập tức cho học sinh.", "ai_summary": "AI đang cách mạng hóa giáo dục thông qua việc cá nhân hóa học tập, tự động hóa đánh giá và hỗ trợ giáo viên."}' . "\n" .
'{"type": "multi", "group_title": "Công nghệ Blockchain", "group_description": "Tổng quan về blockchain", "group_summary": "Blockchain là công nghệ cơ sở cho cryptocurrency, mang lại tính minh bạch và bảo mật.", "documents": [{"title": "Blockchain là gì?", "content": "Blockchain là một cơ sở dữ liệu phân tán được duy trì bởi một mạng lưới các máy tính."}, {"title": "Bitcoin", "content": "Bitcoin được tạo ra vào năm 2009 như một hệ thống thanh toán peer-to-peer."}]}';
                
                file_put_contents($sample_file, $sample_content);
                $setup_messages[] = "✓ Created sample JSONL file";
            }
            
        } else {
            $errors[] = "✗ database.sql file not found";
        }
        
    } else {
        $errors[] = "✗ Failed to connect to database";
    }
    
} catch (Exception $e) {
    $errors[] = "✗ Setup error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', sans-serif;
        }
        .setup-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .message-success {
            color: #28a745;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .message-error {
            color: #dc3545;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .next-steps {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h2 class="text-primary">
                <i class="fas fa-cogs me-2"></i>
                Text Labeling System Setup
            </h2>
            <p class="text-muted">Thiết lập ban đầu hệ thống</p>
        </div>

        <div class="setup-results">
            <?php if (!empty($setup_messages)): ?>
                <h5 class="text-success mb-3">
                    <i class="fas fa-check-circle me-2"></i>
                    Setup thành công
                </h5>
                <?php foreach ($setup_messages as $message): ?>
                    <div class="message-success">
                        <i class="fas fa-check me-2"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <h5 class="text-danger mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Có lỗi xảy ra
                </h5>
                <?php foreach ($errors as $error): ?>
                    <div class="message-error">
                        <i class="fas fa-times me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (empty($errors)): ?>
            <div class="next-steps">
                <h5>
                    <i class="fas fa-rocket me-2"></i>
                    Bước tiếp theo
                </h5>
                <ul class="list-unstyled mt-3">
                    <li class="mb-2">
                        <i class="fas fa-arrow-right me-2 text-primary"></i>
                        Truy cập <a href="login.php" class="fw-bold">trang đăng nhập</a>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-arrow-right me-2 text-primary"></i>
                        Đăng nhập với tài khoản admin: <code>admin / admin123</code>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-arrow-right me-2 text-primary"></i>
                        Thử upload file <code>samples/sample.jsonl</code>
                    </li>
                    <li>
                        <i class="fas fa-arrow-right me-2 text-primary"></i>
                        Tạo tasks và gán cho labelers để bắt đầu gán nhãn
                    </li>
                </ul>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Đăng nhập ngay
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center mt-4">
                <button onclick="location.reload()" class="btn btn-warning">
                    <i class="fas fa-redo me-2"></i>
                    Thử lại
                </button>
            </div>
        <?php endif; ?>

        <div class="mt-4 text-center">
            <small class="text-muted">
                Nếu có vấn đề, vui lòng kiểm tra cấu hình database trong <code>config/database.php</code>
            </small>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>