<?php
// includes/functions.php

// Sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Format file size
function formatFileSize($size) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, 2) . ' ' . $units[$i];
}

// Get time difference in human readable format
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'vừa xong';
    if ($time < 3600) return floor($time/60) . ' phút trước';
    if ($time < 86400) return floor($time/3600) . ' giờ trước';
    if ($time < 2592000) return floor($time/86400) . ' ngày trước';
    if ($time < 31536000) return floor($time/2592000) . ' tháng trước';
    
    return floor($time/31536000) . ' năm trước';
}

// Log user activity with error handling
function logActivity($user_id, $action, $target_type = null, $target_id = null, $description = null) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            error_log("Database connection failed in logActivity");
            return false;
        }
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $query = "INSERT INTO activity_logs (user_id, action, target_type, target_id, description, ip_address, user_agent) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            error_log("Failed to prepare statement in logActivity: " . $db->errorInfo()[2]);
            return false;
        }
        
        $result = $stmt->execute([
            $user_id,
            $action,
            $target_type,
            $target_id,
            $description,
            $ip_address,
            $user_agent
        ]);
        
        if (!$result) {
            error_log("Failed to execute logActivity: " . implode(', ', $stmt->errorInfo()));
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error in logActivity: " . $e->getMessage());
        return false;
    }
}

// Get user by ID
function getUserById($user_id) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            return false;
        }
        
        $query = "SELECT * FROM users WHERE id = ? AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error in getUserById: " . $e->getMessage());
        return false;
    }
}

// Check if user has permission
function hasPermission($user_id, $permission) {
    $user = getUserById($user_id);
    if (!$user) return false;
    
    // Admin has all permissions
    if ($user['role'] === 'admin') return true;
    
    // Define permissions for each role
    $permissions = [
        'labeler' => ['view_assignments', 'edit_assignments', 'view_documents'],
        'reviewer' => ['view_assignments', 'review_assignments', 'view_documents', 'view_reports']
    ];
    
    return isset($permissions[$user['role']]) && in_array($permission, $permissions[$user['role']]);
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Generate secure password hash
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Get document statistics
function getDocumentStats() {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            return ['total' => 0, 'single' => 0, 'multi' => 0];
        }
        
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN type = 'single' THEN 1 ELSE 0 END) as single,
                    SUM(CASE WHEN type = 'multi' THEN 1 ELSE 0 END) as multi
                  FROM documents WHERE status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error in getDocumentStats: " . $e->getMessage());
        return ['total' => 0, 'single' => 0, 'multi' => 0];
    }
}

// Get assignment statistics for user
function getUserAssignmentStats($user_id) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            return ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
        }
        
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                  FROM assignments WHERE user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error in getUserAssignmentStats: " . $e->getMessage());
        return ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
    }
}

// Upload file handler
function handleFileUpload($file, $allowed_types = ['txt', 'doc', 'docx'], $max_size = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file parameters'];
    }
    
    // Check for upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'No file sent'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'File too large'];
        default:
            return ['success' => false, 'error' => 'Unknown upload error'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File exceeds maximum size'];
    }
    
    // Check file extension
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $ext = array_search(
        $finfo->file($file['tmp_name']),
        [
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ],
        true
    );
    
    if ($ext === false || !in_array($ext, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Generate unique filename
    $filename = sprintf('%s.%s', generateRandomString(12), $ext);
    $upload_path = __DIR__ . '/../uploads/' . $filename;
    
    // Create upload directory if it doesn't exist
    if (!is_dir(dirname($upload_path))) {
        mkdir(dirname($upload_path), 0755, true);
    }
    
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
    
    return [
        'success' => true,
        'filename' => $filename,
        'path' => $upload_path,
        'size' => $file['size'],
        'type' => $ext
    ];
}

// Read uploaded text file
function readTextFile($filepath) {
    if (!file_exists($filepath)) {
        return ['success' => false, 'error' => 'File not found'];
    }
    
    $content = file_get_contents($filepath);
    if ($content === false) {
        return ['success' => false, 'error' => 'Failed to read file'];
    }
    
    // Convert encoding to UTF-8 if needed
    $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    
    return ['success' => true, 'content' => $content];
}

// Get system statistics
function getSystemStats() {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            return [];
        }
        
        $stats = [];
        
        // User count by role
        $query = "SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['users'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Document count
        $query = "SELECT COUNT(*) FROM documents WHERE status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['documents'] = $stmt->fetchColumn();
        
        // Assignment count by status
        $query = "SELECT status, COUNT(*) as count FROM assignments GROUP BY status";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['assignments'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error in getSystemStats: " . $e->getMessage());
        return [];
    }
}

// Pagination helper
function paginate($total_records, $current_page = 1, $records_per_page = 10) {
    $total_pages = ceil($total_records / $records_per_page);
    $current_page = max(1, min($total_pages, $current_page));
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'records_per_page' => $records_per_page,
        'offset' => $offset,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

// Get recent activities
function getRecentActivities($limit = 10, $user_id = null) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            return [];
        }
        
        $where_clause = $user_id ? "WHERE al.user_id = ?" : "";
        $params = $user_id ? [$user_id, $limit] : [$limit];
        
        $query = "SELECT al.*, u.full_name 
                  FROM activity_logs al
                  JOIN users u ON al.user_id = u.id
                  $where_clause
                  ORDER BY al.created_at DESC 
                  LIMIT ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error in getRecentActivities: " . $e->getMessage());
        return [];
    }
}

// Send notification (placeholder for future implementation)
function sendNotification($user_id, $title, $message, $type = 'info') {
    // TODO: Implement notification system
    // Could be email, in-app notification, etc.
    error_log("Notification for user $user_id: $title - $message");
    return true;
}

// Backup database (basic implementation)
function backupDatabase() {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        
        $backup_file = __DIR__ . '/../backups/backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Create backup directory if it doesn't exist
        if (!is_dir(dirname($backup_file))) {
            mkdir(dirname($backup_file), 0755, true);
        }
        
        // This is a simplified backup - in production, use mysqldump
        $tables = ['users', 'documents', 'document_groups', 'group_documents', 'assignments', 'labeling_results', 'reviews', 'activity_logs'];
        
        $sql_content = "-- Database backup created on " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            $sql_content .= "-- Table: $table\n";
            $sql_content .= "DELETE FROM $table;\n";
            // Add INSERT statements here (simplified)
        }
        
        file_put_contents($backup_file, $sql_content);
        
        return ['success' => true, 'file' => $backup_file];
        
    } catch (Exception $e) {
        error_log("Error in backupDatabase: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Clean old logs
function cleanOldLogs($days_to_keep = 30) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            return false;
        }
        
        $query = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $db->prepare($query);
        $result = $stmt->execute([$days_to_keep]);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error in cleanOldLogs: " . $e->getMessage());
        return false;
    }
}

?> 