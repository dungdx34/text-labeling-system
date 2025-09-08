<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Labeler Dashboard';

// Use absolute paths
$auth_path = __DIR__ . '/../includes/auth.php';
$functions_path = __DIR__ . '/../includes/functions.php';
$header_path = __DIR__ . '/../includes/header.php';

if (!file_exists($auth_path)) {
    die('Error: Cannot find auth.php. Please check file structure.');
}

if (!file_exists($functions_path)) {
    die('Error: Cannot find functions.php. Please check file structure.');
}

require_once $auth_path;
require_once $functions_path;

try {
    $auth = new Auth();
    $auth->requireRole('labeler');

    $functions = new Functions();

    // Get labeler's statistics with error handling
    $labeler_id = $_SESSION['user_id'];
    $my_labelings = [];
    $completed_count = 0;
    $pending_count = 0;
    $reviewed_count = 0;
    $available_documents = [];

    try {
        $my_labelings = $functions->getLabelings($labeler_id);
        $completed_count = count(array_filter($my_labelings, function($l) { return $l['status'] === 'completed'; }));
        $pending_count = count(array_filter($my_labelings, function($l) { return $l['status'] === 'pending'; }));
        $reviewed_count = count(array_filter($my_labelings, function($l) { return $l['status'] === 'reviewed'; }));
    } catch (Exception $e) {
        // Handle gracefully
        $my_labelings = [];
    }

    try {
        $available_documents = $functions->getDocuments('pending', 10);
    } catch (Exception $e) {
        $available_documents = [];
    }

} catch (Exception $e) {
    die('Error: ' . $e->getMessage() . '. Please run setup.php first.');
}

// Include header if exists, otherwise use simple header
if (file_exists($header_path)) {
    require_once $header_path;
} else {
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
        .sidebar { 
            background: linear-gradient(180deg, #0d6efd 0%, #0a58ca 100%); 
            min-height: calc(100vh - 56px); 
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.8); 
            transition: all 0.3s; 
            border-radius: 8px; 
            margin: 4px 0; 
            padding: 12px 16px; 
            font-weight: 500;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { 
            color: white; 
            background: rgba(255,255,255,0.15); 
            transform: translateX(8px); 
        }
        .main-content { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
            margin: 20px; 
            padding: 40px; 
        }
        .stats-card { 
            background: linear-gradient(135deg, white 0%, #f8f9fa 100%); 
            border-radius: 12px; 
            padding: 25px; 
            text-align: center; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.08); 
            transition: all 0.3s ease; 
        }
        .stats-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
        }
        .stats-number { 
            font-size: 2.5rem; 
            font-weight: 800; 
            background: linear-gradient(135deg, #0d6efd, #0a58ca); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .text-gradient { 
            background: linear-gradient(135deg, #0d6efd, #764ba2); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .navbar { 
            background: rgba(13, 110, 253, 0.95) !important; 
            backdrop-filter: blur(10px); 
        }
        .document-card { 
            transition: all 0.3s ease; 
            margin-bottom: 20px; 
            border: 1px solid rgba(0, 0, 0, 0.08); 
            border-radius: 12px;
        }
        .document-card:hover { 
            transform: translateY(-8px) scale(1.02); 
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15); 
        }
        .card { 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.08); 
        }
        .card-header { 
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); 
            color: white; 
            border: none; 
            font-weight: 600; 
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
                    <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['full_name'] ?? 'Labeler'; ?>
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
                </a>
            </div>
        </div>
    </nav>
<?php } ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 p-0">
            <div class="sidebar p-3">
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="my_tasks.php">
                        <i class="fas fa-tasks me-2"></i>Công việc của tôi
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
                            <i class="fas fa-tachometer-alt me-2"></i>Labeler Dashboard
                        </h2>
                        <p class="text-muted mb-0">Chào mừng trở lại, <?php echo $_SESSION['full_name'] ?? 'Labeler'; ?>!</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Làm mới
                        </button>
                        <a href="my_tasks.php" class="btn btn-primary">
                            <i class="fas fa-tasks me-2"></i>Xem tất cả công việc
                        </a>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo $completed_count; ?></div>
                                    <div class="text-muted">Đã hoàn thành</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-check-circle text-success fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo $pending_count; ?></div>
                                    <div class="text-muted">Đang thực hiện</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-clock text-warning fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo $reviewed_count; ?></div>
                                    <div class="text-muted">Đã review</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-star text-primary fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo count($available_documents); ?></div>
                                    <div class="text-muted">Tài liệu có sẵn</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-file-text text-info fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Available Documents -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-file-text me-2"></i>Tài liệu cần gán nhãn</h5>
                                <span class="badge bg-primary"><?php echo count($available_documents); ?> tài liệu</span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($available_documents)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-inbox text-muted" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                        <h4 class="text-muted">Không có tài liệu nào cần gán nhãn</h4>
                                        <p class="text-muted">Hiện tại không có tài liệu mới nào. Hãy quay lại sau!</p>
                                        <a href="my_tasks.php" class="btn btn-primary">
                                            <i class="fas fa-tasks me-2"></i>Xem công việc của tôi
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($available_documents as $doc): ?>
                                        <div class="col-lg-6 col-xl-4 mb-4">
                                            <div class="document-card card h-100">
                                                <div class="card-body d-flex flex-column">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <h6 class="card-title mb-0 flex-grow-1"><?php echo htmlspecialchars($doc['title']); ?></h6>
                                                        <span class="badge bg-secondary ms-2">Mới</span>
                                                    </div>
                                                    
                                                    <p class="card-text text-muted flex-grow-1">
                                                        <?php echo substr(strip_tags($doc['content']), 0, 120) . '...'; ?>
                                                    </p>
                                                    
                                                    <div class="document-meta mb-3">
                                                        <div class="d-flex justify-content-between text-muted small">
                                                            <span>
                                                                <i class="fas fa-calendar me-1"></i>
                                                                <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                                                            </span>
                                                            <span>
                                                                <i class="fas fa-user me-1"></i>
                                                                <?php echo htmlspecialchars($doc['uploaded_by_name'] ?? 'Admin'); ?>
                                                            </span>
                                                        </div>
                                                        <div class="mt-2">
                                                            <div class="progress" style="height: 4px;">
                                                                <div class="progress-bar bg-primary" style="width: 0%"></div>
                                                            </div>
                                                            <small class="text-muted">Chưa bắt đầu</small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="d-flex gap-2">
                                                        <a href="labeling.php?document_id=<?php echo $doc['id']; ?>" 
                                                           class="btn btn-primary flex-grow-1">
                                                            <i class="fas fa-tags me-1"></i>Bắt đầu gán nhãn
                                                        </a>
                                                        <button class="btn btn-outline-info" 
                                                                onclick="previewDocument(<?php echo $doc['id']; ?>)"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#previewModal">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if (count($available_documents) >= 10): ?>
                                    <div class="text-center mt-4">
                                        <a href="#" class="btn btn-outline-primary">
                                            <i class="fas fa-chevron-down me-2"></i>Xem thêm tài liệu
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Work -->
                <?php if (!empty($my_labelings)): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-history me-2"></i>Công việc gần đây</h5>
                                <a href="my_tasks.php" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tài liệu</th>
                                                <th>Phong cách</th>
                                                <th>Trạng thái</th>
                                                <th>Cập nhật</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $recent_labelings = array_slice($my_labelings, 0, 5);
                                            foreach ($recent_labelings as $labeling): 
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($labeling['document_title'] ?? 'Không xác định'); ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($labeling['text_style_name'] ?? 'Chưa chọn'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_colors = [
                                                        'pending' => 'warning',
                                                        'completed' => 'success',
                                                        'reviewed' => 'primary',
                                                        'rejected' => 'danger'
                                                    ];
                                                    $status_names = [
                                                        'pending' => 'Đang thực hiện',
                                                        'completed' => 'Hoàn thành',
                                                        'reviewed' => 'Đã review',
                                                        'rejected' => 'Bị từ chối'
                                                    ];
                                                    $status = $labeling['status'] ?? 'pending';
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_colors[$status]; ?>">
                                                        <?php echo $status_names[$status]; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($labeling['updated_at'] ?? $labeling['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($status === 'pending'): ?>
                                                        <a href="labeling.php?document_id=<?php echo $labeling['document_id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="fas fa-edit me-1"></i>Tiếp tục
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick="viewLabeling(<?php echo $labeling['id']; ?>)">
                                                            <i class="fas fa-eye me-1"></i>Xem
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bolt me-2"></i>Thao tác nhanh</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <a href="my_tasks.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-tasks me-2"></i>Xem tất cả công việc
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <a href="../debug.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-bug me-2"></i>Debug hệ thống
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Document Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-text me-2"></i>Xem trước tài liệu
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="documentPreviewContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Đang tải...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="startLabelingBtn">
                    <i class="fas fa-tags me-2"></i>Bắt đầu gán nhãn
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
// Preview document function
function previewDocument(documentId) {
    const content = document.getElementById('documentPreviewContent');
    const startBtn = document.getElementById('startLabelingBtn');
    
    content.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Đang tải...</span>
            </div>
        </div>
    `;
    
    // Simulated preview - replace with actual API call
    setTimeout(() => {
        content.innerHTML = `
            <div class="mb-3">
                <h6 class="text-primary">Tiêu đề:</h6>
                <p class="fw-semibold">Tài liệu mẫu ${documentId}</p>
            </div>
            <div class="mb-3">
                <h6 class="text-primary">Nội dung:</h6>
                <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto; background-color: #f8f9fa;">
                    Đây là nội dung mẫu của tài liệu ${documentId}. Trong thực tế, nội dung sẽ được tải từ cơ sở dữ liệu.
                </div>
            </div>
        `;
        
        startBtn.onclick = function() {
            window.location.href = `labeling.php?document_id=${documentId}`;
        };
    }, 1000);
}

function viewLabeling(labelingId) {
    // Implementation for viewing labeling details
    window.location.href = `my_tasks.php#labeling-${labelingId}`;
}

console.log('Labeler Dashboard loaded successfully');
</script>

</body>
</html>