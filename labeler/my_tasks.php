<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is labeler
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'labeler') {
    header('Location: ../login.php');
    exit();
}

$functions = new Functions();
$userId = $_SESSION['user_id'];

// Handle task assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign_task' && isset($_POST['document_id'])) {
        $result = $functions->assignTask($_POST['document_id'], $userId);
        if ($result['success']) {
            $_SESSION['success_message'] = 'Đã nhận nhiệm vụ thành công!';
            header('Location: labeling.php?task_id=' . $result['labeling_id']);
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
}

// Get labeler's tasks and statistics
$myTasks = $functions->getLabelerTasks($userId);
$completedTasks = $functions->getCompletedTasks($userId);
$availableTasks = $functions->getAvailableTasks('single', 10);
$stats = $functions->getLabelerStats($userId);

// Calculate some display stats
$totalTasks = count($myTasks['data'] ?? []);
$completedCount = count($completedTasks['data'] ?? []);
$inProgressCount = count(array_filter($myTasks['data'] ?? [], function($task) {
    return $task['status'] === 'in_progress';
}));
$assignedCount = count(array_filter($myTasks['data'] ?? [], function($task) {
    return $task['status'] === 'assigned';
}));
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhiệm vụ của tôi - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            margin: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        .stats-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        .task-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .task-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.15);
            transform: translateY(-2px);
        }
        .progress-ring {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
        }
        .progress-ring-circle {
            stroke: #e5e7eb;
            stroke-width: 8;
            fill: transparent;
        }
        .progress-ring-progress {
            stroke: #3b82f6;
            stroke-width: 8;
            fill: transparent;
            stroke-linecap: round;
            stroke-dasharray: 251.2;
            stroke-dashoffset: 251.2;
            transition: stroke-dashoffset 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-tasks me-2 text-primary"></i>Nhiệm vụ của tôi</h2>
                <p class="text-muted mb-0">Quản lý và thực hiện các nhiệm vụ gán nhãn</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i>Dashboard
                </a>
                <a href="multi_labeling.php" class="btn btn-success">
                    <i class="fas fa-copy me-1"></i>Gán nhãn đa văn bản
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-5">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="progress-ring">
                        <svg width="80" height="80">
                            <circle class="progress-ring-circle" cx="40" cy="40" r="36"></circle>
                            <circle class="progress-ring-progress" cx="40" cy="40" r="36" 
                                    style="stroke-dashoffset: <?php echo 251.2 - (251.2 * ($stats['data']['completion_rate'] ?? 0) / 100); ?>"></circle>
                        </svg>
                        <div class="position-absolute" style="top: 50%; left: 50%; transform: translate(-50%, -50%);">
                            <strong><?php echo $stats['data']['completion_rate'] ?? 0; ?>%</strong>
                        </div>
                    </div>
                    <h5 class="text-primary"><?php echo $totalTasks; ?></h5>
                    <small class="text-muted">Tổng nhiệm vụ</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h5 class="text-success"><?php echo $completedCount; ?></h5>
                    <small class="text-muted">Đã hoàn thành</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                    <h5 class="text-warning"><?php echo $inProgressCount; ?></h5>
                    <small class="text-muted">Đang làm</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-inbox fa-3x text-info mb-3"></i>
                    <h5 class="text-info"><?php echo $assignedCount; ?></h5>
                    <small class="text-muted">Đã giao</small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- My Current Tasks -->
            <div class="col-lg-8">
                <h4 class="mb-3"><i class="fas fa-list-ul me-2"></i>Nhiệm vụ hiện tại</h4>
                
                <?php if (empty($myTasks['data'])): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Bạn chưa có nhiệm vụ nào. Hãy chọn nhiệm vụ từ danh sách bên cạnh.
                    </div>
                <?php else: ?>
                    <?php foreach ($myTasks['data'] as $task): ?>
                        <div class="task-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        Tạo: <?php echo $task['created_at_formatted']; ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?php 
                                    echo $task['status'] === 'completed' ? 'success' : 
                                        ($task['status'] === 'in_progress' ? 'warning' : 'secondary'); 
                                ?>">
                                    <?php echo $task['status_text']; ?>
                                </span>
                            </div>
                            
                            <div class="progress mb-3" style="height: 6px;">
                                <div class="progress-bar bg-<?php 
                                    echo $task['status'] === 'completed' ? 'success' : 
                                        ($task['status'] === 'in_progress' ? 'warning' : 'secondary'); 
                                ?>" 
                                     style="width: <?php echo $task['progress']; ?>%"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Tiến độ: <?php echo $task['progress']; ?>%</small>
                                </div>
                                <div>
                                    <?php if ($task['status'] !== 'completed'): ?>
                                        <a href="labeling.php?task_id=<?php echo $task['id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-play me-1"></i>
                                            <?php echo $task['status'] === 'assigned' ? 'Bắt đầu' : 'Tiếp tục'; ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="labeling.php?task_id=<?php echo $task['id']; ?>&view=1" 
                                           class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-eye me-1"></i>Xem kết quả
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Available Tasks -->
            <div class="col-lg-4">
                <h4 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Nhiệm vụ có sẵn</h4>
                
                <?php if (empty($availableTasks['data'])): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Hiện tại không có nhiệm vụ nào có sẵn.
                    </div>
                <?php else: ?>
                    <?php foreach ($availableTasks['data'] as $task): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h6>
                                <p class="card-text small text-muted">
                                    <?php echo htmlspecialchars($task['content_preview']); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-file-word me-1"></i>
                                        <?php echo number_format($task['word_count']); ?> từ
                                    </small>
                                    <small class="text-muted">
                                        <?php echo $task['created_at_formatted']; ?>
                                    </small>
                                </div>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="assign_task">
                                    <input type="hidden" name="document_id" value="<?php echo $task['id']; ?>">
                                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                        <i class="fas fa-hand-paper me-1"></i>Nhận nhiệm vụ
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add loading animation to assign buttons
        document.querySelectorAll('form button[type="submit"]').forEach(button => {
            button.addEventListener('click', function() {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang xử lý...';
                this.disabled = true;
                
                // Re-enable after timeout in case of error
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 5000);
            });
        });
    </script>
</body>
</html>