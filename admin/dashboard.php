<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Kiểm tra quyền admin
requireRole('admin');

$database = new Database();
$db = $database->getConnection();

// Lấy thống kê
try {
    // Tổng số users
    $query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Tổng số documents
    $query = "SELECT COUNT(*) as total FROM documents";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_documents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Tổng số assignments
    $query = "SELECT COUNT(*) as total FROM assignments";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_assignments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Assignments hoàn thành
    $query = "SELECT COUNT(*) as total FROM assignments WHERE status = 'completed'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $completed_assignments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Hoạt động gần đây
    $query = "SELECT a.*, u.full_name, d.title 
              FROM assignments a 
              JOIN users u ON a.user_id = u.id 
              JOIN documents d ON a.document_id = d.id 
              ORDER BY a.updated_at DESC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Lỗi database: " . $e->getMessage();
}

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            padding: 20px 0;
            z-index: 1000;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center text-white mb-4">
            <i class="fas fa-tags fa-2x mb-2"></i>
            <h5>Admin Panel</h5>
            <small>Xin chào, <?php echo htmlspecialchars($current_user['full_name']); ?></small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-2"></i>Quản lý người dùng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="upload.php">
                    <i class="fas fa-upload me-2"></i>Upload văn bản
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="documents.php">
                    <i class="fas fa-file-text me-2"></i>Quản lý văn bản
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="assignments.php">
                    <i class="fas fa-tasks me-2"></i>Phân công công việc
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Báo cáo
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark">Dashboard</h2>
            <div class="text-muted">
                <i class="fas fa-calendar me-1"></i>
                <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="ms-3">
                            <div class="h4 mb-0"><?php echo $total_users; ?></div>
                            <div class="text-muted">Người dùng</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #6610f2);">
                            <i class="fas fa-file-text"></i>
                        </div>
                        <div class="ms-3">
                            <div class="h4 mb-0"><?php echo $total_documents; ?></div>
                            <div class="text-muted">Văn bản</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="ms-3">
                            <div class="h4 mb-0"><?php echo $total_assignments; ?></div>
                            <div class="text-muted">Tổng công việc</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #e83e8c);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="ms-3">
                            <div class="h4 mb-0"><?php echo $completed_assignments; ?></div>
                            <div class="text-muted">Hoàn thành</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Progress Chart -->
            <div class="col-lg-8 mb-4">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2 text-primary"></i>
                        Tiến độ công việc
                    </h5>
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="progressChart" width="400" height="200"></canvas>
                        </div>
                        <div class="col-md-6">
                            <div class="h-100 d-flex flex-column justify-content-center">
                                <?php 
                                $completion_rate = $total_assignments > 0 ? round(($completed_assignments / $total_assignments) * 100, 1) : 0;
                                ?>
                                <div class="text-center">
                                    <div class="h1 text-primary"><?php echo $completion_rate; ?>%</div>
                                    <div class="text-muted">Tỷ lệ hoàn thành</div>
                                </div>
                                <div class="progress mt-3" style="height: 10px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $completion_rate; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="col-lg-4 mb-4">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-clock me-2 text-info"></i>
                        Hoạt động gần đây
                    </h5>
                    <div class="activities-list">
                        <?php if (empty($recent_activities)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>Chưa có hoạt động nào</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item d-flex align-items-center mb-3 p-2 rounded bg-light">
                                    <div class="activity-icon me-3">
                                        <?php 
                                        $icon_class = '';
                                        $bg_class = '';
                                        switch ($activity['status']) {
                                            case 'completed':
                                                $icon_class = 'fas fa-check-circle';
                                                $bg_class = 'bg-success';
                                                break;
                                            case 'in_progress':
                                                $icon_class = 'fas fa-clock';
                                                $bg_class = 'bg-warning';
                                                break;
                                            default:
                                                $icon_class = 'fas fa-file';
                                                $bg_class = 'bg-secondary';
                                        }
                                        ?>
                                        <div class="rounded-circle p-2 text-white <?php echo $bg_class; ?>">
                                            <i class="<?php echo $icon_class; ?>"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo htmlspecialchars($activity['full_name']); ?></div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars(substr($activity['title'], 0, 30)); ?>...
                                        </small>
                                        <div class="small text-muted">
                                            <?php echo date('d/m H:i', strtotime($activity['updated_at'])); ?>
                                        </div>
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
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-rocket me-2 text-warning"></i>
                        Thao tác nhanh
                    </h5>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="users.php" class="btn btn-outline-primary w-100 p-3">
                                <i class="fas fa-user-plus fa-2x mb-2"></i>
                                <div>Thêm người dùng</div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="upload.php" class="btn btn-outline-success w-100 p-3">
                                <i class="fas fa-upload fa-2x mb-2"></i>
                                <div>Upload văn bản</div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="assignments.php" class="btn btn-outline-warning w-100 p-3">
                                <i class="fas fa-tasks fa-2x mb-2"></i>
                                <div>Phân công việc</div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="reports.php" class="btn btn-outline-info w-100 p-3">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                <div>Xem báo cáo</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Progress Chart
        const ctx = document.getElementById('progressChart').getContext('2d');
        const progressChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Hoàn thành', 'Chưa hoàn thành'],
                datasets: [{
                    data: [<?php echo $completed_assignments; ?>, <?php echo $total_assignments - $completed_assignments; ?>],
                    backgroundColor: ['#28a745', '#dc3545'],
                    borderWidth: 0
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
    </script>
</body>
</html>