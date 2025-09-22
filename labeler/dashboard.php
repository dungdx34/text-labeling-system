<?php
// Bắt lỗi ngay từ đầu
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Kiểm tra session trước khi include auth
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once '../includes/auth.php';
} catch (Exception $e) {
    die("Error loading auth: " . $e->getMessage());
}

// Kiểm tra quyền labeler
try {
    requireRole('labeler');
} catch (Exception $e) {
    die("Authentication error: " . $e->getMessage());
}

// Kiểm tra database connection
$database = null;
$db = null;
$error_message = '';

try {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Test query
    $stmt = $db->prepare("SELECT 1");
    $stmt->execute();
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Labeler dashboard DB error: " . $e->getMessage());
}

$current_user = getCurrentUser();

// Chỉ lấy stats nếu database OK
$total_assignments = 0;
$completed_assignments = 0;
$in_progress_assignments = 0;
$pending_assignments = 0;
$recent_assignments = [];

if ($db && !$error_message) {
    try {
        // Tổng số assignments được giao
        $query = "SELECT COUNT(*) as total FROM assignments WHERE user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_assignments = $result['total'] ?? 0;

        // Assignments hoàn thành
        $query = "SELECT COUNT(*) as total FROM assignments WHERE user_id = ? AND status = 'completed'";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $completed_assignments = $result['total'] ?? 0;

        // Assignments đang làm
        $query = "SELECT COUNT(*) as total FROM assignments WHERE user_id = ? AND status = 'in_progress'";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $in_progress_assignments = $result['total'] ?? 0;

        // Assignments chưa bắt đầu
        $query = "SELECT COUNT(*) as total FROM assignments WHERE user_id = ? AND status = 'pending'";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $pending_assignments = $result['total'] ?? 0;

        // Lấy danh sách assignments gần đây (với LEFT JOIN để tránh lỗi)
        $query = "SELECT a.*, d.title, d.content, d.ai_summary, d.type, u.full_name as labeler_name
                  FROM assignments a 
                  LEFT JOIN documents d ON a.document_id = d.id 
                  LEFT JOIN users u ON a.user_id = u.id
                  WHERE a.user_id = ? 
                  ORDER BY a.created_at DESC 
                  LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user['id']]);
        $recent_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error_message = "Error loading stats: " . $e->getMessage();
        error_log("Labeler stats error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labeler Dashboard - Text Labeling System</title>
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
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .debug-info {
            background: #e2e3e5;
            border-radius: 5px;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center text-white mb-4">
            <i class="fas fa-user-edit fa-2x mb-2"></i>
            <h5>Labeler Panel</h5>
            <small>Xin chào, <?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?></small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
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
            <h2 class="text-dark">Dashboard Labeler</h2>
            <div class="text-muted">
                <i class="fas fa-calendar me-1"></i>
                <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>

        <!-- Error Display -->
        <?php if ($error_message): ?>
            <div class="error-box">
                <h4><i class="fas fa-exclamation-triangle me-2"></i>Lỗi Database</h4>
                <p><strong>Chi tiết:</strong> <?php echo htmlspecialchars($error_message); ?></p>
                
                <div class="debug-info">
                    <strong>Debug Information:</strong><br>
                    Current User ID: <?php echo $current_user['id'] ?? 'undefined'; ?><br>
                    Current User Role: <?php echo $current_user['role'] ?? 'undefined'; ?><br>
                    Database Object: <?php echo $database ? 'Created' : 'Failed'; ?><br>
                    Connection: <?php echo $db ? 'Connected' : 'Failed'; ?><br>
                    Config File: <?php echo file_exists('../config/database.php') ? 'Exists' : 'Missing'; ?><br>
                    Session Status: <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?>
                </div>
                
                <div class="mt-3">
                    <a href="../check_accounts.php" class="btn btn-warning me-2">
                        <i class="fas fa-tools me-1"></i>Kiểm tra hệ thống
                    </a>
                    <a href="../database_troubleshoot.php" class="btn btn-info me-2">
                        <i class="fas fa-database me-1"></i>Sửa Database
                    </a>
                    <a href="../logout.php" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout & Retry
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #007bff, #6610f2);">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="ms-3">
                            <div class="h4 mb-0"><?php echo $total_assignments; ?></div>
                            <div class="text-muted">Tổng công việc</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="ms-3">
                            <div class="h4 mb-0"><?php echo $pending_assignments; ?></div>
                            <div class="text-muted">Chờ thực hiện</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="ms-3">
                            <div class="h4 mb-0"><?php echo $in_progress_assignments; ?></div>
                            <div class="text-muted">Đang thực hiện</div>
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
                            <div class="h4 mb-0"><?php echo $completed_assignments; ?></div>
                            <div class="text-muted">Hoàn thành</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$error_message): ?>
        <!-- Recent Assignments -->
        <div class="row">
            <div class="col-12">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-list me-2 text-info"></i>
                        Công việc gần đây
                    </h5>
                    
                    <?php if (empty($recent_assignments)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <h6>Chưa có công việc nào</h6>
                            <p>Bạn chưa được giao công việc nào. Vui lòng liên hệ admin để được phân công.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Văn bản</th>
                                        <th>Loại</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_assignments as $assignment): ?>
                                        <tr>
                                            <td>#<?php echo $assignment['id']; ?></td>
                                            <td>
                                                <div class="fw-bold">
                                                    <?php echo htmlspecialchars(substr($assignment['title'] ?? 'No title', 0, 30)); ?>...
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars(substr(strip_tags($assignment['content'] ?? ''), 0, 50)); ?>...
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo ($assignment['type'] ?? 'single') == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $status = $assignment['status'] ?? 'pending';
                                                $status_class = 'status-' . $status;
                                                $status_text = '';
                                                switch ($status) {
                                                    case 'pending':
                                                        $status_text = 'Chờ thực hiện';
                                                        $badge_class = 'bg-warning';
                                                        break;
                                                    case 'in_progress':
                                                        $status_text = 'Đang thực hiện';
                                                        $badge_class = 'bg-info';
                                                        break;
                                                    case 'completed':
                                                        $status_text = 'Hoàn thành';
                                                        $badge_class = 'bg-success';
                                                        break;
                                                    case 'reviewed':
                                                        $status_text = 'Đã review';
                                                        $badge_class = 'bg-secondary';
                                                        break;
                                                    default:
                                                        $status_text = 'Không xác định';
                                                        $badge_class = 'bg-dark';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('d/m/Y', strtotime($assignment['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($status == 'pending' || $status == 'in_progress'): ?>
                                                    <a href="labeling.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit me-1"></i>Gán nhãn
                                                    </a>
                                                <?php else: ?>
                                                    <a href="labeling.php?id=<?php echo $assignment['id']; ?>&view=1" class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-eye me-1"></i>Xem
                                                    </a>
                                                <?php endif; ?>
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
        <?php endif; ?>

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
                            <a href="my_tasks.php" class="btn btn-outline-primary w-100 p-3">
                                <i class="fas fa-tasks fa-2x mb-2"></i>
                                <div>Xem công việc của tôi</div>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="labeling.php" class="btn btn-outline-success w-100 p-3">
                                <i class="fas fa-edit fa-2x mb-2"></i>
                                <div>Bắt đầu gán nhãn</div>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="history.php" class="btn btn-outline-info w-100 p-3">
                                <i class="fas fa-history fa-2x mb-2"></i>
                                <div>Xem lịch sử</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</body>
</html>