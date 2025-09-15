<?php
// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current user info
$current_user = $_SESSION['username'] ?? 'Guest';
$current_role = $_SESSION['role'] ?? 'guest';
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo ($current_role === 'admin') ? '../admin/dashboard.php' : (($current_role === 'labeler') ? '../labeler/dashboard.php' : '../reviewer/dashboard.php'); ?>">
            <i class="fas fa-tags me-2"></i>
            Text Labeling System
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if ($current_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../admin/dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../admin/users.php">
                            <i class="fas fa-users me-1"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../admin/upload.php">
                            <i class="fas fa-upload me-1"></i>Upload
                        </a>
                    </li>
                <?php elseif ($current_role === 'labeler'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../labeler/dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../labeler/labeling.php">
                            <i class="fas fa-edit me-1"></i>Labeling
                        </a>
                    </li>
                <?php elseif ($current_role === 'reviewer'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../reviewer/dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../reviewer/review.php">
                            <i class="fas fa-check-circle me-1"></i>Review
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($current_user); ?>
                        <span class="badge bg-<?php echo ($current_role === 'admin') ? 'danger' : (($current_role === 'labeler') ? 'primary' : 'success'); ?> ms-1">
                            <?php echo ucfirst($current_role); ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user-edit me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Add top margin to account for fixed navbar -->
<div style="margin-top: 70px;"></div>