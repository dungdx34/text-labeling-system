<?php
require_once '../includes/auth.php';
require_once '../includes/enhanced_functions.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$ef = new EnhancedFunctions();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_documents') {
    $result = $ef->processDocumentUpload($_POST, $_FILES);
    
    if ($result['success']) {
        $_SESSION['upload_success'] = true;
        $_SESSION['upload_message'] = $result['message'];
        $_SESSION['group_id'] = $result['group_id'];
    } else {
        $_SESSION['upload_error'] = $result['message'];
    }
    
    header('Location: enhanced_upload.php');
    exit;
}

include '../includes/header.php';
?>

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
                        <a class="nav-link active" href="enhanced_upload.php">
                            <i class="fas fa-upload me-2"></i>Enhanced Upload
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>Quản lý người dùng
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Báo cáo
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-10 ms-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Enhanced Document Upload</h1>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['upload_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['upload_message']; ?>
                    <?php if (isset($_SESSION['group_id'])): ?>
                        <br><strong>Group ID:</strong> <?php echo $_SESSION['group_id']; ?>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php 
                unset($_SESSION['upload_success']); 
                unset($_SESSION['upload_message']); 
                unset($_SESSION['group_id']); 
                ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['upload_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['upload_error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['upload_error']); ?>
            <?php endif; ?>

            <!-- Enhanced Upload Interface -->
            <div class="main-content bg-white rounded shadow-sm p-4">
                <!-- Step Indicator -->
                <div class="step-indicator text-center mb-4">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="step active" id="step-1">1</div>
                        <div class="step-line"></div>
                        <div class="step" id="step-2">2</div>
                        <div class="step-line"></div>
                        <div class="step" id="step-3">3</div>
                    </div>
                    <div class="step-labels d-flex justify-content-center mt-2">
                        <span class="step-label active">Chọn loại</span>
                        <span class="step-label">Upload văn bản</span>
                        <span class="step-label">Xem trước</span>
                    </div>
                </div>

                <!-- Step 1: Choose Upload Type -->
                <div class="upload-step" id="upload-step-1">
                    <h4 class="text-center mb-4">Bước 1: Chọn loại văn bản cần upload</h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="upload-type-card" data-type="single" onclick="selectUploadType('single')">
                                <i class="fas fa-file-text fa-3x text-primary mb-3"></i>
                                <h5>Văn bản đơn</h5>
                                <p class="text-muted mb-0">Upload một văn bản và bản tóm tắt AI tương ứng</p>
                                <div class="mt-3">
                                    <span class="badge bg-light text-primary">1 văn bản</span>
                                    <span class="badge bg-light text-primary">1 tóm tắt</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="upload-type-card" data-type="multi" onclick="selectUploadType('multi')">
                                <i class="fas fa-copy fa-3x text-success mb-3"></i>
                                <h5>Đa văn bản</h5>
                                <p class="text-muted mb-0">Upload nhiều văn bản cùng với bản tóm tắt AI chung</p>
                                <div class="mt-3">
                                    <span class="badge bg-light text-success">Nhiều văn bản</span>
                                    <span class="badge bg-light text-success">1 tóm tắt chung</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button class="btn btn-primary btn-lg" id="next-to-step-2" style="display: none;" onclick="goToStep(2)">
                            Tiếp theo <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Upload Documents -->
                <div class="upload-step" id="upload-step-2" style="display: none;">
                    <h4 class="text-center mb-4">Bước 2: Upload văn bản</h4>
                    
                    <form id="upload-form" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_documents">
                        <input type="hidden" id="upload-type" name="upload_type" value="">
                        
                        <!-- Single Document Upload -->
                        <div id="single-upload" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-primary">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0"><i class="fas fa-file-text me-2"></i>Văn bản gốc</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="single_title" placeholder="Nhập tiêu đề văn bản..." required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Nội dung <span class="text-danger">*</span></label>
                                                <textarea class="form-control" name="single_content" rows="8" placeholder="Nhập nội dung văn bản..." required></textarea>
                                                <div class="form-text">Hoặc upload file bên dưới</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Upload file (tùy chọn)</label>
                                                <input type="file" class="form-control" name="single_file" accept=".txt,.docx,.pdf" onchange="handleSingleFile(this)">
                                                <div class="form-text">Hỗ trợ: .txt, .docx, .pdf</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-success">
                                        <div class="card-header bg-success text-white">
                                            <h6 class="mb-0"><i class="fas fa-robot me-2"></i>Bản tóm tắt AI</h6>
                                        </div>
                                        <div class="card-body">
                                            <textarea class="form-control" name="single_summary" rows="14" placeholder="Nhập bản tóm tắt AI cho văn bản..." required></textarea>
                                            <div class="form-text mt-2">
                                                <i class="fas fa-info-circle text-info"></i>
                                                Bản tóm tắt này sẽ được labeler chỉnh sửa và cải thiện
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Multi Document Upload -->
                        <div id="multi-upload" style="display: none;">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="card border-info">
                                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="fas fa-copy me-2"></i>Danh sách văn bản</h6>
                                            <button type="button" class="btn btn-light btn-sm" onclick="addDocument()">
                                                <i class="fas fa-plus me-1"></i>Thêm văn bản
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Tiêu đề nhóm văn bản <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="group_title" placeholder="Nhập tiêu đề cho nhóm văn bản..." required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Mô tả nhóm</label>
                                                <textarea class="form-control" name="group_description" rows="2" placeholder="Mô tả ngắn về nhóm văn bản này..."></textarea>
                                            </div>
                                            
                                            <div id="documents-container">
                                                <!-- Document template will be inserted here -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning text-dark">
                                            <h6 class="mb-0"><i class="fas fa-robot me-2"></i>Bản tóm tắt AI chung</h6>
                                        </div>
                                        <div class="card-body">
                                            <textarea class="form-control" name="group_summary" rows="20" placeholder="Nhập bản tóm tắt AI cho toàn bộ nhóm văn bản..." required></textarea>
                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Bản tóm tắt này sẽ được sử dụng cho toàn bộ nhóm văn bản
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="d-flex justify-content-between mt-4">
                        <button class="btn btn-outline-secondary" onclick="goToStep(1)">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại
                        </button>
                        <button class="btn btn-primary" onclick="validateAndGoToStep3()">
                            Tiếp theo <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Preview and Confirm -->
                <div class="upload-step" id="upload-step-3" style="display: none;">
                    <h4 class="text-center mb-4">Bước 3: Xem trước và xác nhận</h4>
                    
                    <div id="preview-content">
                        <!-- Preview content will be generated here -->
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <button class="btn btn-outline-secondary" onclick="goToStep(2)">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại
                        </button>
                        <button class="btn btn-success btn-lg" onclick="submitUpload()">
                            <i class="fas fa-check me-2"></i>Xác nhận Upload
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.main-content { 
    background: white; 
    border-radius: 15px; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
}

.upload-type-card {
    border: 2px solid #e9ecef;
    border-radius: 15px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 220px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.upload-type-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.upload-type-card:hover {
    border-color: #0d6efd;
    background: #f8f9ff;
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(13, 110, 253, 0.15);
}

.upload-type-card:hover::before {
    left: 100%;
}

.upload-type-card.selected {
    border-color: #0d6efd;
    background: #e3f2fd;
    box-shadow: 0 8px 25px rgba(13, 110, 253, 0.2);
}

.step-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 30px 0;
}

.step {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 15px;
    font-weight: bold;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    position: relative;
}

.step.active {
    background: linear-gradient(135deg, #0d6efd, #6610f2);
    color: white;
    box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
}

.step.completed {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.step-line {
    width: 80px;
    height: 3px;
    background: #e9ecef;
    border-radius: 2px;
}

.step-labels {
    gap: 100px;
}

.step-label {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 500;
}

.step-label.active {
    color: #0d6efd;
    font-weight: 600;
}

.document-item {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 20px;
    margin: 15px 0;
    transition: all 0.3s ease;
}

.document-item:hover {
    border-color: #0d6efd;
    background: #e3f2fd;
}

.document-item.filled {
    border-style: solid;
    border-color: #28a745;
    background: #d4edda;
}
</style>

<script>
let currentStep = 1;
let uploadType = '';
let documentCounter = 0;

// Step navigation
function goToStep(step) {
    document.querySelectorAll('.upload-step').forEach(el => el.style.display = 'none');
    document.getElementById(`upload-step-${step}`).style.display = 'block';
    
    // Update step indicator
    document.querySelectorAll('.step').forEach((el, index) => {
        el.classList.remove('active', 'completed');
        if (index + 1 < step) {
            el.classList.add('completed');
        } else if (index + 1 === step) {
            el.classList.add('active');
        }
    });
    
    document.querySelectorAll('.step-label').forEach((el, index) => {
        el.classList.remove('active');
        if (index + 1 === step) {
            el.classList.add('active');
        }
    });
    
    currentStep = step;
    
    if (step === 3) {
        generatePreview();
    }
}

// Upload type selection
function selectUploadType(type) {
    uploadType = type;
    document.getElementById('upload-type').value = type;
    
    document.querySelectorAll('.upload-type-card').forEach(card => {
        card.classList.remove('selected');
    });
    document.querySelector(`[data-type="${type}"]`).classList.add('selected');
    
    document.getElementById('next-to-step-2').style.display = 'inline-block';
    
    if (type === 'single') {
        document.getElementById('single-upload').style.display = 'block';
        document.getElementById('multi-upload').style.display = 'none';
    } else {
        document.getElementById('single-upload').style.display = 'none';
        document.getElementById('multi-upload').style.display = 'block';
        if (documentCounter === 0) {
            addDocument();
        }
    }
}

// Multi-document functions
function addDocument() {
    documentCounter++;
    const container = document.getElementById('documents-container');
    
    const documentHtml = `
        <div class="document-item" data-doc-index="${documentCounter}">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-file-text me-2"></i>Văn bản #${documentCounter}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDocument(${documentCounter})" ${documentCounter === 1 ? 'style="display: none;"' : ''}>
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="doc_title[]" placeholder="Tiêu đề văn bản..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload file</label>
                        <input type="file" class="form-control" name="doc_file[]" accept=".txt,.docx,.pdf" onchange="handleDocumentFile(this, ${documentCounter})">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Nội dung <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="doc_content[]" rows="6" placeholder="Hoặc nhập nội dung trực tiếp..." required></textarea>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', documentHtml);
    updateRemoveButtons();
}

function removeDocument(index) {
    const element = document.querySelector(`[data-doc-index="${index}"]`);
    if (element) {
        element.remove();
        updateRemoveButtons();
    }
}

function updateRemoveButtons() {
    const documents = document.querySelectorAll('.document-item');
    documents.forEach((doc, index) => {
        const removeBtn = doc.querySelector('.btn-outline-danger');
        if (documents.length > 1) {
            removeBtn.style.display = 'inline-block';
        } else {
            removeBtn.style.display = 'none';
        }
    });
}

function handleSingleFile(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            document.querySelector('textarea[name="single_content"]').value = e.target.result;
        };
        
        reader.readAsText(file);
    }
}

function handleDocumentFile(input, index) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const textarea = document.querySelector(`[data-doc-index="${index}"] textarea[name="doc_content[]"]`);
            textarea.value = e.target.result;
            
            const docItem = document.querySelector(`[data-doc-index="${index}"]`);
            docItem.classList.add('filled');
        };
        
        reader.readAsText(file);
    }
}

function validateAndGoToStep3() {
    let isValid = true;
    let errorMessage = '';
    
    if (uploadType === 'single') {
        const title = document.querySelector('input[name="single_title"]').value.trim();
        const content = document.querySelector('textarea[name="single_content"]').value.trim();
        const summary = document.querySelector('textarea[name="single_summary"]').value.trim();
        
        if (!title || !content || !summary) {
            isValid = false;
            errorMessage = 'Vui lòng điền đầy đủ thông tin cho văn bản đơn.';
        }
    } else if (uploadType === 'multi') {
        const groupTitle = document.querySelector('input[name="group_title"]').value.trim();
        const groupSummary = document.querySelector('textarea[name="group_summary"]').value.trim();
        
        if (!groupTitle || !groupSummary) {
            isValid = false;
            errorMessage = 'Vui lòng điền tiêu đề nhóm và bản tóm tắt AI.';
        }
        
        const docTitles = document.querySelectorAll('input[name="doc_title[]"]');
        const docContents = document.querySelectorAll('textarea[name="doc_content[]"]');
        
        for (let i = 0; i < docTitles.length; i++) {
            if (!docTitles[i].value.trim() || !docContents[i].value.trim()) {
                isValid = false;
                errorMessage = `Vui lòng điền đầy đủ thông tin cho văn bản #${i + 1}.`;
                break;
            }
        }
    }
    
    if (isValid) {
        goToStep(3);
    } else {
        alert(errorMessage);
    }
}

function generatePreview() {
    const previewContainer = document.getElementById('preview-content');
    let previewHtml = '';

    if (uploadType === 'single') {
        const title = document.querySelector('input[name="single_title"]').value;
        const content = document.querySelector('textarea[name="single_content"]').value;
        const summary = document.querySelector('textarea[name="single_summary"]').value;

        previewHtml = `
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-text me-2"></i>Văn bản đơn</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-primary">Tiêu đề:</h6>
                            <p class="fw-bold">${title}</p>
                            
                            <h6 class="text-primary">Nội dung:</h6>
                            <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                ${content.substring(0, 500)}${content.length > 500 ? '...' : ''}
                            </div>
                            
                            <h6 class="text-primary mt-3">Bản tóm tắt AI:</h6>
                            <div class="border rounded p-3 bg-success bg-opacity-10">
                                ${summary}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-info">Thống kê:</h6>
                            <div class="row text-center">
                                <div class="col-12 mb-2">
                                    <div class="h4 text-info">${content.length}</div>
                                    <small class="text-muted">Ký tự</small>
                                </div>
                                <div class="col-12 mb-2">
                                    <div class="h4 text-success">${content.trim() ? content.trim().split(/\\s+/).length : 0}</div>
                                    <small class="text-muted">Từ</small>
                                </div>
                                <div class="col-12">
                                    <div class="h4 text-warning">${content.split(/[.!?]+/).filter(s => s.trim()).length}</div>
                                    <small class="text-muted">Câu</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else {
        const groupTitle = document.querySelector('input[name="group_title"]').value;
        const groupDescription = document.querySelector('textarea[name="group_description"]').value;
        const groupSummary = document.querySelector('textarea[name="group_summary"]').value;
        const documents = document.querySelectorAll('.document-item');

        previewHtml = `
            <div class="card border-info mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-copy me-2"></i>Nhóm văn bản (${documents.length} văn bản)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-info">Tiêu đề nhóm:</h6>
                            <p class="fw-bold">${groupTitle}</p>
                            
                            <h6 class="text-info">Mô tả:</h6>
                            <p>${groupDescription || 'Không có mô tả'}</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-warning">Bản tóm tắt AI chung:</h6>
                            <div class="border rounded p-3 bg-warning bg-opacity-10" style="max-height: 150px; overflow-y: auto;">
                                ${groupSummary}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
        `;

        documents.forEach((doc, index) => {
            const title = doc.querySelector('input[name="doc_title[]"]').value;
            const content = doc.querySelector('textarea[name="doc_content[]"]').value;
            
            previewHtml += `
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Văn bản #${index + 1}</h6>
                        </div>
                        <div class="card-body">
                            <h6 class="text-primary">Tiêu đề:</h6>
                            <p class="fw-bold">${title}</p>
                            
                            <h6 class="text-primary">Nội dung:</h6>
                            <div class="border rounded p-2 bg-light" style="max-height: 120px; overflow-y: auto; font-size: 0.9rem;">
                                ${content.substring(0, 200)}${content.length > 200 ? '...' : ''}
                            </div>
                            
                            <div class="row mt-2">
                                <div class="col-4 text-center">
                                    <small class="text-info">${content.length} ký tự</small>
                                </div>
                                <div class="col-4 text-center">
                                    <small class="text-success">${content.trim() ? content.trim().split(/\\s+/).length : 0} từ</small>
                                </div>
                                <div class="col-4 text-center">
                                    <small class="text-warning">${content.split(/[.!?]+/).filter(s => s.trim()).length} câu</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        previewHtml += '</div>';
    }

    previewContainer.innerHTML = previewHtml;
}

function submitUpload() {
    const submitBtn = event.target;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang upload...';
    submitBtn.disabled = true;
    
    // Submit the form
    document.getElementById('upload-form').submit();
}
</script>

<?php include '../includes/footer.php'; ?>
            