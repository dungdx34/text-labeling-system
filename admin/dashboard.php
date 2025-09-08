<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Admin Dashboard';

// Use absolute paths to avoid path issues
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
    $auth->requireRole('admin');

    $functions = new Functions();

    // Get statistics with error handling
    $total_users = 0;
    $total_labelers = 0;
    $total_reviewers = 0;
    $total_documents = 0;
    $pending_documents = 0;
    $completed_labelings = 0;
    $recent_activity = [];

    try {
        $all_users = $functions->getUsers();
        $total_users = count($all_users);
        $total_labelers = count($functions->getUsers('labeler'));
        $total_reviewers = count($functions->getUsers('reviewer'));
    } catch (Exception $e) {
        // Handle error gracefully
        $total_users = 0;
    }

    try {
        $all_documents = $functions->getDocuments();
        $total_documents = count($all_documents);
        $pending_documents = count($functions->getDocuments('pending'));
    } catch (Exception $e) {
        $total_documents = 0;
    }

    try {
        $completed_labelings = count($functions->getLabelings(null, 'completed'));
        $recent_activity = $functions->getRecentActivity(5);
    } catch (Exception $e) {
        $completed_labelings = 0;
        $recent_activity = [];
    }

} catch (Exception $e) {
    die('Error: ' . $e->getMessage() . '. Please run setup.php first.');
}

// Include header only if it exists
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
            :root {
                --primary-color: #0d6efd;
                --success-color: #198754;
                --warning-color: #ffc107;
                --danger-color: #dc3545;
            }
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
            }
            .sidebar {
                background: linear-gradient(180deg, var(--primary-color) 0%, #0a58ca 100%);
                min-height: calc(100vh - 56px);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            }
            .sidebar .nav-link {
                color: rgba(255, 255, 255, 0.8);
                transition: all 0.3s ease;
                border-radius: 8px;
                margin: 4px 0;
                font-weight: 500;
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
            .stats-card {
                background: linear-gradient(135deg, white 0%, #f8f9fa 100%);
                border-radius: 12px;
                padding: 25px;
                text-align: center;
                border: none;
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
                background: linear-gradient(135deg, var(--primary-color), #0a58ca);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            .text-gradient {
                background: linear-gradient(135deg, var(--primary-color), #764ba2);
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
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-2"></i>Quản lý người dùng
                    </a>
                    <a class="nav-link" href="upload.php">
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
                            <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                        </h2>
                        <p class="text-muted mb-0">Chào mừng trở lại, <?php echo $_SESSION['full_name'] ?? 'Admin'; ?>!</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Làm mới
                        </button>
                        <a href="../debug.php" class="btn btn-outline-info">
                            <i class="fas fa-bug me-2"></i>Debug
                        </a>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
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
                                    <div class="stats-number"><?php echo $pending_documents; ?></div>
                                    <div class="text-muted">Đang chờ gán nhãn</div>
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
                                    <div class="stats-number"><?php echo $completed_labelings; ?></div>
                                    <div class="text-muted">Đã hoàn thành</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-check-circle text-info fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Distribution -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Phân bổ người dùng</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span><i class="fas fa-circle text-danger me-2"></i>Admins:</span>
                                    <span class="fw-bold"><?php echo count($functions->getUsers('admin')); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><i class="fas fa-circle text-primary me-2"></i>Labelers:</span>
                                    <span class="fw-bold"><?php echo $total_labelers; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><i class="fas fa-circle text-success me-2"></i>Reviewers:</span>
                                    <span class="fw-bold"><?php echo $total_reviewers; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Trạng thái tài liệu</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                try {
                                    $status_counts = [
                                        'pending' => count($functions->getDocuments('pending')),
                                        'in_progress' => count($functions->getDocuments('in_progress')),
                                        'completed' => count($functions->getDocuments('completed')),
                                        'reviewed' => count($functions->getDocuments('reviewed'))
                                    ];
                                } catch (Exception $e) {
                                    $status_counts = [
                                        'pending' => 0,
                                        'in_progress' => 0,
                                        'completed' => 0,
                                        'reviewed' => 0
                                    ];
                                }
                                
                                $status_labels = [
                                    'pending' => 'Chờ gán nhãn',
                                    'in_progress' => 'Đang gán nhãn',
                                    'completed' => 'Hoàn thành',
                                    'reviewed' => 'Đã review'
                                ];
                                
                                $badge_colors = [
                                    'pending' => 'secondary',
                                    'in_progress' => 'warning',
                                    'completed' => 'success',
                                    'reviewed' => 'primary'
                                ];
                                
                                foreach ($status_counts as $status => $count):
                                ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?php echo $status_labels[$status]; ?>:</span>
                                    <span class="badge bg-<?php echo $badge_colors[$status]; ?>"><?php echo $count; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Hoạt động gần đây</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activity)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox text-muted fa-3x mb-3"></i>
                                        <p class="text-muted">Chưa có hoạt động nào</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_activity as $activity): ?>
                                        <div class="list-group-item border-0">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-circle text-primary me-3" style="font-size: 8px;"></i>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($activity['description']); ?></h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($activity['user_name']); ?>
                                                        <span class="ms-2">
                                                            <i class="fas fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($activity['timestamp'])); ?>
                                                        </span>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Thao tác nhanh</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-3">
                                    <a href="users.php" class="btn btn-outline-primary">
                                        <i class="fas fa-user-plus me-2"></i>Quản lý người dùng
                                    </a>
                                    <a href="upload.php" class="btn btn-outline-success">
                                        <i class="fas fa-upload me-2"></i>Upload tài liệu
                                    </a>
                                    <a href="reports.php" class="btn btn-outline-info">
                                        <i class="fas fa-chart-bar me-2"></i>Xem báo cáo
                                    </a>
                                    <a href="../debug.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-bug me-2"></i>Debug System
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Status -->
                        <div class="card mt-4">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="fas fa-server me-2"></i>Trạng thái hệ thống</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span>Database</span>
                                    <span class="badge bg-success">Hoạt động</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span>PHP Version</span>
                                    <span class="badge bg-info"><?php echo PHP_VERSION; ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Server</span>
                                    <span class="badge bg-success">Online</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
// Remove the page loader since we're not using it
// Auto refresh every 5 minutes
setTimeout(() => {
    if (!document.querySelector('.modal.show')) {
        location.reload();
    }
}, 300000);

console.log('Admin Dashboard loaded successfully');
</script>

</body>
</html>