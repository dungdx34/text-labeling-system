<?php
// Prevent output before headers
ob_start();

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // User is already logged in, redirect to appropriate dashboard
    switch ($_SESSION['role']) {
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
            // Invalid role, destroy session
            session_destroy();
            break;
    }
}

// Include auth class with error handling
$auth = null;
try {
    if (file_exists('includes/auth.php')) {
        require_once 'includes/auth.php';
        $auth = new Auth();
    } else {
        throw new Exception('Auth file not found');
    }
} catch (Exception $e) {
    $error_message = 'Lỗi hệ thống: Không thể tải file xác thực. Vui lòng kiểm tra cài đặt.';
}

$error_message = '';
$success_message = '';

// Handle logout message
if (isset($_GET['message']) && $_GET['message'] === 'logout_success') {
    $success_message = 'Đăng xuất thành công!';
}

// Handle login form submission
if ($_POST && $auth) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        try {
            if ($auth->login($username, $password)) {
                // Login successful, redirect based on role
                switch ($_SESSION['role']) {
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
                        $error_message = 'Vai trò không hợp lệ.';
                        session_destroy();
                        break;
                }
            } else {
                $error_message = 'Tên đăng nhập hoặc mật khẩu không đúng!';
            }
        } catch (Exception $e) {
            $error_message = 'Lỗi đăng nhập: ' . $e->getMessage();
        }
    }
}

// Clean output buffer
ob_end_clean();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .login-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: float 20s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 50px 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 450px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 2;
        }
        .logo {
            background: linear-gradient(135deg, #0d6efd 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4);
        }
        .input-group-text {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            border: none;
            border-radius: 12px 0 0 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <h2 class="logo mb-3">
                    <i class="fas fa-tags me-2"></i>Text Labeling System
                </h2>
                <p class="text-muted">Đăng nhập để tiếp tục</p>
            </div>
            
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
            
            <form method="POST" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Tên đăng nhập</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                               required autocomplete="username">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" 
                               required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                </button>
            </form>
            
            <div class="text-center">
                <small class="text-muted">
                    <strong>Tài khoản demo:</strong><br>
                    <div class="row mt-2">
                        <div class="col-4">
                            <span class="badge bg-danger">Admin</span><br>
                            <small>admin<br>admin123</small>
                        </div>
                        <div class="col-4">
                            <span class="badge bg-primary">Labeler</span><br>
                            <small>labeler1<br>admin123</small>
                        </div>
                        <div class="col-4">
                            <span class="badge bg-success">Reviewer</span><br>
                            <small>reviewer1<br>admin123</small>
                        </div>
                    </div>
                </small>
            </div>
            
            <?php if (!$auth): ?>
            <div class="mt-3">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Hệ thống chưa được cài đặt!</strong><br>
                    <a href="setup.php" class="btn btn-warning btn-sm mt-2">
                        <i class="fas fa-cog me-1"></i>Chạy cài đặt
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        // Auto-hide success messages
        setTimeout(() => {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.transition = 'opacity 0.5s';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 500);
            }
        }, 3000);

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.');
                return false;
            }
        });
    </script>
</body>
</html>