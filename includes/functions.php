<?php
// Complete Functions class with all required methods

class Functions {
    private $conn;
    
    public function __construct() {
        // Database connection using MySQLi
        $this->conn = new mysqli('localhost', 'root', '', 'text_labeling_system');
        
        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
        
        $this->conn->set_charset("utf8");
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // Total documents
            $result = $this->conn->query("SELECT COUNT(*) as total_documents FROM documents");
            $stats['total_documents'] = $result ? $result->fetch_assoc()['total_documents'] : 0;
            
            // Total users by role
            $result = $this->conn->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $stats['users_' . $row['role']] = $row['count'];
                }
            }
            
            // Set defaults if not found
            $stats['users_admin'] = $stats['users_admin'] ?? 0;
            $stats['users_labeler'] = $stats['users_labeler'] ?? 0;
            
            // Total labelings
            $result = $this->conn->query("SELECT COUNT(*) as total_labelings FROM labelings");
            $stats['total_labelings'] = $result ? $result->fetch_assoc()['total_labelings'] : 0;
            
            // Completed tasks
            $result = $this->conn->query("SELECT COUNT(*) as completed_tasks FROM labelings WHERE status = 'completed'");
            $stats['completed_tasks'] = $result ? $result->fetch_assoc()['completed_tasks'] : 0;
            
            // Pending tasks  
            $result = $this->conn->query("SELECT COUNT(*) as pending_tasks FROM labelings WHERE status IN ('assigned', 'in_progress')");
            $stats['pending_tasks'] = $result ? $result->fetch_assoc()['pending_tasks'] : 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Dashboard stats error: " . $e->getMessage());
            return [
                'total_documents' => 0,
                'users_admin' => 0,
                'users_labeler' => 0,
                'total_labelings' => 0,
                'completed_tasks' => 0,
                'pending_tasks' => 0
            ];
        }
    }
    
    /**
     * Get active labelers
     */
    public function getActiveLabelers() {
        try {
            $query = "
                SELECT u.*, COUNT(l.id) as active_tasks
                FROM users u 
                LEFT JOIN labelings l ON u.id = l.labeler_id AND l.status IN ('assigned', 'in_progress')
                WHERE u.role = 'labeler' AND u.status = 'active'
                GROUP BY u.id
                ORDER BY active_tasks ASC, u.username
            ";
            
            $result = $this->conn->query($query);
            $labelers = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $labelers[] = $row;
                }
            }
            
            return ['success' => true, 'data' => $labelers];
            
        } catch (Exception $e) {
            error_log("Get active labelers error: " . $e->getMessage());
            return ['success' => false, 'data' => []];
        }
    }
    
    /**
     * Get labeler tasks
     */
    public function getLabelerTasks($labeler_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT l.*, d.title, dg.title as group_title, dg.group_type
                FROM labelings l
                LEFT JOIN documents d ON l.document_id = d.id
                LEFT JOIN document_groups dg ON l.group_id = dg.id
                WHERE l.labeler_id = ?
                ORDER BY l.created_at DESC
            ");
            $stmt->bind_param("i", $labeler_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tasks = [];
            while ($row = $result->fetch_assoc()) {
                // Calculate progress based on status
                switch ($row['status']) {
                    case 'assigned':
                        $row['progress'] = 0;
                        break;
                    case 'in_progress':
                        $row['progress'] = 50;
                        break;
                    case 'completed':
                        $row['progress'] = 100;
                        break;
                    default:
                        $row['progress'] = 0;
                }
                
                // Set title from group or document
                if ($row['group_title']) {
                    $row['title'] = $row['group_title'];
                } elseif (!$row['title']) {
                    $row['title'] = 'Untitled Task';
                }
                
                $tasks[] = $row;
            }
            
            return ['success' => true, 'data' => $tasks];
            
        } catch (Exception $e) {
            error_log("Get labeler tasks error: " . $e->getMessage());
            return ['success' => false, 'data' => []];
        }
    }
    
    /**
     * Get completed tasks for a labeler
     */
    public function getCompletedTasks($labeler_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT l.*, d.title, dg.title as group_title
                FROM labelings l
                LEFT JOIN documents d ON l.document_id = d.id
                LEFT JOIN document_groups dg ON l.group_id = dg.id
                WHERE l.labeler_id = ? AND l.status = 'completed'
                ORDER BY l.completed_at DESC
            ");
            $stmt->bind_param("i", $labeler_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tasks = [];
            while ($row = $result->fetch_assoc()) {
                $row['title'] = $row['group_title'] ?: $row['title'] ?: 'Completed Task';
                $tasks[] = $row;
            }
            
            return ['success' => true, 'data' => $tasks];
            
        } catch (Exception $e) {
            error_log("Get completed tasks error: " . $e->getMessage());
            return ['success' => false, 'data' => []];
        }
    }
    
    /**
     * Get available tasks for assignment
     */
    public function getAvailableTasks($type = 'single') {
        try {
            if ($type === 'single') {
                $query = "
                    SELECT d.*, u.username as uploaded_by_name
                    FROM documents d
                    JOIN users u ON d.uploaded_by = u.id
                    LEFT JOIN labelings l ON d.id = l.document_id
                    WHERE l.id IS NULL OR l.status = 'pending'
                    ORDER BY d.created_at DESC
                ";
            } else {
                $query = "
                    SELECT dg.*, u.username as uploaded_by_name
                    FROM document_groups dg
                    JOIN users u ON dg.uploaded_by = u.id
                    LEFT JOIN labelings l ON dg.id = l.group_id
                    WHERE (l.id IS NULL OR l.status = 'pending') AND dg.status = 'pending'
                    ORDER BY dg.created_at DESC
                ";
            }
            
            $result = $this->conn->query($query);
            $tasks = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $tasks[] = $row;
                }
            }
            
            return ['success' => true, 'data' => $tasks];
            
        } catch (Exception $e) {
            error_log("Get available tasks error: " . $e->getMessage());
            return ['success' => false, 'data' => []];
        }
    }
    
    /**
     * Create a new user
     */
    public function createUser($username, $password, $email, $role, $full_name = null) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->conn->prepare("
                INSERT INTO users (username, password, email, role, full_name, status, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, 'active', 1, NOW())
            ");
            $stmt->bind_param("sssss", $username, $hashed_password, $email, $role, $full_name);
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all users
     */
    public function getAllUsers() {
        try {
            $result = $this->conn->query("
                SELECT id, username, email, role, full_name, status, created_at 
                FROM users 
                ORDER BY created_at DESC
            ");
            
            $users = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
            }
            
            return ['success' => true, 'data' => $users];
            
        } catch (Exception $e) {
            error_log("Get all users error: " . $e->getMessage());
            return ['success' => false, 'data' => []];
        }
    }
    
    /**
     * Update user status
     */
    public function updateUserStatus($user_id, $status) {
        try {
            $is_active = $status === 'active' ? 1 : 0;
            
            $stmt = $this->conn->prepare("UPDATE users SET status = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("sii", $status, $is_active, $user_id);
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Update user status error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Close database connection
     */
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>