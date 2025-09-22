<?php
// admin/users.php - Fixed User Management
require_once '../config/database.php';
require_once '../includes/auth.php';

Auth::requireLogin('admin');

$database = new Database();
$pdo = $database->getConnection();

$message = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        
        // Validation
        if (empty($username) || empty($password) || empty($full_name) || empty($email) || empty($role)) {
            $error = 'Vui lòng điền đầy đủ thông tin';
        } elseif (strlen($password) < 6) {
            $error = 'Mật khẩu phải có ít nhất 6 ký tự';
        } else {
            try {
                // Check if username exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Tên đăng nhập đã tồn tại';
                } else {
                    // Check if email exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'Email đã tồn tại';
                    } else {
                        // Create user
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $hashed_password, $full_name, $email, $role]);
                        $message = "Tạo user '$username' thành công!";
                    }
                }
            } catch (Exception $e) {
                $error = 'Lỗi tạo user: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_user'])) {
        $user_id = $_POST['user_id'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $role, $status, $user_id]);
            $message = 'Cập nhật user thành công!';
        } catch (Exception $e) {
            $error = 'Lỗi cập nhật user: ' . $e->getMessage();
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        
        try {
            // Don't delete admin user
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user['username'] === 'admin') {
                $error = 'Không thể xóa tài khoản admin';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'Xóa user thành công!';
            }
        } catch (Exception $e) {
            $error = 'Lỗi xóa user: ' . $e->getMessage();
        }
    }
}

// Get all users
try {
    $users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
    $error = 'Lỗi tải danh sách users: ' . $e->getMessage();
}

$current_user = Auth::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Users - Text Labeling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .user-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .role-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 15px;
        }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark gradient-bg">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tags me-2"></i>Text Labeling System
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="upload_jsonl.php">
                    <i class="fas fa-file-code me-1"></i>Upload JSONL
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="text-primary">
                            <i class="fas fa-users me-2"></i>Quản lý Users
                        </h2>
                        <p class="text-muted">Tạo và quản lý tài khoản người dùng</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-plus me-2"></i>Thêm User
                    </button>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Users Grid -->
        <div class="row">
            <?php foreach ($users as $user): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card user-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($user['full_name']); ?>
                                    </h5>
                                    <p class="text-muted mb-0">@<?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="editUser(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-edit me-2"></i>Chỉnh sửa
                                            </a>
                                        </li>
                                        <?php if ($user['username'] !== 'admin'): ?>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash me-2"></i>Xóa
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Email:</small><br>
                                <span><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">Vai trò:</small><br>
                                <span class="badge role-badge bg-<?php 
                                    echo $user['role'] === 'admin' ? 'danger' : 
                                        ($user['role'] === 'reviewer' ? 'info' : 'success'); 
                                ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Trạng thái:</small><br>
                                <span class="status-indicator bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>"></span>
                                <?php echo $user['status'] === 'active' ? 'Hoạt động' : 'Vô hiệu hóa'; ?>
                            </div>
                            
                            <div class="text-muted small">
                                <i class="fas fa-calendar me-1"></i>
                                Tạo: <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($users)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h4>Chưa có user nào</h4>
                            <p class="text-muted">Tạo user đầu tiên để bắt đầu sử dụng hệ thống</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                <i class="fas fa-plus me-2"></i>Tạo User Đầu Tiên
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Tạo User Mới
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Tên đăng nhập *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu *</label>
                            <input type="password" class="form-control" name="password" required minlength="6">
                            <div class="form-text">Ít nhất 6 ký tự</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Họ và tên *</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vai trò *</label>
                            <select class="form-select" name="role" required>
                                <option value="">Chọn vai trò...</option>
                                <option value="admin">Admin</option>
                                <option value="labeler">Labeler</option>
                                <option value="reviewer">Reviewer</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" name="create_user" class="btn btn-primary">
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
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Chỉnh sửa User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Họ và tên *</label>
                            <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vai trò *</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="admin">Admin</option>
                                <option value="labeler">Labeler</option>
                                <option value="reviewer">Reviewer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Vô hiệu hóa</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" name="update_user" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Xác nhận xóa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn xóa user <strong id="delete_username"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-warning me-1"></i>
                        Hành động này không thể hoàn tác!
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <button type="submit" name="delete_user" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Xóa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User data for editing
        const users = <?php echo json_encode($users); ?>;

        function editUser(userId) {
            const user = users.find(u => u.id == userId);
            if (user) {
                document.getElementById('edit_user_id').value = user.id;
                document.getElementById('edit_full_name').value = user.full_name;
                document.getElementById('edit_email').value = user.email;
                document.getElementById('edit_role').value = user.role;
                document.getElementById('edit_status').value = user.status;
                
                new bootstrap.Modal(document.getElementById('editUserModal')).show();
            }
        }

        function deleteUser(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_username').textContent = username;
            
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }

        // Form validation
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            const fullName = document.getElementById('edit_full_name').value.trim();
            const email = document.getElementById('edit_email').value.trim();
            
            if (!fullName || !email) {
                e.preventDefault();
                alert('Vui lòng điền đầy đủ thông tin');
            }
        });
    </script>
</body>
</html>