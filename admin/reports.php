<?php
// admin/reports.php - Fixed Reports & Analytics
require_once '../config/database.php';
require_once '../includes/auth.php';

Auth::requireLogin('admin');

$database = new Database();
$pdo = $database->getConnection();

// Get comprehensive statistics
try {
    // Basic stats
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
        'total_documents' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
        'single_documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE type = 'single'")->fetchColumn(),
        'multi_documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE type = 'multi'")->fetchColumn(),
        'document_groups' => $pdo->query("SELECT COUNT(*) FROM document_groups")->fetchColumn() ?: 0,
        'ai_summaries' => $pdo->query("SELECT COUNT(*) FROM ai_summaries")->fetchColumn() ?: 0,
        'auto_generated_titles' => 0,
    ];

    // Check for auto-generated titles if column exists
    try {
        $auto_titles = $pdo->query("SELECT COUNT(*) FROM documents WHERE is_auto_generated_title = TRUE")->fetchColumn();
        $stats['auto_generated_titles'] = $auto_titles ?: 0;
    } catch (Exception $e) {
        // Column doesn't exist, ignore
    }

    // Task statistics (if table exists)
    $task_stats = ['pending' => 0, 'completed' => 0, 'in_progress' => 0];
    try {
        $result = $pdo->query("SELECT status, COUNT(*) as count FROM labeling_tasks GROUP BY status");
        while ($row = $result->fetch()) {
            $task_stats[$row['status']] = $row['count'];
        }
    } catch (Exception $e) {
        // Table doesn't exist
    }

    // Upload statistics (if table exists)  
    $upload_stats = [];
    try {
        $upload_stats = $pdo->query("
            SELECT 
                DATE(upload_date) as date,
                COUNT(*) as uploads,
                SUM(records_success) as total_success,
                SUM(records_failed) as total_failed,
                SUM(auto_generated_titles) as auto_titles
            FROM upload_logs 
            WHERE upload_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(upload_date) 
            ORDER BY date DESC 
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table doesn't exist
    }

    // User activity (if table exists)
    $user_activity = [];
    try {
        $user_activity = $pdo->query("
            SELECT 
                u.username, 
                u.role,
                COUNT(al.id) as activities,
                MAX(al.created_at) as last_activity
            FROM users u 
            LEFT JOIN activity_logs al ON u.id = al.user_id 
            WHERE u.status = 'active'
            GROUP BY u.id 
            ORDER BY activities DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table doesn't exist
    }

    // Document size statistics
    $document_stats = [];
    try {
        $document_stats = $pdo->query("
            SELECT 
                type,
                COUNT(*) as count,
                AVG(word_count) as avg_words,
                AVG(char_count) as avg_chars,
                MAX(word_count) as max_words,
                MIN(word_count) as min_words
            FROM documents 
            WHERE word_count > 0
            GROUP BY type
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Columns don't exist
    }

} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
    $stats = array_fill_keys(['total_users', 'total_documents', 'single_documents', 'multi_documents', 'document_groups', 'ai_summaries', 'auto_generated_titles'], 0);
    $task_stats = ['pending' => 0, 'completed' => 0, 'in_progress' => 0];
    $upload_stats = [];
    $user_activity = [];
    $document_stats = [];
}

$current_user = Auth::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo & Thống kê - Text Labeling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stats-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .metric-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 15px;
        }
        .progress-custom {
            height: 8px;
            border-radius: 10px;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark gradient-bg">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tags me-2"></i>Text Labeling System
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-1"></i>Users
                </a>
                <a class="nav-link" href="upload_jsonl.php">
                    <i class="fas fa-file-code me-1"></i>Upload JSONL
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-primary mb-3">
                    <i class="fas fa-chart-bar me-2"></i>Báo cáo & Thống kê
                </h2>
                <p class="text-muted">Tổng quan hiệu suất và hoạt động của hệ thống</p>
            </div>
        </div>

        <!-- Main Statistics -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h3 class="mb-1"><?php echo number_format($stats['total_users']); ?></h3>
                        <small>Users Hoạt động</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-file-alt fa-2x mb-2"></i>
                        <h3 class="mb-1"><?php echo number_format($stats['total_documents']); ?></h3>
                        <small>Tổng Văn bản</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-file-text fa-2x mb-2"></i>
                        <h3 class="mb-1"><?php echo number_format($stats['single_documents']); ?></h3>
                        <small>Đơn Văn bản</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-copy fa-2x mb-2"></i>
                        <h3 class="mb-1"><?php echo number_format($stats['multi_documents']); ?></h3>
                        <small>Đa Văn bản</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card bg-secondary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-robot fa-2x mb-2"></i>
                        <h3 class="mb-1"><?php echo number_format($stats['ai_summaries']); ?></h3>
                        <small>AI Summaries</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card bg-danger text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-magic fa-2x mb-2"></i>
                        <h3 class="mb-1"><?php echo number_format($stats['auto_generated_titles']); ?></h3>
                        <small>Auto Titles</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Document Type Distribution -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>Phân bố loại văn bản
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="documentTypeChart"></canvas>
                        </div>
                        <div class="row mt-3">
                            <div class="col-6">
                                <div class="metric-box">
                                    <div class="h4 text-info"><?php echo number_format($stats['single_documents']); ?></div>
                                    <small>Đơn văn bản</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-box">
                                    <div class="h4 text-warning"><?php echo number_format($stats['multi_documents']); ?></div>
                                    <small>Đa văn bản</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task Status (if available) -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-tasks me-2"></i>Trạng thái công việc
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (array_sum($task_stats) > 0): ?>
                            <div class="chart-container">
                                <canvas id="taskStatusChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-info-circle text-muted fa-3x mb-3"></i>
                                <h5>Chưa có công việc nào</h5>
                                <p class="text-muted">Tasks sẽ xuất hiện khi bắt đầu assign công việc</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Statistics -->
        <?php if (!empty($upload_stats)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-upload me-2"></i>Thống kê Upload (30 ngày gần đây)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Ngày</th>
                                        <th>Số lần upload</th>
                                        <th>Thành công</th>
                                        <th>Thất bại</th>
                                        <th>Auto Titles</th>
                                        <th>Tỷ lệ thành công</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upload_stats as $upload): ?>
                                        <?php 
                                        $total = $upload['total_success'] + $upload['total_failed'];
                                        $success_rate = $total > 0 ? ($upload['total_success'] / $total * 100) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($upload['date'])); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $upload['uploads']; ?></span></td>
                                            <td><span class="badge bg-success"><?php echo number_format($upload['total_success']); ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo number_format($upload['total_failed']); ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo number_format($upload['auto_titles']); ?></span></td>
                                            <td>
                                                <div class="progress progress-custom">
                                                    <div class="progress-bar bg-<?php echo $success_rate >= 90 ? 'success' : ($success_rate >= 70 ? 'warning' : 'danger'); ?>" 
                                                         style="width: <?php echo $success_rate; ?>%"></div>
                                                </div>
                                                <small><?php echo number_format($success_rate, 1); ?>%</small>
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

        <!-- User Activity -->
        <?php if (!empty($user_activity)): ?>
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-user-friends me-2"></i>Hoạt động người dùng
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tên đăng nhập</th>
                                        <th>Vai trò</th>
                                        <th>Số hoạt động</th>
                                        <th>Hoạt động cuối</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_activity as $user): ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($user['username']); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $user['role'] === 'admin' ? 'danger' : 
                                                        ($user['role'] === 'reviewer' ? 'info' : 'success'); 
                                                ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($user['activities']); ?></td>
                                            <td>
                                                <?php if ($user['last_activity']): ?>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($user['last_activity'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">Chưa có hoạt động</small>
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

            <!-- Document Statistics -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>Thống kê văn bản
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($document_stats)): ?>
                            <?php foreach ($document_stats as $stat): ?>
                                <div class="metric-box">
                                    <h6 class="text-primary">
                                        <?php echo ucfirst($stat['type']); ?> Documents
                                    </h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="small text-muted">Số lượng:</div>
                                            <div class="fw-bold"><?php echo number_format($stat['count']); ?></div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small text-muted">TB từ:</div>
                                            <div class="fw-bold"><?php echo number_format($stat['avg_words']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-chart-bar text-muted fa-2x mb-2"></i>
                                <p class="text-muted">Chưa có dữ liệu thống kê</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Export Options -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-download me-2"></i>Xuất báo cáo
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-success w-100" onclick="exportData('csv')">
                                    <i class="fas fa-file-csv me-2"></i>Xuất CSV
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-danger w-100" onclick="exportData('pdf')">
                                    <i class="fas fa-file-pdf me-2"></i>Xuất PDF
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-primary w-100" onclick="window.print()">
                                    <i class="fas fa-print me-2"></i>In báo cáo
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-info w-100" onclick="location.reload()">
                                    <i class="fas fa-sync me-2"></i>Làm mới
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Document Type Chart
        const documentTypeCtx = document.getElementById('documentTypeChart').getContext('2d');
        new Chart(documentTypeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Đơn văn bản', 'Đa văn bản'],
                datasets: [{
                    data: [<?php echo $stats['single_documents']; ?>, <?php echo $stats['multi_documents']; ?>],
                    backgroundColor: ['#17a2b8', '#ffc107'],
                    borderWidth: 2
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

        <?php if (array_sum($task_stats) > 0): ?>
        // Task Status Chart
        const taskStatusCtx = document.getElementById('taskStatusChart').getContext('2d');
        new Chart(taskStatusCtx, {
            type: 'bar',
            data: {
                labels: ['Đang chờ', 'Đang làm', 'Hoàn thành'],
                datasets: [{
                    label: 'Số lượng',
                    data: [<?php echo $task_stats['pending']; ?>, <?php echo $task_stats['in_progress']; ?>, <?php echo $task_stats['completed']; ?>],
                    backgroundColor: ['#ffc107', '#fd7e14', '#28a745']
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
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>

        // Export functions
        function exportData(type) {
            alert(`Tính năng xuất ${type.toUpperCase()} đang được phát triển!`);
        }

        // Auto refresh every 5 minutes
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000);
    </script>
</body>
</html>