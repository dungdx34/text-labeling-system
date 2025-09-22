<?php
// emergency_create_db.php - API để tạo database và tables

header('Content-Type: text/plain; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    // Kết nối MySQL không chỉ định database
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    if ($action === 'create_database') {
        // Tạo database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS text_labeling_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✅ Tạo database 'text_labeling_system' thành công!";
        
    } elseif ($action === 'create_tables') {
        // Chọn database
        $pdo->exec("USE text_labeling_system");
        
        // Tạo bảng users
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(100),
                role ENUM('admin', 'labeler', 'reviewer') NOT NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Tạo bảng documents
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                ai_summary TEXT NOT NULL,
                type ENUM('single', 'multi') DEFAULT 'single',
                group_title VARCHAR(255) NULL,
                group_description TEXT NULL,
                group_summary TEXT NULL,
                uploaded_by INT NOT NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Tạo bảng assignments
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                document_id INT NULL,
                group_id INT NULL,
                type ENUM('single', 'multi') NOT NULL,
                status ENUM('pending', 'in_progress', 'completed', 'reviewed') DEFAULT 'pending',
                assigned_by INT NOT NULL,
                deadline DATE NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Tạo bảng document_groups
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS document_groups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                ai_summary TEXT NOT NULL,
                uploaded_by INT NOT NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Tạo bảng group_documents
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS group_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                document_id INT NOT NULL,
                order_index INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Tạo bảng labeling_results
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS labeling_results (
                id INT AUTO_INCREMENT PRIMARY KEY,
                assignment_id INT NOT NULL,
                document_id INT NOT NULL,
                selected_sentences JSON,
                writing_style VARCHAR(100),
                edited_summary TEXT,
                step1_completed BOOLEAN DEFAULT FALSE,
                step2_completed BOOLEAN DEFAULT FALSE,
                step3_completed BOOLEAN DEFAULT FALSE,
                auto_saved_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Tạo bảng reviews
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                assignment_id INT NOT NULL,
                reviewer_id INT NOT NULL,
                rating INT CHECK (rating >= 1 AND rating <= 5),
                comments TEXT,
                feedback JSON,
                status ENUM('pending', 'approved', 'rejected', 'needs_revision') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Tạo bảng activity_logs
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                target_type VARCHAR(50),
                target_id INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        echo "✅ Tạo tất cả bảng thành công!\n";
        echo "📋 Các bảng đã tạo:\n";
        echo "- users\n- documents\n- assignments\n- document_groups\n- group_documents\n- labeling_results\n- reviews\n- activity_logs";
        
        // Tạo tài khoản admin mặc định nếu chưa có
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        $stmt->execute();
        $admin_exists = $stmt->fetchColumn() > 0;
        
        if (!$admin_exists) {
            $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['admin', $admin_password, 'Administrator', 'admin@example.com', 'admin']);
            echo "\n\n✅ Tạo tài khoản admin mặc định:\n";
            echo "Username: admin\n";
            echo "Password: admin123";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}
?>