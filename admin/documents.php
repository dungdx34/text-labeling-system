<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Handle document deletion
if ($_GET && isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $doc_id = $_GET['delete'];
    
    try {
        // First delete any related tasks
        $stmt = $db->prepare("DELETE FROM labeling_tasks WHERE document_id = ?");
        $stmt->execute([$doc_id]);
        
        // Then delete the document
        $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$doc_id]);
        
        $message = "Xóa văn bản thành công!";
    } catch (Exception $e) {
        $error = "Không thể xóa văn bản: " . $e->getMessage();
    }
}

// Handle group deletion
if ($_GET && isset($_GET['delete_group']) && is_numeric($_GET['delete_group'])) {
    $group_id = $_GET['delete_group'];
    
    try {
        // Delete tasks related to documents in this group
        $stmt = $db->prepare("DELETE FROM labeling_tasks WHERE group_id = ?");
        $stmt->execute([$group_id]);
        
        // Delete documents in the group
        $stmt = $db->prepare("DELETE FROM documents WHERE group_id = ?");
        $stmt->execute([$group_id]);
        
        // Delete the group
        $stmt = $db->prepare("DELETE FROM document_groups WHERE id = ?");
        $stmt->execute([$group_id]);
        
        $message = "Xóa nhóm văn bản thành công!";
    } catch (Exception $e) {
        $error = "Không thể xóa nhóm văn bản: " . $e->getMessage();
    }
}

// Get filter parameters
$filter_type = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Get ONLY single documents (exclude multi documents as they are shown in Groups tab)
$single_where_conditions = ["(d.type = 'single' OR d.type IS NULL)", "d.group_id IS NULL"];
$params = [];

if ($filter_type === 'multi') {
    // If filtering for multi, show empty result since multi docs are in Groups tab
    $single_where_conditions[] = "1 = 0";
} elseif (!empty($search)) {
    $single_where_conditions[] = "(d.title LIKE ? OR d.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$single_where_clause = 'WHERE ' . implode(' AND ', $single_where_conditions);

$query = "SELECT d.*, 
    u.username as created_by_name,
    COALESCE(
        (SELECT COUNT(*) FROM labeling_tasks WHERE document_id = d.id),
        0
    ) as task_count
FROM documents d
LEFT JOIN users u ON d.created_by = u.id
$single_where_clause
ORDER BY d.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$single_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get document groups with detailed documents
$group_query = "SELECT dg.*, 
    u.username as created_by_name,
    COUNT(d.id) as document_count,
    COALESCE(
        (SELECT COUNT(*) FROM labeling_tasks WHERE group_id = dg.id),
        0
    ) as task_count
FROM document_groups dg
LEFT JOIN users u ON dg.created_by = u.id
LEFT JOIN documents d ON dg.id = d.group_id
GROUP BY dg.id
ORDER BY dg.created_at DESC";

$stmt = $db->prepare($group_query);
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get documents for each group
$groups_with_documents = [];
foreach ($groups as $group) {
    $group_docs_query = "SELECT d.*, 
        COALESCE(
            (SELECT COUNT(*) FROM labeling_tasks WHERE document_id = d.id),
            0
        ) as task_count
    FROM documents d 
    WHERE d.group_id = ? 
    ORDER BY d.created_at ASC";
    
    $stmt = $db->prepare($group_docs_query);
    $stmt->execute([$group['id']]);
    $group['documents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $groups_with_documents[] = $group;
}

// Statistics
$stats_query = "SELECT 
    COUNT(*) as total_docs,
    COUNT(CASE WHEN type = 'single' OR type IS NULL THEN 1 END) as single_docs,
    COUNT(CASE WHEN type = 'multi' THEN 1 END) as multi_docs,
    SUM(CHAR_LENGTH(content)) as total_chars
FROM documents";
$stmt = $db->prepare($stats_query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$group_stats_query = "SELECT COUNT(*) as total_groups FROM document_groups";
$stmt = $db->prepare($group_stats_query);
$stmt->execute();
$group_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$group_stats['total_groups'] = $group_stats['total_groups'] ?? 0;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Văn bản - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            transition: all 0.3s ease;
            margin-bottom: 5px;
            border-radius: 8px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
        }
        .main-content {
            padding: 30px;
        }
        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 20px;
        }
        .document-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background: white;
        }
        .document-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .document-type-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .stats-mini {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stats-mini:hover {
            transform: translateY(-3px);
        }
        .content-preview {
            max-height: 120px;
            overflow: hidden;
            position: relative;
            line-height: 1.5;
        }
        .content-preview::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: linear-gradient(transparent, white);
        }
        .group-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .group-header:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        .group-document {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }
        .group-document:hover {
            background: #e9ecef;
            box-shadow: 0 3px 12px rgba(0,0,0,0.1);
            transform: translateX(5px);
        }
        .group-summary {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #c3e6cb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #28a745;
        }
        .expand-btn {
            cursor: pointer;
            transition: transform 0.3s ease;
            font-size: 0.9rem;
        }
        .expand-btn.expanded {
            transform: rotate(90deg);
        }
        .collapsible-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
        }
        .collapsible-content.expanded {
            max-height: 3000px;
        }
        .no-group-summary {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        .btn-view-doc {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-view-doc:hover {
            transform: scale(1.05);
            color: white;
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .nav-tabs .nav-link.active {
            background-color: #667eea;
            border-color: #667eea;
            color: white;
        }
        .nav-tabs .nav-link:hover {
            border-color: #667eea;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-4">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-tags me-2"></i>
                        Admin Panel
                    </h4>
                    <div class="nav flex-column">
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="users.php" class="nav-link">
                            <i class="fas fa-users me-2"></i>Quản lý Users
                        </a>
                        <a href="upload.php" class="nav-link">
                            <i class="fas fa-upload me-2"></i>Upload Dữ liệu
                        </a>
                        <a href="documents.php" class="nav-link active">
                            <i class="fas fa-file-text me-2"></i>Quản lý Văn bản
                        </a>
                        <a href="tasks.php" class="nav-link">
                            <i class="fas fa-tasks me-2"></i>Quản lý Tasks
                        </a>
                        <a href="reports.php" class="nav-link">
                            <i class="fas fa-chart-bar me-2"></i>Báo cáo
                        </a>
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                        <a href="../logout.php" class="nav-link text-warning">
                            <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="page-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-2">
                                    <i class="fas fa-file-text me-3"></i>Quản lý Văn bản
                                </h2>
                                <p class="mb-0 opacity-90">Xem và quản lý các văn bản đã upload</p>
                            </div>
                            <a href="upload.php" class="btn btn-light btn-lg">
                                <i class="fas fa-plus me-2"></i>Upload Văn bản mới
                            </a>
                        </div>
                    </div>

                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <i class="fas fa-file-text fa-2x text-primary mb-3"></i>
                                <h4 class="text-primary"><?php echo $stats['total_docs'] ?? 0; ?></h4>
                                <small class="text-muted">Tổng văn bản</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <i class="fas fa-file fa-2x text-success mb-3"></i>
                                <h4 class="text-success"><?php echo $stats['single_docs'] ?? 0; ?></h4>
                                <small class="text-muted">Văn bản đơn</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <i class="fas fa-files fa-2x text-info mb-3"></i>
                                <h4 class="text-info"><?php echo $stats['multi_docs'] ?? 0; ?></h4>
                                <small class="text-muted">Văn bản đa</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <i class="fas fa-folder fa-2x text-warning mb-3"></i>
                                <h4 class="text-warning"><?php echo $group_stats['total_groups']; ?></h4>
                                <small class="text-muted">Nhóm văn bản</small>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="content-card">
                        <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Bộ lọc</h6>
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <select class="form-select" name="type">
                                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>Tất cả loại</option>
                                    <option value="single" <?php echo $filter_type === 'single' ? 'selected' : ''; ?>>Văn bản đơn</option>
                                    <option value="multi" <?php echo $filter_type === 'multi' ? 'selected' : ''; ?>>Văn bản đa (xem ở tab Nhóm)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="search" placeholder="Tìm kiếm theo tiêu đề hoặc nội dung..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Lọc
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-4">
                        <li class="nav-item">
                            <button class="nav-link active" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents-content">
                                <i class="fas fa-file-text me-2"></i>Văn bản đơn (<?php echo count($single_documents); ?>)
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="groups-tab" data-bs-toggle="tab" data-bs-target="#groups-content">
                                <i class="fas fa-folder me-2"></i>Nhóm văn bản (<?php echo count($groups_with_documents); ?>)
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Documents Tab - ONLY Single Documents -->
                        <div class="tab-pane fade show active" id="documents-content">
                            <?php if (!empty($single_documents)): ?>
                                <?php foreach ($single_documents as $doc): ?>
                                    <div class="document-card">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-3">
                                                    <h5 class="mb-0 me-3"><?php echo htmlspecialchars($doc['title']); ?></h5>
                                                    <span class="document-type-badge bg-primary text-white">
                                                        Đơn văn bản
                                                    </span>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <h6 class="text-muted mb-2">
                                                            <i class="fas fa-file-alt me-2"></i>Nội dung:
                                                        </h6>
                                                        <div class="content-preview">
                                                            <?php echo nl2br(htmlspecialchars(substr($doc['content'], 0, 300))); ?>
                                                            <?php if (strlen($doc['content']) > 300): ?>
                                                                <span class="text-muted">...</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <?php if (!empty($doc['ai_summary'])): ?>
                                                            <h6 class="text-success mb-2">
                                                                <i class="fas fa-brain me-2"></i>AI Summary:
                                                            </h6>
                                                            <div class="content-preview text-success">
                                                                <?php echo nl2br(htmlspecialchars(substr($doc['ai_summary'], 0, 200))); ?>
                                                                <?php if (strlen($doc['ai_summary']) > 200): ?>
                                                                    <span class="text-muted">...</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-muted">
                                                                <i class="fas fa-info-circle me-2"></i>Chưa có AI Summary
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <button class="btn btn-view-doc btn-sm mb-2" onclick="viewDocument(<?php echo htmlspecialchars(json_encode($doc)); ?>)">
                                                    <i class="fas fa-eye me-1"></i> Xem
                                                </button>
                                                <br>
                                                <button class="btn btn-outline-danger btn-sm" onclick="deleteDocument(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['title']); ?>')">
                                                    <i class="fas fa-trash me-1"></i> Xóa
                                                </button>
                                            </div>
                                        </div>
                                        <div class="row text-muted small">
                                            <div class="col-md-3">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($doc['created_by_name'] ?? 'Unknown'); ?>
                                            </div>
                                            <div class="col-md-3">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?>
                                            </div>
                                            <div class="col-md-3">
                                                <i class="fas fa-align-left me-1"></i>
                                                <?php echo number_format(strlen($doc['content'])); ?> ký tự
                                            </div>
                                            <div class="col-md-3">
                                                <i class="fas fa-tasks me-1"></i>
                                                <?php echo $doc['task_count']; ?> task(s)
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-file-text fa-4x text-muted mb-4"></i>
                                    <?php if ($filter_type === 'multi'): ?>
                                        <h5 class="text-muted">Văn bản đa được hiển thị ở tab "Nhóm văn bản"</h5>
                                        <p class="text-muted">Hãy chuyển sang tab "Nhóm văn bản" để xem các văn bản đa</p>
                                        <button class="btn btn-info btn-lg" onclick="document.getElementById('groups-tab').click()">
                                            <i class="fas fa-folder me-2"></i>Xem Nhóm văn bản
                                        </button>
                                    <?php else: ?>
                                        <h5 class="text-muted">Không có văn bản đơn nào</h5>
                                        <p class="text-muted">Hãy upload văn bản đơn để bắt đầu</p>
                                        <a href="upload.php" class="btn btn-primary btn-lg">
                                            <i class="fas fa-plus me-2"></i>Upload ngay
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Groups Tab -->
                        <div class="tab-pane fade" id="groups-content">
                            <?php if (!empty($groups_with_documents)): ?>
                                <?php foreach ($groups_with_documents as $group): ?>
                                    <div class="group-header" onclick="toggleGroup(<?php echo $group['id']; ?>)">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-chevron-right expand-btn me-3" id="expand-btn-<?php echo $group['id']; ?>"></i>
                                                    <h5 class="mb-0"><?php echo htmlspecialchars($group['title']); ?></h5>
                                                </div>
                                                <p class="mb-0 opacity-90"><?php echo htmlspecialchars($group['description']); ?></p>
                                            </div>
                                            <button class="btn btn-outline-light btn-sm" onclick="event.stopPropagation(); deleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['title']); ?>')">
                                                <i class="fas fa-trash me-1"></i>Xóa nhóm
                                            </button>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-3">
                                                <i class="fas fa-files me-2"></i>
                                                <?php echo $group['document_count']; ?> văn bản
                                            </div>
                                            <div class="col-md-3">
                                                <i class="fas fa-tasks me-2"></i>
                                                <?php echo $group['task_count']; ?> task(s)
                                            </div>
                                            <div class="col-md-3">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($group['created_by_name'] ?? 'Unknown'); ?>
                                            </div>
                                            <div class="col-md-3">
                                                <i class="fas fa-calendar me-2"></i>
                                                <?php echo date('d/m/Y', strtotime($group['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="collapsible-content" id="group-content-<?php echo $group['id']; ?>">
                                        <!-- Group Summary -->
                                        <?php 
                                        $group_summary = $group['ai_summary'] ?? $group['combined_ai_summary'] ?? '';
                                        if (!empty($group_summary)): 
                                        ?>
                                            <div class="group-summary">
                                                <h6 class="text-success mb-3">
                                                    <i class="fas fa-brain me-2"></i>AI Summary cho toàn bộ nhóm:
                                                </h6>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($group_summary)); ?></p>
                                            </div>
                                        <?php else: ?>
                                            <div class="no-group-summary">
                                                <h6 class="text-warning mb-2">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>Chưa có AI Summary tổng cho nhóm
                                                </h6>
                                                <p class="mb-0 small">Nhóm này chưa có bản tóm tắt tổng hợp. Bạn có thể thêm summary khi upload hoặc chỉnh sửa trong database.</p>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Individual Documents in Group -->
                                        <div class="content-card">
                                            <h6 class="text-primary mb-3">
                                                <i class="fas fa-list me-2"></i>Các văn bản trong nhóm (<?php echo count($group['documents']); ?>):
                                            </h6>
                                            
                                            <?php if (!empty($group['documents'])): ?>
                                                <?php foreach ($group['documents'] as $index => $doc): ?>
                                                    <div class="group-document">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-3">
                                                                    <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                                                    <?php echo htmlspecialchars($doc['title']); ?>
                                                                </h6>
                                                                
                                                                <div class="row">
                                                                    <div class="col-md-7">
                                                                        <h6 class="text-muted small mb-2">
                                                                            <i class="fas fa-file-alt me-1"></i>Nội dung:
                                                                        </h6>
                                                                        <div class="content-preview small">
                                                                            <?php echo nl2br(htmlspecialchars(substr($doc['content'], 0, 250))); ?>
                                                                            <?php if (strlen($doc['content']) > 250): ?>
                                                                                <span class="text-muted">...</span>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-5">
                                                                        <?php if (!empty($doc['ai_summary'])): ?>
                                                                            <h6 class="text-success small mb-2">
                                                                                <i class="fas fa-brain me-1"></i>AI Summary riêng:
                                                                            </h6>
                                                                            <div class="content-preview small text-success">
                                                                                <?php echo nl2br(htmlspecialchars(substr($doc['ai_summary'], 0, 180))); ?>
                                                                                <?php if (strlen($doc['ai_summary']) > 180): ?>
                                                                                    <span class="text-muted">...</span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <div class="text-muted small">
                                                                                <i class="fas fa-info-circle me-1"></i>Chưa có summary riêng
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="text-end">
                                                                <button class="btn btn-outline-primary btn-sm" onclick="viewDocument(<?php echo htmlspecialchars(json_encode($doc)); ?>)">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mt-3 text-muted small">
                                                            <div class="col-md-4">
                                                                <i class="fas fa-align-left me-1"></i>
                                                                <?php echo number_format(strlen($doc['content'])); ?> ký tự
                                                            </div>
                                                            <div class="col-md-4">
                                                                <i class="fas fa-tasks me-1"></i>
                                                                <?php echo $doc['task_count']; ?> task(s)
                                                            </div>
                                                            <div class="col-md-4">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-center text-muted py-4">
                                                    <i class="fas fa-folder-open fa-3x mb-3"></i>
                                                    <h6>Nhóm này chưa có văn bản nào</h6>
                                                    <p class="small">Có thể do lỗi khi upload hoặc văn bản đã bị xóa</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-folder fa-4x text-muted mb-4"></i>
                                    <h5 class="text-muted">Không có nhóm văn bản nào</h5>
                                    <p class="text-muted">Upload văn bản đa để tạo nhóm mới</p>
                                    <a href="upload.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-upload me-2"></i>Upload nhóm văn bản
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document View Modal -->
    <div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="documentModalLabel">
                        <i class="fas fa-file-text me-2"></i>
                        <span id="documentTitle"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-file-alt me-2"></i>Nội dung văn bản:
                            </h6>
                            <div id="documentContent" class="border rounded p-3" style="max-height: 400px; overflow-y: auto; background: #f8f9fa; line-height: 1.6;"></div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-success mb-3">
                                <i class="fas fa-brain me-2"></i>AI Summary:
                            </h6>
                            <div id="documentSummary" class="border rounded p-3" style="max-height: 400px; overflow-y: auto; background: #e8f5e8; line-height: 1.6;"></div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-12">
                            <h6 class="text-muted mb-3">
                                <i class="fas fa-info-circle me-2"></i>Thông tin chi tiết:
                            </h6>
                            <div id="documentInfo"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Đóng
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle group expansion
        function toggleGroup(groupId) {
            const content = document.getElementById(`group-content-${groupId}`);
            const btn = document.getElementById(`expand-btn-${groupId}`);
            
            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                btn.classList.remove('expanded');
            } else {
                content.classList.add('expanded');
                btn.classList.add('expanded');
            }
        }

        // View document in modal
        function viewDocument(doc) {
            document.getElementById('documentTitle').textContent = doc.title;
            document.getElementById('documentContent').innerHTML = doc.content.replace(/\n/g, '<br>');
            
            const summaryContent = doc.ai_summary && doc.ai_summary.trim() 
                ? doc.ai_summary.replace(/\n/g, '<br>')
                : '<span class="text-muted"><i class="fas fa-info-circle me-2"></i>Chưa có AI Summary cho văn bản này</span>';
            
            document.getElementById('documentSummary').innerHTML = summaryContent;
            
            // Create document info
            const wordCount = doc.content.split(/\s+/).length;
            const charCount = doc.content.length;
            const createdDate = new Date(doc.created_at).toLocaleString('vi-VN');
            
            const info = `
                <div class="row">
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center p-3">
                                <i class="fas fa-tag fa-2x text-primary mb-2"></i>
                                <h6 class="card-title">Loại</h6>
                                <p class="card-text">${doc.type === 'multi' ? 'Đa văn bản' : 'Đơn văn bản'}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center p-3">
                                <i class="fas fa-align-left fa-2x text-success mb-2"></i>
                                <h6 class="card-title">Ký tự</h6>
                                <p class="card-text">${charCount.toLocaleString()}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body text-center p-3">
                                <i class="fas fa-font fa-2x text-info mb-2"></i>
                                <h6 class="card-title">Từ</h6>
                                <p class="card-text">${wordCount.toLocaleString()}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center p-3">
                                <i class="fas fa-calendar fa-2x text-warning mb-2"></i>
                                <h6 class="card-title">Tạo lúc</h6>
                                <p class="card-text small">${createdDate}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('documentInfo').innerHTML = info;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('documentModal')).show();
        }

        // Delete document with confirmation
        function deleteDocument(id, title) {
            if (confirm(`Bạn có chắc chắn muốn xóa văn bản "${title}"?\n\nHành động này sẽ xóa luôn các task liên quan và không thể hoàn tác.`)) {
                window.location.href = `documents.php?delete=${id}`;
            }
        }

        // Delete group with confirmation
        function deleteGroup(id, title) {
            if (confirm(`Bạn có chắc chắn muốn xóa nhóm "${title}"?\n\nHành động này sẽ xóa:\n- Tất cả văn bản trong nhóm\n- Tất cả task liên quan\n- Không thể hoàn tác\n\nVẫn tiếp tục?`)) {
                window.location.href = `documents.php?delete_group=${id}`;
            }
        }

        // Auto-expand first group when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Auto dismiss alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const alertInstance = new bootstrap.Alert(alert);
                    alertInstance.close();
                });
            }, 5000);

            // Auto-expand first group if exists
            const firstGroup = document.querySelector('[id^="group-content-"]');
            if (firstGroup) {
                const groupId = firstGroup.id.split('-')[2];
                const expandBtn = document.getElementById(`expand-btn-${groupId}`);
                if (expandBtn) {
                    firstGroup.classList.add('expanded');
                    expandBtn.classList.add('expanded');
                }
            }

            // Add search functionality
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>