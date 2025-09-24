<?php
// Set proper UTF-8 headers and encoding FIRST - before any output
header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session first
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection with UTF-8 encoding
require_once '../config/database.php';

// Simple auth check - no external functions needed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'labeler') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Ensure UTF-8 connection
$db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$db->exec("SET CHARACTER SET utf8mb4");

$current_user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';
$assignment = null;
$documents = [];
$view_only = isset($_GET['view']) && $_GET['view'] == '1';

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
    $check_table = $db->query("SHOW TABLES LIKE 'labeling_results'");
    if ($check_table->rowCount() == 0) {
        $create_results_table = "CREATE TABLE IF NOT EXISTS labeling_results (
            id int(11) NOT NULL AUTO_INCREMENT,
            assignment_id int(11) NOT NULL,
            document_id int(11) NOT NULL,
            selected_sentences longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
            writing_style varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            edited_summary longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
            step1_completed tinyint(1) DEFAULT 0,
            step2_completed tinyint(1) DEFAULT 0,
            step3_completed tinyint(1) DEFAULT 0,
            auto_saved_at timestamp NULL DEFAULT NULL,
            completed_at timestamp NULL DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_assignment_document (assignment_id, document_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $db->exec($create_results_table);
    }
} catch (Exception $e) {
    // Continue if table creation fails
}

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
        // Simple query first to test
        $query = "SELECT a.* FROM assignments a WHERE a.id = ? AND a.user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$assignment_id, $current_user_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assignment) {
            $error_message = 'Không tìm thấy công việc hoặc bạn không có quyền truy cập.';
        } else {
            // Determine assignment type based on available fields
            if (isset($assignment['document_id']) && $assignment['document_id'] > 0) {
                $assignment['type'] = 'single';
                $assignment['assignment_type'] = 'single';
                
                // Get single document
                $query = "SELECT * FROM documents WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment['document_id']]);
                $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($doc) {
                    $documents = [$doc];
                    $assignment['title'] = $doc['title'];
                }
            } elseif (isset($assignment['group_id']) && $assignment['group_id'] > 0) {
                $assignment['type'] = 'multi';
                $assignment['assignment_type'] = 'multi';
                
                // Get group info if table exists
                try {
                    $query = "SELECT * FROM document_groups WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$assignment['group_id']]);
                    $group = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($group) {
                        $assignment['title'] = $group['group_name'];
                    }
                } catch (Exception $e) {
                    $assignment['title'] = 'Multi-Document Group';
                }
                
                // Get documents in group
                try {
                    $query = "SELECT d.* FROM documents d 
                              JOIN document_group_items dgi ON d.id = dgi.document_id 
                              WHERE dgi.group_id = ? 
                              ORDER BY dgi.sort_order";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$assignment['group_id']]);
                    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $documents = [];
                }
            }
            
            // Default values if not set
            $assignment['title'] = $assignment['title'] ?? 'Chưa có tiêu đề';
            $assignment['type'] = $assignment['type'] ?? 'single';
            
            // Get existing labeling results
            $results_map = [];
            try {
                $query = "SELECT * FROM labeling_results WHERE assignment_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment_id]);
                $existing_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
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
            
            // Check if updated_at column exists in labeling_results table
            $has_updated_at = false;
            try {
                $columns_check = $db->query("SHOW COLUMNS FROM labeling_results LIKE 'updated_at'");
                $has_updated_at = $columns_check->rowCount() > 0;
            } catch (Exception $e) {
                $has_updated_at = false;
            }
            
            if ($existing) {
                // Update
                if ($has_updated_at) {
                    $query = "UPDATE labeling_results SET 
                              selected_sentences = ?, writing_style = ?, edited_summary = ?,
                              step1_completed = ?, step2_completed = ?, step3_completed = ?,
                              auto_saved_at = CURRENT_TIMESTAMP,
                              completed_at = CASE WHEN ? = 1 AND ? = 1 AND ? = 1 THEN CURRENT_TIMESTAMP ELSE completed_at END,
                              updated_at = CURRENT_TIMESTAMP
                              WHERE assignment_id = ? AND document_id = ?";
                } else {
                    $query = "UPDATE labeling_results SET 
                              selected_sentences = ?, writing_style = ?, edited_summary = ?,
                              step1_completed = ?, step2_completed = ?, step3_completed = ?,
                              auto_saved_at = CURRENT_TIMESTAMP,
                              completed_at = CASE WHEN ? = 1 AND ? = 1 AND ? = 1 THEN CURRENT_TIMESTAMP ELSE completed_at END
                              WHERE assignment_id = ? AND document_id = ?";
                }
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
            
            // Update assignment status - check if updated_at column exists
            try {
                $columns_check = $db->query("SHOW COLUMNS FROM assignments LIKE 'updated_at'");
                $has_updated_at = $columns_check->rowCount() > 0;
                
                if ($step1_completed && $step2_completed && $step3_completed) {
                    if ($has_updated_at) {
                        $query = "UPDATE assignments SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    } else {
                        $query = "UPDATE assignments SET status = 'completed' WHERE id = ?";
                    }
                } else {
                    if ($has_updated_at) {
                        $query = "UPDATE assignments SET status = 'in_progress', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    } else {
                        $query = "UPDATE assignments SET status = 'in_progress' WHERE id = ?";
                    }
                }
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment_id]);
            } catch (Exception $e) {
                // If update fails, try without updated_at
                if ($step1_completed && $step2_completed && $step3_completed) {
                    $query = "UPDATE assignments SET status = 'completed' WHERE id = ?";
                } else {
                    $query = "UPDATE assignments SET status = 'in_progress' WHERE id = ?";
                }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            padding: 8px 12px;
            margin: 3px 0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            font-size: 14px;
            line-height: 1.5;
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
            margin-right: 5px;
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
            margin: 5px;
            height: 100%;
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
            padding: 8px 15px;
            background: #28a745;
            color: white;
            border-radius: 20px;
            display: none;
            z-index: 1100;
            font-size: 14px;
        }
        .content-area {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background: #fafafa;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <!-- Auto-save indicator -->
    <div id="autoSaveIndicator" class="auto-save-indicator">
        <i class="fas fa-check me-1"></i>Đã lưu
    </div>

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center text-white mb-4">
            <i class="fas fa-user-edit fa-2x mb-2"></i>
            <h5>Labeler Panel</h5>
            <small>Xin chào, <?php echo htmlspecialchars($current_user['full_name'], ENT_QUOTES, 'UTF-8'); ?></small>
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
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                <div class="mt-3">
                    <a href="my_tasks.php" class="btn btn-outline-primary">← Quay lại danh sách công việc</a>
                    <a href="dashboard.php" class="btn btn-outline-secondary">← Dashboard</a>
                </div>
            </div>
        <?php elseif ($assignment && !empty($documents)): ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-dark">Gán nhãn: <?php echo htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <small class="text-muted">
                        Assignment #<?php echo $assignment['id']; ?> • 
                        <?php echo $assignment['type'] == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?> • 
                        <?php echo count($documents); ?> văn bản
                        <?php if ($view_only): ?>
                            • <span class="badge bg-secondary">Chế độ xem</span>
                        <?php endif; ?>
                    </small>
                </div>
                <div>
                    <a href="my_tasks.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i>Quay lại
                    </a>
                    <?php if (!$view_only): ?>
                        <button type="button" class="btn btn-success" onclick="saveCurrentDocument()">
                            <i class="fas fa-save me-1"></i>Lưu
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Document Tabs (for multi-document) -->
            <?php if (count($documents) > 1): ?>
                <div class="mb-3">
                    <?php foreach ($documents as $index => $doc): ?>
                        <div class="document-tab <?php echo $index == 0 ? 'active' : ''; ?>" 
                             onclick="switchDocument(<?php echo $index; ?>)" 
                             id="tab-<?php echo $index; ?>">
                            <i class="fas fa-file-text me-1"></i>
                            <?php 
                            $title = htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8');
                            echo mb_strlen($title, 'UTF-8') > 20 ? mb_substr($title, 0, 20, 'UTF-8') . '...' : $title;
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Labeling Interface -->
            <?php foreach ($documents as $doc_index => $document): ?>
                <?php $result = $results_map[$document['id']] ?? null; ?>
                <div class="document-container <?php echo $doc_index == 0 ? '' : 'd-none'; ?>" id="document-<?php echo $doc_index; ?>">
                    
                    <!-- Progress Indicator -->
                    <div class="progress-indicator">
                        <?php 
                        $step1 = $result['step1_completed'] ?? false;
                        $step2 = $result['step2_completed'] ?? false;
                        $step3 = $result['step3_completed'] ?? false;
                        ?>
                        <div class="progress-step <?php echo $step1 ? 'completed' : 'active'; ?>">1</div>
                        <div class="progress-step <?php echo $step2 ? 'completed' : ($step1 ? 'active' : ''); ?>">2</div>
                        <div class="progress-step <?php echo $step3 ? 'completed' : ($step2 ? 'active' : ''); ?>">3</div>
                    </div>

                    <div class="labeling-container">
                        
                        <!-- Step 1: Select Important Sentences -->
                        <div class="step-section" id="step1-<?php echo $doc_index; ?>">
                            <div class="step-header">
                                <h4><i class="fas fa-mouse-pointer me-2"></i>Bước 1: Chọn câu quan trọng</h4>
                                <p class="mb-0">Click vào các câu quan trọng trong văn bản</p>
                            </div>
                            <div class="step-content">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6 class="mb-3">Nội dung văn bản:</h6>
                                        <div class="content-area">
                                            <div id="sentences-<?php echo $doc_index; ?>">
                                                <?php 
                                                $content = $document['content'] ?? '';
                                                $sentences = preg_split('/(?<=[.!?])\s+/u', $content);
                                                foreach ($sentences as $i => $sentence):
                                                    $sentence = trim($sentence);
                                                    if (!empty($sentence)):
                                                ?>
                                                    <div class="sentence" 
                                                         data-doc="<?php echo $doc_index; ?>" 
                                                         data-sentence="<?php echo $i; ?>" 
                                                         onclick="toggleSentence(this)">
                                                        <?php echo htmlspecialchars($sentence, ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="mb-3">Câu đã chọn:</h6>
                                        <div class="content-area">
                                            <div id="selected-sentences-<?php echo $doc_index; ?>" class="text-muted">
                                                Chưa có câu nào được chọn
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-outline-danger btn-sm me-2" onclick="clearSelections(<?php echo $doc_index; ?>)">
                                                <i class="fas fa-eraser me-1"></i>Xóa tất cả
                                            </button>
                                            <span class="badge bg-info" id="selected-count-<?php echo $doc_index; ?>">0 câu đã chọn</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="button" class="btn btn-primary" onclick="completeStep(1, <?php echo $doc_index; ?>)">
                                        Hoàn thành bước 1 <i class="fas fa-arrow-right ms-1"></i>
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
                                            <i class="fas fa-university fa-2x mb-2 d-block"></i>
                                            <h6>Trang trọng</h6>
                                            <small>Báo cáo, tài liệu chính thức</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="writing-style-option" data-style="casual" onclick="selectStyle('casual', <?php echo $doc_index; ?>)">
                                            <i class="fas fa-comments fa-2x mb-2 d-block"></i>
                                            <h6>Thân thiện</h6>
                                            <small>Blog, bài viết cá nhân</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="writing-style-option" data-style="technical" onclick="selectStyle('technical', <?php echo $doc_index; ?>)">
                                            <i class="fas fa-cogs fa-2x mb-2 d-block"></i>
                                            <h6>Kỹ thuật</h6>
                                            <small>Tài liệu chuyên môn</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="writing-style-option" data-style="news" onclick="selectStyle('news', <?php echo $doc_index; ?>)">
                                            <i class="fas fa-newspaper fa-2x mb-2 d-block"></i>
                                            <h6>Tin tức</h6>
                                            <small>Báo chí, thông tin</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary" onclick="showStep(1, <?php echo $doc_index; ?>)">
                                        <i class="fas fa-arrow-left me-1"></i>Quay lại
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="completeStep(2, <?php echo $doc_index; ?>)" disabled id="step2-complete-<?php echo $doc_index; ?>">
                                        Hoàn thành bước 2 <i class="fas fa-arrow-right ms-1"></i>
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
                                        <h6 class="mb-3">Bản tóm tắt AI gốc:</h6>
                                        <div class="content-area">
                                            <?php echo nl2br(htmlspecialchars($document['ai_summary'] ?? 'Không có tóm tắt AI', ENT_QUOTES, 'UTF-8')); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-3">Bản tóm tắt đã chỉnh sửa:</h6>
                                        <textarea class="form-control" 
                                                  id="edited-summary-<?php echo $doc_index; ?>" 
                                                  rows="15" 
                                                  placeholder="Chỉnh sửa bản tóm tắt dựa trên các câu quan trọng đã chọn..."
                                                  <?php echo $view_only ? 'readonly' : ''; ?>
                                                  style="resize: vertical;"><?php echo htmlspecialchars($result['edited_summary'] ?? $document['ai_summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary" onclick="showStep(2, <?php echo $doc_index; ?>)">
                                        <i class="fas fa-arrow-left me-1"></i>Quay lại
                                    </button>
                                    <?php if (!$view_only): ?>
                                        <button type="button" class="btn btn-success" onclick="completeStep(3, <?php echo $doc_index; ?>)">
                                            <i class="fas fa-check me-1"></i>Hoàn thành gán nhãn
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Không có công việc nào để gán nhãn.
                <div class="mt-3">
                    <a href="my_tasks.php" class="btn btn-primary">Xem danh sách công việc</a>
                    <a href="dashboard.php" class="btn btn-outline-primary">Dashboard</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Hidden forms for saving -->
    <?php if (!empty($documents)): ?>
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
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentDocument = 0;
        let selectedSentences = {};
        let writingStyles = {};
        let completedSteps = {};

        // Initialize data from PHP
        function initializeData() {
            <?php if (!empty($documents)): ?>
                <?php foreach ($documents as $doc_index => $document): ?>
                    selectedSentences[<?php echo $doc_index; ?>] = <?php 
                        $result = $results_map[$document['id']] ?? null;
                        echo $result['selected_sentences'] ?? '[]'; 
                    ?>;
                    writingStyles[<?php echo $doc_index; ?>] = '<?php echo $result['writing_style'] ?? ''; ?>';
                    completedSteps[<?php echo $doc_index; ?>] = {
                        step1: <?php echo ($result['step1_completed'] ?? false) ? 'true' : 'false'; ?>,
                        step2: <?php echo ($result['step2_completed'] ?? false) ? 'true' : 'false'; ?>,
                        step3: <?php echo ($result['step3_completed'] ?? false) ? 'true' : 'false'; ?>
                    };
                <?php endforeach; ?>
            <?php endif; ?>
        }

        // Initialize interface
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing...');
            initializeData();
            
            <?php if (!empty($documents)): ?>
                <?php foreach ($documents as $doc_index => $document): ?>
                    loadSavedData(<?php echo $doc_index; ?>);
                    
                    // Show appropriate step
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
            <?php endif; ?>
        });

        function switchDocument(docIndex) {
            // Hide all documents
            document.querySelectorAll('.document-container').forEach(el => el.classList.add('d-none'));
            document.querySelectorAll('.document-tab').forEach(el => el.classList.remove('active'));
            
            // Show selected document
            const targetDoc = document.getElementById('document-' + docIndex);
            const targetTab = document.getElementById('tab-' + docIndex);
            
            if (targetDoc) targetDoc.classList.remove('d-none');
            if (targetTab) targetTab.classList.add('active');
            
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
            updateSelectedCount(docIndex);
            showAutoSaveIndicator();
        }

        function updateSelectedDisplay(docIndex) {
            const container = document.getElementById('selected-sentences-' + docIndex);
            const sentences = document.querySelectorAll(`#sentences-${docIndex} .sentence`);
            
            if (!container) return;
            
            if (selectedSentences[docIndex] && selectedSentences[docIndex].length > 0) {
                let html = '';
                selectedSentences[docIndex].forEach(index => {
                    if (sentences[index]) {
                        html += '<div class="mb-2 p-2 bg-light rounded small">' + sentences[index].textContent.trim() + '</div>';
                    }
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-muted">Chưa có câu nào được chọn</div>';
            }
        }

        function updateSelectedCount(docIndex) {
            const countElement = document.getElementById('selected-count-' + docIndex);
            if (countElement) {
                const count = selectedSentences[docIndex] ? selectedSentences[docIndex].length : 0;
                countElement.textContent = count + ' câu đã chọn';
            }
        }

        function clearSelections(docIndex) {
            selectedSentences[docIndex] = [];
            document.querySelectorAll(`#sentences-${docIndex} .sentence`).forEach(el => {
                el.classList.remove('selected');
            });
            updateSelectedDisplay(docIndex);
            updateSelectedCount(docIndex);
            showAutoSaveIndicator();
        }

        function selectStyle(style, docIndex) {
            writingStyles[docIndex] = style;
            
            // Update UI
            document.querySelectorAll(`#step2-${docIndex} .writing-style-option`).forEach(el => {
                el.classList.remove('selected');
            });
            
            const selectedOption = document.querySelector(`#step2-${docIndex} .writing-style-option[data-style="${style}"]`);
            if (selectedOption) {
                selectedOption.classList.add('selected');
            }
            
            // Enable next button
            const nextButton = document.getElementById('step2-complete-' + docIndex);
            if (nextButton) {
                nextButton.disabled = false;
            }
            
            showAutoSaveIndicator();
        }

        function showStep(step, docIndex) {
            // Hide all steps
            for (let i = 1; i <= 3; i++) {
                const stepElement = document.getElementById(`step${i}-${docIndex}`);
                if (stepElement) {
                    stepElement.classList.add('d-none');
                }
            }
            
            // Show selected step
            const targetStep = document.getElementById(`step${step}-${docIndex}`);
            if (targetStep) {
                targetStep.classList.remove('d-none');
            }
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
                const summaryElement = document.getElementById('edited-summary-' + docIndex);
                const summary = summaryElement ? summaryElement.value.trim() : '';
                
                if (!summary) {
                    alert('Vui lòng nhập bản tóm tắt!');
                    return;
                }
                completedSteps[docIndex].step3 = true;
                saveDocument(docIndex);
                return;
            }
            
            // Update progress indicator
            updateProgressIndicator(docIndex);
        }

        function updateProgressIndicator(docIndex) {
            const steps = document.querySelectorAll(`#document-${docIndex} .progress-step`);
            
            steps.forEach((step, index) => {
                const stepNumber = index + 1;
                step.classList.remove('active', 'completed');
                
                if (completedSteps[docIndex][`step${stepNumber}`]) {
                    step.classList.add('completed');
                } else {
                    // Find the next active step
                    if (stepNumber === 1 && !completedSteps[docIndex].step1) {
                        step.classList.add('active');
                    } else if (stepNumber === 2 && completedSteps[docIndex].step1 && !completedSteps[docIndex].step2) {
                        step.classList.add('active');
                    } else if (stepNumber === 3 && completedSteps[docIndex].step2 && !completedSteps[docIndex].step3) {
                        step.classList.add('active');
                    }
                }
            });
        }

        function showAutoSaveIndicator() {
            const indicator = document.getElementById('autoSaveIndicator');
            if (indicator) {
                indicator.style.display = 'block';
                setTimeout(() => {
                    indicator.style.display = 'none';
                }, 1500);
            }
        }

        function updateFormData(docIndex) {
            const summaryElement = document.getElementById('edited-summary-' + docIndex);
            
            document.getElementById('form-selected-sentences-' + docIndex).value = JSON.stringify(selectedSentences[docIndex] || []);
            document.getElementById('form-writing-style-' + docIndex).value = writingStyles[docIndex] || '';
            document.getElementById('form-edited-summary-' + docIndex).value = summaryElement ? summaryElement.value : '';
            document.getElementById('form-step1-' + docIndex).value = completedSteps[docIndex].step1 ? '1' : '';
            document.getElementById('form-step2-' + docIndex).value = completedSteps[docIndex].step2 ? '1' : '';
            document.getElementById('form-step3-' + docIndex).value = completedSteps[docIndex].step3 ? '1' : '';
        }

        function saveDocument(docIndex) {
            updateFormData(docIndex);
            const form = document.getElementById('save-form-' + docIndex);
            if (form) {
                form.submit();
            }
        }

        function saveCurrentDocument() {
            saveDocument(currentDocument);
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
                updateSelectedCount(docIndex);
            }
            
            // Load writing style
            if (writingStyles[docIndex]) {
                const styleElement = document.querySelector(`#step2-${docIndex} .writing-style-option[data-style="${writingStyles[docIndex]}"]`);
                if (styleElement) {
                    styleElement.classList.add('selected');
                    const nextButton = document.getElementById('step2-complete-' + docIndex);
                    if (nextButton) {
                        nextButton.disabled = false;
                    }
                }
            }
            
            // Update progress indicator
            updateProgressIndicator(docIndex);
        }

        // Auto-save functionality
        document.addEventListener('input', function(e) {
            if (e.target.id && e.target.id.startsWith('edited-summary-')) {
                showAutoSaveIndicator();
            }
        });
    </script>
</body>
</html>