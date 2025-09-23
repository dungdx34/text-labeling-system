<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is admin
requireAdmin();

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
        
        // Log activity
        logActivity($db, $_SESSION['user_id'], 'delete_document', 'document', $doc_id);
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
        
        // Log activity
        logActivity($db, $_SESSION['user_id'], 'delete_document_group', 'document_group', $group_id);
    } catch (Exception $e) {
        $error = "Không thể xóa nhóm văn bản: " . $e->getMessage();
    }
}

// Get filter parameters
$filter_type = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query for documents
$where_conditions = [];
$params = [];

if ($filter_type === 'single') {
    $where_conditions[] = "d.type = 'single'";
} elseif ($filter_type === 'multi') {
    $where_conditions[] = "d.type = 'multi'";
}

if (!empty($search)) {
    $where_conditions[] = "(d.title LIKE ? OR d.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get single documents and multi documents
$query = "SELECT d.*, 
    u.full_name as created_by_name,
    dg.title as group_title,
    (SELECT COUNT(*) FROM labeling_tasks WHERE document_id = d.id) as task_count
FROM documents d
LEFT JOIN users u ON d.created_by = u.id
LEFT JOIN document_groups dg ON d.group_id = dg.id
$where_clause
ORDER BY d.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get document groups
$group_query = "SELECT dg.*, 
    u.full_name as created_by_name,
    COUNT(d.id) as document_count,
    (SELECT COUNT(*) FROM labeling_tasks WHERE group_id = dg.id) as task_count
FROM document_groups dg
LEFT JOIN users u ON dg.created_by = u.id
LEFT JOIN documents d ON dg.id = d.group_id
GROUP BY dg.id
ORDER BY dg.created_at DESC";

$stmt = $db->prepare($group_query);
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stats_query = "SELECT 
    COUNT(*) as total_docs,
    COUNT(CASE WHEN type = 'single' THEN 1 END) as single_docs,
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
            font-family: 'Segoe UI', sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
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
        }
        .document-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .document-type-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .stats-mini {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        .content-preview {
            max-height: 100px;
            overflow: hidden;
            position: relative;
        }
        .content-preview::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(transparent, white);
        }
        .group-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
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
                        <a href="dashboard.php" class="nav-link mb-2">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="users.php" class="nav-link mb-2">
                            <i class="fas fa-users me-2"></i>Quản lý Users
                        </a>
                        <a href="upload.php" class="nav-link mb-2">
                            <i class="fas fa-upload me-2"></i>Upload Dữ liệu
                        </a>
                        <a href="documents.php" class="nav-link active mb-2">
                            <i class="fas fa-file-text me-2"></i>Quản lý Văn bản
                        </a>
                        <a href="tasks.php" class="nav-link mb-2">
                            <i class="fas fa-tasks me-2"></i>Quản lý Tasks
                        </a>
                        <a href="reports.php" class="nav-link mb-2">
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2>Quản lý Văn bản</h2>
                            <p class="text-muted">Xem và quản lý các văn bản đã upload</p>
                        </div>
                        <a href="upload.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Upload Văn bản mới
                        </a>
                    </div>

                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <h4 class="text-primary"><?php echo $stats['total_docs']; ?></h4>
                                <small class="text-muted">Tổng văn bản</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <h4 class="text-success"><?php echo $stats['single_docs']; ?></h4>
                                <small class="text-muted">Văn bản đơn</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <h4 class="text-info"><?php echo $stats['multi_docs']; ?></h4>
                                <small class="text-muted">Văn bản đa</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <h4 class="text-warning"><?php echo $group_stats['total_groups']; ?></h4>
                                <small class="text-muted">Nhóm văn bản</small>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="content-card">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <select class="form-select" name="type">
                                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>Tất cả loại</option>
                                    <option value="single" <?php echo $filter_type === 'single' ? 'selected' : ''; ?>>Văn bản đơn</option>
                                    <option value="multi" <?php echo $filter_type === 'multi' ? 'selected' : ''; ?>>Văn bản đa</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="search" placeholder="Tìm kiếm theo tiêu đề hoặc nội dung..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-search me-2"></i>Lọc
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-4">
                        <li class="nav-item">
                            <button class="nav-link active" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents-content">
                                <i class="fas fa-file-text me-2"></i>Văn bản (<?php echo count($documents); ?>)
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="groups-tab" data-bs-toggle="tab" data-bs-target="#groups-content">
                                <i class="fas fa-folder me-2"></i>Nhóm văn bản (<?php echo count($groups); ?>)
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Documents Tab -->
                        <div class="tab-pane fade show active" id="documents-content">
                            <?php if (!empty($documents)): ?>
                                <?php foreach ($documents as $doc): ?>
                                    <div class="document-card">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <h5 class="mb-0 me-3"><?php echo htmlspecialchars($doc['title']); ?></h5>
                                                    <span class="document-type-badge <?php echo $doc['type'] === 'single' ? 'bg-primary text-white' : 'bg-info text-white'; ?>">
                                                        <?php echo $doc['type'] === 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                                                    </span>
                                                    <?php if ($doc['group_title']): ?>
                                                        <span class="badge bg-secondary ms-2">
                                                            <i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($doc['group_title']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <h6 class="text-muted">Nội dung:</h6>
                                                        <div class="content-preview">
                                                            <?php echo nl2br(htmlspecialchars(substr($doc['content'], 0, 300))); ?>
                                                            <?php if (strlen($doc['content']) > 300): ?>
                                                                <span class="text-muted">...</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <?php if ($doc['ai_summary']): ?>
                                                            <h6 class="text-muted">AI Summary:</h6>
                                                            <div class="content-preview">
                                                                <?php echo nl2br(htmlspecialchars(substr($doc['ai_summary'], 0, 200))); ?>
                                                                <?php if (strlen($doc['ai_summary']) > 200): ?>
                                                                    <span class="text-muted">...</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <button class="btn btn-sm btn-outline-primary mb-2" onclick="viewDocument(<?php echo htmlspecialchars(json_encode($doc)); ?>)">
                                                    <i class="fas fa-eye"></i> Xem
                                                </button>
                                                <br>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteDocument(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['title']); ?>')">
                                                    <i class="fas fa-trash"></i> Xóa
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
                                    <i class="fas fa-file-text fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Không có văn bản nào</h5>
                                    <p class="text-muted">Hãy upload văn bản mới để bắt đầu</p>
                                    <a href="upload.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Upload ngay
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Groups Tab -->
                        <div class="tab-pane fade" id="groups-content">
                            <?php if (!empty($groups)): ?>
                                <?php foreach ($groups as $group): ?>
                                    <div class="group-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-2"><?php echo htmlspecialchars($group['title']); ?></h5>
                                                <p class="mb-0 opacity-75"><?php echo htmlspecialchars($group['description']); ?></p>
                                            </div>
                                            <button class="btn btn-outline-light btn-sm" onclick="deleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['title']); ?>')">
                                                <i class="fas fa-trash"></i> Xóa nhóm
                                            </button>
                                        </div>
                                        <div class="row mt-3 text-sm">
                                            <div class="col-md-3">
                                                <i class="fas fa-files me-1"></i>
                                                <?php echo $group['document_count']; ?> văn bản
                                            </div>
                                            <div class="col-md-3">
                                                <i class="fas fa-tasks me-1"></i>
                                                <?php echo $group['task_count']; ?> task(s)
                                            </div>
                                            <div class="col-md-3">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($group['created_by_name'] ?? 'Unknown'); ?>
                                            </div>
                                            <div class="col-md-3">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($group['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="content-card">
                                        <h6 class="text-primary mb-3">AI Summary cho nhóm:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($group['ai_summary'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-folder fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Không có nhóm văn bản nào</h5>
                                    <p class="text-muted">Upload văn bản đa để tạo nhóm</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document View Modal -->
    <div class="modal fade" id="documentModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-primary">Nội dung văn bản:</h6>
                            <div id="documentContent" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; background: #f8f9fa;"></div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-success">AI Summary:</h6>
                            <div id="documentSummary" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; background: #e8f5e8;"></div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <h6 class="text-muted">Thông tin:</h6>
                            <div id="documentInfo"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDocument(doc) {
            document.getElementById('documentTitle').textContent = doc.title;
            document.getElementById('documentContent').innerHTML = doc.content.replace(/\n/g, '<br>');
            document.getElementById('documentSummary').innerHTML = (doc.ai_summary || 'Không có summary').replace(/\n/g, '<br>');
            
            const info = `
                <div class="row">
                    <div class="col-md-3"><strong>Loại:</strong> ${doc.type === 'single' ? 'Đơn văn bản' : 'Đa văn bản'}</div>
                    <div class="col-md-3"><strong>Ký tự:</strong> ${doc.content.length.toLocaleString()}</div>
                    <div class="col-md-3"><strong>Từ:</strong> ${doc.content.split(/\s+/).length.toLocaleString()}</div>
                    <div class="col-md-3"><strong>Tạo lúc:</strong> ${new Date(doc.created_at).toLocaleString('vi-VN')}</div>
                </div>
            `;
            document.getElementById('documentInfo').innerHTML = info;
            
            new bootstrap.Modal(document.getElementById('documentModal')).show();
        }

        function deleteDocument(id, title) {
            if (confirm(`Bạn có chắc chắn muốn xóa văn bản "${title}"?\n\nHành động này sẽ xóa luôn các task liên quan.`)) {
                window.location.href = `?delete=${id}`;
            }
        }

        function deleteGroup(id, title) {
            if (confirm(`Bạn có chắc chắn muốn xóa nhóm "${title}"?\n\nHành động này sẽ xóa tất cả văn bản trong nhóm và các task liên quan.`)) {
                window.location.href = `?delete_group=${id}`;
            }
        }
    </script>
</body>
</html>