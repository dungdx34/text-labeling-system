<?php
// login.php - Complete Enhanced Version
require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$pdo = $database->getConnection();

$error = '';
$success = '';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    Auth::redirectToRoleDashboard($user['role']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin';
    } else {
        if (Auth::login($username, $password)) {
            // Log activity
            try {
                $user = Auth::getCurrentUser();
                $stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) 
                    VALUES (?, 'login', ?, ?, ?)
                ");
                $stmt->execute([
                    $user['id'],
                    'Đăng nhập thành công',
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Exception $e) {
                error_log("Login activity log error: " . $e->getMessage());
            }
            
            Auth::redirectToRoleDashboard($user['role']);
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
            margin: 20px;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
        }
        
        .login-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 40px;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
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
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            width: 100%;
            color: white;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .alert {
            border: none;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .default-accounts {
            background: rgba(248, 249, 250, 0.8);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .account-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .account-item:hover {
            background-color: rgba(102, 126, 234, 0.1);
            border-radius: 5px;
        }
        
        .account-item:last-child {
            border-bottom: none;
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 20px;
        }
        
        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: -1;
        }
        
        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 60px;
            height: 60px;
            top: 70%;
            left: 80%;
            animation-delay: 2s;
        }
        
        .shape:nth-child(3) {
            width: 100px;
            height: 100px;
            top: 40%;
            right: 10%;
            animation-delay: 4s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-tags fa-2x mb-3"></i>
            <h1>Text Labeling System</h1>
            <p>Hệ thống gán nhãn dữ liệu văn bản</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success d-flex align-items-center">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Tên đăng nhập" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <label for="username">
                        <i class="fas fa-user me-2"></i>Tên đăng nhập
                    </label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Mật khẩu" required>
                    <label for="password">
                        <i class="fas fa-lock me-2"></i>Mật khẩu
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                </button>
            </form>
            
            <div class="default-accounts">
                <h6 class="text-muted mb-3">
                    <i class="fas fa-info-circle me-2"></i>Tài khoản mặc định:
                </h6>
                
                <div class="account-item" data-username="admin" data-password="admin123">
                    <div>
                        <strong>admin</strong><br>
                        <small class="text-muted">admin123</small>
                    </div>
                    <span class="badge bg-danger role-badge">Admin</span>
                </div>
                
                <div class="account-item" data-username="labeler1" data-password="labeler123">
                    <div>
                        <strong>labeler1</strong><br>
                        <small class="text-muted">labeler123</small>
                    </div>
                    <span class="badge bg-success role-badge">Labeler</span>
                </div>
                
                <div class="account-item" data-username="reviewer1" data-password="reviewer123">
                    <div>
                        <strong>reviewer1</strong><br>
                        <small class="text-muted">reviewer123</small>
                    </div>
                    <span class="badge bg-info role-badge">Reviewer</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill login form for demo purposes
        document.addEventListener('DOMContentLoaded', function() {
            const accountItems = document.querySelectorAll('.account-item');
            accountItems.forEach(item => {
                item.addEventListener('click', function() {
                    const username = this.getAttribute('data-username');
                    const password = this.getAttribute('data-password');
                    
                    document.getElementById('username').value = username;
                    document.getElementById('password').value = password;
                });
            });
        });
    </script>
</body>
</html>