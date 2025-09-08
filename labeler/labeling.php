<?php
$page_title = 'Gán nhãn tài liệu';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requireRole('labeler');

$functions = new Functions();
$document_id = $_GET['document_id'] ?? 0;

if (!$document_id) {
    header('Location: dashboard.php');
    exit();
}

$document = $functions->getDocument($document_id);
if (!$document) {
    header('Location: dashboard.php');
    exit();
}

$text_styles = $functions->getTextStyles();
$existing_labeling = $functions->getLabeling($document_id, $_SESSION['user_id']);

// Parse existing data
$selected_sentences = [];
$selected_style = null;
$edited_summary = '';

if ($existing_labeling) {
    $selected_sentences = json_decode($existing_labeling['important_sentences'] ?? '[]', true) ?: [];
    $selected_style = $existing_labeling['text_style_id'];
    $edited_summary = $existing_labeling['edited_summary'] ?? '';
}

// Split document content into sentences
$sentences = preg_split('/(?<=[.!?])\s+/', $document['content'], -1, PREG_SPLIT_NO_EMPTY);

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 p-0">
            <div class="sidebar p-3">
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="#">
                        <i class="fas fa-tags me-2"></i>Gán nhãn
                    </a>
                    <a class="nav-link" href="my_tasks.php">
                        <i class="fas fa-tasks me-2"></i>Công việc của tôi
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
                            <i class="fas fa-tags me-2"></i>Gán nhãn tài liệu
                        </h2>
                        <div class="d-flex align-items-center gap-3">
                            <span class="text-muted">Tài liệu: <strong><?php echo htmlspecialchars($document['title']); ?></strong></span>
                            <span id="unsaved-indicator" class="badge bg-warning" style="display: none;">
                                <i class="fas fa-exclamation-triangle me-1"></i>Chưa lưu
                            </span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary" onclick="history.back()">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại
                        </button>
                        <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#helpModal">
                            <i class="fas fa-question-circle me-2"></i>Trợ giúp
                        </button>
                    </div>
                </div>
                
                <!-- Document Info Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="card-title text-primary">
                                    <i class="fas fa-file-text me-2"></i><?php echo htmlspecialchars($document['title']); ?>
                                </h5>
                                <p class="card-text text-muted mb-2">
                                    <i class="fas fa-calendar me-2"></i>Ngày upload: <?php echo date('d/m/Y H:i', strtotime($document['created_at'])); ?>
                                </p>
                                <p class="card-text text-muted">
                                    <i class="fas fa-align-left me-2"></i>Độ dài: <?php echo strlen($document['content']); ?> ký tự | 
                                    <?php echo count($sentences); ?> câu
                                </p>
                            </div>
                            <div class="col-md-4">
                                <div class="text-end">
                                    <div class="progress mb-2" style="height: 8px;">
                                        <div class="progress-bar bg-primary" id="overall-progress" style="width: 0%"></div>
                                    </div>
                                    <small class="text-muted">Tiến độ hoàn thành: <span id="progress-text">0%</span></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Step Indicator -->
                <div class="step-indicator mb-4">
                    <div class="step <?php echo empty($selected_sentences) ? 'active' : 'completed'; ?>" id="step-indicator-1">
                        <span>1</span>
                    </div>
                    <div class="step <?php echo !$selected_style ? '' : (empty($edited_summary) ? 'active' : 'completed'); ?>" id="step-indicator-2">
                        <span>2</span>
                    </div>
                    <div class="step <?php echo empty($edited_summary) ? '' : 'active'; ?>" id="step-indicator-3">
                        <span>3</span>
                    </div>
                </div>
                
                <input type="hidden" id="document-id" value="<?php echo $document_id; ?>">
                
                <!-- Step 1: Select Important Sentences -->
                <div class="labeling-step" id="step-1">
                    <div class="labeling-interface">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="text-primary">
                                <i class="fas fa-mouse-pointer me-2"></i>Bước 1: Chọn các câu quan trọng
                            </h4>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-primary fs-6">
                                    Đã chọn: <span id="selected-count" class="fw-bold">0</span> câu
                                </span>
                                <button class="btn btn-sm btn-outline-secondary" onclick="clearAllSelections()">
                                    <i class="fas fa-eraser me-1"></i>Xóa tất cả
                                </button>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Hướng dẫn:</strong> Click vào các câu mà bạn cho là quan trọng và có ý nghĩa chính trong văn bản. 
                            Các câu được chọn sẽ được highlight màu xanh.
                        </div>
                        
                        <div class="document-content border rounded p-4" style="max-height: 500px; overflow-y: auto; background: #fafafa;">
                            <?php foreach ($sentences as $index => $sentence): ?>
                                <div class="sentence-selectable <?php echo in_array($index, $selected_sentences) ? 'sentence-selected' : ''; ?>" 
                                     data-index="<?php echo $index; ?>">
                                    <?php echo htmlspecialchars(trim($sentence)); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="text-muted">
                                <i class="fas fa-info-circle me-2"></i>
                                Tổng số câu: <?php echo count($sentences); ?>
                            </div>
                            <button type="button" class="btn btn-primary btn-next-step">
                                Tiếp theo: Chọn phong cách <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Select Text Style -->
                <div class="labeling-step" id="step-2" style="display: none;">
                    <div class="labeling-interface">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="text-primary">
                                <i class="fas fa-palette me-2"></i>Bước 2: Chọn phong cách văn bản
                            </h4>
                            <span class="badge bg-info fs-6" id="selected-style-badge" style="display: none;">
                                Đã chọn phong cách
                            </span>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Hướng dẫn:</strong> Chọn một phong cách văn bản phù hợp nhất với nội dung tài liệu.
                        </div>
                        
                        <div class="row">
                            <?php foreach ($text_styles as $style): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="text-style-option <?php echo $selected_style == $style['id'] ? 'selected' : ''; ?>" 
                                     data-style-id="<?php echo $style['id']; ?>">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success me-3 mt-1" style="font-size: 1.2rem; opacity: 0;"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-2"><?php echo htmlspecialchars($style['name']); ?></h6>
                                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($style['description']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary btn-prev-step">
                                <i class="fas fa-arrow-left me-2"></i>Quay lại: Chọn câu
                            </button>
                            <button type="button" class="btn btn-primary btn-next-step">
                                Tiếp theo: Chỉnh sửa tóm tắt <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Edit AI Summary -->
                <div class="labeling-step" id="step-3" style="display: none;">
                    <div class="labeling-interface">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="text-primary">
                                <i class="fas fa-edit me-2"></i>Bước 3: Chỉnh sửa bản tóm tắt
                            </h4>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-info" onclick="suggestSummary()">
                                    <i class="fas fa-magic me-1"></i>Gợi ý AI
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="resetSummary()">
                                    <i class="fas fa-undo me-1"></i>Khôi phục gốc
                                </button>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Hướng dẫn:</strong> Chỉnh sửa bản tóm tắt để phù hợp với nội dung và các câu quan trọng đã chọn. 
                            Hãy đảm bảo tóm tắt ngắn gọn, súc tích và bao quát được ý chính.
                        </div>
                        
                        <?php if ($document['ai_summary']): ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <i class="fas fa-robot me-2"></i>Bản tóm tắt gốc của AI
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="original-summary">
                                            <?php echo nl2br(htmlspecialchars($document['ai_summary'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-edit me-2"></i>Bản tóm tắt đã chỉnh sửa
                                        </h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <textarea class="form-control summary-editor border-0" 
                                                  id="edited-summary" 
                                                  placeholder="Chỉnh sửa bản tóm tắt ở đây..."
                                                  data-auto-resize><?php echo htmlspecialchars($edited_summary ?: $document['ai_summary']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mb-4">
                            <label for="edited-summary" class="form-label fw-bold">Tạo bản tóm tắt:</label>
                            <textarea class="form-control summary-editor" 
                                      id="edited-summary" 
                                      rows="8"
                                      placeholder="Vui lòng tạo bản tóm tắt cho tài liệu này..."
                                      data-auto-resize><?php echo htmlspecialchars($edited_summary); ?></textarea>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Summary Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <div class="fs-4 fw-bold text-primary" id="word-count">0</div>
                                        <small class="text-muted">Số từ</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <div class="fs-4 fw-bold text-success" id="char-count">0</div>
                                        <small class="text-muted">Ký tự</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <div class="fs-4 fw-bold text-info" id="sentence-count">0</div>
                                        <small class="text-muted">Câu</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Lưu ý:</strong> Sau khi hoàn thành, công việc gán nhãn sẽ được gửi đến reviewer để kiểm tra.
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary btn-prev-step">
                                <i class="fas fa-arrow-left me-2"></i>Quay lại: Chọn phong cách
                            </button>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary" onclick="saveAsDraft()">
                                    <i class="fas fa-save me-2"></i>Lưu nháp
                                </button>
                                <button type="button" class="btn btn-success" onclick="labelingSystem.finalizeLabelingb()">
                                    <i class="fas fa-check me-2"></i>Hoàn thành gán nhãn
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="../js/script.js"></script>

<script>
// Initialize selected sentences and style from existing data
document.addEventListener('DOMContentLoaded', function() {
    labelingSystem = new LabelingSystem();
    
    // Set existing selected sentences
    <?php if (!empty($selected_sentences)): ?>
    labelingSystem.selectedSentences = <?php echo json_encode($selected_sentences); ?>;
    labelingSystem.updateSelectedSentencesDisplay();
    <?php endif; ?>
    
    // Set existing selected style
    <?php if ($selected_style): ?>
    labelingSystem.selectedTextStyle = '<?php echo $selected_style; ?>';
    document.querySelector(`[data-style-id="${labelingSystem.selectedTextStyle}"]`)?.classList.add('selected');
    updateStyleSelection();
    <?php endif; ?>
    
    // Initialize summary counter
    updateSummaryStats();
    
    // Auto-resize textareas
    initializeAutoResize();
    
    // Update overall progress
    updateOverallProgress();
});

// Text style selection visual feedback
function updateStyleSelection() {
    document.querySelectorAll('.text-style-option').forEach(option => {
        const icon = option.querySelector('.fa-check-circle');
        if (option.classList.contains('selected')) {
            icon.style.opacity = '1';
            document.getElementById('selected-style-badge').style.display = 'inline';
        } else {
            icon.style.opacity = '0';
        }
    });
}

// Add click handlers for text styles
document.querySelectorAll('.text-style-option').forEach(option => {
    option.addEventListener('click', function() {
        updateStyleSelection();
    });
});

// Summary statistics
function updateSummaryStats() {
    const textarea = document.getElementById('edited-summary');
    if (!textarea) return;
    
    const text = textarea.value;
    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
    const chars = text.length;
    const sentences = text.trim() ? text.split(/[.!?]+/).filter(s => s.trim()).length : 0;
    
    document.getElementById('word-count').textContent = words;
    document.getElementById('char-count').textContent = chars;
    document.getElementById('sentence-count').textContent = sentences;
}

// Add event listener for summary textarea
document.getElementById('edited-summary')?.addEventListener('input', updateSummaryStats);

// Clear all sentence selections
function clearAllSelections() {
    if (confirm('Bạn có chắc muốn xóa tất cả câu đã chọn?')) {
        document.querySelectorAll('.sentence-selected').forEach(sentence => {
            sentence.classList.remove('sentence-selected');
        });
        labelingSystem.selectedSentences = [];
        labelingSystem.updateSelectedSentencesDisplay();
        labelingSystem.markUnsavedChanges();
    }
}

// Save as draft
function saveAsDraft() {
    if (labelingSystem) {
        labelingSystem.autoSave();
        showToast('Đã lưu nháp thành công', 'success');
    }
}

// Reset to original summary
function resetSummary() {
    if (confirm('Bạn có chắc muốn khôi phục về bản tóm tắt gốc?')) {
        const originalSummary = `<?php echo addslashes($document['ai_summary'] ?? ''); ?>`;
        document.getElementById('edited-summary').value = originalSummary;
        updateSummaryStats();
        labelingSystem.markUnsavedChanges();
    }
}

// Suggest AI improvements (placeholder)
function suggestSummary() {
    showToast('Tính năng gợi ý AI đang được phát triển', 'info');
}

// Update overall progress
function updateOverallProgress() {
    let progress = 0;
    
    // Step 1: Sentence selection (33%)
    if (labelingSystem.selectedSentences.length > 0) {
        progress += 33;
    }
    
    // Step 2: Style selection (33%)
    if (labelingSystem.selectedTextStyle) {
        progress += 33;
    }
    
    // Step 3: Summary editing (34%)
    const summary = document.getElementById('edited-summary')?.value || '';
    if (summary.trim().length > 10) {
        progress += 34;
    }
    
    document.getElementById('overall-progress').style.width = `${progress}%`;
    document.getElementById('progress-text').textContent = `${progress}%`;
}

// Override labelingSystem methods to update progress
const originalToggleSentence = labelingSystem.toggleSentenceSelection;
labelingSystem.toggleSentenceSelection = function(sentence, index) {
    originalToggleSentence.call(this, sentence, index);
    updateOverallProgress();
};

const originalSelectStyle = labelingSystem.selectTextStyle;
labelingSystem.selectTextStyle = function(option) {
    originalSelectStyle.call(this, option);
    updateOverallProgress();
    updateStyleSelection();
};

// Update progress on summary change
document.getElementById('edited-summary')?.addEventListener('input', updateOverallProgress);

// Keyboard shortcuts for labeling
document.addEventListener('keydown', function(e) {
    // Ctrl + Enter to finalize
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        if (labelingSystem.currentStep === 3) {
            labelingSystem.finalizeLabelingb();
        }
    }
    
    // Escape to clear selection in step 1
    if (e.key === 'Escape' && labelingSystem.currentStep === 1) {
        clearAllSelections();
    }
});

// Auto-save every 30 seconds
setInterval(() => {
    if (labelingSystem && labelingSystem.hasUnsavedChanges) {
        labelingSystem.autoSave();
    }
}, 30000);
</script>

</body>
</html>