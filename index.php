<?php
// Prevent redirect loops by checking if we're already on the correct page
$current_page = basename($_SERVER['PHP_SELF']);

// Only start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Not logged in, redirect to login
    if ($current_page !== 'login.php') {
        header('Location: login.php');
        exit();
    }
} else {
    // User is logged in, redirect based on role only if we're on index.php
    if ($current_page === 'index.php') {
        switch ($_SESSION['role']) {
            case 'admin':
                header('Location: admin/dashboard.php');
                exit();
            case 'labeler':
                header('Location: labeler/dashboard.php');
                exit();
            case 'reviewer':
                header('Location: reviewer/dashboard.php');
                exit();
            default:
                // Invalid role, logout
                session_destroy();
                header('Location: login.php');
                exit();
        }
    }
}

// If we reach here and user is logged in but on index.php, show a simple page
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    $dashboard_url = '';
    switch ($role) {
        case 'admin':
            $dashboard_url = 'admin/dashboard.php';
            break;
        case 'labeler':
            $dashboard_url = 'labeler/dashboard.php';
            break;
        case 'reviewer':
            $dashboard_url = 'reviewer/dashboard.php';
            break;
    }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Text Labeling System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .welcome-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
            text-align: center;
            max-width: 500px;
        }
    </style>
</head>
<body>
    <div class="welcome-card">
        <h1 class="mb-4">
            <i class="fas fa-tags text-primary"></i>
            Text Labeling System
        </h1>
        <p class="mb-4">Chào mừng, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?>!</strong></p>
        <a href="<?php echo $dashboard_url; ?>" class="btn btn-primary btn-lg">
            <i class="fas fa-tachometer-alt me-2"></i>Vào Dashboard
        </a>
        <br><br>
        <a href="logout.php" class="btn btn-outline-secondary">
            <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
        </a>
    </div>
</body>
</html>
<?php
} else {
    // Not logged in, show login form or redirect
    header('Location: login.php');
    exit();
}
?>