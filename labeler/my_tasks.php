<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Công việc của tôi';

// Use absolute paths
$auth_path = __DIR__ . '/../includes/auth.php';
$functions_path = __DIR__ . '/../includes/functions.php';
$header_path = __DIR__ . '/../includes/header.php';

if (!file_exists($auth_path)) {
    die('Error: Cannot find auth.php at: ' . $auth_path);
}

if (!file_exists($functions_path)) {
    die('Error: Cannot find functions.php at: ' . $functions_path);
}

require_once $auth_path;
require_once $functions_path;

try {
    $auth = new Auth();
    $auth->requireRole('labeler');

    $functions = new Functions();
    $labeler_id = $_SESSION['user_id'];
    $my_labelings = $functions->getLabelings($labeler_id);

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
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
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar { background: linear-gradient(180deg, #0d6efd 0%, #0a58ca 100%); min-height: calc(100vh - 56px); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); transition: all 0.3s; border-radius: 8px; margin: 4px 0; padding: 12px 16px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: white; background: rgba(255,255,255,0.15); transform: translateX(8px); }
        .main-content { background: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin: 20px; padding: 40px; }
        .document-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); transition: all 0.3s ease; margin-bottom: 20px; }
        .document-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .text-gradient { background: linear-gradient(135deg, #0d6efd, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .navbar { background: rgba(13, 110, 253, 0.95) !important; backdrop-filter: blur(10px); }
        .nav-tabs .nav-link.active { background: #0d6efd; color: white; }
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
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="my_tasks.php">
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
                            <i class="fas fa-tasks me-2"></i>Công việc của tôi
                        </h2>
                        <p class="text-muted mb-0">Tổng cộng: <strong><?php echo count($my_labelings); ?></strong> công việc</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Quay lại Dashboard
                    </a>
                </div>
                
                <!-- Filter Tabs -->
                <ul class="nav nav-tabs mb-4" id="statusTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#all">
                            Tất cả <span class="badge bg-secondary ms-1"><?php echo count($my_labelings); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#pending">
                            Đang thực hiện 
                            <span class="badge bg-warning ms-1">
                                <?php echo count(array_filter($my_labelings, function($l) { return $l['status'] === 'pending'; })); ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#completed">
                            Hoàn thành 
                            <span class="badge bg-success ms-1">
                                <?php echo count(array_filter($my_labelings, function($l) { return $l['status'] === 'completed'; })); ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#reviewed">
                            Đã review 
                            <span class="badge bg-primary ms-1">
                                <?php echo count(array_filter($my_labelings, function($l) { return $l['status'] === 'reviewed'; })); ?>
                            </span>
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- All Tasks -->
                    <div class="tab-pane fade show active" id="all">
                        <?php if (empty($my_labelings)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tasks text-muted" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                <h4 class="text-muted">Bạn chưa có công việc nào</h4>
                                <p class="text-muted">Hãy quay lại Dashboard để bắt đầu gán nhãn tài liệu mới.</p>
                                <a href="dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Về Dashboard
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($my_labelings as $labeling): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="document-card card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title"><?php echo htmlspecialchars($labeling['document_title']); ?></h6>
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
                                                ?>
                                                <span class="badge bg-<?php echo $status_colors[$labeling['status']] ?? 'secondary'; ?>">
                                                    <?php echo $status_names[$labeling['status']] ?? 'Không xác định'; ?>
                                                </span>
                                            </div>
                                            
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <i class="fas fa-palette me-1"></i>Phong cách: <?php echo htmlspecialchars($labeling['text_style_name'] ?? 'Chưa chọn'); ?>
                                                </small>
                                            </p>
                                            
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>Cập nhật: <?php echo date('d/m/Y H:i', strtotime($labeling['updated_at'])); ?>
                                                </small>
                                            </p>
                                            
                                            <?php if ($labeling['review_notes']): ?>
                                            <div class="alert alert-info small mb-3">
                                                <i class="fas fa-comment me-1"></i>
                                                <strong>Ghi chú từ reviewer:</strong><br>
                                                <?php echo htmlspecialchars($labeling['review_notes']); ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <?php if ($labeling['status'] === 'pending'): ?>
                                                    <a href="labeling.php?document_id=<?php echo $labeling['document_id']; ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit me-1"></i>Tiếp tục gán nhãn
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-info btn-sm" 
                                                            onclick="viewLabeling(<?php echo $labeling['id']; ?>)"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewLabelingModal">
                                                        <i class="fas fa-eye me-1"></i>Xem chi tiết
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <small class="text-muted">
                                                    ID: #<?php echo $labeling['id']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Filter by status tabs -->
                    <?php 
                    $status_filters = ['pending', 'completed', 'reviewed'];
                    foreach ($status_filters as $filter_status): 
                        $filtered_labelings = array_filter($my_labelings, function($l) use ($filter_status) { 
                            return $l['status'] === $filter_status; 
                        });
                    ?>
                    <div class="tab-pane fade" id="<?php echo $filter_status; ?>">
                        <?php if (empty($filtered_labelings)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox text-muted fa-3x mb-3"></i>
                                <p class="text-muted">Không có công việc nào với trạng thái này.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($filtered_labelings as $labeling): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="document-card card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title"><?php echo htmlspecialchars($labeling['document_title']); ?></h6>
                                                <span class="badge bg-<?php echo $status_colors[$labeling['status']]; ?>">
                                                    <?php echo $status_names[$labeling['status']]; ?>
                                                </span>
                                            </div>
                                            
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <i class="fas fa-palette me-1"></i>Phong cách: <?php echo htmlspecialchars($labeling['text_style_name'] ?? 'Chưa chọn'); ?>
                                                </small>
                                            </p>
                                            
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>Cập nhật: <?php echo date('d/m/Y H:i', strtotime($labeling['updated_at'])); ?>
                                                </small>
                                            </p>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <?php if ($labeling['status'] === 'pending'): ?>
                                                    <a href="labeling.php?document_id=<?php echo $labeling['document_id']; ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit me-1"></i>Tiếp tục gán nhãn
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-info btn-sm" 
                                                            onclick="viewLabeling(<?php echo $labeling['id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>Xem chi tiết
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <small class="text-muted">
                                                    ID: #<?php echo $labeling['id']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Labeling Modal -->
<div class="modal fade" id="viewLabelingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>Chi tiết công việc gán nhãn
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="labelingDetails">
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
function viewLabeling(labelingId) {
    // Find the labeling data from PHP
    const labelings = <?php echo json_encode($my_labelings); ?>;
    const labeling = labelings.find(l => l.id == labelingId);
    
    if (labeling) {
        document.getElementById('labelingDetails').innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary">Thông tin cơ bản:</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Tài liệu:</strong></td><td>${labeling.document_title}</td></tr>
                        <tr><td><strong>Phong cách:</strong></td><td>${labeling.text_style_name || 'Chưa chọn'}</td></tr>
                        <tr><td><strong>Trạng thái:</strong></td><td><span class="badge bg-${getStatusColor(labeling.status)}">${getStatusName(labeling.status)}</span></td></tr>
                        <tr><td><strong>Cập nhật:</strong></td><td>${new Date(labeling.updated_at).toLocaleString('vi-VN')}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="text-primary">Câu đã chọn:</h6>
                    <div class="border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                        ${labeling.important_sentences ? JSON.parse(labeling.important_sentences).length + ' câu đã được chọn' : 'Chưa chọn câu nào'}
                    </div>
                </div>
            </div>
            ${labeling.edited_summary ? `
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="text-primary">Bản tóm tắt đã chỉnh sửa:</h6>
                    <div class="border rounded p-3 bg-light">
                        ${labeling.edited_summary}
                    </div>
                </div>
            </div>
            ` : ''}
            ${labeling.review_notes ? `
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="text-primary">Ghi chú từ reviewer:</h6>
                    <div class="alert alert-info">
                        ${labeling.review_notes}
                    </div>
                </div>
            </div>
            ` : ''}
        `;
    }
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'completed': 'success', 
        'reviewed': 'primary',
        'rejected': 'danger'
    };
    return colors[status] || 'secondary';
}

function getStatusName(status) {
    const names = {
        'pending': 'Đang thực hiện',
        'completed': 'Hoàn thành',
        'reviewed': 'Đã review',
        'rejected': 'Bị từ chối'
    };
    return names[status] || 'Không xác định';
}

console.log('My Tasks page loaded successfully');
</script>

</body>
</html>