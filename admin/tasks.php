<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Handle task creation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_task') {
    $document_id = $_POST['document_id'] ?? null;
    $group_id = $_POST['group_id'] ?? null;
    $assigned_to = $_POST['assigned_to'];
    $priority = $_POST['priority'] ?? 'medium';
    
    try {
        $query = "INSERT INTO labeling_tasks (document_id, group_id, assigned_to, priority, assigned_by, assigned_at) 
                 VALUES (:document_id, :group_id, :assigned_to, :priority, :assigned_by, NOW())";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':document_id', $document_id);
        $stmt->bindParam(':group_id', $group_id);
        $stmt->bindParam(':assigned_to', $assigned_to);
        $stmt->bindParam(':priority', $priority);
        $stmt->bindParam(':assigned_by', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $message = "Task được tạo thành công!";
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], 'create_task', 'task', $db->lastInsertId());
        } else {
            $error = "Không thể tạo task";
        }
    } catch (Exception $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}

// Handle task update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_task') {
    $task_id = $_POST['task_id'];
    $assigned_to = $_POST['assigned_to'];
    $priority = $_POST['priority'];
    $status = $_POST['status'];
    
    try {
        $query = "UPDATE labeling_tasks SET assigned_to = :assigned_to, priority = :priority, status = :status 
                 WHERE id = :task_id";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':assigned_to', $assigned_to);
        $stmt->bindParam(':priority', $priority);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':task_id', $task_id);
        
        if ($stmt->execute()) {
            $message = "Task được cập nhật thành công!";
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], 'update_task', 'task', $task_id);
        } else {
            $error = "Không thể cập nhật task";
        }
    } catch (Exception $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}

// Get all tasks
$query = "SELECT t.*, 
    COALESCE(d.title, dg.title) as title,
    d.type,
    u_assigned.full_name as assigned_to_name,
    u_assigned.username as assigned_to_username,
    u_creator.full_name as created_by_name
FROM labeling_tasks t
LEFT JOIN documents d ON t.document_id = d.id
LEFT JOIN document_groups dg ON t.group_id = dg.id
LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
LEFT JOIN users u_creator ON t.assigned_by = u_creator.id
ORDER BY t.assigned_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available documents for task creation
$query = "SELECT d.id, d.title, d.type, 'document' as source_type FROM documents d
WHERE d.id NOT IN (SELECT document_id FROM labeling_tasks WHERE document_id IS NOT NULL)
UNION ALL
SELECT dg.id, dg.title, 'multi' as type, 'group' as source_type FROM document_groups dg
WHERE dg.id NOT IN (SELECT group_id FROM labeling_tasks WHERE group_id IS NOT NULL)
ORDER BY title";
$stmt = $db->prepare($query);
$stmt->execute();
$available_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get labelers
$query = "SELECT id, full_name, username FROM users WHERE role = 'labeler' AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$labelers = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Tasks - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }
        .main-content {
            padding: 30px;
        }
        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
        }
        .badge-status {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .priority-high {
            color: #dc3545;
        }
        .priority-medium {
            color: #ffc107;
        }
        .priority-low {
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-4">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-tags me-2"></i>
                        Admin Panel
                    </h4>
                    <div class="nav flex-column">
                        <a href="dashboard.php" class="nav-link mb-2">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="users.php" class="nav-link mb-2">
                            <i class="fas fa-users me-2"></i>Quản lý Users
                        </a>
                        <a href="upload.php" class="nav-link mb-2">
                            <i class="fas fa-upload me-2"></i>Upload Dữ liệu
                        </a>
                        <a href="tasks.php" class="nav-link active mb-2">
                            <i class="fas fa-tasks me-2"></i>Quản lý Tasks
                        </a>
                        <a href="reports.php" class="nav-link mb-2">
                            <i class="fas fa-chart-bar me-2"></i>Báo cáo
                        </a>
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                        <a href="../logout.php" class="nav-link text-warning">
                            <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2>Quản lý Tasks</h2>
                            <p class="text-muted">Tạo và quản lý các task gán nhãn</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                            <i class="fas fa-plus me-2"></i>Tạo Task mới
                        </button>
                    </div>

                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Tasks Table -->
                    <div class="table-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="fas fa-list me-2"></i>Danh sách Tasks</h5>
                            <small class="text-muted">Tổng: <?php echo count($tasks); ?> tasks</small>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Tài liệu</th>
                                        <th>Loại</th>
                                        <th>Người thực hiện</th>
                                        <th>Ưu tiên</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                        <tr>
                                            <td><strong>#<?php echo $task['id']; ?></strong></td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($task['title']); ?></div>
                                                <small class="text-muted">Tạo bởi: <?php echo htmlspecialchars($task['created_by_name']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $task['type'] === 'single' ? 'bg-primary' : 'bg-success'; ?>">
                                                    <?php echo $task['type'] === 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($task['assigned_to_name']); ?></div>
                                                <small class="text-muted">@<?php echo htmlspecialchars($task['assigned_to_username']); ?></small>
                                            </td>
                                            <td>
                                                <i class="fas fa-circle priority-<?php echo $task['priority']; ?>"></i>
                                                <span class="text-capitalize"><?php echo $task['priority']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-status 
                                                    <?php 
                                                    echo $task['status'] === 'completed' ? 'bg-success' : 
                                                         ($task['status'] === 'in_progress' ? 'bg-warning' : 'bg-secondary');
                                                    ?>">
                                                    <?php 
                                                    $status_text = [
                                                        'pending' => 'Chờ xử lý',
                                                        'in_progress' => 'Đang làm',
                                                        'completed' => 'Hoàn thành'
                                                    ];
                                                    echo $status_text[$task['status']] ?? $task['status'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('d/m/Y H:i', strtotime($task['assigned_at'])); ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editTask(<?php echo htmlspecialchars(json_encode($task)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTask(<?php echo $task['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($tasks)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <br>Chưa có task nào được tạo
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Task Modal -->
    <div class="modal fade" id="createTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create_task">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus me-2"></i>Tạo Task mới
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Chọn tài liệu/nhóm tài liệu</label>
                            <select class="form-select" name="document_or_group" onchange="handleDocumentSelection(this)" required>
                                <option value="">-- Chọn tài liệu --</option>
                                <?php foreach ($available_items as $item): ?>
                                    <option value="<?php echo $item['source_type'] . '|' . $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                        (<?php echo $item['type'] === 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="document_id" id="hidden_document_id">
                            <input type="hidden" name="group_id" id="hidden_group_id">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Giao cho Labeler</label>
                            <select class="form-select" name="assigned_to" required>
                                <option value="">-- Chọn Labeler --</option>
                                <?php foreach ($labelers as $labeler): ?>
                                    <option value="<?php echo $labeler['id']; ?>">
                                        <?php echo htmlspecialchars($labeler['full_name']); ?>
                                        (@<?php echo htmlspecialchars($labeler['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Độ ưu tiên</label>
                            <select class="form-select" name="priority">
                                <option value="low">Thấp</option>
                                <option value="medium" selected>Trung bình</option>
                                <option value="high">Cao</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Tạo Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div class="modal fade" id="editTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_task">
                    <input type="hidden" name="task_id" id="edit_task_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit me-2"></i>Chỉnh sửa Task
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Giao cho Labeler</label>
                            <select class="form-select" name="assigned_to" id="edit_assigned_to" required>
                                <?php foreach ($labelers as $labeler): ?>
                                    <option value="<?php echo $labeler['id']; ?>">
                                        <?php echo htmlspecialchars($labeler['full_name']); ?>
                                        (@<?php echo htmlspecialchars($labeler['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Độ ưu tiên</label>
                            <select class="form-select" name="priority" id="edit_priority">
                                <option value="low">Thấp</option>
                                <option value="medium">Trung bình</option>
                                <option value="high">Cao</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="pending">Chờ xử lý</option>
                                <option value="in_progress">Đang làm</option>
                                <option value="completed">Hoàn thành</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Cập nhật
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function handleDocumentSelection(select) {
            const value = select.value;
            const [type, id] = value.split('|');
            
            if (type === 'document') {
                document.getElementById('hidden_document_id').value = id;
                document.getElementById('hidden_group_id').value = '';
            } else if (type === 'group') {
                document.getElementById('hidden_document_id').value = '';
                document.getElementById('hidden_group_id').value = id;
            }
        }

        function editTask(task) {
            document.getElementById('edit_task_id').value = task.id;
            document.getElementById('edit_assigned_to').value = task.assigned_to;
            document.getElementById('edit_priority').value = task.priority;
            document.getElementById('edit_status').value = task.status;
            
            new bootstrap.Modal(document.getElementById('editTaskModal')).show();
        }

        function deleteTask(taskId) {
            if (confirm('Bạn có chắc chắn muốn xóa task này?')) {
                // Implement delete functionality
                window.location.href = `?delete_task=${taskId}`;
            }
        }
    </script>
</body>
</html>