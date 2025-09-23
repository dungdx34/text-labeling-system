<?php
/**
 * Database Check and Repair Script
 * Use this to verify database structure and fix common issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'text_labeling_system';

try {
    // Connect to MySQL server (without selecting database first)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Text Labeling System - Database Check</h1>";
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";
    
    // Check if database exists
    echo "<h2>🔍 Checking Database...</h2>";
    $stmt = $pdo->query("SHOW DATABASES LIKE '$database'");
    if ($stmt->rowCount() == 0) {
        echo "❌ Database '$database' does not exist. Creating...<br>";
        $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✅ Database '$database' created successfully.<br>";
    } else {
        echo "✅ Database '$database' exists.<br>";
    }
    
    // Select database
    $pdo->exec("USE `$database`");
    
    // Check tables
    echo "<h2>🔍 Checking Tables...</h2>";
    $required_tables = [
        'users' => [
            'columns' => ['id', 'username', 'email', 'password', 'full_name', 'role', 'status'],
            'required' => true
        ],
        'document_groups' => [
            'columns' => ['id', 'title', 'description', 'group_summary', 'type', 'status', 'created_by'],
            'required' => true
        ],
        'documents' => [
            'columns' => ['id', 'group_id', 'title', 'content', 'order_index'],
            'required' => true
        ],
        'label_tasks' => [
            'columns' => ['id', 'group_id', 'labeler_id', 'status'],
            'required' => true
        ]
    ];
    
    $missing_tables = [];
    foreach ($required_tables as $table => $info) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            echo "❌ Table '$table' is missing.<br>";
            $missing_tables[] = $table;
        } else {
            echo "✅ Table '$table' exists.<br>";
            
            // Check columns
            $stmt = $pdo->query("DESCRIBE `$table`");
            $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($info['columns'] as $column) {
                if (!in_array($column, $existing_columns)) {
                    echo "⚠️  Column '$table.$column' is missing.<br>";
                }
            }
        }
    }
    
    if (!empty($missing_tables)) {
        echo "<h2>⚠️  Missing Tables Detected</h2>";
        echo "Please run the safe_migration.sql script to create missing tables.<br><br>";
    }
    
    // Check for existing data
    echo "<h2>🔍 Checking Data...</h2>";
    
    // Check users
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $user_count = $stmt->fetch()['count'];
        echo "👥 Total users: $user_count<br>";
        
        if ($user_count > 0) {
            $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
            while ($row = $stmt->fetch()) {
                echo "   - {$row['role']}: {$row['count']}<br>";
            }
            
            // Check admin users
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'");
            $admin_count = $stmt->fetch()['count'];
            if ($admin_count == 0) {
                echo "⚠️  No active admin users found!<br>";
            }
        }
    } catch (Exception $e) {
        echo "❌ Error checking users: " . $e->getMessage() . "<br>";
    }
    
    // Check document groups
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM document_groups");
        $group_count = $stmt->fetch()['count'];
        echo "📄 Total document groups: $group_count<br>";
    } catch (Exception $e) {
        echo "❌ Error checking document groups: " . $e->getMessage() . "<br>";
    }
    
    // Check foreign key constraints
    echo "<h2>🔍 Checking Foreign Key Constraints...</h2>";
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = '$database' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $constraints = $stmt->fetchAll();
    if (empty($constraints)) {
        echo "⚠️  No foreign key constraints found. This might cause data integrity issues.<br>";
    } else {
        echo "✅ Foreign key constraints found: " . count($constraints) . "<br>";
        foreach ($constraints as $constraint) {
            echo "   - {$constraint['CONSTRAINT_NAME']}: {$constraint['TABLE_NAME']}.{$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}<br>";
        }
    }
    
    // Test database operations
    echo "<h2>🔍 Testing Database Operations...</h2>";
    
    // Test connection
    echo "✅ Database connection: OK<br>";
    
    // Test UTF-8
    $pdo->exec("SET NAMES utf8mb4");
    echo "✅ UTF-8 support: OK<br>";
    
    // Test transactions
    try {
        $pdo->beginTransaction();
        $pdo->rollback();
        echo "✅ Transaction support: OK<br>";
    } catch (Exception $e) {
        echo "❌ Transaction support: FAILED - " . $e->getMessage() . "<br>";
    }
    
    // Check MySQL version
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch()['version'];
    echo "✅ MySQL version: $version<br>";
    
    // Check if JSON type is supported
    try {
        $pdo->exec("CREATE TEMPORARY TABLE test_json (data JSON)");
        echo "✅ JSON support: OK<br>";
    } catch (Exception $e) {
        echo "⚠️  JSON support: LIMITED (older MySQL version)<br>";
    }
    
    // Performance check
    echo "<h2>🔍 Performance Check...</h2>";
    
    try {
        $start = microtime(true);
        $stmt = $pdo->query("SELECT 1");
        $end = microtime(true);
        $query_time = round(($end - $start) * 1000, 2);
        
        if ($query_time < 10) {
            echo "✅ Query performance: EXCELLENT ({$query_time}ms)<br>";
        } elseif ($query_time < 50) {
            echo "✅ Query performance: GOOD ({$query_time}ms)<br>";
        } else {
            echo "⚠️  Query performance: SLOW ({$query_time}ms)<br>";
        }
    } catch (Exception $e) {
        echo "❌ Performance check failed: " . $e->getMessage() . "<br>";
    }
    
    // Final recommendations
    echo "<h2>📋 Recommendations</h2>";
    
    if (!empty($missing_tables)) {
        echo "🔧 <strong>Action Required:</strong> Run safe_migration.sql to create missing tables.<br>";
    }
    
    if ($user_count == 0) {
        echo "🔧 <strong>Action Required:</strong> No users found. Run safe_migration.sql to create demo users.<br>";
    }
    
    if (empty($constraints)) {
        echo "🔧 <strong>Recommended:</strong> Add foreign key constraints for data integrity.<br>";
    }
    
    echo "<br>✅ Database check completed successfully!<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; font-family: monospace; background: #ffe6e6; padding: 20px;'>";
    echo "❌ <strong>Database Check Failed:</strong><br>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Code: " . $e->getCode() . "<br><br>";
    
    echo "<strong>Common Solutions:</strong><br>";
    echo "1. Check if MySQL service is running<br>";
    echo "2. Verify database credentials in this script<br>";
    echo "3. Make sure user has proper permissions<br>";
    echo "4. Check if database exists<br>";
    echo "</div>";
}

// Show quick fix buttons
echo "<div style='margin: 20px; padding: 20px; background: #f0f8ff; border: 1px solid #0066cc;'>";
echo "<h3>🛠️ Quick Actions</h3>";
echo "<p><strong>If you need to run the migration:</strong></p>";
echo "<code>mysql -u root -p &lt; safe_migration.sql</code><br><br>";

echo "<p><strong>Alternative method (if command line not available):</strong></p>";
echo "1. Open phpMyAdmin or similar tool<br>";
echo "2. Select your database<br>";
echo "3. Go to SQL tab<br>";
echo "4. Copy and paste the safe_migration.sql content<br>";
echo "5. Execute the query<br><br>";

echo "<p><strong>Reset everything (DANGER - will delete all data):</strong></p>";
echo "<code>DROP DATABASE text_labeling_system; CREATE DATABASE text_labeling_system;</code><br>";
echo "<small style='color: red;'>⚠️ This will delete ALL your data!</small><br>";

echo "</div>";

// Configuration check
echo "<div style='margin: 20px; padding: 20px; background: #fff8dc; border: 1px solid #daa520;'>";
echo "<h3>⚙️ Configuration Check</h3>";

$config_file = __DIR__ . '/config/database.php';
if (file_exists($config_file)) {
    echo "✅ Database config file found: $config_file<br>";
} else {
    echo "❌ Database config file missing: $config_file<br>";
    echo "Please create the config/database.php file from the provided template.<br>";
}

$auth_file = __DIR__ . '/includes/auth.php';
if (file_exists($auth_file)) {
    echo "✅ Auth file found: $auth_file<br>";
} else {
    echo "❌ Auth file missing: $auth_file<br>";
    echo "Please create the includes/auth.php file from the provided template.<br>";
}

// Check directory structure
$required_dirs = [
    'config',
    'includes', 
    'admin',
    'labeler',
    'reviewer'
];

echo "<h4>Directory Structure:</h4>";
foreach ($required_dirs as $dir) {
    $dir_path = __DIR__ . '/' . $dir;
    if (is_dir($dir_path)) {
        echo "✅ Directory exists: /$dir<br>";
    } else {
        echo "❌ Directory missing: /$dir<br>";
        echo "   <small>mkdir $dir</small><br>";
    }
}

echo "</div>";

// Test authentication
echo "<div style='margin: 20px; padding: 20px; background: #f0fff0; border: 1px solid #228b22;'>";
echo "<h3>🔐 Authentication Test</h3>";

if (file_exists($auth_file)) {
    try {
        include_once $auth_file;
        echo "✅ Auth file loaded successfully<br>";
        
        if (class_exists('Auth')) {
            echo "✅ Auth class found<br>";
            
            // Test password hashing
            $test_password = 'password123';
            $hashed = password_hash($test_password, PASSWORD_DEFAULT);
            $verified = password_verify($test_password, $hashed);
            
            if ($verified) {
                echo "✅ Password hashing works correctly<br>";
            } else {
                echo "❌ Password hashing failed<br>";
            }
            
        } else {
            echo "❌ Auth class not found<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Auth file error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ Auth file not found - cannot test authentication<br>";
}

echo "</div>";

// Session test
echo "<div style='margin: 20px; padding: 20px; background: #f5f5dc; border: 1px solid #8b7355;'>";
echo "<h3>🔄 Session Test</h3>";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✅ Session started successfully<br>";
} else {
    echo "✅ Session already active<br>";
}

echo "Session ID: " . session_id() . "<br>";
echo "Session save path: " . session_save_path() . "<br>";

// Check if session directory is writable
$session_path = session_save_path();
if (empty($session_path)) {
    $session_path = sys_get_temp_dir();
}

if (is_writable($session_path)) {
    echo "✅ Session directory is writable<br>";
} else {
    echo "❌ Session directory is not writable: $session_path<br>";
    echo "   <small>Fix: chmod 755 $session_path</small><br>";
}

echo "</div>";

// Server environment
echo "<div style='margin: 20px; padding: 20px; background: #e6f3ff; border: 1px solid #0066cc;'>";
echo "<h3>🖥️ Server Environment</h3>";

echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Web Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "<br>";
echo "Script Path: " . __FILE__ . "<br>";

// Check required PHP extensions
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'session', 'mbstring'];
echo "<h4>Required PHP Extensions:</h4>";

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext<br>";
    } else {
        echo "❌ $ext (MISSING)<br>";
    }
}

// Check PHP settings
echo "<h4>Important PHP Settings:</h4>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";

echo "</div>";

// Final summary
echo "<div style='margin: 20px; padding: 20px; background: #f0f8ff; border: 2px solid #4169e1;'>";
echo "<h3>📊 Summary</h3>";

$total_checks = 0;
$passed_checks = 0;

// Count checks (this is simplified, in real implementation you'd track each check)
echo "<strong>System Status:</strong><br>";

if ($user_count > 0) {
    echo "✅ Database has users<br>";
    $passed_checks++;
}
$total_checks++;

if (!empty($constraints)) {
    echo "✅ Foreign key constraints exist<br>";
    $passed_checks++;
} else {
    echo "⚠️ No foreign key constraints<br>";
}
$total_checks++;

if (file_exists($config_file)) {
    echo "✅ Configuration files present<br>";
    $passed_checks++;
}
$total_checks++;

$score = $total_checks > 0 ? round(($passed_checks / $total_checks) * 100) : 0;

echo "<br><strong>Overall Score: {$score}%</strong><br>";

if ($score >= 80) {
    echo "🎉 <span style='color: green;'>System looks healthy!</span><br>";
} elseif ($score >= 60) {
    echo "⚠️ <span style='color: orange;'>System needs some attention</span><br>";
} else {
    echo "🚨 <span style='color: red;'>System requires immediate fixes</span><br>";
}

echo "</div>";

// Quick links
echo "<div style='margin: 20px; padding: 20px; background: #fffacd; border: 1px solid #daa520;'>";
echo "<h3>🔗 Quick Links</h3>";
echo "<a href='login.php' style='margin: 5px; padding: 10px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Test Login Page</a> ";
echo "<a href='index.php' style='margin: 5px; padding: 10px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px;'>Test Home Page</a> ";
echo "<a href='unauthorized.php' style='margin: 5px; padding: 10px; background: #ff9800; color: white; text-decoration: none; border-radius: 5px;'>Test Unauthorized</a><br><br>";

echo "<small>Use these links to test the system after running the migration.</small>";
echo "</div>";

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Check - Text Labeling System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        h1 { color: #333; text-align: center; }
        .container { max-width: 1200px; margin: 0 auto; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        code { background: #f0f0f0; padding: 2px 5px; border-radius: 3px; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Content already echoed above -->
    </div>
</body>
</html>

echo "