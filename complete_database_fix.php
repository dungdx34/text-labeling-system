<?php
/**
 * Complete Database Structure Fix for Text Labeling System
 * This script will ensure all tables have the correct columns
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Starting complete database structure fix...\n";
    echo "==================================================\n";
    
    // 1. Fix document_groups table structure
    echo "1. Fixing document_groups table...\n";
    
    // Check if table exists
    $table_check = $db->query("SHOW TABLES LIKE 'document_groups'");
    if ($table_check->rowCount() == 0) {
        // Create table with all necessary columns
        $create_groups = "CREATE TABLE document_groups (
            id int(11) NOT NULL AUTO_INCREMENT,
            group_name varchar(255) NOT NULL,
            description text,
            combined_ai_summary text,
            created_by int(11) DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status enum('pending','assigned','completed','reviewed') DEFAULT 'pending',
            total_documents int DEFAULT 0,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($create_groups);
        echo "✓ document_groups table created\n";
    } else {
        // Check existing columns
        $columns_result = $db->query("SHOW COLUMNS FROM document_groups");
        $existing_columns = [];
        while ($col = $columns_result->fetch(PDO::FETCH_ASSOC)) {
            $existing_columns[] = $col['Field'];
        }
        
        // Add missing columns
        $required_columns = [
            'combined_ai_summary' => 'text',
            'status' => "enum('pending','assigned','completed','reviewed') DEFAULT 'pending'",
            'total_documents' => 'int DEFAULT 0'
        ];
        
        foreach ($required_columns as $column => $definition) {
            if (!in_array($column, $existing_columns)) {
                $db->exec("ALTER TABLE document_groups ADD COLUMN $column $definition");
                echo "✓ Added column: $column\n";
            } else {
                echo "✓ Column $column already exists\n";
            }
        }
    }
    
    // 2. Fix documents table structure
    echo "\n2. Fixing documents table...\n";
    
    $table_check = $db->query("SHOW TABLES LIKE 'documents'");
    if ($table_check->rowCount() == 0) {
        $create_documents = "CREATE TABLE documents (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            ai_summary text,
            type enum('single','multi') DEFAULT 'single',
            uploaded_by int(11) DEFAULT 1,
            status enum('pending','assigned','completed','reviewed') DEFAULT 'pending',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($create_documents);
        echo "✓ documents table created\n";
    } else {
        echo "✓ documents table already exists\n";
    }
    
    // 3. Fix assignments table structure
    echo "\n3. Fixing assignments table...\n";
    
    $table_check = $db->query("SHOW TABLES LIKE 'assignments'");
    if ($table_check->rowCount() == 0) {
        $create_assignments = "CREATE TABLE assignments (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            document_id int(11) DEFAULT NULL,
            group_id int(11) DEFAULT NULL,
            assigned_by int(11) NOT NULL,
            status enum('pending','in_progress','completed','reviewed') DEFAULT 'pending',
            deadline datetime DEFAULT NULL,
            assigned_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($create_assignments);
        echo "✓ assignments table created\n";
    } else {
        // Check for deadline column
        $columns_result = $db->query("SHOW COLUMNS FROM assignments");
        $existing_columns = [];
        while ($col = $columns_result->fetch(PDO::FETCH_ASSOC)) {
            $existing_columns[] = $col['Field'];
        }
        
        if (!in_array('deadline', $existing_columns)) {
            $db->exec("ALTER TABLE assignments ADD COLUMN deadline datetime DEFAULT NULL");
            echo "✓ Added deadline column to assignments\n";
        } else {
            echo "✓ assignments table structure is correct\n";
        }
    }
    
    // 4. Fix reviews table structure
    echo "\n4. Fixing reviews table...\n";
    
    $table_check = $db->query("SHOW TABLES LIKE 'reviews'");
    if ($table_check->rowCount() == 0) {
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
        echo "✓ reviews table created\n";
    } else {
        echo "✓ reviews table already exists\n";
    }
    
    // 5. Fix labeling_results table structure  
    echo "\n5. Fixing labeling_results table...\n";
    
    $table_check = $db->query("SHOW TABLES LIKE 'labeling_results'");
    if ($table_check->rowCount() == 0) {
        $create_results = "CREATE TABLE labeling_results (
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
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($create_results);
        echo "✓ labeling_results table created\n";
    } else {
        echo "✓ labeling_results table already exists\n";
    }
    
    // 6. Fix document_group_items table
    echo "\n6. Fixing document_group_items table...\n";
    
    $table_check = $db->query("SHOW TABLES LIKE 'document_group_items'");
    if ($table_check->rowCount() == 0) {
        $create_items = "CREATE TABLE document_group_items (
            id int(11) NOT NULL AUTO_INCREMENT,
            group_id int(11) NOT NULL,
            document_id int(11) NOT NULL,
            sort_order int(11) DEFAULT 0,
            individual_ai_summary text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($create_items);
        echo "✓ document_group_items table created\n";
    } else {
        echo "✓ document_group_items table already exists\n";
    }
    
    // 7. Ensure users table has proper structure
    echo "\n7. Checking users table...\n";
    
    $table_check = $db->query("SHOW TABLES LIKE 'users'");
    if ($table_check->rowCount() == 0) {
        $create_users = "CREATE TABLE users (
            id int(11) NOT NULL AUTO_INCREMENT,
            username varchar(50) NOT NULL UNIQUE,
            password varchar(255) NOT NULL,
            full_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL UNIQUE,
            role enum('admin','labeler','reviewer') NOT NULL,
            status enum('active','inactive') DEFAULT 'active',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($create_users);
        echo "✓ users table created\n";
    } else {
        echo "✓ users table already exists\n";
    }
    
    // 8. Add sample data if tables are empty
    echo "\n8. Adding sample data if needed...\n";
    
    // Check if we have any users
    $user_count = $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    if ($user_count == 0) {
        // Create default users
        $users = [
            ['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'admin@textlabeling.com', 'admin'],
            ['labeler1', password_hash('labeler123', PASSWORD_DEFAULT), 'Labeler One', 'labeler1@textlabeling.com', 'labeler'],
            ['reviewer1', password_hash('reviewer123', PASSWORD_DEFAULT), 'Reviewer One', 'reviewer1@textlabeling.com', 'reviewer']
        ];
        
        $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
        foreach ($users as $user) {
            $stmt->execute($user);
        }
        echo "✓ Sample users created\n";
    } else {
        echo "✓ Users already exist\n";
    }
    
    // Check if we have sample documents
    $doc_count = $db->query("SELECT COUNT(*) as count FROM documents")->fetch()['count'];
    if ($doc_count == 0) {
        $sample_docs = [
            [
                'Sample Document 1',
                'This is a sample document for testing the text labeling system. It contains multiple sentences that can be selected and labeled. The content discusses various topics and provides examples for the labeling workflow. Users can select important sentences and edit the AI-generated summary.',
                'Sample document for testing text labeling with multiple sentences for selection and summary editing.'
            ],
            [
                'Sample Document 2', 
                'Another sample document with different content structure. This document focuses on different aspects of text processing and natural language understanding. It serves as additional test data for the labeling system.',
                'Second sample document focusing on text processing and natural language understanding for testing purposes.'
            ]
        ];
        
        $stmt = $db->prepare("INSERT INTO documents (title, content, ai_summary, uploaded_by) VALUES (?, ?, ?, 1)");
        foreach ($sample_docs as $doc) {
            $stmt->execute($doc);
        }
        echo "✓ Sample documents created\n";
    } else {
        echo "✓ Documents already exist\n";
    }
    
    // 9. Create sample document group if needed
    $group_count = $db->query("SELECT COUNT(*) as count FROM document_groups")->fetch()['count'];
    if ($group_count == 0) {
        $stmt = $db->prepare("INSERT INTO document_groups (group_name, description, combined_ai_summary, created_by, total_documents) VALUES (?, ?, ?, 1, 2)");
        $stmt->execute([
            'Sample Document Group',
            'A sample group containing multiple related documents for multi-document labeling tasks.',
            'This group contains sample documents that demonstrate the multi-document labeling workflow in the text labeling system.'
        ]);
        $group_id = $db->lastInsertId();
        
        // Add documents to group
        $doc_ids = $db->query("SELECT id FROM documents LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
        if (count($doc_ids) >= 2) {
            $stmt = $db->prepare("INSERT INTO document_group_items (group_id, document_id, sort_order) VALUES (?, ?, ?)");
            foreach ($doc_ids as $index => $doc_id) {
                $stmt->execute([$group_id, $doc_id, $index]);
            }
            echo "✓ Sample document group created\n";
        }
    } else {
        echo "✓ Document groups already exist\n";
    }
    
    echo "\n==================================================\n";
    echo "Database structure fix completed successfully!\n";
    echo "==================================================\n";
    
    // Show final table status
    echo "\nFinal database structure:\n";
    $tables = ['users', 'documents', 'document_groups', 'document_group_items', 'assignments', 'reviews', 'labeling_results'];
    
    foreach ($tables as $table) {
        $count_result = $db->query("SELECT COUNT(*) as count FROM $table");
        $count = $count_result->fetch()['count'];
        echo "- $table: $count records\n";
    }
    
    echo "\nTest login credentials:\n";
    echo "- Admin: admin / admin123\n";
    echo "- Labeler: labeler1 / labeler123\n"; 
    echo "- Reviewer: reviewer1 / reviewer123\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>