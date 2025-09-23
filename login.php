<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// If user is already logged in, redirect to appropriate dashboard
if (checkAuth()) {
    redirectByRole($_SESSION['role']);
}

$error = '';
$success = '';

if ($_POST) {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ thông tin đăng nhập";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $query = "SELECT id, username, password, email, full_name, role, status FROM users WHERE username = :username AND status = 'active'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($password, $user['password'])) {
                    // Login successful - set complete session data
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'] ?? $user['username']; // Fallback to username if full_name is null
                    $_SESSION['role'] = $user['role'];
                    
                    // Log login activity
                    logActivity($db, $user['id'], 'login', 'user', $user['id']);
                    
                    // Redirect to appropriate dashboard
                    redirectByRole($user['role']);
                } else {
                    $error = "Sai tên đăng nhập hoặc mật khẩu";
                }
            } else {
                $error = "Sai tên đăng nhập hoặc mật khẩu";
            }
        } else {
            $error = "Không thể kết nối cơ sở dữ liệu";
        }
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
            font-family: 'Segoe UI', sans-serif;
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
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
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
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .demo-account:last-child {
            border-bottom: none;
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
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" name="username" placeholder="Tên đăng nhập" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" name="password" placeholder="Mật khẩu" required>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Đăng nhập
                            </button>
                        </div>
                    </form>
                    
                    <div class="demo-accounts">
                        <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Tài khoản demo:</h6>
                        <div class="demo-account">
                            <div>
                                <strong>Admin:</strong> admin / admin123
                                <br><small class="text-muted">Quản lý hệ thống, upload dữ liệu</small>
                            </div>
                        </div>
                        <div class="demo-account">
                            <div>
                                <strong>Labeler:</strong> labeler1 / password123
                                <br><small class="text-muted">Thực hiện gán nhãn văn bản</small>
                            </div>
                        </div>
                        <div class="demo-account">
                            <div>
                                <strong>Reviewer:</strong> reviewer1 / password123
                                <br><small class="text-muted">Review và góp ý công việc</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>