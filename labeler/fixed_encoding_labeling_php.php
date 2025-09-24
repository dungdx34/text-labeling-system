<?php
// Start session and error handling
header('Content-Type: text/html; charset=UTF-8');
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

// Create necessary tables if they don't exist
try {
    $create_results_table = "CREATE TABLE IF NOT EXISTS labeling_results (
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
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_assignment_document (assignment_id, document_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($create_results_table);
} catch (Exception $e) {
    // Table might already exist
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

// Check what columns exist in related tables
$document_group_columns = [];
try {
    $table_check = $db->query("SHOW TABLES LIKE 'document_groups'");
    if ($table_check->rowCount() > 0) {
        $columns_result = $db->query("SHOW COLUMNS FROM document_groups");
        while ($col = $columns_result->fetch(PDO::FETCH_ASSOC)) {
            $document_group_columns[] = $col['Field'];
        }
    }
} catch (Exception $e) {
    $document_group_columns = ['id', 'group_name', 'description'];
}

// Get assignment info
if ($assignment_id) {
    try {
        // Build query based on available columns
        $group_name_field = in_array('group_name', $document_group_columns) ? 'dg.group_name' : 'CONCAT("Group #", a.group_id)';
        $group_desc_field = in_array('description', $document_group_columns) ? 'dg.description' : '"No description"';
        
        $query = "SELECT a.*, 
                         CASE 
                             WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN d.title 
                             WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN $group_name_field 
                             ELSE 'Untitled'
                         END as title,
                         CASE 
                             WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN d.ai_summary 
                             WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN $group_desc_field 
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
                // Check if document_group_items table exists
                $table_check = $db->query("SHOW TABLES LIKE 'document_group_items'");
                if ($table_check->rowCount() > 0) {
                    $query = "SELECT d.* FROM documents d 
                              JOIN document_group_items dgi ON d.id = dgi.document_id 
                              WHERE dgi.group_id = ? 
                              ORDER BY dgi.sort_order";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$assignment['group_id']]);
                    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            
            // Get existing labeling results
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
                $results_map = [];
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
                          completed_at = CASE WHEN ? = 1 AND ? = 1 AND ? = 1 THEN CURRENT_TIMESTAMP ELSE completed_at END,
                          updated_at = CURRENT_TIMESTAMP
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
            
            // Update assignment status
            if ($step1_completed && $step2_completed && $step3_completed) {
                $query = "UPDATE assignments SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment_id]);
            } else {
                $query = "UPDATE assignments SET status = 'in_progress', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
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
            margin-bottom: 20px;
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
            display: inline-block;
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
            margin: 10px 0;
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
        .step-section {
            display: none;
        }
        .step-section.active {
            display: block;
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
        <?php elseif ($assignment && !empty($documents)): ?>
            
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
                <div class="d-flex mb-3 flex-wrap">
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
                            <div class="progress-step <?php echo $step1 ? 'completed' : 'active'; ?>" id="progress-step-1-<?php echo $doc_index; ?>">1</div>
                            <div class="progress-step <?php echo $step2 ? 'completed' : ($step1 ? 'active' : ''); ?>" id="progress-step-2-<?php echo $doc_index; ?>">2</div>
                            <div class="progress-step <?php echo $step3 ? 'completed' : ($step2 ? 'active' : ''); ?>" id="progress-step-3-<?php echo $doc_index; ?>">3</div>
                        </div>

                        <!-- Step 1: Select Important Sentences -->
                        <div class="step-section <?php echo !$step1 || (!$step2 && !$step3) ? 'active' : ''; ?>" id="step1-<?php echo $doc_index; ?>">
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
                        <div class="step-section <?php echo $step1 && !$step2 ? 'active' : ''; ?>" id="step2-<?php echo $doc_index; ?>">
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
                        <div class="step-section <?php echo $step2 ? 'active' : ''; ?>" id="step3-<?php echo $doc_index; ?>">
                            <div class="step-header">
                                <h4><i class="fas fa-edit me-2"></i>Bước 3: Chỉnh sửa bản tóm tắt</h4>
                                <p class="mb-0">Chỉnh sửa bản tóm tắt AI dựa trên các câu đã chọn</p>
                            </div>
                            <div class="step-content">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Bản tóm tắt AI gốc:</h6>
                                        <div class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto;">
                                            <?php echo nl2br(htmlspecialchars($document['ai_summary'] ?? 'Không