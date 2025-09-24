<?php
/**
 * Debug Upload System - Tìm file gây lỗi ai_summary
 * File: debug_upload.php
 * Chạy file này để tìm chính xác file nào đang gây lỗi
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h1>🔍 DEBUG UPLOAD SYSTEM</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>✅ Database Connection: OK</h2>";
    
    // 1. Kiểm tra cấu trúc bảng documents
    echo "<h3>📋 DOCUMENTS TABLE STRUCTURE:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    
    $stmt = $db->query("DESCRIBE documents");
    $columns = [];
    while ($row = $stmt->fetch()) {
        $columns[] = $row['Field'];
        echo "<tr>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Kiểm tra các cột quan trọng
    echo "<h3>🔍 CRITICAL COLUMNS CHECK:</h3>";
    $required_columns = ['ai_summary', 'uploaded_by', 'type', 'status'];
    foreach ($required_columns as $col) {
        if (in_array($col, $columns)) {
            echo "<span style='color: green;'>✅ $col: EXISTS</span><br>";
        } else {
            echo "<span style='color: red;'>❌ $col: MISSING</span><br>";
        }
    }
    
    // 3. Tìm các file PHP có chứa 'ai_summary'
    echo "<h3>🔎 FILES USING 'ai_summary':</h3>";
    
    $files_to_check = [
        'admin/upload.php',
        'admin/dashboard.php', 
        'admin/reports.php',
        'labeler/dashboard.php',
        'labeler/labeling.php',
        'reviewer/dashboard.php',
        'includes/functions.php'
    ];
    
    foreach ($files_to_check as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if (strpos($content, 'ai_summary') !== false) {
                echo "<span style='color: orange;'>⚠️ $file: CONTAINS ai_summary</span><br>";
                
                // Tìm các dòng chứa ai_summary
                $lines = explode("\n", $content);
                foreach ($lines as $line_num => $line) {
                    if (strpos($line, 'ai_summary') !== false) {
                        echo "&nbsp;&nbsp;&nbsp;&nbsp;Line " . ($line_num + 1) . ": " . htmlspecialchars(trim($line)) . "<br>";
                    }
                }
                echo "<br>";
            } else {
                echo "<span style='color: green;'>✅ $file: OK (no ai_summary references)</span><br>";
            }
        } else {
            echo "<span style='color: gray;'>➖ $file: NOT EXISTS</span><br>";
        }
    }
    
    // 4. Kiểm tra views
    echo "<h3>👁️ DATABASE VIEWS CHECK:</h3>";
    try {
        $stmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
        $views = $stmt->fetchAll();
        
        if (empty($views)) {
            echo "<span style='color: green;'>✅ No problematic views found</span><br>";
        } else {
            foreach ($views as $view) {
                echo "<span style='color: blue;'>📊 View: " . $view['Tables_in_text_labeling_system'] . "</span><br>";
                
                // Check if view contains ai_summary
                try {
                    $stmt2 = $db->query("SHOW CREATE VIEW " . $view['Tables_in_text_labeling_system']);
                    $view_def = $stmt2->fetch();
                    if (strpos($view_def['Create View'], 'ai_summary') !== false) {
                        echo "&nbsp;&nbsp;&nbsp;&nbsp;<span style='color: red;'>❌ Contains ai_summary reference!</span><br>";
                    }
                } catch (Exception $e) {
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;<span style='color: orange;'>⚠️ Could not check view definition</span><br>";
                }
            }
        }
    } catch (Exception $e) {
        echo "<span style='color: red;'>❌ Error checking views: " . $e->getMessage() . "</span><br>";
    }
    
    // 5. Test basic queries
    echo "<h3>🧪 TEST BASIC QUERIES:</h3>";
    
    // Test 1: Select without ai_summary
    try {
        $stmt = $db->query("SELECT id, title, content FROM documents LIMIT 1");
        $result = $stmt->fetch();
        echo "<span style='color: green;'>✅ Basic SELECT works</span><br>";
    } catch (Exception $e) {
        echo "<span style='color: red;'>❌ Basic SELECT failed: " . $e->getMessage() . "</span><br>";
    }
    
    // Test 2: Select with ai_summary if column exists
    if (in_array('ai_summary', $columns)) {
        try {
            $stmt = $db->query("SELECT id, title, ai_summary FROM documents LIMIT 1");
            $result = $stmt->fetch();
            echo "<span style='color: green;'>✅ ai_summary SELECT works</span><br>";
        } catch (Exception $e) {
            echo "<span style='color: red;'>❌ ai_summary SELECT failed: " . $e->getMessage() . "</span><br>";
        }
    }
    
    // 6. Kiểm tra PHP error logs
    echo "<h3>📋 RECENT PHP ERRORS:</h3>";
    $error_log_paths = [
        '/xampp/logs/error.log',
        ini_get('error_log'),
        'error_log',
        '../error_log'
    ];
    
    $found_errors = false;
    foreach ($error_log_paths as $log_path) {
        if (file_exists($log_path) && is_readable($log_path)) {
            $log_content = file_get_contents($log_path);
            $recent_errors = array_slice(explode("\n", $log_content), -10);
            
            foreach ($recent_errors as $error) {
                if (!empty($error) && strpos($error, 'ai_summary') !== false) {
                    echo "<span style='color: red;'>❌ " . htmlspecialchars($error) . "</span><br>";
                    $found_errors = true;
                }
            }
        }
    }
    
    if (!$found_errors) {
        echo "<span style='color: green;'>✅ No recent ai_summary related errors found in logs</span><br>";
    }
    
    // 7. Generate fix suggestions
    echo "<h3>💡 FIX SUGGESTIONS:</h3>";
    
    if (!in_array('ai_summary', $columns)) {
        echo "<div style='background: #ffe6e6; padding: 10px; border: 1px solid #ff9999; margin: 10px 0;'>";
        echo "<strong>🚨 CRITICAL: ai_summary column is missing!</strong><br>";
        echo "Run this SQL command:<br>";
        echo "<code>ALTER TABLE documents ADD COLUMN ai_summary text DEFAULT NULL;</code>";
        echo "</div>";
    }
    
    if (!in_array('uploaded_by', $columns)) {
        echo "<div style='background: #ffe6e6; padding: 10px; border: 1px solid #ff9999; margin: 10px 0;'>";
        echo "<strong>🚨 CRITICAL: uploaded_by column is missing!</strong><br>";
        echo "Run this SQL command:<br>";
        echo "<code>ALTER TABLE documents ADD COLUMN uploaded_by int(11) DEFAULT 1;</code>";
        echo "</div>";
    }
    
    echo "<div style='background: #e6f3ff; padding: 10px; border: 1px solid #99ccff; margin: 10px 0;'>";
    echo "<strong>📝 NEXT STEPS:</strong><br>";
    echo "1. Add missing columns using the SQL commands above<br>";
    echo "2. Replace admin/upload.php with the fixed version<br>";
    echo "3. Test upload again<br>";
    echo "4. If still errors, check the specific files listed above";
    echo "</div>";
    
    // 8. Test sample upload data
    echo "<h3>🧪 TEST SAMPLE UPLOAD DATA:</h3>";
    
    $sample_jsonl = [
        '{"title": "Test Document", "content": "This is test content", "ai_summary": "Test summary", "type": "single"}',
        '{"title": "Multi Doc 1", "content": "Multi content 1", "ai_summary": "Multi summary 1", "type": "multi", "group_name": "Test Group"}',
        '{"title": "Multi Doc 2", "content": "Multi content 2", "ai_summary": "Multi summary 2", "type": "multi"}'
    ];
    
    echo "<div style='background: #f0f8f0; padding: 10px; border: 1px solid #90ee90; margin: 10px 0;'>";
    echo "<strong>📄 SAMPLE JSONL DATA:</strong><br>";
    foreach ($sample_jsonl as $i => $line) {
        echo "Line " . ($i + 1) . ": <code>" . htmlspecialchars($line) . "</code><br>";
    }
    echo "</div>";
    
    // 9. Quick fix SQL script
    echo "<h3>⚡ QUICK FIX SQL SCRIPT:</h3>";
    echo "<div style='background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin: 10px 0;'>";
    echo "<strong>Copy and run this SQL to fix all issues:</strong><br><br>";
    echo "<textarea style='width: 100%; height: 200px; font-family: monospace;' readonly>";
    echo "-- Quick fix for ai_summary column issues
USE text_labeling_system;

-- Add missing columns (ignore errors if they exist)
ALTER TABLE documents ADD COLUMN ai_summary longtext DEFAULT NULL;
ALTER TABLE documents ADD COLUMN uploaded_by int(11) DEFAULT 1;
ALTER TABLE documents ADD COLUMN type enum('single','multi') DEFAULT 'single';
ALTER TABLE documents ADD COLUMN status enum('pending','assigned','completed','reviewed') DEFAULT 'pending';

-- Add missing columns to users
ALTER TABLE users ADD COLUMN role enum('admin','labeler','reviewer') DEFAULT 'labeler';

-- Create missing tables
CREATE TABLE IF NOT EXISTS document_groups (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    group_name varchar(255) NOT NULL,
    description text,
    combined_ai_summary text,
    created_by int(11) DEFAULT 1,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status enum('pending','assigned','completed','reviewed') DEFAULT 'pending'
);

CREATE TABLE IF NOT EXISTS document_group_items (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    group_id int(11) NOT NULL,
    document_id int(11) NOT NULL,
    sort_order int(11) DEFAULT 0,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP
);

-- Create reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    task_id int(11) NOT NULL,
    reviewer_id int(11) NOT NULL,
    review_status enum('approved','rejected','needs_revision') NOT NULL,
    review_comments text,
    review_score int(11),
    reviewed_at timestamp DEFAULT CURRENT_TIMESTAMP
);

-- Update admin user
INSERT IGNORE INTO users (username, password, email, role) VALUES 
('admin', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin');

UPDATE users SET role = 'admin' WHERE username = 'admin';

-- Fix existing data
UPDATE documents SET ai_summary = 'Generated summary' WHERE ai_summary IS NULL;
UPDATE documents SET uploaded_by = 1 WHERE uploaded_by IS NULL OR uploaded_by = 0;

SELECT '✅ Quick fix completed!' as status;";
    echo "</textarea>";
    echo "</div>";
    
    // 10. Current system status
    echo "<h3>📊 CURRENT SYSTEM STATUS:</h3>";
    
    $status_score = 0;
    $total_checks = 6;
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Check</th><th>Status</th><th>Score</th></tr>";
    
    // Check 1: Database connection
    echo "<tr><td>Database Connection</td><td style='color: green;'>✅ OK</td><td>1/1</td></tr>";
    $status_score++;
    
    // Check 2: Documents table exists
    echo "<tr><td>Documents Table</td><td style='color: green;'>✅ EXISTS</td><td>1/1</td></tr>";
    $status_score++;
    
    // Check 3: ai_summary column
    if (in_array('ai_summary', $columns)) {
        echo "<tr><td>ai_summary Column</td><td style='color: green;'>✅ EXISTS</td><td>1/1</td></tr>";
        $status_score++;
    } else {
        echo "<tr><td>ai_summary Column</td><td style='color: red;'>❌ MISSING</td><td>0/1</td></tr>";
    }
    
    // Check 4: uploaded_by column
    if (in_array('uploaded_by', $columns)) {
        echo "<tr><td>uploaded_by Column</td><td style='color: green;'>✅ EXISTS</td><td>1/1</td></tr>";
        $status_score++;
    } else {
        echo "<tr><td>uploaded_by Column</td><td style='color: red;'>❌ MISSING</td><td>0/1</td></tr>";
    }
    
    // Check 5: type column
    if (in_array('type', $columns)) {
        echo "<tr><td>type Column</td><td style='color: green;'>✅ EXISTS</td><td>1/1</td></tr>";
        $status_score++;
    } else {
        echo "<tr><td>type Column</td><td style='color: red;'>❌ MISSING</td><td>0/1</td></tr>";
    }
    
    // Check 6: Admin user
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
        $admin_count = $stmt->fetch()['count'];
        if ($admin_count > 0) {
            echo "<tr><td>Admin User</td><td style='color: green;'>✅ EXISTS</td><td>1/1</td></tr>";
            $status_score++;
        } else {
            echo "<tr><td>Admin User</td><td style='color: red;'>❌ MISSING</td><td>0/1</td></tr>";
        }
    } catch (Exception $e) {
        echo "<tr><td>Admin User</td><td style='color: orange;'>⚠️ UNKNOWN</td><td>0/1</td></tr>";
    }
    
    echo "</table>";
    
    $status_percent = ($status_score / $total_checks) * 100;
    echo "<br><strong>Overall System Health: {$status_percent}% ({$status_score}/{$total_checks})</strong><br>";
    
    if ($status_percent < 100) {
        echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ff9999; margin: 15px 0;'>";
        echo "<h4>🚨 ACTION REQUIRED</h4>";
        echo "Your system is not fully configured. Please run the Quick Fix SQL script above to resolve all issues.";
        echo "</div>";
    } else {
        echo "<div style='background: #e6ffe6; padding: 15px; border: 1px solid #99ff99; margin: 15px 0;'>";
        echo "<h4>🎉 SYSTEM READY</h4>";
        echo "All checks passed! Your upload system should work correctly now.";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Database Connection Failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config/database.php</p>";
}

echo "<hr>";
echo "<p><small>Debug script completed at " . date('Y-m-d H:i:s') . "</small></p>";
echo "<p><small>After fixing issues, delete this debug_upload.php file for security.</small></p>";
?>