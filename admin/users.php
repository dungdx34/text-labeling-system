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

// Xử lý thêm/sửa/xóa user
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_user':
                    $username = trim($_POST['username']);
                    $password = trim($_POST['password']);
                    $full_name = trim($_POST['full_name']);
                    $email = trim($_POST['email']);
                    $role = trim($_POST['role']);
                    
                    // Kiểm tra username đã tồn tại
                    $query = "SELECT id FROM users WHERE username = ?";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $username);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $error_message = 'Tên đăng nhập đã tồn tại!';
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $query = "INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(1, $username);
                        $stmt->bindParam(2, $hashed_password);
                        $stmt->bindParam(3, $full_name);
                        $stmt->bindParam(4, $email);
                        $stmt->bindParam(5, $role);
                        $stmt->execute();
                        
                        $success_message = 'Thêm người dùng thành công!';
                    }
                    break;
                    
                case 'edit_user':
                    $user_id = intval($_POST['user_id']);
                    $full_name = trim($_POST['full_name']);
                    $email = trim($_POST['email']);
                    $role = trim($_POST['role']);
                    $status = trim($_POST['status']);
                    
                    $query = "UPDATE users SET full_name = ?, email = ?, role = ?, status = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $full_name);
                    $stmt->bindParam(2, $email);
                    $stmt->bindParam(3, $role);
                    $stmt->bindParam(4, $status);
                    $stmt->bindParam(5, $user_id);
                    $stmt->execute();
                    
                    $success_message = 'Cập nhật người dùng thành công!';
                    break;
                    
                case 'delete_user':
                    $user_id = intval($_POST['user_id']);
                    if ($user_id != $current_user['id']) { // Không cho phép xóa chính mình
                        $query = "UPDATE users SET status = 'inactive' WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(1, $user_id);
                        $stmt->execute();
                        
                        $success_message = 'Xóa người dùng thành công!';
                    } else {
                        $error_message = 'Không thể xóa tài khoản của chính bạn!';
                    }
                    break;
                    
                case 'reset_password':
                    $user_id = intval($_POST['user_id']);
                    $new_password = 'password'; // Default password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $query = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $hashed_password);
                    $stmt->bindParam(2, $user_id);
                    $stmt->execute();
                    
                    $success_message = 'Reset mật khẩu thành công! Mật khẩu mới: password';
                    break;
            }
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi: ' . $e->getMessage();
    }
}

// Lấy danh sách users
try {
    $query = "SELECT * FROM users ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy danh sách người dùng: ' . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng - Text Labeling System</title>
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
                <a class="nav-link active" href="users.php">
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
                <a class="nav-link" href="assignments.php">
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
            <h2 class="text-dark">Quản lý người dùng</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus me-2"></i>Thêm người dùng
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

        <!-- Users Table -->
        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tên đăng nhập</th>
                            <th>Họ tên</th>
                            <th>Email</th>
                            <th>Vai trò</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php
                                    $role_class = '';
                                    $role_text = '';
                                    switch ($user['role']) {
                                        case 'admin':
                                            $role_class = 'bg-danger';
                                            $role_text = 'Admin';
                                            break;
                                        case 'labeler':
                                            $role_class = 'bg-primary';
                                            $role_text = 'Labeler';
                                            break;
                                        case 'reviewer':
                                            $role_class = 'bg-success';
                                            $role_text = 'Reviewer';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $role_class; ?>"><?php echo $role_text; ?></span>
                                </td>
                                <td>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <span class="badge bg-success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Không hoạt động</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="resetPassword(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($user['id'] != $current_user['id']): ?>
                                            <button class="btn btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Thêm người dùng mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        
                        <div class="mb-3">
                            <label class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Họ tên</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" name="role" required>
                                <option value="">Chọn vai trò</option>
                                <option value="admin">Admin</option>
                                <option value="labeler">Labeler</option>
                                <option value="reviewer">Reviewer</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Thêm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chỉnh sửa người dùng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" id="edit_username" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Họ tên</label>
                            <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="admin">Admin</option>
                                <option value="labeler">Labeler</option>
                                <option value="reviewer">Reviewer</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Không hoạt động</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Cập nhật</button>
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
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function resetPassword(userId) {
            if (confirm('Bạn có chắc muốn reset mật khẩu cho user này? Mật khẩu mới sẽ là "password".')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteUser(userId) {
            if (confirm('Bạn có chắc muốn xóa user này?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>