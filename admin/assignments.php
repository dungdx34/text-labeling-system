<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Kiểm tra quyền admin
requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$success_message = '';
$error_message = '';

// Xử lý phân công
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_assignment':
                $user_id = intval($_POST['user_id']);
                $document_id = !empty($_POST['document_id']) ? intval($_POST['document_id']) : null;
                $group_id = !empty($_POST['group_id']) ? intval($_POST['group_id']) : null;
                $type = $_POST['type'];
                $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
                $notes = trim($_POST['notes']);
                
                if ($type == 'single' && $document_id) {
                    $query = "INSERT INTO assignments (user_id, document_id, type, assigned_by, deadline, notes) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $user_id);
                    $stmt->bindParam(2, $document_id);
                    $stmt->bindParam(3, $type);
                    $stmt->bindParam(4, $current_user['id']);
                    $stmt->bindParam(5, $deadline);
                    $stmt->bindParam(6, $notes);
                    $stmt->execute();
                    $assignment_id = $db->lastInsertId();
                    
                    // Tạo labeling_results entry
                    $query = "INSERT INTO labeling_results (assignment_id, document_id) VALUES (?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $assignment_id);
                    $stmt->bindParam(2, $document_id);
                    $stmt->execute();
                    
                } elseif ($type == 'multi' && $group_id) {
                    $query = "INSERT INTO assignments (user_id, group_id, type, assigned_by, deadline, notes) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $user_id);
                    $stmt->bindParam(2, $group_id);
                    $stmt->bindParam(3, $type);
                    $stmt->bindParam(4, $current_user['id']);
                    $stmt->bindParam(5, $deadline);
                    $stmt->bindParam(6, $notes);
                    $stmt->execute();
                    $assignment_id = $db->lastInsertId();
                    
                    // Tạo labeling_results entries cho tất cả documents trong group
                    $query = "SELECT document_id FROM group_documents WHERE group_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $group_id);
                    $stmt->execute();
                    $group_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($group_docs as $gdoc) {
                        $query = "INSERT INTO labeling_results (assignment_id, document_id) VALUES (?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(1, $assignment_id);
                        $stmt->bindParam(2, $gdoc['document_id']);
                        $stmt->execute();
                    }
                }
                
                $success_message = 'Phân công công việc thành công!';
                break;
                
            case 'update_assignment':
                $assignment_id = intval($_POST['assignment_id']);
                $status = $_POST['status'];
                $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
                $notes = trim($_POST['notes']);
                
                $query = "UPDATE assignments SET status = ?, deadline = ?, notes = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $status);
                $stmt->bindParam(2, $deadline);
                $stmt->bindParam(3, $notes);
                $stmt->bindParam(4, $assignment_id);
                $stmt->execute();
                
                $success_message = 'Cập nhật assignment thành công!';
                break;
                
            case 'delete_assignment':
                $assignment_id = intval($_POST['assignment_id']);
                
                // Xóa labeling_results trước
                $query = "DELETE FROM labeling_results WHERE assignment_id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $assignment_id);
                $stmt->execute();
                
                // Xóa assignment
                $query = "DELETE FROM assignments WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $assignment_id);
                $stmt->execute();
                
                $success_message = 'Xóa assignment thành công!';
                break;
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi: ' . $e->getMessage();
    }
}

// Lấy danh sách assignments
try {
    $query = "SELECT a.*, u.full_name as assignee_name, admin.full_name as assigned_by_name,
                     CASE 
                         WHEN a.type = 'single' THEN d.title 
                         WHEN a.type = 'multi' THEN dg.title 
                     END as title
              FROM assignments a 
              JOIN users u ON a.user_id = u.id 
              JOIN users admin ON a.assigned_by = admin.id
              LEFT JOIN documents d ON a.document_id = d.id AND a.type = 'single'
              LEFT JOIN document_groups dg ON a.group_id = dg.id AND a.type = 'multi'
              ORDER BY a.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách labelers
    $query = "SELECT id, full_name FROM users WHERE role = 'labeler' AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $labelers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách documents
    $query = "SELECT id, title FROM documents WHERE status = 'active' ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách document groups
    $query = "SELECT id, title FROM document_groups WHERE status = 'active' ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $document_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy dữ liệu: ' . $e->getMessage();
    $assignments = [];
    $labelers = [];
    $documents = [];
    $document_groups = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phân công công việc - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center text-white mb-4">
            <i class="fas fa-tags fa-2x mb-2"></i>
            <h5>Admin Panel</h5>
            <small>Xin chào, <?php echo htmlspecialchars($current_user['full_name']); ?></small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-2"></i>Quản lý người dùng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="upload.php">
                    <i class="fas fa-upload me-2"></i>Upload văn bản
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="documents.php">
                    <i class="fas fa-file-text me-2"></i>Quản lý văn bản
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="assignments.php">
                    <i class="fas fa-tasks me-2"></i>Phân công công việc
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Báo cáo
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
            <h2 class="text-dark">Phân công công việc</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                <i class="fas fa-plus me-2"></i>Tạo assignment
            </button>
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

        <!-- Assignments Table -->
        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Người gán nhãn</th>
                            <th>Văn bản/Nhóm</th>
                            <th>Loại</th>
                            <th>Trạng thái</th>
                            <th>Deadline</th>
                            <th>Phân công bởi</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <div>Chưa có assignment nào</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td>#<?php echo $assignment['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($assignment['assignee_name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($assignment['title']); ?>
                                        <?php if ($assignment['notes']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($assignment['notes'], 0, 50)); ?>...</small>
                                        <?php endif; ?>
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
                                        <?php if ($assignment['deadline']): ?>
                                            <?php 
                                            $deadline = new DateTime($assignment['deadline']);
                                            $now = new DateTime();
                                            $is_overdue = $deadline < $now && $assignment['status'] != 'completed';
                                            ?>
                                            <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                                <?php echo $deadline->format('d/m/Y'); ?>
                                                <?php if ($is_overdue): ?>
                                                    <i class="fas fa-exclamation-triangle ms-1"></i>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Không giới hạn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($assignment['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editAssignment(<?php echo htmlspecialchars(json_encode($assignment)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteAssignment(<?php echo $assignment['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Assignment Modal -->
    <div class="modal fade" id="createAssignmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tạo assignment mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_assignment">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Người gán nhãn</label>
                                    <select class="form-select" name="user_id" required>
                                        <option value="">Chọn người gán nhãn</option>
                                        <?php foreach ($labelers as $labeler): ?>
                                            <option value="<?php echo $labeler['id']; ?>">
                                                <?php echo htmlspecialchars($labeler['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Loại assignment</label>
                                    <select class="form-select" name="type" id="assignmentType" onchange="toggleDocumentSelect()" required>
                                        <option value="">Chọn loại</option>
                                        <option value="single">Đơn văn bản</option>
                                        <option value="multi">Đa văn bản</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3"