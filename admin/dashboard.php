<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is admin
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get dashboard statistics
$stats = [];

// Document statistics
$query = "SELECT 
    COUNT(*) as total_documents,
    COUNT(CASE WHEN type = 'single' THEN 1 END) as single_documents,
    COUNT(CASE WHEN type = 'multi' THEN 1 END) as multi_documents
FROM documents";
$stmt = $db->prepare($query);
$stmt->execute();
$doc_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Group statistics
$query = "SELECT COUNT(*) as total_groups FROM document_groups";
$stmt = $db->prepare($query);
$stmt->execute();
$group_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Task statistics
$query = "SELECT 
    COUNT(*) as total_tasks,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_tasks,
    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tasks,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks
FROM labeling_tasks";
$stmt = $db->prepare($query);
$stmt->execute();
$task_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// User statistics
$query = "SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN role = 'labeler' THEN 1 END) as labelers,
    COUNT(CASE WHEN role = 'reviewer' THEN 1 END) as reviewers
FROM users WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent activities
$query = "SELECT al.*, u.full_name 
FROM activity_logs al
JOIN users u ON al.user_id = u.id
ORDER BY al.created_at DESC
LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent tasks
$query = "SELECT t.*, 
    COALESCE(d.title, dg.title) as document_title,
    u.full_name as assigned_to_name
FROM labeling_tasks t
LEFT JOIN documents d ON t.document_id = d.id
LEFT JOIN document_groups dg ON t.group_id = dg.id
LEFT JOIN users u ON t.assigned_to = u.id
ORDER BY t.assigned_at DESC
LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Text Labeling System</title>
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
            padding: 12px 20px;
            margin: 5px 0;
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
            border-left: 5px solid #667eea;
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
        }
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            height: 400px;
        }
        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .badge-status {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .navbar-brand {
            font-weight: 700;
            color: #667eea !important;
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
                    <nav class="nav flex-column">
                        <a href="dashboard.php" class="nav-link active">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="users.php" class="nav-link">
                            <i class="fas fa-users me-2"></i>Quản lý Users
                        </a>
                        <a href="upload.php" class="nav-link">
                            <i class="fas fa-upload me-2"></i>Upload Dữ liệu
                        </a>
                        <a href="documents.php" class="nav-link">
                            <i class="fas fa-file-text me-2"></i>Quản lý Văn bản
                        </a>
                        <a href="tasks.php" class="nav-link">
                            <i class="fas fa-tasks me-2"></i>Quản lý Tasks
                        </a>
                        <a href="reports.php" class="nav-link">
                            <i class="fas fa-chart-bar me-2"></i>Báo cáo
                        </a>
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                        <a href="../logout.php" class="nav-link text-warning">
                            <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
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
                            <h2>Dashboard</h2>
                            <p class="text-muted">Chào mừng trở lại, <?php echo isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['username']; ?>!</p>
                        </div>
                        <div class="text-muted">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo date('d/m/Y H:i'); ?>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="stats-number"><?php echo $doc_stats['total_documents']; ?></div>
                                        <div class="text-muted">Tổng văn bản</div>
                                    </div>
                                    <div class="text-primary">
                                        <i class="fas fa-file-text fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="stats-number"><?php echo $group_stats['total_groups']; ?></div>
                                        <div class="text-muted">Nhóm đa văn bản</div>
                                    </div>
                                    <div class="text-success">
                                        <i class="fas fa-copy fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="stats-number"><?php echo $task_stats['total_tasks']; ?></div>
                                        <div class="text-muted">Tổng tasks</div>
                                    </div>
                                    <div class="text-warning">
                                        <i class="fas fa-tasks fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="stats-number"><?php echo $user_stats['total_users'] - 1; ?></div>
                                        <div class="text-muted">Users hoạt động</div>
                                    </div>
                                    <div class="text-info">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts and Tables Row -->
                    <div class="row mb-4">
                        <!-- Task Status Chart -->
                        <div class="col-md-6">
                            <div class="chart-card">
                                <h5 class="mb-3">Trạng thái Tasks</h5>
                                <canvas id="taskStatusChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Document Types Chart -->
                        <div class="col-md-6">
                            <div class="chart-card">
                                <h5 class="mb-3">Phân loại văn bản</h5>
                                <canvas id="documentTypeChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Tables Row -->
                    <div class="row">
                        <!-- Recent Activities -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-history me-2"></i>Hoạt động gần đây
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($recent_activities)): ?>
                                        <?php foreach ($recent_activities as $activity): ?>
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($activity['full_name'] ?? 'Unknown'); ?></strong>
                                                        <span class="text-muted"><?php echo htmlspecialchars($activity['action']); ?></span>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('H:i d/m', strtotime($activity['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">Chưa có hoạt động nào</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Tasks -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-tasks me-2"></i>Tasks gần đây
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($recent_tasks)): ?>
                                        <?php foreach ($recent_tasks as $task): ?>
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($task['document_title'] ?? 'N/A'); ?></div>
                                                        <small class="text-muted">
                                                            Giao cho: <?php echo htmlspecialchars($task['assigned_to_name'] ?? 'Unknown'); ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge badge-status 
                                                            <?php 
                                                            echo $task['status'] === 'completed' ? 'bg-success' : 
                                                                 ($task['status'] === 'in_progress' ? 'bg-warning' : 'bg-secondary');
                                                            ?>">
                                                            <?php 
                                                            $status_text = [
                                                                'pending' => 'Chờ xử lý',
                                                                'in_progress' => 'Đang làm',
                                                                'completed' => 'Hoàn thành'
                                                            ];
                                                            echo $status_text[$task['status']] ?? $task['status'];
                                                            ?>
                                                        </span>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo date('d/m H:i', strtotime($task['assigned_at'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">Chưa có task nào</p>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.min.js"></script>
    <script>
        // Task Status Chart
        const taskCtx = document.getElementById('taskStatusChart').getContext('2d');
        new Chart(taskCtx, {
            type: 'doughnut',
            data: {
                labels: ['Chờ xử lý', 'Đang làm', 'Hoàn thành'],
                datasets: [{
                    data: [
                        <?php echo $task_stats['pending_tasks']; ?>,
                        <?php echo $task_stats['in_progress_tasks']; ?>,
                        <?php echo $task_stats['completed_tasks']; ?>
                    ],
                    backgroundColor: ['#6c757d', '#ffc107', '#28a745'],
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

        // Document Type Chart
        const docCtx = document.getElementById('documentTypeChart').getContext('2d');
        new Chart(docCtx, {
            type: 'bar',
            data: {
                labels: ['Văn bản đơn', 'Văn bản đa', 'Nhóm đa văn bản'],
                datasets: [{
                    label: 'Số lượng',
                    data: [
                        <?php echo $doc_stats['single_documents']; ?>,
                        <?php echo $doc_stats['multi_documents']; ?>,
                        <?php echo $group_stats['total_groups']; ?>
                    ],
                    backgroundColor: ['#667eea', '#764ba2', '#28a745'],
                    borderColor: ['#667eea', '#764ba2', '#28a745'],
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