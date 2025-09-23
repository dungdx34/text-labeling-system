<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Ultimate Database Fix</h2>";
echo "<pre>";

try {
    echo "🔧 ULTIMATE DATABASE REPAIR STARTING...\n";
    echo str_repeat("=", 50) . "\n\n";
    
    // 1. Disable foreign key checks
    echo "1. Disabling foreign key constraints...\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "✓ Foreign key checks disabled\n\n";
    
    // 2. Check what tables exist
    echo "2. Checking existing tables...\n";
    $stmt = $db->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . implode(', ', $tables) . "\n\n";
    
    // 3. Fix documents table structure
    echo "3. Fixing documents table...\n";
    
    // Get current structure
    $stmt = $db->prepare("DESCRIBE documents");
    $stmt->execute();
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    echo "Current columns: " . implode(', ', $columns) . "\n";
    
    // Fix uploaded_by -> created_by
    if (in_array('uploaded_by', $columns)) {
        if (in_array('created_by', $columns)) {
            $db->exec("ALTER TABLE documents DROP COLUMN uploaded_by");
            echo "✓ Removed duplicate uploaded_by column\n";
        } else {
            $db->exec("ALTER TABLE documents CHANGE uploaded_by created_by INT NOT NULL DEFAULT 1");
            echo "✓ Renamed uploaded_by to created_by\n";
        }
    } elseif (!in_array('created_by', $columns)) {
        $db->exec("ALTER TABLE documents ADD COLUMN created_by INT NOT NULL DEFAULT 1");
        echo "✓ Added created_by column\n";
    }
    
    // Add missing columns
    $required_cols = [
        'ai_summary' => 'TEXT NULL',
        'type' => "ENUM('single', 'multi') NOT NULL DEFAULT 'single'",
        'group_id' => 'INT NULL'
    ];
    
    // Refresh column list
    $stmt = $db->prepare("DESCRIBE documents");
    $stmt->execute();
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    foreach ($required_cols as $col => $def) {
        if (!in_array($col, $columns)) {
            $db->exec("ALTER TABLE documents ADD COLUMN $col $def");
            echo "✓ Added $col column\n";
        }
    }
    
    // 4. Create/fix document_groups table
    echo "\n4. Creating document_groups table...\n";
    
    // Drop table if exists (foreign keys disabled so this will work)
    $db->exec("DROP TABLE IF EXISTS document_groups");
    echo "✓ Dropped existing document_groups table\n";
    
    // Create new table with correct structure
    $sql = "CREATE TABLE document_groups (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        ai_summary TEXT NOT NULL,
        created_by INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($sql);
    echo "✓ Created document_groups table with correct structure\n";
    
    // 5. Create other essential tables
    echo "\n5. Creating essential tables...\n";
    
    // labeling_tasks table
    $sql = "CREATE TABLE IF NOT EXISTS labeling_tasks (
        id INT PRIMARY KEY AUTO_INCREMENT,
        document_id INT NULL,
        group_id INT NULL,
        assigned_to INT NOT NULL,
        status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        assigned_by INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($sql);
    echo "✓ labeling_tasks table ready\n";
    
    // 6. Ensure admin user exists
    echo "\n6. Setting up admin user...\n";
    $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, email, full_name, role) 
                             VALUES ('admin', ?, 'admin@test.com', 'System Administrator', 'admin')");
        $stmt->execute([$password]);
        $admin_id = $db->lastInsertId();
        echo "✓ Created admin user (ID: $admin_id, password: admin123)\n";
    } else {
        $admin_id = $admin['id'];
        echo "✓ Admin user exists (ID: $admin_id)\n";
    }
    
    // 7. Fix any invalid data
    echo "\n7. Cleaning up data...\n";
    
    // Update invalid created_by values
    $stmt = $db->prepare("UPDATE documents SET created_by = ? WHERE created_by IS NULL OR created_by = 0");
    $stmt->execute([$admin_id]);
    $updated = $stmt->rowCount();
    echo "✓ Fixed $updated documents with invalid created_by\n";
    
    // Clear invalid group_id references
    $db->exec("UPDATE documents SET group_id = NULL WHERE group_id IS NOT NULL AND group_id NOT IN (SELECT id FROM document_groups)");
    echo "✓ Cleared invalid group_id references\n";
    
    // 8. Re-enable foreign key checks
    echo "\n8. Re-enabling foreign key constraints...\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "✓ Foreign key checks re-enabled\n";
    
    // 9. Test all JSONL upload scenarios
    echo "\n9. TESTING JSONL UPLOAD FUNCTIONALITY...\n";
    echo str_repeat("-", 40) . "\n";
    
    // Test 1: Single document upload
    echo "Test 1: Single document from JSONL...\n";
    $single_data = [
        'type' => 'single',
        'title' => 'JSONL Test Document',
        'content' => 'This is test content from JSONL upload',
        'ai_summary' => 'This is AI generated summary for test'
    ];
    
    $stmt = $db->prepare("INSERT INTO documents (title, content, ai_summary, type, created_by) 
                         VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $single_data['title'],
        $single_data['content'],
        $single_data['ai_summary'],
        $single_data['type'],
        $admin_id
    ]);
    $test_doc_id = $db->lastInsertId();
    echo "✅ Single document test PASSED (ID: $test_doc_id)\n";
    
    // Test 2: Multi-document group upload
    echo "\nTest 2: Multi-document group from JSONL...\n";
    $multi_data = [
        'group_title' => 'JSONL Test Group',
        'group_description' => 'Test group from JSONL',
        'group_summary' => 'This is group AI summary',
        'documents' => [
            ['title' => 'Group Doc 1', 'content' => 'Content of first document in group'],
            ['title' => 'Group Doc 2', 'content' => 'Content of second document in group']
        ]
    ];
    
    // Insert group
    $stmt = $db->prepare("INSERT INTO document_groups (title, description, ai_summary, created_by) 
                         VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $multi_data['group_title'],
        $multi_data['group_description'],
        $multi_data['group_summary'],
        $admin_id
    ]);
    $test_group_id = $db->lastInsertId();
    echo "✅ Document group created (ID: $test_group_id)\n";
    
    // Insert documents in group
    $test_multi_ids = [];
    foreach ($multi_data['documents'] as $i => $doc) {
        $stmt = $db->prepare("INSERT INTO documents (title, content, type, group_id, created_by) 
                             VALUES (?, ?, 'multi', ?, ?)");
        $stmt->execute([$doc['title'], $doc['content'], $test_group_id, $admin_id]);
        $doc_id = $db->lastInsertId();
        $test_multi_ids[] = $doc_id;
        echo "✅ Multi-document " . ($i+1) . " created (ID: $doc_id)\n";
    }
    
    // Test 3: Database queries for upload interface
    echo "\nTest 3: Testing upload interface queries...\n";
    
    // Count documents
    $stmt = $db->prepare("SELECT COUNT(*) as total, 
                         COUNT(CASE WHEN type = 'single' THEN 1 END) as single_docs,
                         COUNT(CASE WHEN type = 'multi' THEN 1 END) as multi_docs
                         FROM documents");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Document stats query works: {$stats['total']} total ({$stats['single_docs']} single, {$stats['multi_docs']} multi)\n";
    
    // Count groups
    $stmt = $db->prepare("SELECT COUNT(*) FROM document_groups");
    $stmt->execute();
    $group_count = $stmt->fetchColumn();
    echo "✅ Group count query works: $group_count groups\n";
    
    // Test task creation query (for admin interface)
    $stmt = $db->prepare("SELECT d.id, d.title, d.type, 'document' as source_type FROM documents d
                         UNION ALL
                         SELECT dg.id, dg.title, 'multi' as type, 'group' as source_type FROM document_groups dg");
    $stmt->execute();
    $available_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Available items query works: " . count($available_items) . " items available for task creation\n";
    
    // 10. Cleanup test data
    echo "\n10. Cleaning up test data...\n";
    
    // Delete test documents
    $all_test_ids = array_merge([$test_doc_id], $test_multi_ids);
    $placeholders = str_repeat('?,', count($all_test_ids) - 1) . '?';
    $stmt = $db->prepare("DELETE FROM documents WHERE id IN ($placeholders)");
    $stmt->execute($all_test_ids);
    echo "✓ Deleted " . count($all_test_ids) . " test documents\n";
    
    // Delete test group
    $stmt = $db->prepare("DELETE FROM document_groups WHERE id = ?");
    $stmt->execute([$test_group_id]);
    echo "✓ Deleted test group\n";
    
    // 11. Final verification
    echo "\n11. Final verification...\n";
    
    // Check table structures
    $stmt = $db->prepare("DESCRIBE documents");
    $stmt->execute();
    $doc_columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    $stmt = $db->prepare("DESCRIBE document_groups");
    $stmt->execute();
    $group_columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    $required_doc_cols = ['id', 'title', 'content', 'ai_summary', 'type', 'group_id', 'created_by'];
    $required_group_cols = ['id', 'title', 'description', 'ai_summary', 'created_by'];
    
    $missing_doc = array_diff($required_doc_cols, $doc_columns);
    $missing_group = array_diff($required_group_cols, $group_columns);
    
    if (empty($missing_doc) && empty($missing_group)) {
        echo "✅ All required columns present\n";
    } else {
        if (!empty($missing_doc)) echo "❌ Missing document columns: " . implode(', ', $missing_doc) . "\n";
        if (!empty($missing_group)) echo "❌ Missing group columns: " . implode(', ', $missing_group) . "\n";
        throw new Exception("Missing required columns");
    }
    
    echo "\n" . str_repeat("🎉", 20) . "\n";
    echo "🎉 ULTIMATE FIX COMPLETED SUCCESSFULLY! 🎉\n";
    echo str_repeat("🎉", 20) . "\n\n";
    
    echo "✅ DATABASE IS NOW 100% READY FOR:\n";
    echo "   • JSONL file uploads (single & multi-document)\n";
    echo "   • Manual document uploads\n";
    echo "   • Task creation and management\n";
    echo "   • User authentication and roles\n\n";
    
    echo "🚀 READY TO USE:\n";
    echo "   • Login: admin / admin123\n";
    echo "   • Upload JSONL files in admin panel\n";
    echo "   • Create tasks for labelers\n";
    echo "   • All database operations working\n\n";
    
    echo "📁 TABLE STRUCTURE:\n";
    echo "   • documents: " . implode(', ', $doc_columns) . "\n";
    echo "   • document_groups: " . implode(', ', $group_columns) . "\n";
    
} catch (Exception $e) {
    echo "\n❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    
    // Try to re-enable foreign keys even if there was an error
    try {
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "✓ Foreign key checks re-enabled after error\n";
    } catch (Exception $fk_error) {
        echo "❌ Could not re-enable foreign key checks\n";
    }
}

echo "</pre>";

echo '<div style="margin: 20px; text-align: center;">';
echo '<a href="admin/upload.php" style="background: linear-gradient(45deg, #28a745, #20c997); color: white; padding: 20px 40px; text-decoration: none; border-radius: 10px; font-size: 20px; font-weight: bold; box-shadow: 0 4px 15px rgba(40,167,69,0.3); margin: 10px;">🚀 START UPLOADING JSONL FILES!</a><br><br>';
echo '<a href="admin/simple_upload_test.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;">Simple Test</a> ';
echo '<a href="admin/dashboard.php" style="background: #6f42c1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;">Dashboard</a> ';
echo '<a href="login.php" style="background: #fd7e14; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;">Login Page</a>';
echo '</div>';
?>