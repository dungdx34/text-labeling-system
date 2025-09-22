<?php
session_start();

// Nếu chưa đăng nhập, chuyển đến trang login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirect dựa trên vai trò
switch ($_SESSION['role']) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'labeler':
        header('Location: labeler/dashboard.php');
        break;
    case 'reviewer':
        header('Location: reviewer/dashboard.php');
        break;
    default:
        // Nếu role không hợp lệ, logout và redirect về login
        session_destroy();
        header('Location: login.php?error=invalid_role');
        break;
}
exit();
?>