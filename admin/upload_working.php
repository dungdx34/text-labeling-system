<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Simple JSONL upload handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['jsonl_file'])) {
    
    echo "<h3>DEBUG: Processing upload...</h3>";
    echo "POST data: <pre>" . print_r($_POST, true) . "</pre>";
    echo "FILES data: <pre>" . print_r($_FILES, true) . "</pre>";
    
    $file = $_FILES['jsonl_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
        echo "<p>✓ File uploaded successfully</p>";
        
        $content = file_get_contents($file['tmp_name']);
        echo "<p>File content length: " . strlen($content) . " bytes</p>";
        echo "<p>First 500 chars: <pre>" . htmlspecialchars(substr($content, 0, 500)) . "</pre></p>";
        
        $lines = explode("\n", trim($content));
        echo "<p>Number of lines: " . count($lines) . "</p>";
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            echo "<hr><h4>Processing line " . ($line_num + 1) . ":</h4>";
            echo "<pre>" . htmlspecialchars($line) . "</pre>";
            
            $data = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "<p style='color: red;'>JSON Error: " . json_last_error_msg() . "</p>";
                $error_count++;
                continue;
            }
            
            echo "<p style='color: green;'>✓ JSON parsed successfully</p>";
            echo "<p>Data keys: " . implode(', ', array_keys($data)) . "</p>";
            
            if (!isset($data['type'])) {
                echo "<p style='color: red;'>Missing 'type' field</p>";
                $error_count++;
                continue;
            }
            
            try {
                if ($data['type'] === 'single') {
                    echo "<p>Processing single document...</p>";
                    
                    if (!isset($data['title']) || !isset($data['content']) || !isset($data['ai_summary'])) {
                        throw new Exception("Missing required fields: title, content, ai_summary");
                    }
                    
                    $query = "INSERT INTO documents (title, content, ai_summary, type, created_by, created_at) 
                             VALUES (?, ?, ?, 'single', ?, NOW())";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([
                        $data['title'],
                        $data['content'],
                        $data['ai_summary'],
                        $_SESSION['user_id']
                    ]);
                    
                    if ($result) {
                        $doc_id = $db->lastInsertId();
                        echo "<p style='color: green;'>✓ Single document inserted with ID: $doc_id</p>";
                        $success_count++;
                    } else {
                        throw new Exception("Database insert failed");
                    }
                    
                } elseif ($data['type'] === 'multi') {
                    echo "<p>Processing multi-document group...</p>";
                    
                    if (!isset($data['group_title']) || !isset($data['group_summary']) || !isset($data['documents'])) {
                        throw new Exception("Missing required fields: group_title, group_summary, documents");
                    }
                    
                    // Start transaction
                    $db->beginTransaction();
                    
                    // Insert group
                    $query = "INSERT INTO document_groups (title, description, ai_summary, created_by, created_at) 
                             VALUES (?, ?, ?, ?, NOW())";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([
                        $data['group_title'],
                        $data['group_description'] ?? '',
                        $data['group_summary'],
                        $_SESSION['user_id']
                    ]);
                    
                    if (!$result) {
                        throw new Exception("Failed to insert document group");
                    }
                    
                    $group_id = $db->lastInsertId();
                    echo "<p style='color: green;'>✓ Group inserted with ID: $group_id</p>";
                    
                    // Insert documents
                    foreach ($data['documents'] as $doc_index => $document) {
                        if (!isset($document['title']) || !isset($document['content'])) {
                            throw new Exception("Document " . ($doc_index + 1) . ": Missing title or content");
                        }
                        
                        $query = "INSERT INTO documents (title, content, type, group_id, created_by, created_at) 
                                 VALUES (?, ?, 'multi', ?, ?, NOW())";
                        $stmt = $db->prepare($query);
                        $result = $stmt->execute([
                            $document['title'],
                            $document['content'],
                            $group_id,
                            $_SESSION['user_id']
                        ]);
                        
                        if (!$result) {
                            throw new Exception("Failed to insert document " . ($doc_index + 1));
                        }
                        
                        $doc_id = $db->lastInsertId();
                        echo "<p style='color: green;'>✓ Document " . ($doc_index + 1) . " inserted with ID: $doc_id</p>";
                    }
                    
                    $db->commit();
                    $success_count++;
                    
                } else {
                    throw new Exception("Invalid type: " . $data['type']);
                }
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
                $error_count++;
            }
        }
        
        echo "<hr><h3>SUMMARY:</h3>";
        echo "<p style='color: green;'>✓ Successfully processed: $success_count items</p>";
        echo "<p style='color: red;'>✗ Errors: $error_count items</p>";
        
        if ($success_count > 0) {
            $message = "Upload successful! Processed $success_count items.";
        }
        if ($error_count > 0) {
            $error = "Some items failed to process: $error_count errors.";
        }
        
    } else {
        $error = "File upload failed. Error code: " . $file['error'] . ", Size: " . $file['size'];
    }
}

// Get current stats
$doc_count = 0;
$group_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents");
    $stmt->execute();
    $doc_count = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM document_groups");
    $stmt->execute();
    $group_count = $stmt->fetchColumn();
} catch (Exception $e) {
    // Ignore stats errors
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSONL Upload - Working Version</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .upload-area { 
            border: 3px dashed #007bff; 
            border-radius: 10px; 
            padding: 40px; 
            text-align: center; 
            background: #f8f9ff;
            margin: 20px 0;
        }
        .stats { 
            background: #e9ecef; 
            padding: 20px; 
            border-radius: 10px; 
            margin: 20px 0; 
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>JSONL Upload - Working Version</h1>
        
        <div class="stats">
            <h3>Current Database Status</h3>
            <p><strong>Documents:</strong> <?php echo $doc_count; ?></p>
            <p><strong>Document Groups:</strong> <?php echo $group_count; ?></p>
            <p><strong>Current User:</strong> <?php echo $_SESSION['username']; ?> (ID: <?php echo $_SESSION['user_id']; ?>)</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="upload-area">
            <h3>Upload JSONL File</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <input type="file" class="form-control" name="jsonl_file" accept=".jsonl,.json,.txt" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-upload"></i> Upload and Process
                </button>
            </form>
        </div>

        <div class="alert alert-info">
            <h5>Sample JSONL Content:</h5>
            <pre>{"type": "single", "title": "Test Document", "content": "This is test content.", "ai_summary": "Test summary"}
{"type": "multi", "group_title": "Test Group", "group_summary": "Group summary", "documents": [{"title": "Doc 1", "content": "Content 1"}, {"title": "Doc 2", "content": "Content 2"}]}</pre>
        </div>

        <div style="margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            <a href="documents.php" class="btn btn-info">View Documents</a>
            <a href="upload.php" class="btn btn-warning">Original Upload Page</a>
        </div>
    </div>
</body>
</html>