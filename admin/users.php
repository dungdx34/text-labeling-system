<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Quản lý người dùng';

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/functions.php';
    
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
    }
    
    // Get users
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
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
        }
        .navbar {
            background: rgba(13, 110, 253, 0.95) !important;
            backdrop-filter: blur(10px);
        }
        .sidebar { 
            background: linear-gradient(180deg, #0d6efd 0%, #0a58ca 100%); 
            min-height: calc(100vh - 56px); 
        }
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.8); 
            transition: all 0.3s; 
            border-radius: 8px; 
            margin: 4px 0; 
            padding: 12px 16px; 
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { 
            color: white; 
            background: rgba(255,255,255,0.15); 
            transform: translateX(8px); 
        }
        .main-content { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
            margin: 20px; 
            padding: 40px; 
        }
        .stats-card { 
            background: linear-gradient(135deg, white 0%, #f8f9fa 100%); 
            border-radius: 12px; 
            padding: 25px; 
            text-align: center; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.08); 
            transition: all 0.3s ease; 
        }
        .stats-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
        }
        .stats-number { 
            font-size: 2.5rem; 
            font-weight: 800; 
            background: linear-gradient(135deg, #0d6efd, #0a58ca); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .text-gradient { 
            background: linear-gradient(135deg, #0d6efd, #764ba2); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        .card { 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.08); 
        }
        .table { 
            border-radius: 10px; 
            overflow: hidden; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.08); 
        }
        .table thead th { 
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); 
            color: white; 
            border: none; 
            font-weight: 600; 
        }
        .btn-primary {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            border: none;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-tags me-2"></i>Text Labeling System
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link">
                    <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['full_name'] ?? 'Admin'; ?>
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
                </a>
            </div>
        </div>
    </nav>

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
                        <a class="nav-link" href="../debug.php">
                            <i class="fas fa-bug me-2"></i>Debug
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
                    
                    <!-- Search Form -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Tìm kiếm</label>
                                    <input type="text" class="form-control" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Tìm theo tên, email, username...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Vai trò</label>
                                    <select class="form-select" name="role">
                                        <option value="">Tất cả</option>
                                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="labeler" <?php echo $role_filter === 'labeler' ? 'selected' : ''; ?>>Labeler</option>
                                        <option value="reviewer" <?php echo $role_filter === 'reviewer' ? 'selected' : ''; ?>>Reviewer</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Lọc</button>
                                        <a href="users.php" class="btn btn-outline-secondary">Reset</a>
                                    </div>
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
                                            <th>ID</th>
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
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-users text-muted fa-3x mb-3"></i>
                                                <p class="text-muted">Không tìm thấy người dùng nào</p>
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo $user['id']; ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user['username']); ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $role_colors = ['admin' => 'danger', 'labeler' => 'primary', 'reviewer' => 'success'];
                                                    $role_names = ['admin' => 'Admin', 'labeler' => 'Gán nhãn', 'reviewer' => 'Review'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $role_colors[$user['role']]; ?>">
                                                        <?php echo $role_names[$user['role']]; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($user['is_active']): ?>
                                                        <span class="badge bg-success">Hoạt động</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Vô hiệu</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-info">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <button type="button" class="btn btn-outline-danger">
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
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Tên đăng nhập *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Họ và tên *</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Vai trò *</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Chọn vai trò</option>
                                    <option value="labeler">Người gán nhãn</option>
                                    <option value="reviewer">Người review</option>
                                    <option value="admin">Quản trị viên</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Mật khẩu *</label>
                                <input type="password" class="form-control" name="password" required>
                                <div class="form-text">Tối thiểu 6 ký tự</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Tạo người dùng</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
    console.log('Admin Users page loaded successfully');
    console.log('Total users: <?php echo count($users); ?>');
    </script>

</body>
</html>