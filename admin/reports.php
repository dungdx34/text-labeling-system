<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is admin
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get date range from request or default to last 30 days
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Overall Statistics
$stats = [];

// Documents statistics
$query = "SELECT 
    COUNT(*) as total_documents,
    COUNT(CASE WHEN type = 'single' THEN 1 END) as single_documents,
    COUNT(CASE WHEN type = 'multi' THEN 1 END) as multi_documents,
    COUNT(CASE WHEN created_at >= ? THEN 1 END) as documents_this_period
FROM documents";
$stmt = $db->prepare($query);
$stmt->execute([$start_date]);
$doc_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Groups statistics
$query = "SELECT COUNT(*) as total_groups,
    COUNT(CASE WHEN created_at >= ? THEN 1 END) as groups_this_period
FROM document_groups";
$stmt = $db->prepare($query);
$stmt->execute([$start_date]);
$group_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Tasks statistics
$query = "SELECT 
    COUNT(*) as total_tasks,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_tasks,
    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tasks,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks,
    COUNT(CASE WHEN assigned_at >= ? THEN 1 END) as tasks_this_period
FROM labeling_tasks";
$stmt = $db->prepare($query);
$stmt->execute([$start_date]);
$task_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// User statistics
$query = "SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN role = 'admin' THEN 1 END) as admins,
    COUNT(CASE WHEN role = 'labeler' THEN 1 END) as labelers,
    COUNT(CASE WHEN role = 'reviewer' THEN 1 END) as reviewers,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users
FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Top performing labelers
$query = "SELECT u.full_name, u.username,
    COUNT(t.id) as total_tasks,
    COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks,
    ROUND(COUNT(CASE WHEN t.status = 'completed' THEN 1 END) * 100.0 / COUNT(t.id), 1) as completion_rate
FROM users u
LEFT JOIN labeling_tasks t ON u.id = t.assigned_to
WHERE u.role = 'labeler' AND u.status = 'active'
GROUP BY u.id, u.full_name, u.username
HAVING COUNT(t.id) > 0
ORDER BY completion_rate DESC, completed_tasks DESC
LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$top_labelers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent activity
$query = "SELECT al.*, u.full_name, u.username
FROM activity_logs al
JOIN users u ON al.user_id = u.id
WHERE al.created_at >= ?
ORDER BY al.created_at DESC
LIMIT 20";
$stmt = $db->prepare($query);
$stmt->execute([$start_date]);
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Task completion over time (last 30 days)
$query = "SELECT DATE(completed_at) as date, COUNT(*) as completed_count
FROM labeling_tasks 
WHERE completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(completed_at)
ORDER BY date";
$stmt = $db->prepare($query);
$stmt->execute();
$completion_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Document upload trend (last 30 days)
$query = "SELECT DATE(created_at) as date, COUNT(*) as upload_count
FROM documents 
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date";
$stmt = $db->prepare($query);
$stmt->execute();
$upload_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }
        .main-content {
            padding: 30px;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 5px solid;
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .progress-custom {
            height: 8px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-4">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-tags me-2"></i>
                        Admin Panel
                    </h4>
                    <div class="nav flex-column">
                        <a href="dashboard.php" class="nav-link mb-2">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="users.php" class="nav-link mb-2">
                            <i class="fas fa-users me-2"></i>Quản lý Users
                        </a>
                        <a href="upload.php" class="nav-link mb-2">
                            <i class="fas fa-upload me-2"></i>Upload Dữ liệu
                        </a>
                        <a href="tasks.php" class="nav-link mb-2">
                            <i class="fas fa-tasks me-2"></i>Quản lý Tasks
                        </a>
                        <a href="reports.php" class="nav-link active mb-2">
                            <i class="fas fa-chart-bar me-2"></i>Báo cáo
                        </a>
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                        <a href="../logout.php" class="nav-link text-warning">
                            <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2>Báo cáo Hệ thống</h2>
                            <p class="text-muted">Thống kê và phân tích hiệu suất</p>
                        </div>
                        <div>
                            <form method="GET" class="d-flex">
                                <input type="date" class="form-control me-2" name="start_date" value="<?php echo $start_date; ?>">
                                <input type="date" class="form-control me-2" name="end_date" value="<?php echo $end_date; ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>Lọc
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Overview Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card" style="border-left-color: #007bff;">
                                <div class="stats-number text-primary"><?php echo $doc_stats['total_documents']; ?></div>
                                <div class="text-muted">Tổng văn bản</div>
                                <small class="text-success">
                                    +<?php echo $doc_stats['documents_this_period']; ?> trong kỳ
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card" style="border-left-color: #28a745;">
                                <div class="stats-number text-success"><?php echo $task_stats['completed_tasks']; ?></div>
                                <div class="text-muted">Tasks hoàn thành</div>
                                <small class="text-info">
                                    <?php echo $task_stats['pending_tasks']; ?> đang chờ
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card" style="border-left-color: #ffc107;">
                                <div class="stats-number text-warning"><?php echo $user_stats['active_users']; ?></div>
                                <div class="text-muted">Users hoạt động</div>
                                <small class="text-muted">
                                    <?php echo $user_stats['labelers']; ?> labelers, <?php echo $user_stats['reviewers']; ?> reviewers
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card" style="border-left-color: #17a2b8;">
                                <div class="stats-number text-info"><?php echo $group_stats['total_groups']; ?></div>
                                <div class="text-muted">Nhóm văn bản</div>
                                <small class="text-success">
                                    +<?php echo $group_stats['groups_this_period']; ?> trong kỳ
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row mb-4">
                        <!-- Task Completion Trend -->
                        <div class="col-md-6">
                            <div class="chart-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-line me-2"></i>Xu hướng hoàn thành Tasks (30 ngày)
                                </h5>
                                <canvas id="completionTrendChart" height="200"></canvas>
                            </div>
                        </div>
                        
                        <!-- Upload Trend -->
                        <div class="col-md-6">
                            <div class="chart-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-bar me-2"></i>Xu hướng Upload văn bản (30 ngày)
                                </h5>
                                <canvas id="uploadTrendChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Reports Row -->
                    <div class="row">
                        <!-- Top Labelers -->
                        <div class="col-md-6">
                            <div class="chart-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-trophy me-2"></i>Top Labelers
                                </h5>
                                <?php if (!empty($top_labelers)): ?>
                                    <?php foreach ($top_labelers as $index => $labeler): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <div class="fw-bold">
                                                    <?php if ($index < 3): ?>
                                                        <i class="fas fa-medal text-<?php echo ['warning', 'secondary', 'warning'][$index]; ?> me-2"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($labeler['full_name'] ?? $labeler['username']); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo $labeler['completed_tasks']; ?>/<?php echo $labeler['total_tasks']; ?> tasks
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-success"><?php echo $labeler['completion_rate']; ?>%</div>
                                                <div class="progress progress-custom" style="width: 100px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $labeler['completion_rate']; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">Chưa có dữ liệu labeler</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="col-md-6">
                            <div class="chart-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-history me-2"></i>Hoạt động gần đây
                                </h5>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php if (!empty($recent_activity)): ?>
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($activity['full_name'] ?? $activity['username']); ?></strong>
                                                        <span class="text-muted">
                                                            <?php 
                                                            $action_names = [
                                                                'login' => 'đăng nhập',
                                                                'logout' => 'đăng xuất',
                                                                'create_user' => 'tạo user',
                                                                'update_user' => 'cập nhật user',
                                                                'delete_user' => 'xóa user',
                                                                'create_task' => 'tạo task',
                                                                'update_task' => 'cập nhật task',
                                                                'upload_document' => 'upload tài liệu'
                                                            ];
                                                            echo $action_names[$activity['action']] ?? $activity['action'];
                                                            ?>
                                                        </span>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('H:i d/m', strtotime($activity['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">Không có hoạt động nào trong kỳ này</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="chart-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Tổng quan Hệ thống
                                </h5>
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <h6 class="text-muted">Loại văn bản</h6>
                                        <p>Đơn: <strong><?php echo $doc_stats['single_documents']; ?></strong></p>
                                        <p>Đa: <strong><?php echo $doc_stats['multi_documents']; ?></strong></p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h6 class="text-muted">Trạng thái Tasks</h6>
                                        <p>Chờ: <strong><?php echo $task_stats['pending_tasks']; ?></strong></p>
                                        <p>Đang làm: <strong><?php echo $task_stats['in_progress_tasks']; ?></strong></p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h6 class="text-muted">Phân quyền Users</h6>
                                        <p>Admin: <strong><?php echo $user_stats['admins']; ?></strong></p>
                                        <p>Labeler: <strong><?php echo $user_stats['labelers']; ?></strong></p>
                                        <p>Reviewer: <strong><?php echo $user_stats['reviewers']; ?></strong></p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h6 class="text-muted">Tỷ lệ hoàn thành</h6>
                                        <?php 
                                        $completion_rate = $task_stats['total_tasks'] > 0 ? 
                                            round($task_stats['completed_tasks'] * 100 / $task_stats['total_tasks'], 1) : 0;
                                        ?>
                                        <p><strong class="text-success"><?php echo $completion_rate; ?>%</strong></p>
                                        <div class="progress progress-custom">
                                            <div class="progress-bar bg-success" style="width: <?php echo $completion_rate; ?>%"></div>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.min.js"></script>
    <script>
        // Task Completion Trend Chart
        const completionData = <?php echo json_encode($completion_trend); ?>;
        const completionLabels = completionData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('vi-VN', { month: 'short', day: 'numeric' });
        });
        const completionValues = completionData.map(item => item.completed_count);

        const completionCtx = document.getElementById('completionTrendChart').getContext('2d');
        new Chart(completionCtx, {
            type: 'line',
            data: {
                labels: completionLabels,
                datasets: [{
                    label: 'Tasks hoàn thành',
                    data: completionValues,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Upload Trend Chart
        const uploadData = <?php echo json_encode($upload_trend); ?>;
        const uploadLabels = uploadData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('vi-VN', { month: 'short', day: 'numeric' });
        });
        const uploadValues = uploadData.map(item => item.upload_count);

        const uploadCtx = document.getElementById('uploadTrendChart').getContext('2d');
        new Chart(uploadCtx, {
            type: 'bar',
            data: {
                labels: uploadLabels,
                datasets: [{
                    label: 'Văn bản upload',
                    data: uploadValues,
                    backgroundColor: '#007bff',
                    borderColor: '#007bff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>