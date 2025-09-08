<?php
$page_title = 'Reviewer Dashboard';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requireRole('reviewer');

$functions = new Functions();

// Get completed labelings for review
$completed_labelings = $functions->getLabelings(null, 'completed');
$reviewed_count = count($functions->getLabelings(null, 'reviewed'));

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 p-0">
            <div class="sidebar p-3">
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="review.php">
                        <i class="fas fa-check-double me-2"></i>Review công việc
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-10">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-tachometer-alt me-2"></i>Reviewer Dashboard</h2>
                    <span class="text-muted">Xin chào, <?php echo $_SESSION['full_name']; ?></span>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($completed_labelings); ?></div>
                            <div class="text-muted">Cần review</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $reviewed_count; ?></div>
                            <div class="text-muted">Đã review</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($completed_labelings) + $reviewed_count; ?></div>
                            <div class="text-muted">Tổng cộng</div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Reviews -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-clipboard-check me-2"></i>Công việc cần review</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($completed_labelings)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>Hiện tại không có công việc nào cần review.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Tài liệu</th>
                                                    <th>Người gán nhãn</th>
                                                    <th>Phong cách</th>
                                                    <th>Hoàn thành</th>
                                                    <th>Thao tác</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($completed_labelings as $labeling): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($labeling['document_title']); ?></td>
                                                    <td><?php echo htmlspecialchars($labeling['labeler_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($labeling['text_style_name'] ?? 'Chưa chọn'); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($labeling['updated_at'])); ?></td>
                                                    <td>
                                                        <a href="review.php?labeling_id=<?php echo $labeling['id']; ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="fas fa-search me-1"></i>Review
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
<script src="../js/script.js"></script>

</body>
</html>