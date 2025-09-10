<?php
// Enhanced Functions class to support multi-document labeling

class EnhancedFunctions {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // Document Groups Management
    public function createDocumentGroup($title, $description, $group_type, $ai_summary, $uploaded_by) {
        try {
            $query = "INSERT INTO document_groups (title, description, group_type, ai_summary, uploaded_by) 
                      VALUES (:title, :description, :group_type, :ai_summary, :uploaded_by)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':group_type', $group_type);
            $stmt->bindParam(':ai_summary', $ai_summary);
            $stmt->bindParam(':uploaded_by', $uploaded_by);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch(PDOException $e) {
            error_log("Create document group error: " . $e->getMessage());
            return false;
        }
    }
    
    public function addDocumentToGroup($group_id, $title, $content, $document_order = 1) {
        try {
            $query = "INSERT INTO documents (title, content, group_id, document_order) 
                      VALUES (:title, :content, :group_id, :document_order)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':group_id', $group_id);
            $stmt->bindParam(':document_order', $document_order);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function getDocumentGroups($status = null, $group_type = null) {
        $query = "SELECT dg.*, u.full_name as uploaded_by_name,
                         COUNT(d.id) as document_count
                  FROM document_groups dg 
                  LEFT JOIN users u ON dg.uploaded_by = u.id
                  LEFT JOIN documents d ON dg.id = d.group_id
                  WHERE 1=1";
        
        $params = [];
        
        if ($status) {
            $query .= " AND dg.status = :status";
            $params['status'] = $status;
        }
        
        if ($group_type) {
            $query .= " AND dg.group_type = :group_type";
            $params['group_type'] = $group_type;
        }
        
        $query .= " GROUP BY dg.id ORDER BY dg.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getDocumentGroup($group_id) {
        $query = "SELECT dg.*, u.full_name as uploaded_by_name
                  FROM document_groups dg 
                  LEFT JOIN users u ON dg.uploaded_by = u.id
                  WHERE dg.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $group_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getDocumentsInGroup($group_id) {
        $query = "SELECT * FROM documents WHERE group_id = :group_id ORDER BY document_order ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':group_id', $group_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Enhanced labeling functions
    public function saveMultiDocumentLabeling($group_id, $labeler_id, $labeling_data) {
        try {
            // Check if labeling exists
            $query = "SELECT id FROM labelings WHERE group_id = :group_id AND labeler_id = :labeler_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':group_id', $group_id);
            $stmt->bindParam(':labeler_id', $labeler_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Update existing labeling
                $labeling = $stmt->fetch(PDO::FETCH_ASSOC);
                $update_query = "UPDATE labelings SET 
                                document_sentences = :document_sentences,
                                text_style_id = :text_style_id,
                                edited_summary = :edited_summary,
                                status = :status,
                                updated_at = CURRENT_TIMESTAMP 
                                WHERE id = :id";
                
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bindParam(':document_sentences', json_encode($labeling_data['document_sentences']));
                $update_stmt->bindParam(':text_style_id', $labeling_data['text_style_id'] ?? null);
                $update_stmt->bindParam(':edited_summary', $labeling_data['edited_summary'] ?? '');
                $update_stmt->bindParam(':status', $labeling_data['status'] ?? 'pending');
                $update_stmt->bindParam(':id', $labeling['id']);
                
                return $update_stmt->execute();
            } else {
                // Create new labeling
                $insert_query = "INSERT INTO labelings (group_id, labeler_id, document_sentences, text_style_id, edited_summary, status) 
                                VALUES (:group_id, :labeler_id, :document_sentences, :text_style_id, :edited_summary, :status)";
                
                $insert_stmt = $this->conn->prepare($insert_query);
                $insert_stmt->bindParam(':group_id', $group_id);
                $insert_stmt->bindParam(':labeler_id', $labeler_id);
                $insert_stmt->bindParam(':document_sentences', json_encode($labeling_data['document_sentences']));
                $insert_stmt->bindParam(':text_style_id', $labeling_data['text_style_id'] ?? null);
                $insert_stmt->bindParam(':edited_summary', $labeling_data['edited_summary'] ?? '');
                $insert_stmt->bindParam(':status', $labeling_data['status'] ?? 'pending');
                
                return $insert_stmt->execute();
            }
        } catch(PDOException $e) {
            error_log("Save multi-document labeling error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getMultiDocumentLabeling($group_id, $labeler_id) {
        $query = "SELECT l.*, ts.name as text_style_name, dg.title as group_title, dg.group_type
                  FROM labelings l 
                  LEFT JOIN text_styles ts ON l.text_style_id = ts.id 
                  LEFT JOIN document_groups dg ON l.group_id = dg.id
                  WHERE l.group_id = :group_id AND l.labeler_id = :labeler_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':group_id', $group_id);
        $stmt->bindParam(':labeler_id', $labeler_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAllLabelings($labeler_id = null, $status = null, $group_type = null) {
        $query = "SELECT l.*, dg.title as group_title, dg.group_type, u.full_name as labeler_name, 
                         ts.name as text_style_name, r.full_name as reviewer_name
                  FROM labelings l 
                  LEFT JOIN document_groups dg ON l.group_id = dg.id
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
        
        if ($group_type) {
            $query .= " AND dg.group_type = :group_type";
            $params['group_type'] = $group_type;
        }
        
        $query .= " ORDER BY l.updated_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getLabelingById($labeling_id) {
        $query = "SELECT l.*, dg.title as group_title, dg.group_type, dg.ai_summary as group_ai_summary,
                         u.full_name as labeler_name, ts.name as text_style_name
                  FROM labelings l 
                  LEFT JOIN document_groups dg ON l.group_id = dg.id
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
            
            $result = $stmt->execute();
            
            // Update group status if reviewed
            if ($result && $status === 'reviewed') {
                $labeling = $this->getLabelingById($labeling_id);
                if ($labeling && $labeling['group_id']) {
                    $this->updateDocumentGroupStatus($labeling['group_id'], 'reviewed');
                }
            }
            
            return $result;
        } catch(PDOException $e) {
            error_log("Update labeling review error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateDocumentGroupStatus($group_id, $status) {
        try {
            $query = "UPDATE document_groups SET status = :status WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $group_id);
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    // Statistics for enhanced system
    public function getEnhancedStatistics() {
        $stats = [];
        
        // Group statistics
        $query = "SELECT group_type, status, COUNT(*) as count FROM document_groups GROUP BY group_type, status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $group_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($group_stats as $stat) {
            $stats['groups'][$stat['group_type']][$stat['status']] = $stat['count'];
        }
        
        // Labeling statistics by group type
        $query = "SELECT dg.group_type, l.status, COUNT(*) as count 
                  FROM labelings l 
                  JOIN document_groups dg ON l.group_id = dg.id 
                  GROUP BY dg.group_type, l.status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $labeling_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($labeling_stats as $stat) {
            $stats['labelings'][$stat['group_type']][$stat['status']] = $stat['count'];
        }
        
        return $stats;
    }
    
    // Utility functions
    public function splitIntoSentences($text) {
        // Enhanced sentence splitting for Vietnamese text
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-ZÀÁÂÃÈÉÊÌÍÒÓÔÕÙÚĂĐĨŨƠƯỲ])/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_map('trim', $sentences);
    }
    
    public function generateSummaryStatistics($summary) {
        return [
            'word_count' => str_word_count($summary),
            'char_count' => mb_strlen($summary, 'UTF-8'),
            'sentence_count' => count($this->splitIntoSentences($summary)),
            'paragraph_count' => count(array_filter(explode("\n", $summary)))
        ];
    }
}
?>