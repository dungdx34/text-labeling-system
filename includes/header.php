<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Text Labeling System'; ?></title>
    <meta name="description" content="Hệ thống gán nhãn dữ liệu tóm tắt văn bản - Text Summarization Labeling System">
    <meta name="keywords" content="text labeling, summarization, AI, machine learning, annotation">
    <meta name="author" content="Text Labeling System">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --border-radius: 12px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(13, 110, 253, 0.9) !important;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            text-decoration: none !important;
        }

        .navbar-nav .nav-link {
            font-weight: 500;
            transition: var(--transition);
            border-radius: 8px;
            margin: 0 4px;
            padding: 8px 16px !important;
        }

        .navbar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }

        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, #0a58ca 100%);
            min-height: calc(100vh - 56px);
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .sidebar .nav {
            position: relative;
            z-index: 2;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            transition: var(--transition);
            border-radius: var(--border-radius);
            margin: 4px 0;
            font-weight: 500;
            padding: 12px 16px;
            border-left: 3px solid transparent;
            backdrop-filter: blur(10px);
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(8px);
            border-left-color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }

        .main-content {
            background: white;
            border-radius: 20px;
            box-shadow: var(--box-shadow);
            margin: 20px;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .main-content::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(13, 110, 253, 0.03) 0%, transparent 70%);
            pointer-events: none;
        }

        .main-content > * {
            position: relative;
            z-index: 2;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), #0a58ca);
            color: white;
            border: none;
            padding: 20px;
            font-weight: 600;
        }

        .stats-card {
            background: linear-gradient(135deg, white 0%, #f8f9fa 100%);
            border-radius: var(--border-radius);
            padding: 30px 25px;
            text-align: center;
            border: none;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stats-card:hover {
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 20px 50px rgba(13, 110, 253, 0.2);
        }

        .stats-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-color), #0a58ca);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .btn {
            border-radius: var(--border-radius);
            font-weight: 600;
            padding: 12px 24px;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0a58ca 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4);
        }

        .table {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            margin-bottom: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0a58ca 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 20px;
            font-size: 0.95rem;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.05) 0%, rgba(13, 110, 253, 0.02) 100%);
            transform: scale(1.01);
        }

        .badge {
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }

        .text-gradient {
            background: linear-gradient(135deg, var(--primary-color), #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -100%;
                transition: left 0.3s ease;
                z-index: 1000;
                width: 250px;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .main-content {
                margin: 10px;
                padding: 20px;
            }
        }
    </style>
    
    <!-- Chart.js for statistics -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <i class="fas fa-tags me-2"></i>
                <span class="fw-bold">Text Labeling System</span>
            </a>
            
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler d-lg-none" type="button" onclick="toggleSidebar()">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Items -->
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="navbar-nav ms-auto d-flex flex-row align-items-center">
                <!-- Notifications -->
                <div class="nav-item dropdown me-3">
                    <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            3
                            <span class="visually-hidden">unread messages</span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Thông báo mới</h6></li>
                        <li><a class="dropdown-item" href="#">
                            <i class="fas fa-file-text text-primary me-2"></i>
                            <div>
                                <div class="fw-semibold">Tài liệu mới cần gán nhãn</div>
                                <small class="text-muted">2 phút trước</small>
                            </div>
                        </a></li>
                        <li><a class="dropdown-item" href="#">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <div>
                                <div class="fw-semibold">Công việc đã được review</div>
                                <small class="text-muted">1 giờ trước</small>
                            </div>
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="#">Xem tất cả thông báo</a></li>
                    </ul>
                </div>
                
                <!-- User Profile Dropdown -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="avatar me-2">
                            <i class="fas fa-user-circle fs-4"></i>
                        </div>
                        <div class="d-none d-md-block">
                            <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                            <small class="text-light opacity-75"><?php echo ucfirst($_SESSION['role']); ?></small>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">
                            <div class="text-center">
                                <i class="fas fa-user-circle fs-2 text-primary"></i>
                                <div class="mt-2">
                                    <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['username']); ?></small>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Role-based navigation -->
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                            <li><a class="dropdown-item" href="../admin/dashboard.php">
                                <i class="fas fa-tachometer-alt me-2 text-primary"></i>Admin Dashboard
                            </a></li>
                            <li><a class="dropdown-item" href="../admin/users.php">
                                <i class="fas fa-users me-2 text-info"></i>Quản lý người dùng
                            </a></li>
                            <li><a class="dropdown-item" href="../admin/upload.php">
                                <i class="fas fa-upload me-2 text-success"></i>Upload dữ liệu
                            </a></li>
                            <li><a class="dropdown-item" href="../admin/reports.php">
                                <i class="fas fa-chart-bar me-2 text-warning"></i>Báo cáo
                            </a></li>
                        <?php elseif ($_SESSION['role'] == 'labeler'): ?>
                            <li><a class="dropdown-item" href="../labeler/dashboard.php">
                                <i class="fas fa-tachometer-alt me-2 text-primary"></i>Labeler Dashboard
                            </a></li>
                            <li><a class="dropdown-item" href="../labeler/my_tasks.php">
                                <i class="fas fa-tasks me-2 text-info"></i>Công việc của tôi
                            </a></li>
                        <?php elseif ($_SESSION['role'] == 'reviewer'): ?>
                            <li><a class="dropdown-item" href="../reviewer/dashboard.php">
                                <i class="fas fa-tachometer-alt me-2 text-primary"></i>Reviewer Dashboard
                            </a></li>
                            <li><a class="dropdown-item" href="../reviewer/review.php">
                                <i class="fas fa-check-double me-2 text-success"></i>Review công việc
                            </a></li>
                        <?php endif; ?>
                        
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                        </a></li>
                    </ul>
                </div>
            </div>
            <?php else: ?>
            <!-- Guest Navigation -->
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../login.php">
                    <i class="fas fa-sign-in-alt me-1"></i>Đăng nhập
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content Start -->
    <main class="main-wrapper">
        <!-- Content will be inserted here by individual pages -->