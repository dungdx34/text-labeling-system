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
$success_message = '';

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] == 'update_review') {
            $review_id = intval($_POST['review_id']);
            $rating = intval($_POST['rating']);
            $comments = trim($_POST['comments']);
            $status = $_POST['status'];
            
            $query = "UPDATE reviews SET rating = ?, comments = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                      WHERE id = ? AND reviewer_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$rating, $comments, $status, $review_id, $current_user['id']]);
            
            $success_message = 'Cập nhật review thành công!';
        }
        
        if ($_POST['action'] == 'delete_review') {
            $review_id = intval($_POST['review_id']);
            
            $query = "DELETE FROM reviews WHERE id = ? AND reviewer_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$review_id, $current_user['id']]);
            
            $success_message = 'Xóa review thành công!';
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi: ' . $e->getMessage();
    }
}

// Lấy danh sách reviews
$filter_status = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$reviews = [];
try {
    $where_clause = "r.reviewer_id = ?";
    $params = [$current_user['id']];
    
    if (!empty($filter_status)) {
        $where_clause .= " AND r.status = ?";
        $params[] = $filter_status;
    }
    
    // Đếm tổng số records
    $count_query = "SELECT COUNT(*) as total FROM reviews r WHERE $where_clause";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Lấy reviews với thông tin assignment
    $query = "SELECT r.*, a.id as assignment_id, a.type as assignment_type,
                     CASE 
                         WHEN a.type = 'single' THEN d.title 
                         WHEN a.type = 'multi' THEN dg.title 
                     END as document_title,
                     u.full_name as labeler_name,
                     lr.step1_completed, lr.step2_completed, lr.step3_completed,
                     lr.writing_style, lr.completed_at
              FROM reviews r
              JOIN assignments a ON r.assignment_id = a.id
              LEFT JOIN documents d ON a.document_id = d.id AND a.type = 'single'
              LEFT JOIN document_groups dg ON a.group_id = dg.id AND a.type = 'multi'
              JOIN users u ON a.user_id = u.id
              LEFT JOIN labeling_results lr ON a.id = lr.assignment_id
              WHERE $where_clause 
              ORDER BY r.created_at DESC 
              LIMIT $limit OFFSET $offset";
              
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy danh sách reviews: ' . $e->getMessage();
}

// Thống kê
$stats = [];
try {
    $query = "SELECT 
                COUNT(*) as total,
                AVG(rating) as avg_rating,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'needs_revision' THEN 1 ELSE 0 END) as needs_revision
              FROM reviews WHERE reviewer_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user['id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total' => 0, 'avg_rating' => 0, 'approved' => 0, 'rejected' => 0, 'needs_revision' => 0];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews của tôi - Text Labeling System</title>
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
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
        }
        .review-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
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
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .rating-stars {
            color: #ffc107;
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
                <a class="nav-link active" href="my_reviews.php">
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
            <h2 class="text-dark">Reviews của tôi</h2>
            <div class="d-flex gap-2">
                <select class="form-select" onchange="filterByStatus(this.value)">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Chờ xử lý</option>
                    <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Đã duyệt</option>
                    <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Từ chối</option>
                    <option value="needs_revision" <?php echo $filter_status == 'needs_revision' ? 'selected' : ''; ?>>Cần sửa</option>
                </select>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="content-card text-center">
                    <div class="stat-number text-primary"><?php echo $stats['total']; ?></div>
                    <div class="text-muted small">Tổng reviews</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="content-card text-center">
                    <div class="stat-number text-success"><?php echo $stats['approved']; ?></div>
                    <div class="text-muted small">Đã duyệt</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="content-card text-center">
                    <div class="stat-number text-danger"><?php echo $stats['rejected']; ?></div>
                    <div class="text-muted small">Từ chối</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="content-card text-center">
                    <div class="stat-number text-info"><?php echo $stats['needs_revision']; ?></div>
                    <div class="text-muted small">Cần sửa</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="content-card text-center">
                    <div class="stat-number text-warning"><?php echo $stats['avg_rating'] ? round($stats['avg_rating'], 1) : 0; ?></div>
                    <div class="text-muted small">Rating TB</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="content-card text-center">
                    <div class="stat-number text-dark">
                        <?php echo $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100, 1) : 0; ?>%
                    </div>
                    <div class="text-muted small">Tỷ lệ duyệt</div>
                </div>
            </div>
        </div>

        <!-- Reviews List -->
        <div class="content-card">
            <?php if (empty($reviews)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                    <h5>Không có review nào</h5>
                    <p>
                        <?php if ($filter_status): ?>
                            Không có review nào với trạng thái "<?php echo ucfirst($filter_status); ?>".
                        <?php else: ?>
                            Bạn chưa thực hiện review nào.
                        <?php endif; ?>
                    </p>
                    <?php if ($filter_status): ?>
                        <a href="my_reviews.php" class="btn btn-primary">Xem tất cả reviews</a>
                    <?php else: ?>
                        <a href="review.php" class="btn btn-primary">Bắt đầu review</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Văn bản</th>
                                <th>Labeler</th>
                                <th>Rating</th>
                                <th>Trạng thái</th>
                                <th>Ngày review</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td>#<?php echo $review['id']; ?></td>
                                    <td>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars(substr($review['document_title'] ?: 'Không có tiêu đề', 0, 30)); ?>...
                                        </div>
                                        <small class="text-muted">
                                            Assignment #<?php echo $review['assignment_id']; ?> • 
                                            <?php echo $review['assignment_type'] == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($review['labeler_name']); ?>
                                        </span>
                                        <?php if ($review['writing_style']): ?>
                                            <small class="text-muted d-block">Style: <?php echo htmlspecialchars($review['writing_style']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $review['rating'] ? '' : 'text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted"><?php echo $review['rating']; ?>/5</small>
                                    </td>
                                    <td>
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
                                    </td>
                                    <td>
                                        <small><?php echo date('d/m/Y H:i', strtotime($review['created_at'])); ?></small>
                                        <?php if ($review['updated_at'] != $review['created_at']): ?>
                                            <small class="text-muted d-block">Sửa: <?php echo date('d/m/Y H:i', strtotime($review['updated_at'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewReview(<?php echo htmlspecialchars(json_encode($review)); ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-warning" onclick="editReview(<?php echo htmlspecialchars(json_encode($review)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteReview(<?php echo $review['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($filter_status); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($filter_status); ?>">
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

    <!-- View Review Modal -->
    <div class="modal fade" id="viewReviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chi tiết Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewReviewContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Review Modal -->
    <div class="modal fade" id="editReviewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chỉnh sửa Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_review">
                        <input type="hidden" name="review_id" id="editReviewId">
                        
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <select class="form-select" name="rating" id="editRating" required>
                                <option value="">Chọn rating</option>
                                <option value="1">1 - Rất kém</option>
                                <option value="2">2 - Kém</option>
                                <option value="3">3 - Trung bình</option>
                                <option value="4">4 - Tốt</option>
                                <option value="5">5 - Xuất sắc</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="status" id="editStatus" required>
                                <option value="pending">Chờ xử lý</option>
                                <option value="approved">Đã duyệt</option>
                                <option value="rejected">Từ chối</option>
                                <option value="needs_revision">Cần sửa</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nhận xét</label>
                            <textarea class="form-control" name="comments" id="editComments" rows="4" 
                                      placeholder="Nhập nhận xét về công việc gán nhãn..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Cập nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterByStatus(status) {
            window.location.href = 'my_reviews.php' + (status ? '?status=' + status : '');
        }

        function viewReview(review) {
            let content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Thông tin Review:</h6>
                        <p><strong>ID:</strong> #${review.id}</p>
                        <p><strong>Văn bản:</strong> ${review.document_title}</p>
                        <p><strong>Labeler:</strong> ${review.labeler_name}</p>
                        <p><strong>Rating:</strong> ${review.rating}/5</p>
                        <p><strong>Trạng thái:</strong> ${getStatusText(review.status)}</p>
                        <p><strong>Ngày tạo:</strong> ${formatDate(review.created_at)}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Nhận xét:</h6>
                        <div class="border rounded p-3 bg-light">
                            ${review.comments || 'Không có nhận xét'}
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('viewReviewContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('viewReviewModal')).show();
        }

        function editReview(review) {
            document.getElementById('editReviewId').value = review.id;
            document.getElementById('editRating').value = review.rating;
            document.getElementById('editStatus').value = review.status;
            document.getElementById('editComments').value = review.comments || '';
            
            new bootstrap.Modal(document.getElementById('editReviewModal')).show();
        }

        function deleteReview(reviewId) {
            if (confirm('Bạn có chắc muốn xóa review này?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_review">
                    <input type="hidden" name="review_id" value="${reviewId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function getStatusText(status) {
            const statusMap = {
                'pending': 'Chờ xử lý',
                'approved': 'Đã duyệt', 
                'rejected': 'Từ chối',
                'needs_revision': 'Cần sửa'
            };
            return statusMap[status] || status;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('vi-VN') + ' ' + date.toLocaleTimeString('vi-VN');
        }
    </script>
</body>
</html>