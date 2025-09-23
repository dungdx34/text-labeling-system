<?php
// Authentication and session management

function checkAuth($required_role = null) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    if ($required_role && $_SESSION['role'] !== $required_role) {
        return false;
    }
    
    return true;
}

function requireAuth($required_role = null) {
    if (!checkAuth($required_role)) {
        header('Location: ../login.php');
        exit();
    }
}

function requireAdmin() {
    requireAuth('admin');
}

function requireLabeler() {
    requireAuth('labeler');
}

function requireReviewer() {
    requireAuth('reviewer');
}

function getUserInfo() {
    if (!checkAuth()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role']
    ];
}

function logActivity($db, $user_id, $action, $entity_type = null, $entity_id = null, $details = null) {
    try {
        $query = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                 VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address, :user_agent)";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':entity_type', $entity_type);
        $stmt->bindParam(':entity_id', $entity_id);
        $stmt->bindParam(':details', json_encode($details));
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
        
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

function redirectByRole($role) {
    switch ($role) {
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
            header('Location: login.php');
    }
    exit();
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>