<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Kiểm tra quyền admin
requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$success_message = '';
$error_message = '';

// Xử lý upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] == 'upload_single') {
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $ai_summary = trim($_POST['ai_summary']);
            
            if (empty($title) || empty($content) || empty($ai_summary)) {
                $error_message = 'Vui lòng điền đầy đủ thông tin!';
            } else {
                $query = "INSERT INTO documents (title, content, ai_summary, type, uploaded_by) VALUES (?, ?, ?, 'single', ?)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $title);
                $stmt->bindParam(2, $content);
                $stmt->bindParam(3, $ai_summary);
                $stmt->bindParam(4, $current_user['id']);
                $stmt->execute();
                
                $success_message = 'Upload văn bản đơn thành công!';
            }
        } elseif ($_POST['action'] == 'upload_multi') {
            $group_title = trim($_POST['group_title']);
            $group_description = trim($_POST['group_description']);
            $group_summary = trim($_POST['group_summary']);
            
            if (empty($group_title) || empty($group_summary)) {
                $error_message = 'Vui lòng điền tiêu đề nhóm và bản tóm tắt!';
            } else {
                // Bắt đầu transaction
                $db->beginTransaction();
                
                // Tạo nhóm văn bản
                $query = "INSERT INTO document_groups (title, description, ai_summary, uploaded_by) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $group_title);
                $stmt->bindParam(2, $group_description);
                $stmt->bindParam(3, $group_summary);
                $stmt->bindParam(4, $current_user['id']);
                $stmt->execute();
                $group_id = $db->lastInsertId();
                
                // Thêm các văn bản vào nhóm
                $doc_titles = $_POST['doc_title'];
                $doc_contents = $_POST['doc_content'];
                
                for ($i = 0; $i < count($doc_titles); $i++) {
                    if (!empty($doc_titles[$i]) && !empty($doc_contents[$i])) {
                        // Thêm document
                        $query = "INSERT INTO documents (title, content, ai_summary, type, uploaded_by) VALUES (?, ?, ?, 'single', ?)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(1, $doc_titles[$i]);
                        $stmt->bindParam(2, $doc_contents[$i]);
                        $stmt->bindParam(3, $group_summary); // Dùng chung summary
                        $stmt->bindParam(4, $current_user['id']);
                        $stmt->execute();
                        $doc_id = $db->lastInsertId();
                        
                        // Liên kết với nhóm
                        $query = "INSERT INTO group_documents (group_id, document_id, order_index) VALUES (?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(1, $group_id);
                        $stmt->bindParam(2, $doc_id);
                        $stmt->bindParam(3, $i);
                        $stmt->execute();
                    }
                }
                
                $db->commit();
                $success_message = 'Upload nhóm văn bản thành công!';
            }
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error_message = 'Lỗi upload: ' . $e->getMessage();
    }
}

// Lấy thống kê upload
try {
    $query = "SELECT COUNT(*) as total FROM documents";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_docs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $query = "SELECT COUNT(*) as total FROM document_groups";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_groups = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    $total_docs = 0;
    $total_groups = 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload văn bản - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            padding: 20px 0;
            z-index: 1000;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        .upload-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s ease;
            cursor: pointer;
            height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .upload-card:hover {
            transform: translateY(-5px);
        }
        .upload-card.active {
            border: 3px solid #007bff;
            background: #f8f9ff;
        }
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
        }
        .document-item {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center text-white mb-4">
            <i class="fas fa-tags fa-2x mb-2"></i>
            <h5>Admin Panel</h5>
            <small>Xin chào, <?php echo htmlspecialchars($current_user['full_name']); ?></small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-2"></i>Quản lý người dùng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="upload.php">
                    <i class="fas fa-upload me-2"></i>Upload văn bản
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="documents.php">
                    <i class="fas fa-file-text me-2"></i>Quản lý văn bản
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="assignments.php">
                    <i class="fas fa-tasks me-2"></i>Phân công công việc
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Báo cáo
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark">Upload văn bản</h2>
            <div class="text-muted">
                <span class="badge bg-primary me-2">Văn bản: <?php echo $total_docs; ?></span>
                <span class="badge bg-success">Nhóm: <?php echo $total_groups; ?></span>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Upload Type Selection -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="upload-card" onclick="selectUploadType('single')">
                    <i class="fas fa-file-text fa-3x text-primary mb-3"></i>
                    <h5>Văn bản đơn</h5>
                    <p class="text-muted mb-0">Upload một văn bản và bản tóm tắt AI tương ứng</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="upload-card" onclick="selectUploadType('multi')">
                    <i class="fas fa-copy fa-3x text-success mb-3"></i>
                    <h5>Đa văn bản</h5>
                    <p class="text-muted mb-0">Upload nhiều văn bản cùng với bản tóm tắt AI chung</p>
                </div>
            </div>
        </div>

        <!-- Single Document Upload Form -->
        <div id="single-upload" class="content-card" style="display: none;">
            <h4 class="mb-4">
                <i class="fas fa-file-text me-2 text-primary"></i>
                Upload văn bản đơn
            </h4>
            
            <form method="POST">
                <input type="hidden" name="action" value="upload_single">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Tiêu đề văn bản</label>
                            <input type="text" class="form-control" name="title" required 
                                   placeholder="Nhập tiêu đề văn bản...">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nội dung văn bản</label>
                            <textarea class="form-control" name="content" rows="15" required 
                                      placeholder="Nhập nội dung văn bản..."></textarea>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Bản tóm tắt AI</label>
                            <textarea class="form-control" name="ai_summary" rows="18" required 
                                      placeholder="Nhập bản tóm tắt AI cho văn bản..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="button" class="btn btn-secondary me-2" onclick="resetUpload()">
                        <i class="fas fa-times me-2"></i>Hủy
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload văn bản
                    </button>
                </div>
            </form>
        </div>

        <!-- Multi Document Upload Form -->
        <div id="multi-upload" class="content-card" style="display: none;">
            <h4 class="mb-4">
                <i class="fas fa-copy me-2 text-success"></i>
                Upload nhóm văn bản
            </h4>
            
            <form method="POST">
                <input type="hidden" name="action" value="upload_multi">
                
                <div class="row">
                    <div class="col-md-8">
                        <!-- Group Info -->
                        <div class="mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Tiêu đề nhóm</label>
                                    <input type="text" class="form-control" name="group_title" required 
                                           placeholder="Tiêu đề cho nhóm văn bản...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mô tả nhóm</label>
                                    <textarea class="form-control" name="group_description" rows="2" 
                                              placeholder="Mô tả ngắn về nhóm..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Container -->
                        <div id="documents-container">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Danh sách văn bản</h6>
                                <button type="button" class="btn btn-sm btn-success" onclick="addDocument()">
                                    <i class="fas fa-plus me-1"></i>Thêm văn bản
                                </button>
                            </div>
                            
                            <!-- Document 1 -->
                            <div class="document-item" data-doc-index="1">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Văn bản #1</h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="removeDocument(1)" style="display: none;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tiêu đề</label>
                                            <input type="text" class="form-control" name="doc_title[]" 
                                                   placeholder="Tiêu đề văn bản...">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nội dung</label>
                                            <textarea class="form-control" name="doc_content[]" rows="4" 
                                                      placeholder="Nội dung văn bản..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Bản tóm tắt AI chung</label>
                            <textarea class="form-control" name="group_summary" rows="20" required 
                                      placeholder="Nhập bản tóm tắt AI cho toàn bộ nhóm văn bản..."></textarea>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Bản tóm tắt này sẽ được sử dụng cho toàn bộ nhóm văn bản
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="button" class="btn btn-secondary me-2" onclick="resetUpload()">
                        <i class="fas fa-times me-2"></i>Hủy
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload me-2"></i>Upload nhóm văn bản
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Stats -->
        <div class="content-card mt-4">
            <h5 class="mb-3">
                <i class="fas fa-chart-pie me-2 text-info"></i>
                Thống kê upload
            </h5>
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="h3 text-primary"><?php echo $total_docs; ?></div>
                        <div class="text-muted">Tổng văn bản</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="h3 text-success"><?php echo $total_groups; ?></div>
                        <div class="text-muted">Nhóm văn bản</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="h3 text-warning">
                            <?php 
                            try {
                                $query = "SELECT COUNT(*) as total FROM documents WHERE uploaded_by = ?";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(1, $current_user['id']);
                                $stmt->execute();
                                echo $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </div>
                        <div class="text-muted">Của bạn</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="h3 text-info">
                            <?php 
                            try {
                                $query = "SELECT COUNT(*) as total FROM documents WHERE DATE(created_at) = CURDATE()";
                                $stmt = $db->prepare($query);
                                $stmt->execute();
                                echo $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </div>
                        <div class="text-muted">Hôm nay</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let documentCounter = 1;

        function selectUploadType(type) {
            // Reset all cards
            document.querySelectorAll('.upload-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Hide all forms
            document.getElementById('single-upload').style.display = 'none';
            document.getElementById('multi-upload').style.display = 'none';
            
            // Show selected form
            if (type === 'single') {
                document.querySelector('.upload-card:first-child').classList.add('active');
                document.getElementById('single-upload').style.display = 'block';
            } else if (type === 'multi') {
                document.querySelector('.upload-card:last-child').classList.add('active');
                document.getElementById('multi-upload').style.display = 'block';
            }
        }

        function addDocument() {
            documentCounter++;
            const container = document.getElementById('documents-container');
            
            const documentHtml = `
                <div class="document-item" data-doc-index="${documentCounter}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Văn bản #${documentCounter}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="removeDocument(${documentCounter})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tiêu đề</label>
                                <input type="text" class="form-control" name="doc_title[]" 
                                       placeholder="Tiêu đề văn bản...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nội dung</label>
                                <textarea class="form-control" name="doc_content[]" rows="4" 
                                          placeholder="Nội dung văn bản..."></textarea>
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

        function resetUpload() {
            // Reset forms
            document.querySelectorAll('form').forEach(form => form.reset());
            
            // Hide all forms
            document.getElementById('single-upload').style.display = 'none';
            document.getElementById('multi-upload').style.display = 'none';
            
            // Reset upload cards
            document.querySelectorAll('.upload-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Reset document counter
            documentCounter = 1;
            
            // Reset documents container
            const container = document.getElementById('documents-container');
            const firstChild = container.querySelector('.document-item');
            if (firstChild) {
                container.innerHTML = container.innerHTML.split('<div class="document-item"')[0] + 
                                    '<div class="document-item" data-doc-index="1">' + 
                                    container.innerHTML.split('<div class="document-item"')[1].split('</div>')[0] + '</div>';
            }
            
            updateRemoveButtons();
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateRemoveButtons();
        });
    </script>
</body>
</html>