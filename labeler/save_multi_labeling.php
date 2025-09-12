<?php
require_once '../includes/auth.php';
require_once '../includes/enhanced_functions.php';

// Check if user is labeler
if ($_SESSION['role'] !== 'labeler') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dữ liệu không hợp lệ');
    }
    
    // Validate required fields
    if (!isset($data['group_id']) || !isset($data['action'])) {
        throw new Exception('Thiếu thông tin bắt buộc');
    }
    
    $groupId = intval($data['group_id']);
    $action = $data['action'];
    $labelerId = $_SESSION['user_id'];
    
    $ef = new EnhancedFunctions();
    
    switch ($action) {
        case 'save_progress':
            handleSaveProgress($ef, $groupId, $labelerId, $data);
            break;
            
        case 'submit_completed':
            handleSubmitCompleted($ef, $groupId, $labelerId, $data);
            break;
            
        case 'auto_save':
            handleAutoSave($ef, $groupId, $labelerId, $data);
            break;
            
        default:
            throw new Exception('Action không hợp lệ');
    }
    
} catch (Exception $e) {
    error_log("Save multi labeling error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Save labeling progress (not completed)
 */
function handleSaveProgress($ef, $groupId, $labelerId, $data) {
    $labelingData = prepareLabelingData($data);
    
    try {
        global $conn;
        
        // Update labeling with progress
        $stmt = $conn->prepare("
            UPDATE labelings SET 
                document_sentences = ?,
                ai_summary_edited = ?,
                status = 'in_progress',
                updated_at = NOW()
            WHERE group_id = ? AND labeler_id = ?
        ");
        
        $documentSentencesJson = json_encode($labelingData['document_sentences']);
        $editedSummary = $labelingData['edited_summary'];
        
        $stmt->bind_param("ssii", $documentSentencesJson, $editedSummary, $groupId, $labelerId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Đã lưu tiến độ thành công',
                'timestamp' => date('H:i:s')
            ]);
        } else {
            throw new Exception('Không tìm thấy nhiệm vụ hoặc không có thay đổi');
        }
        
    } catch (Exception $e) {
        throw new Exception('Lỗi lưu tiến độ: ' . $e->getMessage());
    }
}

/**
 * Submit completed labeling
 */
function handleSubmitCompleted($ef, $groupId, $labelerId, $data) {
    $labelingData = prepareLabelingData($data);
    
    // Validate completion requirements
    $totalSelected = 0;
    foreach ($labelingData['document_sentences'] as $docSentences) {
        $totalSelected += count($docSentences);
    }
    
    if ($totalSelected === 0) {
        throw new Exception('Cần chọn ít nhất một câu để hoàn thành');
    }
    
    if (empty(trim($labelingData['edited_summary']))) {
        throw new Exception('Cần chỉnh sửa bản tóm tắt để hoàn thành');
    }
    
    $result = $ef->saveMultiLabeling($groupId, $labelerId, $labelingData);
    
    if ($result['success']) {
        // Add completion statistics
        $result['statistics'] = [
            'total_selected_sentences' => $totalSelected,
            'documents_processed' => count($labelingData['document_sentences']),
            'summary_length' => strlen($labelingData['edited_summary']),
            'completion_time' => date('Y-m-d H:i:s')
        ];
    }
    
    echo json_encode($result);
}

/**
 * Auto-save functionality
 */
function handleAutoSave($ef, $groupId, $labelerId, $data) {
    try {
        $labelingData = prepareLabelingData($data);
        
        global $conn;
        
        // Simple update without status change
        $stmt = $conn->prepare("
            UPDATE labelings SET 
                document_sentences = ?,
                ai_summary_edited = ?,
                updated_at = NOW()
            WHERE group_id = ? AND labeler_id = ?
        ");
        
        $documentSentencesJson = json_encode($labelingData['document_sentences']);
        $editedSummary = $labelingData['edited_summary'];
        
        $stmt->bind_param("ssii", $documentSentencesJson, $editedSummary, $groupId, $labelerId);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Auto-saved',
            'timestamp' => date('H:i:s')
        ]);
        
    } catch (Exception $e) {
        // For auto-save, we don't want to show errors to user
        error_log("Auto-save error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Auto-save failed silently'
        ]);
    }
}

/**
 * Prepare labeling data from request
 */
function prepareLabelingData($data) {
    $documentSentences = [];
    $editedSummary = '';
    
    // Extract document sentences
    if (isset($data['document_sentences'])) {
        foreach ($data['document_sentences'] as $docId => $sentences) {
            $documentSentences[intval($docId)] = array_map('intval', $sentences);
        }
    }
    
    // Extract edited summary
    if (isset($data['edited_summary'])) {
        $editedSummary = trim($data['edited_summary']);
    }
    
    return [
        'document_sentences' => $documentSentences,
        'edited_summary' => $editedSummary
    ];
}

/**
 * Validate labeling data
 */
function validateLabelingData($labelingData) {
    $errors = [];
    
    // Check if any sentences are selected
    $totalSelected = 0;
    foreach ($labelingData['document_sentences'] as $sentences) {
        $totalSelected += count($sentences);
    }
    
    if ($totalSelected === 0) {
        $errors[] = 'Cần chọn ít nhất một câu';
    }
    
    // Check summary length
    $summaryLength = strlen($labelingData['edited_summary']);
    if ($summaryLength < 10) {
        $errors[] = 'Bản tóm tắt quá ngắn (tối thiểu 10 ký tự)';
    }
    
    if ($summaryLength > 5000) {
        $errors[] = 'Bản tóm tắt quá dài (tối đa 5000 ký tự)';
    }
    
    return $errors;
}

/**
 * Log labeling activity
 */
function logLabelingActivity($groupId, $labelerId, $action, $details = []) {
    try {
        global $conn;
        
        $stmt = $conn->prepare("
            INSERT INTO labeling_logs (group_id, labeler_id, action, details, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $detailsJson = json_encode($details);
        $stmt->bind_param("iiss", $groupId, $labelerId, $action, $detailsJson);
        $stmt->execute();
        
    } catch (Exception $e) {
        // Don't throw error for logging, just record it
        error_log("Logging error: " . $e->getMessage());
    }
}

/**
 * Get labeling statistics for response
 */
function getLabelingStats($labelingData) {
    $totalSentences = 0;
    $selectedSentences = 0;
    $documentsProcessed = count($labelingData['document_sentences']);
    
    foreach ($labelingData['document_sentences'] as $sentences) {
        $selectedSentences += count($sentences);
    }
    
    return [
        'documents_processed' => $documentsProcessed,
        'selected_sentences' => $selectedSentences,
        'summary_length' => strlen($labelingData['edited_summary']),
        'completion_percentage' => $totalSentences > 0 ? round(($selectedSentences / $totalSentences) * 100, 2) : 0
    ];
}
?>