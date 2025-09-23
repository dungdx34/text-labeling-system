<?php
/**
 * Enhanced Login Page - Text Labeling System
 * Secure login with enhanced UI and security features
 */
session_start();

require_once 'includes/auth.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    $redirect_url = $auth->getRedirectUrl($_SESSION['role']);
    header("Location: $redirect_url");
    exit();
}

$error_message = '';
$success_message = '';
$show_demo_accounts = true;

// Handle login form submission
if ($_POST && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $error_message = 'Vui lòng nhập đầy đủ thông tin đăng nhập.';
    } else {
        $result = $auth->login($username, $password, $remember_me);
        
        if ($result['success']) {
            $success_message = $result['message'];
            
            // Determine redirect URL
            $redirect_url = $auth->getRedirectUrl($result['role']);
            
            // Check if there's a specific redirect URL
            if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                $redirect_url = $_GET['redirect'];
            }
            
            // Use JavaScript for redirect to ensure message is shown
            echo "<script>
                setTimeout(function() {
                    window.location.href = '$redirect_url';
                }, 1500);
            </script>";
        } else {
            $error_message = $result['message'];
            if (isset($result['locked']) && $result['locked']) {
                $show_demo_accounts = false;
            }
        }
    }
}

// Handle session timeout message
if (isset($_GET['error']) && $_GET['error'] === 'session_timeout') {
    $error_message = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Text Labeling System</title>
    
    <!-- CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --error-gradient: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
        }
        
        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .login-header {
            background: var(--primary-gradient);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }
        
        .login-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        
        .login-header p {
            margin: 15px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
            position: relative;
            z-index: 1;
        }
        
        .login-header .logo {
            font-size: 3rem;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-floating {
            margin-bottom: 25px;
        }
        
        .form-floating > .form-control {
            height: calc(3.5rem + 2px);
            border: 2px solid #e9ecef;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 16px;
        }
        
        .form-floating > .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
            transform: translateY(-2px);
        }
        
        .form-floating > label {
            padding: 1rem 0.75rem;
            font-weight: 500;
        }
        
        .form-check {
            margin: 20px 0;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .btn-login {
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 16px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(-1px);
        }
        
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff416c20, #ff4b2b20);
            color: #d63384;
            border-left: 4px solid #d63384;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #56ab2f20, #a8e6cf20);
            color: #198754;
            border-left: 4px solid #198754;
        }
        
        .demo-accounts {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 25px;
            margin-top: 25px;
            border: 1px solid #dee2e6;
        }
        
        .demo-accounts h6 {
            color: #495057;
            margin-bottom: 20px;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .demo-account {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 12px;
            border: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .demo-account:last-child {
            margin-bottom: 0;
        }
        
        .demo-account:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }
        
        .demo-account strong {
            font-size: 14px;
            color: #212529;
        }
        
        .demo-account small {
            display: block;
            color: #6c757d;
            margin-top: 5px;
            font-size: 12px;
        }
        
        .role-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 6px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .role-admin { background: #dc3545; color: white; }
        .role-labeler { background: #28a745; color: white; }
        .role-reviewer { background: #007bff; color: white; }
        
        .spinner-border-sm {
            width: 1.2rem;
            height: 1.2rem;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        
        @media (max-width: 768px) {
            .login-container {
                padding: 10px;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-body {
                padding: 30px 20px;
            }
        }
        
        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-card {
            animation: slideInUp 0.6s ease-out;
        }
        
        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }
        
        .shape:nth-child(1) { top: 20%; left: 10%; animation-delay: 0s; }
        .shape:nth-child(2) { top: 60%; left: 80%; animation-delay: 2s; }
        .shape:nth-child(3) { top: 40%; left: 60%; animation-delay: 4s; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
    </style>
</head>
<body>
    <!-- Floating background shapes -->
    <div class="floating-shapes">
        <div class="shape">
            <i class="fas fa-tags fa-3x"></i>
        </div>
        <div class="shape">
            <i class="fas fa-file-alt fa-2x"></i>
        </div>
        <div class="shape">
            <i class="fas fa-check-circle fa-4x"></i>
        </div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-tags"></i>
                </div>
                <h2>Text Labeling System</h2>
                <p>Hệ thống gán nhãn văn bản thông minh</p>
            </div>
            
            <div class="login-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <div class="mt-2">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            <small>Đang chuyển hướng...</small>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm" novalidate>
                    <div class="form-floating">
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="Tên đăng nhập" 
                               required
                               autocomplete="username"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        <label for="username">
                            <i class="fas fa-user me-2"></i>Tên đăng nhập hoặc Email
                        </label>
                    </div>
                    
                    <div class="form-floating position-relative">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Mật khẩu" 
                               required
                               autocomplete="current-password">
                        <label for="password">
                            <i class="fas fa-lock me-2"></i>Mật khẩu
                        </label>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                        <label class="form-check-label" for="remember_me">
                            Ghi nhớ đăng nhập (30 ngày)
                        </label>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary btn-login" id="loginBtn">
                        <span class="btn-text">
                            <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                        </span>
                        <span class="btn-loading d-none">
                            <i class="fas fa-spinner fa-spin me-2"></i>Đang đăng nhập...
                        </span>
                    </button>
                    
                    <div class="loading-overlay" id="loadingOverlay">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </form>
                
                <?php if ($show_demo_accounts): ?>
                <!-- Demo Accounts Information -->
                <div class="demo-accounts">
                    <h6><i class="fas fa-users me-2"></i>Tài khoản demo</h6>
                    
                    <div class="demo-account" onclick="quickLogin('admin', 'password123')">
                        <span class="role-badge role-admin">Admin</span>
                        <strong>admin</strong>
                        <small>Mật khẩu: password123 • Quản trị hệ thống</small>
                    </div>
                    
                    <div class="demo-account" onclick="quickLogin('labeler1', 'password123')">
                        <span class="role-badge role-labeler">Labeler</span>
                        <strong>labeler1</strong>
                        <small>Mật khẩu: password123 • Người gán nhãn</small>
                    </div>
                    
                    <div class="demo-account" onclick="quickLogin('reviewer1', 'password123')">
                        <span class="role-badge role-reviewer">Reviewer</span>
                        <strong>reviewer1</strong>
                        <small>Mật khẩu: password123 • Người review</small>
                    </div>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Nhấp vào tài khoản để điền tự động
                        </small>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Hệ thống được bảo vệ bởi SSL và 2FA
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
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
        
        // Quick login for demo accounts
        function quickLogin(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Add visual feedback
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            usernameField.classList.add('border-success');
            passwordField.classList.add('border-success');
            
            setTimeout(() => {
                usernameField.classList.remove('border-success');
                passwordField.classList.remove('border-success');
            }, 2000);
            
            // Focus on login button
            document.getElementById('loginBtn').focus();
        }
        
        // Form submission handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoading = btn.querySelector('.btn-loading');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            // Show loading state
            btn.disabled = true;
            btnText.classList.add('d-none');
            btnLoading.classList.remove('d-none');
            loadingOverlay.style.display = 'flex';
            
            // Validate form
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                showError('Vui lòng nhập đầy đủ thông tin đăng nhập.');
                resetButton();
                return;
            }
            
            // If validation passes, form will submit normally
        });
        
        // Reset button state
        function resetButton() {
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoading = btn.querySelector('.btn-loading');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            btn.disabled = false;
            btnText.classList.remove('d-none');
            btnLoading.classList.add('d-none');
            loadingOverlay.style.display = 'none';
        }
        
        // Show error message
        function showError(message) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger';
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
            `;
            
            // Insert before form
            const form = document.getElementById('loginForm');
            form.parentNode.insertBefore(alertDiv, form);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
        
        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Handle enter key in password field
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
        
        // Add floating label animation
        document.querySelectorAll('.form-floating .form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
        });
        
        // Check for browser password manager
        window.addEventListener('load', function() {
            setTimeout(() => {
                const username = document.getElementById('username');
                const password = document.getElementById('password');
                
                if (username.value || password.value) {
                    // Browser filled in credentials, update UI
                    if (username.value) username.parentElement.classList.add('focused');
                    if (password.value) password.parentElement.classList.add('focused');
                }
            }, 100);
        });
        
        // Prevent multiple form submissions
        let formSubmitted = false;
        document.getElementById('loginForm').addEventListener('submit', function() {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
        });
        
        // Reset form submission flag if there's an error
        <?php if ($error_message): ?>
        formSubmitted = false;
        // Reset button state if there was an error
        setTimeout(resetButton, 100);
        <?php endif; ?>
        
        // Add ripple effect to demo accounts
        document.querySelectorAll('.demo-account').forEach(account => {
            account.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
        
        // Keyboard navigation for demo accounts
        document.addEventListener('keydown', function(e) {
            if (e.altKey) {
                switch(e.key) {
                    case '1':
                        quickLogin('admin', 'password123');
                        break;
                    case '2':
                        quickLogin('labeler1', 'password123');
                        break;
                    case '3':
                        quickLogin('reviewer1', 'password123');
                        break;
                }
            }
        });
    </script>
    
    <style>
        /* Additional CSS for ripple effect */
        .demo-account {
            position: relative;
            overflow: hidden;
        }
        
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.3);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        .form-floating.focused .form-control {
            border-color: #667eea;
        }
    </style>
</body>
</html>
                