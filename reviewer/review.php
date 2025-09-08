<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Review công việc gán nhãn';

// Use absolute paths
$auth_path = __DIR__ . '/../includes/auth.php';
$functions_path = __DIR__ . '/../includes/functions.php';
$header_path = __DIR__ . '/../includes/header.php';

if (!file_exists($auth_path)) {
    die('Error: Cannot find auth.php at: ' . $auth_path);
}

if (!file_exists($functions_path)) {
    die('Error: Cannot find functions.php at: ' . $functions_path);
}

require_once $auth_path;
require_once $functions_path;

try {
    $auth = new Auth();
    $auth->requireRole('reviewer');

    $functions = new Functions();
    $success_message = '';
    $error_message = '';

    // Handle review submission
    if ($_POST && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
        $labeling_id = $_POST['labeling_id'];
        $review_status = $_POST['review_status']; // 'reviewed' or 'rejected'
        $review_notes = trim($_POST['review_notes']);
        $reviewer_id = $_SESSION['user_id'];
        
        if ($functions->updateLabelingReview($labeling_id, $reviewer_id, $review_notes, $review_status)) {
            $success_message = $review_status === 'reviewed' ? 'Phê duyệt thành công!' : 'Đã từ chối và gửi phản hồi!';
            
            // Update document status if reviewed
            if ($review_status === 'reviewed') {
                $labeling = $functions->getLabelingById($labeling_id);
                if ($labeling) {
                    $functions->updateDocumentStatus($labeling['document_id'], 'reviewed');
                }
            }
        } else {
            $error_message = 'Có lỗi khi lưu review. Vui lòng thử lại.';
        }
    }

    // Get labeling to review
    $labeling_id = $_GET['labeling_id'] ?? 0;
    $current_labeling = null;

    if ($labeling_id) {
        $current_labeling = $functions->getLabelingById($labeling_id);
        if (!$current_labeling || $current_labeling['status'] !== 'completed') {
            $current_labeling = null;
            $error_message = 'Không tìm thấy công việc cần review hoặc công việc đã được review.';
        }
    }

    // Get all completed labelings for list
    $completed_labelings = $functions->getLabelings(null, 'completed');

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// Include header if exists, otherwise use simple header
if (file_exists($header_path)) {
    require_once $header_path;
} else {
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar { background: linear-gradient(180deg, #0d6efd 0%, #0a58ca 100%); min-height: calc(100vh - 56px); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); transition: all 0.3s; border-radius: 8px; margin: 4px 0; padding: 12px 16px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: white; background: rgba(255,255,255,0.15); transform: translateX(8px); }
        .main-content { background: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin: 20px; padding: 40px; }
        .text-gradient { background: linear-gradient(135deg, #0d6efd, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .navbar { background: rgba(13, 110, 253, 0.95) !important; backdrop-filter: blur(10px); }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .review-interface { background: #f8f9fa; border-radius: 12px; padding: 30px; margin: 20px 0; }
        .sentence-highlight { background: linear-gradient(135deg, #bbdefb 0%, #c8e6c9 100%); padding: 8px 12px; margin: 4px 0; border-radius: 8px; border-left: 4px solid #0d6efd; }
        .original-summary { background: #e3f2fd; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3; }
        .edited-summary { background: #e8f5e8; padding: 20px; border-radius: 8px; border-left: 4px solid #4caf50; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-tags me-2"></i>Text Labeling System
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link">
                    <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['full_name'] ?? 'Reviewer'; ?>
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
                </a>
            </div>
        </div>
    </nav>
<?php } ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 p-0">
            <div class="sidebar p-3">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="review.php">
                        <i class="fas fa-check-double me-2"></i>Review công việc
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-10">
            <div class="main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="text-gradient">
                            <i class="fas fa-check-double me-2"></i>Review công việc gán nhãn
                        </h2>
                        <p class="text-muted mb-0">
                            <?php if ($current_labeling): ?>
                                Đang review: <strong><?php echo htmlspecialchars($current_labeling['document_title']); ?></strong>
                            <?php else: ?>
                                Chọn công việc để review
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Dashboard
                        </a>
                        <?php if ($current_labeling): ?>
                        <button class="btn btn-outline-info" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Làm mới
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Alerts -->
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
                
                <?php if ($current_labeling): ?>
                    <!-- Review Interface -->
                    <div class="review-interface">
                        <!-- Document Information -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <h4 class="text-primary">
                                    <i class="fas fa-file-text me-2"></i><?php echo htmlspecialchars($current_labeling['document_title']); ?>
                                </h4>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <p><strong>Người gán nhãn:</strong> <?php echo htmlspecialchars($current_labeling['labeler_name']); ?></p>
                                        <p><strong>Phong cách đã chọn:</strong> 
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($current_labeling['text_style_name'] ?? 'Chưa xác định'); ?></span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Ngày hoàn thành:</strong> <?php echo date('d/m/Y H:i', strtotime($current_labeling['updated_at'])); ?></p>
                                        <p><strong>Trạng thái:</strong> 
                                            <span class="badge bg-warning">Cần review</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-end">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Hướng dẫn:</strong> Kiểm tra các câu đã chọn, phong cách văn bản và bản tóm tắt đã chỉnh sửa.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Content Review -->
                        <div class="row">
                            <div class="col-lg-6">
                                <!-- Document Content with Selected Sentences -->
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-file-alt me-2"></i>Nội dung tài liệu & Câu đã chọn
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div style="max-height: 400px; overflow-y: auto;">
                                            <?php 
                                            $content = $current_labeling['document_content'];
                                            $sentences = preg_split('/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);
                                            $selected_sentences = json_decode($current_labeling['important_sentences'] ?? '[]', true) ?: [];
                                            
                                            foreach ($sentences as $index => $sentence):
                                                $isSelected = in_array($index, $selected_sentences);
                                            ?>
                                            <div class="<?php echo $isSelected ? 'sentence-highlight' : ''; ?> mb-2">
                                                <?php if ($isSelected): ?>
                                                    <i class="fas fa-check-circle text-success me-2"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars(trim($sentence)); ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <small class="text-muted">
                                                <i class="fas fa-lightbulb me-1"></i>
                                                Đã chọn <strong><?php echo count($selected_sentences); ?></strong> câu quan trọng 
                                                trong tổng số <strong><?php echo count($sentences); ?></strong> câu
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <!-- Summary Comparison -->
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-edit me-2"></i>So sánh bản tóm tắt
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <!-- Original AI Summary -->
                                        <?php if ($current_labeling['ai_summary']): ?>
                                        <div class="mb-3">
                                            <h6 class="text-info">
                                                <i class="fas fa-robot me-2"></i>Bản tóm tắt gốc của AI:
                                            </h6>
                                            <div class="original-summary">
                                                <?php echo nl2br(htmlspecialchars($current_labeling['ai_summary'])); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Edited Summary -->
                                        <div class="mb-3">
                                            <h6 class="text-success">
                                                <i class="fas fa-user-edit me-2"></i>Bản tóm tắt đã chỉnh sửa:
                                            </h6>
                                            <div class="edited-summary">
                                                <?php echo nl2br(htmlspecialchars($current_labeling['edited_summary'])); ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Summary Statistics -->
                                        <div class="row">
                                            <div class="col-4">
                                                <div class="text-center">
                                                    <div class="h5 text-primary"><?php echo str_word_count($current_labeling['edited_summary']); ?></div>
                                                    <small class="text-muted">Từ</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-center">
                                                    <div class="h5 text-success"><?php echo strlen($current_labeling['edited_summary']); ?></div>
                                                    <small class="text-muted">Ký tự</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-center">
                                                    <div class="h5 text-info"><?php echo count(explode('.', $current_labeling['edited_summary'])); ?></div>
                                                    <small class="text-muted">Câu</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Review Form -->
                        <div class="card mt-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-clipboard-check me-2"></i>Quyết định review
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="submit_review">
                                    <input type="hidden" name="labeling_id" value="<?php echo $current_labeling['id']; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="review_notes" class="form-label">Ghi chú review</label>
                                                <textarea class="form-control" id="review_notes" name="review_notes" 
                                                          rows="4" placeholder="Nhập ghi chú, góp ý hoặc yêu cầu chỉnh sửa..."></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Quyết định</label>
                                                <div class="d-grid gap-2">
                                                    <button type="submit" name="review_status" value="reviewed" 
                                                            class="btn btn-success btn-lg"
                                                            onclick="return confirm('Bạn có chắc chắn muốn phê duyệt công việc này?')">
                                                        <i class="fas fa-check me-2"></i>Phê duyệt
                                                    </button>
                                                    <button type="submit" name="review_status" value="rejected" 
                                                            class="btn btn-danger btn-lg"
                                                            onclick="return confirm('Bạn có chắc chắn muốn từ chối công việc này?')">
                                                        <i class="fas fa-times me-2"></i>Từ chối
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                
                <?php else: ?>
                    <!-- List of Pending Reviews -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Danh sách công việc cần review
                                <span class="badge bg-warning ms-2"><?php echo count($completed_labelings); ?> công việc</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($completed_labelings)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-clipboard-check text-muted" style="font-size: 4rem;"></i>
                                    <h4 class="text-muted mt-3">Không có công việc nào cần review</h4>
                                    <p class="text-muted">Tất cả công việc đã được review hoặc chưa có công việc nào hoàn thành.</p>
                                    <a href="dashboard.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-left me-2"></i>Quay lại Dashboard
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="bg-primary text-white">
                                            <tr>
                                                <th>Tài liệu</th>
                                                <th>Người gán nhãn</th>
                                                <th>Phong cách</th>
                                                <th>Hoàn thành</th>
                                                <th>Độ ưu tiên</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($completed_labelings as $labeling): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($labeling['document_title']); ?></div>
                                                    <small class="text-muted">ID: <?php echo $labeling['document_id']; ?></small>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm me-2">
                                                            <?php 
                                                            $initials = strtoupper(substr($labeling['labeler_name'], 0, 1));
                                                            $colors = ['bg-primary', 'bg-success', 'bg-warning', 'bg-info'];
                                                            $color = $colors[$labeling['labeler_id'] % count($colors)];
                                                            ?>
                                                            <div class="rounded-circle d-flex align-items-center justify-content-center <?php echo $color; ?> text-white fw-bold" 
                                                                 style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                                <?php echo $initials; ?>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold"><?php echo htmlspecialchars($labeling['labeler_name']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($labeling['text_style_name'] ?? 'Chưa xác định'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-muted small">
                                                        <?php 
                                                        $completed_time = strtotime($labeling['updated_at']);
                                                        $time_diff = time() - $completed_time;
                                                        
                                                        if ($time_diff < 3600) {
                                                            echo floor($time_diff / 60) . ' phút trước';
                                                        } elseif ($time_diff < 86400) {
                                                            echo floor($time_diff / 3600) . ' giờ trước';
                                                        } else {
                                                            echo date('d/m/Y H:i', $completed_time);
                                                        }
                                                        ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $priority = 'normal';
                                                    $priority_class = 'secondary';
                                                    $priority_text = 'Bình thường';
                                                    
                                                    if ($time_diff > 86400) { // > 1 day
                                                        $priority = 'high';
                                                        $priority_class = 'danger';
                                                        $priority_text = 'Cao';
                                                    } elseif ($time_diff > 43200) { // > 12 hours
                                                        $priority = 'medium';
                                                        $priority_class = 'warning';
                                                        $priority_text = 'Trung bình';
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $priority_class; ?>">
                                                        <?php echo $priority_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="review.php?labeling_id=<?php echo $labeling['id']; ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-search me-1"></i>Bắt đầu review
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
console.log('Review page loaded successfully');

// Auto-refresh list every 2 minutes if not reviewing a specific item
<?php if (!$current_labeling): ?>
setTimeout(() => {
    location.reload();
}, 120000);
<?php endif; ?>

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const reviewForm = document.querySelector('form');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            const reviewNotes = document.getElementById('review_notes');
            const rejectButton = e.submitter;
            
            if (rejectButton && rejectButton.value === 'rejected' && reviewNotes.value.trim() === '') {
                e.preventDefault();
                alert('Vui lòng nhập ghi chú khi từ chối công việc!');
                reviewNotes.focus();
                return false;
            }
        });
    }
});
</script>

</body>
</html>