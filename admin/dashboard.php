<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/enhanced_functions.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$functions = new Functions();
$ef = new EnhancedFunctions();

// Get dashboard statistics
$stats = $functions->getDashboardStats();
$enhancedStats = $ef->getLabelingStats();

// Get recent activities
$recentUploads = $ef->getAllDocumentGroups(null, 5, 0);
$recentLabelers = $functions->getActiveLabelers();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="enhanced_upload.php">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Enhanced Upload
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload.php">
                            <i class="fas fa-upload me-2"></i>Upload Cơ bản
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
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Cài đặt
                        </a>
                    </li>
                </ul>
                
                <hr>
                
                <div class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>Quản lý nhanh</span>
                </div>
                <ul class="nav flex-column mb-2">
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="assignRandomTask()">
                            <i class="fas fa-random me-2"></i>Giao việc ngẫu nhiên
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="exportData()">
                            <i class="fas fa-download me-2"></i>Xuất dữ liệu
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-10 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-tachometer-alt text-primary me-2"></i>Admin Dashboard
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshStats()">
                            <i class="fas fa-sync-alt"></i> Làm mới
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="showQuickActions()">
                        <i class="fas fa-bolt"></i> Thao tác nhanh
                    </button>
                </div>
            </div>

            <!-- Welcome Message -->
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Chào mừng trở lại!</strong> Hôm nay bạn có <?php echo $stats['pending_tasks'] ?? 0; ?> nhiệm vụ đang chờ xử lý.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Tổng văn bản
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['total_documents'] ?? 0; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-text fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Đã hoàn thành
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['completed_tasks'] ?? 0; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Nhóm văn bản
                                    </div>
                                    <div class="row no-gutters align-items-center">
                                        <div class="col-auto">
                                            <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">
                                                <?php echo $enhancedStats['success'] ? $enhancedStats['data']['groups']['total_groups'] : 0; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-copy fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Labeler hoạt động
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo count($recentLabelers['data'] ?? []); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Row -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-bolt me-2"></i>Thao tác nhanh
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <a href="enhanced_upload.php" class="btn btn-primary btn-block btn-lg">
                                        <i class="fas fa-cloud-upload-alt me-2"></i>
                                        Upload Văn bản Mới
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="users.php" class="btn btn-success btn-block btn-lg">
                                        <i class="fas fa-user-plus me-2"></i>
                                        Thêm Labeler
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <button class="btn btn-info btn-block btn-lg" onclick="viewPendingTasks()">
                                        <i class="fas fa-tasks me-2"></i>
                                        Xem Nhiệm vụ Chờ
                                    </button>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="reports.php" class="btn btn-warning btn-block btn-lg">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Xem Báo cáo
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-info-circle me-2"></i>Hướng dẫn nhanh
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="upload-guide">
                                <h6 class="text-primary">
                                    <i class="fas fa-file-text me-2"></i>Văn bản đơn
                                </h6>
                                <p class="small text-muted">
                                    Upload 1 văn bản + bản tóm tắt AI tương ứng. Phù hợp cho các văn bản độc lập.
                                </p>
                                
                                <h6 class="text-success">
                                    <i class="fas fa-copy me-2"></i>Đa văn bản
                                </h6>
                                <p class="small text-muted">
                                    Upload nhiều văn bản cùng chủ đề + 1 bản tóm tắt chung. Phù hợp cho phân tích tổng hợp.
                                </p>
                                
                                <div class="mt-3">
                                    <a href="#" class="btn btn-sm btn-outline-primary" onclick="showDetailedGuide()">
                                        <i class="fas fa-book me-1"></i>Xem chi tiết
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-clock me-2"></i>Hoạt động gần đây
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($recentUploads['success'] && !empty($recentUploads['data'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Tiêu đề</th>
                                                <th>Loại</th>
                                                <th>Trạng thái</th>
                                                <th>Ngày tạo</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($recentUploads['data'], 0, 5) as $group): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars(substr($group['title'], 0, 40)); ?></strong>
                                                    <?php if (strlen($group['title']) > 40) echo '...'; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $group['group_type'] === 'single' ? 'primary' : 'success'; ?>">
                                                        <?php echo $group['group_type'] === 'single' ? 'Đơn' : 'Đa'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo 
                                                        $group['status'] === 'completed' ? 'success' : 
                                                        ($group['status'] === 'in_progress' ? 'warning' : 'secondary'); 
                                                    ?>">
                                                        <?php echo ucfirst($group['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($group['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewGroupDetails(<?php echo $group['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($group['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-outline-success" onclick="assignGroup(<?php echo $group['id']; ?>)">
                                                        <i class="fas fa-user-plus"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p>Chưa có hoạt động nào gần đây</p>
                                    <a href="enhanced_upload.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Tạo nhiệm vụ đầu tiên
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-users me-2"></i>Labeler hoạt động
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($recentLabelers['success'] && !empty($recentLabelers['data'])): ?>
                                <?php foreach (array_slice($recentLabelers['data'], 0, 5) as $labeler): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar-sm me-3">
                                        <div class="avatar-title bg-primary rounded-circle">
                                            <?php echo strtoupper(substr($labeler['username'], 0, 1)); ?>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($labeler['username']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo $labeler['active_tasks'] ?? 0; ?> nhiệm vụ đang làm
                                        </small>
                                    </div>
                                    <div>
                                        <span class="badge bg-success">Online</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-user-slash fa-2x mb-2"></i>
                                    <p class="small">Không có labeler hoạt động</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- System Status -->
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-server me-2"></i>Trạng thái hệ thống
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Database</span>
                                <span class="badge bg-success">Online</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Upload Service</span>
                                <span class="badge bg-success">Active</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Auto-save</span>
                                <span class="badge bg-success">Running</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small">Last Update</span>
                                <span class="small text-muted"><?php echo date('H:i:s'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="groupDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết nhóm văn bản</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="groupDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Giao nhiệm vụ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Chọn Labeler:</label>
                    <select class="form-select" id="labelerSelect">
                        <option value="">-- Chọn labeler --</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="confirmAssign()">Giao việc</button>
            </div>
        </div>
    </div>
</div>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }

.avatar-sm { width: 40px; height: 40px; }
.avatar-title {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 48px 0 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
}

.sidebar-sticky {
    position: relative;
    top: 0;
    height: calc(100vh - 48px);
    padding-top: .5rem;
    overflow-x: hidden;
    overflow-y: auto;
}

.nav-link {
    color: #333;
    padding: 10px 20px;
    border-radius: 0;
    transition: all 0.3s ease;
}

.nav-link:hover {
    background: #f8f9fc;
    color: #5a5c69;
}

.nav-link.active {
    background: #4e73df;
    color: white !important;
}

.btn-block { width: 100%; }
</style>

<script>
function viewGroupDetails(groupId) {
    fetch(`get_group_details.php?action=get_group&group_id=${groupId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const group = data.data;
                document.getElementById('groupDetailsContent').innerHTML = `
                    <h6>Thông tin cơ bản</h6>
                    <p><strong>Tiêu đề:</strong> ${group.title}</p>
                    <p><strong>Loại:</strong> ${group.group_type}</p>
                    <p><strong>Mô tả:</strong> ${group.description || 'Không có'}</p>
                    <p><strong>Số văn bản:</strong> ${group.documents.length}</p>
                    
                    <h6>Bản tóm tắt AI:</h6>
                    <div class="bg-light p-3 rounded">${group.ai_summary}</div>
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('groupDetailsModal'));
                modal.show();
            }
        });
}

function assignGroup(groupId) {
    // Load labelers
    fetch('get_group_details.php?action=get_labelers')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('labelerSelect');
                select.innerHTML = '<option value="">-- Chọn labeler --</option>';
                
                data.data.forEach(labeler => {
                    select.innerHTML += `<option value="${labeler.id}">${labeler.username} (${labeler.active_tasks} nhiệm vụ)</option>`;
                });
                
                const modal = new bootstrap.Modal(document.getElementById('assignModal'));
                modal.show();
                
                // Store group ID for later use
                document.getElementById('assignModal').dataset.groupId = groupId;
            }
        });
}

function confirmAssign() {
    const groupId = document.getElementById('assignModal').dataset.groupId;
    const labelerId = document.getElementById('labelerSelect').value;
    
    if (!labelerId) {
        alert('Vui lòng chọn labeler!');
        return;
    }
    
    fetch('get_group_details.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'assign_labeler',
            group_id: parseInt(groupId),
            labeler_id: parseInt(labelerId)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Giao việc thành công!');
            location.reload();
        } else {
            alert('Lỗi: ' + data.message);
        }
    });
}

function refreshStats() {
    location.reload();
}

function viewPendingTasks() {
    // Implement view pending tasks
    window.location.href = 'reports.php?filter=pending';
}

function assignRandomTask() {
    if (confirm('Giao ngẫu nhiên nhiệm vụ chờ cho labeler có ít việc nhất?')) {
        // Implement random assignment logic
        fetch('get_group_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'assign_random' })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
}

function exportData() {
    window.open('export.php', '_blank');
}

function showQuickActions() {
    // Implement quick actions dropdown
    alert('Tính năng đang phát triển!');
}

function showDetailedGuide() {
    window.open('guide.html', '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>