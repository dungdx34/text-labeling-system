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
	
		/**
	 * Get all labelings for a user
	 */
	public function getLabelings($user_id = null) {
		try {
			$query = "
				SELECT l.*, d.title, d.content, d.ai_summary, u.username as labeler_name
				FROM labelings l
				LEFT JOIN documents d ON l.document_id = d.id
				LEFT JOIN users u ON l.labeler_id = u.id
			";
			
			$params = [];
			$types = "";
			
			if ($user_id !== null) {
				$query .= " WHERE l.labeler_id = ?";
				$params[] = $user_id;
				$types .= "i";
			}
			
			$query .= " ORDER BY l.created_at DESC";
			
			$stmt = $this->conn->prepare($query);
			if (!empty($params)) {
				$stmt->bind_param($types, ...$params);
			}
			$stmt->execute();
			$result = $stmt->get_result();
			
			$labelings = [];
			while ($row = $result->fetch_assoc()) {
				$labelings[] = $row;
			}
			
			return ['success' => true, 'data' => $labelings];
			
		} catch (Exception $e) {
			error_log("Get labelings error: " . $e->getMessage());
			return ['success' => false, 'data' => []];
		}
	}

	/**
	 * Get labeler tasks with enhanced information
	 */
	public function getLabelerTasks($labeler_id) {
		try {
			$stmt = $this->conn->prepare("
				SELECT l.*, d.title, d.content, d.ai_summary, 
					   CASE 
						   WHEN l.status = 'assigned' THEN 'Đã giao'
						   WHEN l.status = 'in_progress' THEN 'Đang làm'
						   WHEN l.status = 'completed' THEN 'Hoàn thành'
						   ELSE 'Không xác định'
					   END as status_text,
					   CASE 
						   WHEN l.status = 'assigned' THEN 0
						   WHEN l.status = 'in_progress' THEN 50
						   WHEN l.status = 'completed' THEN 100
						   ELSE 0
					   END as progress
				FROM labelings l
				LEFT JOIN documents d ON l.document_id = d.id
				WHERE l.labeler_id = ?
				ORDER BY l.created_at DESC
			");
			$stmt->bind_param("i", $labeler_id);
			$stmt->execute();
			$result = $stmt->get_result();
			
			$tasks = [];
			while ($row = $result->fetch_assoc()) {
				// Add default title if empty
				if (empty($row['title'])) {
					$row['title'] = 'Untitled Document #' . $row['id'];
				}
				
				// Format dates
				$row['created_at_formatted'] = date('d/m/Y H:i', strtotime($row['created_at']));
				if ($row['completed_at']) {
					$row['completed_at_formatted'] = date('d/m/Y H:i', strtotime($row['completed_at']));
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
				SELECT l.*, d.title, d.content
				FROM labelings l
				LEFT JOIN documents d ON l.document_id = d.id
				WHERE l.labeler_id = ? AND l.status = 'completed'
				ORDER BY l.completed_at DESC
			");
			$stmt->bind_param("i", $labeler_id);
			$stmt->execute();
			$result = $stmt->get_result();
			
			$tasks = [];
			while ($row = $result->fetch_assoc()) {
				if (empty($row['title'])) {
					$row['title'] = 'Completed Document #' . $row['id'];
				}
				$row['completed_at_formatted'] = date('d/m/Y H:i', strtotime($row['completed_at']));
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
	public function getAvailableTasks($type = 'single', $limit = 10) {
		try {
			$query = "
				SELECT d.*, 
					   COUNT(l.id) as assignment_count,
					   MAX(l.created_at) as last_assigned
				FROM documents d
				LEFT JOIN labelings l ON d.id = l.document_id
				WHERE d.status = 'active'
			";
			
			if ($type === 'single') {
				$query .= " AND d.document_type = 'single'";
			}
			
			$query .= "
				GROUP BY d.id
				HAVING assignment_count < 3
				ORDER BY d.created_at DESC
				LIMIT ?
			";
			
			$stmt = $this->conn->prepare($query);
			$stmt->bind_param("i", $limit);
			$stmt->execute();
			$result = $stmt->get_result();
			
			$tasks = [];
			while ($row = $result->fetch_assoc()) {
				if (empty($row['title'])) {
					$row['title'] = 'Available Document #' . $row['id'];
				}
				
				// Calculate content preview
				$row['content_preview'] = mb_substr($row['content'], 0, 200) . '...';
				$row['word_count'] = str_word_count(strip_tags($row['content']));
				$row['created_at_formatted'] = date('d/m/Y', strtotime($row['created_at']));
				
				$tasks[] = $row;
			}
			
			return ['success' => true, 'data' => $tasks];
			
		} catch (Exception $e) {
			error_log("Get available tasks error: " . $e->getMessage());
			return ['success' => false, 'data' => []];
		}
	}

	/**
	 * Assign task to labeler
	 */
	public function assignTask($document_id, $labeler_id) {
		try {
			// Check if already assigned
			$stmt = $this->conn->prepare("
				SELECT COUNT(*) as count 
				FROM labelings 
				WHERE document_id = ? AND labeler_id = ? AND status != 'completed'
			");
			$stmt->bind_param("ii", $document_id, $labeler_id);
			$stmt->execute();
			$result = $stmt->get_result()->fetch_assoc();
			
			if ($result['count'] > 0) {
				return ['success' => false, 'message' => 'Task already assigned to this labeler'];
			}
			
			// Create new assignment
			$stmt = $this->conn->prepare("
				INSERT INTO labelings (document_id, labeler_id, status, created_at)
				VALUES (?, ?, 'assigned', NOW())
			");
			$stmt->bind_param("ii", $document_id, $labeler_id);
			
			if ($stmt->execute()) {
				return ['success' => true, 'message' => 'Task assigned successfully', 'labeling_id' => $this->conn->insert_id];
			} else {
				return ['success' => false, 'message' => 'Failed to assign task'];
			}
			
		} catch (Exception $e) {
			error_log("Assign task error: " . $e->getMessage());
			return ['success' => false, 'message' => 'Error assigning task'];
		}
	}

	/**
	 * Get task statistics for labeler
	 */
	public function getLabelerStats($labeler_id) {
		try {
			$stats = [
				'total' => 0,
				'completed' => 0,
				'in_progress' => 0,
				'assigned' => 0
			];
			
			$stmt = $this->conn->prepare("
				SELECT status, COUNT(*) as count
				FROM labelings
				WHERE labeler_id = ?
				GROUP BY status
			");
			$stmt->bind_param("i", $labeler_id);
			$stmt->execute();
			$result = $stmt->get_result();
			
			while ($row = $result->fetch_assoc()) {
				$stats[$row['status']] = (int)$row['count'];
				$stats['total'] += (int)$row['count'];
			}
			
			// Calculate completion rate
			$stats['completion_rate'] = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 2) : 0;
			
			return ['success' => true, 'data' => $stats];
			
		} catch (Exception $e) {
			error_log("Get labeler stats error: " . $e->getMessage());
			return ['success' => false, 'data' => ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'assigned' => 0, 'completion_rate' => 0]];
		}
	}
}
?>