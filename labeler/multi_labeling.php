<?php
require_once '../includes/auth.php';
require_once '../includes/enhanced_functions.php';

// Check if user is labeler
if ($_SESSION['role'] !== 'labeler') {
    header('Location: ../index.php');
    exit;
}

$ef = new EnhancedFunctions();
$groupId = $_GET['group_id'] ?? null;

if (!$groupId) {
    header('Location: dashboard.php');
    exit;
}

// Get labeling task
$taskResult = $ef->getLabelingTask($groupId, $_SESSION['user_id']);
if (!$taskResult['success']) {
    $_SESSION['error'] = $taskResult['message'];
    header('Location: dashboard.php');
    exit;
}

$task = $taskResult['data'];
$documents = $task['documents'];
$existingLabeling = $task['existing_labeling'] ?? [];

include '../includes/header.php';
?>

<link rel="stylesheet" href="../css/multi-document.css">

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-tags me-2"></i>Gán nhãn đa văn bản
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_tasks.php">
                            <i class="fas fa-tasks me-2"></i>Nhiệm vụ của tôi
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-10 ms-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-copy me-2 text-primary"></i>
                    <?php echo htmlspecialchars($task['group_title']); ?>
                </h1>
                <div>
                    <button class="btn btn-success" onclick="saveLabeling()">
                        <i class="fas fa-save me-2"></i>Lưu tiến độ
                    </button>
                    <button class="btn btn-primary" onclick="submitLabeling()">
                        <i class="fas fa-check me-2"></i>Hoàn thành
                    </button>
                </div>
            </div>

            <!-- Task Info -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Thông tin nhóm văn bản</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Mô tả:</strong> <?php echo htmlspecialchars($task['group_description'] ?: 'Không có mô tả'); ?></p>
                            <p><strong>Số lượng văn bản:</strong> <?php echo count($documents); ?> văn bản</p>
                            <p><strong>Trạng thái:</strong> 
                                <span class="badge bg-<?php echo $task['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                    <?php echo $task['status']; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Tiến độ</h6>
                        </div>
                        <div class="card-body text-center">
                            <div class="progress-circle mb-3">
                                <div class="progress-text">
                                    <span id="progress-percentage">0%</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="h5 text-primary" id="selected-sentences">0</div>
                                    <small>Câu đã chọn</small>
                                </div>
                                <div class="col-6">
                                    <div class="h5 text-info" id="total-sentences"><?php 
                                        $totalSentences = 0;
                                        foreach ($documents as $doc) {
                                            $totalSentences += count($doc['sentences']);
                                        }
                                        echo $totalSentences;
                                    ?></div>
                                    <small>Tổng câu</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Labeling Interface -->
            <div class="row">
                <!-- Documents Panel -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <ul class="nav nav-tabs card-header-tabs" id="document-tabs" role="tablist">
                                <?php foreach ($documents as $index => $doc): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" 
                                            id="doc-<?php echo $doc['id']; ?>-tab" 
                                            data-bs-toggle="tab" 
                                            data-bs-target="#doc-<?php echo $doc['id']; ?>" 
                                            type="button" role="tab">
                                        <i class="fas fa-file-text me-1"></i>
                                        <?php echo htmlspecialchars(substr($doc['title'], 0, 20)); ?>
                                        <?php if (strlen($doc['title']) > 20) echo '...'; ?>
                                        <span class="badge bg-secondary ms-2" id="doc-<?php echo $doc['id']; ?>-count">0</span>
                                    </button>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="document-content">
                                <?php foreach ($documents as $index => $doc): ?>
                                <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" 
                                     id="doc-<?php echo $doc['id']; ?>" 
                                     role="tabpanel">
                                     
                                    <div class="document-header mb-3">
                                        <h5 class="text-primary"><?php echo htmlspecialchars($doc['title']); ?></h5>
                                        <div class="document-actions">
                                            <button class="btn btn-sm btn-outline-success" onclick="selectAllSentences(<?php echo $doc['id']; ?>)">
                                                <i class="fas fa-check-double me-1"></i>Chọn tất cả
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deselectAllSentences(<?php echo $doc['id']; ?>)">
                                                <i class="fas fa-times me-1"></i>Bỏ chọn tất cả
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="sentences-container" data-document-id="<?php echo $doc['id']; ?>">
                                        <?php foreach ($doc['sentences'] as $sentence): ?>
                                        <div class="sentence-item" 
                                             data-sentence-id="<?php echo $sentence['id']; ?>"
                                             data-document-id="<?php echo $doc['id']; ?>">
                                            <div class="sentence-checkbox">
                                                <input type="checkbox" 
                                                       class="sentence-check" 
                                                       id="sentence-<?php echo $doc['id']; ?>-<?php echo $sentence['id']; ?>"
                                                       data-document-id="<?php echo $doc['id']; ?>"
                                                       data-sentence-id="<?php echo $sentence['id']; ?>"
                                                       <?php echo isset($existingLabeling[$doc['id']]) && in_array($sentence['id'], $existingLabeling[$doc['id']]) ? 'checked' : ''; ?>>
                                            </div>
                                            <div class="sentence-content">
                                                <label for="sentence-<?php echo $doc['id']; ?>-<?php echo $sentence['id']; ?>" class="sentence-text">
                                                    <?php echo htmlspecialchars($sentence['text']); ?>
                                                </label>
                                            </div>
                                            <div class="sentence-actions">
                                                <button class="btn btn-sm btn-outline-info" onclick="highlightSentence(<?php echo $doc['id']; ?>, <?php echo $sentence['id']; ?>)">
                                                    <i class="fas fa-highlighter"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Summary Panel -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-robot me-2"></i>Bản tóm tắt AI - Chỉnh sửa</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Bản tóm tắt gốc của AI:</label>
                                <div class="original-summary p-3 bg-light rounded" style="max-height: 150px; overflow-y: auto;">
                                    <?php echo nl2br(htmlspecialchars($task['ai_summary'])); ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edited-summary" class="form-label">Bản tóm tắt đã chỉnh sửa:</label>
                                <textarea class="form-control" 
                                          id="edited-summary" 
                                          rows="12" 
                                          placeholder="Chỉnh sửa bản tóm tắt dựa trên các câu đã chọn..."><?php echo htmlspecialchars($task['ai_summary_edited'] ?? $task['ai_summary']); ?></textarea>
                            </div>
                            
                            <div class="summary-stats">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="h6 text-primary" id="summary-words">0</div>
                                        <small>Từ</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h6 text-info" id="summary-chars">0</div>
                                        <small>Ký tự</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Selected Sentences Preview -->
                    <div class="card mt-3">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-list me-2"></i>Câu đã chọn</h6>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            <div id="selected-sentences-preview">
                                <p class="text-muted text-center">Chưa có câu nào được chọn</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auto-save notification -->
            <div class="auto-save-notification" id="auto-save-notification">
                <i class="fas fa-save me-2"></i>Đã lưu tự động
            </div>
        </main>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>Xác nhận hoàn thành
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn hoàn thành nhiệm vụ gán nhãn này?</p>
                <div class="alert alert-info">
                    <strong>Thống kê:</strong>
                    <ul class="mb-0">
                        <li><span id="modal-selected-count">0</span> câu đã được chọn</li>
                        <li>Bản tóm tắt: <span id="modal-summary-length">0</span> ký tự</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-success" onclick="confirmSubmit()">
                    <i class="fas fa-check me-2"></i>Hoàn thành
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../js/multi-labeling.js"></script>
<script>
// Initialize page data
const TASK_DATA = <?php echo json_encode([
    'group_id' => $task['group_id'],
    'documents' => $documents,
    'existing_labeling' => $existingLabeling,
    'ai_summary' => $task['ai_summary']
]); ?>;

// Initialize multi-labeling interface
document.addEventListener('DOMContentLoaded', function() {
    initMultiLabeling();
});
</script>

<?php include '../includes/footer.php'; ?>