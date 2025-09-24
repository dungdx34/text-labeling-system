<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Debug Test</title>
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
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Test Button -->
        <div class="mb-3">
            <button onclick="alert('JavaScript works!')" class="btn btn-success">Test JavaScript</button>
            <button onclick="console.log('Console works!')" class="btn btn-info">Test Console</button>
            <button onclick="completeStep1()" class="btn btn-primary">Complete Step 1</button>
        </div>

        <!-- Step 1 -->
        <div id="step1" class="step-section active">
            <div class="card">
                <div class="card-header">
                    <h4>Bước 1: Chọn câu quan trọng</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6>Văn bản mẫu:</h6>
                            <div id="sentences">
                                <div class="sentence" onclick="toggleSentence(this, 0)">Đây là câu đầu tiên trong văn bản.</div>
                                <div class="sentence" onclick="toggleSentence(this, 1)">Câu thứ hai có nội dung quan trọng.</div>
                                <div class="sentence" onclick="toggleSentence(this, 2)">Câu cuối cùng kết thúc văn bản.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6>Câu đã chọn:</h6>
                            <div id="selected-display">
                                Chưa chọn câu nào
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button onclick="completeStep1()" class="btn btn-primary">Hoàn thành bước 1</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2 -->
        <div id="step2" class="step-section">
            <div class="card">
                <div class="card-header">
                    <h4>Bước 2: Chọn phong cách</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card text-center" onclick="selectStyle('formal')" style="cursor: pointer;">
                                <div class="card-body">
                                    <h6>Trang trọng</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center" onclick="selectStyle('casual')" style="cursor: pointer;">
                                <div class="card-body">
                                    <h6>Thân thiện</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button onclick="showStep(1)" class="btn btn-secondary">Quay lại</button>
                        <button onclick="completeStep2()" class="btn btn-primary" id="step2-btn" disabled>Hoàn thành bước 2</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3 -->
        <div id="step3" class="step-section">
            <div class="card">
                <div class="card-header">
                    <h4>Bước 3: Chỉnh sửa tóm tắt</h4>
                </div>
                <div class="card-body">
                    <textarea id="summary" class="form-control" rows="5" placeholder="Nhập bản tóm tắt..."></textarea>
                    <div class="mt-3">
                        <button onclick="showStep(2)" class="btn btn-secondary">Quay lại</button>
                        <button onclick="finishLabeling()" class="btn btn-success">Hoàn thành</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let selectedSentences = [];
        let selectedStyle = '';

        // Test if JavaScript loads
        console.log('JavaScript loaded successfully!');

        // Toggle sentence selection
        function toggleSentence(element, index) {
            console.log('Toggle sentence called:', index);
            
            if (element.classList.contains('selected')) {
                element.classList.remove('selected');
                selectedSentences = selectedSentences.filter(i => i !== index);
            } else {
                element.classList.add('selected');
                selectedSentences.push(index);
            }
            
            updateSelectedDisplay();
        }

        // Update selected sentences display
        function updateSelectedDisplay() {
            const display = document.getElementById('selected-display');
            const sentences = document.querySelectorAll('.sentence');
            
            if (selectedSentences.length > 0) {
                let html = '';
                selectedSentences.forEach(index => {
                    if (sentences[index]) {
                        html += '<div class="mb-1 p-2 bg-light rounded">' + sentences[index].textContent + '</div>';
                    }
                });
                display.innerHTML = html;
            } else {
                display.innerHTML = 'Chưa chọn câu nào';
            }
        }

        // Show specific step
        function showStep(stepNumber) {
            console.log('Showing step:', stepNumber);
            
            // Hide all steps
            document.querySelectorAll('.step-section').forEach(el => {
                el.classList.remove('active');
            });
            
            // Show target step
            const targetStep = document.getElementById('step' + stepNumber);
            if (targetStep) {
                targetStep.classList.add('active');
            } else {
                console.error('Step not found:', 'step' + stepNumber);
            }
        }

        // Complete step 1
        function completeStep1() {
            console.log('Complete step 1 called');
            
            if (selectedSentences.length === 0) {
                alert('Vui lòng chọn ít nhất một câu!');
                return;
            }
            
            showStep(2);
        }

        // Select writing style
        function selectStyle(style) {
            console.log('Style selected:', style);
            selectedStyle = style;
            
            // Enable step 2 button
            document.getElementById('step2-btn').disabled = false;
            
            // Highlight selected style (simple version)
            document.querySelectorAll('.card').forEach(card => {
                card.style.backgroundColor = '';
            });
            event.target.closest('.card').style.backgroundColor = '#e3f2fd';
        }

        // Complete step 2
        function completeStep2() {
            console.log('Complete step 2 called');
            
            if (!selectedStyle) {
                alert('Vui lòng chọn phong cách!');
                return;
            }
            
            showStep(3);
        }

        // Finish labeling
        function finishLabeling() {
            const summary = document.getElementById('summary').value;
            
            if (!summary.trim()) {
                alert('Vui lòng nhập tóm tắt!');
                return;
            }
            
            alert('Hoàn thành gán nhãn!\nCâu đã chọn: ' + selectedSentences.length + 
                  '\nPhong cách: ' + selectedStyle + 
                  '\nTóm tắt: ' + summary.substring(0, 50) + '...');
        }
    </script>
</body>
</html>