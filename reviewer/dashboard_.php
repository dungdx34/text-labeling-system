<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Kiểm tra quyền reviewer
requireRole('reviewer');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

// Lấy thông tin công việc của reviewer
try {
    // Assignments cần review (đã hoàn thành nhưng chưa được review)
    $query = "SELECT COUNT(*) as total FROM assignments WHERE status = 'completed'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $available_for_review = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Tổng số reviews đã thực hiện
    $query = "SELECT COUNT(*) as total FROM reviews WHERE reviewer_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $current_user['id']);
    $stmt->execute();
    $total_reviews = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Reviews đã approved
    $query = "SELECT COUNT(*) as total FROM reviews WHERE reviewer_id = ? AND status = 'approved'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $current_user['id']);
    $stmt->execute();
    $approved_reviews = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Reviews bị rejected
    $query = "SELECT COUNT(*) as total FROM reviews WHERE reviewer_id = ? AND status = 'rejected'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $current_user['id']);
    $stmt->execute();
    $rejected_reviews = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Lấy danh sách assignments cần review
    $query = "SELECT a.*, d.title, d.content, d.ai_summary, d.type, u.full_name as labeler_name,
                     lr.selected_sentences, lr.writing_style, lr.edited_summary,
                     lr.completed_at
              FROM assignments a 
              JOIN documents d ON a.document_id = d.id 
              JOIN users u ON a.user_id = u.id
              LEFT JOIN labeling_results lr ON a.id = lr.assignment_id AND lr.document_id = d.id
              WHERE a.status = 'completed' 
              AND a.id NOT IN (SELECT assignment_id FROM reviews)
              ORDER BY a.updated_at DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pending_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy reviews gần đây của reviewer này
    $query = "SELECT r.*, a.id as assignment_id, d.title, u.full_name as labeler_name
              FROM reviews r
              JOIN assignments a ON r.assignment_id = a.id
              JOIN documents d ON a.document_id = d.id
              JOIN users u ON a.user_id = u.id
              WHERE r.reviewer_id = ?
              ORDER BY r.created_at DESC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $current_user['id']);
    $stmt->execute();
    $recent_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Lỗi database: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviewer Dashboard - Text Labeling System</title>
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
        .review-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .review-card:hover {
            transform: translateY(-3px);
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-needs_revision { background: #d1ecf1; color: #0c5460; }
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
                <a class="nav-link active" href="dashboard.php">
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
                <a class="nav-link" href="statistics.php">
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
            <h2 class="text-dark">Dashboard Reviewer</h2>
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
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="ms-3">
                            <div class="h4 mb-0"><?php echo $available_for_review; ?></div>
                            <div class="text-muted">Cần review</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #6610f2);">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="ms-3">
                            <div class="h4 mb-0"><?php echo $total_reviews; ?></div>
                            <div class="text-muted">Tổng reviews</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="ms-3">
                            <div class="h4 mb-0"><?php echo $approved_reviews; ?></div>
                            <div class="text-muted">Đã duyệt</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #e83e8c);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="ms-3">
                            <div class="h4 mb-0"><?php echo $rejected_reviews; ?></div>
                            <div class="text-muted">Từ chối</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Review Chart -->
            <div class="col-lg-4 mb-4">
                <div class="stat-card h-100">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie me-2 text-primary"></i>
                        Thống kê review
                    </h5>
                    <div class="text-center">
                        <canvas id="reviewChart" width="200" height="200"></canvas>
                        <?php 
                        $approval_rate = $total_reviews > 0 ? round(($approved_reviews / $total_reviews) * 100, 1) : 0;
                        ?>
                        <div class="mt-3">
                            <div class="h3 text-success"><?php echo $approval_rate; ?>%</div>
                            <div class="text-muted">Tỷ lệ duyệt</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Reviews -->
            <div class="col-lg-8 mb-4">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2 text-warning"></i>
                            Công việc cần review
                        </h5>
                        <a href="review.php" class="btn btn-outline-primary btn-sm">
                            Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    
                    <?php if (empty($pending_reviews)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-check-double fa-3x mb-3"></i>
                            <h6>Không có công việc cần review</h6>
                            <p>Tất cả công việc đã được review hoặc chưa có công việc nào hoàn thành.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Văn bản</th>
                                        <th>Người gán nhãn</th>
                                        <th>Loại</th>
                                        <th>Hoàn thành</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_reviews as $review): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars(substr($review['title'], 0, 30)); ?>...</div>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars(substr(strip_tags($review['content']), 0, 50)); ?>...
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($review['labeler_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo $review['type'] == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('d/m/Y H:i', strtotime($review['completed_at'])); ?></small>
                                            </td>
                                            <td>
                                                <a href="review.php?id=<?php echo $review['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye me-1"></i>Review
                                                </a>
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

        <!-- Recent Reviews -->
        <div class="row">
            <div class="col-12">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-history me-2 text-info"></i>
                        Reviews gần đây
                    </h5>
                    
                    <?php if (empty($recent_reviews)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p>Chưa có review nào được thực hiện.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($recent_reviews as $review): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0"><?php echo htmlspecialchars(substr($review['title'], 0, 30)); ?>...</h6>
                                                <?php
                                                $status_class = 'status-' . $review['status'];
                                                $status_text = '';
                                                switch ($review['status']) {
                                                    case 'pending':
                                                        $status_text = 'Chờ xử lý';
                                                        break;
                                                    case 'approved':
                                                        $status_text = 'Đã duyệt';
                                                        break;
                                                    case 'rejected':
                                                        $status_text = 'Từ chối';
                                                        break;
                                                    case 'needs_revision':
                                                        $status_text = 'Cần sửa';
                                                        break;
                                                }
                                                ?>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </div>
                                            <p class="card-text">
                                                <small class="text-muted">Người gán nhãn: <?php echo htmlspecialchars($review['labeler_name']); ?></small>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <small class="text-muted"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></small>
                                            </div>
                                            <?php if ($review['comments']): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($review['comments'], 0, 100)); ?>...</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-rocket me-2 text-warning"></i>
                        Thao tác nhanh
                    </h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <a href="review.php" class="btn btn-outline-primary w-100 p-3">
                                <i class="fas fa-clipboard-check fa-2x mb-2"></i>
                                <div>Bắt đầu review</div>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="my_reviews.php" class="btn btn-outline-success w-100 p-3">
                                <i class="fas fa-list-check fa-2x mb-2"></i>
                                <div>Xem reviews của tôi</div>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="statistics.php" class="btn btn-outline-info w-100 p-3">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <div>Xem thống kê</div>
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
        // Review Chart
        const ctx = document.getElementById('reviewChart').getContext('2d');
        const reviewChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Đã duyệt', 'Từ chối', 'Cần sửa'],
                datasets: [{
                    data: [
                        <?php echo $approved_reviews; ?>, 
                        <?php echo $rejected_reviews; ?>, 
                        <?php echo $total_reviews - $approved_reviews - $rejected_reviews; ?>
                    ],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
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