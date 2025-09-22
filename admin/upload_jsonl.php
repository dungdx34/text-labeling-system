<?php
// admin/upload_jsonl.php - Complete Enhanced JSONL Upload Interface
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'jsonl_handler.php';

Auth::requireLogin('admin');

$database = new Database();
$pdo = $database->getConnection();

$message = '';
$error = '';
$upload_stats = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_jsonl'])) {
        if (isset($_FILES['jsonl_file']) && $_FILES['jsonl_file']['error'] === 0) {
            $file = $_FILES['jsonl_file'];
            
            // Validate file type
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            if ($file_extension !== 'jsonl') {
                $error = 'Chỉ chấp nhận file .jsonl';
            } else {
                $upload_result = processJsonlUploadEnhanced($file, $pdo);
                if ($upload_result['success']) {
                    $message = $upload_result['message'];
                    $upload_stats = $upload_result['stats'];
                    
                    // Log the upload activity
                    $current_user = Auth::getCurrentUser();
                    logUploadActivity($pdo, $current_user['id'], $file, $upload_result);
                    
                    // Display warnings if any
                    if (!empty($upload_result['warnings'])) {
                        $message .= '<br><br><strong>⚠️ Cảnh báo:</strong><ul class="mb-0">';
                        foreach (array_slice($upload_result['warnings'], 0, 10) as $warning) {
                            $message .= '<li>' . htmlspecialchars($warning) . '</li>';
                        }
                        if (count($upload_result['warnings']) > 10) {
                            $message .= '<li><em>... và ' . (count($upload_result['warnings']) - 10) . ' cảnh báo khác</em></li>';
                        }
                        $message .= '</ul>';
                    }
                } else {
                    $error = $upload_result['message'];
                    if (!empty($upload_result['errors'])) {
                        $error .= '<br><br><strong>❌ Chi tiết lỗi:</strong><ul class="mb-0">';
                        foreach (array_slice($upload_result['errors'], 0, 10) as $err) {
                            $error .= '<li>' . htmlspecialchars($err) . '</li>';
                        }
                        if (count($upload_result['errors']) > 10) {
                            $error .= '<li><em>... và ' . (count($upload_result['errors']) - 10) . ' lỗi khác</em></li>';
                        }
                        $error .= '</ul>';
                    }
                }
            }
        } else {
            $error = 'Vui lòng chọn file để upload';
        }
    }
}

$current_user = Auth::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload JSONL - Text Labeling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .upload-zone {
            border: 3px dashed #dee2e6;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        .upload-zone:hover {
            border-color: #0d6efd;
            background: #e3f2fd;
        }
        .upload-zone.dragover {
            border-color: #28a745;
            background: #d4edda;
        }
        .format-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .code-block {
            background: #282c34;
            color: #abb2bf;
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin: 10px 0;
        }
        .badge-large {
            padding: 8px 12px;
            font-size: 0.9rem;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tags me-2"></i>Text Labeling System
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="upload.php">
                    <i class="fas fa-upload me-1"></i>Upload thường
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="text-primary">
                            <i class="fas fa-file-code me-2"></i>Upload Dữ Liệu JSONL
                        </h2>
                        <p class="text-muted mb-0">
                            Upload dữ liệu với định dạng JSONL. Trường "query" là tùy chọn - sẽ tự động tạo nếu thiếu.
                        </p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Quay lại
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <h5 class="alert-heading">
                    <i class="fas fa-check-circle me-2"></i>Upload thành công!
                </h5>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <h5 class="alert-heading">
                    <i class="fas fa-exclamation-circle me-2"></i>Lỗi upload!
                </h5>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Upload Form -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Tải lên file JSONL
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="upload-zone" id="uploadZone">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <h5>Kéo thả file JSONL vào đây</h5>
                                <p class="text-muted mb-3">hoặc</p>
                                <input type="file" class="form-control" id="jsonl_file" name="jsonl_file" 
                                       accept=".jsonl" required style="display: none;">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('jsonl_file').click()">
                                    <i class="fas fa-folder-open me-2"></i>Chọn file
                                </button>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Chỉ chấp nhận file .jsonl. Trường "query" là tùy chọn.
                                    </small>
                                </div>
                            </div>
                            
                            <div id="fileInfo" style="display: none;" class="mt-3">
                                <div class="alert alert-info">
                                    <i class="fas fa-file me-2"></i>
                                    <span id="fileName"></span>
                                    <span id="fileSize" class="text-muted ms-2"></span>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" name="upload_jsonl" class="btn btn-success btn-lg">
                                    <i class="fas fa-upload me-2"></i>Bắt đầu Upload
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Format Guide -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-book me-2"></i>Hướng dẫn Format JSONL
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="format-card">
                            <h6 class="text-info">
                                <i class="fas fa-exclamation-triangle me-1"></i>Lưu ý quan trọng:
                            </h6>
                            <ul class="small mb-0">
                                <li><code>summary</code> và <code>document</code> là <strong>bắt buộc</strong></li>
                                <li><code>query</code> là <strong>tùy chọn</strong> - nếu không có sẽ tự động tạo</li>
                                <li>Nếu document có &gt; 1 phần tử = đa văn bản</li>
                                <li>Nếu document có 1 phần tử = đơn văn bản</li>
                            </ul>
                        </div>
                        
                        <h6 class="text-success">✅ Có query:</h6>
                        <div class="code-block">
{
  "query": "tiêu đề văn bản",
  "summary": "tóm tắt AI",
  "document": ["nội dung văn bản"]
}
                        </div>
                        
                        <h6 class="text-warning">⚠️ Không có query:</h6>
                        <div class="code-block">
{
  "summary": "tóm tắt AI", 
  "document": ["nội dung văn bản"]
}
                        </div>
                        
                        <h6 class="text-primary">📚 Đa văn bản:</h6>
                        <div class="code-block">
{
  "query": "tiêu đề nhóm",
  "summary": "tóm tắt chung",
  "document": [
    "văn bản 1",
    "văn bản 2"
  ]
}
                        </div>
                        
                        <div class="mt-3 p-3 bg-light border-start border-4 border-info">
                            <small>
                                <strong>💡 Tự động tạo tiêu đề:</strong><br>
                                Khi không có query, hệ thống sẽ tự động tạo tiêu đề từ nội dung văn bản hoặc timestamp.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <?php if ($upload_stats): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="stats-card">
                    <h5>
                        <i class="fas fa-chart-pie me-2"></i>Kết Quả Upload
                    </h5>
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="h3"><?php echo $upload_stats['success_count']; ?></div>
                            <small>Thành công</small>
                        </div>
                        <div class="col-md-3">
                            <div class="h3"><?php echo $upload_stats['error_count']; ?></div>
                            <small>Lỗi</small>
                        </div>
                        <div class="col-md-3">
                            <div class="h3"><?php echo $upload_stats['auto_generated_titles']; ?></div>
                            <small>Tiêu đề tự tạo</small>
                        </div>
                        <div class="col-md-3">
                            <div class="h3"><?php echo $upload_stats['total_processed']; ?></div>
                            <small>Tổng xử lý</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Current Statistics -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-database me-2"></i>Thống Kê Dữ Liệu Hiện Tại
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <?php
                            // Get current statistics
                            try {
                                $stats = [
                                    'total_documents' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
                                    'single_documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE type = 'single'")->fetchColumn(),
                                    'multi_documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE type = 'multi'")->fetchColumn(),
                                    'document_groups' => $pdo->query("SELECT COUNT(*) FROM document_groups")->fetchColumn(),
                                    'ai_summaries' => $pdo->query("SELECT COUNT(*) FROM ai_summaries")->fetchColumn(),
                                    'recent_uploads' => $pdo->query("SELECT COUNT(*) FROM upload_logs WHERE upload_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn()
                                ];
                            } catch (Exception $e) {
                                $stats = array_fill_keys(['total_documents', 'single_documents', 'multi_documents', 'document_groups', 'ai_summaries', 'recent_uploads'], 0);
                            }
                            ?>
                            
                            <div class="col-md-2">
                                <div class="h4 text-primary"><?php echo number_format($stats['total_documents']); ?></div>
                                <small class="text-muted">Tổng Văn Bản</small>
                            </div>
                            <div class="col-md-2">
                                <div class="h4 text-success"><?php echo number_format($stats['single_documents']); ?></div>
                                <small class="text-muted">Đơn Văn Bản</small>
                            </div>
                            <div class="col-md-2">
                                <div class="h4 text-info"><?php echo number_format($stats['multi_documents']); ?></div>
                                <small class="text-muted">Đa Văn Bản</small>
                            </div>
                            <div class="col-md-2">
                                <div class="h4 text-warning"><?php echo number_format($stats['document_groups']); ?></div>
                                <small class="text-muted">Nhóm Văn Bản</small>
                            </div>
                            <div class="col-md-2">
                                <div class="h4 text-danger"><?php echo number_format($stats['ai_summaries']); ?></div>
                                <small class="text-muted">AI Summaries</small>
                            </div>
                            <div class="col-md-2">
                                <div class="h4 text-secondary"><?php echo number_format($stats['recent_uploads']); ?></div>
                                <small class="text-muted">Upload 7 ngày</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Drag and drop functionality
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('jsonl_file');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            uploadZone.classList.add('dragover');
        }

        function unhighlight(e) {
            uploadZone.classList.remove('dragover');
        }

        uploadZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                const file = files[0];
                if (file.name.endsWith('.jsonl')) {
                    fileInput.files = files;
                    showFileInfo(file);
                } else {
                    alert('Chỉ chấp nhận file .jsonl');
                }
            }
        }

        fileInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                showFileInfo(e.target.files[0]);
            }
        });

        function showFileInfo(file) {
            fileName.textContent = file.name;
            fileSize.textContent = '(' + formatFileSize(file.size) + ')';
            fileInfo.style.display = 'block';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Form submission with loading state
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
            submitBtn.disabled = true;
            
            // Note: In real implementation, you might want to handle this via AJAX
            // For now, let the form submit normally
        });
    </script>
</body>
</html>