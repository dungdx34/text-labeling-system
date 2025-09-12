<?php
require_once '../includes/auth.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'text_labeling_system');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_user':
            $username = trim($_POST['username']);
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            
            // Validation
            if (empty($username) || empty($email) || empty($password) || empty($role)) {
                $message = 'Vui lòng điền đầy đủ thông tin bắt buộc!';
                $messageType = 'danger';
            } else {
                // Check if username or email exists
                $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $check->bind_param("ss", $username, $email);
                $check->execute();
                $result = $check->get_result();
                
                if ($result->num_rows > 0) {
                    $message = 'Username hoặc email đã tồn tại!';
                    $messageType = 'danger';
                } else {
                    // Hash password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user
                    $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password, role, status, is_active, created_at) VALUES (?, ?, ?, ?, ?, 'active', 1, NOW())");
                    $stmt->bind_param("sssss", $username, $full_name, $email, $hashedPassword, $role);
                    
                    if ($stmt->execute()) {
                        $message = 'Thêm người dùng thành công!';
                        $messageType = 'success';
                    } else {
                        $message = 'Lỗi thêm người dùng: ' . $conn->error;
                        $messageType = 'danger';
                    }
                }
            }
            break;
            
        case 'update_status':
            $userId = intval($_POST['user_id']);
            $status = $_POST['status'];
            $isActive = ($status === 'active') ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE users SET status = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("sii", $status, $isActive, $userId);
            
            if ($stmt->execute()) {
                $message = 'Cập nhật trạng thái thành công!';
                $messageType = 'success';
            } else {
                $message = 'Lỗi cập nhật: ' . $conn->error;
                $messageType = 'danger';
            }
            break;
            
        case 'delete_user':
            $userId = intval($_POST['user_id']);
            
            // Don't allow deleting current user
            if ($userId == $_SESSION['user_id']) {
                $message = 'Không thể xóa tài khoản đang đăng nhập!';
                $messageType = 'danger';
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                
                if ($stmt->execute()) {
                    $message = 'Xóa người dùng thành công!';
                    $messageType = 'success';
                } else {
                    $message = 'Lỗi xóa: ' . $conn->error;
                    $messageType = 'danger';
                }
            }
            break;
            
        case 'reset_password':
            $userId = intval($_POST['user_id']);
            $newPassword = $_POST['new_password'];
            
            if (empty($newPassword)) {
                $message = 'Mật khẩu không được để trống!';
                $messageType = 'danger';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $userId);
                
                if ($stmt->execute()) {
                    $message = 'Reset mật khẩu thành công!';
                    $messageType = 'success';
                } else {
                    $message = 'Lỗi reset mật khẩu: ' . $conn->error;
                    $messageType = 'danger';
                }
            }
            break;
    }
}

// Get all users
$users = [];
$result = $conn->query("
    SELECT u.*, 
           COUNT(l.id) as total_labelings,
           SUM(CASE WHEN l.status = 'completed' THEN 1 ELSE 0 END) as completed_labelings
    FROM users u
    LEFT JOIN labelings l ON u.id = l.labeler_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get user statistics
$stats = [];
$result = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats[$row['role']] = $row['count'];
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="enhanced_upload.php">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Enhanced Upload
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="users.php">
                            <i class="fas fa-users me-2"></i>Quản lý người dùng
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Báo cáo
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-10 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-users text-primary me-2"></i>Quản lý người dùng
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus me-2"></i>Thêm người dùng
                    </button>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Tổng người dùng
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo count($users); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Admin
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['admin'] ?? 0; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Labeler
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['labeler'] ?? 0; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-tag fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Người dùng hoạt động
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo count(array_filter($users, function($u) { return $u['status'] === 'active'; })); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Danh sách người dùng</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Họ tên</th>
                                    <th>Email</th>
                                    <th>Vai trò</th>
                                    <th>Trạng thái</th>
                                    <th>Thống kê</th>
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
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge bg-info">Bạn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['role'] === 'labeler'): ?>
                                            <small>
                                                Tổng: <?php echo $user['total_labelings']; ?><br>
                                                Hoàn thành: <?php echo $user['completed_labelings']; ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">N/A</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
                                                Thao tác
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="openStatusModal(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')">
                                                        <i class="fas fa-toggle-on me-2"></i>Đổi trạng thái
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="openPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                        <i class="fas fa-key me-2"></i>Reset mật khẩu
                                                    </a>
                                                </li>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                        <i class="fas fa-trash me-2"></i>Xóa
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Thêm người dùng mới
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Họ tên</label>
                        <input type="text" class="form-control" name="full_name">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vai trò <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" required>
                            <option value="">-- Chọn vai trò --</option>
                            <option value="admin">Admin</option>
                            <option value="labeler">Labeler</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Thêm người dùng
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Thay đổi trạng thái</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="user_id" id="statusUserId">
                    
                    <div class="mb-3">
                        <label class="form-label">Trạng thái mới:</label>
                        <select class="form-select" name="status" id="statusSelect">
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Không hoạt động</option>
                            <option value="suspended">Tạm khóa</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-warning">Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Reset mật khẩu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="passwordUserId">
                    
                    <p>Reset mật khẩu cho user: <strong id="passwordUsername"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới:</label>
                        <input type="password" class="form-control" name="new_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-warning">Reset mật khẩu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Xóa người dùng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Cảnh báo!</strong> Bạn có chắc chắn muốn xóa người dùng <strong id="deleteUsername"></strong>?
                        <br><small>Hành động này không thể hoàn tác!</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa người dùng
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }

.sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 48px 0 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
}

.sidebar-sticky {
    position: relative;
    top: 0;
    height: calc(100vh - 48px);
    padding-top: .5rem;
    overflow-x: hidden;
    overflow-y: auto;
}

.nav-link {
    color: #333;
    padding: 10px 20px;
    border-radius: 0;
    transition: all 0.3s ease;
}

.nav-link:hover {
    background: #f8f9fc;
    color: #5a5c69;
}

.nav-link.active {
    background: #4e73df;
    color: white !important;
}
</style>

<script>
function openStatusModal(userId, currentStatus) {
    document.getElementById('statusUserId').value = userId;
    document.getElementById('statusSelect').value = currentStatus;
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

function openPasswordModal(userId, username) {
    document.getElementById('passwordUserId').value = userId;
    document.getElementById('passwordUsername').textContent = username;
    const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
    modal.show();
}

function openDeleteModal(userId, username) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUsername').textContent = username;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?>