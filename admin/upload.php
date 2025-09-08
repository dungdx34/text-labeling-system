<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Upload dữ liệu';

// Use absolute paths to avoid path issues
$auth_path = __DIR__ . '/../includes/auth.php';
$functions_path = __DIR__ . '/../includes/functions.php';
$header_path = __DIR__ . '/../includes/header.php';

if (!file_exists($auth_path)) {
    die('Error: Cannot find auth.php. Please run quick-fix.php first.');
}

if (!file_exists($functions_path)) {
    die('Error: Cannot find functions.php. Please run quick-fix.php first.');
}

require_once $auth_path;
require_once $functions_path;

try {
    $auth = new Auth();
    $auth->requireRole('admin');

    $functions = new Functions();
    $success_message = '';
    $error_message = '';

    // Handle file upload
    if ($_POST && isset($_FILES['document_file'])) {
        $title = trim($_POST['title']);
        $ai_summary = trim($_POST['ai_summary']);
        $uploaded_by = $_SESSION['user_id'];
        
        $file = $_FILES['document_file'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $content = file_get_contents($file['tmp_name']);
            
            if ($functions->uploadDocument($title, $content, $ai_summary, $uploaded_by)) {
                $success_message = 'Upload tài liệu thành công!';
            } else {
                $error_message = 'Có lỗi khi lưu tài liệu vào cơ sở dữ liệu.';
            }
        } else {
            $error_message = 'Có lỗi khi upload file.';
        }
    } elseif ($_POST && !isset($_FILES['document_file'])) {
        // Manual text input
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $ai_summary = trim($_POST['ai_summary']);
        $uploaded_by = $_SESSION['user_id'];
        
        if ($functions->uploadDocument($title, $content, $ai_summary, $uploaded_by)) {
            $success_message = 'Thêm tài liệu thành công!';
        } else {
            $error_message = 'Có lỗi khi lưu tài liệu vào cơ sở dữ liệu.';
        }
    }

    // Get recent documents
    $recent_documents = [];
    try {
        $recent_documents = array_slice($functions->getDocuments(), 0, 10);
    } catch (Exception $e) {
        $recent_documents = [];
    }

} catch (Exception $e) {
    die('Error: ' . $e->getMessage() . '. Please run setup.php first.');
}

// Include header
if (file_exists($header_path)) {
    require_once $header_path;
} else {
    // Fallback header
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $page_title; ?></title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
            }
            .sidebar {
                background: linear-gradient(180deg, #0d6efd 0%, #0a58ca 100%);
                min-height: calc(100vh - 56px);
            }
            .sidebar .nav-link {
                color: rgba(255, 255, 255, 0.8);
                transition: all 0.3s ease;
                border-radius: 8px;
                margin: 4px 0;
                padding: 12px 16px;
            }
            .sidebar .nav-link:hover, .sidebar .nav-link.active {
                color: white;
                background: rgba(255, 255, 255, 0.15);
                transform: translateX(8px);
            }
            .main-content {
                background: white;
                border-radius: 20px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                margin: 20px;
                padding: 40px;
            }
            .text-gradient {
                background: linear-gradient(135deg, #0d6efd, #764ba2);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            .navbar {
                background: rgba(13, 110, 253, 0.95) !important;
                backdrop-filter: blur(10px);
            }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="../index.php">
                    <i class="fas fa-tags me-2"></i>Text Labeling System
                </a>
                <div class="navbar-nav ms-auto">
                    <span class="nav-link">
                        <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['full_name'] ?? 'Admin'; ?>
                    </span>
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
                    </a>
                </div>
            </div>
        </nav>
    <?php
}
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 p-0">
            <div class="sidebar p-3">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-2"></i>Quản lý người dùng
                    </a>
                    <a class="nav-link active" href="upload.php">
                        <i class="fas fa-upload me-2"></i>Upload dữ liệu
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i>Báo cáo
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
                        <h2 class="text-gradient">
                            <i class="fas fa-upload me-2"></i>Upload dữ liệu
                        </h2>
                        <p class="text-muted mb-0">Thêm tài liệu mới vào hệ thống</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Quay lại Dashboard
                    </a>
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
                
                <!-- Upload Methods -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-file-upload me-2"></i>Upload từ file</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="title_file" class="form-label">Tiêu đề tài liệu <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title_file" name="title" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="document_file" class="form-label">Chọn file văn bản <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control" id="document_file" name="document_file" 
                                               accept=".txt,.doc,.docx" onchange="handleFileUpload(this)" required>
                                        <div class="form-text">Hỗ trợ file: .txt, .doc, .docx (tối đa 10MB)</div>
                                        <div id="file-preview" class="mt-2"></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="ai_summary_file" class="form-label">Bản tóm tắt AI</label>
                                        <textarea class="form-control" id="ai_summary_file" name="ai_summary" 
                                                  rows="4" placeholder="Nhập bản tóm tắt được tạo bởi AI (tùy chọn)..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-upload me-2"></i>Upload tài liệu
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-keyboard me-2"></i>Nhập trực tiếp</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="title_manual" class="form-label">Tiêu đề tài liệu <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title_manual" name="title" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="content_manual" class="form-label">Nội dung văn bản <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="content_manual" name="content" 
                                                  rows="8" placeholder="Nhập nội dung văn bản..." required></textarea>
                                        <div class="form-text">
                                            <span id="char-count">0</span> ký tự | 
                                            <span id="word-count">0</span> từ
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="ai_summary_manual" class="form-label">Bản tóm tắt AI</label>
                                        <textarea class="form-control" id="ai_summary_manual" name="ai_summary" 
                                                  rows="4" placeholder="Nhập bản tóm tắt được tạo bởi AI (tùy chọn)..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-plus me-2"></i>Thêm tài liệu
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Uploads -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Tài liệu đã upload gần đây</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_documents)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox text-muted fa-3x mb-3"></i>
                                        <p class="text-muted">Chưa có tài liệu nào được upload</p>
                                        <p class="text-muted">Hãy upload tài liệu đầu tiên bằng form ở trên!</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tiêu đề</th>
                                                    <th>Người upload</th>
                                                    <th>Ngày upload</th>
                                                    <th>Trạng thái</th>
                                                    <th>Thao tác</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_documents as $doc): ?>
                                                <tr>
                                                    <td class="fw-bold"><?php echo $doc['id']; ?></td>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($doc['title']); ?></div>
                                                        <small class="text-muted">
                                                            <?php echo strlen($doc['content']); ?> ký tự
                                                        </small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($doc['uploaded_by_name'] ?? 'Unknown'); ?></td>
                                                    <td>
                                                        <small>
                                                            <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                                                            <br>
                                                            <?php echo date('H:i', strtotime($doc['created_at'])); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $status_colors = [
                                                            'pending' => 'secondary',
                                                            'in_progress' => 'warning',
                                                            'completed' => 'success',
                                                            'reviewed' => 'primary'
                                                        ];
                                                        $status_names = [
                                                            'pending' => 'Chờ gán nhãn',
                                                            'in_progress' => 'Đang gán nhãn',
                                                            'completed' => 'Hoàn thành',
                                                            'reviewed' => 'Đã review'
                                                        ];
                                                        ?>
                                                        <span class="badge bg-<?php echo $status_colors[$doc['status']]; ?>">
                                                            <?php echo $status_names[$doc['status']]; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-outline-info" 
                                                                    onclick="viewDocument(<?php echo $doc['id']; ?>)"
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#viewDocumentModal">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger"
                                                                    onclick="deleteDocument(<?php echo $doc['id']; ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h4 class="text-primary"><?php echo count($recent_documents); ?></h4>
                                <small class="text-muted">Tài liệu trong hệ thống</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h4 class="text-warning"><?php echo count($functions->getDocuments('pending')); ?></h4>
                                <small class="text-muted">Chờ gán nhãn</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h4 class="text-success"><?php echo count($functions->getDocuments('completed')); ?></h4>
                                <small class="text-muted">Đã hoàn thành</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h4 class="text-info"><?php echo count($functions->getDocuments('reviewed')); ?></h4>
                                <small class="text-muted">Đã review</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Document Modal -->
<div class="modal fade" id="viewDocumentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-text me-2"></i>Chi tiết tài liệu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="documentContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Đang tải...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
// File upload handler
function handleFileUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        // Validate file size
        if (file.size > maxSize) {
            alert('File quá lớn! Vui lòng chọn file nhỏ hơn 10MB.');
            input.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['text/plain', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        const allowedExtensions = ['.txt', '.docx'];
        
        const fileExtension = file.name.toLowerCase().substring(file.name.lastIndexOf('.'));
        if (!allowedExtensions.includes(fileExtension)) {
            alert('File không được hỗ trợ! Chỉ chấp nhận file .txt và .docx');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const preview = document.getElementById('file-preview');
            if (preview) {
                preview.innerHTML = `
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-file-text me-3 fs-4"></i>
                        <div>
                            <div class="fw-bold">${file.name}</div>
                            <small class="text-muted">${formatFileSize(file.size)} • ${fileExtension}</small>
                        </div>
                    </div>
                `;
            }
        };
        
        reader.onerror = function() {
            alert('Có lỗi khi đọc file!');
        };
        
        reader.readAsText(file);
    }
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Character and word count for manual input
document.getElementById('content_manual').addEventListener('input', function() {
    const text = this.value;
    const charCount = text.length;
    const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
    
    document.getElementById('char-count').textContent = charCount;
    document.getElementById('word-count').textContent = wordCount;
});

// View document function
function viewDocument(documentId) {
    const content = document.getElementById('documentContent');
    
    content.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Đang tải...</span>
            </div>
        </div>
    `;
    
    fetch(`get_document.php?id=${documentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = `
                    <div class="mb-3">
                        <h6 class="text-primary">Tiêu đề:</h6>
                        <p class="fw-semibold">${data.document.title}</p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-primary">Nội dung:</h6>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto; background-color: #f8f9fa;">
                            ${data.document.content.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                    ${data.document.ai_summary ? `
                    <div class="mb-3">
                        <h6 class="text-primary">Bản tóm tắt AI:</h6>
                        <div class="alert alert-info">
                            ${data.document.ai_summary.replace(/\n/g, '<br>')}
                        </div>
                    </div>` : ''}
                    <div class="mb-3">
                        <h6 class="text-primary">Thông tin:</h6>
                        <small class="text-muted">
                            ID: ${data.document.id} | 
                            Upload: ${new Date(data.document.created_at).toLocaleString('vi-VN')} |
                            Ký tự: ${data.document.content.length}
                        </small>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Không thể tải nội dung tài liệu
                    </div>
                `;
            }
        })
        .catch(error => {
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Có lỗi xảy ra khi tải tài liệu
                </div>
            `;
        });
}

// Delete document function
function deleteDocument(documentId) {
    if (confirm('Bạn có chắc chắn muốn xóa tài liệu này?')) {
        // Implementation for document deletion
        alert('Tính năng xóa tài liệu sẽ được triển khai trong phiên bản tiếp theo.');
    }
}

console.log('Upload page loaded successfully');
</script>

</body>
</html>