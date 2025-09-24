<?php
// Start session and error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// Simple auth check - no external functions needed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'labeler') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$current_user_id = $_SESSION['user_id'];

// Get current user info
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current_user) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$error_message = '';
$success_message = '';
$assignment = null;
$documents = [];
$view_only = isset($_GET['view']) && $_GET['view'] == '1';

// Get assignment ID
$assignment_id = $_GET['id'] ?? null;

if (!$assignment_id) {
    // If no specific ID, get first unfinished assignment
    try {
        $query = "SELECT id FROM assignments WHERE user_id = ? AND status IN ('pending', 'in_progress') ORDER BY id ASC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user_id]);
        $result = $stmt->fetch();
        
        if ($result) {
            $assignment_id = $result['id'];
        } else {
            $error_message = 'Không có công việc nào để gán nhãn.';
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi khi tìm công việc: ' . $e->getMessage();
    }
}

// Get assignment info
if ($assignment_id) {
    try {
        $query = "SELECT a.*, 
                         CASE 
                             WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN d.title 
                             WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN dg.group_name 
                             ELSE 'Untitled'
                         END as title,
                         CASE 
                             WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN d.ai_summary 
                             WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN dg.description 
                             ELSE ''
                         END as ai_summary,
                         CASE 
                             WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN 'single' 
                             WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN 'multi' 
                             ELSE 'single'
                         END as assignment_type,
                         admin.full_name as assigned_by_name
                  FROM assignments a 
                  LEFT JOIN documents d ON a.document_id = d.id
                  LEFT JOIN document_groups dg ON a.group_id = dg.id
                  LEFT JOIN users admin ON a.assigned_by = admin.id
                  WHERE a.id = ? AND a.user_id = ?";
                  
        $stmt = $db->prepare($query);
        $stmt->execute([$assignment_id, $current_user_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assignment) {
            $error_message = 'Không tìm thấy công việc hoặc bạn không có quyền truy cập.';
        } else {
            // Get related documents
            $assignment['type'] = $assignment['assignment_type']; // For backward compatibility
            
            if ($assignment['assignment_type'] == 'single' && $assignment['document_id']) {
                $query = "SELECT * FROM documents WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment['document_id']]);
                $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($doc) {
                    $documents = [$doc];
                }
            } elseif ($assignment['assignment_type'] == 'multi' && $assignment['group_id']) {
                // Get documents in group via document_group_items table
                $query = "SELECT d.* FROM documents d 
                          JOIN document_group_items dgi ON d.id = dgi.document_id 
                          WHERE dgi.group_id = ? 
                          ORDER BY dgi.sort_order";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment['group_id']]);
                $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Get existing labeling results if table exists
            $results_map = [];
            try {
                $query = "SELECT * FROM labeling_results WHERE assignment_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment_id]);
                $existing_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Create results map by document_id
                foreach ($existing_results as $result) {
                    $results_map[$result['document_id']] = $result;
                }
            } catch (Exception $e) {
                // Table doesn't exist, create it
                try {
                    $create_table = "CREATE TABLE IF NOT EXISTS labeling_results (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        assignment_id int(11) NOT NULL,
                        document_id int(11) NOT NULL,
                        selected_sentences longtext,
                        writing_style varchar(50) DEFAULT NULL,
                        edited_summary longtext,
                        step1_completed tinyint(1) DEFAULT 0,
                        step2_completed tinyint(1) DEFAULT 0,
                        step3_completed tinyint(1) DEFAULT 0,
                        auto_saved_at timestamp NULL DEFAULT NULL,
                        completed_at timestamp NULL DEFAULT NULL,
                        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY unique_assignment_document (assignment_id, document_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $db->exec($create_table);
                    $results_map = [];
                } catch (Exception $e2) {
                    // If we can't create table, use empty results
                    $results_map = [];
                }
            }
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi khi lấy thông tin assignment: ' . $e->getMessage();
    }
}

// Handle save labeling
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$view_only) {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'save_labeling') {
            $document_id = intval($_POST['document_id']);
            $selected_sentences = $_POST['selected_sentences'] ?? '[]';
            $writing_style = $_POST['writing_style'] ?? '';
            $edited_summary = $_POST['edited_summary'] ?? '';
            $step1_completed = isset($_POST['step1_completed']) ? 1 : 0;
            $step2_completed = isset($_POST['step2_completed']) ? 1 : 0;
            $step3_completed = isset($_POST['step3_completed']) ? 1 : 0;
            
            try {
                // Check if result exists
                $query = "SELECT id FROM labeling_results WHERE assignment_id = ? AND document_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment_id, $document_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update
                    $query = "UPDATE labeling_results SET 
                              selected_sentences = ?, writing_style = ?, edited_summary = ?,
                              step1_completed = ?, step2_completed = ?, step3_completed = ?,
                              auto_saved_at = CURRENT_TIMESTAMP,
                              completed_at = CASE WHEN ? = 1 AND ? = 1 AND ? = 1 THEN CURRENT_TIMESTAMP ELSE completed_at END
                              WHERE assignment_id = ? AND document_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $selected_sentences, $writing_style, $edited_summary,
                        $step1_completed, $step2_completed, $step3_completed,
                        $step1_completed, $step2_completed, $step3_completed,
                        $assignment_id, $document_id
                    ]);
                } else {
                    // Insert
                    $query = "INSERT INTO labeling_results 
                              (assignment_id, document_id, selected_sentences, writing_style, edited_summary,
                               step1_completed, step2_completed, step3_completed, auto_saved_at,
                               completed_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 
                                      CASE WHEN ? = 1 AND ? = 1 AND ? = 1 THEN CURRENT_TIMESTAMP ELSE NULL END)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $assignment_id, $document_id, $selected_sentences, $writing_style, $edited_summary,
                        $step1_completed, $step2_completed, $step3_completed,
                        $step1_completed, $step2_completed, $step3_completed
                    ]);
                }
            } catch (Exception $e) {
                // If table doesn't exist, create it and try again
                if (strpos($e->getMessage(), "doesn't exist") !== false) {
                    $create_table = "CREATE TABLE IF NOT EXISTS labeling_results (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        assignment_id int(11) NOT NULL,
                        document_id int(11) NOT NULL,
                        selected_sentences longtext,
                        writing_style varchar(50) DEFAULT NULL,
                        edited_summary longtext,
                        step1_completed tinyint(1) DEFAULT 0,
                        step2_completed tinyint(1) DEFAULT 0,
                        step3_completed tinyint(1) DEFAULT 0,
                        auto_saved_at timestamp NULL DEFAULT NULL,
                        completed_at timestamp NULL DEFAULT NULL,
                        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY unique_assignment_document (assignment_id, document_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $db->exec($create_table);
                    
                    // Try insert again
                    $query = "INSERT INTO labeling_results 
                              (assignment_id, document_id, selected_sentences, writing_style, edited_summary,
                               step1_completed, step2_completed, step3_completed, auto_saved_at,
                               completed_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 
                                      CASE WHEN ? = 1 AND ? = 1 AND ? = 1 THEN CURRENT_TIMESTAMP ELSE NULL END)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $assignment_id, $document_id, $selected_sentences, $writing_style, $edited_summary,
                        $step1_completed, $step2_completed, $step3_completed,
                        $step1_completed, $step2_completed, $step3_completed
                    ]);
                } else {
                    throw $e;
                }
            }
            
            // Update assignment status if all steps completed
            if ($step1_completed && $step2_completed && $step3_completed) {
                $query = "UPDATE assignments SET status = 'completed' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment_id]);
            } else {
                $query = "UPDATE assignments SET status = 'in_progress' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment_id]);
            }
            
            $success_message = 'Lưu kết quả gán nhãn thành công!';
            
            // Reload data
            header("Location: labeling.php?id=$assignment_id&saved=1");
            exit();
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi khi lưu: ' . $e->getMessage();
    }
}

if (isset($_GET['saved'])) {
    $success_message = 'Đã lưu thành công!';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gán nhãn - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
        }
        .sidebar {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            padding: 20px 0;
            z-index: 1000;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        .labeling-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .step-header {
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .step-content {
            padding: 30px;
        }
        .sentence {
            padding: 10px;
            margin: 5px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .sentence:hover {
            background: #f8f9fa;
            border-color: #dee2e6;
        }
        .sentence.selected {
            background: #e3f2fd;
            border-color: #2196f3;
            color: #1976d2;
        }
        .document-tab {
            background: white;
            border: 1px solid #dee2e6;
            border-bottom: none;
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .document-tab.active {
            background: #007bff;
            color: white;
        }
        .progress-indicator {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        .progress-step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            color: #6c757d;
            font-weight: bold;
        }
        .progress-step.completed {
            background: #28a745;
            color: white;
        }
        .progress-step.active {
            background: #007bff;
            color: white;
        }
        .writing-style-option {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px;
        }
        .writing-style-option:hover {
            border-color: #007bff;
            background: #f8f9ff;
        }
        .writing-style-option.selected {
            border-color: #007bff;
            background: #e3f2fd;
            color: #1976d2;
        }
        .auto-save-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 15px;
            background: #28a745;
            color: white;
            border-radius: 20px;
            display: none;
            z-index: 1100;
        }
    </style>
</head>
<body>
    <!-- Auto-save indicator -->
    <div id="autoSaveIndicator" class="auto-save-indicator">
        <i class="fas fa-check me-2"></i>Đã lưu tự động
    </div>

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center text-white mb-4">
            <i class="fas fa-user-edit fa-2x mb-2"></i>
            <h5>Labeler Panel</h5>
            <small>Xin chào, <?php echo htmlspecialchars($current_user['full_name']); ?></small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my_tasks.php">
                    <i class="fas fa-tasks me-2"></i>Công việc của tôi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="labeling.php">
                    <i class="fas fa-edit me-2"></i>Gán nhãn
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="history.php">
                    <i class="fas fa-history me-2"></i>Lịch sử
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <div class="mt-2">
                    <a href="my_tasks.php" class="btn btn-outline-primary">← Quay lại danh sách công việc</a>
                </div>
            </div>
        <?php elseif ($assignment): ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-dark">Gán nhãn: <?php echo htmlspecialchars($assignment['title']); ?></h2>
                    <small class="text-muted">
                        Assignment #<?php echo $assignment['id']; ?> • 
                        <?php echo $assignment['type'] == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                        <?php if ($view_only): ?>
                            • <span class="badge bg-secondary">Chế độ xem</span>
                        <?php endif; ?>
                    </small>
                </div>
                <div>
                    <a href="my_tasks.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-2"></i>Quay lại
                    </a>
                    <?php if (!$view_only): ?>
                        <button type="button" class="btn btn-success" onclick="saveAll()">
                            <i class="fas fa-save me-2"></i>Lưu tất cả
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Document Tabs (for multi-document) -->
            <?php if (count($documents) > 1): ?>
                <div class="d-flex mb-3">
                    <?php foreach ($documents as $index => $doc): ?>
                        <div class="document-tab <?php echo $index == 0 ? 'active' : ''; ?>" 
                             onclick="switchDocument(<?php echo $index; ?>)" 
                             id="tab-<?php echo $index; ?>">
                            <i class="fas fa-file-text me-2"></i>
                            <?php echo htmlspecialchars(substr($doc['title'], 0, 20)); ?>
                            <?php if (strlen($doc['title']) > 20): ?>...<?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Labeling Interface -->
            <?php foreach ($documents as $doc_index => $document): ?>
                <div class="document-container <?php echo $doc_index == 0 ? '' : 'd-none'; ?>" id="document-<?php echo $doc_index; ?>">
                    <div class="labeling-container">
                        
                        <!-- Progress Indicator -->
                        <div class="progress-indicator">
                            <?php 
                            $result = $results_map[$document['id']] ?? null;
                            $step1 = $result['step1_completed'] ?? false;
                            $step2 = $result['step2_completed'] ?? false;
                            $step3 = $result['step3_completed'] ?? false;
                            ?>
                            <div class="progress-step <?php echo $step1 ? 'completed' : 'active'; ?>">1</div>
                            <div class="progress-step <?php echo $step2 ? 'completed' : ($step1 ? 'active' : ''); ?>">2</div>
                            <div class="progress-step <?php echo $step3 ? 'completed' : ($step2 ? 'active' : ''); ?>">3</div>
                        </div>

                        <!-- Step 1: Select Important Sentences -->
                        <div class="step-section" id="step1-<?php echo $doc_index; ?>">
                            <div class="step-header">
                                <h4><i class="fas fa-mouse-pointer me-2"></i>Bước 1: Chọn câu quan trọng</h4>
                                <p class="mb-0">Click vào các câu quan trọng trong văn bản</p>
                            </div>
                            <div class="step-content">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6>Nội dung văn bản:</h6>
                                        <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
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
                                    </div>
                                    <div class="col-md-4">
                                        <h6>Câu đã chọn:</h6>
                                        <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                                            <div id="selected-sentences-<?php echo $doc_index; ?>" class="text-muted">
                                                Chưa có câu nào được chọn
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-danger btn-sm mt-2" onclick="clearSelections(<?php echo $doc_index; ?>)">
                                            <i class="fas fa-eraser me-1"></i>Xóa tất cả
                                        </button>
                                    </div>
                                </div>
                                <div class="text-end mt-3">
                                    <button type="button" class="btn btn-primary" onclick="completeStep(1, <?php echo $doc_index; ?>)">
                                        Hoàn thành bước 1 <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Choose Writing Style -->
                        <div class="step-section d-none" id="step2-<?php echo $doc_index; ?>">
                            <div class="step-header">
                                <h4><i class="fas fa-palette me-2"></i>Bước 2: Chọn phong cách văn bản</h4>
                                <p class="mb-0">Chọn phong cách phù hợp cho bản tóm tắt</p>
                            </div>
                            <div class="step-content">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="writing-style-option" data-style="formal" onclick="selectStyle('formal', <?php echo $doc_index; ?>)">
                                            <i class="fas fa-university fa-2x mb-2"></i>
                                            <h6>Trang trọng</h6>
                                            <small>Phù hợp cho báo cáo, tài liệu chính thức</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="writing-style-option" data-style="casual" onclick="selectStyle('casual', <?php echo $doc_index; ?>)">
                                            <i class="fas fa-comments fa-2x mb-2"></i>
                                            <h6>Thân thiện</h6>
                                            <small>Phù hợp cho blog, bài viết cá nhân</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="writing-style-option" data-style="technical" onclick="selectStyle('technical', <?php echo $doc_index; ?>)">
                                            <i class="fas fa-cogs fa-2x mb-2"></i>
                                            <h6>Kỹ thuật</h6>
                                            <small>Phù hợp cho tài liệu chuyên môn</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="writing-style-option" data-style="news" onclick="selectStyle('news', <?php echo $doc_index; ?>)">
                                            <i class="fas fa-newspaper fa-2x mb-2"></i>
                                            <h6>Tin tức</h6>
                                            <small>Phù hợp cho báo chí, thông tin</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end mt-3">
                                    <button type="button" class="btn btn-outline-secondary me-2" onclick="showStep(1, <?php echo $doc_index; ?>)">
                                        <i class="fas fa-arrow-left me-2"></i>Quay lại
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="completeStep(2, <?php echo $doc_index; ?>)" disabled id="step2-complete-<?php echo $doc_index; ?>">
                                        Hoàn thành bước 2 <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Edit Summary -->
                        <div class="step-section d-none" id="step3-<?php echo $doc_index; ?>">
                            <div class="step-header">
                                <h4><i class="fas fa-edit me-2"></i>Bước 3: Chỉnh sửa bản tóm tắt</h4>
                                <p class="mb-0">Chỉnh sửa bản tóm tắt AI dựa trên các câu đã chọn</p>
                            </div>
                            <div class="step-content">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Bản tóm tắt AI gốc:</h6>
                                        <div class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto;">
                                            <?php echo htmlspecialchars($document['ai_summary'] ?? 'Không có tóm tắt AI'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Bản tóm tắt đã chỉnh sửa:</h6>
                                        <textarea class="form-control" id="edited-summary-<?php echo $doc_index; ?>" rows="12" 
                                                  placeholder="Chỉnh sửa bản tóm tắt dựa trên các câu quan trọng đã chọn..."
                                                  <?php echo $view_only ? 'readonly' : ''; ?>><?php echo htmlspecialchars($result['edited_summary'] ?? $document['ai_summary'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="text-end mt-3">
                                    <button type="button" class="btn btn-outline-secondary me-2" onclick="showStep(2, <?php echo $doc_index; ?>)">
                                        <i class="fas fa-arrow-left me-2"></i>Quay lại
                                    </button>
                                    <?php if (!$view_only): ?>
                                        <button type="button" class="btn btn-success" onclick="completeStep(3, <?php echo $doc_index; ?>)">
                                            <i class="fas fa-check me-2"></i>Hoàn thành gán nhãn
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <!-- Hidden forms for saving -->
    <?php foreach ($documents as $doc_index => $document): ?>
        <form id="save-form-<?php echo $doc_index; ?>" method="POST" style="display: none;">
            <input type="hidden" name="action" value="save_labeling">
            <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
            <input type="hidden" name="selected_sentences" id="form-selected-sentences-<?php echo $doc_index; ?>">
            <input type="hidden" name="writing_style" id="form-writing-style-<?php echo $doc_index; ?>">
            <input type="hidden" name="edited_summary" id="form-edited-summary-<?php echo $doc_index; ?>">
            <input type="hidden" name="step1_completed" id="form-step1-<?php echo $doc_index; ?>">
            <input type="hidden" name="step2_completed" id="form-step2-<?php echo $doc_index; ?>">
            <input type="hidden" name="step3_completed" id="form-step3-<?php echo $doc_index; ?>">
        </form>
    <?php endforeach; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentDocument = 0;
        let selectedSentences = {};
        let writingStyles = {};
        let completedSteps = {};

        // Initialize data from PHP
        <?php foreach ($documents as $doc_index => $document): ?>
            selectedSentences[<?php echo $doc_index; ?>] = <?php 
                $result = $results_map[$document['id']] ?? null;
                echo $result['selected_sentences'] ?? '[]'; 
            ?>;
            writingStyles[<?php echo $doc_index; ?>] = '<?php echo $result['writing_style'] ?? ''; ?>';
            completedSteps[<?php echo $doc_index; ?>] = {
                step1: <?php echo $result['step1_completed'] ? 'true' : 'false'; ?>,
                step2: <?php echo $result['step2_completed'] ? 'true' : 'false'; ?>,
                step3: <?php echo $result['step3_completed'] ? 'true' : 'false'; ?>
            };
        <?php endforeach; ?>

        // Initialize interface
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($documents as $doc_index => $document): ?>
                loadSavedData(<?php echo $doc_index; ?>);
            <?php endforeach; ?>
            
            // Show appropriate step
            <?php foreach ($documents as $doc_index => $document): ?>
                if (completedSteps[<?php echo $doc_index; ?>].step3) {
                    showStep(3, <?php echo $doc_index; ?>);
                } else if (completedSteps[<?php echo $doc_index; ?>].step2) {
                    showStep(3, <?php echo $doc_index; ?>);
                } else if (completedSteps[<?php echo $doc_index; ?>].step1) {
                    showStep(2, <?php echo $doc_index; ?>);
                } else {
                    showStep(1, <?php echo $doc_index; ?>);
                }
            <?php endforeach; ?>
        });

        function switchDocument(docIndex) {
            // Hide all documents
            document.querySelectorAll('.document-container').forEach(el => el.classList.add('d-none'));
            document.querySelectorAll('.document-tab').forEach(el => el.classList.remove('active'));
            
            // Show selected document
            document.getElementById('document-' + docIndex).classList.remove('d-none');
            document.getElementById('tab-' + docIndex).classList.add('active');
            
            currentDocument = docIndex;
        }

        function toggleSentence(element) {
            const docIndex = parseInt(element.dataset.doc);
            const sentenceIndex = parseInt(element.dataset.sentence);
            
            if (!selectedSentences[docIndex]) {
                selectedSentences[docIndex] = [];
            }
            
            if (element.classList.contains('selected')) {
                element.classList.remove('selected');
                selectedSentences[docIndex] = selectedSentences[docIndex].filter(i => i !== sentenceIndex);
            } else {
                element.classList.add('selected');
                selectedSentences[docIndex].push(sentenceIndex);
            }
            
            updateSelectedDisplay(docIndex);
            autoSave(docIndex);
        }

        function updateSelectedDisplay(docIndex) {
            const container = document.getElementById('selected-sentences-' + docIndex);
            const sentences = document.querySelectorAll(`#sentences-${docIndex} .sentence`);
            
            if (selectedSentences[docIndex] && selectedSentences[docIndex].length > 0) {
                let html = '';
                selectedSentences[docIndex].forEach(index => {
                    if (sentences[index]) {
                        html += '<div class="mb-2 p-2 bg-light rounded">' + sentences[index].textContent + '</div>';
                    }
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-muted">Chưa có câu nào được chọn</div>';
            }
        }

        function clearSelections(docIndex) {
            selectedSentences[docIndex] = [];
            document.querySelectorAll(`#sentences-${docIndex} .sentence`).forEach(el => {
                el.classList.remove('selected');
            });
            updateSelectedDisplay(docIndex);
            autoSave(docIndex);
        }

        function selectStyle(style, docIndex) {
            writingStyles[docIndex] = style;
            
            // Update UI
            document.querySelectorAll(`#step2-${docIndex} .writing-style-option`).forEach(el => {
                el.classList.remove('selected');
            });
            document.querySelector(`#step2-${docIndex} .writing-style-option[data-style="${style}"]`).classList.add('selected');
            
            // Enable next button
            document.getElementById('step2-complete-' + docIndex).disabled = false;
            
            autoSave(docIndex);
        }

        function showStep(step, docIndex) {
            // Hide all steps
            for (let i = 1; i <= 3; i++) {
                document.getElementById(`step${i}-${docIndex}`).classList.add('d-none');
            }
            
            // Show selected step
            document.getElementById(`step${step}-${docIndex}`).classList.remove('d-none');
        }

        function completeStep(step, docIndex) {
            if (step === 1) {
                if (!selectedSentences[docIndex] || selectedSentences[docIndex].length === 0) {
                    alert('Vui lòng chọn ít nhất một câu quan trọng!');
                    return;
                }
                completedSteps[docIndex].step1 = true;
                showStep(2, docIndex);
            } else if (step === 2) {
                if (!writingStyles[docIndex]) {
                    alert('Vui lòng chọn phong cách văn bản!');
                    return;
                }
                completedSteps[docIndex].step2 = true;
                showStep(3, docIndex);
            } else if (step === 3) {
                const summary = document.getElementById('edited-summary-' + docIndex).value.trim();
                if (!summary) {
                    alert('Vui lòng nhập bản tóm tắt!');
                    return;
                }
                completedSteps[docIndex].step3 = true;
                saveDocument(docIndex);
            }
            
            autoSave(docIndex);
        }

        function autoSave(docIndex) {
            // Update form data
            updateFormData(docIndex);
            
            // Show auto-save indicator
            const indicator = document.getElementById('autoSaveIndicator');
            indicator.style.display = 'block';
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 2000);
        }

        function updateFormData(docIndex) {
            document.getElementById('form-selected-sentences-' + docIndex).value = JSON.stringify(selectedSentences[docIndex] || []);
            document.getElementById('form-writing-style-' + docIndex).value = writingStyles[docIndex] || '';
            document.getElementById('form-edited-summary-' + docIndex).value = document.getElementById('edited-summary-' + docIndex).value;
            document.getElementById('form-step1-' + docIndex).value = completedSteps[docIndex].step1 ? '1' : '';
            document.getElementById('form-step2-' + docIndex).value = completedSteps[docIndex].step2 ? '1' : '';
            document.getElementById('form-step3-' + docIndex).value = completedSteps[docIndex].step3 ? '1' : '';
        }

        function saveDocument(docIndex) {
            updateFormData(docIndex);
            document.getElementById('save-form-' + docIndex).submit();
        }

        function saveAll() {
            updateFormData(currentDocument);
            document.getElementById('save-form-' + currentDocument).submit();
        }

        function loadSavedData(docIndex) {
            // Load selected sentences
            if (selectedSentences[docIndex] && selectedSentences[docIndex].length > 0) {
                const sentences = document.querySelectorAll(`#sentences-${docIndex} .sentence`);
                selectedSentences[docIndex].forEach(index => {
                    if (sentences[index]) {
                        sentences[index].classList.add('selected');
                    }
                });
                updateSelectedDisplay(docIndex);
            }
            
            // Load writing style
            if (writingStyles[docIndex]) {
                const styleElement = document.querySelector(`#step2-${docIndex} .writing-style-option[data-style="${writingStyles[docIndex]}"]`);
                if (styleElement) {
                    styleElement.classList.add('selected');
                    document.getElementById('step2-complete-' + docIndex).disabled = false;
                }
            }
        }
    </script>
</body>
</html>