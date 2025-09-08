<?php
// Determine the correct path to database.php based on current file location
if (file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';
} elseif (file_exists('../config/database.php')) {
    require_once '../config/database.php';
} elseif (file_exists('config/database.php')) {
    require_once 'config/database.php';
} else {
    die('Error: Cannot find database configuration file.');
}

class Functions {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function createUser($username, $email, $password, $role, $full_name) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, email, password, role, full_name) VALUES (:username, :email, :password, :role, :full_name)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':full_name', $full_name);
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function getUsers($role = null) {
        $query = "SELECT id, username, email, role, full_name, created_at, is_active FROM users";
        if ($role) {
            $query .= " WHERE role = :role";
        }
        $query .= " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        if ($role) {
            $stmt->bindParam(':role', $role);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateUser($user_id, $username, $email, $role, $full_name, $is_active = 1) {
        try {
            $query = "UPDATE users SET username = :username, email = :email, role = :role, full_name = :full_name, is_active = :is_active WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':id', $user_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function deleteUser($user_id) {
        try {
            $query = "UPDATE users SET is_active = 0 WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $user_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function uploadDocument($title, $content, $ai_summary, $uploaded_by) {
        try {
            $query = "INSERT INTO documents (title, content, ai_summary, uploaded_by) VALUES (:title, :content, :ai_summary, :uploaded_by)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':ai_summary', $ai_summary);
            $stmt->bindParam(':uploaded_by', $uploaded_by);
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function getDocuments($status = null, $limit = null) {
        $query = "SELECT d.*, u.full_name as uploaded_by_name FROM documents d 
                  LEFT JOIN users u ON d.uploaded_by = u.id";
        if ($status) {
            $query .= " WHERE d.status = :status";
        }
        $query .= " ORDER BY d.created_at DESC";
        if ($limit) {
            $query .= " LIMIT :limit";
        }
        
        $stmt = $this->conn->prepare($query);
        if ($status) {
            $stmt->bindParam(':status', $status);
        }
        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getDocument($id) {
        $query = "SELECT * FROM documents WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateDocumentStatus($document_id, $status) {
        try {
            $query = "UPDATE documents SET status = :status WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $document_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function getTextStyles() {
        $query = "SELECT * FROM text_styles ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function saveLabelingStep($document_id, $labeler_id, $step_data) {
        try {
            // Check if labeling exists
            $query = "SELECT id FROM labelings WHERE document_id = :document_id AND labeler_id = :labeler_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':document_id', $document_id);
            $stmt->bindParam(':labeler_id', $labeler_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Update existing labeling
                $labeling = $stmt->fetch(PDO::FETCH_ASSOC);
                $update_query = "UPDATE labelings SET ";
                $params = [];
                
                if (isset($step_data['important_sentences'])) {
                    $update_query .= "important_sentences = :important_sentences, ";
                    $params['important_sentences'] = json_encode($step_data['important_sentences']);
                }
                if (isset($step_data['text_style_id'])) {
                    $update_query .= "text_style_id = :text_style_id, ";
                    $params['text_style_id'] = $step_data['text_style_id'];
                }
                if (isset($step_data['edited_summary'])) {
                    $update_query .= "edited_summary = :edited_summary, status = 'completed', ";
                    $params['edited_summary'] = $step_data['edited_summary'];
                }
                
                $update_query .= "updated_at = CURRENT_TIMESTAMP WHERE id = :id";
                $params['id'] = $labeling['id'];
                
                $update_stmt = $this->conn->prepare($update_query);
                return $update_stmt->execute($params);
            } else {
                // Create new labeling
                $insert_query = "INSERT INTO labelings (document_id, labeler_id, important_sentences, text_style_id, edited_summary, status) 
                                VALUES (:document_id, :labeler_id, :important_sentences, :text_style_id, :edited_summary, :status)";
                $insert_stmt = $this->conn->prepare($insert_query);
                $insert_stmt->bindParam(':document_id', $document_id);
                $insert_stmt->bindParam(':labeler_id', $labeler_id);
                $important_sentences_json = json_encode($step_data['important_sentences'] ?? []);
                $insert_stmt->bindParam(':important_sentences', $important_sentences_json);
                $insert_stmt->bindParam(':text_style_id', $step_data['text_style_id'] ?? null);
                $insert_stmt->bindParam(':edited_summary', $step_data['edited_summary'] ?? '');
                $status = isset($step_data['edited_summary']) ? 'completed' : 'pending';
                $insert_stmt->bindParam(':status', $status);
                return $insert_stmt->execute();
            }
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function getLabeling($document_id, $labeler_id) {
        $query = "SELECT l.*, ts.name as text_style_name, d.title as document_title 
                  FROM labelings l 
                  LEFT JOIN text_styles ts ON l.text_style_id = ts.id 
                  LEFT JOIN documents d ON l.document_id = d.id
                  WHERE l.document_id = :document_id AND l.labeler_id = :labeler_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':document_id', $document_id);
        $stmt->bindParam(':labeler_id', $labeler_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getLabelings($labeler_id = null, $status = null) {
        $query = "SELECT l.*, d.title as document_title, u.full_name as labeler_name, 
                         ts.name as text_style_name, r.full_name as reviewer_name
                  FROM labelings l 
                  LEFT JOIN documents d ON l.document_id = d.id
                  LEFT JOIN users u ON l.labeler_id = u.id
                  LEFT JOIN users r ON l.reviewer_id = r.id
                  LEFT JOIN text_styles ts ON l.text_style_id = ts.id
                  WHERE 1=1";
        $params = [];
        
        if ($labeler_id) {
            $query .= " AND l.labeler_id = :labeler_id";
            $params['labeler_id'] = $labeler_id;
        }
        if ($status) {
            $query .= " AND l.status = :status";
            $params['status'] = $status;
        }
        
        $query .= " ORDER BY l.updated_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getLabelingById($labeling_id) {
        $query = "SELECT l.*, d.title as document_title, d.content as document_content, 
                         d.ai_summary, u.full_name as labeler_name, ts.name as text_style_name
                  FROM labelings l 
                  LEFT JOIN documents d ON l.document_id = d.id
                  LEFT JOIN users u ON l.labeler_id = u.id
                  LEFT JOIN text_styles ts ON l.text_style_id = ts.id
                  WHERE l.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $labeling_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateLabelingReview($labeling_id, $reviewer_id, $review_notes, $status) {
        try {
            $query = "UPDATE labelings SET reviewer_id = :reviewer_id, review_notes = :review_notes, 
                      status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':reviewer_id', $reviewer_id);
            $stmt->bindParam(':review_notes', $review_notes);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $labeling_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function getStatistics() {
        $stats = [];
        
        // Total users by role
        $query = "SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $user_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($user_stats as $stat) {
            $stats['users'][$stat['role']] = $stat['count'];
        }
        
        // Total documents by status
        $query = "SELECT status, COUNT(*) as count FROM documents GROUP BY status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $doc_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($doc_stats as $stat) {
            $stats['documents'][$stat['status']] = $stat['count'];
        }
        
        // Total labelings by status
        $query = "SELECT status, COUNT(*) as count FROM labelings GROUP BY status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $labeling_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($labeling_stats as $stat) {
            $stats['labelings'][$stat['status']] = $stat['count'];
        }
        
        return $stats;
    }
    
    public function getRecentActivity($limit = 10) {
        $query = "
            SELECT 'document_uploaded' as type, d.title as description, d.created_at as timestamp, u.full_name as user_name
            FROM documents d 
            LEFT JOIN users u ON d.uploaded_by = u.id
            
            UNION ALL
            
            SELECT 'labeling_completed' as type, CONCAT('Completed labeling for: ', d.title) as description, 
                   l.updated_at as timestamp, u.full_name as user_name
            FROM labelings l 
            LEFT JOIN documents d ON l.document_id = d.id
            LEFT JOIN users u ON l.labeler_id = u.id
            WHERE l.status = 'completed'
            
            ORDER BY timestamp DESC
            LIMIT :limit
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>