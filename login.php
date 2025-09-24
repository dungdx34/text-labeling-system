<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// If user is already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'labeler':
            header('Location: labeler/dashboard.php');
            break;
        case 'reviewer':
            header('Location: reviewer/dashboard.php');
            break;
    }
    exit();
}

$error = '';
$success = '';

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = "Đăng xuất thành công!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ thông tin đăng nhập";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                throw new Exception("Không thể kết nối cơ sở dữ liệu");
            }
            
            // FIXED: Proper parameter binding for username/email search
            $query = "SELECT id, username, password, email, full_name, role, status 
                     FROM users 
                     WHERE (username = ? OR email = ?) 
                     AND status = 'active' 
                     LIMIT 1";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$username, $username]); // Pass username twice for both parameters
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password - support both hashed and plain text
                $password_valid = false;
                
                if (password_verify($password, $user['password'])) {
                    // Modern hashed password
                    $password_valid = true;
                } elseif ($password === $user['password']) {
                    // Plain text password (backward compatibility)
                    $password_valid = true;
                    
                    // Auto-upgrade to hashed password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update_stmt->execute([$hashed_password, $user['id']]);
                }
                
                if ($password_valid) {
                    // Login successful - set session data
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'] ?? '';
                    $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_login'] = date('Y-m-d H:i:s');
                    
                    // Update last login time if column exists
                    try {
                        $update_stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $update_stmt->execute([$user['id']]);
                    } catch (Exception $e) {
                        // Ignore if last_login column doesn't exist
                    }
                    
                    // Log login activity
                    if (function_exists('logActivity')) {
                        logActivity($db, $user['id'], 'login', 'user', $user['id']);
                    }
                    
                    // Redirect based on role
                    switch ($user['role']) {
                        case 'admin':
                            header('Location: admin/dashboard.php');
                            exit();
                        case 'labeler':
                            header('Location: labeler/dashboard.php');
                            exit();
                        case 'reviewer':
                            header('Location: reviewer/dashboard.php');
                            exit();
                        default:
                            $error = "Vai trò không hợp lệ: " . $user['role'];
                            break;
                    }
                } else {
                    $error = "Sai tên đăng nhập hoặc mật khẩu";
                }
            } else {
                $error = "Sai tên đăng nhập hoặc mật khẩu";
            }
        } catch (Exception $e) {
            $error = "Lỗi hệ thống: " . $e->getMessage();
            
            // Debug info (remove in production)
            error_log("Login error: " . $e->getMessage());
            error_log("Username: " . $username);
        }
    }
}

// Get demo account info and system status
try {
    $database = new Database();
    $db = $database->getConnection();
    $db_connected = ($db !== null);
    
    // Test basic query to ensure tables exist
    if ($db_connected) {
        try {
            // Simple test query
            $stmt = $db->query("SELECT COUNT(*) as count FROM users LIMIT 1");
            $test_result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get user counts
            $stmt = $db->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
            $user_counts = ['admin' => 0, 'labeler' => 0, 'reviewer' => 0];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($user_counts[$row['role']])) {
                    $user_counts[$row['role']] = $row['count'];
                }
            }
        } catch (Exception $e) {
            // Tables might not exist, create them
            $user_counts = ['admin' => 0, 'labeler' => 0, 'reviewer' => 0];
            $error = "Database tables not found. Please run setup first.";
        }
    } else {
        $user_counts = ['admin' => 0, 'labeler' => 0, 'reviewer' => 0];
    }
} catch (Exception $e) {
    $db_connected = false;
    $user_counts = ['admin' => 0, 'labeler' => 0, 'reviewer' => 0];
}

// Show actual users if any exist for debugging
$existing_users = [];
if ($db_connected) {
    try {
        $stmt = $db->query("SELECT username, role FROM users WHERE status = 'active' ORDER BY role, username");
        $existing_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Ignore
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            margin: 20px;
        }
        .login-left {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 40px;
            text-align: center;
        }
        .login-right {
            padding: 60px 40px;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 15px 20px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .input-group-text {
            border: 2px solid #e9ecef;
            border-right: none;
            background: #f8f9fa;
            border-radius: 10px 0 0 10px;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        .demo-accounts {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        .demo-account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            transition: background 0.3s ease;
            border-radius: 5px;
            margin: 5px 0;
        }
        .demo-account:hover {
            background: #e9ecef;
        }
        .demo-account:last-child {
            border-bottom: none;
        }
        .system-status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 10px;
            font-size: 12px;
            max-width: 300px;
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .feature {
            padding: 8px 0;
            font-size: 14px;
        }
        .existing-users {
            margin-top: 10px;
            max-height: 150px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="row g-0">
            <div class="col-md-5">
                <div class="login-left h-100 d-flex flex-column justify-content-center">
                    <h2 class="mb-4">
                        <i class="fas fa-tags me-3"></i>
                        Text Labeling System
                    </h2>
                    <p class="lead mb-4">Hệ thống gán nhãn văn bản thông minh với AI</p>
                    <div class="features">
                        <div class="feature mb-3">
                            <i class="fas fa-check-circle me-2"></i>
                            Gán nhãn văn bản đơn và đa văn bản
                        </div>
                        <div class="feature mb-3">
                            <i class="fas fa-check-circle me-2"></i>
                            Chỉnh sửa bản tóm tắt AI
                        </div>
                        <div class="feature mb-3">
                            <i class="fas fa-check-circle me-2"></i>
                            Hệ thống review và phân quyền
                        </div>
                        <div class="feature">
                            <i class="fas fa-check-circle me-2"></i>
                            Giao diện thân thiện và dễ sử dụng
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="login-right">
                    <h3 class="mb-4 text-center">Đăng nhập vào hệ thống</h3>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" name="username" id="username" 
                                       placeholder="Tên đăng nhập hoặc email" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                       required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" name="password" id="password" 
                                       placeholder="Mật khẩu" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Đăng nhập
                            </button>
                        </div>
                    </form>
                    
                    <div class="demo-accounts">
                        <h6 class="mb-3">
                            <i class="fas fa-info-circle me-2"></i>Tài khoản demo (click để điền):
                        </h6>
                        <div class="demo-account" onclick="fillLogin('admin', 'admin123')">
                            <div>
                                <strong>Admin:</strong> admin / admin123
                                <br><small class="text-muted">Quản lý hệ thống, upload dữ liệu</small>
                            </div>
                            <span class="badge bg-danger">Admin</span>
                        </div>
                        <div class="demo-account" onclick="fillLogin('label1', 'label123')">
                            <div>
                                <strong>Labeler:</strong> label1 / label123
                                <br><small class="text-muted">Thực hiện gán nhãn văn bản</small>
                            </div>
                            <span class="badge bg-primary">Labeler</span>
                        </div>
                        <div class="demo-account" onclick="fillLogin('review1', 'review123')">
                            <div>
                                <strong>Reviewer:</strong> review1 / review123
                                <br><small class="text-muted">Review và góp ý công việc</small>
                            </div>
                            <span class="badge bg-success">Reviewer</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Status -->
    <div class="system-status">
        <strong>Trạng thái hệ thống:</strong><br>
        <?php if ($db_connected): ?>
            <span class="text-success">
                <i class="fas fa-check-circle"></i> Database: Kết nối OK
            </span><br>
            <small>
                Admin: <?php echo $user_counts['admin']; ?> | 
                Labeler: <?php echo $user_counts['labeler']; ?> | 
                Reviewer: <?php echo $user_counts['reviewer']; ?>
            </small>
            
            <?php if (!empty($existing_users)): ?>
                <div class="existing-users">
                    <strong>Users hiện có:</strong><br>
                    <?php foreach ($existing_users as $user): ?>
                        <small><?php echo htmlspecialchars($user['username']); ?> (<?php echo $user['role']; ?>)</small><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <span class="text-danger">
                <i class="fas fa-exclamation-triangle"></i> Database: Lỗi kết nối
            </span>
        <?php endif; ?>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function fillLogin(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Visual feedback
            const demoAccounts = document.querySelectorAll('.demo-account');
            demoAccounts.forEach(account => account.style.background = '');
            event.currentTarget.style.background = '#e9ecef';
            
            // Focus submit button
            document.querySelector('.btn-login').focus();
        }
        
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const alertInstance = new bootstrap.Alert(alert);
                alertInstance.close();
            });
        }, 5000);
        
        // Focus username field on load
        document.getElementById('username').focus();
        
        // Enter key handling
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('.btn-login').click();
            }
        });
    </script>
</body>
</html>