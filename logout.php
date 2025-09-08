<?php
require_once 'includes/auth.php';

$auth = new Auth();
$auth->logout();

// Redirect to login with success message
header('Location: login.php?message=logout_success');
exit();
?>