<?php
/**
 * Unauthorized Access Page - Text Labeling System
 * Displayed when user tries to access pages without proper permissions
 */
session_start();

// Get user info if available
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? 'guest';
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Không có quyền truy cập - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 50px 40px;
            text-align: center;
            max-width: 600px;
            width: 90%;
            position: relative;
            overflow: hidden;
        }
        
        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="rgba(102,126,234,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
            pointer-events: none;
        }
        
        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .error-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #495057;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }
        
        .error-message {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 30px;
            line-height: 1.6;
            position: relative;
            z-index: 2;
        }
        
        .user-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 20px;
            margin: 30px 0;
            border-left: 4px solid #ffc107;
            position: relative;
            z-index: 2;
        }
        
        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .role-admin { background: #dc3545; color: white; }
        .role-labeler { background: #28a745; color: white; }
        .role-reviewer { background: #007bff; color: white; }
        .role-guest { background: #6c757d; color: white; }
        
        .btn-action {
            border-radius: 12px;
            padding: 12px 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 10px;
            display: inline-block;
            position: relative;
            z-index: 2;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-secondary {
            background: transparent;
            border: 2px solid #6c757d;
            color: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-outline-danger {
            border: 2px solid #dc3545;
            color: #dc3545;
            background: transparent;
        }
        
        .btn-outline-danger:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .access-levels {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            position: relative;
            z-index: 2;
        }
        
        .access-level {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .access-level:last-child {
            border-bottom: none;
        }
        
        .access-level-info {
            display: flex;
            align-items: center;
        }
        
        .access-level-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1.2rem;
        }
        
        .countdown {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            font-weight: 600;
            color: #495057;
        }
        
        @media (max-width: 768px) {
            .error-container {
                padding: 40px 30px;
                margin: 20px;
            }
            
            .error-title {
                font-size: 2rem;
            }
            
            .error-message {
                font-size: 1.1rem;
            }
            
            .countdown {
                position: relative;
                top: auto;
                right: auto;
                margin: 20px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Auto redirect countdown -->
    <div class="countdown" id="countdown" style="display: none;">
        <i class="fas fa-clock me-2"></i>
        Tự động chuyển hướng trong <span id="countdownNumber">10</span> giây
    </div>

    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        
        <h1 class="error-title">Không có quyền truy cập</h1>
        
        <p class="error-message">
            Xin lỗi, bạn không có quyền truy cập vào trang này. 
            Vui lòng kiểm tra lại quyền của tài khoản hoặc liên hệ quản trị viên.
        </p>
        
        <?php if ($is_logged_in): ?>
            <div class="user-info">
                <h5><i class="fas fa-user me-2"></i>Thông tin tài khoản</h5>
                <p class="mb-2">
                    <strong>Tên đăng nhập:</strong> <?php echo htmlspecialchars($username); ?>
                </p>
                <p class="mb-0">
                    <strong>Vai trò hiện tại:</strong> 
                    <span class="role-badge role-<?php echo $user_role; ?>">
                        <?php
                        $role_names = [
                            'admin' => 'Quản trị viên',
                            'labeler' => 'Người gán nhãn',
                            'reviewer' => 'Người review',
                            'guest' => 'Khách'
                        ];
                        echo $role_names[$user_role] ?? ucfirst($user_role);
                        ?>
                    </span>
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Access levels information -->
        <div class="access-levels">
            <h5 class="mb-3"><i class="fas fa-key me-2"></i>Phân quyền hệ thống</h5>
            
            <div class="access-level">
                <div class="access-level-info">
                    <div class="access-level-icon" style="background: #dc3545;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <strong>Admin</strong>
                        <div class="text-muted small">Quản lý toàn bộ hệ thống, người dùng, dữ liệu</div>
                    </div>
                </div>
                <span class="badge bg-danger">Toàn quyền</span>
            </div>
            
            <div class="access-level">
                <div class="access-level-info">
                    <div class="access-level-icon" style="background: #007bff;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <strong>Reviewer</strong>
                        <div class="text-muted small">Review và đánh giá công việc gán nhãn</div>
                    </div>
                </div>
                <span class="badge bg-primary">Kiểm duyệt</span>
            </div>
            
            <div class="access-level">
                <div class="access-level-info">
                    <div class="access-level-icon" style="background: #28a745;">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div>
                        <strong>Labeler</strong>
                        <div class="text-muted small">Thực hiện gán nhãn và tóm tắt văn bản</div>
                    </div>
                </div>
                <span class="badge bg-success">Gán nhãn</span>
            </div>
        </div>
        
        <div class="d-flex justify-content-center flex-wrap">
            <?php if ($is_logged_in): ?>
                <!-- Go back to appropriate dashboard -->
                <a href="javascript:history.back()" class="btn btn-secondary btn-action">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                </a>
                
                <?php
                // Provide link to user's appropriate dashboard
                $dashboard_links = [
                    'admin' => ['url' => '/admin/dashboard.php', 'text' => 'Admin Dashboard', 'icon' => 'fa-tachometer-alt'],
                    'labeler' => ['url' => '/labeler/dashboard.php', 'text' => 'Labeler Dashboard', 'icon' => 'fa-tags'],
                    'reviewer' => ['url' => '/reviewer/dashboard.php', 'text' => 'Reviewer Dashboard', 'icon' => 'fa-check-circle']
                ];
                
                if (isset($dashboard_links[$user_role])):
                    $link = $dashboard_links[$user_role];
                ?>
                    <a href="<?php echo $link['url']; ?>" class="btn btn-primary btn-action" id="dashboardLink">
                        <i class="fas <?php echo $link['icon']; ?> me-2"></i><?php echo $link['text']; ?>
                    </a>
                <?php endif; ?>
                
                <a href="/logout.php" class="btn btn-outline-danger btn-action">
                    <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                </a>
            <?php else: ?>
                <!-- User not logged in -->
                <a href="/login.php" class="btn btn-primary btn-action">
                    <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                </a>
                
                <a href="javascript:history.back()" class="btn btn-secondary btn-action">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                </a>
            <?php endif; ?>
        </div>
        
        <div class="mt-4">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Nếu bạn cho rằng đây là lỗi, vui lòng liên hệ quản trị viên hệ thống
            </small>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto redirect countdown (only if user is logged in)
        <?php if ($is_logged_in && isset($dashboard_links[$user_role])): ?>
        let countdown = 10;
        const countdownElement = document.getElementById('countdown');
        const countdownNumber = document.getElementById('countdownNumber');
        const dashboardLink = document.getElementById('dashboardLink');
        
        function updateCountdown() {
            countdownNumber.textContent = countdown;
            
            if (countdown <= 0) {
                if (dashboardLink) {
                    window.location.href = dashboardLink.href;
                }
            } else {
                countdown--;
                setTimeout(updateCountdown, 1000);
            }
        }
        
        // Show countdown and start
        setTimeout(() => {
            countdownElement.style.display = 'block';
            updateCountdown();
        }, 2000);
        
        // Stop countdown if user interacts with page
        document.addEventListener('click', function() {
            countdown = -1;
            countdownElement.style.display = 'none';
        });
        
        document.addEventListener('keydown', function() {
            countdown = -1;
            countdownElement.style.display = 'none';
        });
        <?php endif; ?>
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'Escape':
                    history.back();
                    break;
                case 'Enter':
                    <?php if ($is_logged_in && isset($dashboard_links[$user_role])): ?>
                        document.getElementById('dashboardLink')?.click();
                    <?php else: ?>
                        window.location.href = '/login.php';
                    <?php endif; ?>
                    break;
                case 'l':
                case 'L':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        <?php if ($is_logged_in): ?>
                            window.location.href = '/logout.php';
                        <?php else: ?>
                            window.location.href = '/login.php';
                        <?php endif; ?>
                    }
                    break;
            }
        });
        
        // Add entrance animation
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.error-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(50px) scale(0.9)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.8s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0) scale(1)';
            }, 100);
        });
        
        // Add click effects to buttons
        document.querySelectorAll('.btn-action').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.pointerEvents = 'none';
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    </script>
    
    <style>
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        .btn-action {
            position: relative;
            overflow: hidden;
        }
    </style>
</body>
</html>