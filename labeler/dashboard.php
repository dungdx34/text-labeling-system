<?php
// labeler/dashboard.php - Fixed Labeler Dashboard
require_once '../includes/auth.php';

// Require labeler role
$auth->requireLogin(['labeler']);

$user = $auth->getCurrentUser();

// Get labeling tasks for current user
try {
    $query = "SELECT lt.*, dg.title as group_title, dg.description, dg.type, 
                     dg.group_summary, dg.created_at as assigned_date
              FROM label_tasks lt
              JOIN document_groups dg ON lt.group_id = dg.id
              WHERE lt.labeler_id = :user_id
              ORDER BY 
                CASE lt.status 
                    WHEN 'assigned' THEN 1
                    WHEN 'in_progress' THEN 2
                    WHEN 'completed' THEN 3
                    WHEN 'reviewed' THEN 4
                    ELSE 5
                END,
                lt.assigned_at DESC";
    
    $stmt = $auth->db->prepare($query);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_query = "SELECT 
                        COUNT(*) as total_tasks,
                        SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as pending_tasks,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                        SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_tasks
                    FROM label_tasks 
                    WHERE labeler_id = :user_id";
    
    $stats_stmt = $auth->db->prepare($stats_query);
    $stats_stmt->bindParam(':user_id', $user['id']);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $tasks = [];
    $stats = ['total_tasks' => 0, 'pending_tasks' => 0, 'in_progress_tasks' => 0, 'completed_tasks' => 0, 'reviewed_tasks' => 0];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Người gán nhãn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .stats-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .icon-pending { background: linear-gradient(45deg, #ffc107, #ffab00); }
        .icon-progress { background: linear-gradient(45deg, #17a2b8, #138496); }
        .icon-completed { background: linear-gradient(45deg, #28a745, #20c997); }
        .icon-reviewed { background: linear-gradient(45deg, #6f42c1, #e83e8c); }
        
        .task-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .task-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-assigned { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-in_progress { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .status-completed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-reviewed { background: #e2e3f0; color: #383d41; border: 1px solid #d6d8db; }
        
        .btn-action {
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-start {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border: none;
        }
        
        .btn-continue {
            background: linear-gradient(45deg, #17a2b8, #138496);
            color: white;
            border: none;
        }
        
        .btn-view {
            background: linear-gradient(45deg, #6f42c1, #e83e8c);
            color: white;
            border: none;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            color: white;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .task-type-badge {
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tags me-2"></i>Text Labeling System
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($user['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit me-2"></i>Hồ sơ</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-wave-square me-2"></i>Chào mừng trở lại, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
                    <p class="mb-0 opacity-75">Hôm nay bạn có <?php echo $stats['pending_tasks']; ?> nhiệm vụ đang chờ xử lý</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex justify-content-end align-items-center">
                        <div class="me-3">
                            <small class="opacity-75">Tổng tiến độ</small>
                            <div class="progress" style="height: 8px; width: 150px;">
                                <?php 
                                $completion_rate = $stats['total_tasks'] > 0 ? 
                                    ($stats['completed_tasks'] + $stats['reviewed_tasks']) / $stats['total_tasks'] * 100 : 0;
                                ?>
                                <div class="progress-bar bg-warning" style="width: <?php echo $completion_rate; ?>%"></div>
                            </div>
                            <small class="opacity-75"><?php echo number_format($completion_rate, 1); ?>%</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stats-icon icon-pending me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $stats['pending_tasks']; ?></h3>
                            <small class="text-muted">Chờ xử lý</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stats-icon icon-progress me-3">
                            <i class="fas fa-play"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $stats['in_progress_tasks']; ?></h3>
                            <small class="text-muted">Đang thực hiện</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stats-icon icon-completed me-3">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $stats['completed_tasks']; ?></h3>
                            <small class="text-muted">Hoàn thành</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stats-icon icon-reviewed me-3">
                            <i class="fas fa-star"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $stats['reviewed_tasks']; ?></h3>
                            <small class="text-muted">Đã review</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task List -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4><i class="fas fa-tasks me-2"></i>Nhiệm vụ của tôi</h4>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="statusFilter" id="all" value="all" checked>
                        <label class="btn btn-outline-primary btn-sm" for="all">Tất cả</label>
                        
                        <input type="radio" class="btn-check" name="statusFilter" id="pending" value="assigned">
                        <label class="btn btn-outline-warning btn-sm" for="pending">Chờ xử lý</label>
                        
                        <input type="radio" class="btn-check" name="statusFilter" id="progress" value="in_progress">
                        <label class="btn btn-outline-info btn-sm" for="progress">Đang làm</label>
                        
                        <input type="radio" class="btn-check" name="statusFilter" id="completed" value="completed">
                        <label class="btn btn-outline-success btn-sm" for="completed">Hoàn thành</label>
                    </div>
                </div>

                <?php if (empty($tasks)): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h5>Chưa có nhiệm vụ nào</h5>
                                <p class="text-muted">Hiện tại bạn chưa được giao nhiệm vụ gán nhãn nào.<br>Vui lòng liên hệ quản trị viên để được giao việc.</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-card card" data-status="<?php echo $task['status']; ?>">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center mb-2">
                                            <h5 class="mb-0 me-2"><?php echo htmlspecialchars($task['group_title']); ?></h5>
                                            <span class="task-type-badge">
                                                <?php echo $task['type'] === 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($task['description']): ?>
                                            <p class="text-muted mb-2"><?php echo htmlspecialchars($task['description']); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="status-badge status-<?php echo $task['status']; ?>">
                                                <?php
                                                $status_labels = [
                                                    'assigned' => 'Chờ xử lý',
                                                    'in_progress' => 'Đang thực hiện',
                                                    'completed' => 'Hoàn thành',
                                                    'reviewed' => 'Đã review'
                                                ];
                                                echo $status_labels[$task['status']] ?? $task['status'];
                                                ?>
                                            </span>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                Giao: <?php echo date('d/m/Y', strtotime($task['assigned_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 text-end">
                                        <?php if ($task['status'] === 'assigned'): ?>
                                            <a href="labeling.php?task_id=<?php echo $task['id']; ?>" class="btn btn-start btn-action">
                                                <i class="fas fa-play me-2"></i>Bắt đầu
                                            </a>
                                        <?php elseif ($task['status'] === 'in_progress'): ?>
                                            <a href="labeling.php?task_id=<?php echo $task['id']; ?>" class="btn btn-continue btn-action">
                                                <i class="fas fa-edit me-2"></i>Tiếp tục
                                            </a>
                                        <?php else: ?>
                                            <a href="labeling.php?task_id=<?php echo $task['id']; ?>&view=1" class="btn btn-view btn-action">
                                                <i class="fas fa-eye me-2"></i>Xem
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter tasks by status
        document.querySelectorAll('input[name="statusFilter"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const filterValue = this.value;
                const taskCards = document.querySelectorAll('.task-card');
                
                taskCards.forEach(card => {
                    if (filterValue === 'all' || card.dataset.status === filterValue) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
        
        // Auto-refresh page every 5 minutes to check for new tasks
        setInterval(function() {
            // Only refresh if user is still on the page (not switched tabs)
            if (!document.hidden) {
                location.reload();
            }
        }, 300000); // 5 minutes
        
        // Show notification when new tasks are assigned (if implemented with WebSocket/SSE)
        function showNotification(message) {
            if (Notification.permission === 'granted') {
                new Notification('Text Labeling System', {
                    body: message,
                    icon: '/favicon.ico'
                });
            }
        }
        
        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    </script>
</body>
</html>