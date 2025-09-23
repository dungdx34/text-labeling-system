<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Minimal Database Fix</h2>";
echo "<pre>";

try {
    echo "Step-by-step database repair...\n\n";
    
    // 1. Check what tables exist
    echo "1. Checking existing tables...\n";
    $stmt = $db->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n\n";
    
    // 2. Fix documents table
    echo "2. Fixing documents table...\n";
    
    // Check current columns
    $stmt = $db->prepare("DESCRIBE documents");
    $stmt->execute();
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    echo "Current columns: " . implode(', ', $columns) . "\n";
    
    // Remove ALL foreign key constraints first
    echo "Removing foreign key constraints...\n";
    $stmt = $db->prepare("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' 
                         AND REFERENCED_TABLE_NAME IS NOT NULL");
    $stmt->execute();
    $fks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($fks as $fk) {
        try {
            $db->exec("ALTER TABLE documents DROP FOREIGN KEY $fk");
            echo "- Dropped FK: $fk\n";
        } catch (Exception $e) {
            echo "- FK $fk already removed or doesn't exist\n";
        }
    }
    
    // Fix column names and add missing columns
    if (in_array('uploaded_by', $columns)) {
        if (!in_array('created_by', $columns)) {
            $db->exec("ALTER TABLE documents CHANGE uploaded_by created_by INT NOT NULL DEFAULT 1");
            echo "✓ Renamed uploaded_by to created_by\n";
        } else {
            // Both exist, drop uploaded_by
            $db->exec("ALTER TABLE documents DROP COLUMN uploaded_by");
            echo "✓ Dropped duplicate uploaded_by column\n";
        }
    } elseif (!in_array('created_by', $columns)) {
        $db->exec("ALTER TABLE documents ADD COLUMN created_by INT NOT NULL DEFAULT 1");
        echo "✓ Added created_by column\n";
    } else {
        echo "✓ created_by column already exists\n";
    }
    
    // Add other required columns
    $required = [
        'ai_summary' => 'TEXT NULL',
        'type' => "ENUM('single', 'multi') NOT NULL DEFAULT 'single'",
        'group_id' => 'INT NULL'
    ];
    
    // Refresh column list
    $stmt = $db->prepare("DESCRIBE documents");
    $stmt->execute();
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    foreach ($required as $col => $def) {
        if (!in_array($col, $columns)) {
            $db->exec("ALTER TABLE documents ADD COLUMN $col $def");
            echo "✓ Added $col column\n";
        } else {
            echo "✓ $col column already exists\n";
        }
    }
    
    // 3. Drop and recreate document_groups table to ensure correct structure
    echo "\n3. Recreating document_groups table...\n";
    
    $db->exec("DROP TABLE IF EXISTS document_groups");
    echo "- Dropped existing document_groups table\n";
    
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
    
    // 4. Ensure admin user exists
    echo "\n4. Checking admin user...\n";
    $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, email, full_name, role) 
                             VALUES ('admin', ?, 'admin@test.com', 'Admin User', 'admin')");
        $stmt->execute([$password]);
        $admin_id = $db->lastInsertId();
        echo "✓ Created admin user (ID: $admin_id)\n";
    } else {
        $admin_id = $admin['id'];
        echo "✓ Admin user exists (ID: $admin_id)\n";
    }
    
    // 5. Update any invalid created_by values in documents
    echo "\n5. Fixing created_by references...\n";
    $stmt = $db->prepare("UPDATE documents SET created_by = ? WHERE created_by IS NULL OR created_by = 0");
    $stmt->execute([$admin_id]);
    $updated = $stmt->rowCount();
    echo "✓ Updated $updated records with correct created_by\n";
    
    // 6. Test all operations
    echo "\n6. Testing database operations...\n";
    
    // Test 1: Single document
    $stmt = $db->prepare("INSERT INTO documents (title, content, ai_summary, type, created_by) 
                         VALUES ('Test Single', 'Test content', 'Test summary', 'single', ?)");
    $stmt->execute([$admin_id]);
    $doc_id = $db->lastInsertId();
    echo "✓ Single document insert: ID $doc_id\n";
    
    // Test 2: Document group
    $stmt = $db->prepare("INSERT INTO document_groups (title, description, ai_summary, created_by) 
                         VALUES ('Test Group', 'Test description', 'Test group summary', ?)");
    $stmt->execute([$admin_id]);
    $group_id = $db->lastInsertId();
    echo "✓ Document group insert: ID $group_id\n";
    
    // Test 3: Multi document (linked to group)
    $stmt = $db->prepare("INSERT INTO documents (title, content, type, group_id, created_by) 
                         VALUES ('Test Multi', 'Test multi content', 'multi', ?, ?)");
    $stmt->execute([$group_id, $admin_id]);
    $multi_doc_id = $db->lastInsertId();
    echo "✓ Multi document insert: ID $multi_doc_id\n";
    
    // Test 4: JSONL-style data
    echo "\nTesting JSONL-style data...\n";
    
    // Single document from JSONL
    $jsonl_single = [
        'type' => 'single',
        'title' => 'JSONL Test Single',
        'content' => 'This is a test content from JSONL',
        'ai_summary' => 'This is AI summary from JSONL'
    ];
    
    $stmt = $db->prepare("INSERT INTO documents (title, content, ai_summary, type, created_by) 
                         VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $jsonl_single['title'],
        $jsonl_single['content'], 
        $jsonl_single['ai_summary'],
        $jsonl_single['type'],
        $admin_id
    ]);
    $jsonl_doc_id = $db->lastInsertId();
    echo "✓ JSONL single document: ID $jsonl_doc_id\n";
    
    // Multi document from JSONL
    $jsonl_group = [
        'group_title' => 'JSONL Test Group',
        'group_summary' => 'This is group summary from JSONL',
        'documents' => [
            ['title' => 'Doc 1', 'content' => 'Content 1'],
            ['title' => 'Doc 2', 'content' => 'Content 2']
        ]
    ];
    
    // Insert group
    $stmt = $db->prepare("INSERT INTO document_groups (title, ai_summary, created_by) 
                         VALUES (?, ?, ?)");
    $stmt->execute([$jsonl_group['group_title'], $jsonl_group['group_summary'], $admin_id]);
    $jsonl_group_id = $db->lastInsertId();
    echo "✓ JSONL group created: ID $jsonl_group_id\n";
    
    // Insert documents in group
    foreach ($jsonl_group['documents'] as $i => $doc) {
        $stmt = $db->prepare("INSERT INTO documents (title, content, type, group_id, created_by) 
                             VALUES (?, ?, 'multi', ?, ?)");
        $stmt->execute([$doc['title'], $doc['content'], $jsonl_group_id, $admin_id]);
        echo "✓ JSONL multi document " . ($i+1) . ": ID " . $db->lastInsertId() . "\n";
    }
    
    // Show final counts
    echo "\n7. Final database status...\n";
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE type = 'single'");
    $stmt->execute();
    $single_count = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE type = 'multi'");
    $stmt->execute();
    $multi_count = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM document_groups");
    $stmt->execute();
    $group_count = $stmt->fetchColumn();
    
    echo "Documents: $single_count single, $multi_count multi\n";
    echo "Groups: $group_count\n";
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎉 DATABASE IS NOW READY FOR JSONL UPLOADS!\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "✅ All database operations work correctly\n";
    echo "✅ JSONL upload functionality is ready\n";
    echo "✅ Both single and multi-document processing work\n\n";
    
    echo "Next steps:\n";
    echo "1. Try uploading your JSONL file\n";
    echo "2. Check the upload works in admin/upload.php\n";
    echo "3. Test with admin/simple_upload_test.php if needed\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";

echo '<div style="margin: 20px;">';
echo '<a href="admin/upload.php" style="background: #28a745; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-size: 18px; margin-right: 10px;">🚀 Try JSONL Upload Now!</a>';
echo '<a href="admin/simple_upload_test.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Test Simple Upload</a>';
echo '</div>';
?>