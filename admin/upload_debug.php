<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$debug_info = [];

// Debug: Show POST and FILES data
if ($_POST) {
    $debug_info[] = "POST Data received: " . print_r($_POST, true);
}
if ($_FILES) {
    $debug_info[] = "FILES Data received: " . print_r($_FILES, true);
}

// Handle JSONL file upload
if ($_POST && isset($_FILES['jsonl_file'])) {
    $debug_info[] = "POST and jsonl_file detected";
    
    $uploaded_file = $_FILES['jsonl_file'];
    $debug_info[] = "File error code: " . $uploaded_file['error'];
    $debug_info[] = "File size: " . $uploaded_file['size'];
    $debug_info[] = "File tmp_name: " . $uploaded_file['tmp_name'];
    $debug_info[] = "File exists: " . (file_exists($uploaded_file['tmp_name']) ? 'YES' : 'NO');
    
    if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload error code: " . $uploaded_file['error'];
        $debug_info[] = "Upload failed with error: " . $uploaded_file['error'];
    } elseif (empty($uploaded_file['tmp_name']) || !file_exists($uploaded_file['tmp_name'])) {
        $error = "Temporary file not found";
        $debug_info[] = "Temporary file not found or empty";
    } else {
        $debug_info[] = "File upload OK, processing...";
        
        $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
        $debug_info[] = "File extension: " . $file_extension;
        
        if ($file_extension === 'jsonl' || $file_extension === 'json') { // Accept both .jsonl and .json
            $debug_info[] = "File extension accepted, reading content...";
            
            $file_content = file_get_contents($uploaded_file['tmp_name']);
            $debug_info[] = "File content length: " . strlen($file_content);
            
            if (strlen($file_content) == 0) {
                $error = "File is empty";
                $debug_info[] = "File content is empty";
            } else {
                $debug_info[] = "First 500 chars: " . substr($file_content, 0, 500);
                
                $lines = explode("\n", $file_content);
                $debug_info[] = "Number of lines: " . count($lines);
                
                $success_count = 0;
                $error_count = 0;
                $errors = [];
                
                foreach ($lines as $line_number => $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        $debug_info[] = "Skipping empty line " . ($line_number + 1);
                        continue;
                    }
                    
                    $debug_info[] = "Processing line " . ($line_number + 1) . ": " . substr($line, 0, 100) . "...";
                    
                    $data = json_decode($line, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $error_count++;
                        $error_msg = "Line " . ($line_number + 1) . ": Invalid JSON - " . json_last_error_msg();
                        $errors[] = $error_msg;
                        $debug_info[] = $error_msg;
                        continue;
                    }
                    
                    $debug_info[] = "JSON parsed successfully. Keys: " . implode(', ', array_keys($data));
                    
                    // Validate required fields based on type
                    if (!isset($data['type']) || !in_array($data['type'], ['single', 'multi'])) {
                        $error_count++;
                        $error_msg = "Line " . ($line_number + 1) . ": Missing or invalid 'type' field. Found: " . ($data['type'] ?? 'NULL');
                        $errors[] = $error_msg;
                        $debug_info[] = $error_msg;
                        continue;
                    }
                    
                    $debug_info[] = "Type validation passed: " . $data['type'];
                    
                    try {
                        if ($data['type'] === 'single') {
                            $debug_info[] = "Processing single document...";
                            
                            // Check required fields
                            $required_fields = ['title', 'content', 'ai_summary'];
                            $missing_fields = [];
                            foreach ($required_fields as $field) {
                                if (!isset($data[$field])) {
                                    $missing_fields[] = $field;
                                }
                            }
                            
                            if (!empty($missing_fields)) {
                                throw new Exception("Missing required fields: " . implode(', ', $missing_fields));
                            }
                            
                            $debug_info[] = "All required fields present. Title: " . substr($data['title'], 0, 50);
                            
                            $query = "INSERT INTO documents (title, content, ai_summary, type, created_by, created_at) 
                                     VALUES (:title, :content, :ai_summary, :type, :created_by, NOW())";
                            $stmt = $db->prepare($query);
                            
                            $debug_info[] = "Prepared INSERT query for documents table";
                            
                            $stmt->bindParam(':title', $data['title']);
                            $stmt->bindParam(':content', $data['content']);
                            $stmt->bindParam(':ai_summary', $data['ai_summary']);
                            $stmt->bindParam(':type', $data['type']);
                            $stmt->bindParam(':created_by', $_SESSION['user_id']);
                            
                            $debug_info[] = "Parameters bound. User ID: " . $_SESSION['user_id'];
                            
                            if ($stmt->execute()) {
                                $insert_id = $db->lastInsertId();
                                $success_count++;
                                $debug_info[] = "✅ Successfully inserted single document with ID: " . $insert_id . " - " . $data['title'];
                            } else {
                                $error_info = $stmt->errorInfo();
                                throw new Exception("Database INSERT error: " . $error_info[2]);
                            }
                            
                        } else if ($data['type'] === 'multi') {
                            $debug_info[] = "Processing multi-document...";
                            
                            // Check required fields
                            $required_fields = ['group_title', 'group_summary', 'documents'];
                            $missing_fields = [];
                            foreach ($required_fields as $field) {
                                if (!isset($data[$field])) {
                                    $missing_fields[] = $field;
                                }
                            }
                            
                            if (!empty($missing_fields)) {
                                throw new Exception("Missing required fields: " . implode(', ', $missing_fields));
                            }
                            
                            if (!is_array($data['documents'])) {
                                throw new Exception("'documents' field must be an array");
                            }
                            
                            $debug_info[] = "Multi-document validation passed. Group: " . $data['group_title'] . ", Documents: " . count($data['documents']);
                            
                            // Start transaction
                            $db->beginTransaction();
                            $debug_info[] = "Started database transaction";
                            
                            // Insert document group
                            $query = "INSERT INTO document_groups (title, description, ai_summary, created_by, created_at) 
                                     VALUES (:title, :description, :ai_summary, :created_by, NOW())";
                            $stmt = $db->prepare($query);
                            
                            $stmt->bindParam(':title', $data['group_title']);
                            $group_desc = isset($data['group_description']) ? $data['group_description'] : '';
                            $stmt->bindParam(':description', $group_desc);
                            $stmt->bindParam(':ai_summary', $data['group_summary']);
                            $stmt->bindParam(':created_by', $_SESSION['user_id']);
                            
                            if (!$stmt->execute()) {
                                $error_info = $stmt->errorInfo();
                                throw new Exception("Failed to create document group: " . $error_info[2]);
                            }
                            
                            $group_id = $db->lastInsertId();
                            $debug_info[] = "✅ Created document group with ID: " . $group_id;
                            
                            // Insert individual documents
                            foreach ($data['documents'] as $doc_index => $document) {
                                if (!isset($document['title']) || !isset($document['content'])) {
                                    throw new Exception("Document " . ($doc_index + 1) . ": Missing title or content");
                                }
                                
                                $query = "INSERT INTO documents (title, content, type, group_id, created_by, created_at) 
                                         VALUES (:title, :content, 'multi', :group_id, :created_by, NOW())";
                                $stmt = $db->prepare($query);
                                
                                $stmt->bindParam(':title', $document['title']);
                                $stmt->bindParam(':content', $document['content']);
                                $stmt->bindParam(':group_id', $group_id);
                                $stmt->bindParam(':created_by', $_SESSION['user_id']);
                                
                                if (!$stmt->execute()) {
                                    $error_info = $stmt->errorInfo();
                                    throw new Exception("Failed to insert document " . ($doc_index + 1) . ": " . $error_info[2]);
                                }
                                
                                $doc_id = $db->lastInsertId();
                                $debug_info[] = "✅ Inserted document " . ($doc_index + 1) . " with ID: " . $doc_id . " - " . $document['title'];
                            }
                            
                            $db->commit();
                            $success_count++;
                            $debug_info[] = "✅ Committed transaction for multi-document group";
                        }
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                            $debug_info[] = "❌ Rolled back transaction due to error";
                        }
                        $error_count++;
                        $error_msg = "Line " . ($line_number + 1) . ": " . $e->getMessage();
                        $errors[] = $error_msg;
                        $debug_info[] = "❌ " . $error_msg;
                    }
                }
                
                $debug_info[] = "Processing complete. Success: $success_count, Errors: $error_count";
                
                if ($success_count > 0) {
                    $message = "Successfully imported $success_count item(s)";
                }
                if ($error_count > 0) {
                    $error = "Failed to import $error_count item(s):<br>" . implode('<br>', array_slice($errors, 0, 10));
                    if (count($errors) > 10) {
                        $error .= "<br>... and " . (count($errors) - 10) . " more errors";
                    }
                }
            }
        } else {
            $error = "Please upload a valid JSONL file (current extension: $file_extension)";
            $debug_info[] = "Invalid file extension: $file_extension";
        }
    }
} else {
    if ($_POST) {
        $debug_info[] = "POST received but jsonl_file not found in FILES or is empty";
        if (isset($_FILES['jsonl_file'])) {
            $debug_info[] = "jsonl_file exists but may be empty. Size: " . $_FILES['jsonl_file']['size'];
        } else {
            $debug_info[] = "jsonl_file not found in FILES array";
        }
    } else {
        $debug_info[] = "No POST data received";
    }
}

// Check database connection
try {
    $query = "SELECT COUNT(*) FROM documents";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $doc_count = $stmt->fetchColumn();
    $debug_info[] = "Current documents in database: " . $doc_count;
} catch (Exception $e) {
    $debug_info[] = "Database query error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Debug - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .debug-section {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="debug-section">
                    <h2 class="text-primary">
                        <i class="fas fa-bug me-2"></i>Upload Debug Tool
                    </h2>
                    <p class="text-muted">Debug tool để kiểm tra quá trình upload JSONL</p>
                    
                    <a href="upload.php" class="btn btn-outline-secondary mb-3">
                        <i class="fas fa-arrow-left me-2"></i>Quay lại Upload chính
                    </a>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Upload Form -->
                <div class="debug-section">
                    <h4><i class="fas fa-upload me-2"></i>Test JSONL Upload</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Chọn file JSONL:</label>
                            <input type="file" class="form-control" name="jsonl_file" accept=".jsonl" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>Upload và Debug
                        </button>
                    </form>
                </div>

                <!-- Debug Information -->
                <?php if (!empty($debug_info)): ?>
                    <div class="debug-section">
                        <h4><i class="fas fa-info-circle me-2"></i>Debug Information</h4>
                        <div class="debug-info">
<?php echo implode("\n\n", $debug_info); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Database Check -->
                <div class="debug-section">
                    <h4><i class="fas fa-database me-2"></i>Database Status</h4>
                    <?php
                    try {
                        // Check tables exist
                        $tables = ['users', 'documents', 'document_groups', 'labeling_tasks'];
                        foreach ($tables as $table) {
                            $query = "SHOW TABLES LIKE '$table'";
                            $stmt = $db->prepare($query);
                            $stmt->execute();
                            if ($stmt->rowCount() > 0) {
                                $count_query = "SELECT COUNT(*) FROM $table";
                                $count_stmt = $db->prepare($count_query);
                                $count_stmt->execute();
                                $count = $count_stmt->fetchColumn();
                                echo "<p class='text-success'><i class='fas fa-check me-2'></i>Table '$table' exists ($count records)</p>";
                            } else {
                                echo "<p class='text-danger'><i class='fas fa-times me-2'></i>Table '$table' NOT found</p>";
                            }
                        }
                    } catch (Exception $e) {
                        echo "<p class='text-danger'><i class='fas fa-exclamation-triangle me-2'></i>Database error: " . $e->getMessage() . "</p>";
                    }
                    ?>
                </div>

                <!-- Sample JSONL -->
                <div class="debug-section">
                    <h4><i class="fas fa-file-code me-2"></i>Sample JSONL Content</h4>
                    <p>Nếu chưa có file sample.jsonl, copy nội dung sau vào file:</p>
                    <div class="debug-info" style="max-height: 200px;">
{"type": "single", "title": "Test Document 1", "content": "This is a test document content for single document upload.", "ai_summary": "Test AI summary for single document."}
{"type": "multi", "group_title": "Test Multi Group", "group_description": "Test description", "group_summary": "Test group summary", "documents": [{"title": "Doc 1", "content": "Content 1"}, {"title": "Doc 2", "content": "Content 2"}]}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>