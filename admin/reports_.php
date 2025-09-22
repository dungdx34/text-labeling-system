<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Kiểm tra quyền admin
requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

// Lấy thống kê tổng quan
try {
    // Tổng số người dùng theo role
    $query = "SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $user_stats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $user_stats[$row['role']] = $row['count'];
    }
    
    // Thống kê văn bản
    $query = "SELECT 
                COUNT(*) as total_documents,
                SUM(CASE WHEN type = 'single' THEN 1 ELSE 0 END) as single_docs,
                SUM(CASE WHEN type = 'multi' THEN 1 ELSE 0 END) as multi_docs
              FROM documents WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $doc_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Thống kê assignments
    $query = "SELECT 
                status,
                COUNT(*) as count
              FROM assignments 
              GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $assignment_stats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $assignment_stats[$row['status']] = $row['count'];
    }
    
    // Hiệu suất người gán nhãn
    $query = "SELECT 
                u.full_name,
                COUNT(a.id) as total_assignments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                ROUND(AVG(CASE WHEN a.status = 'completed' THEN 
                    TIMESTAMPDIFF(HOUR, a.created_at, a.updated_at) 
                ELSE NULL END), 2) as avg_completion_hours
              FROM users u
              LEFT JOIN assignments a ON u.id = a.user_id
              WHERE u.role = 'labeler' AND u.status = 'active'
              GROUP BY u.id, u.full_name
              ORDER BY completed DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $labeler_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Thống kê theo thời gian (7 ngày gần đây)
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as assignments_created
              FROM assignments 
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY DATE(created_at)
              ORDER BY date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top văn bản được gán nhiều nhất
    $query = "SELECT 
                d.title,
                COUNT(a.id) as assignment_count
              FROM documents d
              JOIN assignments a ON d.id = a.document_id
              WHERE d.status = 'active'
              GROUP BY d.id, d.title
              ORDER BY assignment_count DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $top_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Thống kê reviews
    $query = "SELECT 
                status,
                COUNT(*) as count,
                AVG(rating) as avg_rating
              FROM reviews 
              GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $review_stats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $review_stats[$row['status']] = $row;
    }
    
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy thống kê: ' . $e->getMessage();
}
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
            margin-bottom: 20px;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .metric-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .metric-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
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
                <a class="nav-link" href="dashboard.php">
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
                <a class="nav-link active" href="reports.php">
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
            <h2 class="text-dark">Báo cáo & Thống kê</h2>
            <div class="text-muted">
                <i class="fas fa-calendar me-1"></i>
                Cập nhật: <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>

        <!-- Overview Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="metric-card bg-primary text-white">
                    <div class="metric-number"><?php echo $user_stats['admin'] ?? 0; ?></div>
                    <div class="metric-label">Admin</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card bg-success text-white">
                    <div class="metric-number"><?php echo $user_stats['labeler'] ?? 0; ?></div>
                    <div class="metric-label">Labeler</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card bg-warning text-white">
                    <div class="metric-number"><?php echo $user_stats['reviewer'] ?? 0; ?></div>
                    <div class="metric-label">Reviewer</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card bg-info text-white">
                    <div class="metric-number"><?php echo $doc_stats['total_documents']; ?></div>
                    <div class="metric-label">Tổng văn bản</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Assignment Status Chart -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-tasks me-2 text-primary"></i>
                        Trạng thái assignments
                    </h5>
                    <canvas id="assignmentStatusChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Document Type Chart -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-file-text me-2 text-success"></i>
                        Loại văn bản
                    </h5>
                    <canvas id="documentTypeChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Labeler Performance -->
            <div class="col-lg-8">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-user-edit me-2 text-info"></i>
                        Hiệu suất Labeler
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tên</th>
                                    <th>Tổng assignments</th>
                                    <th>Hoàn thành</th>
                                    <th>Tỷ lệ (%)</th>
                                    <th>Thời gian TB (giờ)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($labeler_performance as $labeler): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($labeler['full_name']); ?></td>
                                        <td><?php echo $labeler['total_assignments']; ?></td>
                                        <td><?php echo $labeler['completed']; ?></td>
                                        <td>
                                            <?php 
                                            $rate = $labeler['total_assignments'] > 0 ? 
                                                round(($labeler['completed'] / $labeler['total_assignments']) * 100, 1) : 0;
                                            echo $rate; 
                                            ?>%
                                        </td>
                                        <td>
                                            <?php echo $labeler['avg_completion_hours'] ?? 'N/A'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Top Documents -->
            <div class="col-lg-4">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-trophy me-2 text-warning"></i>
                        Top văn bản
                    </h5>
                    <?php if (empty($top_documents)): ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p>Chưa có dữ liệu</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($top_documents as $doc): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars(substr($doc['title'], 0, 30)); ?>...</strong>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?php echo $doc['assignment_count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Daily Activity Chart -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2 text-danger"></i>
                        Hoạt động 7 ngày gần đây
                    </h5>
                    <canvas id="dailyActivityChart" width="400" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Export Section -->
        <div class="stat-card">
            <h5 class="mb-3">
                <i class="fas fa-download me-2 text-secondary"></i>
                Xuất báo cáo
            </h5>
            <div class="row">
                <div class="col-md-3">
                    <button class="btn btn-outline-primary w-100" onclick="exportReport('users')">
                        <i class="fas fa-users me-2"></i>Báo cáo người dùng
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-success w-100" onclick="exportReport('assignments')">
                        <i class="fas fa-tasks me-2"></i>Báo cáo assignments
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-info w-100" onclick="exportReport('performance')">
                        <i class="fas fa-chart-bar me-2"></i>Báo cáo hiệu suất
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-warning w-100" onclick="exportReport('summary')">
                        <i class="fas fa-file-pdf me-2"></i>Tổng hợp
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Assignment Status Chart
        const assignmentCtx = document.getElementById('assignmentStatusChart').getContext('2d');
        new Chart(assignmentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Chờ thực hiện', 'Đang thực hiện', 'Hoàn thành', 'Đã review'],
                datasets: [{
                    data: [
                        <?php echo $assignment_stats['pending'] ?? 0; ?>,
                        <?php echo $assignment_stats['in_progress'] ?? 0; ?>,
                        <?php echo $assignment_stats['completed'] ?? 0; ?>,
                        <?php echo $assignment_stats['reviewed'] ?? 0; ?>
                    ],
                    backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#6c757d']
                }]
            },
            options: {
                responsive: true,
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
                labels: ['Đơn văn bản', 'Đa văn bản'],
                datasets: [{
                    label: 'Số lượng',
                    data: [
                        <?php echo $doc_stats['single_docs']; ?>,
                        <?php echo $doc_stats['multi_docs']; ?>
                    ],
                    backgroundColor: ['#007bff', '#28a745']
                }]
            },
            options: {
                responsive: true,
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

        // Daily Activity Chart
        const dailyCtx = document.getElementById('dailyActivityChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    foreach ($daily_stats as $stat) {
                        echo "'" . date('d/m', strtotime($stat['date'])) . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Assignments được tạo',
                    data: [
                        <?php 
                        foreach ($daily_stats as $stat) {
                            echo $stat['assignments_created'] . ',';
                        }
                        ?>
                    ],
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        function exportReport(type) {
            alert('Tính năng xuất báo cáo ' + type + ' sẽ được phát triển trong phiên bản tiếp theo.');
        }
    </script>
</body>
</html>