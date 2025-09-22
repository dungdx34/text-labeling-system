<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Kiểm tra quyền admin
requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$success_message = '';
$error_message = '';

// Xử lý các actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'delete_document':
                $doc_id = intval($_POST['document_id']);
                $query = "UPDATE documents SET status = 'inactive' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $doc_id);
                $stmt->execute();
                $success_message = 'Xóa văn bản thành công!';
                break;
                
            case 'edit_document':
                $doc_id = intval($_POST['document_id']);
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                $ai_summary = trim($_POST['ai_summary']);
                
                $query = "UPDATE documents SET title = ?, content = ?, ai_summary = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $title);
                $stmt->bindParam(2, $content);
                $stmt->bindParam(3, $ai_summary);
                $stmt->bindParam(4, $doc_id);
                $stmt->execute();
                $success_message = 'Cập nhật văn bản thành công!';
                break;
        }
    } catch (Exception $e) {
        $error_message = 'Lỗi: ' . $e->getMessage();
    }
}

// Lấy danh sách documents với phân trang
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';

try {
    // Build query
    $where_conditions = ["d.status = 'active'"];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(d.title LIKE ? OR d.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($filter_type)) {
        $where_conditions[] = "d.type = ?";
        $params[] = $filter_type;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Count total records
    $count_query = "SELECT COUNT(*) as total FROM documents d WHERE $where_clause";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get documents
    $query = "SELECT d.*, u.full_name as uploaded_by_name 
              FROM documents d 
              JOIN users u ON d.uploaded_by = u.id 
              WHERE $where_clause 
              ORDER BY d.created_at DESC 
              LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy danh sách văn bản: ' . $e->getMessage();
    $documents = [];
    $total_pages = 1;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý văn bản - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
        }
        .document-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .document-card:hover {
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center text-white mb-4">
            <i class="fas fa-tags fa-2x mb-2"></i>
            <h5>Admin Panel</h5>
            <small>Xin chào, <?php echo htmlspecialchars($current_user['full_name']); ?></small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-2"></i>Quản lý người dùng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="upload.php">
                    <i class="fas fa-upload me-2"></i>Upload văn bản
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="documents.php">
                    <i class="fas fa-file-text me-2"></i>Quản lý văn bản
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="assignments.php">
                    <i class="fas fa-tasks me-2"></i>Phân công công việc
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Báo cáo
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
            <h2 class="text-dark">Quản lý văn bản</h2>
            <a href="upload.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Thêm văn bản
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div class="content-card mb-4">
            <form method="GET" class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Tìm theo tiêu đề hoặc nội dung...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Loại văn bản</label>
                    <select class="form-select" name="type">
                        <option value="">Tất cả</option>
                        <option value="single" <?php echo $filter_type == 'single' ? 'selected' : ''; ?>>Đơn văn bản</option>
                        <option value="multi" <?php echo $filter_type == 'multi' ? 'selected' : ''; ?>>Đa văn bản</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Tìm kiếm
                    </button>
                    <a href="documents.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-2"></i>Reset
                    </a>
                </div>
                <div class="col-md-2 text-end">
                    <small class="text-muted">
                        Tìm thấy: <?php echo $total_records; ?> văn bản
                    </small>
                </div>
            </form>
        </div>

        <!-- Documents List -->
        <div class="row">
            <?php if (empty($documents)): ?>
                <div class="col-12">
                    <div class="content-card text-center">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5>Không có văn bản nào</h5>
                        <p class="text-muted">Chưa có văn bản nào được upload hoặc không tìm thấy kết quả phù hợp.</p>
                        <a href="upload.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Upload văn bản đầu tiên
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="document-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($doc['title']); ?></h5>
                                    <small class="text-muted">
                                        Bởi: <?php echo htmlspecialchars($doc['uploaded_by_name']); ?> • 
                                        <?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?>
                                    </small>
                                </div>
                                <span class="badge <?php echo $doc['type'] == 'single' ? 'bg-primary' : 'bg-success'; ?>">
                                    <?php echo $doc['type'] == 'single' ? 'Đơn văn bản' : 'Đa văn bản'; ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="text-primary">Nội dung:</h6>
                                <p class="text-muted mb-2">
                                    <?php echo htmlspecialchars(substr(strip_tags($doc['content']), 0, 150)); ?>...
                                </p>
                                
                                <h6 class="text-success">Tóm tắt AI:</h6>
                                <p class="text-muted">
                                    <?php echo htmlspecialchars(substr(strip_tags($doc['ai_summary']), 0, 100)); ?>...
                                </p>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <i class="fas fa-file-text me-1"></i>
                                    <?php echo number_format(strlen($doc['content'])); ?> ký tự
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-info" onclick="viewDocument(<?php echo htmlspecialchars(json_encode($doc)); ?>)">
                                        <i class="fas fa-eye"></i> Xem
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="editDocument(<?php echo htmlspecialchars(json_encode($doc)); ?>)">
                                        <i class="fas fa-edit"></i> Sửa
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteDocument(<?php echo $doc['id']; ?>)">
                                        <i class="fas fa-trash"></i> Xóa
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($filter_type); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($filter_type); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($filter_type); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <!-- View Document Modal -->
    <div class="modal fade" id="viewDocumentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewDocumentTitle">Chi tiết văn bản</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">Nội dung văn bản:</h6>
                            <div id="viewDocumentContent" class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto;"></div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success">Tóm tắt AI:</h6>
                            <div id="viewDocumentSummary" class="border rounded p-3 bg-success bg-opacity-10" style="max-height: 300px; overflow-y: auto;"></div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <strong>Loại:</strong> <span id="viewDocumentType"></span>
                        </div>
                        <div class="col-md-4">
                            <strong>Upload bởi:</strong> <span id="viewDocumentUploader"></span>
                        </div>
                        <div class="col-md-4">
                            <strong>Ngày tạo:</strong> <span id="viewDocumentDate"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Document Modal -->
    <div class="modal fade" id="editDocumentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chỉnh sửa văn bản</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_document">
                        <input type="hidden" name="document_id" id="editDocumentId">
                        
                        <div class="mb-3">
                            <label class="form-label">Tiêu đề</label>
                            <input type="text" class="form-control" name="title" id="editDocumentTitle" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nội dung</label>
                                    <textarea class="form-control" name="content" id="editDocumentContent" rows="10" required></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tóm tắt AI</label>
                                    <textarea class="form-control" name="ai_summary" id="editDocumentSummary" rows="10" required></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Cập nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDocument(doc) {
            document.getElementById('viewDocumentTitle').textContent = doc.title;
            document.getElementById('viewDocumentContent').textContent = doc.content;
            document.getElementById('viewDocumentSummary').textContent = doc.ai_summary;
            document.getElementById('viewDocumentType').textContent = doc.type === 'single' ? 'Đơn văn bản' : 'Đa văn bản';
            document.getElementById('viewDocumentUploader').textContent = doc.uploaded_by_name;
            
            const date = new Date(doc.created_at);
            document.getElementById('viewDocumentDate').textContent = date.toLocaleDateString('vi-VN') + ' ' + date.toLocaleTimeString('vi-VN');
            
            new bootstrap.Modal(document.getElementById('viewDocumentModal')).show();
        }

        function editDocument(doc) {
            document.getElementById('editDocumentId').value = doc.id;
            document.getElementById('editDocumentTitle').value = doc.title;
            document.getElementById('editDocumentContent').value = doc.content;
            document.getElementById('editDocumentSummary').value = doc.ai_summary;
            
            new bootstrap.Modal(document.getElementById('editDocumentModal')).show();
        }

        function deleteDocument(docId) {
            if (confirm('Bạn có chắc muốn xóa văn bản này? Thao tác này không thể hoàn tác.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_document">
                    <input type="hidden" name="document_id" value="${docId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>