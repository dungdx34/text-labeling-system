<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

echo "<h2>Upload Test Tool</h2>";
echo "<style>body { font-family: Arial; padding: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; } pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }</style>";

// Test 1: Basic database connectivity
echo "<h3>1. Testing Database Connection</h3>";
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "<div class='success'>✓ Database connected. Users count: $count</div>";
} catch (Exception $e) {
    echo "<div class='error'>✗ Database error: " . $e->getMessage() . "</div>";
}

// Test 2: Check tables structure
echo "<h3>2. Checking Table Structure</h3>";
$tables = ['documents', 'document_groups', 'labeling_tasks'];
foreach ($tables as $table) {
    try {
        $stmt = $db->prepare("DESCRIBE $table");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<div class='success'>✓ Table '$table' exists with columns: " . implode(', ', $columns) . "</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Table '$table' error: " . $e->getMessage() . "</div>";
    }
}

// Test 3: Manual insert test
echo "<h3>3. Testing Manual Insert</h3>";
if ($_POST && isset($_POST['test_insert'])) {
    try {
        $stmt = $db->prepare("INSERT INTO documents (title, content, ai_summary, type, created_by) 
                             VALUES ('Test Manual Insert', 'Test content from manual insert', 'Test AI summary', 'single', ?)");
        $stmt->execute([$_SESSION['user_id']]);
        $insert_id = $db->lastInsertId();
        echo "<div class='success'>✓ Manual insert successful. Document ID: $insert_id</div>";
        
        // Clean up
        $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$insert_id]);
        echo "<div class='info'>→ Test document cleaned up</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Manual insert failed: " . $e->getMessage() . "</div>";
    }
}

// Test 4: File upload test
echo "<h3>4. Testing File Upload</h3>";
if ($_POST && isset($_FILES['test_file'])) {
    echo "<div class='info'>POST Data received:</div>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    echo "<div class='info'>FILES Data received:</div>";
    echo "<pre>" . print_r($_FILES, true) . "</pre>";
    
    $file = $_FILES['test_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($file['tmp_name']);
        echo "<div class='success'>✓ File uploaded successfully</div>";
        echo "<div class='info'>File size: " . strlen($content) . " bytes</div>";
        echo "<div class='info'>First 200 chars:</div>";
        echo "<pre>" . htmlspecialchars(substr($content, 0, 200)) . "</pre>";
        
        // Try to process as JSONL
        $lines = explode("\n", trim($content));
        echo "<div class='info'>Lines found: " . count($lines) . "</div>";
        
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            echo "<div class='info'>Processing line " . ($i + 1) . ":</div>";
            echo "<pre>" . htmlspecialchars($line) . "</pre>";
            
            $data = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "<div class='success'>✓ JSON valid</div>";
                echo "<div class='info'>Data keys: " . implode(', ', array_keys($data)) . "</div>";
                
                // Try to insert
                if (isset($data['type']) && $data['type'] === 'single') {
                    if (isset($data['title'], $data['content'], $data['ai_summary'])) {
                        try {
                            $stmt = $db->prepare("INSERT INTO documents (title, content, ai_summary, type, created_by) 
                                                 VALUES (?, ?, ?, 'single', ?)");
                            $stmt->execute([$data['title'], $data['content'], $data['ai_summary'], $_SESSION['user_id']]);
                            $doc_id = $db->lastInsertId();
                            echo "<div class='success'>✓ Document inserted with ID: $doc_id</div>";
                        } catch (Exception $e) {
                            echo "<div class='error'>✗ Insert failed: " . $e->getMessage() . "</div>";
                        }
                    } else {
                        echo "<div class='error'>✗ Missing required fields for single document</div>";
                    }
                } elseif (isset($data['type']) && $data['type'] === 'multi') {
                    if (isset($data['group_title'], $data['group_summary'], $data['documents'])) {
                        try {
                            // Insert group
                            $stmt = $db->prepare("INSERT INTO document_groups (title, description, ai_summary, created_by) 
                                                 VALUES (?, ?, ?, ?)");
                            $stmt->execute([
                                $data['group_title'], 
                                $data['group_description'] ?? '',
                                $data['group_summary'], 
                                $_SESSION['user_id']
                            ]);
                            $group_id = $db->lastInsertId();
                            echo "<div class='success'>✓ Group inserted with ID: $group_id</div>";
                            
                            // Insert documents
                            foreach ($data['documents'] as $j => $doc) {
                                $stmt = $db->prepare("INSERT INTO documents (title, content, type, group_id, created_by) 
                                                     VALUES (?, ?, 'multi', ?, ?)");
                                $stmt->execute([$doc['title'], $doc['content'], $group_id, $_SESSION['user_id']]);
                                $doc_id = $db->lastInsertId();
                                echo "<div class='success'>✓ Multi-document " . ($j + 1) . " inserted with ID: $doc_id</div>";
                            }
                        } catch (Exception $e) {
                            echo "<div class='error'>✗ Multi-document insert failed: " . $e->getMessage() . "</div>";
                        }
                    } else {
                        echo "<div class='error'>✗ Missing required fields for multi-document</div>";
                    }
                } else {
                    echo "<div class='error'>✗ Invalid or missing 'type' field</div>";
                }
            } else {
                echo "<div class='error'>✗ JSON invalid: " . json_last_error_msg() . "</div>";
            }
            
            echo "<hr>";
        }
    } else {
        echo "<div class='error'>✗ File upload error: " . $file['error'] . "</div>";
    }
}

// Test 5: Check current documents
echo "<h3>5. Current Documents in Database</h3>";
try {
    $stmt = $db->prepare("SELECT id, title, type, created_at FROM documents ORDER BY id DESC LIMIT 10");
    $stmt->execute();
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($docs)) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Type</th><th>Created</th></tr>";
        foreach ($docs as $doc) {
            echo "<tr>";
            echo "<td>{$doc['id']}</td>";
            echo "<td>" . htmlspecialchars($doc['title']) . "</td>";
            echo "<td>{$doc['type']}</td>";
            echo "<td>{$doc['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>No documents found in database</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error fetching documents: " . $e->getMessage() . "</div>";
}

?>

<h3>Test Forms</h3>

<form method="POST" style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
    <h4>Test 1: Manual Insert</h4>
    <button type="submit" name="test_insert" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;">Test Manual Insert</button>
</form>

<form method="POST" enctype="multipart/form-data" style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
    <h4>Test 2: File Upload</h4>
    <p>Upload your JSONL file to test the upload process:</p>
    <input type="file" name="test_file" accept=".jsonl,.json,.txt" required style="margin: 10px 0;">
    <br>
    <button type="submit" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px;">Test File Upload</button>
</form>

<div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 5px;">
    <h4>Sample JSONL Content</h4>
    <p>Create a file named <code>test.jsonl</code> with this content:</p>
    <pre>{"type": "single", "title": "Test Single Document", "content": "This is test content for single document upload.", "ai_summary": "Test AI summary for single document."}</pre>
</div>

<div style="margin: 20px 0;">
    <a href="admin/upload.php" style="background: #6f42c1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Back to Main Upload</a>
    <a href="admin/documents.php" style="background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">View Documents</a>
    <a href="admin/dashboard.php" style="background: #fd7e14; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">Dashboard</a>
</div>