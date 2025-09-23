<?php
/**
 * Main Entry Point - Text Labeling System
 * Fixed routing with proper redirect handling
 */
session_start();

require_once 'includes/auth.php';

// If user is not logged in, redirect to login
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user info
$user = $auth->getCurrentUser();
if (!$user) {
    // Session might be corrupted, logout and redirect
    $auth->logout();
    header('Location: login.php?error=session_expired');
    exit();
}

// Get redirect URL based on role
$redirect_url = $auth->getRedirectUrl($user['role']);

// Check if we're already at the correct dashboard to prevent infinite redirect
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$redirect_path = parse_url($redirect_url, PHP_URL_PATH);

if ($current_path !== $redirect_path && $current_path !== '/index.php') {
    header("Location: $redirect_url");
    exit();
}

// If we're on index.php, show welcome page with redirect
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Text Labeling System - Chào mừng</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --admin-gradient: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            --labeler-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            --reviewer-gradient: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);
        }
        
        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        .main-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }
        
        .welcome-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            padding: 50px 40px;
            text-align: center;
            max-width: 600px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
            100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        }
        
        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 3rem;
            position: relative;
            z-index: 2;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .user-avatar.admin { background: var(--admin-gradient); }
        .user-avatar.labeler { background: var(--labeler-gradient); }
        .user-avatar.reviewer { background: var(--reviewer-gradient); }
        
        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            z-index: 2;
        }
        
        .welcome-subtitle {
            font-size: 1.5rem;
            color: #6c757d;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }
        
        .role-badge {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            margin: 20px 0;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            z-index: 2;
        }
        
        .role-badge.admin { background: var(--admin-gradient); }
        .role-badge.labeler { background: var(--labeler-gradient); }
        .role-badge.reviewer { background: var(--reviewer-gradient); }
        
        .btn-dashboard {
            background: var(--primary-gradient);
            border: none;
            border-radius: 15px;
            padding: 15px 35px;
            color: white;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 30px 10px 20px;
            transition: all 0.4s ease;
            font-size: 18px;
            position: relative;
            z-index: 2;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-dashboard:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
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
            box-shadow: 0 10px 25px rgba(108, 117, 125, 0.3);
        }
        
        .redirect-info {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border-left: 4px solid #ffc107;
            position: relative;
            z-index: 2;
        }
        
        .countdown-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            margin: 20px auto;
            position: relative;
            z-index: 2;
        }
        
        .stats-row {
            display: flex;
            justify-content: space-around;
            margin: 30px 0;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }
        
        .stat-item {
            text-align: center;
            margin: 10px;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #495057;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }
        
        .floating-icon {
            position: absolute;
            color: rgba(255, 255, 255, 0.1);
            animation: float 8s ease-in-out infinite;
        }
        
        .floating-icon:nth-child(1) { top: 10%; left: 10%; font-size: 2rem; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 20%; right: 10%; font-size: 1.5rem; animation-delay: 2s; }
        .floating-icon:nth-child(3) { bottom: 30%; left: 15%; font-size: 2.5rem; animation-delay: 4s; }
        .floating-icon:nth-child(4) { bottom: 20%; right: 20%; font-size: 1.8rem; animation-delay: 6s; }
        .floating-icon:nth-child(5) { top: 50%; left: 5%; font-size: 1.2rem; animation-delay: 1s; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.1; }
            50% { transform: translateY(-30px) rotate(180deg); opacity: 0.3; }
        }
        
        .progress-ring {
            transform: rotate(-90deg);
            margin: 20px auto;
        }
        
        .progress-ring circle {
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            transition: stroke-dashoffset 1s ease;
        }
        
        @media (max-width: 768px) {
            .welcome-card {
                padding: 40px 30px;
                margin: 10px;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .welcome-subtitle {
                font-size: 1.2rem;
            }
            
            .user-avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
            
            .stats-row {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <!-- Floating background elements -->
    <div class="floating-elements">
        <div class="floating-icon"><i class="fas fa-tags"></i></div>
        <div class="floating-icon"><i class="fas fa-file-alt"></i></div>
        <div class="floating-icon"><i class="fas fa-check-circle"></i></div>
        <div class="floating-icon"><i class="fas fa-users"></i></div>
        <div class="floating-icon"><i class="fas fa-chart-bar"></i></div>
    </div>

    <div class="main-container">
        <div class="welcome-card">
            <div class="user-avatar <?php echo $user['role']; ?>">
                <?php
                $role_icons = [
                    'admin' => 'fa-user-shield',
                    'labeler' => 'fa-tags',
                    'reviewer' => 'fa-check-circle'
                ];
                $icon = $role_icons[$user['role']] ?? 'fa-user';
                echo "<i class='fas $icon'></i>";
                ?>
            </div>
            
            <h1 class="welcome-title">Chào mừng trở lại!</h1>
            <h2 class="welcome-subtitle"><?php echo htmlspecialchars($user['full_name']); ?></h2>
            
            <span class="role-badge <?php echo $user['role']; ?>">
                <?php
                $role_names = [
                    'admin' => 'Quản trị viên',
                    'labeler' => 'Người gán nhãn',
                    'reviewer' => 'Người review'
                ];
                echo $role_names[$user['role']] ?? ucfirst($user['role']);
                ?>
            </span>
            
            <!-- User stats -->
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-number"><?php echo date('d/m/Y'); ?></div>
                    <div class="stat-label">Hôm nay</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo date('H:i'); ?></div>
                    <div class="stat-label">Giờ hiện tại</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php 
                        if ($user['last_login']) {
                            echo date('H:i', strtotime($user['last_login']));
                        } else {
                            echo 'Lần đầu';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Đăng nhập cuối</div>
                </div>
            </div>
            
            <div class="redirect-info">
                <div class="d-flex align-items-center justify-content-center mb-3">
                    <i class="fas fa-route text-warning me-2" style="font-size: 1.5rem;"></i>
                    <strong style="font-size: 1.1rem;">Chuyển hướng tự động</strong>
                </div>
                <p class="mb-3">Bạn sẽ được chuyển hướng đến dashboard phù hợp với vai trò của mình trong:</p>
                
                <!-- Countdown circle -->
                <div class="countdown-circle" id="countdownCircle">
                    <span id="countdown">5</span>
                </div>
                
                <!-- Progress ring -->
                <svg class="progress-ring" width="80" height="80">
                    <circle cx="40" cy="40" r="35" stroke="#ffc107" stroke-width="4" fill="transparent" id="progressRing"/>
                </svg>
            </div>
            
            <div class="d-flex justify-content-center flex-wrap">
                <?php
                $dashboard_links = [
                    'admin' => [
                        'url' => 'admin/dashboard.php', 
                        'text' => 'Admin Dashboard', 
                        'icon' => 'fa-tachometer-alt'
                    ],
                    'labeler' => [
                        'url' => 'labeler/dashboard.php', 
                        'text' => 'Labeler Dashboard', 
                        'icon' => 'fa-tags'
                    ],
                    'reviewer' => [
                        'url' => 'reviewer/dashboard.php', 
                        'text' => 'Reviewer Dashboard', 
                        'icon' => 'fa-check-circle'
                    ]
                ];
                
                $link_info = $dashboard_links[$user['role']] ?? null;
                if ($link_info):
                ?>
                    <a href="<?php echo $link_info['url']; ?>" class="btn-dashboard" id="dashboardLink">
                        <i class="fas <?php echo $link_info['icon']; ?> me-2"></i>
                        <?php echo $link_info['text']; ?>
                    </a>
                <?php endif; ?>
                
                <a href="logout.php" class="btn-dashboard btn-secondary">
                    <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                </a>
            </div>
            
            <div class="mt-4">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Phiên: <?php echo substr(session_id(), 0, 8); ?>... | 
                    IP: <?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?> |
                    Vai trò: <?php echo ucfirst($user['role']); ?>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Countdown and auto redirect
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        const progressRing = document.getElementById('progressRing');
        const dashboardLink = document.getElementById('dashboardLink');
        
        // Calculate progress ring circumference
        const circumference = 2 * Math.PI * 35;
        progressRing.style.strokeDasharray = circumference;
        
        function updateCountdown() {
            countdownElement.textContent = countdown;
            
            // Update progress ring
            const offset = circumference - (countdown / 5) * circumference;
            progressRing.style.strokeDashoffset = offset;
            
            if (countdown <= 0) {
                // Redirect to dashboard
                <?php if (isset($link_info)): ?>
                    window.location.href = '<?php echo $link_info['url']; ?>';
                <?php endif; ?>
            } else {
                countdown--;
                setTimeout(updateCountdown, 1000);
            }
        }
        
        // Start countdown
        updateCountdown();
        
        // Click dashboard link to stop countdown
        if (dashboardLink) {
            dashboardLink.addEventListener('click', function() {
                countdown = -1; // Stop countdown
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    if (dashboardLink) dashboardLink.click();
                    break;
                case 'Escape':
                    countdown = -1; // Stop countdown
                    break;
                case 'l':
                case 'L':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        window.location.href = 'logout.php';
                    }
                    break;
            }
        });
        
        // Add entrance animation
        document.addEventListener('DOMContentLoaded', function() {
            const card = document.querySelector('.welcome-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(50px) scale(0.9)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.8s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0) scale(1)';
            }, 100);
        });
        
        // Pause countdown on hover
        let countdownPaused = false;
        const welcomeCard = document.querySelector('.welcome-card');
        
        welcomeCard.addEventListener('mouseenter', function() {
            countdownPaused = true;
        });
        
        welcomeCard.addEventListener('mouseleave', function() {
            countdownPaused = false;
        });
        
        // Update countdown function with pause support
        function updateCountdown() {
            if (!countdownPaused) {
                countdownElement.textContent = countdown;
                
                // Update progress ring
                const offset = circumference - (countdown / 5) * circumference;
                progressRing.style.strokeDashoffset = offset;
                
                if (countdown <= 0) {
                    // Redirect to dashboard
                    <?php if (isset($link_info)): ?>
                        window.location.href = '<?php echo $link_info['url']; ?>';
                    <?php endif; ?>
                    return;
                } else {
                    countdown--;
                }
            }
            setTimeout(updateCountdown, 1000);
        }
        
        // Show tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Add click effect to buttons
        document.querySelectorAll('.btn-dashboard').forEach(btn => {
            btn.addEventListener('click', function(e) {
                // Create ripple effect
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
        
        .btn-dashboard {
            position: relative;
            overflow: hidden;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
    </style>
</body>
</html>