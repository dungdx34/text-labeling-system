<?php
session_start();

// Include files in correct order (database first, then functions)
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication using function from functions.php
requireRole('labeler');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';

// Get group_id from URL
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (!$group_id) {
    header('Location: dashboard.php?error=no_group_id');
    exit();
}

try {
    // Get document group info
    $group = getDocumentGroup($group_id);
    
    if (!$group) {
        header('Location: dashboard.php?error=group_not_found');
        exit();
    }
    
    // Check if user is assigned to this group
    if ($group['assigned_labeler'] != $user_id) {
        header('Location: dashboard.php?error=not_assigned');
        exit();
    }
    
    // Get documents in this group
    $documents = getDocumentsByGroup($group_id);
    
    // Check if labeling already exists
    $existing_labeling = getLabeling($group_id, $user_id);
    
    // Initialize variables
    $selected_sentences = [];
    $edited_summary = $group['ai_summary'];
    $text_style = '';
    
    if ($existing_labeling) {
        $selected_sentences = json_decode($existing_labeling['selected_sentences'] ?? '[]', true);
        $edited_summary = $existing_labeling['edited_summary'] ?? $group['ai_summary'];
        $text_style = $existing_labeling['text_style'] ?? '';
    }

} catch (Exception $e) {
    $error_message = "Lỗi khi tải dữ liệu: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gán nhãn văn bản - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        .sentence {
            padding: 8px 12px;
            margin: 4px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            line-height: 1.6;
            background-color: #f8f9fa;
        }
        .sentence:hover {
            background-color: #e9ecef;
            border-color: #007bff;
        }
        .sentence.selected {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .summary-editor {
            min-height: 300px;
            resize: vertical;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
        }
        .summary-editor:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .progress-step {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .step.active {
            background: #007bff;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_tasks.php">
                                <i class="fas fa-tasks me-2"></i>My Tasks
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="labeling.php">
                                <i class="fas fa-edit me-2"></i>Labeling
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="multi_labeling.php">
                                <i class="fas fa-copy me-2"></i>Multi Labeling
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-edit me-2 text-primary"></i>
                        Gán nhãn văn bản
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại Dashboard
                        </a>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Group Information -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Thông tin văn bản
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="text-primary">Tiêu đề:</h6>
                                <p class="mb-2"><strong><?php echo htmlspecialchars($group['title']); ?></strong></p>
                                
                                <h6 class="text-primary">Mô tả:</h6>
                                <p class="mb-2"><?php echo htmlspecialchars($group['description'] ?? ''); ?></p>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-primary">Thông tin khác:</h6>
                                <p class="mb-1"><strong>Loại:</strong> <?php echo $group['group_type'] === 'multi' ? 'Đa văn bản' : 'Đơn văn bản'; ?></p>
                                <p class="mb-1"><strong>Số văn bản:</strong> <?php echo count($documents); ?></p>
                                <p class="mb-0"><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($group['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Steps -->
                <div class="progress-step">
                    <div class="step active" id="step-1">1</div>
                    <div class="step" id="step-2">2</div>
                    <div class="step" id="step-3">3</div>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs" id="labelingTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="step1-tab" data-bs-toggle="tab" data-bs-target="#step1" type="button">
                            <i class="fas fa-mouse-pointer me-2"></i>Bước 1: Chọn câu quan trọng
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="step2-tab" data-bs-toggle="tab" data-bs-target="#step2" type="button">
                            <i class="fas fa-palette me-2"></i>Bước 2: Xác định phong cách
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="step3-tab" data-bs-toggle="tab" data-bs-target="#step3" type="button">
                            <i class="fas fa-edit me-2"></i>Bước 3: Chỉnh sửa tóm tắt
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="labelingTabContent">
                    <!-- Step 1: Select Important Sentences -->
                    <div class="tab-pane fade show active" id="step1" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <!-- Documents -->
                                <?php if (!empty($documents)): ?>
                                    <?php foreach ($documents as $index => $document): ?>
                                        <div class="card mb-3">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-file-text me-2"></i>
                                                    <?php echo htmlspecialchars($document['title']); ?>
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="document-content" data-document-id="<?php echo $document['id']; ?>">
                                                    <?php
                                                    $sentences = preg_split('/(?<=[.!?])\s+/', $document['content'], -1, PREG_SPLIT_NO_EMPTY);
                                                    foreach ($sentences as $sentence_index => $sentence):
                                                        $sentence_id = $document['id'] . '_' . $sentence_index;
                                                        $is_selected = in_array($sentence_id, $selected_sentences);
                                                    ?>
                                                        <div class="sentence <?php echo $is_selected ? 'selected' : ''; ?>" 
                                                             data-sentence-id="<?php echo $sentence_id; ?>"
                                                             data-document-id="<?php echo $document['id']; ?>"
                                                             data-sentence-index="<?php echo $sentence_index; ?>">
                                                            <?php echo htmlspecialchars(trim($sentence)); ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Không có văn bản nào trong nhóm này.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-4">
                                <!-- Selected Sentences Summary -->
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-list me-2"></i>
                                            Câu đã chọn (<span id="selected-count">0</span>)
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="selected-sentences-summary">
                                            <div class="text-muted text-center py-3">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Chưa chọn câu nào
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-primary w-100" onclick="goToStep(2)">
                                                Tiếp theo <i class="fas fa-arrow-right ms-2"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Text Style -->
                    <div class="tab-pane fade" id="step2" role="tabpanel">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-palette me-2"></i>
                                    Xác định phong cách văn bản
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Chọn phong cách văn bản phù hợp:</h6>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="text_style" id="formal" value="formal" <?php echo $text_style === 'formal' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="formal">
                                                <strong>Trang trọng</strong> - Ngôn ngữ chính thức, khách quan
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="text_style" id="informal" value="informal" <?php echo $text_style === 'informal' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="informal">
                                                <strong>Thân thiện</strong> - Ngôn ngữ gần gụi, dễ hiểu
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="text_style" id="academic" value="academic" <?php echo $text_style === 'academic' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="academic">
                                                <strong>Học thuật</strong> - Ngôn ngữ chuyên môn, chi tiết
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="text_style" id="news" value="news" <?php echo $text_style === 'news' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="news">
                                                <strong>Báo chí</strong> - Ngôn ngữ tin tức, súc tích
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Hướng dẫn:</h6>
                                        <div class="alert alert-info">
                                            <ul class="mb-0">
                                                <li><strong>Trang trọng:</strong> Phù hợp với văn bản công việc, báo cáo</li>
                                                <li><strong>Thân thiện:</strong> Phù hợp với blog, bài viết cá nhân</li>
                                                <li><strong>Học thuật:</strong> Phù hợp với nghiên cứu, luận văn</li>
                                                <li><strong>Báo chí:</strong> Phù hợp với tin tức, bài viết truyền thông</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary" onclick="goToStep(1)">
                                        <i class="fas fa-arrow-left me-2"></i>Quay lại
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">
                                        Tiếp theo <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Edit Summary -->
                    <div class="tab-pane fade" id="step3" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-secondary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-robot me-2"></i>
                                            Bản tóm tắt AI gốc
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="border rounded p-3 bg-light" style="min-height: 300px;">
                                            <?php echo nl2br(htmlspecialchars($group['ai_summary'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-edit me-2"></i>
                                            Bản tóm tắt đã chỉnh sửa
                                        </h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <textarea class="summary-editor border-0 w-100" 
                                                  id="edited-summary" 
                                                  placeholder="Chỉnh sửa bản tóm tắt dựa trên các câu đã chọn và phong cách đã xác định..."><?php echo htmlspecialchars($edited_summary); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="goToStep(2)">
                                <i class="fas fa-arrow-left me-2"></i>Quay lại
                            </button>
                            <div>
                                <button type="button" class="btn btn-success me-2" onclick="saveDraft()">
                                    <i class="fas fa-save me-2"></i>Lưu nháp
                                </button>
                                <button type="button" class="btn btn-primary" onclick="completeLabeling()">
                                    <i class="fas fa-check me-2"></i>Hoàn thành
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedSentences = <?php echo json_encode($selected_sentences); ?>;
        let autoSaveTimer;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedSentencesDisplay();
            setupAutoSave();
        });

        // Sentence selection
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('sentence')) {
                const sentenceId = e.target.getAttribute('data-sentence-id');
                
                if (e.target.classList.contains('selected')) {
                    // Deselect
                    e.target.classList.remove('selected');
                    selectedSentences = selectedSentences.filter(id => id !== sentenceId);
                } else {
                    // Select
                    e.target.classList.add('selected');
                    selectedSentences.push(sentenceId);
                }
                
                updateSelectedSentencesDisplay();
                autoSave();
            }
        });

        // Update selected sentences display
        function updateSelectedSentencesDisplay() {
            const container = document.getElementById('selected-sentences-summary');
            const countElement = document.getElementById('selected-count');
            
            countElement.textContent = selectedSentences.length;
            
            if (selectedSentences.length === 0) {
                container.innerHTML = `
                    <div class="text-muted text-center py-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Chưa chọn câu nào
                    </div>
                `;
                return;
            }
            
            let html = '';
            selectedSentences.forEach(sentenceId => {
                const sentenceElement = document.querySelector(`[data-sentence-id="${sentenceId}"]`);
                if (sentenceElement) {
                    const text = sentenceElement.textContent.trim();
                    html += `
                        <div class="alert alert-success small mb-2">
                            <button class="btn-close btn-close-sm float-end" onclick="removeSentence('${sentenceId}')"></button>
                            ${text}
                        </div>
                    `;
                }
            });
            
            container.innerHTML = html;
        }

        // Remove sentence
        function removeSentence(sentenceId) {
            const sentenceElement = document.querySelector(`[data-sentence-id="${sentenceId}"]`);
            if (sentenceElement) {
                sentenceElement.classList.remove('selected');
            }
            selectedSentences = selectedSentences.filter(id => id !== sentenceId);
            updateSelectedSentencesDisplay();
            autoSave();
        }

        // Step navigation
        function goToStep(step) {
            // Update progress steps
            document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
            for (let i = 1; i <= step; i++) {
                const stepEl = document.getElementById(`step-${i}`);
                if (i < step) {
                    stepEl.classList.add('completed');
                } else if (i === step) {
                    stepEl.classList.add('active');
                }
            }
            
            // Switch tabs
            const tabTrigger = document.querySelector(`#step${step}-tab`);
            if (tabTrigger) {
                const tab = new bootstrap.Tab(tabTrigger);
                tab.show();
            }
        }

        // Text style change
        document.addEventListener('change', function(e) {
            if (e.target.name === 'text_style') {
                autoSave();
            }
        });

        // Summary editing
        document.getElementById('edited-summary').addEventListener('input', function() {
            autoSave();
        });

        // Auto save setup
        function setupAutoSave() {
            setInterval(function() {
                autoSave();
            }, 30000);
        }

        // Auto save function
        function autoSave() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                saveData(false);
            }, 2000);
        }

        // Save data
        function saveData(showMessage = false) {
            const data = {
                group_id: <?php echo $group_id; ?>,
                selected_sentences: selectedSentences,
                text_style: document.querySelector('input[name="text_style"]:checked')?.value || '',
                edited_summary: document.getElementById('edited-summary').value,
                is_draft: true
            };

            fetch('save_labeling.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (showMessage && result.success) {
                    alert('Đã lưu nháp thành công!');
                }
            })
            .catch(error => {
                console.error('Auto-save error:', error);
            });
        }

        // Save draft
        function saveDraft() {
            saveData(true);
        }

        // Complete labeling
        function completeLabeling() {
            if (selectedSentences.length === 0) {
                alert('Vui lòng chọn ít nhất một câu quan trọng!');
                goToStep(1);
                return;
            }

            const textStyle = document.querySelector('input[name="text_style"]:checked');
            if (!textStyle) {
                alert('Vui lòng chọn phong cách văn bản!');
                goToStep(2);
                return;
            }

            const editedSummary = document.getElementById('edited-summary').value.trim();
            if (!editedSummary) {
                alert('Vui lòng chỉnh sửa bản tóm tắt!');
                goToStep(3);
                return;
            }

            if (confirm('Bạn có chắc chắn muốn hoàn thành công việc gán nhãn này?')) {
                const data = {
                    group_id: <?php echo $group_id; ?>,
                    selected_sentences: selectedSentences,
                    text_style: textStyle.value,
                    edited_summary: editedSummary,
                    is_draft: false
                };

                fetch('save_labeling.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Hoàn thành gán nhãn thành công!');
                        window.location.href = 'dashboard.php';
                    } else {
                        alert('Có lỗi xảy ra: ' + (result.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra khi lưu dữ liệu!');
                });
            }
        }
    </script>
</body>
</html>