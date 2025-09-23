<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Handle JSONL file upload
if ($_POST && isset($_FILES['jsonl_file']) && $_FILES['jsonl_file']['error'] === UPLOAD_ERR_OK) {
    $uploaded_file = $_FILES['jsonl_file'];
    $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
    
    if ($file_extension === 'jsonl') {
        $file_content = file_get_contents($uploaded_file['tmp_name']);
        $lines = explode("\n", $file_content);
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($lines as $line_number => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $data = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_count++;
                $errors[] = "Line " . ($line_number + 1) . ": Invalid JSON - " . json_last_error_msg();
                continue;
            }
            
            // Validate required fields based on type
            if (!isset($data['type']) || !in_array($data['type'], ['single', 'multi'])) {
                $error_count++;
                $errors[] = "Line " . ($line_number + 1) . ": Missing or invalid 'type' field";
                continue;
            }
            
            try {
                if ($data['type'] === 'single') {
                    // Handle single document
                    if (!isset($data['title']) || !isset($data['content']) || !isset($data['ai_summary'])) {
                        throw new Exception("Missing required fields: title, content, ai_summary");
                    }
                    
                    $query = "INSERT INTO documents (title, content, ai_summary, type, created_by, created_at) 
                             VALUES (:title, :content, :ai_summary, :type, :created_by, NOW())";
                    $stmt = $db->prepare($query);
                    
                    $stmt->bindParam(':title', $data['title']);
                    $stmt->bindParam(':content', $data['content']);
                    $stmt->bindParam(':ai_summary', $data['ai_summary']);
                    $stmt->bindParam(':type', $data['type']);
                    $stmt->bindParam(':created_by', $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $success_count++;
                    } else {
                        throw new Exception("Database error");
                    }
                    
                } else if ($data['type'] === 'multi') {
                    // Handle multi-document
                    if (!isset($data['group_title']) || !isset($data['group_summary']) || !isset($data['documents']) || !is_array($data['documents'])) {
                        throw new Exception("Missing required fields: group_title, group_summary, documents array");
                    }
                    
                    // Start transaction
                    $db->beginTransaction();
                    
                    // Insert document group
                    $query = "INSERT INTO document_groups (title, description, ai_summary, created_by, created_at) 
                             VALUES (:title, :description, :ai_summary, :created_by, NOW())";
                    $stmt = $db->prepare($query);
                    
                    $stmt->bindParam(':title', $data['group_title']);
                    $stmt->bindParam(':description', $data['group_description'] ?? '');
                    $stmt->bindParam(':ai_summary', $data['group_summary']);
                    $stmt->bindParam(':created_by', $_SESSION['user_id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to create document group");
                    }
                    
                    $group_id = $db->lastInsertId();
                    
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
                            throw new Exception("Failed to insert document " . ($doc_index + 1));
                        }
                    }
                    
                    $db->commit();
                    $success_count++;
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error_count++;
                $errors[] = "Line " . ($line_number + 1) . ": " . $e->getMessage();
            }
        }
        
        if ($success_count > 0) {
            $message = "Successfully imported $success_count item(s)";
        }
        if ($error_count > 0) {
            $error = "Failed to import $error_count item(s):<br>" . implode('<br>', array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $error .= "<br>... and " . (count($errors) - 10) . " more errors";
            }
        }
    } else {
        $error = "Please upload a valid JSONL file";
    }
}

// Handle manual document upload
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'upload_documents') {
    $upload_type = $_POST['upload_type'];
    
    try {
        if ($upload_type === 'single') {
            $title = $_POST['single_title'];
            $content = $_POST['single_content'];
            $summary = $_POST['single_summary'];
            
            // Handle file upload if provided
            if (isset($_FILES['single_file']) && $_FILES['single_file']['error'] === UPLOAD_ERR_OK) {
                $content = file_get_contents($_FILES['single_file']['tmp_name']);
            }
            
            $query = "INSERT INTO documents (title, content, ai_summary, type, created_by, created_at) 
                     VALUES (:title, :content, :ai_summary, :type, :created_by, NOW())";
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':ai_summary', $summary);
            $stmt->bindValue(':type', 'single');
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $message = "Document uploaded successfully!";
            } else {
                $error = "Failed to upload document";
            }
            
        } else if ($upload_type === 'multi') {
            $group_title = $_POST['group_title'];
            $group_description = $_POST['group_description'];
            $group_summary = $_POST['group_summary'];
            $doc_titles = $_POST['doc_title'];
            $doc_contents = $_POST['doc_content'];
            
            // Start transaction
            $db->beginTransaction();
            
            // Insert document group
            $query = "INSERT INTO document_groups (title, description, ai_summary, created_by, created_at) 
                     VALUES (:title, :description, :ai_summary, :created_by, NOW())";
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(':title', $group_title);
            $stmt->bindParam(':description', $group_description);
            $stmt->bindParam(':ai_summary', $group_summary);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create document group");
            }
            
            $group_id = $db->lastInsertId();
            
            // Insert individual documents
            for ($i = 0; $i < count($doc_titles); $i++) {
                if (!empty($doc_titles[$i]) && !empty($doc_contents[$i])) {
                    // Handle file upload for this document
                    if (isset($_FILES['doc_file']['tmp_name'][$i]) && $_FILES['doc_file']['error'][$i] === UPLOAD_ERR_OK) {
                        $doc_contents[$i] = file_get_contents($_FILES['doc_file']['tmp_name'][$i]);
                    }
                    
                    $query = "INSERT INTO documents (title, content, type, group_id, created_by, created_at) 
                             VALUES (:title, :content, 'multi', :group_id, :created_by, NOW())";
                    $stmt = $db->prepare($query);
                    
                    $stmt->bindParam(':title', $doc_titles[$i]);
                    $stmt->bindParam(':content', $doc_contents[$i]);
                    $stmt->bindParam(':group_id', $group_id);
                    $stmt->bindParam(':created_by', $_SESSION['user_id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to insert document " . ($i + 1));
                    }
                }
            }
            
            $db->commit();
            $message = "Multi-document group uploaded successfully!";
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Upload failed: " . $e->getMessage();
    }
}

// Get statistics
$query = "SELECT 
            (SELECT COUNT(*) FROM documents WHERE type = 'single') as single_docs,
            (SELECT COUNT(*) FROM document_groups) as multi_groups,
            (SELECT COUNT(*) FROM documents WHERE type = 'multi') as multi_docs,
            (SELECT COUNT(*) FROM labeling_tasks WHERE status = 'pending') as pending_tasks,
            (SELECT COUNT(*) FROM labeling_tasks WHERE status = 'completed') as completed_tasks";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

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
        .main-content { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
            margin: 20px; 
            padding: 40px; 
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
        .text-gradient { 
            background: linear-gradient(135deg, #0d6efd, #764ba2); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-gradient">
                        <i class="fas fa-upload me-2"></i>Upload Văn bản để Gán nhãn
                    </h2>
                    <p class="text-muted mb-0">Upload dữ liệu từ file JSONL hoặc nhập thủ công</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại Dashboard
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
                        <small>Tham khao: samples/sample.jsonl</small>
                    </p>
                    <div class="mb-3">
                        <input type="file" class="form-control" name="jsonl_file" accept=".jsonl" required>
                    </div>
                    <button type="submit" class="btn btn-success">
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
                    <h4 class="text-center mb-4">Bước 2: Upload văn bản</h4>
                    
                    <form id="manual-upload-form" method="POST" enctype="multipart/form-data">
                        <input type="hidden" id="upload-type" name="upload_type" value="">
                        
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
                                                <input type="text" class="form-control" name="single_title" placeholder="Nhập tiêu đề văn bản...">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Nội dung</label>
                                                <textarea class="form-control" name="single_content" rows="8" placeholder="Nhập nội dung văn bản..."></textarea>
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
                                            <textarea class="form-control" name="single_summary" rows="12" placeholder="Nhập bản tóm tắt AI cho văn bản..."></textarea>
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
                                                <input type="text" class="form-control" name="group_title" placeholder="Nhập tiêu đề cho nhóm văn bản...">
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
                                                                <input type="text" class="form-control" name="doc_title[]" placeholder="Tiêu đề văn bản...">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Upload file</label>
                                                                <input type="file" class="form-control" name="doc_file[]" accept=".txt,.docx" onchange="handleDocumentFile(this, 1)">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Nội dung</label>
                                                                <textarea class="form-control" name="doc_content[]" rows="6" placeholder="Hoặc nhập nội dung trực tiếp..."></textarea>
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
                                        <div class="card-header bg-warning text-dark">
                                            <h6 class="mb-0"><i class="fas fa-robot me-2"></i>Bản tóm tắt AI chung</h6>
                                        </div>
                                        <div class="card-body">
                                            <textarea class="form-control" name="group_summary" rows="20" placeholder="Nhập bản tóm tắt AI cho toàn bộ nhóm văn bản..."></textarea>
                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Bản tóm tắt này sẽ được sử dụng cho toàn bộ nhóm văn bản
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="d-flex justify-content-between mt-4">
                        <button class="btn btn-outline-secondary" onclick="goToStep(1)">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại
                        </button>
                        <button class="btn btn-primary" onclick="goToStep(3)">
                            Tiếp theo <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Preview and Confirm -->
                <div class="upload-step" id="upload-step-3" style="display: none;">
                    <h4 class="text-center mb-4">Bước 3: Xem trước và xác nhận</h4>
                    
                    <div id="preview-content">
                        <!-- Preview content will be generated here -->
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <button class="btn btn-outline-secondary" onclick="goToStep(2)">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại
                        </button>
                        <button class="btn btn-success" onclick="submitManualUpload()">
                            <i class="fas fa-check me-2"></i>Xác nhận Upload
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStep = 1;
        let uploadType = '';
        let documentCounter = 1;

        // Step navigation
        function goToStep(step) {
            // Hide all steps
            document.querySelectorAll('.upload-step').forEach(el => el.style.display = 'none');
            
            // Show target step
            document.getElementById(`upload-step-${step}`).style.display = 'block';
            
            // Update step indicator
            document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
            for (let i = 1; i <= step; i++) {
                const stepEl = document.getElementById(`step-${i}`);
                if (i < step) {
                    stepEl.classList.add('completed');
                } else if (i === step) {
                    stepEl.classList.add('active');
                }
            }
            
            currentStep = step;
            
            // Generate preview for step 3
            if (step === 3) {
                generatePreview();
            }
        }

        // Upload type selection
        function selectUploadType(type) {
            uploadType = type;
            document.getElementById('upload-type').value = type;
            
            // Update UI
            document.querySelectorAll('.upload-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`[data-type="${type}"]`).classList.add('selected');
            
            // Show next button
            document.getElementById('next-to-step-2').style.display = 'inline-block';
            
            // Prepare step 2 based on type
            if (type === 'single') {
                document.getElementById('single-upload').style.display = 'block';
                document.getElementById('multi-upload').style.display = 'none';
            } else {
                document.getElementById('single-upload').style.display = 'none';
                document.getElementById('multi-upload').style.display = 'block';
            }
        }

        // Multi-document functions
        function addDocument() {
            documentCounter++;
            const container = document.getElementById('documents-container');
            
            const documentHtml = `
                <div class="document-item" data-doc-index="${documentCounter}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="fas fa-file-text me-2"></i>Văn bản #${documentCounter}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDocument(${documentCounter})">
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
                                <input type="file" class="form-control" name="doc_file[]" accept=".txt,.docx" onchange="handleDocumentFile(this, ${documentCounter})">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nội dung</label>
                                <textarea class="form-control" name="doc_content[]" rows="6" placeholder="Hoặc nhập nội dung trực tiếp..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', documentHtml);
            updateRemoveButtons();
        }

        function removeDocument(index) {
            const element = document.querySelector(`[data-doc-index="${index}"]`);
            if (element) {
                element.remove();
                updateRemoveButtons();
            }
        }

        function updateRemoveButtons() {
            const documents = document.querySelectorAll('.document-item');
            documents.forEach((doc, index) => {
                const removeBtn = doc.querySelector('.btn-outline-danger');
                if (documents.length > 1) {
                    removeBtn.style.display = 'inline-block';
                } else {
                    removeBtn.style.display = 'none';
                }
            });
        }

        function handleDocumentFile(input, index) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const textarea = document.querySelector(`[data-doc-index="${index}"] textarea[name="doc_content[]"]`);
                    textarea.value = e.target.result;
                    
                    // Mark as filled
                    const docItem = document.querySelector(`[data-doc-index="${index}"]`);
                    docItem.classList.add('filled');
                };
                
                reader.readAsText(file);
            }
        }

        // Preview generation
        function generatePreview() {
            const previewContainer = document.getElementById('preview-content');
            let previewHtml = '';

            if (uploadType === 'single') {
                const title = document.querySelector('input[name="single_title"]').value;
                const content = document.querySelector('textarea[name="single_content"]').value;
                const summary = document.querySelector('textarea[name="single_summary"]').value;

                previewHtml = `
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-file-text me-2"></i>Văn bản đơn</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="text-primary">Tiêu đề:</h6>
                            <p><strong>${title || 'Chưa có tiêu đề'}</strong></p>
                            
                            <h6 class="text-primary">Nội dung:</h6>
                            <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                ${content || 'Chưa có nội dung'}
                            </div>
                            
                            <h6 class="text-primary mt-3">Bản tóm tắt AI:</h6>
                            <div class="border rounded p-3 bg-success bg-opacity-10" style="max-height: 150px; overflow-y: auto;">
                                ${summary || 'Chưa có bản tóm tắt'}
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-4 text-center">
                                    <div class="h5 text-info">${content.length}</div>
                                    <small class="text-muted">Ký tự</small>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="h5 text-success">${content.trim() ? content.trim().split(/\\s+/).length : 0}</div>
                                    <small class="text-muted">Từ</small>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="h5 text-warning">${content.split(/[.!?]+/).filter(s => s.trim()).length}</div>
                                    <small class="text-muted">Câu</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                const groupTitle = document.querySelector('input[name="group_title"]').value;
                const groupDescription = document.querySelector('textarea[name="group_description"]').value;
                const groupSummary = document.querySelector('textarea[name="group_summary"]').value;
                const documents = document.querySelectorAll('.document-item');

                previewHtml = `
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-copy me-2"></i>Nhóm văn bản</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="text-info">Tiêu đề nhóm:</h6>
                            <p><strong>${groupTitle || 'Chưa có tiêu đề'}</strong></p>
                            
                            <h6 class="text-info">Mô tả:</h6>
                            <p>${groupDescription || 'Chưa có mô tả'}</p>
                            
                            <h6 class="text-info">Bản tóm tắt AI chung:</h6>
                            <div class="border rounded p-3 bg-warning bg-opacity-10">
                                ${groupSummary || 'Chưa có bản tóm tắt'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                `;

                documents.forEach((doc, index) => {
                    const title = doc.querySelector('input[name="doc_title[]"]').value;
                    const content = doc.querySelector('textarea[name="doc_content[]"]').value;
                    
                    previewHtml += `
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Văn bản #${index + 1}</h6>
                                </div>
                                <div class="card-body">
                                    <h6 class="text-primary">Tiêu đề:</h6>
                                    <p><strong>${title || 'Chưa có tiêu đề'}</strong></p>
                                    
                                    <h6 class="text-primary">Nội dung:</h6>
                                    <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y: auto; font-size: 0.9rem;">
                                        ${content ? content.substring(0, 200) + (content.length > 200 ? '...' : '') : 'Chưa có nội dung'}
                                    </div>
                                    
                                    <div class="row mt-2">
                                        <div class="col-4 text-center">
                                            <small class="text-info">${content.length} ký tự</small>
                                        </div>
                                        <div class="col-4 text-center">
                                            <small class="text-success">${content.trim() ? content.trim().split(/\\s+/).length : 0} từ</small>
                                        </div>
                                        <div class="col-4 text-center">
                                            <small class="text-warning">${content.split(/[.!?]+/).filter(s => s.trim()).length} câu</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });

                previewHtml += '</div>';
            }

            previewContainer.innerHTML = previewHtml;
        }

        // Form submission for manual upload
        function submitManualUpload() {
            const form = document.getElementById('manual-upload-form');
            const formData = new FormData(form);
            
            // Add action
            formData.append('action', 'upload_documents');
            
            // Show loading
            const submitBtn = event.target;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang upload...';
            submitBtn.disabled = true;
            
            // Submit form using fetch
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Reload page to show results
                window.location.reload();
            })
            .catch(error => {
                alert('Có lỗi xảy ra khi upload!');
                console.error('Error:', error);
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateRemoveButtons();
        });
    </script>
</body>
</html>