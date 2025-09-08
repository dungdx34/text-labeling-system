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
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../css/style.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
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
                        <li><a class="dropdown-item" href="#">
                            <i class="fas fa-user-plus text-info me-2"></i>
                            <div>
                                <div class="fw-semibold">Người dùng mới được thêm</div>
                                <small class="text-muted">3 giờ trước</small>
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
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                            <i class="fas fa-user-edit me-2 text-secondary"></i>Thông tin cá nhân
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal">
                            <i class="fas fa-cog me-2 text-secondary"></i>Cài đặt
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#helpModal">
                            <i class="fas fa-question-circle me-2 text-info"></i>Trợ giúp
                        </a></li>
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

    <!-- Page Loading Spinner -->
    <div id="pageLoader" class="position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center bg-light" style="z-index: 9999; display: none !important;">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Đang tải...</span>
            </div>
            <div class="mt-3 fw-semibold text-primary">Đang tải dữ liệu...</div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Thông tin cá nhân
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle text-primary" style="font-size: 4rem;"></i>
                        <h4 class="mt-2"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></h4>
                        <span class="badge bg-primary"><?php echo ucfirst($_SESSION['role'] ?? ''); ?></span>
                    </div>
                    
                    <form id="profileForm">
                        <div class="mb-3">
                            <label class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Họ và tên</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vai trò</label>
                            <input type="text" class="form-control" value="<?php echo ucfirst($_SESSION['role'] ?? ''); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
                            <input type="password" class="form-control" placeholder="Nhập mật khẩu mới">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-primary">Cập nhật</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-cog me-2"></i>Cài đặt
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <h6>Giao diện</h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="darkMode">
                            <label class="form-check-label" for="darkMode">Chế độ tối</label>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Thông báo</h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                            <label class="form-check-label" for="emailNotifications">Thông báo qua email</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="browserNotifications" checked>
                            <label class="form-check-label" for="browserNotifications">Thông báo trình duyệt</label>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Tự động lưu</h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="autoSave" checked>
                            <label class="form-check-label" for="autoSave">Tự động lưu khi gán nhãn</label>
                        </div>
                        <small class="text-muted">Lưu tiến độ mỗi 30 giây</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-primary">Lưu cài đặt</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-question-circle me-2"></i>Trợ giúp & Hướng dẫn
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="accordion" id="helpAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#help1">
                                    Cách thực hiện gán nhãn
                                </button>
                            </h2>
                            <div id="help1" class="accordion-collapse collapse show" data-bs-parent="#helpAccordion">
                                <div class="accordion-body">
                                    <strong>Quy trình gán nhãn 3 bước:</strong>
                                    <ol>
                                        <li><strong>Chọn câu quan trọng:</strong> Click vào các câu trong văn bản mà bạn cho là quan trọng</li>
                                        <li><strong>Chọn phong cách:</strong> Xác định phong cách văn bản phù hợp</li>
                                        <li><strong>Chỉnh sửa tóm tắt:</strong> Hoàn thiện bản tóm tắt của AI</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help2">
                                    Các phong cách văn bản
                                </button>
                            </h2>
                            <div id="help2" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                <div class="accordion-body">
                                    <ul>
                                        <li><strong>Tường thuật:</strong> Mô tả sự kiện, hiện tượng theo thời gian</li>
                                        <li><strong>Nghị luận:</strong> Trình bày quan điểm, lập luận về vấn đề</li>
                                        <li><strong>Miêu tả:</strong> Tả lại hình ảnh, đặc điểm sự vật</li>
                                        <li><strong>Biểu cảm:</strong> Thể hiện cảm xúc, tâm trạng</li>
                                        <li><strong>Thuyết minh:</strong> Giải thích, làm rõ về sự vật, hiện tượng</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help3">
                                    Phím tắt hữu ích
                                </button>
                            </h2>
                            <div id="help3" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                <div class="accordion-body">
                                    <ul>
                                        <li><strong>Ctrl + S:</strong> Lưu nhanh</li>
                                        <li><strong>Ctrl + Z:</strong> Hoàn tác</li>
                                        <li><strong>Tab:</strong> Chuyển sang bước tiếp theo</li>
                                        <li><strong>Shift + Tab:</strong> Quay lại bước trước</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6>Cần hỗ trợ thêm?</h6>
                        <p class="mb-2">Liên hệ với chúng tôi:</p>
                        <p class="mb-1"><i class="fas fa-envelope me-2"></i>support@textlabeling.com</p>
                        <p class="mb-0"><i class="fas fa-phone me-2"></i>+84 123 456 789</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Đã hiểu</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
        <!-- Toasts will be dynamically inserted here -->
    </div>

    <!-- Main Content Start -->
    <main class="main-wrapper">
        <!-- Content will be inserted here by individual pages -->