<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once '../includes/auth.php';
} catch (Exception $e) {
    die("Error loading auth: " . $e->getMessage());
}

// FIXED: Use the correct function name from auth.php
try {
    requireLabeler(); // This function exists in our auth.php
} catch (Exception $e) {
    die("Authentication error: " . $e->getMessage());
}

// Database connection
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
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Labeler dashboard DB error: " . $e->getMessage());
}

$current_user = getCurrentUser();

// Initialize stats
$total_assignments = 0;
$completed_assignments = 0;
$in_progress_assignments = 0;
$pending_assignments = 0;
$recent_assignments = [];

// Get statistics if database is available
if ($db && !$error_message) {
    try {
        // Check if assignments table exists
        $check_table = $db->query("SHOW TABLES LIKE 'assignments'");
        if ($check_table->rowCount() == 0) {
            // Create assignments table if it doesn't exist
            $create_assignments = "CREATE TABLE IF NOT EXISTS assignments (
                id int(11) NOT NULL AUTO_INCREMENT,
                user_id int(11) NOT NULL,
                document_id int(11),
                group_id int(11),
                task_type enum('single','multi') DEFAULT 'single',
                status enum('pending','in_progress','completed','reviewed') DEFAULT 'pending',
                assigned_by int(11),
                assigned_at timestamp DEFAULT CURRENT_TIMESTAMP,
                started_at timestamp NULL,
                completed_at timestamp NULL,
                notes text,
                PRIMARY KEY (id),
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_assigned_at (assigned_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->exec($create_assignments);
        }

        // Get assignment statistics
        $query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM assignments WHERE user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user['id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_assignments = $stats['total'] ?? 0;
        $completed_assignments = $stats['completed'] ?? 0;
        $in_progress_assignments = $stats['in_progress'] ?? 0;
        $pending_assignments = $stats['pending'] ?? 0;

        // Get recent assignments with document info
        $query = "SELECT a.*, 
                    d.title, d.content, d.ai_summary, d.type,
                    dg.title as group_title
                  FROM assignments a 
                  LEFT JOIN documents d ON a.document_id = d.id 
                  LEFT JOIN document_groups dg ON a.group_id = dg.id
                  WHERE a.user_id = ? 
                  ORDER BY a.assigned_at DESC 
                  LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user['id']]);
        $recent_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error_message = "Error loading assignment stats: " . $e->getMessage();
        error_log("Assignment stats error: " . $e->getMessage());
    }
}

// Create sample assignments if none exist (for demo purposes)
if ($db && $total_assignments == 0 && !$error_message) {
    try {
        // Check if there are any documents to assign
        $stmt = $db->query("SELECT COUNT(*) as count FROM documents LIMIT 1");
        $doc_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        if ($doc_count > 0) {
            // Create sample assignments
            $stmt = $db->query("SELECT id FROM documents ORDER BY created_at DESC LIMIT 3");
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($documents as $index => $doc) {
                $status = ['pending', 'in_progress', 'completed'][$index % 3];
                $insert_stmt = $db->prepare("INSERT INTO assignments (user_id, document_id, task_type, status, assigned_by, assigned_at) VALUES (?, ?, 'single', ?, 1, NOW())");
                $insert_stmt->execute([$current_user['id'], $doc['id'], $status]);
            }
            
            // Refresh stats
            header('Location: dashboard.php');
            exit();
        }
    } catch (Exception $e) {
        // Ignore sample creation errors
        error_log("Sample assignment creation error: " . $e->getMessage());
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
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
            text-decoration: none;
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
            height: 100%;
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
        .welcome-banner {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center text-white mb-4">
            <i class="fas fa-user-edit fa-2x mb-2"></i>
            <h5>Labeler Panel</h5>
            <small>Xin chào, <?php echo htmlspecialchars($current_user['full_name'] ?? $current_user['username'] ?? 'User'); ?></small>
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
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">Chào mừng trở lại!</h2>
                    <p class="mb-0">Hôm nay là ngày tuyệt vời để hoàn thành các công việc gán nhãn. Hãy bắt đầu ngay!</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="text-white-50">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('d/m/Y H:i'); ?>
                    </div>
                </div>
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
                    <a href="../login.php" class="btn btn-warning me-2">
                        <i class="fas fa-sign-in-alt me-1"></i>Đăng nhập lại
                    </a>
                    <a href="../logout.php" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout & Retry
                    </a>
                </div>
            </div>
        <?php else: ?>

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

        <!-- Recent Assignments -->
        <div class="row">
            <div class="col-12">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2 text-info"></i>
                            Công việc gần đây
                        </h5>
                        <a href="my_tasks.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-arrow-right me-1"></i>Xem tất cả
                        </a>
                    </div>
                    
                    <?php if (empty($recent_assignments)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <h6>Chưa có công việc nào</h6>
                            <p>Bạn chưa được giao công việc nào. Vui lòng liên hệ admin để được phân công.</p>
                            <a href="my_tasks.php" class="btn btn-primary">
                                <i class="fas fa-refresh me-1"></i>Làm mới
                            </a>
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
                                        <th>Ngày tạo</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_assignments as $assignment): ?>
                                        <tr>
                                            <td><strong>#<?php echo $assignment['id']; ?></strong></td>
                                            <td>
                                                <div class="fw-bold">
                                                    <?php 
                                                    $title = $assignment['title'] ?? $assignment['group_title'] ?? 'Không có tiêu đề';
                                                    echo htmlspecialchars(substr($title, 0, 40)); 
                                                    if (strlen($title) > 40) echo '...';
                                                    ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php 
                                                    $content = $assignment['content'] ?? 'Không có nội dung';
                                                    echo htmlspecialchars(substr(strip_tags($content), 0, 60)); 
                                                    if (strlen($content) > 60) echo '...';
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo ($assignment['task_type'] ?? 'single') == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $status = $assignment['status'] ?? 'pending';
                                                switch ($status) {
                                                    case 'pending':
                                                        echo '<span class="badge bg-warning">Chờ thực hiện</span>';
                                                        break;
                                                    case 'in_progress':
                                                        echo '<span class="badge bg-info">Đang thực hiện</span>';
                                                        break;
                                                    case 'completed':
                                                        echo '<span class="badge bg-success">Hoàn thành</span>';
                                                        break;
                                                    case 'reviewed':
                                                        echo '<span class="badge bg-secondary">Đã review</span>';
                                                        break;
                                                    default:
                                                        echo '<span class="badge bg-dark">Không xác định</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('d/m/Y H:i', strtotime($assignment['assigned_at'])); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($status == 'pending' || $status == 'in_progress'): ?>
                                                    <a href="labeling.php?assignment_id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit me-1"></i>Gán nhãn
                                                    </a>
                                                <?php else: ?>
                                                    <a href="labeling.php?assignment_id=<?php echo $assignment['id']; ?>&view=1" class="btn btn-sm btn-outline-secondary">
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
                            <a href="my_tasks.php" class="btn btn-outline-primary w-100 p-3 h-100">
                                <i class="fas fa-tasks fa-2x mb-2 d-block"></i>
                                <div>Xem công việc của tôi</div>
                                <small class="text-muted">Quản lý tất cả task được giao</small>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="labeling.php" class="btn btn-outline-success w-100 p-3 h-100">
                                <i class="fas fa-edit fa-2x mb-2 d-block"></i>
                                <div>Bắt đầu gán nhãn</div>
                                <small class="text-muted">Chỉnh sửa tóm tắt AI</small>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="history.php" class="btn btn-outline-info w-100 p-3 h-100">
                                <i class="fas fa-history fa-2x mb-2 d-block"></i>
                                <div>Xem lịch sử</div>
                                <small class="text-muted">Theo dõi tiến độ hoàn thành</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
        
        // Mobile sidebar toggle (if needed)
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
    </script>
</body>
</html>