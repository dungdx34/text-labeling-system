<?php
// Start session and error handling
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

// Create necessary tables if they don't exist
try {
    // Create reviews table
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
    
    // Create assignments table
    $create_assignments_table = "CREATE TABLE IF NOT EXISTS assignments (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        document_id int(11) DEFAULT NULL,
        group_id int(11) DEFAULT NULL,
        assigned_by int(11) NOT NULL,
        status enum('pending','in_progress','completed','reviewed') DEFAULT 'pending',
        assigned_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($create_assignments_table);
    
    // Create labeling_results table
    $create_results_table = "CREATE TABLE IF NOT EXISTS labeling_results (
        id int(11) NOT NULL AUTO_INCREMENT,
        assignment_id int(11) NOT NULL,
        document_id int(11) NOT NULL,
        selected_sentences longtext,
        writing_style varchar(50) DEFAULT 'formal',
        edited_summary text,
        step1_completed tinyint(1) DEFAULT 0,
        step2_completed tinyint(1) DEFAULT 0,
        step3_completed tinyint(1) DEFAULT 0,
        completed_at timestamp NULL DEFAULT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($create_results_table);
    
} catch (Exception $e) {
    // Tables might already exist - continue
}

$error_message = '';

// Check if tables exist and get column information
$tables_exist = [];
$table_columns = [];

try {
    $tables_to_check = ['reviews', 'assignments', 'labeling_results', 'documents', 'document_groups', 'users'];
    foreach ($tables_to_check as $table) {
        $check = $db->query("SHOW TABLES LIKE '$table'");
        $tables_exist[$table] = $check->rowCount() > 0;
        
        if ($tables_exist[$table]) {
            $columns = $db->query("SHOW COLUMNS FROM $table");
            $table_columns[$table] = [];
            while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
                $table_columns[$table][] = $col['Field'];
            }
        }
    }
} catch (Exception $e) {
    $error_message = 'Lỗi kiểm tra cấu trúc database: ' . $e->getMessage();
}

// Initialize stats with default values
$personal_stats = [
    'total_reviews' => 0,
    'avg_rating' => 0,
    'approved' => 0,
    'rejected' => 0,
    'needs_revision' => 0,
    'pending' => 0
];

$daily_stats = [];
$labeler_stats = [];
$document_type_stats = [];
$writing_style_stats = [];
$system_stats = [
    'total_reviewers' => 0,
    'system_avg_rating' => 0,
    'total_system_reviews' => 0,
    'my_rank' => 0
];
$top_assignments = [];

// Get personal statistics
if ($tables_exist['reviews']) {
    try {
        // Check which columns exist in reviews table
        $rating_col = in_array('rating', $table_columns['reviews']) ? 'rating' : 'NULL';
        $status_col = in_array('status', $table_columns['reviews']) ? 'status' : "'pending'";
        $reviewer_col = in_array('reviewer_id', $table_columns['reviews']) ? 'reviewer_id' : 'NULL';
        
        $query = "SELECT 
                    COUNT(*) as total_reviews,
                    AVG($rating_col) as avg_rating,
                    SUM(CASE WHEN $status_col = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN $status_col = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN $status_col = 'needs_revision' THEN 1 ELSE 0 END) as needs_revision,
                    SUM(CASE WHEN $status_col = 'pending' THEN 1 ELSE 0 END) as pending
                  FROM reviews WHERE $reviewer_col = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $personal_stats = $result;
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi khi lấy thống kê cá nhân: ' . $e->getMessage();
    }
}

// Get daily statistics (last 30 days)
if ($tables_exist['reviews']) {
    try {
        $created_col = in_array('created_at', $table_columns['reviews']) ? 'created_at' : 'NOW()';
        $rating_col = in_array('rating', $table_columns['reviews']) ? 'rating' : 'NULL';
        $reviewer_col = in_array('reviewer_id', $table_columns['reviews']) ? 'reviewer_id' : 'NULL';
        
        $query = "SELECT 
                    DATE($created_col) as date,
                    COUNT(*) as reviews_count,
                    AVG($rating_col) as avg_rating
                  FROM reviews 
                  WHERE $reviewer_col = ? AND $created_col >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  GROUP BY DATE($created_col)
                  ORDER BY date DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user_id]);
        $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $daily_stats = [];
    }
}

// Get labeler statistics
if ($tables_exist['reviews'] && $tables_exist['assignments'] && $tables_exist['users']) {
    try {
        $rating_col = in_array('rating', $table_columns['reviews']) ? 'r.rating' : 'NULL';
        $status_col = in_array('status', $table_columns['reviews']) ? 'r.status' : "'pending'";
        $reviewer_col = in_array('reviewer_id', $table_columns['reviews']) ? 'r.reviewer_id' : 'NULL';
        $assignment_col = in_array('assignment_id', $table_columns['reviews']) ? 'r.assignment_id' : 'NULL';
        
        $query = "SELECT 
                    u.full_name as labeler_name,
                    COUNT(r.id) as total_reviews,
                    AVG($rating_col) as avg_rating,
                    SUM(CASE WHEN $status_col = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN $status_col = 'rejected' THEN 1 ELSE 0 END) as rejected
                  FROM reviews r
                  JOIN assignments a ON $assignment_col = a.id
                  JOIN users u ON a.user_id = u.id
                  WHERE $reviewer_col = ?
                  GROUP BY u.id, u.full_name
                  HAVING COUNT(r.id) > 0
                  ORDER BY total_reviews DESC
                  LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user_id]);
        $labeler_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $labeler_stats = [];
    }
}

// Get document type statistics
if ($tables_exist['reviews'] && $tables_exist['assignments']) {
    try {
        $rating_col = in_array('rating', $table_columns['reviews']) ? 'r.rating' : 'NULL';
        $status_col = in_array('status', $table_columns['reviews']) ? 'r.status' : "'pending'";
        $reviewer_col = in_array('reviewer_id', $table_columns['reviews']) ? 'r.reviewer_id' : 'NULL';
        $assignment_col = in_array('assignment_id', $table_columns['reviews']) ? 'r.assignment_id' : 'NULL';
        
        // Determine document type based on available fields
        $type_case = "CASE 
                        WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN 'single'
                        WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN 'multi'
                        ELSE 'single'
                      END";
        
        $query = "SELECT 
                    $type_case as document_type,
                    COUNT(r.id) as total_reviews,
                    AVG($rating_col) as avg_rating,
                    SUM(CASE WHEN $status_col = 'approved' THEN 1 ELSE 0 END) as approved
                  FROM reviews r
                  JOIN assignments a ON $assignment_col = a.id
                  WHERE $reviewer_col = ?
                  GROUP BY $type_case";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user_id]);
        $document_type_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $document_type_stats = [];
    }
}

// Get writing style statistics
if ($tables_exist['reviews'] && $tables_exist['assignments'] && $tables_exist['labeling_results']) {
    try {
        $rating_col = in_array('rating', $table_columns['reviews']) ? 'r.rating' : 'NULL';
        $reviewer_col = in_array('reviewer_id', $table_columns['reviews']) ? 'r.reviewer_id' : 'NULL';
        $assignment_col = in_array('assignment_id', $table_columns['reviews']) ? 'r.assignment_id' : 'NULL';
        
        $query = "SELECT 
                    COALESCE(lr.writing_style, 'formal') as writing_style,
                    COUNT(r.id) as total_reviews,
                    AVG($rating_col) as avg_rating
                  FROM reviews r
                  JOIN assignments a ON $assignment_col = a.id
                  LEFT JOIN labeling_results lr ON a.id = lr.assignment_id
                  WHERE $reviewer_col = ?
                  GROUP BY COALESCE(lr.writing_style, 'formal')
                  ORDER BY total_reviews DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user_id]);
        $writing_style_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $writing_style_stats = [];
    }
}

// Get system statistics
if ($tables_exist['reviews']) {
    try {
        $rating_col = in_array('rating', $table_columns['reviews']) ? 'rating' : 'NULL';
        $reviewer_col = in_array('reviewer_id', $table_columns['reviews']) ? 'reviewer_id' : 'NULL';
        
        $query = "SELECT 
                    COUNT(DISTINCT $reviewer_col) as total_reviewers,
                    AVG($rating_col) as system_avg_rating,
                    COUNT(*) as total_system_reviews
                  FROM reviews";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $system_stats = array_merge($system_stats, $result);
        }
        
        // Get user rank
        $query = "SELECT reviewer_rank FROM (
                    SELECT $reviewer_col, 
                           ROW_NUMBER() OVER (ORDER BY COUNT(*) DESC) as reviewer_rank
                    FROM reviews 
                    GROUP BY $reviewer_col
                  ) ranking WHERE $reviewer_col = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user_id]);
        $rank_result = $stmt->fetch();
        $system_stats['my_rank'] = $rank_result['reviewer_rank'] ?? 0;
    } catch (Exception $e) {
        // Keep default values
    }
}

// Get top assignments
if ($tables_exist['reviews'] && $tables_exist['assignments'] && $tables_exist['users']) {
    try {
        $rating_col = in_array('rating', $table_columns['reviews']) ? 'r.rating' : 'NULL';
        $created_col = in_array('created_at', $table_columns['reviews']) ? 'r.created_at' : 'NOW()';
        $reviewer_col = in_array('reviewer_id', $table_columns['reviews']) ? 'r.reviewer_id' : 'NULL';
        $assignment_col = in_array('assignment_id', $table_columns['reviews']) ? 'r.assignment_id' : 'NULL';
        
        // Build title selection based on available tables
        $title_case = "'Assignment #' + CAST(a.id AS CHAR)";
        if ($tables_exist['documents'] && $tables_exist['document_groups']) {
            $title_case = "CASE 
                            WHEN a.document_id IS NOT NULL AND a.document_id > 0 THEN COALESCE(d.title, CONCAT('Document #', a.document_id))
                            WHEN a.group_id IS NOT NULL AND a.group_id > 0 THEN COALESCE(dg.group_name, CONCAT('Group #', a.group_id))
                            ELSE CONCAT('Assignment #', a.id)
                          END";
        }
        
        $query = "SELECT 
                    $rating_col as rating,
                    $title_case as document_title,
                    u.full_name as labeler_name,
                    $created_col as created_at
                  FROM reviews r
                  JOIN assignments a ON $assignment_col = a.id" .
                  ($tables_exist['documents'] ? " LEFT JOIN documents d ON a.document_id = d.id" : "") .
                  ($tables_exist['document_groups'] ? " LEFT JOIN document_groups dg ON a.group_id = dg.id" : "") . "
                  JOIN users u ON a.user_id = u.id
                  WHERE $reviewer_col = ?
                  ORDER BY $rating_col DESC, $created_col DESC
                  LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->execute([$current_user_id]);
        $top_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $top_assignments = [];
    }
}

// Ensure numeric values
foreach ($personal_stats as $key => $value) {
    $personal_stats[$key] = is_numeric($value) ? $value : 0;
}
foreach ($system_stats as $key => $value) {
    $system_stats[$key] = is_numeric($value) ? $value : 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê - Text Labeling System</title>
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
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .progress-custom {
            height: 8px;
            border-radius: 4px;
        }
        .rating-stars {
            color: #ffc107;
        }
        .comparison-bar {
            background: #e9ecef;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        .comparison-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
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
                <a class="nav-link" href="review.php">
                    <i class="fas fa-clipboard-check me-2"></i>Review công việc
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my_reviews.php">
                    <i class="fas fa-list-check me-2"></i>Reviews của tôi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="statistics.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark">Thống kê Review</h2>
            <div class="text-muted">
                <i class="fas fa-calendar me-1"></i>
                Cập nhật: <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                <br><small>Một số thống kê có thể không chính xác do cấu trúc database.</small>
            </div>
        <?php endif; ?>

        <?php if (!$tables_exist['reviews']): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Bảng reviews chưa tồn tại. Hệ thống sẽ tự động tạo khi bạn thực hiện review đầu tiên.
            </div>
        <?php endif; ?>

        <!-- Personal Statistics -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number text-primary"><?php echo intval($personal_stats['total_reviews']); ?></div>
                    <div class="stat-label">Tổng Reviews</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number text-warning">
                        <?php echo $personal_stats['avg_rating'] ? round($personal_stats['avg_rating'], 1) : 0; ?>
                    </div>
                    <div class="stat-label">Rating Trung Bình</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number text-success"><?php echo intval($personal_stats['approved']); ?></div>
                    <div class="stat-label">Đã Duyệt</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number text-info">#<?php echo $system_stats['my_rank'] ?: 'N/A'; ?></div>
                    <div class="stat-label">Xếp Hạng</div>
                </div>
            </div>
        </div>

        <!-- Review Status Distribution -->
        <div class="row">
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie me-2 text-primary"></i>
                        Phân bố trạng thái reviews
                    </h5>
                    <?php if ($personal_stats['total_reviews'] > 0): ?>
                        <canvas id="statusChart" width="400" height="200"></canvas>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-pie"></i>
                            <p>Chưa có dữ liệu để hiển thị</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Daily Activity Chart -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2 text-info"></i>
                        Hoạt động 30 ngày gần đây
                    </h5>
                    <?php if (!empty($daily_stats)): ?>
                        <canvas id="dailyChart" width="400" height="200"></canvas>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-line"></i>
                            <p>Chưa có hoạt động trong 30 ngày gần đây</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Statistics -->
        <div class="row">
            <!-- Labeler Performance -->
            <div class="col-lg-6">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-users me-2 text-success"></i>
                        Thống kê theo Labeler
                    </h5>
                    <?php if (empty($labeler_stats)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>Chưa có dữ liệu về labeler</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Labeler</th>
                                        <th>Reviews</th>
                                        <th>Rating TB</th>
                                        <th>Tỷ lệ duyệt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($labeler_stats as $labeler): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($labeler['labeler_name']); ?></td>
                                            <td><?php echo intval($labeler['total_reviews']); ?></td>
                                            <td>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= round($labeler['avg_rating']) ? '' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $approval_rate = $labeler['total_reviews'] > 0 ? 
                                                    round(($labeler['approved'] / $labeler['total_reviews']) * 100, 1) : 0;
                                                ?>
                                                <div class="comparison-bar">
                                                    <div class="comparison-fill" style="width: <?php echo $approval_rate; ?>%"></div>
                                                </div>
                                                <small><?php echo $approval_rate; ?>%</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Document Type & Writing Style Stats -->
            <div class="col-lg-6">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-file-text me-2 text-warning"></i>
                        Thống kê chi tiết
                    </h5>
                    
                    <!-- Document Type Stats -->
                    <h6 class="text-primary">Theo loại văn bản:</h6>
                    <?php if (!empty($document_type_stats)): ?>
                        <?php foreach ($document_type_stats as $type_stat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo $type_stat['document_type'] == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?></span>
                                <div>
                                    <span class="badge bg-primary"><?php echo intval($type_stat['total_reviews']); ?> reviews</span>
                                    <span class="badge bg-warning"><?php echo round($type_stat['avg_rating'], 1); ?> ⭐</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Chưa có dữ liệu</p>
                    <?php endif; ?>

                    <hr>

                    <!-- Writing Style Stats -->
                    <h6 class="text-success">Theo phong cách văn bản:</h6>
                    <?php if (!empty($writing_style_stats)): ?>
                        <?php foreach ($writing_style_stats as $style_stat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo ucfirst($style_stat['writing_style']); ?></span>
                                <div>
                                    <span class="badge bg-info"><?php echo intval($style_stat['total_reviews']); ?></span>
                                    <span class="badge bg-warning"><?php echo round($style_stat['avg_rating'], 1); ?> ⭐</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Chưa có dữ liệu</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Assignments -->
        <div class="row">
            <div class="col-lg-8">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-trophy me-2 text-warning"></i>
                        Top assignments được đánh giá cao
                    </h5>
                    <?php if (empty($top_assignments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-trophy"></i>
                            <p>Chưa có assignment nào được review</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Văn bản</th>
                                        <th>Labeler</th>
                                        <th>Rating</th>
                                        <th>Ngày review</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_assignments as $assignment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars(substr($assignment['document_title'] ?? 'Untitled', 0, 40)); ?></strong>
                                                <?php if (strlen($assignment['document_title'] ?? '') > 40): ?>...<?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($assignment['labeler_name'] ?? 'Unknown'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= ($assignment['rating'] ?? 0) ? '' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?php echo date('d/m/Y', strtotime($assignment['created_at'] ?? 'now')); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Comparison -->
            <div class="col-lg-4">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-bar me-2 text-danger"></i>
                        So sánh hệ thống
                    </h5>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Rating của bạn:</span>
                            <strong class="text-primary"><?php echo $personal_stats['avg_rating'] ? round($personal_stats['avg_rating'], 1) : 0; ?></strong>
                        </div>
                        <div class="progress progress-custom mt-1">
                            <div class="progress-bar bg-primary" style="width: <?php echo ($personal_stats['avg_rating'] ?? 0) * 20; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Rating TB hệ thống:</span>
                            <strong class="text-secondary"><?php echo round($system_stats['system_avg_rating'] ?? 0, 1); ?></strong>
                        </div>
                        <div class="progress progress-custom mt-1">
                            <div class="progress-bar bg-secondary" style="width: <?php echo ($system_stats['system_avg_rating'] ?? 0) * 20; ?>%"></div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="text-center">
                        <h6>Thứ hạng của bạn</h6>
                        <div class="display-4 text-warning">#<?php echo $system_stats['my_rank'] ?: 'N/A'; ?></div>
                        <small class="text-muted">trong <?php echo intval($system_stats['total_reviewers']); ?> reviewers</small>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Xếp hạng dựa trên số lượng reviews
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Only create charts if there's data and the canvas exists
        <?php if ($personal_stats['total_reviews'] > 0): ?>
        // Status Distribution Chart
        const statusCanvas = document.getElementById('statusChart');
        if (statusCanvas) {
            const statusCtx = statusCanvas.getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Đã duyệt', 'Từ chối', 'Cần sửa', 'Chờ xử lý'],
                    datasets: [{
                        data: [
                            <?php echo intval($personal_stats['approved']); ?>,
                            <?php echo intval($personal_stats['rejected']); ?>,
                            <?php echo intval($personal_stats['needs_revision']); ?>,
                            <?php echo intval($personal_stats['pending']); ?>
                        ],
                        backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#6c757d']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        <?php endif; ?>

        <?php if (!empty($daily_stats)): ?>
        // Daily Activity Chart
        const dailyCanvas = document.getElementById('dailyChart');
        if (dailyCanvas) {
            const dailyCtx = dailyCanvas.getContext('2d');
            
            // Create array for last 30 days
            const last30Days = [];
            const reviewData = [];
            
            for (let i = 29; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                const dateStr = date.toISOString().split('T')[0];
                const displayDate = (date.getMonth() + 1) + '/' + date.getDate();
                
                last30Days.push(displayDate);
                
                // Find reviews for this date
                let count = 0;
                <?php foreach ($daily_stats as $stat): ?>
                if ('<?php echo $stat['date']; ?>' === dateStr) {
                    count = <?php echo intval($stat['reviews_count']); ?>;
                }
                <?php endforeach; ?>
                reviewData.push(count);
            }
            
            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: last30Days,
                    datasets: [{
                        label: 'Reviews per day',
                        data: reviewData,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>