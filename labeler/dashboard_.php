<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check database connection
if (!isset($pdo) || !$pdo) {
    die("Database connection failed. Please check config/database.php");
}

// Check labeler authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'labeler') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';

try {
    // Get assigned tasks for this labeler
    $stmt = $pdo->prepare("
        SELECT 
            dg.id as group_id,
            dg.title,
            dg.description,
            dg.group_type,
            dg.status,
            dg.created_at,
            COUNT(d.id) as document_count,
            u.username as uploaded_by_user
        FROM document_groups dg 
        LEFT JOIN documents d ON dg.id = d.group_id
        LEFT JOIN users u ON dg.uploaded_by = u.id
        WHERE dg.assigned_labeler = ? 
        GROUP BY dg.id 
        ORDER BY dg.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $assigned_tasks = $stmt->fetchAll();

    // Get completed tasks count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as completed_count 
        FROM document_groups 
        WHERE assigned_labeler = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $completed_count = $stmt->fetch()['completed_count'];

    // Get in progress tasks count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as in_progress_count 
        FROM document_groups 
        WHERE assigned_labeler = ? AND status = 'in_progress'
    ");
    $stmt->execute([$user_id]);
    $in_progress_count = $stmt->fetch()['in_progress_count'];

    // Get pending tasks count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count 
        FROM document_groups 
        WHERE assigned_labeler = ? AND status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_count = $stmt->fetch()['pending_count'];

} catch (Exception $e) {
    $error_message = "Lỗi khi tải dữ liệu: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Labeler - Text Labeling System</title>
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
        .task-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .task-card:hover {
            border-left-color: #0056b3;
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
                        <span>Menu Labeler</span>
                    </h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_tasks.php">
                                <i class="fas fa-tasks me-2"></i>Nhiệm vụ của tôi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="labeling.php">
                                <i class="fas fa-edit me-2"></i>Gán nhãn đơn văn bản
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="multi_labeling.php">
                                <i class="fas fa-copy me-2"></i>Gán nhãn đa văn bản
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tachometer-alt me-2 text-primary"></i>
                        Dashboard Labeler
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
                                <h4><?php echo $pending_count; ?></h4>
                                <p class="mb-0">Nhiệm vụ chờ</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card" style="background: linear-gradient(135deg, #ffa726 0%, #ff7043 100%); color: white;">
                            <div class="card-body text-center">
                                <i class="fas fa-spinner fa-2x mb-2"></i>
                                <h4><?php echo $in_progress_count; ?></h4>
                                <p class="mb-0">Đang thực hiện</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card" style="background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%); color: white;">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h4><?php echo $completed_count; ?></h4>
                                <p class="mb-0">Đã hoàn thành</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card" style="background: linear-gradient(135deg, #42a5f5 0%, #1e88e5 100%); color: white;">
                            <div class="card-body text-center">
                                <i class="fas fa-list fa-2x mb-2"></i>
                                <h4><?php echo count($assigned_tasks); ?></h4>
                                <p class="mb-0">Tổng nhiệm vụ</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tasks List -->
                <div class="card dashboard-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-tasks me-2"></i>
                            Nhiệm vụ được giao
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assigned_tasks)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Chưa có nhiệm vụ nào được giao</h5>
                                <p class="text-muted">Hãy chờ admin giao nhiệm vụ cho bạn.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($assigned_tasks as $task): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card task-card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0">
                                                        <i class="fas <?php echo $task['group_type'] === 'multi' ? 'fa-copy' : 'fa-file-text'; ?> me-2"></i>
                                                        <?php echo htmlspecialchars($task['title']); ?>
                                                    </h6>
                                                    <span class="badge status-badge <?php 
                                                        echo $task['status'] === 'pending' ? 'bg-warning' : 
                                                            ($task['status'] === 'in_progress' ? 'bg-info' : 
                                                            ($task['status'] === 'completed' ? 'bg-success' : 'bg-secondary')); 
                                                    ?>">
                                                        <?php 
                                                        $status_text = [
                                                            'pending' => 'Chờ xử lý',
                                                            'in_progress' => 'Đang thực hiện', 
                                                            'completed' => 'Đã hoàn thành',
                                                            'reviewed' => 'Đã review'
                                                        ];
                                                        echo $status_text[$task['status']] ?? $task['status'];
                                                        ?>
                                                    </span>
                                                </div>
                                                
                                                <p class="card-text text-muted small mb-2">
                                                    <?php echo htmlspecialchars($task['description']); ?>
                                                </p>
                                                
                                                <div class="d-flex justify-content-between align-items-center text-small">
                                                    <span class="text-muted">
                                                        <i class="fas fa-file me-1"></i>
                                                        <?php echo $task['document_count']; ?> văn bản
                                                    </span>
                                                    <span class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($task['uploaded_by_user']); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center mt-3">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo date('d/m/Y H:i', strtotime($task['created_at'])); ?>
                                                    </small>
                                                    
                                                    <div class="btn-group">
                                                        <?php if ($task['status'] === 'pending' || $task['status'] === 'in_progress'): ?>
                                                            <?php if ($task['group_type'] === 'single'): ?>
                                                                <a href="labeling.php?group_id=<?php echo $task['group_id']; ?>" 
                                                                   class="btn btn-primary btn-sm btn-action">
                                                                    <i class="fas fa-edit me-1"></i>Gán nhãn
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="multi_labeling.php?group_id=<?php echo $task['group_id']; ?>" 
                                                                   class="btn btn-info btn-sm btn-action">
                                                                    <i class="fas fa-copy me-1"></i>Gán nhãn đa văn bản
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="btn btn-success btn-sm btn-action disabled">
                                                                <i class="fas fa-check me-1"></i>Đã hoàn thành
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
                                <i class="fas fa-edit fa-3x text-primary mb-3"></i>
                                <h5>Gán nhãn văn bản đơn</h5>
                                <p class="text-muted">Thực hiện gán nhãn cho một văn bản với bản tóm tắt AI</p>
                                <a href="labeling.php" class="btn btn-primary btn-action">
                                    <i class="fas fa-arrow-right me-2"></i>Bắt đầu
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-body text-center">
                                <i class="fas fa-copy fa-3x text-info mb-3"></i>
                                <h5>Gán nhãn đa văn bản</h5>
                                <p class="text-muted">Thực hiện gán nhãn cho nhiều văn bản với bản tóm tắt AI chung</p>
                                <a href="multi_labeling.php" class="btn btn-info btn-action">
                                    <i class="fas fa-arrow-right me-2"></i>Bắt đầu
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
        // Auto refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>