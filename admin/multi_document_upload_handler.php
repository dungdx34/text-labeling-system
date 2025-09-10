<?php
// admin/enhanced_upload.php - Enhanced upload handler for single and multi-document labeling
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Enhanced Upload - Text Labeling System';

require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/enhanced_functions.php';

try {
    $auth = new Auth();
    $auth->requireRole('admin');

    $enhancedFunctions = new EnhancedFunctions();
    $success_message = '';
    $error_message = '';

    // Handle upload form submission
    if ($_POST && isset($_POST['action']) && $_POST['action'] === 'upload_documents') {
        $upload_type = $_POST['upload_type'] ?? '';
        $uploaded_by = $_SESSION['user_id'];

        if ($upload_type === 'single') {
            // Handle single document upload
            $title = trim($_POST['single_title'] ?? '');
            $content = '';
            $ai_summary = trim($_POST['single_summary'] ?? '');

            // Check if file was uploaded
            if (isset($_FILES['single_file']) && $_FILES['single_file']['error'] === UPLOAD_ERR_OK) {
                $content = file_get_contents($_FILES['single_file']['tmp_name']);
            } else {
                $content = trim($_POST['single_content'] ?? '');
            }

            if (empty($title) || empty($content)) {
                $error_message = 'Vui lòng nhập đầy đủ tiêu đề và nội dung văn bản.';
            } else {
                // Create single document group
                $group_id = $enhancedFunctions->createDocumentGroup($title, 'Văn bản đơn', 'single', $ai_summary, $uploaded_by);
                
                if ($group_id) {
                    // Add document to group
                    if ($enhancedFunctions->addDocumentToGroup($group_id, $title, $content, 1)) {
                        $success_message = 'Upload văn bản đơn thành công!';
                    } else {
                        $error_message = 'Có lỗi khi lưu văn bản.';
                    }
                } else {
                    $error_message = 'Có lỗi khi tạo nhóm văn bản.';
                }
            }

        } elseif ($upload_type === 'multi') {
            // Handle multi-document upload
            $group_title = trim($_POST['group_title'] ?? '');
            $group_description = trim($_POST['group_description'] ?? '');
            $group_summary = trim($_POST['group_summary'] ?? '');

            if (empty($group_title)) {
                $error_message = 'Vui lòng nhập tiêu đề cho nhóm văn bản.';
            } else {
                // Create document group
                $group_id = $enhancedFunctions->createDocumentGroup($group_title, $group_description, 'multi', $group_summary, $uploaded_by);
                
                if ($group_id) {
                    $document_count = 0;
                    $doc_titles = $_POST['doc_title'] ?? [];
                    $doc_contents = $_POST['doc_content'] ?? [];
                    $doc_files = $_FILES['doc_file'] ?? [];

                    // Process each document
                    for ($i = 0; $i < count($doc_titles); $i++) {
                        $doc_title = trim($doc_titles[$i] ?? '');
                        $doc_content = '';

                        // Check if file was uploaded for this document
                        if (isset($doc_files['tmp_name'][$i]) && $doc_files['error'][$i] === UPLOAD_ERR_OK) {
                            $doc_content = file_get_contents($doc_files['tmp_name'][$i]);
                        } else {
                            $doc_content = trim($doc_contents[$i] ?? '');
                        }

                        // Only add document if it has title and content
                        if (!empty($doc_title) && !empty($doc_content)) {
                            if ($enhancedFunctions->addDocumentToGroup($group_id, $doc_title, $doc_content, $i + 1)) {
                                $document_count++;
                            }
                        }
                    }

                    if ($document_count > 0) {
                        $success_message = "Upload nhóm văn bản thành công! Đã thêm $document_count văn bản.";
                    } else {
                        $error_message = 'Không có văn bản nào được thêm. Vui lòng kiểm tra lại dữ liệu.';
                    }
                } else {
                    $error_message = 'Có lỗi khi tạo nhóm văn bản.';
                }
            }
        }
    }

    // Get recent document groups for display
    $recent_groups = $enhancedFunctions->getDocumentGroups(null, null);
    $recent_groups = array_slice($recent_groups, 0, 10);

} catch (Exception $e) {
    $error_message = 'Lỗi hệ thống: ' . $e->getMessage();
}
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
        .text-gradient { 
            background: linear-gradient(135deg, #0d6efd, #764ba2); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .group-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        .group-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .group-card.single {
            border-left: 4px solid #28a745;
        }
        .group-card.multi {
            border-left: 4px solid #0d6efd;
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
                        <i class="fas fa-upload me-2"></i>Enhanced Document Upload
                    </h2>
                    <p class="text-muted mb-0">Upload văn bản đơn hoặc nhóm văn bản để gán nhãn</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Dashboard
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

            <!-- Upload Interface -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-cloud-upload-alt me-2"></i>Upload Documents
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <a href="../multi-document-upload.html" class="btn btn-lg btn-primary me-3">
                                    <i class="fas fa-upload me-2"></i>Bắt đầu Upload
                                </a>
                                <button class="btn btn-lg btn-outline-info" onclick="showUploadDemo()">
                                    <i class="fas fa-play me-2"></i>Xem Demo
                                </button>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="text-center">
                                        <i class="fas fa-file-text text-success fa-3x mb-3"></i>
                                        <h5>Văn bản đơn</h5>
                                        <p class="text-muted">Upload một văn bản với bản tóm tắt AI</p>
                                        <div class="small text-muted">
                                            <i class="fas fa-check-circle me-1 text-success"></i>Nhanh chóng<br>
                                            <i class="fas fa-check-circle me-1 text-success"></i>Đơn giản<br>
                                            <i class="fas fa-check-circle me-1 text-success"></i>Phù hợp văn bản độc lập
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-center">
                                        <i class="fas fa-copy text-primary fa-3x mb-3"></i>
                                        <h5>Đa văn bản</h5>
                                        <p class="text-muted">Upload nhiều văn bản cùng chủ đề</p>
                                        <div class="small text-muted">
                                            <i class="fas fa-check-circle me-1 text-primary"></i>Tích hợp toàn diện<br>
                                            <i class="fas fa-check-circle me-1 text-primary"></i>Tóm tắt chung<br>
                                            <i class="fas fa-check-circle me-1 text-primary"></i>Phù hợp chủ đề lớn
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
                            <h6 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Thống kê Upload
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $single_count = count(array_filter($recent_groups, function($g) { return $g['group_type'] === 'single'; }));
                            $multi_count = count(array_filter($recent_groups, function($g) { return $g['group_type'] === 'multi'; }));
                            $total_documents = array_sum(array_column($recent_groups, 'document_count'));
                            ?>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="h4 text-success"><?php echo $single_count; ?></div>
                                    <small class="text-muted">Văn bản đơn</small>
                                </div>
                                <div class="col-6">
                                    <div class="h4 text-primary"><?php echo $multi_count; ?></div>
                                    <small class="text-muted">Nhóm văn bản</small>
                                </div>
                            </div>
                            <hr>
                            <div class="text-center">
                                <div class="h5 text-info"><?php echo $total_documents; ?></div>
                                <small class="text-muted">Tổng số văn bản</small>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fas fa-lightbulb me-2"></i>Hướng dẫn
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="small">
                                <strong>Văn bản đơn:</strong>
                                <ul class="mb-2">
                                    <li>Phù hợp với bài viết độc lập</li>
                                    <li>Quy trình gán nhãn nhanh</li>
                                </ul>
                                
                                <strong>Đa văn bản:</strong>
                                <ul class="mb-0">
                                    <li>Phù hợp với cùng chủ đề</li>
                                    <li>Tạo tóm tắt tổng hợp</li>
                                    <li>Gán nhãn toàn diện hơn</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Uploads -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Nhóm văn bản đã upload gần đây
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_groups)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox text-muted fa-3x mb-3"></i>
                                    <p class="text-muted">Chưa có nhóm văn bản nào được upload</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($recent_groups as $group): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="group-card card <?php echo $group['group_type']; ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0">
                                                        <?php echo htmlspecialchars($group['title']); ?>
                                                    </h6>
                                                    <span class="badge bg-<?php echo $group['group_type'] === 'single' ? 'success' : 'primary'; ?>">
                                                        <?php echo $group['group_type'] === 'single' ? 'Đơn' : 'Đa'; ?>
                                                    </span>
                                                </div>
                                                
                                                <?php if ($group['description']): ?>
                                                <p class="card-text small text-muted">
                                                    <?php echo htmlspecialchars(substr($group['description'], 0, 100)); ?>
                                                    <?php echo strlen($group['description']) > 100 ? '...' : ''; ?>
                                                </p>
                                                <?php endif; ?>
                                                
                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <small class="text-muted">
                                                            <i class="fas fa-file-text me-1"></i>
                                                            <?php echo $group['document_count']; ?> văn bản
                                                        </small>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                