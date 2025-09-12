<?php
require_once '../includes/auth.php';

// Check if user is labeler
if ($_SESSION['role'] !== 'labeler') {
    header('Location: ../index.php');
    exit;
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'text_labeling_system');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userId = $_SESSION['user_id'];

// Get labeler's tasks and statistics
function getLabelerStats($conn, $userId) {
    $stats = [];
    
    // Get tasks assigned to this labeler
    $result = $conn->query("
        SELECT l.*, d.title, dg.title as group_title, dg.group_type
        FROM labelings l
        LEFT JOIN documents d ON l.document_id = d.id
        LEFT JOIN document_groups dg ON l.group_id = dg.id
        WHERE l.labeler_id = $userId
        ORDER BY l.created_at DESC
    ");
    
    $myTasks = [];
    $completedCount = 0;
    $inProgressCount = 0;
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Calculate progress based on status
            switch ($row['status']) {
                case 'assigned':
                    $row['progress'] = 0;
                    break;
                case 'in_progress':
                    $row['progress'] = 50;
                    $inProgressCount++;
                    break;
                case 'completed':
                    $row['progress'] = 100;
                    $completedCount++;
                    break;
                default:
                    $row['progress'] = 0;
            }
            
            // Set title from group or document
            if ($row['group_title']) {
                $row['title'] = $row['group_title'];
            } elseif (!$row['title']) {
                $row['title'] = 'Untitled Task';
            }
            
            $myTasks[] = $row;
        }
    }
    
    $stats['my_tasks'] = $myTasks;
    $stats['total_tasks'] = count($myTasks);
    $stats['completed_tasks'] = $completedCount;
    $stats['in_progress_tasks'] = $inProgressCount;
    
    return $stats;
}

// Get available tasks
function getAvailableTasks($conn) {
    $tasks = [];
    
    // Available single documents
    $result = $conn->query("
        SELECT d.*, u.username as uploaded_by_name, 'single' as task_type
        FROM documents d
        JOIN users u ON d.uploaded_by = u.id
        LEFT JOIN labelings l ON d.id = l.document_id
        WHERE l.id IS NULL
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
    }
    
    // Available document groups (if table exists)
    $tableExists = $conn->query("SHOW TABLES LIKE 'document_groups'");
    if ($tableExists && $tableExists->num_rows > 0) {
        $result = $conn->query("
            SELECT dg.*, u.username as uploaded_by_name, 'multi' as task_type
            FROM document_groups dg
            JOIN users u ON dg.uploaded_by = u.id
            LEFT JOIN labelings l ON dg.id = l.group_id
            WHERE l.id IS NULL AND dg.status = 'pending'
            ORDER BY dg.created_at DESC
            LIMIT 5
        ");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        }
    }
    
    return $tasks;
}

$stats = getLabelerStats($conn, $userId);
$availableTasks = getAvailableTasks($conn);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labeler Dashboard - Text Labeling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
        }
        
        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 0 20px 20px 0;
            min-height: 100vh;
            position: fixed;
            width: 250px;
            z-index: 1000;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .nav-link {
            color: #333;
            padding: 15px 25px;
            margin: 5px 15px;
            border-radius: 15px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .nav-link:hover {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            transform: translateX(10px);
        }
        
        .nav-link.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white !important;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .nav-link i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        
        .welcome-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .stat-icon.primary { color: #667eea; }
        .stat-icon.success { color: #1cc88a; }
        .stat-icon.warning { color: #f6c23e; }
        .stat-icon.info { color: #36b9cc; }
        
        .task-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .action-btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 12px 25px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .action-btn.success {
            background: linear-gradient(45deg, #1cc88a, #2ecc71);
        }
        
        .action-btn.info {
            background: linear-gradient(45deg, #36b9cc, #3498db);
        }
        
        .progress-ring {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
        }
        
        .progress-ring circle {
            transition: stroke-dasharray 0.3s ease;
        }
        
        .navbar-custom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            margin-bottom: 20px;
            padding: 15px 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-primary d-md-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <h4 class="mb-0 text-primary">
                    <i class="fas fa-tags me-2"></i>Text Labeling System
                </h4>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge bg-success me-3">
                    <i class="fas fa-circle me-1"></i>Online
                </span>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-dark" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="d-flex align-items-center">
                            <div class="avatar me-2" style="width: 35px; height: 35px; background: linear-gradient(45deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                                <small class="text-muted">Labeler</small>
                            </div>
                        </div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="sidebar" id="sidebar">
                <div class="p-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="labeling.php">
                                <i class="fas fa-tag"></i>Gán nhãn đơn
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="multi_labeling.php">
                                <i class="fas fa-tags"></i>Gán nhãn đa văn bản
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_tasks.php">
                                <i class="fas fa-tasks"></i>Nhiệm vụ của tôi
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="main-content">
                <!-- Welcome Card -->
                <div class="welcome-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="text-primary mb-2">
                                <i class="fas fa-hand-wave me-2"></i>
                                Chào mừng trở lại, <?php echo htmlspecialchars($_SESSION['full_name'] ?: $_SESSION['username']); ?>!
                            </h2>
                            <p class="text-muted mb-0">
                                Hôm nay bạn có <?php echo $stats['in_progress_tasks']; ?> nhiệm vụ đang làm 
                                và <?php echo $stats['completed_tasks']; ?> nhiệm vụ đã hoàn thành.
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="progress-ring">
                                <?php 
                                $completionRate = $stats['total_tasks'] > 0 ? ($stats['completed_tasks'] / $stats['total_tasks']) * 100 : 0;
                                $circumference = 2 * pi() * 35;
                                $offset = $circumference - ($completionRate / 100) * $circumference;
                                ?>
                                <svg width="80" height="80">
                                    <circle cx="40" cy="40" r="35" stroke="#e9ecef" stroke-width="8" fill="none"/>
                                    <circle cx="40" cy="40" r="35" stroke="#28a745" stroke-width="8" fill="none"
                                            stroke-dasharray="<?php echo $circumference; ?>" 
                                            stroke-dashoffset="<?php echo $offset; ?>"
                                            stroke-linecap="round" 
                                            transform="rotate(-90 40 40)"/>
                                    <text x="40" y="45" text-anchor="middle" font-size="16" font-weight="bold" fill="#333">
                                        <?php echo round($completionRate); ?>%
                                    </text>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-tasks stat-icon primary"></i>
                            <h3 class="text-primary mb-2"><?php echo $stats['total_tasks']; ?></h3>
                            <p class="mb-0 text-muted">Tổng nhiệm vụ</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-check-circle stat-icon success"></i>
                            <h3 class="text-success mb-2"><?php echo $stats['completed_tasks']; ?></h3>
                            <p class="mb-0 text-muted">Đã hoàn thành</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-clock stat-icon warning"></i>
                            <h3 class="text-warning mb-2"><?php echo $stats['in_progress_tasks']; ?></h3>
                            <p class="mb-0 text-muted">Đang làm</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-list-alt stat-icon info"></i>
                            <h3 class="text-info mb-2"><?php echo count($availableTasks); ?></h3>
                            <p class="mb-0 text-muted">Nhiệm vụ có sẵn</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="task-card text-center">
                            <i class="fas fa-tag fa-3x text-primary mb-3"></i>
                            <h5>Gán nhãn văn bản đơn</h5>
                            <p class="text-muted mb-3">Xử lý từng văn bản riêng lẻ với bản tóm tắt AI tương ứng</p>
                            <a href="labeling.php" class="action-btn">
                                <i class="fas fa-play me-2"></i>Bắt đầu
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="task-card text-center">
                            <i class="fas fa-copy fa-3x text-success mb-3"></i>
                            <h5>Gán nhãn đa văn bản</h5>
                            <p class="text-muted mb-3">Xử lý nhóm văn bản cùng chủ đề với bản tóm tắt chung</p>
                            <button class="action-btn success" onclick="selectMultiTask()">
                                <i class="fas fa-tags me-2"></i>Chọn nhóm
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="task-card text-center">
                            <i class="fas fa-tasks fa-3x text-info mb-3"></i>
                            <h5>Nhiệm vụ của tôi</h5>
                            <p class="text-muted mb-3">Xem và tiếp tục các nhiệm vụ đã được giao</p>
                            <a href="my_tasks.php" class="action-btn info">
                                <i class="fas fa-list me-2"></i>Xem tất cả
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Current Tasks -->
                <?php if (!empty($stats['my_tasks']) && $stats['in_progress_tasks'] > 0): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="task-card">
                            <h5 class="mb-3">
                                <i class="fas fa-clock me-2"></i>Nhiệm vụ đang làm
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tiêu đề</th>
                                            <th>Loại</th>
                                            <th>Tiến độ</th>
                                            <th>Ngày giao</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['my_tasks'] as $task): ?>
                                            <?php if ($task['status'] !== 'completed'): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-<?php echo isset($task['group_id']) ? 'copy' : 'file-text'; ?> text-<?php echo isset($task['group_id']) ? 'success' : 'primary'; ?> me-2"></i>
                                                        <strong><?php echo htmlspecialchars(substr($task['title'], 0, 40)); ?></strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo isset($task['group_id']) ? 'success' : 'primary'; ?>">
                                                        <?php echo isset($task['group_id']) ? 'Đa văn bản' : 'Đơn văn bản'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-<?php echo $task['progress'] < 30 ? 'danger' : ($task['progress'] < 70 ? 'warning' : 'success'); ?>" 
                                                             style="width: <?php echo $task['progress']; ?>%">
                                                            <?php echo $task['progress']; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($task['created_at'])); ?></td>
                                                <td>
                                                    <?php if (isset($task['group_id'])): ?>
                                                        <a href="multi_labeling.php?group_id=<?php echo $task['group_id']; ?>" 
                                                           class="btn btn-sm btn-success">
                                                            <i class="fas fa-play me-1"></i>Tiếp tục
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="labeling.php?document_id=<?php echo $task['document_id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="fas fa-play me-1"></i>Tiếp tục
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Available Tasks -->
                <?php if (!empty($availableTasks)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="task-card">
                            <h5 class="mb-3">
                                <i class="fas fa-list-alt me-2"></i>Nhiệm vụ có sẵn
                            </h5>
                            <div class="row">
                                <?php foreach (array_slice($availableTasks, 0, 6) as $task): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="badge bg-<?php echo $task['task_type'] === 'single' ? 'primary' : 'success'; ?>">
                                                    <?php echo $task['task_type'] === 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                                                </span>
                                                <small class="text-muted"><?php echo date('d/m', strtotime($task['created_at'])); ?></small>
                                            </div>
                                            <h6 class="card-title"><?php echo htmlspecialchars(substr($task['title'], 0, 30)); ?></h6>
                                            <p class="card-text small text-muted">
                                                Upload bởi <?php echo htmlspecialchars($task['uploaded_by_name']); ?>
                                            </p>
                                            <button class="btn btn-sm btn-outline-<?php echo $task['task_type'] === 'single' ? 'primary' : 'success'; ?>" 
                                                    onclick="<?php echo $task['task_type'] === 'single' ? 'assignSingleTask(' . $task['id'] . ')' : 'assignGroupTask(' . $task['id'] . ')'; ?>">
                                                <i class="fas fa-hand-paper me-1"></i>Nhận nhiệm vụ
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Empty State -->
                <?php if (empty($stats['my_tasks']) && empty($availableTasks)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="task-card text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted mb-3">Chưa có nhiệm vụ nào</h4>
                            <p class="text-muted mb-4">
                                Hiện tại chưa có nhiệm vụ gán nhãn nào. Hãy liên hệ admin để được giao việc.
                            </p>
                            <button class="action-btn" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt me-2"></i>Làm mới
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectMultiTask() {
            // Show available multi tasks
            alert('Tính năng đang phát triển! Sẽ hiển thị danh sách nhóm văn bản có sẵn.');
        }

        function assignSingleTask(documentId) {
            if (confirm('Bạn có muốn nhận nhiệm vụ gán nhãn cho văn bản này?')) {
                // AJAX call to assign task
                window.location.href = `labeling.php?document_id=${documentId}`;
            }
        }

        function assignGroupTask(groupId) {
            if (confirm('Bạn có muốn nhận nhiệm vụ gán nhãn cho nhóm văn bản này?')) {
                // AJAX call to assign task
                window.location.href = `multi_labeling.php?group_id=${groupId}`;
            }
        }

        // Auto-refresh every 5 minutes
        setInterval(function() {
            console.log('Auto refresh dashboard...');
        }, 300000);
    </script>
</body>
</html>

<?php $conn->close(); ?>
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="labeling.php">
                            <i class="fas fa-tag me-2"></i>Gán nhãn đơn
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="multi_labeling.php">
                            <i class="fas fa-tags me-2"></i>Gán nhãn đa văn bản
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_tasks.php">
                            <i class="fas fa-tasks me-2"></i>Nhiệm vụ của tôi
                        