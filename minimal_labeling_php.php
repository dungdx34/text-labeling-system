<?php
session_start();

// Simple test data
$assignment = [
    'id' => 1,
    'title' => 'Test Assignment',
    'type' => 'single'
];

$documents = [
    [
        'id' => 1,
        'title' => 'Test Document',
        'content' => 'Đây là câu đầu tiên. Đây là câu thứ hai có nội dung quan trọng. Câu cuối cùng kết thúc văn bản.',
        'ai_summary' => 'Đây là bản tóm tắt AI mẫu cho tài liệu test.'
    ]
];

$view_only = false;
$error_message = '';
$success_message = '';

// Mock results data
$results_map = [
    1 => [
        'selected_sentences' => '[]',
        'writing_style' => '',
        'edited_summary' => '',
        'step1_completed' => 0,
        'step2_completed' => 0,
        'step3_completed' => 0
    ]
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Labeling</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sentence {
            padding: 10px;
            margin: 5px 0;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            background: #f8f9fa;
        }
        .sentence:hover {
            background: #e9ecef;
        }
        .sentence.selected {
            background: #e3f2fd;
            border-color: #2196f3;
            color: #1976d2;
        }
        .step-section {
            display: none;
        }
        .step-section.active {
            display: block;
        }
        .writing-style-option {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .writing-style-option:hover {
            border-color: #007bff;
        }
        .writing-style-option.selected {
            border-color: #007bff;
            background: #e3f2fd;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        
        <!-- Test Buttons -->
        <div class="mb-3">
            <button onclick="alert('JS Works!')" class="btn btn-success">Test JS</button>
            <button onclick="debugLog()" class="btn btn-info">Debug</button>
        </div>

        <h2>Test Labeling: <?php echo htmlspecialchars($assignment['title']); ?></h2>

        <?php foreach ($documents as $doc_index => $document): ?>
            <div class="document-container" id="document-<?php echo $doc_index; ?>">
                
                <!-- Step 1: Select Sentences -->
                <div class="step-section active" id="step1-<?php echo $doc_index; ?>">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Bước 1: Chọn câu quan trọng</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6>Nội dung văn bản:</h6>
                                    <div id="sentences-<?php echo $doc_index; ?>">
                                        <?php 
                                        $sentences = preg_split('/(?<=[.!?])\s+/', $document['content']);
                                        foreach ($sentences as $i => $sentence):
                                            if (trim($sentence)):
                                        ?>
                                            <div class="sentence" data-doc="<?php echo $doc_index; ?>" data-sentence="<?php echo $i; ?>" onclick="toggleSentence(this)">
                                                <?php echo htmlspecialchars(trim($sentence)); ?>
                                            </div>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <h6>Câu đã chọn:</h6>
                                    <div id="selected-sentences-<?php echo $doc_index; ?>">
                                        Chưa có câu nào được chọn
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button onclick="completeStep(1, <?php echo $doc_index; ?>)" class="btn btn-primary">
                                    Hoàn thành bước 1
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Writing Style -->
                <div class="step-section" id="step2-<?php echo $doc_index; ?>">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Bước 2: Chọn phong cách văn bản</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="writing-style-option" data-style="formal" onclick="selectStyle('formal', <?php echo $doc_index; ?>)">
                                        <h6>Trang trọng</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="writing-style-option" data-style="casual" onclick="selectStyle('casual', <?php echo $doc_index; ?>)">
                                        <h6>Thân thiện</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button onclick="showStep(1, <?php echo $doc_index; ?>)" class="btn btn-secondary">Quay lại</button>
                                <button onclick="completeStep(2, <?php echo $doc_index; ?>)" class="btn btn-primary" id="step2-complete-<?php echo $doc_index; ?>" disabled>
                                    Hoàn thành bước 2
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Edit Summary -->
                <div class="step-section" id="step3-<?php echo $doc_index; ?>">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Bước 3: Chỉnh sửa bản tóm tắt</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Bản tóm tắt AI gốc:</h6>
                                    <div class="border rounded p-3 bg-light">
                                        <?php echo htmlspecialchars($document['ai_summary']); ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Bản tóm tắt đã chỉnh sửa:</h6>
                                    <textarea class="form-control" id="edited-summary-<?php echo $doc_index; ?>" rows="5" 
                                              placeholder="Chỉnh sửa bản tóm tắt..."><?php echo htmlspecialchars($document['ai_summary']); ?></textarea>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button onclick="showStep(2, <?php echo $doc_index; ?>)" class="btn btn-secondary">Quay lại</button>
                                <button onclick="completeStep(3, <?php echo $doc_index; ?>)" class="btn btn-success">
                                    Hoàn thành gán nhãn
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>

    </div>

    <script>
        // Global variables
        let selectedSentences = {};
        let writingStyles = {};

        // Initialize
        selectedSentences[0] = [];
        writingStyles[0] = '';

        console.log('JavaScript loaded successfully!');

        function debugLog() {
            console.log('Current state:', {
                selectedSentences: selectedSentences,
                writingStyles: writingStyles
            });
            alert('Check console for debug info');
        }

        function toggleSentence(element) {
            const docIndex = parseInt(element.dataset.doc);
            const sentenceIndex = parseInt(element.dataset.sentence);
            
            console.log('Toggle sentence:', docIndex, sentenceIndex);
            
            if (element.classList.contains('selected')) {
                element.classList.remove('selected');
                selectedSentences[docIndex] = selectedSentences[docIndex].filter(i => i !== sentenceIndex);
            } else {
                element.classList.add('selected');
                selectedSentences[docIndex].push(sentenceIndex);
            }
            
            updateSelectedDisplay(docIndex);
        }

        function updateSelectedDisplay(docIndex) {
            const container = document.getElementById('selected-sentences-' + docIndex);
            const sentences = document.querySelectorAll(`#sentences-${docIndex} .sentence`);
            
            if (selectedSentences[docIndex].length > 0) {
                let html = '';
                selectedSentences[docIndex].forEach(index => {
                    if (sentences[index]) {
                        html += '<div class="mb-2 p-2 bg-light rounded">' + sentences[index].textContent + '</div>';
                    }
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = 'Chưa có câu nào được chọn';
            }
        }

        function selectStyle(style, docIndex) {
            console.log('Style selected:', style);
            writingStyles[docIndex] = style;
            
            // Update UI
            document.querySelectorAll(`#step2-${docIndex} .writing-style-option`).forEach(el => {
                el.classList.remove('selected');
            });
            document.querySelector(`#step2-${docIndex} .writing-style-option[data-style="${style}"]`).classList.add('selected');
            
            // Enable button
            document.getElementById('step2-complete-' + docIndex).disabled = false;
        }

        function showStep(step, docIndex) {
            console.log('Showing step:', step);
            
            // Hide all steps
            for (let i = 1; i <= 3; i++) {
                const stepEl = document.getElementById(`step${i}-${docIndex}`);
                if (stepEl) {
                    stepEl.classList.remove('active');
                }
            }
            
            // Show target step
            const targetStep = document.getElementById(`step${step}-${docIndex}`);
            if (targetStep) {
                targetStep.classList.add('active');
                console.log('Step', step, 'shown successfully');
            }
        }

        function completeStep(step, docIndex) {
            console.log('Complete step:', step);
            
            if (step === 1) {
                if (selectedSentences[docIndex].length === 0) {
                    alert('Vui lòng chọn ít nhất một câu quan trọng!');
                    return;
                }
                showStep(2, docIndex);
            } else if (step === 2) {
                if (!writingStyles[docIndex]) {
                    alert('Vui lòng chọn phong cách văn bản!');
                    return;
                }
                showStep(3, docIndex);
            } else if (step === 3) {
                const summary = document.getElementById('edited-summary-' + docIndex).value.trim();
                if (!summary) {
                    alert('Vui lòng nhập bản tóm tắt!');
                    return;
                }
                alert('Hoàn thành! Selected: ' + selectedSentences[docIndex].length + ' sentences, Style: ' + writingStyles[docIndex]);
            }
        }
    </script>
</body>
</html>