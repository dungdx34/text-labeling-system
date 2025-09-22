<?php
// admin/upload.php - Fixed Traditional Upload
require_once '../config/database.php';
require_once '../includes/auth.php';

Auth::requireLogin('admin');

$database = new Database();
$pdo = $database->getConnection();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $summary = trim($_POST['summary']);
    
    if (empty($title) || empty($content) || empty($summary)) {
        $error = 'Vui lòng điền đầy đủ thông tin';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert document
            $stmt = $pdo->prepare("INSERT INTO documents (title, content, type, created_by, created_at) VALUES (?, ?, 'single', ?, NOW())");
            $stmt->execute([$title, $content, 1]); // created_by = 1 (admin)
            $document_id = $pdo->lastInsertId();
            
            // Insert AI summary
            $stmt = $pdo->prepare("INSERT INTO ai_summaries (document_id, summary, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$document_id, $summary]);
            
            $pdo->commit();
            $message = 'Upload văn bản thành công!';
            
            // Clear form
            $title = $content = $summary = '';
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = 'Lỗi upload: ' . $e->getMessage();
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
    <title>Upload Văn bản - Text Labeling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .preview-box {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark gradient-bg">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tags me-2"></i>Text Labeling System
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-1"></i>Users
                </a>
                <a class="nav-link" href="upload_jsonl.php">
                    <i class="fas fa-file-code me-1"></i>Upload JSONL
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
                            <i class="fas fa-upload me-2"></i>Upload Văn bản
                        </h2>
                        <p class="text-muted">Tạo văn bản đơn lẻ với tóm tắt AI</p>
                    </div>
                    <a href="upload_jsonl.php" class="btn btn-outline-primary">
                        <i class="fas fa-file-code me-2"></i>Upload JSONL
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Upload Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-edit me-2"></i>Thông tin văn bản
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-section">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-file-text me-2"></i>Văn bản gốc
                                </h6>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Tiêu đề văn bản *</label>
                                    <input type="text" class="form-control" name="title" required 
                                           placeholder="Nhập tiêu đề văn bản..."
                                           value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Nội dung văn bản *</label>
                                    <textarea class="form-control" name="content" rows="10" required 
                                              placeholder="Nhập nội dung đầy đủ của văn bản..."><?php echo isset($content) ? htmlspecialchars($content) : ''; ?></textarea>
                                    <div class="form-text">
                                        <span id="content-stats">0 ký tự, 0 từ</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h6 class="text-success mb-3">
                                    <i class="fas fa-robot me-2"></i>Tóm tắt AI
                                </h6>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Bản tóm tắt *</label>
                                    <textarea class="form-control" name="summary" rows="6" required 
                                              placeholder="Nhập bản tóm tắt AI cho văn bản này..."><?php echo isset($summary) ? htmlspecialchars($summary) : ''; ?></textarea>
                                    <div class="form-text">
                                        <span id="summary-stats">0 ký tự, 0 từ</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Xóa form
                                </button>
                                <button type="submit" name="upload_document" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Lưu văn bản
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Instructions -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Hướng dẫn
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="text-primary">📝 Cách thức hoạt động:</h6>
                            <ul class="small">
                                <li>Nhập tiêu đề mô tả nội dung văn bản</li>
                                <li>Copy/paste nội dung văn bản đầy đủ</li>
                                <li>Thêm bản tóm tắt AI tương ứng</li>
                                <li>Hệ thống sẽ tự động tính toán thống kê</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-success">✅ Tips:</h6>
                            <ul class="small">
                                <li>Tiêu đề nên ngắn gọn và mô tả chính xác</li>
                                <li>Nội dung nên được format đẹp</li>
                                <li>Tóm tắt AI nên chính xác và đầy đủ</li>
                                <li>Sử dụng Upload JSONL cho nhiều văn bản</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <small>
                                <i class="fas fa-lightbulb me-1"></i>
                                <strong>Gợi ý:</strong> Để upload hàng loạt văn bản, 
                                sử dụng tính năng <a href="upload_jsonl.php">Upload JSONL</a>
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card mt-3">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Thống kê nhanh
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $total_docs = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
                            $single_docs = $pdo->query("SELECT COUNT(*) FROM documents WHERE type = 'single'")->fetchColumn();
                            $summaries = $pdo->query("SELECT COUNT(*) FROM ai_summaries")->fetchColumn();
                        } catch (Exception $e) {
                            $total_docs = $single_docs = $summaries = 0;
                        }
                        ?>
                        
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h5 text-primary"><?php echo $total_docs; ?></div>
                                <small class="text-muted">Tổng</small>
                            </div>
                            <div class="col-4">
                                <div class="h5 text-success"><?php echo $single_docs; ?></div>
                                <small class="text-muted">Đơn</small>
                            </div>
                            <div class="col-4">
                                <div class="h5 text-info"><?php echo $summaries; ?></div>
                                <small class="text-muted">Tóm tắt</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Preview -->
                <div class="card mt-3" id="preview-card" style="display: none;">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-eye me-2"></i>Xem trước
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Tiêu đề:</strong>
                            <div id="preview-title" class="preview-box small"></div>
                        </div>
                        <div class="mb-2">
                            <strong>Nội dung:</strong>
                            <div id="preview-content" class="preview-box small"></div>
                        </div>
                        <div class="mb-2">
                            <strong>Tóm tắt:</strong>
                            <div id="preview-summary" class="preview-box small"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Text statistics
        function updateStats(textareaId, statsId) {
            const textarea = document.querySelector(`[name="${textareaId}"]`);
            const stats = document.getElementById(statsId);
            
            if (textarea && stats) {
                const text = textarea.value;
                const charCount = text.length;
                const wordCount = text.trim() === '' ? 0 : text.trim().split(/\s+/).length;
                
                stats.textContent = `${charCount.toLocaleString()} ký tự, ${wordCount.toLocaleString()} từ`;
            }
        }

        // Preview functionality
        function updatePreview() {
            const title = document.querySelector('[name="title"]').value;
            const content = document.querySelector('[name="content"]').value;
            const summary = document.querySelector('[name="summary"]').value;
            
            if (title || content || summary) {
                document.getElementById('preview-card').style.display = 'block';
                
                document.getElementById('preview-title').textContent = title || '(Chưa có tiêu đề)';
                document.getElementById('preview-content').textContent = content ? content.substring(0, 200) + '...' : '(Chưa có nội dung)';
                document.getElementById('preview-summary').textContent = summary ? summary.substring(0, 150) + '...' : '(Chưa có tóm tắt)';
            } else {
                document.getElementById('preview-card').style.display = 'none';
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const contentTextarea = document.querySelector('[name="content"]');
            const summaryTextarea = document.querySelector('[name="summary"]');
            const titleInput = document.querySelector('[name="title"]');

            // Update stats on input
            contentTextarea.addEventListener('input', function() {
                updateStats('content', 'content-stats');
                updatePreview();
            });
            
            summaryTextarea.addEventListener('input', function() {
                updateStats('summary', 'summary-stats');
                updatePreview();
            });

            titleInput.addEventListener('input', updatePreview);

            // Initial stats update
            updateStats('content', 'content-stats');
            updateStats('summary', 'summary-stats');
            updatePreview();
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const title = document.querySelector('[name="title"]').value.trim();
            const content = document.querySelector('[name="content"]').value.trim();
            const summary = document.querySelector('[name="summary"]').value.trim();
            
            if (!title || !content || !summary) {
                e.preventDefault();
                alert('Vui lòng điền đầy đủ thông tin!');
                return;
            }
            
            if (content.length < 50) {
                e.preventDefault();
                alert('Nội dung văn bản quá ngắn (tối thiểu 50 ký tự)');
                return;
            }
            
            if (summary.length < 20) {
                e.preventDefault();
                alert('Tóm tắt quá ngắn (tối thiểu 20 ký tự)');
                return;
            }

            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang lưu...';
            submitBtn.disabled = true;
        });

        // Reset form
        document.querySelector('button[type="reset"]').addEventListener('click', function() {
            setTimeout(function() {
                updateStats('content', 'content-stats');
                updateStats('summary', 'summary-stats');
                updatePreview();
            }, 10);
        });
    </script>
</body>
</html>