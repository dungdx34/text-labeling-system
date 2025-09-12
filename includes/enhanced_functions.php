<?php
// Enhanced Functions with proper database connection

class EnhancedFunctions {
    protected $conn;
    
    public function __construct() {
        // Initialize database connection
        $this->conn = new mysqli('localhost', 'root', '', 'text_labeling_system');
        
        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
        
        $this->conn->set_charset("utf8");
    }
    
    /**
     * Process document upload for both single and multi-document types
     */
    public function processDocumentUpload($postData, $files = null) {
        try {
            $uploadType = $postData['upload_type'];
            
            if ($uploadType === 'single') {
                return $this->processSingleDocument($postData, $files);
            } elseif ($uploadType === 'multi') {
                return $this->processMultiDocument($postData, $files);
            } else {
                return ['success' => false, 'message' => 'Loại upload không hợp lệ'];
            }
        } catch (Exception $e) {
            error_log("Enhanced upload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi xử lý upload: ' . $e->getMessage()];
        }
    }
    
    /**
     * Process single document upload
     */
    private function processSingleDocument($postData, $files) {
        $title = trim($postData['single_title']);
        $content = trim($postData['single_content']);
        $summary = trim($postData['single_summary']);
        
        // Validation
        if (empty($title) || empty($content) || empty($summary)) {
            return ['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin'];
        }
        
        // Begin transaction
        $this->conn->begin_transaction();
        
        try {
            // Create document group
            $stmt = $this->conn->prepare("INSERT INTO document_groups (title, description, group_type, ai_summary, uploaded_by, created_at) VALUES (?, ?, 'single', ?, ?, NOW())");
            $description = "Văn bản đơn lẻ: " . substr($content, 0, 100) . "...";
            $stmt->bind_param("sssi", $title, $description, $summary, $_SESSION['user_id']);
            $stmt->execute();
            
            $groupId = $this->conn->insert_id;
            
            // Insert document
            $stmt = $this->conn->prepare("INSERT INTO documents (title, content, group_id, document_order, uploaded_by, created_at) VALUES (?, ?, ?, 1, ?, NOW())");
            $stmt->bind_param("ssii", $title, $content, $groupId, $_SESSION['user_id']);
            $stmt->execute();
            
            $documentId = $this->conn->insert_id;
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true, 
                'message' => 'Upload văn bản đơn thành công!',
                'group_id' => $groupId,
                'document_id' => $documentId
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Process multi-document upload
     */
    private function processMultiDocument($postData, $files) {
        $groupTitle = trim($postData['group_title']);
        $groupDescription = trim($postData['group_description']) ?: '';
        $groupSummary = trim($postData['group_summary']);
        $docTitles = $postData['doc_title'] ?? [];
        $docContents = $postData['doc_content'] ?? [];
        
        // Validation
        if (empty($groupTitle) || empty($groupSummary)) {
            return ['success' => false, 'message' => 'Vui lòng điền tiêu đề nhóm và bản tóm tắt AI'];
        }
        
        if (empty($docTitles) || empty($docContents)) {
            return ['success' => false, 'message' => 'Cần có ít nhất một văn bản'];
        }
        
        // Validate each document
        for ($i = 0; $i < count($docTitles); $i++) {
            if (empty(trim($docTitles[$i])) || empty(trim($docContents[$i]))) {
                return ['success' => false, 'message' => "Văn bản #" . ($i + 1) . " chưa đầy đủ thông tin"];
            }
        }
        
        // Begin transaction
        $this->conn->begin_transaction();
        
        try {
            // Create document group
            $stmt = $this->conn->prepare("INSERT INTO document_groups (title, description, group_type, ai_summary, uploaded_by, created_at) VALUES (?, ?, 'multi', ?, ?, NOW())");
            $stmt->bind_param("sssi", $groupTitle, $groupDescription, $groupSummary, $_SESSION['user_id']);
            $stmt->execute();
            
            $groupId = $this->conn->insert_id;
            
            // Insert documents
            $documentIds = [];
            for ($i = 0; $i < count($docTitles); $i++) {
                $title = trim($docTitles[$i]);
                $content = trim($docContents[$i]);
                $order = $i + 1;
                
                $stmt = $this->conn->prepare("INSERT INTO documents (title, content, group_id, document_order, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssiii", $title, $content, $groupId, $order, $_SESSION['user_id']);
                $stmt->execute();
                
                $documentIds[] = $this->conn->insert_id;
            }
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true, 
                'message' => 'Upload nhóm văn bản thành công! (' . count($docTitles) . ' văn bản)',
                'group_id' => $groupId,
                'document_ids' => $documentIds
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Get document group details with all documents
     */
    public function getDocumentGroup($groupId) {
        try {
            // Get group info
            $stmt = $this->conn->prepare("
                SELECT dg.*, u.username as uploaded_by_name 
                FROM document_groups dg 
                LEFT JOIN users u ON dg.uploaded_by = u.id 
                WHERE dg.id = ?
            ");
            $stmt->bind_param("i", $groupId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ['success' => false, 'message' => 'Không tìm thấy nhóm văn bản'];
            }
            
            $group = $result->fetch_assoc();
            
            // Get documents in group
            $stmt = $this->conn->prepare("
                SELECT * FROM documents 
                WHERE group_id = ? 
                ORDER BY document_order ASC
            ");
            $stmt->bind_param("i", $groupId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $documents = [];
            while ($row = $result->fetch_assoc()) {
                $documents[] = $row;
            }
            
            $group['documents'] = $documents;
            
            return ['success' => true, 'data' => $group];
            
        } catch (Exception $e) {
            error_log("Get group error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi truy xuất dữ liệu'];
        }
    }
    
    /**
     * Get all document groups for listing
     */
    public function getAllDocumentGroups($status = null, $limit = 50, $offset = 0) {
        try {
            $sql = "
                SELECT dg.*, u.username as uploaded_by_name,
                       COUNT(d.id) as document_count,
                       COUNT(l.id) as labeling_count
                FROM document_groups dg 
                LEFT JOIN users u ON dg.uploaded_by = u.id 
                LEFT JOIN documents d ON dg.id = d.group_id
                LEFT JOIN labelings l ON dg.id = l.group_id
            ";
            
            $conditions = [];
            $params = [];
            $types = "";
            
            if ($status) {
                $conditions[] = "dg.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $sql .= " GROUP BY dg.id ORDER BY dg.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            
            $stmt = $this->conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $groups = [];
            while ($row = $result->fetch_assoc()) {
                $groups[] = $row;
            }
            
            return ['success' => true, 'data' => $groups];
            
        } catch (Exception $e) {
            error_log("Get all groups error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi truy xuất danh sách', 'data' => []];
        }
    }
    
    /**
     * Get labeling statistics
     */
    public function getLabelingStats() {
        try {
            $stats = [];
            
            // Group stats
            $result = $this->conn->query("
                SELECT 
                    COUNT(*) as total_groups,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_groups,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_groups,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_groups
                FROM document_groups
            ");
            $stats['groups'] = $result ? $result->fetch_assoc() : [
                'total_groups' => 0,
                'pending_groups' => 0, 
                'in_progress_groups' => 0,
                'completed_groups' => 0
            ];
            
            // Document stats
            $result = $this->conn->query("
                SELECT 
                    COUNT(*) as total_documents,
                    COUNT(DISTINCT group_id) as groups_with_documents
                FROM documents
            ");
            $stats['documents'] = $result ? $result->fetch_assoc() : [
                'total_documents' => 0,
                'groups_with_documents' => 0
            ];
            
            // Labeling stats
            $result = $this->conn->query("
                SELECT 
                    COUNT(*) as total_labelings,
                    COUNT(DISTINCT labeler_id) as active_labelers,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_labelings
                FROM labelings
            ");
            $stats['labelings'] = $result ? $result->fetch_assoc() : [
                'total_labelings' => 0,
                'active_labelers' => 0,
                'completed_labelings' => 0
            ];
            
            return ['success' => true, 'data' => $stats];
            
        } catch (Exception $e) {
            error_log("Get stats error: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'Lỗi truy xuất thống kê',
                'data' => [
                    'groups' => ['total_groups' => 0, 'pending_groups' => 0, 'in_progress_groups' => 0, 'completed_groups' => 0],
                    'documents' => ['total_documents' => 0, 'groups_with_documents' => 0],
                    'labelings' => ['total_labelings' => 0, 'active_labelers' => 0, 'completed_labelings' => 0]
                ]
            ];
        }
    }
    
    /**
     * Get labeling task for labeler
     */
    public function getLabelingTask($groupId, $labelerId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT l.*, dg.title as group_title, dg.description as group_description,
                       dg.ai_summary, dg.group_type
                FROM labelings l
                JOIN document_groups dg ON l.group_id = dg.id
                WHERE l.group_id = ? AND l.labeler_id = ?
            ");
            $stmt->bind_param("ii", $groupId, $labelerId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ['success' => false, 'message' => 'Không tìm thấy nhiệm vụ'];
            }
            
            $task = $result->fetch_assoc();
            
            // Get documents
            $stmt = $this->conn->prepare("
                SELECT * FROM documents 
                WHERE group_id = ? 
                ORDER BY document_order ASC
            ");
            $stmt->bind_param("i", $groupId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $documents = [];
            while ($row = $result->fetch_assoc()) {
                // Split content into sentences
                $sentences = $this->splitIntoSentences($row['content']);
                $row['sentences'] = $sentences;
                $documents[] = $row;
            }
            
            $task['documents'] = $documents;
            
            // Parse existing labeling data if available
            if ($task['document_sentences']) {
                $task['existing_labeling'] = json_decode($task['document_sentences'], true);
            }
            
            return ['success' => true, 'data' => $task];
            
        } catch (Exception $e) {
            error_log("Get labeling task error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi truy xuất nhiệm vụ'];
        }
    }
    
    /**
     * Split text into sentences
     */
    private function splitIntoSentences($text) {
        // Basic sentence splitting for Vietnamese
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        
        foreach ($sentences as $index => $sentence) {
            $sentence = trim($sentence);
            if (!empty($sentence)) {
                $result[] = [
                    'id' => $index + 1,
                    'text' => $sentence,
                    'selected' => false
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Save multi-document labeling results
     */
    public function saveMultiLabeling($groupId, $labelerId, $labelingData) {
        try {
            $this->conn->begin_transaction();
            
            // Validate labeling exists
            $stmt = $this->conn->prepare("
                SELECT id FROM labelings 
                WHERE group_id = ? AND labeler_id = ?
            ");
            $stmt->bind_param("ii", $groupId, $labelerId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Không tìm thấy phiên gán nhãn");
            }
            
            $labeling = $result->fetch_assoc();
            $labelingId = $labeling['id'];
            
            // Update labeling with new data
            $stmt = $this->conn->prepare("
                UPDATE labelings SET 
                    document_sentences = ?,
                    ai_summary_edited = ?,
                    status = 'completed',
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $documentSentencesJson = json_encode($labelingData['document_sentences']);
            $editedSummary = $labelingData['edited_summary'];
            
            $stmt->bind_param("ssi", $documentSentencesJson, $editedSummary, $labelingId);
            $stmt->execute();
            
            // Update group status if needed
            $stmt = $this->conn->prepare("UPDATE document_groups SET status = 'completed' WHERE id = ?");
            $stmt->bind_param("i", $groupId);
            $stmt->execute();
            
            $this->conn->commit();
            
            return ['success' => true, 'message' => 'Lưu kết quả gán nhãn thành công'];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Save multi labeling error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi lưu kết quả: ' . $e->getMessage()];
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