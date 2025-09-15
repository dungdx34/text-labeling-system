<?php
// Prevent multiple inclusions
if (defined('FUNCTIONS_INCLUDED')) {
    return;
}
define('FUNCTIONS_INCLUDED', true);

/**
 * Simple Functions for Text Labeling System
 * No classes, no conflicts, just simple functions
 */

/**
 * Authentication Functions
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function hasRole($required_role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $required_role;
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

function requireRole($required_role) {
    requireAuth();
    if (!hasRole($required_role)) {
        header('Location: ../login.php?error=insufficient_privileges');
        exit();
    }
}

function getCurrentUser() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * User Management Functions
 */
function getAllUsers($role = null) {
    global $pdo;
    
    try {
        if ($role) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? ORDER BY created_at DESC");
            $stmt->execute([$role]);
        } else {
            $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
        }
        return $stmt->fetchAll();
    } catch (Exception $e) {
        throw new Exception("Error fetching users: " . $e->getMessage());
    }
}

function createUser($username, $password, $full_name, $email, $role = 'labeler') {
    global $pdo;
    
    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, email, role) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$username, $hashed_password, $full_name, $email, $role]);
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        throw new Exception("Error creating user: " . $e->getMessage());
    }
}

/**
 * Document Group Functions
 */
function getDocumentGroup($group_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT dg.*, u.username as uploaded_by_user 
            FROM document_groups dg 
            LEFT JOIN users u ON dg.uploaded_by = u.id 
            WHERE dg.id = ?
        ");
        $stmt->execute([$group_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        throw new Exception("Error fetching document group: " . $e->getMessage());
    }
}

function getDocumentsByGroup($group_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM documents 
            WHERE group_id = ? 
            ORDER BY document_order ASC
        ");
        $stmt->execute([$group_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        throw new Exception("Error fetching documents: " . $e->getMessage());
    }
}

/**
 * Labeler Task Functions
 */
function getLabelerTasks($labeler_id, $status = null) {
    global $pdo;
    
    try {
        $sql = "
            SELECT 
                dg.id as group_id,
                dg.title,
                dg.description,
                dg.group_type,
                dg.status,
                dg.created_at,
                dg.updated_at,
                COUNT(d.id) as document_count,
                u.username as uploaded_by_user,
                l.is_completed,
                l.updated_at as labeling_updated_at
            FROM document_groups dg 
            LEFT JOIN documents d ON dg.id = d.group_id
            LEFT JOIN users u ON dg.uploaded_by = u.id
            LEFT JOIN labelings l ON dg.id = l.group_id AND l.user_id = ?
            WHERE dg.assigned_labeler = ?
        ";
        
        if ($status) {
            $sql .= " AND dg.status = ?";
            $stmt = $pdo->prepare($sql . " GROUP BY dg.id ORDER BY dg.created_at DESC");
            $stmt->execute([$labeler_id, $labeler_id, $status]);
        } else {
            $stmt = $pdo->prepare($sql . " GROUP BY dg.id ORDER BY dg.created_at DESC");
            $stmt->execute([$labeler_id, $labeler_id]);
        }
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        throw new Exception("Error fetching labeler tasks: " . $e->getMessage());
    }
}

function getLabelerStats($labeler_id) {
    global $pdo;
    
    try {
        $stats = [];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM document_groups WHERE assigned_labeler = ?");
        $stmt->execute([$labeler_id]);
        $stats['total_assigned'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM document_groups WHERE assigned_labeler = ? AND status = 'completed'");
        $stmt->execute([$labeler_id]);
        $stats['completed'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM document_groups WHERE assigned_labeler = ? AND status = 'in_progress'");
        $stmt->execute([$labeler_id]);
        $stats['in_progress'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM document_groups WHERE assigned_labeler = ? AND status = 'pending'");
        $stmt->execute([$labeler_id]);
        $stats['pending'] = $stmt->fetch()['count'];
        
        return $stats;
    } catch (Exception $e) {
        throw new Exception("Error fetching labeler stats: " . $e->getMessage());
    }
}

/**
 * Labeling Functions
 */
function saveLabeling($user_id, $group_id, $document_id, $selected_sentences, $text_style, $edited_summary, $labeling_type = 'single', $is_completed = false) {
    global $pdo;
    
    try {
        // Check if labeling exists
        $stmt = $pdo->prepare("SELECT id FROM labelings WHERE user_id = ? AND group_id = ?");
        $stmt->execute([$user_id, $group_id]);
        $existing = $stmt->fetch();
        
        $current_time = date('Y-m-d H:i:s');
        
        if ($existing) {
            // Update existing labeling
            $stmt = $pdo->prepare("
                UPDATE labelings SET 
                    selected_sentences = ?,
                    text_style = ?,
                    edited_summary = ?,
                    labeling_type = ?,
                    is_completed = ?,
                    updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($selected_sentences),
                $text_style,
                $edited_summary,
                $labeling_type,
                $is_completed ? 1 : 0,
                $current_time,
                $existing['id']
            ]);
            
            $labeling_id = $existing['id'];
        } else {
            // Create new labeling
            $stmt = $pdo->prepare("
                INSERT INTO labelings (
                    user_id, document_id, group_id, selected_sentences, 
                    text_style, edited_summary, labeling_type, is_completed
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $document_id,
                $group_id,
                json_encode($selected_sentences),
                $text_style,
                $edited_summary,
                $labeling_type,
                $is_completed ? 1 : 0
            ]);
            
            $labeling_id = $pdo->lastInsertId();
        }
        
        // Update group status if completing
        if ($is_completed) {
            $stmt = $pdo->prepare("UPDATE document_groups SET status = 'completed' WHERE id = ?");
            $stmt->execute([$group_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE document_groups SET status = 'in_progress' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$group_id]);
        }
        
        return $labeling_id;
        
    } catch (Exception $e) {
        throw new Exception("Error saving labeling: " . $e->getMessage());
    }
}

function getLabeling($group_id, $user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM labelings WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        throw new Exception("Error fetching labeling: " . $e->getMessage());
    }
}

/**
 * Review Functions
 */
function getReviewerTasks($reviewer_id, $status = null) {
    global $pdo;
    
    try {
        $sql = "
            SELECT 
                dg.id as group_id,
                dg.title,
                dg.description,
                dg.group_type,
                dg.status as group_status,
                dg.created_at,
                dg.updated_at,
                COUNT(d.id) as document_count,
                u1.username as uploaded_by_user,
                u2.username as labeler_user,
                l.id as labeling_id,
                l.is_completed,
                l.updated_at as labeling_updated_at,
                r.review_status,
                r.reviewed_at
            FROM document_groups dg 
            LEFT JOIN documents d ON dg.id = d.group_id
            LEFT JOIN users u1 ON dg.uploaded_by = u1.id
            LEFT JOIN users u2 ON dg.assigned_labeler = u2.id
            LEFT JOIN labelings l ON dg.id = l.group_id
            LEFT JOIN reviews r ON l.id = r.labeling_id AND r.reviewer_id = ?
            WHERE dg.assigned_reviewer = ? AND dg.status IN ('completed', 'reviewed')
        ";
        
        if ($status) {
            $sql .= " AND r.review_status = ?";
            $stmt = $pdo->prepare($sql . " GROUP BY dg.id ORDER BY dg.updated_at DESC");
            $stmt->execute([$reviewer_id, $reviewer_id, $status]);
        } else {
            $stmt = $pdo->prepare($sql . " GROUP BY dg.id ORDER BY dg.updated_at DESC");
            $stmt->execute([$reviewer_id, $reviewer_id]);
        }
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        throw new Exception("Error fetching reviewer tasks: " . $e->getMessage());
    }
}

function getReviewerStats($reviewer_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_reviews,
                SUM(CASE WHEN r.review_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN r.review_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN r.review_status = 'needs_revision' THEN 1 ELSE 0 END) as revision_count,
                SUM(CASE WHEN r.review_status = 'pending' THEN 1 ELSE 0 END) as pending_count
            FROM reviews r
            JOIN document_groups dg ON r.group_id = dg.id
            WHERE r.reviewer_id = ?
        ");
        $stmt->execute([$reviewer_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        throw new Exception("Error fetching reviewer stats: " . $e->getMessage());
    }
}

/**
 * Utility Functions
 */
function logActivity($user_id, $action, $entity_type = null, $entity_id = null, $description = null) {
    global $pdo;
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$user_id, $action, $entity_type, $entity_id, $description, $ip_address]);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

/**
 * Admin Functions
 */
function assignLabeler($group_id, $labeler_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE document_groups 
            SET assigned_labeler = ?, status = 'in_progress', updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$labeler_id, $group_id]);
    } catch (Exception $e) {
        throw new Exception("Error assigning labeler: " . $e->getMessage());
    }
}

function assignReviewer($group_id, $reviewer_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE document_groups 
            SET assigned_reviewer = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$reviewer_id, $group_id]);
    } catch (Exception $e) {
        throw new Exception("Error assigning reviewer: " . $e->getMessage());
    }
}

function getAllDocumentGroups($status = null) {
    global $pdo;
    
    try {
        $sql = "
            SELECT 
                dg.*,
                u1.username as uploaded_by_user,
                u2.username as assigned_labeler_user,
                u3.username as assigned_reviewer_user,
                COUNT(d.id) as document_count
            FROM document_groups dg
            LEFT JOIN users u1 ON dg.uploaded_by = u1.id
            LEFT JOIN users u2 ON dg.assigned_labeler = u2.id
            LEFT JOIN users u3 ON dg.assigned_reviewer = u3.id
            LEFT JOIN documents d ON dg.id = d.group_id
        ";
        
        if ($status) {
            $sql .= " WHERE dg.status = ?";
            $stmt = $pdo->prepare($sql . " GROUP BY dg.id ORDER BY dg.created_at DESC");
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->prepare($sql . " GROUP BY dg.id ORDER BY dg.created_at DESC");
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        throw new Exception("Error fetching document groups: " . $e->getMessage());
    }
}

/**
 * Statistics Functions
 */
function getSystemStats() {
    global $pdo;
    
    try {
        $stats = [];
        
        // Total users by role
        $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role");
        $users_by_role = $stmt->fetchAll();
        foreach ($users_by_role as $row) {
            $stats['users_' . $row['role']] = $row['count'];
        }
        
        // Total document groups by status
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM document_groups GROUP BY status");
        $groups_by_status = $stmt->fetchAll();
        foreach ($groups_by_status as $row) {
            $stats['groups_' . $row['status']] = $row['count'];
        }
        
        // Total documents
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM documents");
        $stats['total_documents'] = $stmt->fetch()['count'];
        
        // Total labelings
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM labelings WHERE is_completed = 1");
        $stats['completed_labelings'] = $stmt->fetch()['count'];
        
        // Total reviews
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reviews");
        $stats['total_reviews'] = $stmt->fetch()['count'];
        
        return $stats;
    } catch (Exception $e) {
        throw new Exception("Error fetching system stats: " . $e->getMessage());
    }
}

function getUserActivity($user_id, $limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM activity_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        throw new Exception("Error fetching user activity: " . $e->getMessage());
    }
}

/**
 * Helper Functions for Forms
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function flashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = ['message' => $message, 'type' => $type];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * File Upload Helpers
 */
function validateUploadedFile($file, $allowed_types = ['txt', 'docx', 'pdf'], $max_size = 10485760) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload failed'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    return ['success' => true];
}

function readTextFile($file_path) {
    $content = file_get_contents($file_path);
    
    // Convert encoding if needed
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'auto');
    }
    
    return $content;
}

/**
 * Debug Functions (Remove in production)
 */
function debug($data, $die = false) {
    echo "<pre style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
    print_r($data);
    echo "</pre>";
    
    if ($die) {
        die();
    }
}

function logError($error, $context = '') {
    $log_message = date('Y-m-d H:i:s') . " - " . $context . " - " . $error . PHP_EOL;
    error_log($log_message, 3, '../logs/error.log');
}

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>