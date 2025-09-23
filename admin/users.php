<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is admin
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Handle user creation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $full_name = sanitizeInput($_POST['full_name']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Validate input
    if (empty($username) || empty($email) || empty($full_name) || empty($password) || empty($role)) {
        $error = "Vui lòng điền đầy đủ thông tin";
    } else {
        // Check if username or email already exists
        $check_query = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$username, $email]);
        
        if ($check_stmt->fetchColumn() > 0) {
            $error = "Username hoặc email đã tồn tại";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $query = "INSERT INTO users (username, password, email, full_name, role, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, 'active', NOW())";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$username, $hashed_password, $email, $full_name, $role])) {
                $message = "Tạo user thành công!";
                
                // Log activity
                logActivity($db, $_SESSION['user_id'], 'create_user', 'user', $db->lastInsertId());
            } else {
                $error = "Không thể tạo user";
            }
        }
    }
}

// Handle user update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $user_id = $_POST['user_id'];
    $username = sanitizeInput($_POST['edit_username']);
    $email = sanitizeInput($_POST['edit_email']);
    $full_name = sanitizeInput($_POST['edit_full_name']);
    $role = $_POST['edit_role'];
    $status = $_POST['edit_status'];
    
    $query = "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, status = ?, updated_at = NOW() 
             WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$username, $email, $full_name, $role, $status, $user_id])) {
        $message = "Cập nhật user thành công!";
        
        // Log activity
        logActivity($db, $_SESSION['user_id'], 'update_user', 'user', $user_id);
    } else {
        $error = "Không thể cập nhật user";
    }
}

// Handle password reset
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];
    
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$hashed_password, $user_id])) {
            $message = "Reset mật khẩu thành công!";
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], 'reset_password', 'user', $user_id);
        } else {
            $error = "Không thể reset mật khẩu";
        }
    }
}

// Handle user deletion
if ($_GET && isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // Don't allow deleting self
    if ($user_id != $_SESSION['user_id']) {
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$user_id])) {
            $message = "Xóa user thành công!";
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], 'delete_user', 'user', $user_id);
        } else {
            $error = "Không thể xóa user";
        }
    } else {
        $error = "Không thể xóa chính mình";
    }
}

// Get all users with statistics
$query = "SELECT u.*, 
    COALESCE(task_stats.total_tasks, 0) as total_tasks,
    COALESCE(task_stats.completed_tasks, 0) as completed_tasks,
    COALESCE(review_stats.total_reviews, 0) as total_reviews
FROM users u
LEFT JOIN (
    SELECT assigned_to, 
           COUNT(*) as total_tasks,
           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
    FROM labeling_tasks 
    GROUP BY assigned_to
) task_stats ON u.id = task_stats.assigned_to
LEFT JOIN (
    SELECT reviewer_id, COUNT(*) as total_reviews
    FROM reviews 
    GROUP BY reviewer_id
) review_stats ON u.id = review_stats.reviewer_id
ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Users - Text Labeling System</title>
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
        .badge-role {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
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
                        <a href="users.php" class="nav-link active mb-2">
                            <i class="fas fa-users me-2"></i>Quản lý Users
                        </a>
                        <a href="upload.php" class="nav-link mb-2">
                            <i class="fas fa-upload me-2"></i>Upload Dữ liệu
                        </a>
                        <a href="documents.php" class="nav-link mb-2">
                            <i class="fas fa-file-text me-2"></i>Quản lý Văn bản
                        </a>
                        <a href="tasks.php" class="nav-link mb-2">
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
                            <h2>Quản lý Users</h2>
                            <p class="text-muted">Tạo và quản lý tài khoản người dùng</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                            <i class="fas fa-plus me-2"></i>Tạo User mới
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

                    <!-- Users Table -->
                    <div class="table-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="fas fa-users me-2"></i>Danh sách Users</h5>
                            <small class="text-muted">Tổng: <?php echo count($users); ?> users</small>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Thống kê</th>
                                        <th>Ngày tạo</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3" style="background: <?php 
                                                        $colors = ['#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8'];
                                                        echo $colors[$user['id'] % count($colors)];
                                                    ?>">
                                                        <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge badge-role <?php 
                                                    echo $user['role'] === 'admin' ? 'bg-danger' : 
                                                        ($user['role'] === 'labeler' ? 'bg-primary' : 'bg-success');
                                                ?>">
                                                    <?php 
                                                    $role_names = [
                                                        'admin' => 'Admin',
                                                        'labeler' => 'Labeler', 
                                                        'reviewer' => 'Reviewer'
                                                    ];
                                                    echo $role_names[$user['role']] ?? $user['role'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $user['status'] === 'active' ? 'Hoạt động' : 'Không hoạt động'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php if ($user['role'] === 'labeler'): ?>
                                                        Tasks: <?php echo $user['completed_tasks']; ?>/<?php echo $user['total_tasks']; ?>
                                                    <?php elseif ($user['role'] === 'reviewer'): ?>
                                                        Reviews: <?php echo $user['total_reviews']; ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'N/A'; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Bạn</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus me-2"></i>Tạo User mới
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Họ tên</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="">-- Chọn role --</option>
                                <option value="labeler">Labeler</option>
                                <option value="reviewer">Reviewer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Tạo User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit me-2"></i>Chỉnh sửa User
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="edit_username" id="edit_username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="edit_email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Họ tên</label>
                            <input type="text" class="form-control" name="edit_full_name" id="edit_full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="edit_role" id="edit_role" required>
                                <option value="labeler">Labeler</option>
                                <option value="reviewer">Reviewer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="edit_status" id="edit_status" required>
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Không hoạt động</option>
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

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-key me-2"></i>Reset Mật khẩu
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Reset mật khẩu cho user: <strong id="reset_username"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu mới</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_full_name').value = user.full_name || user.username;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function resetPassword(userId, username) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_username').textContent = username;
            
            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        }

        function deleteUser(userId, username) {
            if (confirm(`Bạn có chắc chắn muốn xóa user "${username}"?`)) {
                window.location.href = `?delete=${userId}`;
            }
        }
    </script>
</body>
</html>