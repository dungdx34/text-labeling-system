<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Báo cáo thống kê';

// Use absolute paths
$auth_path = __DIR__ . '/../includes/auth.php';
$functions_path = __DIR__ . '/../includes/functions.php';
$header_path = __DIR__ . '/../includes/header.php';

if (!file_exists($auth_path)) {
    die('Error: Cannot find auth.php');
}

if (!file_exists($functions_path)) {
    die('Error: Cannot find functions.php');
}

require_once $auth_path;
require_once $functions_path;

try {
    $auth = new Auth();
    $auth->requireRole('admin');

    $functions = new Functions();

    // Get comprehensive statistics
    $stats = $functions->getStatistics();
    $all_users = $functions->getUsers();
    $all_documents = $functions->getDocuments();
    $all_labelings = $functions->getLabelings();
    $recent_activity = $functions->getRecentActivity(20);

    // Calculate additional metrics
    $total_users = count($all_users);
    $total_documents = count($all_documents);
    $total_labelings = count($all_labelings);
    
    // User distribution
    $admin_count = count($functions->getUsers('admin'));
    $labeler_count = count($functions->getUsers('labeler'));
    $reviewer_count = count($functions->getUsers('reviewer'));
    
    // Document status distribution
    $pending_docs = count($functions->getDocuments('pending'));
    $in_progress_docs = count($functions->getDocuments('in_progress'));
    $completed_docs = count($functions->getDocuments('completed'));
    $reviewed_docs = count($functions->getDocuments('reviewed'));
    
    // Labeling status distribution
    $pending_labelings = count($functions->getLabelings(null, 'pending'));
    $completed_labelings = count($functions->getLabelings(null, 'completed'));
    $reviewed_labelings = count($functions->getLabelings(null, 'reviewed'));
    $rejected_labelings = count($functions->getLabelings(null, 'rejected'));
    
    // Performance metrics
    $completion_rate = $total_documents > 0 ? round(($completed_docs / $total_documents) * 100, 2) : 0;
    $review_rate = $completed_labelings > 0 ? round(($reviewed_labelings / $completed_labelings) * 100, 2) : 0;
    $average_labelings_per_user = $labeler_count > 0 ? round($total_labelings / $labeler_count, 2) : 0;

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// Include header if exists
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
        .stats-card { background: linear-gradient(135deg, white 0%, #f8f9fa 100%); border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.08); transition: all 0.3s ease; }
        .stats-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .stats-number { font-size: 2.5rem; font-weight: 800; background: linear-gradient(135deg, #0d6efd, #0a58ca); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .text-gradient { background: linear-gradient(135deg, #0d6efd, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .navbar { background: rgba(13, 110, 253, 0.95) !important; backdrop-filter: blur(10px); }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .metric-card { background: linear-gradient(135deg, #f8f9ff 0%, #e3f2fd 100%); border-left: 4px solid #0d6efd; }
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
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-2"></i>Quản lý người dùng
                    </a>
                    <a class="nav-link" href="upload.php">
                        <i class="fas fa-upload me-2"></i>Upload dữ liệu
                    </a>
                    <a class="nav-link active" href="reports.php">
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
                            <i class="fas fa-chart-bar me-2"></i>Báo cáo thống kê
                        </h2>
                        <p class="text-muted mb-0">Tổng quan hiệu suất hệ thống</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="exportReport('pdf')">
                            <i class="fas fa-file-pdf me-2"></i>Xuất PDF
                        </button>
                        <button class="btn btn-outline-success" onclick="exportReport('excel')">
                            <i class="fas fa-file-excel me-2"></i>Xuất Excel
                        </button>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Làm mới
                        </button>
                    </div>
                </div>
                
                <!-- Key Metrics -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo $total_users; ?></div>
                                    <div class="text-muted">Tổng người dùng</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-users text-primary fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo $total_documents; ?></div>
                                    <div class="text-muted">Tổng tài liệu</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-file-text text-success fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo $total_labelings; ?></div>
                                    <div class="text-muted">Tổng công việc</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-tags text-warning fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo $completion_rate; ?>%</div>
                                    <div class="text-muted">Tỷ lệ hoàn thành</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-percentage text-info fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Metrics -->
                <div class="row mb-4">
                    <div class="col-lg-4 mb-3">
                        <div class="card metric-card">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-chart-line me-2"></i>Hiệu suất tổng thể
                                </h6>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="h4 text-success"><?php echo $completion_rate; ?>%</div>
                                        <small class="text-muted">Tỷ lệ hoàn thành</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h4 text-info"><?php echo $review_rate; ?>%</div>
                                        <small class="text-muted">Tỷ lệ review</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 mb-3">
                        <div class="card metric-card">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-user-friends me-2"></i>Phân bổ người dùng
                                </h6>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="h5 text-danger"><?php echo $admin_count; ?></div>
                                        <small class="text-muted">Admins</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h5 text-primary"><?php echo $labeler_count; ?></div>
                                        <small class="text-muted">Labelers</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h5 text-success"><?php echo $reviewer_count; ?></div>
                                        <small class="text-muted">Reviewers</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 mb-3">
                        <div class="card metric-card">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-calculator me-2"></i>Trung bình
                                </h6>
                                <div class="text-center">
                                    <div class="h4 text-warning"><?php echo $average_labelings_per_user; ?></div>
                                    <small class="text-muted">Công việc / người dùng</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Trạng thái tài liệu</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="documentStatusChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-doughnut me-2"></i>Trạng thái công việc</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="labelingStatusChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Statistics -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Thống kê chi tiết</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Chỉ số</th>
                                                <th>Giá trị</th>
                                                <th>Phần trăm</th>
                                                <th>Trạng thái</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><i class="fas fa-file-text text-secondary me-2"></i>Tài liệu chờ gán nhãn</td>
                                                <td><strong><?php echo $pending_docs; ?></strong></td>
                                                <td><?php echo $total_documents > 0 ? round(($pending_docs / $total_documents) * 100, 1) : 0; ?>%</td>
                                                <td><span class="badge bg-secondary">Chờ xử lý</span></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-clock text-warning me-2"></i>Tài liệu đang gán nhãn</td>
                                                <td><strong><?php echo $in_progress_docs; ?></strong></td>
                                                <td><?php echo $total_documents > 0 ? round(($in_progress_docs / $total_documents) * 100, 1) : 0; ?>%</td>
                                                <td><span class="badge bg-warning">Đang xử lý</span></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-check text-success me-2"></i>Tài liệu đã hoàn thành</td>
                                                <td><strong><?php echo $completed_docs; ?></strong></td>
                                                <td><?php echo $total_documents > 0 ? round(($completed_docs / $total_documents) * 100, 1) : 0; ?>%</td>
                                                <td><span class="badge bg-success">Hoàn thành</span></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-check-double text-primary me-2"></i>Tài liệu đã review</td>
                                                <td><strong><?php echo $reviewed_docs; ?></strong></td>
                                                <td><?php echo $total_documents > 0 ? round(($reviewed_docs / $total_documents) * 100, 1) : 0; ?>%</td>
                                                <td><span class="badge bg-primary">Đã review</span></td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-times text-danger me-2"></i>Công việc bị từ chối</td>
                                                <td><strong><?php echo $rejected_labelings; ?></strong></td>
                                                <td><?php echo $total_labelings > 0 ? round(($rejected_labelings / $total_labelings) * 100, 1) : 0; ?>%</td>
                                                <td><span class="badge bg-danger">Từ chối</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Cảnh báo</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($pending_docs > 10): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Nhiều tài liệu chờ xử lý!</strong><br>
                                    Có <?php echo $pending_docs; ?> tài liệu cần được gán nhãn.
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($labeler_count == 0): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-user-times me-2"></i>
                                    <strong>Không có labeler!</strong><br>
                                    Cần thêm người gán nhãn để xử lý tài liệu.
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($reviewer_count == 0): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-user-check me-2"></i>
                                    <strong>Không có reviewer!</strong><br>
                                    Cần thêm người review để kiểm tra chất lượng.
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($completion_rate < 50): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-chart-line me-2"></i>
                                    <strong>Tỷ lệ hoàn thành thấp</strong><br>
                                    Hiện tại chỉ <?php echo $completion_rate; ?>% tài liệu đã hoàn thành.
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($pending_docs == 0 && $in_progress_docs == 0 && $completed_docs > 0): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Tuyệt vời!</strong><br>
                                    Tất cả tài liệu đã được xử lý.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="card mt-4">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Thao tác nhanh</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="users.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-users me-2"></i>Quản lý người dùng
                                    </a>
                                    <a href="upload.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-upload me-2"></i>Upload tài liệu mới
                                    </a>
                                    <a href="dashboard.php" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-tachometer-alt me-2"></i>Về Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Hoạt động gần đây (20 mục cuối)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activity)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox text-muted fa-3x mb-3"></i>
                                        <p class="text-muted">Chưa có hoạt động nào được ghi nhận</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Thời gian</th>
                                                    <th>Hoạt động</th>
                                                    <th>Người thực hiện</th>
                                                    <th>Loại</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_activity as $activity): ?>
                                                <tr>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y H:i', strtotime($activity['timestamp'])); ?>
                                                        </small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                                    <td>
                                                        <span class="fw-semibold"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($activity['type'] == 'document_uploaded'): ?>
                                                            <span class="badge bg-success">Upload</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-primary">Labeling</span>
                                                        <?php endif; ?>
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
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Document Status Chart
    const docCtx = document.getElementById('documentStatusChart');
    if (docCtx) {
        new Chart(docCtx, {
            type: 'pie',
            data: {
                labels: ['Chờ gán nhãn', 'Đang gán nhãn', 'Đã hoàn thành', 'Đã review'],
                datasets: [{
                    data: [<?php echo $pending_docs; ?>, <?php echo $in_progress_docs; ?>, <?php echo $completed_docs; ?>, <?php echo $reviewed_docs; ?>],
                    backgroundColor: ['#6c757d', '#ffc107', '#28a745', '#007bff'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Labeling Status Chart
    const labelCtx = document.getElementById('labelingStatusChart');
    if (labelCtx) {
        new Chart(labelCtx, {
            type: 'doughnut',
            data: {
                labels: ['Chờ xử lý', 'Đã hoàn thành', 'Đã review', 'Bị từ chối'],
                datasets: [{
                    data: [<?php echo $pending_labelings; ?>, <?php echo $completed_labelings; ?>, <?php echo $reviewed_labelings; ?>, <?php echo $rejected_labelings; ?>],
                    backgroundColor: ['#ffc107', '#28a745', '#007bff', '#dc3545'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});

// Export functions
function exportReport(format) {
    if (format === 'pdf') {
        alert('Tính năng xuất PDF đang được phát triển.');
    } else if (format === 'excel') {
        alert('Tính năng xuất Excel đang được phát triển.');
    }
}

console.log('Reports page loaded successfully');
</script>

</body>
</html>