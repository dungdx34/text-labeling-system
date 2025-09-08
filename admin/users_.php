<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Quản lý người dùng';

// Use absolute paths to avoid path issues
$auth_path = __DIR__ . '/../includes/auth.php';
$functions_path = __DIR__ . '/../includes/functions.php';
$header_path = __DIR__ . '/../includes/header.php';

if (!file_exists($auth_path)) {
    die('Error: Cannot find auth.php. Please run quick-fix.php first.');
}

if (!file_exists($functions_path)) {
    die('Error: Cannot find functions.php. Please run quick-fix.php first.');
}

require_once $auth_path;
require_once $functions_path;

try {
    $auth = new Auth();
    $auth->requireRole('admin');

    $functions = new Functions();
    $success_message = '';
    $error_message = '';

    // Handle form submissions
    if ($_POST) {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_user') {<?php
$page_title = 'Quản lý người dùng';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$functions = new Functions();
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_user') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name']);
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($role) || empty($full_name)) {
            $error_message = 'Vui lòng điền đầy đủ thông tin.';
        } elseif (strlen($password) < 6) {
            $error_message = 'Mật khẩu phải có ít nhất 6 ký tự.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Email không hợp lệ.';
        } else {
            if ($functions->createUser($username, $email, $password, $role, $full_name)) {
                $success_message = 'Tạo người dùng thành công!';
            } else {
                $error_message = 'Có lỗi khi tạo người dùng. Có thể tên đăng nhập hoặc email đã tồn tại.';
            }
        }
    }
    
    if ($action === 'update_user') {
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $full_name = trim($_POST['full_name']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($functions->updateUser($user_id, $username, $email, $role, $full_name, $is_active)) {
            $success_message = 'Cập nhật người dùng thành công!';
        } else {
            $error_message = 'Có lỗi khi cập nhật người dùng.';
        }
    }
    
    if ($action === 'delete_user') {
        $user_id = $_POST['user_id'];
        if ($user_id != $_SESSION['user_id']) { // Không cho phép xóa chính mình
            if ($functions->deleteUser($user_id)) {
                $success_message = 'Vô hiệu hóa người dùng thành công!';
            } else {
                $error_message = 'Có lỗi khi vô hiệu hóa người dùng.';
            }
        } else {
            $error_message = 'Không thể xóa tài khoản của chính mình.';
        }
    }
}

// Get filter parameters
$role_filter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';

$users = $functions->getUsers($role_filter);

// Apply search filter
if ($search) {
    $users = array_filter($users, function($user) use ($search) {
        return stripos($user['username'], $search) !== false || 
               stripos($user['email'], $search) !== false || 
               stripos($user['full_name'], $search) !== false;
    });
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 p-0">
            <div class="sidebar p-3">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="users.php">
                        <i class="fas fa-users me-2"></i>Quản lý người dùng
                    </a>
                    <a class="nav-link" href="upload.php">
                        <i class="fas fa-upload me-2"></i>Upload dữ liệu
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i>Báo cáo
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-10">
            <div class="main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="text-gradient">
                            <i class="fas fa-users me-2"></i>Quản lý người dùng
                        </h2>
                        <p class="text-muted mb-0">Tổng cộng: <strong><?php echo count($users); ?></strong> người dùng</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-2"></i>Thêm người dùng
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
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo count($functions->getUsers('admin')); ?></div>
                                    <div class="text-muted">Admins</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-user-shield text-danger fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo count($functions->getUsers('labeler')); ?></div>
                                    <div class="text-muted">Labelers</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-tags text-primary fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo count($functions->getUsers('reviewer')); ?></div>
                                    <div class="text-muted">Reviewers</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-check-double text-success fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="stats-number"><?php echo count(array_filter($users, function($u) { return $u['is_active']; })); ?></div>
                                    <div class="text-muted">Đang hoạt động</div>
                                </div>
                                <div class="ms-3">
                                    <i class="fas fa-user-check text-info fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters and Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tìm kiếm</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Tìm theo tên, email, username...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Lọc theo vai trò</label>
                                <select class="form-select" name="role">
                                    <option value="">Tất cả vai trò</option>
                                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="labeler" <?php echo $role_filter === 'labeler' ? 'selected' : ''; ?>>Labeler</option>
                                    <option value="reviewer" <?php echo $role_filter === 'reviewer' ? 'selected' : ''; ?>>Reviewer</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i>Lọc
                                    </button>
                                    <a href="users.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-1"></i>Reset
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-success w-100" onclick="exportUsers()">
                                    <i class="fas fa-download me-1"></i>Xuất Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>Danh sách người dùng
                            <?php if ($search || $role_filter): ?>
                                <span class="badge bg-primary ms-2"><?php echo count($users); ?> kết quả</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()">
                                        </th>
                                        <th>ID</th>
                                        <th>Avatar</th>
                                        <th>Thông tin</th>
                                        <th>Vai trò</th>
                                        <th>Ngày tạo</th>
                                        <th>Trạng thái</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-users text-muted fa-3x mb-3"></i>
                                            <p class="text-muted">Không tìm thấy người dùng nào</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input user-checkbox" value="<?php echo $user['id']; ?>">
                                            </td>
                                            <td class="fw-bold"><?php echo $user['id']; ?></td>
                                            <td>
                                                <div class="avatar-sm">
                                                    <?php 
                                                    $initials = strtoupper(substr($user['full_name'], 0, 1));
                                                    $colors = ['bg-primary', 'bg-success', 'bg-warning', 'bg-info', 'bg-secondary'];
                                                    $color = $colors[$user['id'] % count($colors)];
                                                    ?>
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center <?php echo $color; ?> text-white fw-bold" 
                                                         style="width: 40px; height: 40px;">
                                                        <?php echo $initials; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user['username']); ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $role_colors = [
                                                    'admin' => 'danger',
                                                    'labeler' => 'primary',
                                                    'reviewer' => 'success'
                                                ];
                                                $role_names = [
                                                    'admin' => 'Admin',
                                                    'labeler' => 'Gán nhãn',
                                                    'reviewer' => 'Review'
                                                ];
                                                $role_icons = [
                                                    'admin' => 'fa-user-shield',
                                                    'labeler' => 'fa-tags',
                                                    'reviewer' => 'fa-check-double'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $role_colors[$user['role']]; ?>">
                                                    <i class="fas <?php echo $role_icons[$user['role']]; ?> me-1"></i>
                                                    <?php echo $role_names[$user['role']]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-muted small">
                                                    <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                                    <br>
                                                    <?php echo date('H:i', strtotime($user['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>Hoạt động
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-times me-1"></i>Vô hiệu
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-info" 
                                                            onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                                            data-bs-toggle="modal" data-bs-target="#editUserModal">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-warning" 
                                                            onclick="resetPassword(<?php echo $user['id']; ?>)">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($users)): ?>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted">Hiển thị <?php echo count($users); ?> người dùng</span>
                            </div>
                            <div>
                                <button class="btn btn-outline-danger btn-sm" onclick="bulkDelete()" disabled id="bulkDeleteBtn">
                                    <i class="fas fa-trash me-1"></i>Xóa đã chọn
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Thêm người dùng mới
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="return validateUserForm(this)">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="form-text">Chỉ chữ cái, số và dấu gạch dưới</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Vai trò <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Chọn vai trò</option>
                                <option value="labeler">Người gán nhãn</option>
                                <option value="reviewer">Người review</option>
                                <option value="admin">Quản trị viên</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Tối thiểu 6 ký tự</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Tạo người dùng
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
                    <i class="fas fa-user-edit me-2"></i>Chỉnh sửa người dùng
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_username" class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_full_name" class="form-label">Họ và tên</label>
                        <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_role" class="form-label">Vai trò</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="labeler">Người gán nhãn</option>
                                <option value="reviewer">Người review</option>
                                <option value="admin">Quản trị viên</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" checked>
                                <label class="form-check-label" for="edit_is_active">
                                    Tài khoản hoạt động
                                </label>
                            </div>
                        </div>
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
<script src="../js/script.js"></script>

<script>
// Edit user function
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_is_active').checked = user.is_active == 1;
}

// Delete user function
function deleteUser(userId, userName) {
    if (confirm(`Bạn có chắc chắn muốn vô hiệu hóa tài khoản "${userName}"?`)) {
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

// Reset password function
function resetPassword(userId) {
    const newPassword = prompt('Nhập mật khẩu mới (tối thiểu 6 ký tự):');
    if (newPassword && newPassword.length >= 6) {
        // Implementation for password reset
        alert('Tính năng đặt lại mật khẩu sẽ được triển khai trong phiên bản tiếp theo.');
    } else if (newPassword) {
        alert('Mật khẩu phải có ít nhất 6 ký tự.');
    }
}

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Select all checkboxes
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    bulkDeleteBtn.disabled = !selectAll.checked;
}

// Handle individual checkbox changes
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const selectAll = document.getElementById('selectAll');
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            bulkDeleteBtn.disabled = checkedBoxes.length === 0;
            selectAll.checked = checkedBoxes.length === checkboxes.length;
        });
    });
});

// Bulk delete function
function bulkDelete() {
    const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
    if (checkedBoxes.length === 0) return;
    
    if (confirm(`Bạn có chắc chắn muốn vô hiệu hóa ${checkedBoxes.length} người dùng đã chọn?`)) {
        checkedBoxes.forEach(checkbox => {
            deleteUser(checkbox.value, 'người dùng');
        });
    }
}

// Export users function
function exportUsers() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'users.php?' + params.toString();
}

// Form validation
function validateUserForm(form) {
    const username = form.username.value.trim();
    const email = form.email.value.trim();
    const password = form.password.value;
    const fullName = form.full_name.value.trim();
    const role = form.role.value;
    
    // Validate username
    if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
        alert('Tên đăng nhập phải có 3-20 ký tự và chỉ chứa chữ cái, số, dấu gạch dưới.');
        return false;
    }
    
    // Validate email
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('Email không hợp lệ.');
        return false;
    }
    
    // Validate password
    if (password.length < 6) {
        alert('Mật khẩu phải có ít nhất 6 ký tự.');
        return false;
    }
    
    // Validate full name
    if (fullName.length < 2) {
        alert('Họ tên phải có ít nhất 2 ký tự.');
        return false;
    }
    
    // Validate role
    if (!role) {
        alert('Vui lòng chọn vai trò.');
        return false;
    }
    
    return true;
}

// Auto-refresh every 5 minutes
setInterval(() => {
    if (!document.querySelector('.modal.show')) {
        location.reload();
    }
}, 300000);

// Real-time search
let searchTimeout;
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }
});
</script>

</body>
</html>