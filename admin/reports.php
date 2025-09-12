<?php
require_once '../includes/auth.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Direct database connection for statistics
$conn = new mysqli('localhost', 'root', '', 'text_labeling_system');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get basic statistics directly
function getBasicStats($conn) {
    $stats = [];
    
    // Total users
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stats['total_users'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total documents  
    $result = $conn->query("SELECT COUNT(*) as count FROM documents");
    $stats['total_documents'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total labelings
    $result = $conn->query("SELECT COUNT(*) as count FROM labelings");
    $stats['total_labelings'] = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Total groups (if table exists)
    $tableExists = $conn->query("SHOW TABLES LIKE 'document_groups'");
    if ($tableExists && $tableExists->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as count FROM document_groups");
        $stats['total_groups'] = $result ? $result->fetch_assoc()['count'] : 0;
    } else {
        $stats['total_groups'] = 0;
    }
    
    // User roles
    $stats['user_roles'] = [];
    $result = $conn->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['user_roles'][$row['role']] = $row['count'];
        }
    }
    
    // Labeling status
    $stats['labeling_status'] = [];
    $result = $conn->query("SELECT status, COUNT(*) as count FROM labelings GROUP BY status");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['labeling_status'][$row['status']] = $row['count'];
        }
    }
    
    // Group status (if table exists)
    $stats['group_status'] = [];
    if ($tableExists && $tableExists->num_rows > 0) {
        $result = $conn->query("SELECT status, COUNT(*) as count FROM document_groups GROUP BY status");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $stats['group_status'][$row['status']] = $row['count'];
            }
        }
    }
    
    return $stats;
}

$stats = getBasicStats($conn);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="enhanced_upload.php">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Enhanced Upload
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>Quản lý người dùng
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Báo cáo
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-10 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-chart-bar text-info me-2"></i>Báo cáo hệ thống
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData('all')">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt"></i> Làm mới
                    </button>
                </div>
            </div>

            <!-- Alert if no data -->
            <?php if ($stats['total_users'] == 0 && $stats['total_documents'] == 0 && $stats['total_labelings'] == 0): ?>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Thông báo:</strong> Hệ thống chưa có dữ liệu. Hãy thêm người dùng và upload văn bản để xem báo cáo.
            </div>
            <?php endif; ?>

            <!-- Statistics Overview -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Tổng người dùng
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['total_users']); ?>
                                    </div>
                                    <?php if (!empty($stats['user_roles'])): ?>
                                    <div class="small text-muted">
                                        <?php foreach ($stats['user_roles'] as $role => $count): ?>
                                            <?php echo ucfirst($role); ?>: <?php echo $count; ?> 
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                        Tổng văn bản
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['total_documents']); ?>
                                    </div>
                                    <div class="small text-muted">Đã upload vào hệ thống</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-text fa-2x text-gray-300"></i>
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
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['total_groups']); ?>
                                    </div>
                                    <?php if (!empty($stats['group_status'])): ?>
                                    <div class="small text-muted">
                                        <?php foreach ($stats['group_status'] as $status => $count): ?>
                                            <?php echo ucfirst($status); ?>: <?php echo $count; ?> 
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
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
                                        Tổng gán nhãn
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['total_labelings']); ?>
                                    </div>
                                    <?php if (!empty($stats['labeling_status'])): ?>
                                    <div class="small text-muted">
                                        <?php foreach ($stats['labeling_status'] as $status => $count): ?>
                                            <?php echo ucfirst($status); ?>: <?php echo $count; ?> 
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tags fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <?php if ($stats['total_labelings'] > 0 || $stats['total_users'] > 0): ?>
            <div class="row">
                <?php if (!empty($stats['labeling_status'])): ?>
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Trạng thái gán nhãn</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="labelingChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($stats['user_roles'])): ?>
                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Phân bố người dùng</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="userRoleChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Thao tác nhanh</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <button class="btn btn-primary btn-block" onclick="exportData('users')">
                                        <i class="fas fa-users me-2"></i>Export Users
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-success btn-block" onclick="exportData('documents')">
                                        <i class="fas fa-file-text me-2"></i>Export Documents
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-info btn-block" onclick="exportData('labelings')">
                                        <i class="fas fa-tags me-2"></i>Export Labelings
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-warning btn-block" onclick="exportData('groups')">
                                        <i class="fas fa-copy me-2"></i>Export Groups
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Hoạt động gần đây</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get recent activities
                            $activities = [];
                            
                            // Recent documents
                            $result = $conn->query("
                                SELECT 'Upload văn bản' as activity, d.title, u.username, d.created_at as time
                                FROM documents d 
                                JOIN users u ON d.uploaded_by = u.id 
                                ORDER BY d.created_at DESC 
                                LIMIT 5
                            ");
                            if ($result) {
                                while ($row = $result->fetch_assoc()) {
                                    $activities[] = $row;
                                }
                            }
                            
                            // Recent completed labelings
                            $result = $conn->query("
                                SELECT 'Hoàn thành gán nhãn' as activity, d.title, u.username, l.completed_at as time
                                FROM labelings l
                                JOIN users u ON l.labeler_id = u.id
                                LEFT JOIN documents d ON l.document_id = d.id
                                WHERE l.status = 'completed' AND l.completed_at IS NOT NULL
                                ORDER BY l.completed_at DESC 
                                LIMIT 5
                            ");
                            if ($result) {
                                while ($row = $result->fetch_assoc()) {
                                    $activities[] = $row;
                                }
                            }
                            
                            // Sort by time
                            usort($activities, function($a, $b) {
                                return strtotime($b['time']) - strtotime($a['time']);
                            });
                            ?>
                            
                            <?php if (!empty($activities)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Hoạt động</th>
                                            <th>Tiêu đề</th>
                                            <th>Người thực hiện</th>
                                            <th>Thời gian</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($activities, 0, 10) as $activity): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php echo strpos($activity['activity'], 'Upload') !== false ? 'primary' : 'success'; ?>">
                                                    <?php echo $activity['activity']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($activity['title'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($activity['time'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-clock fa-3x mb-3"></i>
                                <p>Chưa có hoạt động nào</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }

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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function exportData(type) {
    window.open(`export.php?type=${type}`, '_blank');
}

// Charts
<?php if (!empty($stats['labeling_status'])): ?>
// Labeling Status Chart
const ctx1 = document.getElementById('labelingChart');
if (ctx1) {
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_keys($stats['labeling_status'])); ?>,
            datasets: [{
                label: 'Số lượng',
                data: <?php echo json_encode(array_values($stats['labeling_status'])); ?>,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}
<?php endif; ?>

<?php if (!empty($stats['user_roles'])): ?>
// User Role Chart
const ctx2 = document.getElementById('userRoleChart');
if (ctx2) {
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_map('ucfirst', array_keys($stats['user_roles']))); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($stats['user_roles'])); ?>,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
<?php endif; ?>
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?>