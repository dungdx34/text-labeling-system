<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Quick Database Fix</h2>";
echo "<pre>";

try {
    echo "Fixing database for upload functionality...\n\n";
    
    // 1. Fix documents table
    echo "1. Fixing documents table...\n";
    
    // Get current structure
    $stmt = $db->prepare("DESCRIBE documents");
    $stmt->execute();
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    echo "Current columns: " . implode(', ', $columns) . "\n";
    
    // Drop all foreign keys first
    $stmt = $db->prepare("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' 
                         AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    $stmt->execute();
    $fks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($fks as $fk) {
        try {
            $db->exec("ALTER TABLE documents DROP FOREIGN KEY $fk");
            echo "Dropped FK: $fk\n";
        } catch (Exception $e) {
            // Ignore if already dropped
        }
    }
    
    // Rename uploaded_by to created_by if needed
    if (in_array('uploaded_by', $columns) && !in_array('created_by', $columns)) {
        $db->exec("ALTER TABLE documents CHANGE uploaded_by created_by INT NOT NULL DEFAULT 1");
        echo "✓ Renamed uploaded_by to created_by\n";
    } elseif (!in_array('created_by', $columns)) {
        $db->exec("ALTER TABLE documents ADD COLUMN created_by INT NOT NULL DEFAULT 1");
        echo "✓ Added created_by column\n";
    }
    
    // Add other required columns
    if (!in_array('ai_summary', $columns)) {
        $db->exec("ALTER TABLE documents ADD COLUMN ai_summary TEXT NULL");
        echo "✓ Added ai_summary column\n";
    }
    
    if (!in_array('type', $columns)) {
        $db->exec("ALTER TABLE documents ADD COLUMN type ENUM('single', 'multi') NOT NULL DEFAULT 'single'");
        echo "✓ Added type column\n";
    }
    
    if (!in_array('group_id', $columns)) {
        $db->exec("ALTER TABLE documents ADD COLUMN group_id INT NULL");
        echo "✓ Added group_id column\n";
    }
    
    // 2. Create document_groups table
    echo "\n2. Creating document_groups table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS document_groups (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        ai_summary TEXT NOT NULL,
        created_by INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($sql);
    echo "✓ document_groups table ready\n";
    
    // 3. Ensure admin user exists
    echo "\n3. Checking admin user...\n";
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
    
    // 4. Update any invalid created_by values
    $stmt = $db->prepare("UPDATE documents SET created_by = ? WHERE created_by IS NULL OR created_by = 0 OR created_by NOT IN (SELECT id FROM users)");
    $stmt->execute([$admin_id]);
    echo "✓ Updated created_by references\n";
    
    // 5. Test upload functionality
    echo "\n4. Testing upload functionality...\n";
    
    // Test single document insert
    $stmt = $db->prepare("INSERT INTO documents (title, content, ai_summary, type, created_by) 
                         VALUES ('Test Single', 'Test content', 'Test summary', 'single', ?)");
    $stmt->execute([$admin_id]);
    $doc_id = $db->lastInsertId();
    echo "✓ Single document insert works (ID: $doc_id)\n";
    
    // Test group insert
    $stmt = $db->prepare("INSERT INTO document_groups (title, ai_summary, created_by) 
                         VALUES ('Test Group', 'Test group summary', ?)");
    $stmt->execute([$admin_id]);
    $group_id = $db->lastInsertId();
    echo "✓ Document group insert works (ID: $group_id)\n";
    
    // Test multi document insert
    $stmt = $db->prepare("INSERT INTO documents (title, content, type, group_id, created_by) 
                         VALUES ('Test Multi', 'Test multi content', 'multi', ?, ?)");
    $stmt->execute([$group_id, $admin_id]);
    $multi_doc_id = $db->lastInsertId();
    echo "✓ Multi document insert works (ID: $multi_doc_id)\n";
    
    // Clean up test data
    $db->prepare("DELETE FROM documents WHERE id IN (?, ?)")->execute([$doc_id, $multi_doc_id]);
    $db->prepare("DELETE FROM document_groups WHERE id = ?")->execute([$group_id]);
    echo "✓ Test data cleaned up\n";
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ QUICK FIX COMPLETED SUCCESSFULLY!\n";
    echo str_repeat("=", 50) . "\n\n";
    
    echo "Your database is now ready for:\n";
    echo "✓ JSONL file uploads\n";
    echo "✓ Single document processing\n"; 
    echo "✓ Multi-document processing\n";
    echo "✓ Document groups\n\n";
    
    echo "You can now try uploading your JSONL file!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
}

echo "</pre>";

echo '<div style="margin: 20px;">';
echo '<a href="admin/simple_upload_test.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">Test Upload</a>';
echo '<a href="admin/upload.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Go to Upload</a>';
echo '</div>';
?>