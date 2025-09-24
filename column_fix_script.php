<?php
/**
 * Quick fix for missing columns in reviews table
 * Run this script to add missing columns to existing reviews table
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Checking and fixing reviews table structure...\n";
    echo "================================================\n";
    
    // Check if reviews table exists
    $table_check = $db->query("SHOW TABLES LIKE 'reviews'");
    if ($table_check->rowCount() == 0) {
        echo "Reviews table doesn't exist. Creating it...\n";
        
        $create_reviews = "CREATE TABLE reviews (
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
            UNIQUE KEY unique_assignment_reviewer (assignment_id, reviewer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $db->exec($create_reviews);
        echo "✓ Reviews table created successfully\n";
    } else {
        echo "Reviews table exists. Checking columns...\n";
        
        // Get current columns
        $columns_result = $db->query("SHOW COLUMNS FROM reviews");
        $existing_columns = [];
        while ($col = $columns_result->fetch(PDO::FETCH_ASSOC)) {
            $existing_columns[] = $col['Field'];
        }
        
        echo "Current columns: " . implode(', ', $existing_columns) . "\n";
        
        // Define required columns with their SQL definitions
        $required_columns = [
            'assignment_id' => 'int(11) NOT NULL',
            'reviewer_id' => 'int(11) NOT NULL', 
            'rating' => 'int(11) DEFAULT NULL',
            'comments' => 'text',
            'status' => "enum('pending','approved','rejected','needs_revision') DEFAULT 'pending'",
            'feedback' => 'longtext',
            'created_at' => 'timestamp DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];
        
        // Check and add missing columns
        foreach ($required_columns as $column => $definition) {
            if (!in_array($column, $existing_columns)) {
                echo "Adding missing column: $column\n";
                $alter_query = "ALTER TABLE reviews ADD COLUMN $column $definition";
                $db->exec($alter_query);
                echo "✓ Column $column added\n";
            } else {
                echo "✓ Column $column already exists\n";
            }
        }
        
        // Check and add unique key if not exists
        $indexes_result = $db->query("SHOW INDEXES FROM reviews WHERE Key_name = 'unique_assignment_reviewer'");
        if ($indexes_result->rowCount() == 0) {
            echo "Adding unique constraint...\n";
            try {
                $db->exec("ALTER TABLE reviews ADD UNIQUE KEY unique_assignment_reviewer (assignment_id, reviewer_id)");
                echo "✓ Unique constraint added\n";
            } catch (Exception $e) {
                echo "Note: Could not add unique constraint (may already exist or have data conflicts)\n";
            }
        } else {
            echo "✓ Unique constraint already exists\n";
        }
    }
    
    // Check if assignments table exists, create if not
    $assignments_check = $db->query("SHOW TABLES LIKE 'assignments'");
    if ($assignments_check->rowCount() == 0) {
        echo "\nCreating assignments table...\n";
        $create_assignments = "CREATE TABLE assignments (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            document_id int(11) DEFAULT NULL,
            group_id int(11) DEFAULT NULL,
            assigned_by int(11) NOT NULL,
            status enum('pending','in_progress','completed','reviewed') DEFAULT 'pending',
            assigned_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $db->exec($create_assignments);
        echo "✓ Assignments table created\n";
    }
    
    // Insert some sample data if tables are empty
    $reviews_count = $db->query("SELECT COUNT(*) as count FROM reviews")->fetch()['count'];
    $assignments_count = $db->query("SELECT COUNT(*) as count FROM assignments")->fetch()['count'];
    
    if ($assignments_count == 0) {
        echo "\nCreating sample assignment for testing...\n";
        
        // First, ensure we have some documents
        $docs_count = $db->query("SELECT COUNT(*) as count FROM documents")->fetch()['count'];
        if ($docs_count == 0) {
            echo "Creating sample document...\n";
            $sample_doc = $db->prepare("INSERT INTO documents (title, content, ai_summary) VALUES (?, ?, ?)");
            $sample_doc->execute([
                'Sample Document for Testing',
                'This is a sample document content for testing the labeling system. It contains multiple sentences that can be selected for labeling. The content discusses various topics and provides a good example for the labeling workflow.',
                'This document serves as a test case for the text labeling system with sample content.'
            ]);
            echo "✓ Sample document created\n";
        }
        
        // Get user IDs
        $labeler = $db->query("SELECT id FROM users WHERE role = 'labeler' LIMIT 1")->fetch();
        $admin = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
        $document = $db->query("SELECT id FROM documents LIMIT 1")->fetch();
        
        if ($labeler && $admin && $document) {
            $sample_assignment = $db->prepare("INSERT INTO assignments (user_id, document_id, assigned_by, status) VALUES (?, ?, ?, ?)");
            $sample_assignment->execute([$labeler['id'], $document['id'], $admin['id'], 'completed']);
            echo "✓ Sample assignment created\n";
        }
    }
    
    echo "\n================================================\n";
    echo "Fix completed successfully!\n";
    echo "Your reviews table should now work properly.\n";
    echo "================================================\n";
    
    // Show current structure
    echo "\nCurrent reviews table structure:\n";
    $structure = $db->query("DESCRIBE reviews");
    while ($field = $structure->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$field['Field']}: {$field['Type']} {$field['Null']} {$field['Key']} {$field['Default']}\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>