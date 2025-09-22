<?php
// Bắt lỗi và khởi tạo session an toàn
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../config/database.php';

// Kiểm tra quyền reviewer
requireRole('reviewer');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$error_message = '';

// Lấy thống kê cá nhân
$personal_stats = [];
try {
    $query = "SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as avg_rating,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'needs_revision' THEN 1 ELSE 0 END) as needs_revision,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
              FROM reviews WHERE reviewer_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user['id']]);
    $personal_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy thống kê cá nhân: ' . $e->getMessage();
}

// Thống kê theo thời gian (30 ngày gần đây)
$daily_stats = [];
try {
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as reviews_count,
                AVG(rating) as avg_rating
              FROM reviews 
              WHERE reviewer_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              GROUP BY DATE(created_at)
              ORDER BY date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user['id']]);
    $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $daily_stats = [];
}

// Thống kê theo labeler
$labeler_stats = [];
try {
    $query = "SELECT 
                u.full_name as labeler_name,
                COUNT(r.id) as total_reviews,
                AVG(r.rating) as avg_rating,
                SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected
              FROM reviews r
              JOIN assignments a ON r.assignment_id = a.id
              JOIN users u ON a.user_id = u.id
              WHERE r.reviewer_id = ?
              GROUP BY u.id, u.full_name
              HAVING COUNT(r.id) > 0
              ORDER BY total_reviews DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user['id']]);
    $labeler_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $labeler_stats = [];
}

// Thống kê theo loại văn bản
$document_type_stats = [];
try {
    $query = "SELECT 
                a.type as document_type,
                COUNT(r.id) as total_reviews,
                AVG(r.rating) as avg_rating,
                SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved
              FROM reviews r
              JOIN assignments a ON r.assignment_id = a.id
              WHERE r.reviewer_id = ?
              GROUP BY a.type";
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user['id']]);
    $document_type_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $document_type_stats = [];
}

// Thống kê theo phong cách văn bản
$writing_style_stats = [];
try {
    $query = "SELECT 
                lr.writing_style,
                COUNT(r.id) as total_reviews,
                AVG(r.rating) as avg_rating
              FROM reviews r
              JOIN assignments a ON r.assignment_id = a.id
              LEFT JOIN labeling_results lr ON a.id = lr.assignment_id
              WHERE r.reviewer_id = ? AND lr.writing_style IS NOT NULL
              GROUP BY lr.writing_style
              ORDER BY total_reviews DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user['id']]);
    $writing_style_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $writing_style_stats = [];
}

// Thống kê hệ thống (so sánh với reviewer khác)
$system_stats = [];
try {
    $query = "SELECT 
                COUNT(DISTINCT reviewer_id) as total_reviewers,
                AVG(rating) as system_avg_rating,
                COUNT(*) as total_system_reviews
              FROM reviews";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $system_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ranking của reviewer hiện tại
    $query = "SELECT reviewer_rank FROM (
                SELECT reviewer_id, 
                       ROW_NUMBER() OVER (ORDER BY COUNT(*) DESC) as reviewer_rank
                FROM reviews 
                GROUP BY reviewer_id
              ) ranking WHERE reviewer_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user['id']]);
    $rank_result = $stmt->fetch();
    $system_stats['my_rank'] = $rank_result['reviewer_rank'] ?? 0;
} catch (Exception $e) {
    $system_stats = ['total_reviewers' => 0, 'system_avg_rating' => 0, 'total_system_reviews' => 0, 'my_rank' => 0];
}

// Top assignments được review cao nhất
$top_assignments = [];
try {
    $query = "SELECT 
                r.rating,
                CASE 
                    WHEN a.type = 'single' THEN d.title 
                    WHEN a.type = 'multi' THEN dg.title 
                END as document_title,
                u.full_name as labeler_name,
                r.created_at
              FROM reviews r
              JOIN assignments a ON r.assignment_id = a.id
              LEFT JOIN documents d ON a.document_id = d.id AND a.type = 'single'
              LEFT JOIN document_groups dg ON a.group_id = dg.id AND a.type = 'multi'
              JOIN users u ON a.user_id = u.id
              WHERE r.reviewer_id = ?
              ORDER BY r.rating DESC, r.created_at DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user['id']]);
    $top_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top_assignments = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
        }
        .sidebar {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
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
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .progress-custom {
            height: 8px;
            border-radius: 4px;
        }
        .rating-stars {
            color: #ffc107;
        }
        .comparison-bar {
            background: #e9ecef;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        .comparison-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center text-white mb-4">
            <i class="fas fa-user-check fa-2x mb-2"></i>
            <h5>Reviewer Panel</h5>
            <small>Xin chào, <?php echo htmlspecialchars($current_user['full_name']); ?></small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="review.php">
                    <i class="fas fa-clipboard-check me-2"></i>Review công việc
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my_reviews.php">
                    <i class="fas fa-list-check me-2"></i>Reviews của tôi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="statistics.php">
                    <i class="fas fa-chart-line me-2"></i>Thống kê
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
            <h2 class="text-dark">Thống kê Review</h2>
            <div class="text-muted">
                <i class="fas fa-calendar me-1"></i>
                Cập nhật: <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Personal Statistics -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number text-primary"><?php echo $personal_stats['total_reviews'] ?? 0; ?></div>
                    <div class="stat-label">Tổng Reviews</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number text-warning">
                        <?php echo $personal_stats['avg_rating'] ? round($personal_stats['avg_rating'], 1) : 0; ?>
                    </div>
                    <div class="stat-label">Rating Trung Bình</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number text-success"><?php echo $personal_stats['approved'] ?? 0; ?></div>
                    <div class="stat-label">Đã Duyệt</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number text-info">#<?php echo $system_stats['my_rank'] ?? 'N/A'; ?></div>
                    <div class="stat-label">Xếp Hạng</div>
                </div>
            </div>
        </div>

        <!-- Review Status Distribution -->
        <div class="row">
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie me-2 text-primary"></i>
                        Phân bố trạng thái reviews
                    </h5>
                    <canvas id="statusChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Daily Activity Chart -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2 text-info"></i>
                        Hoạt động 30 ngày gần đây
                    </h5>
                    <canvas id="dailyChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Statistics -->
        <div class="row">
            <!-- Labeler Performance -->
            <div class="col-lg-6">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-users me-2 text-success"></i>
                        Thống kê theo Labeler
                    </h5>
                    <?php if (empty($labeler_stats)): ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p>Chưa có dữ liệu</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Labeler</th>
                                        <th>Reviews</th>
                                        <th>Rating TB</th>
                                        <th>Tỷ lệ duyệt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($labeler_stats as $labeler): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($labeler['labeler_name']); ?></td>
                                            <td><?php echo $labeler['total_reviews']; ?></td>
                                            <td>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= round($labeler['avg_rating']) ? '' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $approval_rate = $labeler['total_reviews'] > 0 ? 
                                                    round(($labeler['approved'] / $labeler['total_reviews']) * 100, 1) : 0;
                                                ?>
                                                <div class="comparison-bar">
                                                    <div class="comparison-fill" style="width: <?php echo $approval_rate; ?>%"></div>
                                                </div>
                                                <small><?php echo $approval_rate; ?>%</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Document Type & Writing Style Stats -->
            <div class="col-lg-6">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-file-text me-2 text-warning"></i>
                        Thống kê chi tiết
                    </h5>
                    
                    <!-- Document Type Stats -->
                    <h6 class="text-primary">Theo loại văn bản:</h6>
                    <?php if (!empty($document_type_stats)): ?>
                        <?php foreach ($document_type_stats as $type_stat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo $type_stat['document_type'] == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?></span>
                                <div>
                                    <span class="badge bg-primary"><?php echo $type_stat['total_reviews']; ?> reviews</span>
                                    <span class="badge bg-warning"><?php echo round($type_stat['avg_rating'], 1); ?> ⭐</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Chưa có dữ liệu</p>
                    <?php endif; ?>

                    <hr>

                    <!-- Writing Style Stats -->
                    <h6 class="text-success">Theo phong cách văn bản:</h6>
                    <?php if (!empty($writing_style_stats)): ?>
                        <?php foreach ($writing_style_stats as $style_stat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo ucfirst($style_stat['writing_style']); ?></span>
                                <div>
                                    <span class="badge bg-info"><?php echo $style_stat['total_reviews']; ?></span>
                                    <span class="badge bg-warning"><?php echo round($style_stat['avg_rating'], 1); ?> ⭐</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Chưa có dữ liệu</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Assignments -->
        <div class="row">
            <div class="col-lg-8">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-trophy me-2 text-warning"></i>
                        Top assignments được đánh giá cao
                    </h5>
                    <?php if (empty($top_assignments)): ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p>Chưa có assignment nào được review</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Văn bản</th>
                                        <th>Labeler</th>
                                        <th>Rating</th>
                                        <th>Ngày review</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_assignments as $assignment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars(substr($assignment['document_title'], 0, 40)); ?></strong>
                                                <?php if (strlen($assignment['document_title']) > 40): ?>...<?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($assignment['labeler_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $assignment['rating'] ? '' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?php echo date('d/m/Y', strtotime($assignment['created_at'])); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Comparison -->
            <div class="col-lg-4">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-bar me-2 text-danger"></i>
                        So sánh hệ thống
                    </h5>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Rating của bạn:</span>
                            <strong class="text-primary"><?php echo $personal_stats['avg_rating'] ? round($personal_stats['avg_rating'], 1) : 0; ?></strong>
                        </div>
                        <div class="progress progress-custom mt-1">
                            <div class="progress-bar bg-primary" style="width: <?php echo ($personal_stats['avg_rating'] ?? 0) * 20; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Rating TB hệ thống:</span>
                            <strong class="text-secondary"><?php echo round($system_stats['system_avg_rating'], 1); ?></strong>
                        </div>
                        <div class="progress progress-custom mt-1">
                            <div class="progress-bar bg-secondary" style="width: <?php echo $system_stats['system_avg_rating'] * 20; ?>%"></div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="text-center">
                        <h6>Thứ hạng của bạn</h6>
                        <div class="display-4 text-warning">#<?php echo $system_stats['my_rank'] ?? 'N/A'; ?></div>
                        <small class="text-muted">trong <?php echo $system_stats['total_reviewers']; ?> reviewers</small>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Xếp hạng dựa trên số lượng reviews
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Đã duyệt', 'Từ chối', 'Cần sửa', 'Chờ xử lý'],
                datasets: [{
                    data: [
                        <?php echo $personal_stats['approved'] ?? 0; ?>,
                        <?php echo $personal_stats['rejected'] ?? 0; ?>,
                        <?php echo $personal_stats['needs_revision'] ?? 0; ?>,
                        <?php echo $personal_stats['pending'] ?? 0; ?>
                    ],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#6c757d']
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

        // Daily Activity Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    for ($i = 29; $i >= 0; $i--) {
                        $date = date('m/d', strtotime("-$i days"));
                        echo "'$date',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Reviews per day',
                    data: [
                        <?php 
                        for ($i = 29; $i >= 0; $i--) {
                            $date = date('Y-m-d', strtotime("-$i days"));
                            $count = 0;
                            foreach ($daily_stats as $stat) {
                                if ($stat['date'] == $date) {
                                    $count = $stat['reviews_count'];
                                    break;
                                }
                            }
                            echo "$count,";
                        }
                        ?>
                    ],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>