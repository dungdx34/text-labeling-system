<?php
// Bắt lỗi và khởi tạo session an toàn
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../config/database.php';

// Kiểm tra quyền labeler
requireRole('labeler');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$error_message = '';

// Lấy lịch sử assignments
$assignments = [];
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Đếm tổng số records
    $count_query = "SELECT COUNT(*) as total FROM assignments WHERE user_id = ?";
    $stmt = $db->prepare($count_query);
    $stmt->execute([$current_user['id']]);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Lấy assignments với thông tin chi tiết
    $query = "SELECT a.*, 
                     CASE 
                         WHEN a.type = 'single' THEN d.title 
                         WHEN a.type = 'multi' THEN dg.title 
                     END as title,
                     CASE 
                         WHEN a.type = 'single' THEN d.content 
                         WHEN a.type = 'multi' THEN dg.description 
                     END as content,
                     admin.full_name as assigned_by_name,
                     lr.step1_completed, lr.step2_completed, lr.step3_completed,
                     lr.writing_style, lr.completed_at,
                     r.rating, r.comments as review_comments, r.status as review_status,
                     reviewer.full_name as reviewer_name
              FROM assignments a 
              LEFT JOIN documents d ON a.document_id = d.id AND a.type = 'single'
              LEFT JOIN document_groups dg ON a.group_id = dg.id AND a.type = 'multi'
              LEFT JOIN users admin ON a.assigned_by = admin.id
              LEFT JOIN labeling_results lr ON a.id = lr.assignment_id
              LEFT JOIN reviews r ON a.id = r.assignment_id
              LEFT JOIN users reviewer ON r.reviewer_id = reviewer.id
              WHERE a.user_id = ? 
              ORDER BY a.updated_at DESC 
              LIMIT $limit OFFSET $offset";
              
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user['id']]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy lịch sử: ' . $e->getMessage();
}

// Thống kê tổng quan
$stats = [];
try {
    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                AVG(CASE WHEN status = 'completed' THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) ELSE NULL END) as avg_hours
              FROM assignments WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user['id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total' => 0, 'completed' => 0, 'avg_hours' => 0];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
        }
        .sidebar {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
        }
        .history-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .history-card:hover {
            transform: translateY(-3px);
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_progress { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-reviewed { background: #e2e3e5; color: #383d41; }
        .review-approved { background: #d4edda; color: #155724; }
        .review-rejected { background: #f8d7da; color: #721c24; }
        .review-pending { background: #fff3cd; color: #856404; }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center text-white mb-4">
            <i class="fas fa-user-edit fa-2x mb-2"></i>
            <h5>Labeler Panel</h5>
            <small>Xin chào, <?php echo htmlspecialchars($current_user['full_name']); ?></small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my_tasks.php">
                    <i class="fas fa-tasks me-2"></i>Công việc của tôi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="labeling.php">
                    <i class="fas fa-edit me-2"></i>Gán nhãn
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="history.php">
                    <i class="fas fa-history me-2"></i>Lịch sử
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
            <h2 class="text-dark">Lịch sử gán nhãn</h2>
            <div class="text-muted">
                <i class="fas fa-chart-line me-1"></i>
                Tổng: <?php echo $total_records; ?> công việc
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="content-card text-center">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="text-muted">Tổng công việc</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="content-card text-center">
                    <div class="stat-number text-success"><?php echo $stats['completed']; ?></div>
                    <div class="text-muted">Đã hoàn thành</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="content-card text-center">
                    <div class="stat-number text-info">
                        <?php echo $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) : 0; ?>%
                    </div>
                    <div class="text-muted">Tỷ lệ hoàn thành</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="content-card text-center">
                    <div class="stat-number text-warning">
                        <?php echo $stats['avg_hours'] ? round($stats['avg_hours'], 1) : 0; ?>h
                    </div>
                    <div class="text-muted">Thời gian TB</div>
                </div>
            </div>
        </div>

        <!-- History List -->
        <div class="content-card">
            <?php if (empty($assignments)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-history fa-3x mb-3"></i>
                    <h5>Chưa có lịch sử</h5>
                    <p>Bạn chưa có công việc gán nhãn nào.</p>
                    <a href="my_tasks.php" class="btn btn-primary">Xem công việc hiện tại</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Văn bản</th>
                                <th>Loại</th>
                                <th>Trạng thái</th>
                                <th>Tiến độ</th>
                                <th>Review</th>
                                <th>Ngày hoàn thành</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td>#<?php echo $assignment['id']; ?></td>
                                    <td>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars(substr($assignment['title'] ?: 'Không có tiêu đề', 0, 30)); ?>...
                                        </div>
                                        <small class="text-muted">
                                            Giao bởi: <?php echo htmlspecialchars($assignment['assigned_by_name'] ?: 'N/A'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $assignment['type'] == 'single' ? 'bg-primary' : 'bg-success'; ?>">
                                            <?php echo $assignment['type'] == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'status-' . $assignment['status'];
                                        $status_text = '';
                                        switch ($assignment['status']) {
                                            case 'pending':
                                                $status_text = 'Chờ thực hiện';
                                                break;
                                            case 'in_progress':
                                                $status_text = 'Đang thực hiện';
                                                break;
                                            case 'completed':
                                                $status_text = 'Hoàn thành';
                                                break;
                                            case 'reviewed':
                                                $status_text = 'Đã review';
                                                break;
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <span class="badge <?php echo $assignment['step1_completed'] ? 'bg-success' : 'bg-secondary'; ?> me-1">1</span>
                                            <span class="badge <?php echo $assignment['step2_completed'] ? 'bg-success' : 'bg-secondary'; ?> me-1">2</span>
                                            <span class="badge <?php echo $assignment['step3_completed'] ? 'bg-success' : 'bg-secondary'; ?>">3</span>
                                        </div>
                                        <?php if ($assignment['writing_style']): ?>
                                            <small class="text-muted d-block">Style: <?php echo htmlspecialchars($assignment['writing_style']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($assignment['review_status']): ?>
                                            <span class="status-badge review-<?php echo $assignment['review_status']; ?>">
                                                <?php
                                                switch ($assignment['review_status']) {
                                                    case 'pending':
                                                        echo 'Chờ review';
                                                        break;
                                                    case 'approved':
                                                        echo 'Đã duyệt';
                                                        break;
                                                    case 'rejected':
                                                        echo 'Từ chối';
                                                        break;
                                                    case 'needs_revision':
                                                        echo 'Cần sửa';
                                                        break;
                                                }
                                                ?>
                                            </span>
                                            <?php if ($assignment['rating']): ?>
                                                <div class="mt-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $assignment['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($assignment['reviewer_name']): ?>
                                                <small class="text-muted d-block">By: <?php echo htmlspecialchars($assignment['reviewer_name']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Chưa review</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($assignment['completed_at']): ?>
                                            <small><?php echo date('d/m/Y H:i', strtotime($assignment['completed_at'])); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="labeling.php?id=<?php echo $assignment['id']; ?>&view=1" class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($assignment['review_comments']): ?>
                                                <button class="btn btn-outline-info" onclick="showReviewComments('<?php echo htmlspecialchars($assignment['review_comments']); ?>')">
                                                    <i class="fas fa-comment"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($assignment['status'] == 'pending' || $assignment['status'] == 'in_progress'): ?>
                                                <a href="labeling.php?id=<?php echo $assignment['id']; ?>" class="btn btn-outline-success">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Review Comments Modal -->
    <div class="modal fade" id="reviewCommentsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-comment me-2"></i>Nhận xét từ Reviewer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="reviewCommentsContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function showReviewComments(comments) {
            document.getElementById('reviewCommentsContent').innerHTML = '<p>' + comments + '</p>';
            new bootstrap.Modal(document.getElementById('reviewCommentsModal')).show();
        }
    </script>
</body>
</html>