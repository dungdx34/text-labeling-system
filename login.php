<?php
session_start();
require_once 'config/database.php';

// Nếu đã đăng nhập, redirect về dashboard
if (isset($_SESSION['user_id'])) {
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
        default:
            header('Location: index.php');
            break;
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT id, username, password, role, full_name, status FROM users WHERE username = ? AND status = 'active'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $username);
            $stmt->execute();
            
            if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (password_verify($password, $user['password'])) {
                    // Login thành công
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    // Redirect dựa trên role
                    switch ($user['role']) {
                        case 'admin':
                            header('Location: admin/dashboard.php');
                            break;
                        case 'labeler':
                            header('Location: labeler/dashboard.php');
                            break;
                        case 'reviewer':
                            header('Location: reviewer/dashboard.php');
                            break;
                        default:
                            header('Location: index.php');
                            break;
                    }
                    exit();
                } else {
                    $error = 'Tên đăng nhập hoặc mật khẩu không đúng';
                }
            } else {
                $error = 'Tên đăng nhập hoặc mật khẩu không đúng';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi hệ thống: ' . $e->getMessage();
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
            font-family: 'Segoe UI', sans-serif;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 40px 30px;
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
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
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-container">
                    <div class="login-header">
                        <i class="fas fa-tags fa-2x mb-3"></i>
                        <h3>Text Labeling System</h3>
                        <p class="mb-0">Đăng nhập vào hệ thống</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['message'])): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php 
                                switch ($_GET['message']) {
                                    case 'logout_success':
                                        echo 'Bạn đã đăng xuất thành công.';
                                        break;
                                    default:
                                        echo 'Thao tác thành công.';
                                }
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php 
                                switch ($_GET['error']) {
                                    case 'invalid_role':
                                        echo 'Vai trò không hợp lệ. Vui lòng đăng nhập lại.';
                                        break;
                                    case 'logout':
                                        echo 'Bạn đã đăng xuất thành công.';
                                        break;
                                    default:
                                        echo 'Có lỗi xảy ra. Vui lòng thử lại.';
                                }
                                ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-user me-2"></i>Tên đăng nhập
                                </label>
                                <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-lock me-2"></i>Mật khẩu
                                </label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-login w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                            </button>
                        </form>

                        <div class="mt-4 text-center">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Tài khoản mặc định:</h6>
                                    <small class="text-muted">
                                        <strong>Admin:</strong> admin / admin123<br>
                                        <strong>Labeler:</strong> labeler1 / labeler123<br>
                                        <strong>Reviewer:</strong> reviewer1 / reviewer123
                                    </small>
                                </div>
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