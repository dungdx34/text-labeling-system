<?php
// Set proper UTF-8 headers and encoding
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

// Debug session - remove this after fixing
error_log("Session data: " . print_r($_SESSION, true));

// Database connection with UTF-8 encoding
require_once '../config/database.php';

// Check if session variables exist
if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session");
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['role'])) {
    error_log("No role in session");
    header('Location: ../login.php');
    exit();
}

// Check role
if ($_SESSION['role'] !== 'labeler') {
    error_log("Role is: " . $_SESSION['role'] . ", not labeler");
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

// Debug current user
error_log("Current user ID: " . $current_user_id);

// Get current user info - verify user exists
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_user) {
        error_log("User not found in database");
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
    
    // Double check role from database
    if ($current_user['role'] !== 'labeler') {
        error_log("Database role mismatch: " . $current_user['role']);
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
    
    error_log("User verified: " . $current_user['username']);
} catch (Exception $e) {
    error_log("Database error in user check: " . $e->getMessage());
    die("Database error: " . $e->getMessage());
}

// Create necessary tables if they don't exist
try {
    // Check if labeling_results table exists
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
        error_log("Created labeling_results table");
    }
} catch (Exception $e) {
    error_log("Error creating tables: " . $e->getMessage());
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
            error_log("Found assignment: " . $assignment_id);
        } else {
            error_log("No assignments found for user");
            $error_message = 'Không có công việc nào để gán nhãn.';
        }
    } catch (Exception $e) {
        error_log("Error finding assignment: " . $e->getMessage());
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
        // First check if assignment table has the right columns
        $assignment_columns = [];
        $columns_result = $db->query("SHOW COLUMNS FROM assignments");
        while ($col = $columns_result->fetch(PDO::FETCH_ASSOC)) {
            $assignment_columns[] = $col['Field'];
        }
        
        // Build query based on available columns
        $group_name_field = in_array('group_name', $document_group_columns) ? 'dg.group_name' : 'CONCAT("Group #", a.group_id)';
        $group_desc_field = in_array('description', $document_group_columns) ? 'dg.description' : '"Không có mô tả"';
        
        // Simple query first to test
        $query = "SELECT a.* FROM assignments a WHERE a.id = ? AND a.user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$assignment_id, $current_user_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assignment) {
            error_log("Assignment not found or access denied");
            $error_message = 'Không tìm thấy công việc hoặc bạn không có quyền truy cập.';
        } else {
            error_log("Assignment found: " . json_encode($assignment));
            
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
                    $assignment['ai_summary'] = $doc['ai_summary'];
                }
            } elseif (isset($assignment['group_id']) && $assignment['group_id'] > 0) {
                $assignment['type'] = 'multi';
                $assignment['assignment_type'] = 'multi';
                
                // Get group info if table exists
                if (in_array('group_name', $document_group_columns)) {
                    $query = "SELECT * FROM document_groups WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$assignment['group_id']]);
                    $group = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($group) {
                        $assignment['title'] = $group['group_name'];
                        $assignment['ai_summary'] = $group['description'];
                    }
                }
                
                // Get documents in group
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
            
            // Default values if not set
            $assignment['title'] = $assignment['title'] ?? 'Chưa có tiêu đề';
            $assignment['ai_summary'] = $assignment['ai_summary'] ?? '';
            
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
                error_log("Error getting results: " . $e->getMessage());
                $results_map = [];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting assignment info: " . $e->getMessage());
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
        error_log("Save error: " . $e->getMessage());
        $error_message = 'Lỗi khi lưu: ' . $e->getMessage();
    }
}

if (isset($_GET['saved'])) {
    $success_message = 'Đã lưu thành công!';
}

// Debug final state
error_log("Final state - Assignment ID: " . $assignment_id . ", Documents count: " . count($documents));
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
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
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
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
            font-size: 14px;
            line-height: 1.6;
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
        .content-box {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            background: #fafafa;
        }
    </style>
</head>
<body>
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
        <!-- Debug Information (remove after fixing) -->
        <div class="debug-info">
            <strong>Debug Information:</strong><br>
            Session User ID: <?php echo $_SESSION['user_id'] ?? 'Not set'; ?><br>
            Session Role: <?php echo $_SESSION['role'] ?? 'Not set'; ?><br>
            Assignment ID: <?php echo $assignment_id ?? 'Not found'; ?><br>
            Documents Count: <?php echo count($documents); ?><br>
            Assignment Type: <?php echo $assignment['type'] ?? 'Not set'; ?>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <div class="mt-2">
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
                    <a href="dashboard.php" class="btn btn-outline-info">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Simple labeling interface for now -->
            <?php foreach ($documents as $doc_index => $document): ?>
                <div class="labeling-container">
                    <div class="step-header">
                        <h4><i class="fas fa-file-text me-2"></i>Văn bản: <?php echo htmlspecialchars($document['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                    </div>
                    <div class="step-content">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Nội dung:</h6>
                                <div class="content-box" style="max-height: 400px; overflow-y: auto;">
                                    <?php echo nl2br(htmlspecialchars($document['content'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Tóm tắt AI:</h6>
                                <div class="content-box" style="max-height: 400px; overflow-y: auto;">
                                    <?php echo nl2br(htmlspecialchars($document['ai_summary'] ?? 'Không có tóm tắt', ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Không có công việc nào để gán nhãn.
                <div class="mt-2">
                    <a href="my_tasks.php" class="btn btn-primary">Xem danh sách công việc</a>
                    <a href="dashboard.php" class="btn btn-outline-primary">Dashboard</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>