<?php
// Start session and error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// Simple auth check - no external functions needed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reviewer') {
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

// Get assignment ID from URL
$assignment_id = $_GET['id'] ?? null;
$assignment = null;
$documents = [];
$labeling_results = [];

// Handle submit review
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] == 'submit_review') {
            // Create reviews table if it doesn't exist
            try {
                $create_reviews_table = "CREATE TABLE IF NOT EXISTS reviews (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    assignment_id int(11) NOT NULL,
                    reviewer_id int(11) NOT NULL,
                    rating int(11) DEFAULT NULL,
                    comments text,
                    status enum('pending','approved','rejected','needs_revision') DEFAULT 'pending',
                    feedback longtext,
                    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY unique_assignment_reviewer (assignment_id, reviewer_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $db->exec($create_reviews_table);
            } catch (Exception $e) {
                // Table already exists or creation failed
            }
            
            $assignment_id = intval($_POST['assignment_id']);
            $rating = intval($_POST['rating']);
            $comments = trim($_POST['comments']);
            $status = $_POST['review_status'];
            $feedback = json_encode($_POST['feedback'] ?? []);
            
            // Check if review already exists
            $query = "SELECT id FROM reviews WHERE assignment_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$assignment_id]);
            $existing_review = $stmt->fetch();
            
            if ($existing_review) {
                // Update existing review
                $query = "UPDATE reviews SET rating = ?, comments = ?, status = ?, feedback = ? WHERE assignment_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$rating, $comments, $status, $feedback, $assignment_id]);
            } else {
                // Insert new review
                $query = "INSERT INTO reviews (assignment_id, reviewer_id, rating, comments, status, feedback) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment_id, $current_user_id, $rating, $comments, $status, $feedback]);
            }
            
            // Update assignment status
            $query = "UPDATE assignments SET status = 'reviewed' WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$assignment_id]);
            
            $success_message = 'Gửi review thành công!';
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi khi gửi review: ' . $e->getMessage();
    }
}

// Get assignment info if ID provided
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
                         END as original_ai_summary,
                         CASE 
                             WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN 'single' 
                             WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN 'multi' 
                             ELSE 'single'
                         END as type,
                         labeler.full_name as labeler_name,
                         admin.full_name as assigned_by_name,
                         r.rating as existing_rating,
                         r.comments as existing_comments,
                         r.status as existing_review_status
                  FROM assignments a 
                  LEFT JOIN documents d ON a.document_id = d.id
                  LEFT JOIN document_groups dg ON a.group_id = dg.id
                  LEFT JOIN users labeler ON a.user_id = labeler.id
                  LEFT JOIN users admin ON a.assigned_by = admin.id
                  LEFT JOIN reviews r ON a.id = r.assignment_id
                  WHERE a.id = ? AND a.status IN ('completed', 'reviewed')";
                  
        $stmt = $db->prepare($query);
        $stmt->execute([$assignment_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assignment) {
            $error_message = 'Không tìm thấy assignment hoặc assignment chưa hoàn thành.';
        } else {
            // Get related documents
            if ($assignment['type'] == 'single' && $assignment['document_id']) {
                $query = "SELECT * FROM documents WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment['document_id']]);
                $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($doc) {
                    $documents = [$doc];
                }
            } elseif ($assignment['type'] == 'multi' && $assignment['group_id']) {
                // Get documents in group via document_group_items table
                $query = "SELECT d.* FROM documents d 
                          JOIN document_group_items dgi ON d.id = dgi.document_id 
                          WHERE dgi.group_id = ? 
                          ORDER BY dgi.sort_order";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment['group_id']]);
                $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Get labeling results
            try {
                $query = "SELECT * FROM labeling_results WHERE assignment_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$assignment_id]);
                $labeling_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Create results map by document_id
                $results_map = [];
                foreach ($labeling_results as $result) {
                    $results_map[$result['document_id']] = $result;
                }
            } catch (Exception $e) {
                // labeling_results table doesn't exist - create dummy data
                $results_map = [];
                foreach ($documents as $doc) {
                    $results_map[$doc['id']] = [
                        'selected_sentences' => '[]',
                        'writing_style' => 'formal',
                        'edited_summary' => $doc['ai_summary'] ?? 'No summary available',
                        'step1_completed' => 1,
                        'step2_completed' => 1,
                        'step3_completed' => 1,
                        'completed_at' => $assignment['updated_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi khi lấy thông tin assignment: ' . $e->getMessage();
    }
} else {
    // If no ID, get first assignment that needs review
    try {
        $query = "SELECT id FROM assignments WHERE status = 'completed' ORDER BY id ASC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result) {
            header("Location: review.php?id=" . $result['id']);
            exit();
        } else {
            $error_message = 'Không có assignment nào cần review.';
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi khi tìm assignment: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review công việc - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
        }
        .sidebar {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
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
        .review-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .review-header {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
            padding: 20px;
        }
        .content-section {
            padding: 25px;
            border-bottom: 1px solid #dee2e6;
        }
        .selected-sentence {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .rating-stars {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .rating-stars.active,
        .rating-stars:hover {
            color: #ffc107;
        }
        .comparison-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
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
            background: #dc3545;
            color: white;
        }
        .step-progress {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step-completed {
            background: #28a745;
            color: white;
        }
        .step-incomplete {
            background: #e9ecef;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center text-white mb-4">
            <i class="fas fa-user-check fa-2x mb-2"></i>
            <h5>Reviewer Panel</h5>
            <small>Xin chào, <?php echo htmlspecialchars($current_user['full_name']); ?></small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="review.php">
                    <i class="fas fa-clipboard-check me-2"></i>Review công việc
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my_reviews.php">
                    <i class="fas fa-list-check me-2"></i>Reviews của tôi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="statistics.php">
                    <i class="fas fa-chart-line me-2"></i>Thống kê
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
                    <a href="dashboard.php" class="btn btn-outline-primary">← Quay lại Dashboard</a>
                </div>
            </div>
        <?php elseif ($assignment): ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-dark">Review: <?php echo htmlspecialchars($assignment['title']); ?></h2>
                    <small class="text-muted">
                        Assignment #<?php echo $assignment['id']; ?> • 
                        Người gán nhãn: <?php echo htmlspecialchars($assignment['labeler_name']); ?> •
                        <?php echo $assignment['type'] == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                    </small>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-2"></i>Quay lại
                    </a>
                    <?php if (!$assignment['existing_rating']): ?>
                        <button type="button" class="btn btn-success" onclick="submitReview()">
                            <i class="fas fa-check me-2"></i>Gửi Review
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

            <!-- Review Interface -->
            <?php foreach ($documents as $doc_index => $document): ?>
                <?php $result = $results_map[$document['id']] ?? []; ?>
                <div class="document-container <?php echo $doc_index == 0 ? '' : 'd-none'; ?>" id="document-<?php echo $doc_index; ?>">
                    <div class="review-container mb-4">
                        
                        <!-- Progress Overview -->
                        <div class="review-header">
                            <h4><i class="fas fa-clipboard-check me-2"></i><?php echo htmlspecialchars($document['title']); ?></h4>
                            <div class="step-progress">
                                <div class="step-circle <?php echo ($result['step1_completed'] ?? 0) ? 'step-completed' : 'step-incomplete'; ?>">1</div>
                                <div class="step-circle <?php echo ($result['step2_completed'] ?? 0) ? 'step-completed' : 'step-incomplete'; ?>">2</div>
                                <div class="step-circle <?php echo ($result['step3_completed'] ?? 0) ? 'step-completed' : 'step-incomplete'; ?>">3</div>
                            </div>
                            <p class="text-center mb-0">
                                Hoàn thành: <?php echo date('d/m/Y H:i', strtotime($result['completed_at'] ?? $assignment['updated_at'] ?? date('Y-m-d H:i:s'))); ?>
                            </p>
                        </div>

                        <!-- Step 1 Review: Selected Sentences -->
                        <div class="content-section">
                            <h5><i class="fas fa-mouse-pointer me-2 text-primary"></i>Bước 1: Câu được chọn</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Nội dung gốc:</h6>
                                    <div class="comparison-box" style="max-height: 300px; overflow-y: auto;">
                                        <?php echo nl2br(htmlspecialchars($document['content'])); ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Câu quan trọng đã chọn:</h6>
                                    <div class="comparison-box" style="max-height: 300px; overflow-y: auto;">
                                        <?php 
                                        $selected_sentences = json_decode($result['selected_sentences'] ?? '[]', true);
                                        if (!empty($selected_sentences)) {
                                            $sentences = preg_split('/(?<=[.!?])\s+/', $document['content']);
                                            foreach ($selected_sentences as $index) {
                                                if (isset($sentences[$index])) {
                                                    echo '<div class="selected-sentence">' . htmlspecialchars(trim($sentences[$index])) . '</div>';
                                                }
                                            }
                                        } else {
                                            echo '<div class="text-muted">Không có câu nào được chọn</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Đánh giá việc chọn câu:</label>
                                <select class="form-select" name="step1_feedback">
                                    <option value="excellent">Xuất sắc - Chọn đúng các câu quan trọng</option>
                                    <option value="good">Tốt - Chọn hầu hết câu quan trọng</option>
                                    <option value="fair">Khá - Còn thiếu một số câu quan trọng</option>
                                    <option value="poor">Cần cải thiện - Chọn chưa chính xác</option>
                                </select>
                            </div>
                        </div>

                        <!-- Step 2 Review: Writing Style -->
                        <div class="content-section">
                            <h5><i class="fas fa-palette me-2 text-info"></i>Bước 2: Phong cách văn bản</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Phong cách đã chọn:</h6>
                                    <div class="comparison-box">
                                        <?php 
                                        $style_names = [
                                            'formal' => 'Trang trọng',
                                            'casual' => 'Thân thiện',
                                            'technical' => 'Kỹ thuật',
                                            'news' => 'Tin tức'
                                        ];
                                        $chosen_style = $result['writing_style'] ?? 'Chưa chọn';
                                        echo '<span class="badge bg-primary fs-6">' . ($style_names[$chosen_style] ?? $chosen_style) . '</span>';
                                        ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Phù hợp với nội dung:</h6>
                                    <div class="comparison-box">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="step2_feedback" value="appropriate" id="style_appropriate">
                                            <label class="form-check-label" for="style_appropriate">
                                                <i class="fas fa-check text-success me-1"></i>Phù hợp
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="step2_feedback" value="inappropriate" id="style_inappropriate">
                                            <label class="form-check-label" for="style_inappropriate">
                                                <i class="fas fa-times text-danger me-1"></i>Không phù hợp
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 Review: Summary Comparison -->
                        <div class="content-section">
                            <h5><i class="fas fa-edit me-2 text-success"></i>Bước 3: So sánh bản tóm tắt</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Bản tóm tắt AI gốc:</h6>
                                    <div class="comparison-box" style="max-height: 200px; overflow-y: auto;">
                                        <?php echo nl2br(htmlspecialchars($document['ai_summary'] ?? 'Không có tóm tắt')); ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Bản tóm tắt đã chỉnh sửa:</h6>
                                    <div class="comparison-box" style="max-height: 200px; overflow-y: auto;">
                                        <?php echo nl2br(htmlspecialchars($result['edited_summary'] ?? 'Chưa có chỉnh sửa')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Chất lượng chỉnh sửa:</label>
                                <select class="form-select" name="step3_feedback">
                                    <option value="improved">Cải thiện đáng kể so với bản gốc</option>
                                    <option value="same">Tương đương với bản gốc</option>
                                    <option value="slightly_better">Cải thiện nhẹ</option>
                                    <option value="worse">Kém hơn bản gốc</option>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Review Form -->
            <div class="review-container">
                <div class="content-section">
                    <h5><i class="fas fa-star me-2 text-warning"></i>Đánh giá tổng thể</h5>
                    
                    <form id="reviewForm" method="POST">
                        <input type="hidden" name="action" value="submit_review">
                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                        <input type="hidden" name="rating" id="ratingValue" value="<?php echo $assignment['existing_rating'] ?? ''; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Xếp hạng (1-5 sao):</label>
                                    <div class="text-center">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star rating-stars <?php echo ($assignment['existing_rating'] && $i <= $assignment['existing_rating']) ? 'active' : ''; ?>" 
                                               data-rating="<?php echo $i; ?>" 
                                               onclick="setRating(<?php echo $i; ?>)"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="text-center mt-2">
                                        <span id="ratingText">
                                            <?php echo $assignment['existing_rating'] ? $assignment['existing_rating'] . ' sao' : 'Chưa đánh giá'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Kết quả review:</label>
                                    <select class="form-select" name="review_status" required>
                                        <option value="">Chọn kết quả</option>
                                        <option value="approved" <?php echo ($assignment['existing_review_status'] == 'approved') ? 'selected' : ''; ?>>
                                            Chấp thuận
                                        </option>
                                        <option value="needs_revision" <?php echo ($assignment['existing_review_status'] == 'needs_revision') ? 'selected' : ''; ?>>
                                            Cần chỉnh sửa
                                        </option>
                                        <option value="rejected" <?php echo ($assignment['existing_review_status'] == 'rejected') ? 'selected' : ''; ?>>
                                            Từ chối
                                        </option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nhận xét chi tiết:</label>
                                    <textarea class="form-control" name="comments" rows="8" 
                                              placeholder="Nhập nhận xét chi tiết về chất lượng gán nhãn..."
                                              <?php echo $assignment['existing_rating'] ? 'readonly' : ''; ?>><?php echo htmlspecialchars($assignment['existing_comments'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <?php if (!$assignment['existing_rating']): ?>
                            <div class="text-end">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-check me-2"></i>Gửi Review
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Assignment này đã được review vào <?php echo date('d/m/Y H:i', strtotime($assignment['updated_at'] ?? date('Y-m-d H:i:s'))); ?>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentDocument = 0;

        function switchDocument(docIndex) {
            // Hide all documents
            document.querySelectorAll('.document-container').forEach(el => el.classList.add('d-none'));
            document.querySelectorAll('.document-tab').forEach(el => el.classList.remove('active'));
            
            // Show selected document
            document.getElementById('document-' + docIndex).classList.remove('d-none');
            document.getElementById('tab-' + docIndex).classList.add('active');
            
            currentDocument = docIndex;
        }

        function setRating(rating) {
            document.getElementById('ratingValue').value = rating;
            
            // Update visual stars
            document.querySelectorAll('.rating-stars').forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
            
            // Update rating text
            const ratingTexts = {
                1: '1 sao - Rất kém',
                2: '2 sao - Kém',
                3: '3 sao - Trung bình',
                4: '4 sao - Tốt',
                5: '5 sao - Xuất sắc'
            };
            document.getElementById('ratingText').textContent = ratingTexts[rating];
        }

        function submitReview() {
            const rating = document.getElementById('ratingValue').value;
            const status = document.querySelector('select[name="review_status"]').value;
            const comments = document.querySelector('textarea[name="comments"]').value.trim();
            
            if (!rating) {
                alert('Vui lòng chọn xếp hạng!');
                return;
            }
            
            if (!status) {
                alert('Vui lòng chọn kết quả review!');
                return;
            }
            
            if (!comments) {
                alert('Vui lòng nhập nhận xét!');
                return;
            }
            
            if (confirm('Bạn có chắc muốn gửi review này?')) {
                document.getElementById('reviewForm').submit();
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial rating if exists
            const existingRating = <?php echo $assignment['existing_rating'] ?? 0; ?>;
            if (existingRating > 0) {
                setRating(existingRating);
            }
        });
    </script>
</body>
</html>