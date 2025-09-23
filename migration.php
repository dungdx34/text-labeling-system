<?php
// Migration script để update database một cách an toàn
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Không thể kết nối database!");
}

$migrations = [];
$errors = [];

echo "<h2>Text Labeling System - Database Migration</h2>";
echo "<pre>";

try {
    // 1. Create document_groups table if not exists
    echo "1. Creating document_groups table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS document_groups (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        ai_summary TEXT NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )";
    $db->exec($sql);
    echo "✓ document_groups table ready\n\n";

    // 2. Create/update documents table
    echo "2. Creating/updating documents table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS documents (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        ai_summary TEXT NULL,
        type ENUM('single', 'multi') NOT NULL,
        group_id INT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE
    )";
    $db->exec($sql);
    
    // Add indexes if they don't exist
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_type ON documents(type)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_group_id ON documents(group_id)");
    } catch (Exception $e) {
        // Indexes might already exist
    }
    
    echo "✓ documents table ready\n\n";

    // 3. Create labeling_tasks table
    echo "3. Creating labeling_tasks table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS labeling_tasks (
        id INT PRIMARY KEY AUTO_INCREMENT,
        document_id INT NULL,
        group_id INT NULL,
        assigned_to INT NOT NULL,
        status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        assigned_by INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        started_at TIMESTAMP NULL,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
    )";
    $db->exec($sql);
    
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_assigned_to ON labeling_tasks(assigned_to)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_status ON labeling_tasks(status)");
    } catch (Exception $e) {
        // Indexes might already exist
    }
    
    echo "✓ labeling_tasks table ready\n\n";

    // 4. Create sentence_selections table
    echo "4. Creating sentence_selections table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS sentence_selections (
        id INT PRIMARY KEY AUTO_INCREMENT,
        task_id INT NOT NULL,
        document_id INT NOT NULL,
        sentence_text TEXT NOT NULL,
        sentence_position INT NOT NULL,
        is_selected BOOLEAN DEFAULT FALSE,
        importance_score INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
    )";
    $db->exec($sql);
    
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_task_document ON sentence_selections(task_id, document_id)");
    } catch (Exception $e) {
        // Index might already exist
    }
    
    echo "✓ sentence_selections table ready\n\n";

    // 5. Create text_styles table
    echo "5. Creating text_styles table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS text_styles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        task_id INT NOT NULL,
        style_type ENUM('formal', 'informal', 'academic', 'conversational', 'technical', 'creative') NOT NULL,
        confidence_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE
    )";
    $db->exec($sql);
    echo "✓ text_styles table ready\n\n";

    // 6. Create summary_edits table
    echo "6. Creating summary_edits table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS summary_edits (
        id INT PRIMARY KEY AUTO_INCREMENT,
        task_id INT NOT NULL,
        original_summary TEXT NOT NULL,
        edited_summary TEXT NOT NULL,
        edit_type ENUM('minor', 'major', 'complete_rewrite') NOT NULL,
        edit_reason TEXT,
        quality_score INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE
    )";
    $db->exec($sql);
    echo "✓ summary_edits table ready\n\n";

    // 7. Create reviews table
    echo "7. Creating reviews table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS reviews (
        id INT PRIMARY KEY AUTO_INCREMENT,
        task_id INT NOT NULL,
        reviewer_id INT NOT NULL,
        status ENUM('approved', 'rejected', 'needs_revision') NOT NULL,
        overall_score INT DEFAULT 0,
        sentence_selection_feedback TEXT,
        style_feedback TEXT,
        summary_feedback TEXT,
        general_comments TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $db->exec($sql);
    echo "✓ reviews table ready\n\n";

    // 8. Create activity_logs table
    echo "8. Creating activity_logs table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50),
        entity_id INT,
        details JSON,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $db->exec($sql);
    
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_user_action ON activity_logs(user_id, action)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON activity_logs(created_at)");
    } catch (Exception $e) {
        // Indexes might already exist
    }
    
    echo "✓ activity_logs table ready\n\n";

    // 9. Insert default users if they don't exist
    echo "9. Checking default users...\n";
    
    $users = [
        ['admin', 'admin123', 'admin@textlabeling.com', 'System Administrator', 'admin'],
        ['labeler1', 'password123', 'labeler1@test.com', 'Labeler User 1', 'labeler'],
        ['reviewer1', 'password123', 'reviewer1@test.com', 'Reviewer User 1', 'reviewer']
    ];
    
    foreach ($users as $user) {
        $check_sql = "SELECT COUNT(*) FROM users WHERE username = ?";
        $stmt = $db->prepare($check_sql);
        $stmt->execute([$user[0]]);
        
        if ($stmt->fetchColumn() == 0) {
            $hashed_password = password_hash($user[1], PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($insert_sql);
            $stmt->execute([$user[0], $hashed_password, $user[2], $user[3], $user[4]]);
            echo "✓ Created user: {$user[0]} ({$user[4]})\n";
        } else {
            echo "- User {$user[0]} already exists\n";
        }
    }
    echo "\n";

    // 10. Create views
    echo "10. Creating views...\n";
    
    $task_summary_view = "CREATE OR REPLACE VIEW task_summary AS
    SELECT 
        t.id as task_id,
        t.status,
        t.priority,
        COALESCE(d.title, dg.title) as title,
        COALESCE(d.type, 'multi') as type,
        u_assigned.full_name as assigned_to_name,
        u_creator.full_name as assigned_by_name,
        t.assigned_at,
        t.completed_at
    FROM labeling_tasks t
    LEFT JOIN documents d ON t.document_id = d.id
    LEFT JOIN document_groups dg ON t.group_id = dg.id
    LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
    LEFT JOIN users u_creator ON t.assigned_by = u_creator.id";
    
    $db->exec($task_summary_view);
    echo "✓ task_summary view created\n";
    
    $user_stats_view = "CREATE OR REPLACE VIEW user_stats AS
    SELECT 
        u.id,
        u.username,
        u.full_name,
        u.role,
        COALESCE(task_counts.total_tasks, 0) as total_tasks,
        COALESCE(task_counts.completed_tasks, 0) as completed_tasks,
        COALESCE(task_counts.pending_tasks, 0) as pending_tasks,
        COALESCE(review_counts.total_reviews, 0) as total_reviews
    FROM users u
    LEFT JOIN (
        SELECT 
            assigned_to,
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks
        FROM labeling_tasks 
        GROUP BY assigned_to
    ) task_counts ON u.id = task_counts.assigned_to
    LEFT JOIN (
        SELECT 
            reviewer_id,
            COUNT(*) as total_reviews
        FROM reviews 
        GROUP BY reviewer_id
    ) review_counts ON u.id = review_counts.reviewer_id";
    
    $db->exec($user_stats_view);
    echo "✓ user_stats view created\n\n";

    // 11. Create directories
    echo "11. Creating required directories...\n";
    
    $dirs = ['uploads', 'samples'];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "✓ Created directory: $dir\n";
        } else {
            echo "- Directory $dir already exists\n";
        }
    }
    
    // Create sample JSONL file
    $sample_file = 'samples/sample.jsonl';
    if (!file_exists($sample_file)) {
        $sample_content = '{"type": "single", "title": "Tác động của AI trong giáo dục", "content": "Trí tuệ nhân tạo (AI) đang thay đổi cách chúng ta học và dạy. AI có thể cá nhân hóa quá trình học tập, tự động hóa việc chấm điểm và cung cấp phản hồi ngay lập tức cho học sinh.", "ai_summary": "AI đang cách mạng hóa giáo dục thông qua việc cá nhân hóa học tập, tự động hóa đánh giá và hỗ trợ giáo viên."}' . "\n" .
'{"type": "multi", "group_title": "Công nghệ Blockchain", "group_description": "Tổng quan về blockchain", "group_summary": "Blockchain là công nghệ cơ sở cho cryptocurrency, mang lại tính minh bạch và bảo mật.", "documents": [{"title": "Blockchain là gì?", "content": "Blockchain là một cơ sở dữ liệu phân tán được duy trì bởi một mạng lưới các máy tính."}, {"title": "Bitcoin", "content": "Bitcoin được tạo ra vào năm 2009 như một hệ thống thanh toán peer-to-peer."}]}';
        
        file_put_contents($sample_file, $sample_content);
        echo "✓ Created sample JSONL file\n";
    } else {
        echo "- Sample JSONL file already exists\n";
    }
    
    echo "\n";
    echo "============================================\n";
    echo "✅ MIGRATION COMPLETED SUCCESSFULLY!\n";
    echo "============================================\n\n";
    
    echo "Next steps:\n";
    echo "1. Go to: " . $_SERVER['HTTP_HOST'] . "/login.php\n";
    echo "2. Login with: admin / admin123\n";
    echo "3. Upload samples/sample.jsonl to test\n";
    echo "4. Create tasks and assign to labelers\n\n";
    
    echo "Default accounts:\n";
    echo "- Admin: admin / admin123\n";
    echo "- Labeler: labeler1 / password123\n";
    echo "- Reviewer: reviewer1 / password123\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}

echo "</pre>";
?>