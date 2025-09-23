<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Không thể kết nối database!");
}

echo "<h2>Database Fix Tool</h2>";
echo "<pre>";

try {
    // 1. Check current table structure
    echo "1. Checking current documents table structure...\n";
    
    $stmt = $db->prepare("DESCRIBE documents");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existing_columns = array_column($columns, 'Field');
    
    echo "Current columns: " . implode(', ', $existing_columns) . "\n\n";
    
    // 2. Check foreign key constraints
    echo "2. Checking foreign key constraints...\n";
    $stmt = $db->prepare("SELECT 
        CONSTRAINT_NAME, 
        COLUMN_NAME, 
        REFERENCED_TABLE_NAME, 
        REFERENCED_COLUMN_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'documents' 
        AND REFERENCED_TABLE_NAME IS NOT NULL");
    $stmt->execute();
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($fks as $fk) {
        echo "FK: {$fk['CONSTRAINT_NAME']} - {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
    }
    
    // 3. Drop existing foreign key constraints that might cause issues
    echo "\n3. Dropping problematic foreign key constraints...\n";
    foreach ($fks as $fk) {
        try {
            $sql = "ALTER TABLE documents DROP FOREIGN KEY {$fk['CONSTRAINT_NAME']}";
            $db->exec($sql);
            echo "✓ Dropped FK constraint: {$fk['CONSTRAINT_NAME']}\n";
        } catch (Exception $e) {
            echo "- Failed to drop FK {$fk['CONSTRAINT_NAME']}: " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Rename uploaded_by to created_by if it exists
    echo "\n4. Checking column names...\n";
    if (in_array('uploaded_by', $existing_columns) && !in_array('created_by', $existing_columns)) {
        echo "Renaming 'uploaded_by' to 'created_by'...\n";
        $db->exec("ALTER TABLE documents CHANGE uploaded_by created_by INT NOT NULL DEFAULT 1");
        echo "✓ Renamed column\n";
    } elseif (in_array('created_by', $existing_columns)) {
        echo "- 'created_by' column already exists\n";
    } else {
        echo "Adding 'created_by' column...\n";
        $db->exec("ALTER TABLE documents ADD COLUMN created_by INT NOT NULL DEFAULT 1");
        echo "✓ Added 'created_by' column\n";
    }
    
    // 5. Add missing columns
    echo "\n5. Adding missing columns...\n";
    
    // Refresh column list
    $stmt = $db->prepare("DESCRIBE documents");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existing_columns = array_column($columns, 'Field');
    
    $required_columns = [
        'ai_summary' => 'TEXT NULL',
        'type' => "ENUM('single', 'multi') NOT NULL DEFAULT 'single'",
        'group_id' => 'INT NULL',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            echo "Adding column '$column'...\n";
            $sql = "ALTER TABLE documents ADD COLUMN $column $definition";
            $db->exec($sql);
            echo "✓ Added column '$column'\n";
        } else {
            echo "- Column '$column' already exists\n";
        }
    }
    
    // 6. Ensure we have a valid admin user
    echo "\n6. Checking admin user...\n";
    $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin_user) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, email, full_name, role) 
                             VALUES ('admin', ?, 'admin@test.com', 'Admin User', 'admin')");
        $stmt->execute([$admin_password]);
        $admin_id = $db->lastInsertId();
        echo "✓ Created admin user with ID: $admin_id\n";
    } else {
        $admin_id = $admin_user['id'];
        echo "✓ Using existing admin user ID: $admin_id\n";
    }
    
    // 7. Update any NULL created_by values
    echo "\n7. Updating NULL created_by values...\n";
    $stmt = $db->prepare("UPDATE documents SET created_by = ? WHERE created_by IS NULL OR created_by = 0");
    $stmt->execute([$admin_id]);
    $updated_rows = $stmt->rowCount();
    echo "✓ Updated $updated_rows rows with created_by = $admin_id\n";
    
    // 8. Create other tables
    echo "\n8. Creating additional tables...\n";
    
    // document_groups table
    $sql = "CREATE TABLE IF NOT EXISTS document_groups (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        ai_summary TEXT NOT NULL,
        created_by INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($sql);
    echo "✓ document_groups table ready\n";
    
    // labeling_tasks table
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
        completed_at TIMESTAMP NULL
    )";
    $db->exec($sql);
    echo "✓ labeling_tasks table ready\n";
    
    // 9. Test insert
    echo "\n9. Testing database operations...\n";
    
    try {
        // Test documents insert
        $stmt = $db->prepare("INSERT INTO documents (title, content, ai_summary, type, created_by) 
                             VALUES ('Test Document', 'Test content', 'Test AI summary', 'single', ?)");
        $stmt->execute([$admin_id]);
        $test_doc_id = $db->lastInsertId();
        echo "✓ Test document inserted with ID: $test_doc_id\n";
        
        // Test document_groups insert
        $stmt = $db->prepare("INSERT INTO document_groups (title, description, ai_summary, created_by) 
                             VALUES ('Test Group', 'Test description', 'Test group summary', ?)");
        $stmt->execute([$admin_id]);
        $test_group_id = $db->lastInsertId();
        echo "✓ Test group inserted with ID: $test_group_id\n";
        
        // Clean up test data
        $db->prepare("DELETE FROM documents WHERE id = ?")->execute([$test_doc_id]);
        $db->prepare("DELETE FROM document_groups WHERE id = ?")->execute([$test_group_id]);
        echo "✓ Test data cleaned up\n";
        
    } catch (Exception $e) {
        echo "❌ Test insert failed: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    // 10. Show final table structure
    echo "\n10. Final table structure:\n";
    $stmt = $db->prepare("DESCRIBE documents");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "- {$col['Field']}: {$col['Type']} " . ($col['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    echo "\n============================================\n";
    echo "✅ DATABASE FIX COMPLETED SUCCESSFULLY!\n";
    echo "============================================\n\n";
    
    echo "Database is now ready for:\n";
    echo "1. JSONL uploads\n";
    echo "2. Single and multi-document processing\n";
    echo "3. Task management\n\n";
    
    echo "Next steps:\n";
    echo "- Try uploading a JSONL file\n";
    echo "- Use admin/simple_upload_test.php for testing\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "</pre>";

echo '<div style="margin: 20px;">';
echo '<a href="admin/upload.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">Go to Upload</a>';
echo '<a href="admin/simple_upload_test.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Test Simple Upload</a>';
echo '</div>';
?>