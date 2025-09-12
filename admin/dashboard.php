<?php
require_once '../includes/auth.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'text_labeling_system');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get basic statistics
function getStats($conn) {
    $stats = [];
    
    // Total documents
    $result = $conn->query("SELECT COUNT(*) as count FROM documents");
    $stats['total_documents'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total users
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stats['total_users'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total labelings
    $result = $conn->query("SELECT COUNT(*) as count FROM labelings");
    $stats['total_labelings'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Completed tasks
    $result = $conn->query("SELECT COUNT(*) as count FROM labelings WHERE status = 'completed'");
    $stats['completed_tasks'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Pending tasks
    $result = $conn->query("SELECT COUNT(*) as count FROM labelings WHERE status IN ('assigned', 'in_progress')");
    $stats['pending_tasks'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total groups (if exists)
    $tableExists = $conn->query("SHOW TABLES LIKE 'document_groups'");
    if ($tableExists && $tableExists->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as count FROM document_groups");
        $stats['total_groups'] = $result ? $result->fetch_assoc()['count'] : 0;
    } else {
        $stats['total_groups'] = 0;
    }
    
    return $stats;
}

$stats = getStats($conn);

// Get recent activities
$recentUploads = [];
$result = $conn->query("
    SELECT d.title, u.username, d.created_at 
    FROM documents d 
    JOIN users u ON d.uploaded_by = u.id 
    ORDER BY d.created_at DESC 
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentUploads[] = $row;
    }
}

// Get active labelers
$activeLabelers = [];
$result = $conn->query("
    SELECT u.username, u.full_name, COUNT(l.id) as active_tasks
    FROM users u
    LEFT JOIN labelings l ON u.id = l.labeler_id AND l.status IN ('assigned', 'in_progress')
    WHERE u.role = 'labeler' AND u.status = 'active'
    GROUP BY u.id
    ORDER BY active_tasks DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $activeLabelers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Text Labeling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #fff;
            width: 16.66667%;
            min-height: 100vh;
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .nav-link {
            color: #333;
            padding: 12px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .nav-link:hover {
            background: #f8f9fc;
            color: #5a5c69;
        }
        
        .nav-link.active {
            background: #4e73df;
            color: white !important;
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: 16.66667%;
            padding: 20px;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.25rem 2rem 0 rgba(58, 59, 69, 0.2);
        }
        
        .border-left-primary {
            border-left: 0.25rem solid #4e73df !important;
        }
        
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        
        .border-left-info {
            border-left: 0.25rem solid #36b9cc !important;
        }
        
        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }
        
        .quick-action-card {
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 20px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        
        .quick-action-card:hover {
            transform: translateY(-5px);
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .quick-action-card.upload {
            background: linear-gradient(45deg, #4e73df 0%, #36b9cc 100%);
        }
        
        .quick-action-card.users {
            background: linear-gradient(45deg, #1cc88a 0%, #2ecc71 100%);
        }
        
        .quick-action-card.tasks {
            background: linear-gradient(45deg, #36b9cc 0%, #3498db 100%);
        }
        
        .quick-action-card.reports {
            background: linear-gradient(45deg, #f6c23e 0%, #f39c12 100%);
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.3;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .labeler-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .labeler-item:last-child {
            border-bottom: none;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, #4e73df, #36b9cc);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 15px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-primary" href="#">
                <i class="fas fa-tags me-2"></i>Text Labeling System
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="enhanced_upload.php">
                                <i class="fas fa-cloud-upload-alt me-2"></i>Enhanced Upload
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>Quản lý người dùng
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Báo cáo
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h2 mb-2">
                                <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                            </h1>
                            <p class="mb-0 opacity-75">Chào mừng trở lại, <?php echo htmlspecialchars($_SESSION['full_name'] ?: $_SESSION['username']); ?>!</p>
                        </div>
                        <div>
                            <button class="btn btn-light btn-sm" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt me-2"></i>Làm mới
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Tổng văn bản
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['total_documents']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-file-text stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Đã hoàn thành
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['completed_tasks']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Nhóm văn bản
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['total_groups']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-copy stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Labeler hoạt động
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo count($activeLabelers); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="text-gray-800 mb-3">
                            <i class="fas fa-bolt me-2"></i>Thao tác nhanh
                        </h5>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="enhanced_upload.php" class="quick-action-card upload">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <span class="fw-bold">Upload Văn bản Mới</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="users.php" class="quick-action-card users">
                            <i class="fas fa-user-plus fa-2x mb-2"></i>
                            <span class="fw-bold">Thêm Labeler</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="#" class="quick-action-card tasks" onclick="viewPendingTasks()">
                            <i class="fas fa-tasks fa-2x mb-2"></i>
                            <span class="fw-bold">Xem Nhiệm vụ Chờ</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reports.php" class="quick-action-card reports">
                            <i class="fas fa-chart-line fa-2x mb-2"></i>
                            <span class="fw-bold">Xem Báo cáo</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-clock me-2"></i>Hoạt động gần đây
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recentUploads)): ?>
                                    <?php foreach ($recentUploads as $upload): ?>
                                    <div class="activity-item">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-file-upload text-primary"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold"><?php echo htmlspecialchars($upload['title']); ?></div>
                                                <small class="text-muted">
                                                    Upload bởi <?php echo htmlspecialchars($upload['username']); ?> 
                                                    - <?php echo date('d/m/Y H:i', strtotime($upload['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>Chưa có hoạt động nào gần đây</p>
                                        <a href="enhanced_upload.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Tạo nhiệm vụ đầu tiên
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-users me-2"></i>Labeler hoạt động
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($activeLabelers)): ?>
                                    <?php foreach ($activeLabelers as $labeler): ?>
                                    <div class="labeler-item">
                                        <div class="avatar">
                                            <?php echo strtoupper(substr($labeler['username'], 0, 1)); ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold"><?php echo htmlspecialchars($labeler['username']); ?></div>
                                            <small class="text-muted">
                                                <?php echo $labeler['active_tasks']; ?> nhiệm vụ đang làm
                                            </small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted">
                                        <i class="fas fa-user-slash fa-2x mb-2"></i>
                                        <p class="small">Không có labeler hoạt động</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- System Status -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-server me-2"></i>Trạng thái hệ thống
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small">Database</span>
                                    <span class="badge bg-success">Online</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small">Upload Service</span>
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small">Last Update</span>
                                    <span class="small text-muted"><?php echo date('H:i:s'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewPendingTasks() {
            window.location.href = 'reports.php?filter=pending';
        }

        // Auto refresh every 5 minutes
        setInterval(function() {
            console.log('Auto refresh stats...');
            // You can add AJAX call here to refresh stats without page reload
        }, 300000);
    </script>
</body>
</html>

<?php $conn->close(); ?>