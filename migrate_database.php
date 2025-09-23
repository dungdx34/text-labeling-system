<?php
// migrate_database.php - Cập nhật cấu trúc database
require_once 'config/database.php';

echo "<h2>Database Migration Tool</h2>";
echo "<p>Cập nhật cấu trúc database cho Text Labeling System</p>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h3>Bước 1: Kiểm tra và thêm cột 'type' vào bảng documents</h3>";
    
    // Kiểm tra xem cột type đã tồn tại chưa
    $checkColumn = $db->prepare("SHOW COLUMNS FROM documents LIKE 'type'");
    $checkColumn->execute();
    
    if ($checkColumn->rowCount() == 0) {
        // Thêm cột type
        $alterTable = "ALTER TABLE documents ADD COLUMN type ENUM('single', 'multi') DEFAULT 'single' AFTER ai_summary";
        $db->exec($alterTable);
        echo "<p style='color: green;'>✅ Đã thêm cột 'type' vào bảng documents</p>";
        
        // Cập nhật dữ liệu existing
        $updateData = "UPDATE documents SET type = 'single' WHERE type IS NULL";
        $db->exec($updateData);
        echo "<p style='color: green;'>✅ Đã cập nhật dữ liệu cột 'type'</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ Cột 'type' đã tồn tại trong bảng documents</p>";
    }
    
    echo "<h3>Bước 2: Kiểm tra bảng document_groups</h3>";
    
    // Tạo bảng document_groups nếu chưa tồn tại
    $createGroupsTable = "
    CREATE TABLE IF NOT EXISTS document_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        ai_summary TEXT,
        uploaded_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
    )";
    $db->exec($createGroupsTable);
    echo "<p style='color: green;'>✅ Bảng document_groups đã sẵn sàng</p>";
    
    echo "<h3>Bước 3: Kiểm tra bảng group_documents</h3>";
    
    // Tạo bảng group_documents nếu chưa tồn tại
    $createGroupDocsTable = "
    CREATE TABLE IF NOT EXISTS group_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        document_title VARCHAR(255) NOT NULL,
        document_content TEXT NOT NULL,
        position INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE
    )";
    $db->exec($createGroupDocsTable);
    echo "<p style='color: green;'>✅ Bảng group_documents đã sẵn sàng</p>";
    
    echo "<h3>Bước 4: Kiểm tra bảng labeling_tasks</h3>";
    
    // Kiểm tra cấu trúc bảng labeling_tasks
    $checkTasksTable = $db->prepare("SHOW TABLES LIKE 'labeling_tasks'");
    $checkTasksTable->execute();
    
    if ($checkTasksTable->rowCount() == 0) {
        $createTasksTable = "
        CREATE TABLE labeling_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NULL,
            group_id INT NULL,
            assigned_to INT NOT NULL,
            assigned_by INT NOT NULL,
            status ENUM('pending', 'in_progress', 'completed', 'reviewed') DEFAULT 'pending',
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            deadline DATE NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            reviewed_at TIMESTAMP NULL,
            notes TEXT,
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
            CHECK ((document_id IS NOT NULL AND group_id IS NULL) OR (document_id IS NULL AND group_id IS NOT NULL))
        )";
        $db->exec($createTasksTable);
        echo "<p style='color: green;'>✅ Đã tạo bảng labeling_tasks</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ Bảng labeling_tasks đã tồn tại</p>";
    }
    
    echo "<h3>Bước 5: Kiểm tra bảng activity_logs</h3>";
    
    // Kiểm tra bảng activity_logs
    $checkLogsTable = $db->prepare("SHOW TABLES LIKE 'activity_logs'");
    $checkLogsTable->execute();
    
    if ($checkLogsTable->rowCount() == 0) {
        $createLogsTable = "
        CREATE TABLE activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $db->exec($createLogsTable);
        echo "<p style='color: green;'>✅ Đã tạo bảng activity_logs</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ Bảng activity_logs đã tồn tại</p>";
    }
    
    echo "<h3>Bước 6: Kiểm tra dữ liệu mẫu</h3>";
    
    // Kiểm tra admin user
    $checkAdmin = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $checkAdmin->execute();
    
    if ($checkAdmin->fetchColumn() == 0) {
        // Tạo admin user mặc định (password: admin123)
        $insertAdmin = "INSERT INTO users (username, password, email, full_name, role) VALUES 
                       ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@textlabeling.com', 'Administrator', 'admin')";
        $db->exec($insertAdmin);
        echo "<p style='color: green;'>✅ Đã tạo tài khoản admin mặc định (username: admin, password: admin123)</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ Tài khoản admin đã tồn tại</p>";
    }
    
    // Thêm một số dữ liệu mẫu nếu bảng documents trống
    $checkDocs = $db->prepare("SELECT COUNT(*) FROM documents");
    $checkDocs->execute();
    
    if ($checkDocs->fetchColumn() == 0) {
        $sampleDoc = "INSERT INTO documents (title, content, ai_summary, type, uploaded_by) VALUES 
                     ('Báo cáo mẫu', 'Đây là nội dung báo cáo mẫu để test hệ thống gán nhãn văn bản.', 'Báo cáo mẫu cho việc test hệ thống.', 'single', 1)";
        $db->exec($sampleDoc);
        echo "<p style='color: green;'>✅ Đã thêm document mẫu</p>";
    }
    
    echo "<h3>Bước 7: Tạo indexes để tối ưu hiệu suất</h3>";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_documents_type ON documents(type)",
        "CREATE INDEX IF NOT EXISTS idx_documents_created_at ON documents(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_labeling_tasks_status ON labeling_tasks(status)",
        "CREATE INDEX IF NOT EXISTS idx_labeling_tasks_assigned_to ON labeling_tasks(assigned_to)",
        "CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at)"
    ];
    
    foreach ($indexes as $index) {
        try {
            $db->exec($index);
        } catch (PDOException $e) {
            // Index có thể đã tồn tại, bỏ qua lỗi
        }
    }
    echo "<p style='color: green;'>✅ Đã tạo indexes</p>";
    
    echo "<hr>";
    echo "<h2 style='color: green;'>✅ Migration hoàn tất!</h2>";
    echo "<p><strong>Thông tin đăng nhập:</strong></p>";
    echo "<ul>";
    echo "<li>Username: admin</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
    echo "<p><a href='admin/dashboard.php'>Truy cập Admin Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Lỗi: " . $e->getMessage() . "</p>";
}
?>