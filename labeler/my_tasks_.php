<?php
// Start session and error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// Simple auth check - no external functions needed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'labeler') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$current_user_id = $_SESSION['user_id'];

// Get current user info
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current_user) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$error_message = '';
$success_message = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] == 'update_status') {
            $assignment_id = intval($_POST['assignment_id']);
            $new_status = $_POST['status'];
            
            $query = "UPDATE assignments SET status = ? WHERE id = ? AND user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_status, $assignment_id, $current_user_id]);
            
            $success_message = 'Cập nhật trạng thái thành công!';
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi: ' . $e->getMessage();
    }
}

// Get assignments list
$assignments = [];
$filter_status = $_GET['status'] ?? '';

try {
    $where_clause = "a.user_id = ?";
    $params = [$current_user_id];
    
    if (!empty($filter_status)) {
        $where_clause .= " AND a.status = ?";
        $params[] = $filter_status;
    }
    
    // Fixed query to match actual database structure
    $query = "SELECT a.*, 
                     CASE 
                         WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN d.title 
                         WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN dg.group_name 
                         ELSE 'Untitled'
                     END as title,
                     CASE 
                         WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN d.content 
                         WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN dg.description 
                         ELSE 'No content'
                     END as content,
                     CASE 
                         WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN d.ai_summary 
                         WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN dg.description 
                         ELSE ''
                     END as ai_summary,
                     CASE 
                         WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN 'single' 
                         WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN 'multi' 
                         ELSE 'single'
                     END as type,
                     admin.full_name as assigned_by_name
              FROM assignments a 
              LEFT JOIN documents d ON a.document_id = d.id
              LEFT JOIN document_groups dg ON a.group_id = dg.id
              LEFT JOIN users admin ON a.assigned_by = admin.id
              WHERE $where_clause 
              ORDER BY a.id DESC";
              
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add dummy progress data if labeling_results table doesn't exist
    foreach ($assignments as &$assignment) {
        $assignment['step1_completed'] = 0;
        $assignment['step2_completed'] = 0; 
        $assignment['step3_completed'] = 0;
        $assignment['writing_style'] = '';
    }
    
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy danh sách công việc: ' . $e->getMessage();
}

// Statistics
$stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'reviewed' => 0];
try {
    $query = "SELECT status, COUNT(*) as count FROM assignments WHERE user_id = ? GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute([$current_user_id]);
    
    while ($row = $stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
        $stats['total'] += $row['count'];
    }
} catch (Exception $e) {
    // Ignore stats error
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Công việc của tôi - Text Labeling System</title>
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
        .assignment-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .assignment-card:hover {
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
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
        }
        .step {
            text-align: center;
            flex: 1;
        }
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px;
        }
        .step-circle.completed {
            background: #28a745;
            color: white;
        }
        .step-circle.current {
            background: #007bff;
            color: white;
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
                <a class="nav-link active" href="my_tasks.php">
                    <i class="fas fa-tasks me-2"></i>Công việc của tôi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="labeling.php">
                    <i class="fas fa-edit me-2"></i>Gán nhãn
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="history.php">
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
            <h2 class="text-dark">Công việc của tôi</h2>
            <div class="d-flex gap-2">
                <select class="form-select" onchange="filterByStatus(this.value)">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Chờ thực hiện</option>
                    <option value="in_progress" <?php echo $filter_status == 'in_progress' ? 'selected' : ''; ?>>Đang thực hiện</option>
                    <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                    <option value="reviewed" <?php echo $filter_status == 'reviewed' ? 'selected' : ''; ?>>Đã review</option>
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
                <div class="text-center">
                    <div class="h4 text-primary"><?php echo $stats['total']; ?></div>
                    <div class="text-muted small">Tổng cộng</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <div class="h4 text-warning"><?php echo $stats['pending']; ?></div>
                    <div class="text-muted small">Chờ thực hiện</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <div class="h4 text-info"><?php echo $stats['in_progress']; ?></div>
                    <div class="text-muted small">Đang thực hiện</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <div class="h4 text-success"><?php echo $stats['completed']; ?></div>
                    <div class="text-muted small">Hoàn thành</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <div class="h4 text-secondary"><?php echo $stats['reviewed']; ?></div>
                    <div class="text-muted small">Đã review</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <div class="h4 text-dark">
                        <?php echo $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) : 0; ?>%
                    </div>
                    <div class="text-muted small">Hoàn thành</div>
                </div>
            </div>
        </div>

        <!-- Assignments List -->
        <div class="content-card">
            <?php if (empty($assignments)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <h5>Không có công việc nào</h5>
                    <p>
                        <?php if ($filter_status): ?>
                            Không có công việc nào với trạng thái "<?php echo ucfirst($filter_status); ?>".
                        <?php else: ?>
                            Bạn chưa được giao công việc nào. Liên hệ admin để được phân công.
                        <?php endif; ?>
                    </p>
                    <?php if ($filter_status): ?>
                        <a href="my_tasks.php" class="btn btn-primary">Xem tất cả công việc</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($assignments as $assignment): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="assignment-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars(substr($assignment['title'] ?: 'Không có tiêu đề', 0, 50)); ?></h5>
                                        <small class="text-muted">
                                            ID: #<?php echo $assignment['id']; ?> • 
                                            Giao bởi: <?php echo htmlspecialchars($assignment['assigned_by_name'] ?: 'N/A'); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <span class="badge <?php echo $assignment['type'] == 'single' ? 'bg-primary' : 'bg-success'; ?>">
                                            <?php echo $assignment['type'] == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="text-muted mb-2">
                                        <?php echo htmlspecialchars(substr(strip_tags($assignment['content'] ?: ''), 0, 100)); ?>...
                                    </p>
                                </div>
                                
                                <!-- Progress Steps -->
                                <div class="progress-steps">
                                    <div class="step">
                                        <div class="step-circle <?php echo $assignment['step1_completed'] ? 'completed' : 'current'; ?>">
                                            1
                                        </div>
                                        <div class="small">Chọn câu</div>
                                    </div>
                                    <div class="step">
                                        <div class="step-circle <?php echo $assignment['step2_completed'] ? 'completed' : ($assignment['step1_completed'] ? 'current' : ''); ?>">
                                            2
                                        </div>
                                        <div class="small">Phong cách</div>
                                    </div>
                                    <div class="step">
                                        <div class="step-circle <?php echo $assignment['step3_completed'] ? 'completed' : ($assignment['step2_completed'] ? 'current' : ''); ?>">
                                            3
                                        </div>
                                        <div class="small">Chỉnh sửa</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
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
                                        
                                        <?php if ($assignment['deadline']): ?>
                                            <small class="text-muted ms-2">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($assignment['deadline'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($assignment['status'] == 'pending' || $assignment['status'] == 'in_progress'): ?>
                                            <a href="labeling.php?id=<?php echo $assignment['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-edit me-1"></i>Gán nhãn
                                            </a>
                                        <?php else: ?>
                                            <a href="labeling.php?id=<?php echo $assignment['id']; ?>&view=1" class="btn btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i>Xem
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mt-2 small text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Assignment ID: <?php echo $assignment['id']; ?>
                                    <?php if (isset($assignment['deadline'])): ?>
                                        • Deadline: <?php echo date('d/m/Y H:i', strtotime($assignment['deadline'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterByStatus(status) {
            window.location.href = 'my_tasks.php' + (status ? '?status=' + status : '');
        }

        function updateStatus(assignmentId, newStatus) {
            if (confirm('Bạn có chắc muốn cập nhật trạng thái?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="assignment_id" value="${assignmentId}">
                    <input type="hidden" name="status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>