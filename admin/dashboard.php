<?php
// admin/dashboard.php - Fixed Version
require_once '../config/database.php';
require_once '../includes/auth.php';

// Use correct Auth class method
Auth::requireLogin('admin');

$database = new Database();
$pdo = $database->getConnection();

// Get dashboard statistics
try {
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
        'total_documents' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
        'pending_tasks' => $pdo->query("SELECT COUNT(*) FROM labeling_tasks WHERE status = 'pending'")->fetchColumn() ?: 0,
        'completed_tasks' => $pdo->query("SELECT COUNT(*) FROM labeling_tasks WHERE status = 'completed'")->fetchColumn() ?: 0,
        'single_documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE type = 'single'")->fetchColumn(),
        'multi_documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE type = 'multi'")->fetchColumn(),
        'document_groups' => $pdo->query("SELECT COUNT(*) FROM document_groups")->fetchColumn() ?: 0
    ];
    
    // Recent activities (if activity_logs table exists)
    $recent_activities = [];
    $table_exists = $pdo->query("SHOW TABLES LIKE 'activity_logs'")->rowCount();
    if ($table_exists) {
        $recent_activities = $pdo->query("
            SELECT al.*, u.username, u.full_name 
            FROM activity_logs al 
            JOIN users u ON al.user_id = u.id 
            ORDER BY al.created_at DESC 
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Recent uploads
    $recent_uploads = [];
    $upload_table_exists = $pdo->query("SHOW TABLES LIKE 'upload_logs'")->rowCount();
    if ($upload_table_exists) {
        $recent_uploads = $pdo->query("
            SELECT ul.*, u.username 
            FROM upload_logs ul 
            JOIN users u ON ul.uploaded_by = u.id 
            ORDER BY ul.upload_date DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $stats = array_fill_keys(['total_users', 'total_documents', 'pending_tasks', 'completed_tasks', 'single_documents', 'multi_documents', 'document_groups'], 0);
    $recent_activities = [];
    $recent_uploads = [];
}

$current_user = Auth::getCurrentUser();
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
        .stats-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .activity-item {
            border-left: 3px solid #007bff;
            margin-bottom: 15px;
            padding-left: 15px;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: transform 0.2s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark gradient-bg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tags me-2"></i>Text Labeling System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-1"></i>Quản lý Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload.php">
                            <i class="fas fa-upload me-1"></i>Upload Văn bản
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload_jsonl.php">
                            <i class="fas fa-file-code me-1"></i>Upload JSONL
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-1"></i>Báo cáo
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($current_user['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard Quản Trị
                </h2>
                <p class="text-muted">Chào mừng trở lại, <?php echo htmlspecialchars($current_user['username']); ?>!</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-light bg-opacity-25 me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <div class="h3 mb-0"><?php echo number_format($stats['total_users']); ?></div>
                                <div class="small">Tổng Users</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-light bg-opacity-25 me-3">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div>
                                <div class="h3 mb-0"><?php echo number_format($stats['total_documents']); ?></div>
                                <div class="small">Tổng Văn bản</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-light bg-opacity-25 me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <div class="h3 mb-0"><?php echo number_format($stats['pending_tasks']); ?></div>
                                <div class="small">Tasks Đang chờ</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-light bg-opacity-25 me-3">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <div class="h3 mb-0"><?php echo number_format($stats['completed_tasks']); ?></div>
                                <div class="small">Tasks Hoàn thành</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Statistics -->
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card card-hover">
                    <div class="card-body text-center">
                        <i class="fas fa-file-text text-primary fa-3x mb-3"></i>
                        <h4 class="text-primary"><?php echo number_format($stats['single_documents']); ?></h4>
                        <p class="text-muted mb-0">Đơn Văn bản</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card card-hover">
                    <div class="card-body text-center">
                        <i class="fas fa-copy text-success fa-3x mb-3"></i>
                        <h4 class="text-success"><?php echo number_format($stats['multi_documents']); ?></h4>
                        <p class="text-muted mb-0">Đa Văn bản</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card card-hover">
                    <div class="card-body text-center">
                        <i class="fas fa-layer-group text-info fa-3x mb-3"></i>
                        <h4 class="text-info"><?php echo number_format($stats['document_groups']); ?></h4>
                        <p class="text-muted mb-0">Nhóm Văn bản</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Activities -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Hoạt Động Gần Đây
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($recent_activities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-info-circle text-muted fa-2x mb-3"></i>
                                <p class="text-muted">Chưa có hoạt động nào được ghi lại</p>
                                <small class="text-muted">Các hoạt động sẽ xuất hiện sau khi bạn sử dụng hệ thống</small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                            <span class="badge bg-<?php echo $activity['activity_type'] === 'login' ? 'success' : 'primary'; ?> ms-2">
                                                <?php echo ucfirst($activity['activity_type']); ?>
                                            </span>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($activity['description']); ?></p>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Uploads -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-upload me-2"></i>Upload Gần Đây
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_uploads)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-cloud-upload-alt text-muted fa-2x mb-3"></i>
                                <p class="text-muted">Chưa có upload nào</p>
                                <a href="upload_jsonl.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-1"></i>Upload JSONL
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_uploads as $upload): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="small fw-bold">
                                                <i class="fas fa-file me-1"></i>
                                                <?php echo htmlspecialchars(basename($upload['file_name'])); ?>
                                            </div>
                                            <div class="text-muted small">
                                                By: <?php echo htmlspecialchars($upload['username']); ?><br>
                                                <?php echo date('d/m/Y H:i', strtotime($upload['upload_date'])); ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-<?php echo $upload['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($upload['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Thao Tác Nhanh
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="users.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i>Quản lý Users
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="upload.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-upload me-2"></i>Upload Văn Bản
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="upload_jsonl.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-file-code me-2"></i>Upload JSONL
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="reports.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-chart-line me-2"></i>Xem Báo Cáo
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh stats every 5 minutes
        setInterval(function() {
            // Only refresh if user is still active
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000); // 5 minutes

        // Add loading animation to quick action buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function() {
                const icon = this.querySelector('i');
                const originalClass = icon.className;
                icon.className = 'fas fa-spinner fa-spin me-2';
                
                setTimeout(() => {
                    icon.className = originalClass;
                }, 1000);
            });
        });
    </script>
</body>
</html>