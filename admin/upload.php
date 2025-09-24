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

// FIXED LOGIC FOR MULTI-DOCUMENT WITH NESTED FORMAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['jsonl_file'])) {
    $file = $_FILES['jsonl_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
        $content = file_get_contents($file['tmp_name']);
        $lines = explode("\n", trim($content));
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $data = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_count++;
                $errors[] = "Dòng " . ($line_num + 1) . ": JSON không hợp lệ";
                continue;
            }
            
            if (!isset($data['type'])) {
                $error_count++;
                $errors[] = "Dòng " . ($line_num + 1) . ": Thiếu trường 'type'";
                continue;
            }
            
            try {
                if ($data['type'] === 'single') {
                    if (!isset($data['title']) || !isset($data['content'])) {
                        throw new Exception("Thiếu trường bắt buộc: title, content");
                    }
                    
                    // Kiểm tra các cột tồn tại trong database
                    $columns_query = $db->query("SHOW COLUMNS FROM documents");
                    $existing_columns = [];
                    while ($col = $columns_query->fetch(PDO::FETCH_ASSOC)) {
                        $existing_columns[] = $col['Field'];
                    }
                    
                    // Build dynamic INSERT query
                    $insert_columns = ['title', 'content'];
                    $insert_values = [$data['title'], $data['content']];
                    $placeholders = '?, ?';
                    
                    // Add ai_summary if column exists
                    if (in_array('ai_summary', $existing_columns)) {
                        $insert_columns[] = 'ai_summary';
                        $insert_values[] = $data['ai_summary'] ?? $data['summary'] ?? '';
                        $placeholders .= ', ?';
                    }
                    
                    // Add type if column exists
                    if (in_array('type', $existing_columns)) {
                        $insert_columns[] = 'type';
                        $insert_values[] = 'single';
                        $placeholders .= ', ?';
                    }
                    
                    // Add created_by if column exists (use uploaded_by or created_by)
                    if (in_array('created_by', $existing_columns)) {
                        $insert_columns[] = 'created_by';
                        $insert_values[] = $_SESSION['user_id'];
                        $placeholders .= ', ?';
                    } elseif (in_array('uploaded_by', $existing_columns)) {
                        $insert_columns[] = 'uploaded_by';
                        $insert_values[] = $_SESSION['user_id'];
                        $placeholders .= ', ?';
                    }
                    
                    $query = "INSERT INTO documents (" . implode(', ', $insert_columns) . ", created_at) VALUES (" . $placeholders . ", NOW())";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute($insert_values);
                    
                    if ($result) {
                        $success_count++;
                    } else {
                        throw new Exception("Database insert failed");
                    }
                    
                } elseif ($data['type'] === 'multi') {
					$columns_query = $db->query("SHOW COLUMNS FROM documents");
					$existing_columns = [];
					while ($col = $columns_query->fetch(PDO::FETCH_ASSOC)) {
						$existing_columns[] = $col['Field'];
					}
                    // FIXED: Handle nested documents format
                    if (!isset($data['group_title']) && !isset($data['group_name'])) {
                        throw new Exception("Thiếu trường bắt buộc: group_title hoặc group_name");
                    }
                    
                    if (!isset($data['documents']) || !is_array($data['documents'])) {
                        throw new Exception("Thiếu trường bắt buộc: documents (phải là array)");
                    }
                    
                    // Start transaction
                    $db->beginTransaction();
                    
                    // Create document_groups table if not exists
                    $check_table = $db->query("SHOW TABLES LIKE 'document_groups'");
                    if ($check_table->rowCount() == 0) {
                        $create_table = "CREATE TABLE IF NOT EXISTS document_groups (
                            id int(11) NOT NULL AUTO_INCREMENT,
                            group_name varchar(255) NOT NULL,
                            title varchar(255) NOT NULL,
                            description text,
                            ai_summary text,
                            combined_ai_summary text,
                            created_by int(11) DEFAULT 1,
                            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            status enum('pending','assigned','completed','reviewed') DEFAULT 'pending',
                            PRIMARY KEY (id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                        $db->exec($create_table);
                    }
                    
                    // Extract group info
                    $group_title = $data['group_title'] ?? $data['group_name'] ?? 'Multi-Document Group';
                    $group_description = $data['group_description'] ?? $data['description'] ?? '';
                    $group_summary = $data['group_summary'] ?? $data['ai_summary'] ?? '';
                    
                    // Insert group - check which columns exist
                    $group_columns_query = $db->query("SHOW COLUMNS FROM document_groups");
                    $group_existing_columns = [];
                    while ($col = $group_columns_query->fetch(PDO::FETCH_ASSOC)) {
                        $group_existing_columns[] = $col['Field'];
                    }
                    
                    // Build INSERT query for group
                    $group_insert_columns = ['group_name', 'created_by'];
                    $group_insert_values = [$group_title, $_SESSION['user_id']];
                    $group_placeholders = '?, ?';
                    
                    // Add title if column exists
                    if (in_array('title', $group_existing_columns)) {
                        $group_insert_columns[] = 'title';
                        $group_insert_values[] = $group_title;
                        $group_placeholders .= ', ?';
                    }
                    
                    // Add description if column exists
                    if (in_array('description', $group_existing_columns)) {
                        $group_insert_columns[] = 'description';
                        $group_insert_values[] = $group_description;
                        $group_placeholders .= ', ?';
                    }
                    
                    // Add ai_summary or combined_ai_summary if column exists
                    if (in_array('ai_summary', $group_existing_columns)) {
                        $group_insert_columns[] = 'ai_summary';
                        $group_insert_values[] = $group_summary;
                        $group_placeholders .= ', ?';
                    } elseif (in_array('combined_ai_summary', $group_existing_columns)) {
                        $group_insert_columns[] = 'combined_ai_summary';
                        $group_insert_values[] = $group_summary;
                        $group_placeholders .= ', ?';
                    }
                    
                    $query = "INSERT INTO document_groups (" . implode(', ', $group_insert_columns) . ", created_at) VALUES (" . $group_placeholders . ", NOW())";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute($group_insert_values);
                    
                    if (!$result) {
                        throw new Exception("Failed to insert document group");
                    }
                    
                    $group_id = $db->lastInsertId();
                    
                    // Insert individual documents from the documents array
                    foreach ($data['documents'] as $doc_index => $document) {
						if (!isset($document['title']) || !isset($document['content'])) {
							throw new Exception("Document " . ($doc_index + 1) . ": Missing title or content");
						}
						
						// Build dynamic INSERT for individual document
						$doc_insert_columns = ['title', 'content'];
						$doc_insert_values = [$document['title'], $document['content']];
						$doc_placeholders = '?, ?';
						
						// FIXED: Create AI summary for individual document
						$individual_ai_summary = '';
						
						// Priority 1: Use document's own summary if provided
						if (isset($document['ai_summary']) && !empty($document['ai_summary'])) {
							$individual_ai_summary = $document['ai_summary'];
						} 
						// Priority 2: Use document's summary field if provided
						elseif (isset($document['summary']) && !empty($document['summary'])) {
							$individual_ai_summary = $document['summary'];
						}
						// Priority 3: Generate from group summary + document title
						elseif (!empty($group_summary)) {
							$individual_ai_summary = "Tóm tắt cho '" . $document['title'] . "': " . 
								substr($group_summary, 0, 200) . "...";
						}
						// Priority 4: Generate from document content
						elseif (!empty($document['content'])) {
							$individual_ai_summary = "Tóm tắt AI cho: " . $document['title'] . ". " . 
								substr($document['content'], 0, 150) . "...";
						}
						// Fallback: Basic summary
						else {
							$individual_ai_summary = "Tóm tắt AI cho văn bản: " . $document['title'];
						}
						
						// Add ai_summary if column exists
						if (in_array('ai_summary', $existing_columns)) {
							$doc_insert_columns[] = 'ai_summary';
							$doc_insert_values[] = $individual_ai_summary;
							$doc_placeholders .= ', ?';
						}
						
						// Add type if column exists
						if (in_array('type', $existing_columns)) {
							$doc_insert_columns[] = 'type';
							$doc_insert_values[] = 'multi';
							$doc_placeholders .= ', ?';
						}
						
						// Add group_id if column exists
						if (in_array('group_id', $existing_columns)) {
							$doc_insert_columns[] = 'group_id';
							$doc_insert_values[] = $group_id;
							$doc_placeholders .= ', ?';
						}
						
						// Add created_by if column exists
						if (in_array('created_by', $existing_columns)) {
							$doc_insert_columns[] = 'created_by';
							$doc_insert_values[] = $_SESSION['user_id'];
							$doc_placeholders .= ', ?';
						} elseif (in_array('uploaded_by', $existing_columns)) {
							$doc_insert_columns[] = 'uploaded_by';
							$doc_insert_values[] = $_SESSION['user_id'];
							$doc_placeholders .= ', ?';
						}
						
						$query = "INSERT INTO documents (" . implode(', ', $doc_insert_columns) . ", created_at) VALUES (" . $doc_placeholders . ", NOW())";
						$stmt = $db->prepare($query);
						$result = $stmt->execute($doc_insert_values);
						
						if (!$result) {
							throw new Exception("Failed to insert document " . ($doc_index + 1));
						}
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
                $error_count++;
                $errors[] = "Dòng " . ($line_num + 1) . ": " . $e->getMessage();
            }
        }
        
        if ($success_count > 0) {
            $message = "Upload thành công! Đã xử lý $success_count item(s)";
            
            // Log activity if function exists
            if (function_exists('logActivity')) {
                logActivity($db, $_SESSION['user_id'], 'upload_jsonl', 'document', null, [
                    'success_count' => $success_count,
                    'error_count' => $error_count
                ]);
            }
        }
        if ($error_count > 0) {
            $error = "Có $error_count lỗi xảy ra";
            if (!empty($errors)) {
                $error .= ":<br>" . implode('<br>', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $error .= "<br>... và " . (count($errors) - 3) . " lỗi khác";
                }
            }
        }
        
    } else {
        $error = "File upload failed. Error code: " . $file['error'] . ", Size: " . $file['size'];
    }
}

// Handle manual document upload (keep existing logic)
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'upload_documents') {
    $upload_type = $_POST['upload_type'];
    
    try {
        if ($upload_type === 'single') {
            $title = trim($_POST['single_title']);
            $content = trim($_POST['single_content']);
            $summary = trim($_POST['single_summary']);
            
            // Handle file upload if provided
            if (isset($_FILES['single_file']) && $_FILES['single_file']['error'] === UPLOAD_ERR_OK) {
                $content = file_get_contents($_FILES['single_file']['tmp_name']);
            }
            
            if (empty($title) || empty($content) || empty($summary)) {
                throw new Exception("Vui lòng điền đầy đủ thông tin");
            }
            
            // Check columns exist
            $columns_query = $db->query("SHOW COLUMNS FROM documents");
            $existing_columns = [];
            while ($col = $columns_query->fetch(PDO::FETCH_ASSOC)) {
                $existing_columns[] = $col['Field'];
            }
            
            // Build dynamic query
            $insert_columns = ['title', 'content'];
            $insert_values = [$title, $content];
            $placeholders = '?, ?';
            
            if (in_array('ai_summary', $existing_columns)) {
                $insert_columns[] = 'ai_summary';
                $insert_values[] = $summary;
                $placeholders .= ', ?';
            }
            
            if (in_array('type', $existing_columns)) {
                $insert_columns[] = 'type';
                $insert_values[] = 'single';
                $placeholders .= ', ?';
            }
            
            if (in_array('created_by', $existing_columns)) {
                $insert_columns[] = 'created_by';
                $insert_values[] = $_SESSION['user_id'];
                $placeholders .= ', ?';
            } elseif (in_array('uploaded_by', $existing_columns)) {
                $insert_columns[] = 'uploaded_by';
                $insert_values[] = $_SESSION['user_id'];
                $placeholders .= ', ?';
            }
            
            $query = "INSERT INTO documents (" . implode(', ', $insert_columns) . ", created_at) VALUES (" . $placeholders . ", NOW())";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute($insert_values)) {
                $message = "Upload văn bản đơn thành công!";
                if (function_exists('logActivity')) {
                    logActivity($db, $_SESSION['user_id'], 'upload_single_document', 'document', $db->lastInsertId());
                }
            } else {
                throw new Exception("Không thể lưu văn bản");
            }
            
        } else if ($upload_type === 'multi') {
            $group_title = trim($_POST['group_title']);
            $group_description = trim($_POST['group_description']);
            $group_summary = trim($_POST['group_summary']);
            $doc_titles = $_POST['doc_title'];
            $doc_contents = $_POST['doc_content'];
            
            if (empty($group_title) || empty($group_summary)) {
                throw new Exception("Vui lòng điền đầy đủ thông tin nhóm");
            }
            
            // Start transaction
            $db->beginTransaction();
            
            // Check if document_groups table exists
            $check_table = $db->query("SHOW TABLES LIKE 'document_groups'");
            if ($check_table->rowCount() == 0) {
                // Create table if not exists
                $create_table = "CREATE TABLE IF NOT EXISTS document_groups (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    title varchar(255) NOT NULL,
                    description text,
                    ai_summary text,
                    created_by int(11) DEFAULT 1,
                    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $db->exec($create_table);
            }
            
            // Insert document group
            $query = "INSERT INTO document_groups (title, description, ai_summary, created_by, created_at) 
                     VALUES (?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);
            
            if (!$stmt->execute([$group_title, $group_description, $group_summary, $_SESSION['user_id']])) {
                throw new Exception("Không thể tạo nhóm văn bản");
            }
            
            $group_id = $db->lastInsertId();
            $doc_count = 0;
            
            // Insert individual documents
            for ($i = 0; $i < count($doc_titles); $i++) {
                if (!empty($doc_titles[$i]) && !empty($doc_contents[$i])) {
                    // Handle file upload for this document
                    $content = trim($doc_contents[$i]);
                    if (isset($_FILES['doc_file']['tmp_name'][$i]) && $_FILES['doc_file']['error'][$i] === UPLOAD_ERR_OK) {
                        $content = file_get_contents($_FILES['doc_file']['tmp_name'][$i]);
                    }
                    
                    // Check columns exist
                    $columns_query = $db->query("SHOW COLUMNS FROM documents");
                    $existing_columns = [];
                    while ($col = $columns_query->fetch(PDO::FETCH_ASSOC)) {
                        $existing_columns[] = $col['Field'];
                    }
                    
                    // Build dynamic query
                    $insert_columns = ['title', 'content'];
                    $insert_values = [trim($doc_titles[$i]), $content];
                    $placeholders = '?, ?';
                    
                    if (in_array('type', $existing_columns)) {
                        $insert_columns[] = 'type';
                        $insert_values[] = 'multi';
                        $placeholders .= ', ?';
                    }
                    
                    if (in_array('group_id', $existing_columns)) {
                        $insert_columns[] = 'group_id';
                        $insert_values[] = $group_id;
                        $placeholders .= ', ?';
                    }
                    
                    if (in_array('created_by', $existing_columns)) {
                        $insert_columns[] = 'created_by';
                        $insert_values[] = $_SESSION['user_id'];
                        $placeholders .= ', ?';
                    } elseif (in_array('uploaded_by', $existing_columns)) {
                        $insert_columns[] = 'uploaded_by';
                        $insert_values[] = $_SESSION['user_id'];
                        $placeholders .= ', ?';
                    }
                    
                    $query = "INSERT INTO documents (" . implode(', ', $insert_columns) . ", created_at) VALUES (" . $placeholders . ", NOW())";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute($insert_values)) {
                        $doc_count++;
                    }
                }
            }
            
            if ($doc_count > 0) {
                $db->commit();
                $message = "Upload nhóm văn bản thành công! Đã thêm $doc_count văn bản";
                if (function_exists('logActivity')) {
                    logActivity($db, $_SESSION['user_id'], 'upload_multi_documents', 'document_group', $group_id);
                }
            } else {
                $db->rollBack();
                throw new Exception("Không có văn bản nào được thêm");
            }
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Upload thất bại: " . $e->getMessage();
    }
}

// Get statistics with error handling
try {
    $query = "SELECT 
        (SELECT COUNT(*) FROM documents WHERE type = 'single' OR type IS NULL) as single_docs,
        (SELECT COUNT(*) FROM document_groups) as multi_groups,
        (SELECT COUNT(*) FROM documents WHERE type = 'multi') as multi_docs,
        (SELECT COUNT(*) FROM labeling_tasks WHERE status = 'pending') as pending_tasks,
        (SELECT COUNT(*) FROM labeling_tasks WHERE status = 'completed') as completed_tasks";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback statistics if some tables don't exist
    $stats = [
        'single_docs' => 0,
        'multi_groups' => 0,
        'multi_docs' => 0,
        'pending_tasks' => 0,
        'completed_tasks' => 0
    ];
    
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM documents");
        $stats['single_docs'] = $stmt->fetch()['count'];
    } catch (Exception $e) {}
    
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM labeling_tasks");
        $stats['pending_tasks'] = $stmt->fetch()['count'];
    } catch (Exception $e) {}
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Văn bản - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
        }
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            transition: all 0.3s ease;
            padding: 12px 20px;
            margin: 5px 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }
        .main-content { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
            margin: 20px; 
            padding: 40px; 
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .jsonl-upload {
            background: #f8f9fa;
            border: 3px dashed #28a745;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }
        .jsonl-upload:hover {
            background: #e9f7ef;
            border-color: #1e7e34;
        }
        .upload-type-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .upload-type-card:hover {
            border-color: #0d6efd;
            background: #f8f9ff;
            transform: translateY(-5px);
        }
        .upload-type-card.selected {
            border-color: #0d6efd;
            background: #e3f2fd;
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.2);
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .step.active {
            background: #0d6efd;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .document-item {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
            transition: all 0.3s ease;
        }
        .document-item:hover {
            border-color: #0d6efd;
            background: #e3f2fd;
        }
        .document-item.filled {
            border-style: solid;
            border-color: #28a745;
            background: #d4edda;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-4">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-tags me-2"></i>
                        Admin Panel
                    </h4>
                    <nav class="nav flex-column">
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="users.php" class="nav-link">
                            <i class="fas fa-users me-2"></i>Quản lý Users
                        </a>
                        <a href="upload.php" class="nav-link active">
                            <i class="fas fa-upload me-2"></i>Upload Dữ liệu
                        </a>
                        <a href="documents.php" class="nav-link">
                            <i class="fas fa-file-text me-2"></i>Quản lý Văn bản
                        </a>
                        <a href="tasks.php" class="nav-link">
                            <i class="fas fa-tasks me-2"></i>Quản lý Tasks
                        </a>
                        <a href="reports.php" class="nav-link">
                            <i class="fas fa-chart-bar me-2"></i>Báo cáo
                        </a>
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                        <a href="../logout.php" class="nav-link text-warning">
                            <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="text-primary">
                                <i class="fas fa-upload me-2"></i>Upload Văn bản để Gán nhãn
                            </h2>
                            <p class="text-muted mb-0">Upload dữ liệu từ file JSONL hoặc nhập thủ công</p>
                        </div>
                        <a href="documents.php" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-2"></i>Xem Văn bản
                        </a>
                    </div>

                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <h4><?php echo $stats['single_docs']; ?></h4>
                                <small>Văn bản đơn</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <h4><?php echo $stats['multi_groups']; ?></h4>
                                <small>Nhóm đa văn bản</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <h4><?php echo $stats['pending_tasks']; ?></h4>
                                <small>Task chờ gán nhãn</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <h4><?php echo $stats['completed_tasks']; ?></h4>
                                <small>Task hoàn thành</small>
                            </div>
                        </div>
                    </div>

                    <!-- JSONL Upload Section -->
                    <div class="jsonl-upload">
                        <form method="POST" enctype="multipart/form-data">
                            <h4 class="mb-3 text-success">
                                <i class="fas fa-file-import me-2"></i>Upload từ file JSONL
                            </h4>
                            <p class="text-muted mb-3">
                                Tải lên file JSONL chứa dữ liệu văn bản và bản tóm tắt AI.<br>
                                <small>Tham khảo: samples/sample.jsonl</small>
                            </p>
                            <div class="mb-3">
                                <input type="file" class="form-control" name="jsonl_file" accept=".jsonl,.json,.txt" required>
                            </div>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-upload me-2"></i>Upload JSONL File
                            </button>
                        </form>
                    </div>

                    <!-- Divider -->
                    <div class="text-center mb-4">
                        <hr class="w-25 d-inline-block">
                        <span class="px-3 text-muted">HOẶC</span>
                        <hr class="w-25 d-inline-block">
                    </div>

                    <!-- Manual Upload Section -->
                    <div class="manual-upload">
                        <h4 class="mb-4 text-center">
                            <i class="fas fa-edit me-2"></i>Nhập thủ công
                        </h4>
                        
                        <!-- Step Indicator -->
                        <div class="step-indicator">
                            <div class="step active" id="step-1">1</div>
                            <div class="step" id="step-2">2</div>
                            <div class="step" id="step-3">3</div>
                        </div>

                        <!-- Step 1: Choose Upload Type -->
                        <div class="upload-step" id="upload-step-1">
                            <h4 class="text-center mb-4">Bước 1: Chọn loại văn bản cần upload</h4>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="upload-type-card" data-type="single" onclick="selectUploadType('single')">
                                        <i class="fas fa-file-text fa-3x text-primary mb-3"></i>
                                        <h5>Văn bản đơn</h5>
                                        <p class="text-muted mb-0">Upload một văn bản và bản tóm tắt AI tương ứng</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="upload-type-card" data-type="multi" onclick="selectUploadType('multi')">
                                        <i class="fas fa-copy fa-3x text-success mb-3"></i>
                                        <h5>Đa văn bản</h5>
                                        <p class="text-muted mb-0">Upload nhiều văn bản cùng với bản tóm tắt AI chung</p>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button class="btn btn-primary" id="next-to-step-2" style="display: none;" onclick="goToStep(2)">
                                    Tiếp theo <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Upload Documents -->
                        <div class="upload-step" id="upload-step-2" style="display: none;">
                            <h4 class="text-center mb-4">Bước 2: Nhập văn bản</h4>
                            
                            <form id="manual-upload-form" method="POST" enctype="multipart/form-data">
                                <input type="hidden" id="upload-type" name="upload_type" value="">
                                <input type="hidden" name="action" value="upload_documents">
                                
                                <!-- Single Document Upload -->
                                <div id="single-upload" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header bg-primary text-white">
                                                    <h6 class="mb-0"><i class="fas fa-file-text me-2"></i>Văn bản</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Tiêu đề</label>
                                                        <input type="text" class="form-control" name="single_title" placeholder="Nhập tiêu đề văn bản..." required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Nội dung</label>
                                                        <textarea class="form-control" name="single_content" rows="8" placeholder="Nhập nội dung văn bản..." required></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Hoặc upload file</label>
                                                        <input type="file" class="form-control" name="single_file" accept=".txt,.docx">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header bg-success text-white">
                                                    <h6 class="mb-0"><i class="fas fa-robot me-2"></i>Bản tóm tắt AI</h6>
                                                </div>
                                                <div class="card-body">
                                                    <textarea class="form-control" name="single_summary" rows="12" placeholder="Nhập bản tóm tắt AI cho văn bản..." required></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Multi Document Upload -->
                                <div id="multi-upload" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="card">
                                                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0"><i class="fas fa-copy me-2"></i>Danh sách văn bản</h6>
                                                    <button type="button" class="btn btn-light btn-sm" onclick="addDocument()">
                                                        <i class="fas fa-plus me-1"></i>Thêm văn bản
                                                    </button>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Tiêu đề nhóm văn bản</label>
                                                        <input type="text" class="form-control" name="group_title" placeholder="Nhập tiêu đề cho nhóm văn bản..." required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Mô tả nhóm</label>
                                                        <textarea class="form-control" name="group_description" rows="2" placeholder="Mô tả ngắn về nhóm văn bản này..."></textarea>
                                                    </div>
                                                    
                                                    <div id="documents-container">
                                                        <!-- Document 1 -->
                                                        <div class="document-item" data-doc-index="1">
                                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                                <h6 class="mb-0"><i class="fas fa-file-text me-2"></i>Văn bản #1</h6>
                                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDocument(1)" style="display: none;">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                            
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Tiêu đề</label>
                                                                        <input type="text" class="form-control" name="doc_title[]" placeholder="Tiêu đề văn bản..." required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Upload file</label>
                                                                        <input type="file" class="form-control" name="doc_file[]" accept=".txt,.docx">
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Nội dung</label>
                                                                        <textarea class="form-control" name="doc_content[]" rows="6" placeholder="Hoặc nhập nội dung trực tiếp..." required></textarea>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card">
                                                <div class="card-header bg-success text-white">
                                                    <h6 class="mb-0"><i class="fas fa-robot me-2"></i>Bản tóm tắt AI chung</h6>
                                                </div>
                                                <div class="card-body">
                                                    <textarea class="form-control" name="group_summary" rows="15" placeholder="Nhập bản tóm tắt AI cho toàn bộ nhóm văn bản..." required></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center mt-4">
                                    <button type="button" class="btn btn-secondary me-3" onclick="goToStep(1)">
                                        <i class="fas fa-arrow-left me-2"></i>Quay lại
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">
                                        Xem trước <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Step 3: Review and Submit -->
                        <div class="upload-step" id="upload-step-3" style="display: none;">
                            <h4 class="text-center mb-4">Bước 3: Xem trước và xác nhận</h4>
                            
                            <div class="card">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0"><i class="fas fa-eye me-2"></i>Xem trước dữ liệu</h6>
                                </div>
                                <div class="card-body" id="preview-content">
                                    <!-- Preview will be generated here -->
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="button" class="btn btn-secondary me-3" onclick="goToStep(2)">
                                    <i class="fas fa-arrow-left me-2"></i>Sửa đổi
                                </button>
                                <button type="button" class="btn btn-success btn-lg" onclick="submitForm()">
                                    <i class="fas fa-check me-2"></i>Xác nhận Upload
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedUploadType = '';
        let documentCount = 1;

        function selectUploadType(type) {
            selectedUploadType = type;
            
            // Update UI
            document.querySelectorAll('.upload-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`[data-type="${type}"]`).classList.add('selected');
            
            // Show next button
            document.getElementById('next-to-step-2').style.display = 'block';
            
            // Set hidden input
            document.getElementById('upload-type').value = type;
        }

        function goToStep(step) {
            // Hide all steps
            document.querySelectorAll('.upload-step').forEach(stepDiv => {
                stepDiv.style.display = 'none';
            });
            
            // Update step indicators
            document.querySelectorAll('.step').forEach(stepIndicator => {
                stepIndicator.classList.remove('active', 'completed');
            });
            
            // Show current step and update indicators
            document.getElementById(`upload-step-${step}`).style.display = 'block';
            
            for (let i = 1; i < step; i++) {
                document.getElementById(`step-${i}`).classList.add('completed');
            }
            document.getElementById(`step-${step}`).classList.add('active');
            
            // Show appropriate upload form
            if (step === 2) {
                if (selectedUploadType === 'single') {
                    document.getElementById('single-upload').style.display = 'block';
                    document.getElementById('multi-upload').style.display = 'none';
                } else {
                    document.getElementById('single-upload').style.display = 'none';
                    document.getElementById('multi-upload').style.display = 'block';
                }
            }
            
            // Generate preview if step 3
            if (step === 3) {
                generatePreview();
            }
        }

        function addDocument() {
            documentCount++;
            const container = document.getElementById('documents-container');
            
            const newDoc = document.createElement('div');
            newDoc.className = 'document-item';
            newDoc.setAttribute('data-doc-index', documentCount);
            
            newDoc.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="fas fa-file-text me-2"></i>Văn bản #${documentCount}</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDocument(${documentCount})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Tiêu đề</label>
                            <input type="text" class="form-control" name="doc_title[]" placeholder="Tiêu đề văn bản...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload file</label>
                            <input type="file" class="form-control" name="doc_file[]" accept=".txt,.docx">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Nội dung</label>
                            <textarea class="form-control" name="doc_content[]" rows="6" placeholder="Hoặc nhập nội dung trực tiếp..."></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(newDoc);
            
            // Show remove buttons for all documents except first
            document.querySelectorAll('.document-item').forEach((item, index) => {
                const removeBtn = item.querySelector('.btn-outline-danger');
                if (index > 0) {
                    removeBtn.style.display = 'block';
                }
            });
        }

        function removeDocument(index) {
            const docElement = document.querySelector(`[data-doc-index="${index}"]`);
            if (docElement) {
                docElement.remove();
                
                // Update numbering
                document.querySelectorAll('.document-item').forEach((item, idx) => {
                    const title = item.querySelector('h6');
                    title.innerHTML = `<i class="fas fa-file-text me-2"></i>Văn bản #${idx + 1}`;
                    item.setAttribute('data-doc-index', idx + 1);
                });
                
                documentCount = document.querySelectorAll('.document-item').length;
                
                // Hide remove button for first document if only one left
                if (documentCount === 1) {
                    document.querySelector('.btn-outline-danger').style.display = 'none';
                }
            }
        }

        function generatePreview() {
            const previewContent = document.getElementById('preview-content');
            let html = '';
            
            if (selectedUploadType === 'single') {
                const title = document.querySelector('[name="single_title"]').value;
                const content = document.querySelector('[name="single_content"]').value;
                const summary = document.querySelector('[name="single_summary"]').value;
                
                html = `
                    <h5><i class="fas fa-file-text me-2"></i>${title || 'Chưa nhập tiêu đề'}</h5>
                    <p><strong>Loại:</strong> Văn bản đơn</p>
                    <p><strong>Nội dung:</strong> ${content ? content.substring(0, 200) + '...' : 'Chưa nhập nội dung'}</p>
                    <p><strong>Bản tóm tắt AI:</strong> ${summary ? summary.substring(0, 200) + '...' : 'Chưa nhập tóm tắt'}</p>
                `;
            } else {
                const groupTitle = document.querySelector('[name="group_title"]').value;
                const groupDesc = document.querySelector('[name="group_description"]').value;
                const groupSummary = document.querySelector('[name="group_summary"]').value;
                const docTitles = document.querySelectorAll('[name="doc_title[]"]');
                
                html = `
                    <h5><i class="fas fa-copy me-2"></i>${groupTitle || 'Chưa nhập tiêu đề nhóm'}</h5>
                    <p><strong>Loại:</strong> Đa văn bản</p>
                    <p><strong>Mô tả:</strong> ${groupDesc || 'Chưa có mô tả'}</p>
                    <p><strong>Số văn bản:</strong> ${docTitles.length}</p>
                    <p><strong>Danh sách văn bản:</strong></p>
                    <ul>
                `;
                
                docTitles.forEach((titleInput, index) => {
                    html += `<li>${titleInput.value || 'Văn bản ' + (index + 1)}</li>`;
                });
                
                html += `
                    </ul>
                    <p><strong>Bản tóm tắt AI chung:</strong> ${groupSummary ? groupSummary.substring(0, 200) + '...' : 'Chưa nhập tóm tắt'}</p>
                `;
            }
            
            previewContent.innerHTML = html;
        }

        function submitForm() {
            document.getElementById('manual-upload-form').submit();
        }

        // Auto-save functionality
        setInterval(() => {
            // Auto-save form data to localStorage
            const formData = new FormData(document.getElementById('manual-upload-form'));
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            localStorage.setItem('upload_form_backup', JSON.stringify(data));
        }, 30000); // Save every 30 seconds

        // Load saved data on page load
        window.addEventListener('load', () => {
            const savedData = localStorage.getItem('upload_form_backup');
            if (savedData) {
                // Optionally restore form data
                console.log('Backup data available');
            }
        });
    </script>
</body>
</html>
                        