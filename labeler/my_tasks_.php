<?php
session_start();

// Include database and functions
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'labeler') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';

try {
    // Use simple functions (no class)
    $tasks = getLabelerTasks($user_id);
    $stats = getLabelerStats($user_id);
    
} catch (Exception $e) {
    $error_message = "Lỗi khi tải dữ liệu: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="my_tasks.php">
                                <i class="fas fa-tasks me-2"></i>My Tasks
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="labeling.php">
                                <i class="fas fa-edit me-2"></i>Single Labeling
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="multi_labeling.php">
                                <i class="fas fa-copy me-2"></i>Multi Labeling
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tasks me-2 text-primary"></i>
                        My Tasks
                    </h1>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['pending'] ?? 0; ?></h4>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['in_progress'] ?? 0; ?></h4>
                                <p class="mb-0">In Progress</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['completed'] ?? 0; ?></h4>
                                <p class="mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['total_assigned'] ?? 0; ?></h4>
                                <p class="mb-0">Total</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tasks List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Assigned Tasks
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No tasks assigned</h5>
                                <p class="text-muted">You don't have any tasks assigned yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Documents</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tasks as $task): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($task['description'] ?? ''); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $task['group_type'] === 'multi' ? 'info' : 'secondary'; ?>">
                                                        <?php echo $task['group_type'] === 'multi' ? 'Multi' : 'Single'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $task['status'] === 'pending' ? 'warning' : 
                                                            ($task['status'] === 'in_progress' ? 'info' : 
                                                            ($task['status'] === 'completed' ? 'success' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $task['document_count'] ?? 0; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($task['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($task['status'] === 'pending' || $task['status'] === 'in_progress'): ?>
                                                        <?php if ($task['group_type'] === 'single'): ?>
                                                            <a href="labeling.php?group_id=<?php echo $task['group_id']; ?>" 
                                                               class="btn btn-primary btn-sm">
                                                                <i class="fas fa-edit me-1"></i>Label
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="multi_labeling.php?group_id=<?php echo $task['group_id']; ?>" 
                                                               class="btn btn-info btn-sm">
                                                                <i class="fas fa-copy me-1"></i>Multi Label
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-success">
                                                            <i class="fas fa-check me-1"></i>Completed
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>