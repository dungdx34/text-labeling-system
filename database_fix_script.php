<?php
/**
 * Database Fix Script for Text Labeling System
 * This script will create/fix all necessary database tables
 * Run this once to fix the database structure issues
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Starting database fix...\n";
    echo "==============================\n";
    
    // 1. Create/Fix users table
    echo "1. Checking users table...\n";
    $create_users = "CREATE TABLE IF NOT EXISTS users (
        id int(11) NOT NULL AUTO_INCREMENT,
        username varchar(50) NOT NULL UNIQUE,
        password varchar(255) NOT NULL,
        full_name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        role enum('admin','labeler','reviewer') NOT NULL,
        status enum('active','inactive') DEFAULT 'active',
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_username (username),
        UNIQUE KEY unique_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($create_users);
    echo "✓ Users table created/verified\n";
    
    // 2. Create/Fix documents table
    echo "2. Checking documents table...\n";
    $create_documents = "CREATE TABLE IF NOT EXISTS documents (
        id int(11) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        content longtext NOT NULL,
        ai_summary text,
        type enum('single','multi') DEFAULT 'single',
        uploaded_by int(11) DEFAULT 1,
        status enum('pending','assigned','completed','reviewed') DEFAULT 'pending',
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_uploaded_by (uploaded_by),
        KEY idx_status (status),
        KEY idx_type (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($create_documents);
    echo "✓ Documents table created/verified\n";
    
    // 3. Create/Fix document_groups table
    echo "3. Checking document_groups table...\n";
    $create_document_groups = "CREATE TABLE IF NOT EXISTS document_groups (
        id int(11) NOT NULL AUTO_INCREMENT,
        group_name varchar(255) NOT NULL,
        description text,
        combined_ai_summary text,
        created_by int(11) DEFAULT 1,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        status enum('pending','assigned','completed','reviewed') DEFAULT 'pending',
        total_documents int DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_created_by (created_by),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($create_document_groups);
    echo "✓ Document_groups table created/verified\n";
    
    // 4. Create/Fix document_group_items table
    echo "4. Checking document_group_items table...\n";
    $create_group_items = "CREATE TABLE IF NOT EXISTS document_group_items (
        id int(11) NOT NULL AUTO_INCREMENT,
        group_id int(11) NOT NULL,
        document_id int(11) NOT NULL,
        sort_order int(11) DEFAULT 0,
        individual_ai_summary text,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_group_id (group_id),
        KEY idx_document_id (document_id),
        FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($create_group_items);
    echo "✓ Document_group_items table created/verified\n";
    
    // 5. Create/Fix assignments table
    echo "5. Checking assignments table...\n";
    $create_assignments = "CREATE TABLE IF NOT EXISTS assignments (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        document_id int(11) DEFAULT NULL,
        group_id int(11) DEFAULT NULL,
        assigned_by int(11) NOT NULL,
        status enum('pending','in_progress','completed','reviewed') DEFAULT 'pending',
        assigned_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_id (user_id),
        KEY idx_document_id (document_id),
        KEY idx_group_id (group_id),
        KEY idx_assigned_by (assigned_by),
        KEY idx_status (status),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($create_assignments);
    echo "✓ Assignments table created/verified\n";
    
    // 6. Create/Fix reviews table - THIS WAS THE MAIN PROBLEM
    echo "6. Checking reviews table...\n";
    $create_reviews = "CREATE TABLE IF NOT EXISTS reviews (
        id int(11) NOT NULL AUTO_INCREMENT,
        assignment_id int(11) NOT NULL,
        reviewer_id int(11) NOT NULL,
        rating int(11) DEFAULT NULL,
        comments text,
        status enum('pending','approved','rejected','needs_revision') DEFAULT 'pending',
        feedback longtext,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_assignment_reviewer (assignment_id, reviewer_id),
        KEY idx_assignment_id (assignment_id),
        KEY idx_reviewer_id (reviewer_id),
        KEY idx_status (status),
        FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($create_reviews);
    echo "✓ Reviews table created/verified\n";
    
    // 7. Create/Fix labeling_results table
    echo "7. Checking labeling_results table...\n";
    $create_labeling_results = "CREATE TABLE IF NOT EXISTS labeling_results (
        id int(11) NOT NULL AUTO_INCREMENT,
        assignment_id int(11) NOT NULL,
        document_id int(11) NOT NULL,
        selected_sentences longtext,
        writing_style varchar(50) DEFAULT 'formal',
        edited_summary text,
        step1_completed tinyint(1) DEFAULT 0,
        step2_completed tinyint(1) DEFAULT 0,
        step3_completed tinyint(1) DEFAULT 0,
        completed_at timestamp NULL DEFAULT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_assignment_id (assignment_id),
        KEY idx_document_id (document_id),
        FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($create_labeling_results);
    echo "✓ Labeling_results table created/verified\n";
    
    // 8. Insert default admin user if not exists
    echo "8. Checking default admin user...\n";
    $check_admin = $db->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $admin_exists = $check_admin->fetch()['count'] > 0;
    
    if (!$admin_exists) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $admin_password, 'Administrator', 'admin@textlabeling.com', 'admin']);
        echo "✓ Default admin user created (username: admin, password: admin123)\n";
    } else {
        echo "✓ Admin user already exists\n";
    }
    
    // 9. Create sample labeler and reviewer users if they don't exist
    echo "9. Checking sample users...\n";
    $sample_users = [
        ['labeler1', 'labeler123', 'Labeler One', 'labeler1@textlabeling.com', 'labeler'],
        ['reviewer1', 'reviewer123', 'Reviewer One', 'reviewer1@textlabeling.com', 'reviewer']
    ];
    
    foreach ($sample_users as $user_data) {
        $check_user = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
        $check_user->execute([$user_data[0]]);
        $user_exists = $check_user->fetch()['count'] > 0;
        
        if (!$user_exists) {
            $password_hash = password_hash($user_data[1], PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_data[0], $password_hash, $user_data[2], $user_data[3], $user_data[4]]);
            echo "✓ Sample user created: {$user_data[0]} (password: {$user_data[1]})\n";
        }
    }
    
    // 10. Fix any existing data inconsistencies
    echo "10. Fixing data inconsistencies...\n";
    
    // Update documents without ai_summary
    $db->exec("UPDATE documents SET ai_summary = CONCAT('Auto-generated summary for: ', SUBSTRING(title, 1, 50), '...') WHERE ai_summary IS NULL OR ai_summary = ''");
    
    // Update documents without type
    $db->exec("UPDATE documents SET type = 'single' WHERE type IS NULL");
    
    echo "✓ Data inconsistencies fixed\n";
    
    echo "==============================\n";
    echo "Database fix completed successfully!\n";
    echo "==============================\n";
    
    // Show current table status
    echo "Current database structure:\n";
    $tables = ['users', 'documents', 'document_groups', 'document_group_items', 'assignments', 'reviews', 'labeling_results'];
    
    foreach ($tables as $table) {
        $result = $db->query("SELECT COUNT(*) as count FROM $table");
        $count = $result->fetch()['count'];
        echo "- $table: $count records\n";
    }
    
    echo "\nTest users:\n";
    echo "- Admin: username=admin, password=admin123\n";
    echo "- Labeler: username=labeler1, password=labeler123\n";
    echo "- Reviewer: username=reviewer1, password=reviewer123\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    if ($db && $db->inTransaction()) {
        $db->rollback();
    }
}
?>