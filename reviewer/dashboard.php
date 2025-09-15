<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';  // KHÔNG include auth.php

// Check reviewer authentication
requireRole('reviewer');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';

try {
    // Get completed tasks assigned to this reviewer
    $assigned_reviews = getReviewerTasks($user_id);
    
    // Get review statistics
    $stats = getReviewerStats($user_id);

} catch (Exception $e) {
    $error_message = "Lỗi khi tải dữ liệu: " . $e->getMessage();
    $assigned_reviews = [];
    $stats = [
        'total_reviews' => 0,
        'approved_count' => 0,
        'rejected_count' => 0,
        'revision_count' => 0,
        'pending_count' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Reviewer - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        .dashboard-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .review-card {
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
        }
        .review-card:hover {
            border-left-color: #1e7e34;
            background-color: #f8f9fa;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        .btn-action {
            border-radius: 20px;
            padding: 0.5rem 1.5rem;
        }
        .review-status-approved { border-left-color: #28a745; }
        .review-status-rejected { border-left-color: #dc3545; }
        .review-status-needs_revision { border-left-color: #ffc107; }
        .review-status-pending { border-left-color: #6c757d; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>Menu Reviewer</span>
                    </h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="review.php">
                                <i class="fas fa-check-circle me-2"></i>Review Tasks
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_reviews.php">
                                <i class="fas fa-history me-2"></i>Lịch sử Review
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tachometer-alt me-2 text-success"></i>
                        Dashboard Reviewer
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="badge bg-success fs-6">
                                <i class="fas fa-user me-1"></i>
                                Xin chào, <?php echo htmlspecialchars($username); ?>!
                            </span>
                        </div>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card dashboard-card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h4><?php echo $stats['pending_count'] ?? 0; ?></h4>
                                <p class="mb-0">Chờ review</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card" style="background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%); color: white;">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h4><?php echo $stats['approved_count'] ?? 0; ?></h4>
                                <p class="mb-0">Đã duyệt</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card" style="background: linear-gradient(135deg, #ef5350 0%, #e53935 100%); color: white;">
                            <div class="card-body text-center">
                                <i class="fas fa-times-circle fa-2x mb-2"></i>
                                <h4><?php echo $stats['rejected_count'] ?? 0; ?></h4>
                                <p class="mb-0">Từ chối</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card" style="background: linear-gradient(135deg, #ffa726 0%, #ff7043 100%); color: white;">
                            <div class="card-body text-center">
                                <i class="fas fa-edit fa-2x mb-2"></i>
                                <h4><?php echo $stats['revision_count'] ?? 0; ?></h4>
                                <p class="mb-0">Cần sửa</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reviews List -->
                <div class="card dashboard-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            Nhiệm vụ Review
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assigned_reviews)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Chưa có nhiệm vụ review nào</h5>
                                <p class="text-muted">Chờ các labeler hoàn thành công việc để review.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($assigned_reviews as $review): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card review-card review-status-<?php echo $review['review_status'] ?? 'pending'; ?> h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0">
                                                        <i class="fas <?php echo $review['group_type'] === 'multi' ? 'fa-copy' : 'fa-file-text'; ?> me-2"></i>
                                                        <?php echo htmlspecialchars($review['title']); ?>
                                                    </h6>
                                                    <span class="badge status-badge <?php 
                                                        $review_status = $review['review_status'] ?? 'pending';
                                                        echo $review_status === 'approved' ? 'bg-success' : 
                                                            ($review_status === 'rejected' ? 'bg-danger' : 
                                                            ($review_status === 'needs_revision' ? 'bg-warning' : 'bg-secondary')); 
                                                    ?>">
                                                        <?php 
                                                        $status_text = [
                                                            'pending' => 'Chờ review',
                                                            'approved' => 'Đã duyệt',
                                                            'rejected' => 'Từ chối',
                                                            'needs_revision' => 'Cần sửa'
                                                        ];
                                                        echo $status_text[$review_status] ?? 'Chờ review';
                                                        ?>
                                                    </span>
                                                </div>
                                                
                                                <p class="card-text text-muted small mb-2">
                                                    <?php echo htmlspecialchars($review['description'] ?? ''); ?>
                                                </p>
                                                
                                                <div class="row text-small mb-2">
                                                    <div class="col-6">
                                                        <span class="text-muted">
                                                            <i class="fas fa-file me-1"></i>
                                                            <?php echo $review['document_count'] ?? 0; ?> văn bản
                                                        </span>
                                                    </div>
                                                    <div class="col-6">
                                                        <span class="text-muted">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php echo htmlspecialchars($review['labeler_user'] ?? 'N/A'); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="row text-small mb-3">
                                                    <div class="col-6">
                                                        <small class="text-muted">
                                                            <i class="fas fa-upload me-1"></i>
                                                            Upload: <?php echo isset($review['created_at']) ? date('d/m/Y', strtotime($review['created_at'])) : 'N/A'; ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">
                                                            <i class="fas fa-check me-1"></i>
                                                            Hoàn thành: <?php echo isset($review['labeling_updated_at']) ? date('d/m/Y', strtotime($review['labeling_updated_at'])) : 'N/A'; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <?php if (isset($review['reviewed_at']) && $review['reviewed_at']): ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar-check me-1"></i>
                                                            Reviewed: <?php echo date('d/m/Y H:i', strtotime($review['reviewed_at'])); ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-hourglass-half me-1"></i>
                                                            Chưa review
                                                        </small>
                                                    <?php endif; ?>
                                                    
                                                    <div class="btn-group">
                                                        <?php if (isset($review['labeling_id']) && $review['is_completed']): ?>
                                                            <a href="review.php?labeling_id=<?php echo $review['labeling_id']; ?>" 
                                                               class="btn btn-success btn-sm btn-action">
                                                                <i class="fas fa-eye me-1"></i>
                                                                <?php echo (isset($review['review_status']) && $review['review_status'] !== 'pending') ? 'Xem lại' : 'Review'; ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="btn btn-secondary btn-sm btn-action disabled">
                                                                <i class="fas fa-clock me-1"></i>Chờ hoàn thành
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h5>Review Tasks</h5>
                                <p class="text-muted">Kiểm tra và đánh giá các công việc gán nhãn đã hoàn thành</p>
                                <a href="review.php" class="btn btn-success btn-action">
                                    <i class="fas fa-arrow-right me-2"></i>Bắt đầu Review
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-body text-center">
                                <i class="fas fa-history fa-3x text-info mb-3"></i>
                                <h5>Lịch sử Review</h5>
                                <p class="text-muted">Xem lại các review đã thực hiện và thống kê chi tiết</p>
                                <a href="my_reviews.php" class="btn btn-info btn-action">
                                    <i class="fas fa-arrow-right me-2"></i>Xem Lịch sử
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh every 60 seconds
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>