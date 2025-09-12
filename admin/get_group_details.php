<?php
require_once '../includes/auth.php';
require_once '../includes/enhanced_functions.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    $ef = new EnhancedFunctions();
    
    // Handle different actions
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_group':
            handleGetGroup($ef);
            break;
            
        case 'get_all_groups':
            handleGetAllGroups($ef);
            break;
            
        case 'assign_labeler':
            handleAssignLabeler($ef);
            break;
            
        case 'update_status':
            handleUpdateStatus($ef);
            break;
            
        case 'delete_group':
            handleDeleteGroup($ef);
            break;
            
        case 'get_stats':
            handleGetStats($ef);
            break;
            
        default:
            throw new Exception('Action không hợp lệ');
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

/**
 * Get specific group details
 */
function handleGetGroup($ef) {
    if (!isset($_GET['group_id'])) {
        throw new Exception('Thiếu group_id');
    }
    
    $groupId = intval($_GET['group_id']);
    $result = $ef->getDocumentGroup($groupId);
    
    echo json_encode($result);
}

/**
 * Get all groups with pagination and filtering
 */
function handleGetAllGroups($ef) {
    $status = $_GET['status'] ?? null;
    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);
    $search = $_GET['search'] ?? '';
    
    // Validate limit
    if ($limit > 100) $limit = 100;
    if ($limit < 1) $limit = 20;
    
    $result = $ef->getAllDocumentGroups($status, $limit, $offset);
    
    // If search is provided, filter results
    if (!empty($search) && $result['success']) {
        $filteredData = array_filter($result['data'], function($group) use ($search) {
            return stripos($group['title'], $search) !== false || 
                   stripos($group['description'], $search) !== false;
        });
        $result['data'] = array_values($filteredData);
    }
    
    echo json_encode($result);
}

/**
 * Assign group to labeler
 */
function handleAssignLabeler($ef) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['group_id']) || !isset($data['labeler_id'])) {
        throw new Exception('Thiếu thông tin group_id hoặc labeler_id');
    }
    
    $groupId = intval($data['group_id']);
    $labelerId = intval($data['labeler_id']);
    
    $result = $ef->assignGroupToLabeler($groupId, $labelerId);
    
    echo json_encode($result);
}

/**
 * Update group status
 */
function handleUpdateStatus($ef) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['group_id']) || !isset($data['status'])) {
        throw new Exception('Thiếu thông tin group_id hoặc status');
    }
    
    $groupId = intval($data['group_id']);
    $status = $data['status'];
    
    // Validate status
    $validStatuses = ['pending', 'in_progress', 'completed', 'reviewed'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Status không hợp lệ');
    }
    
    try {
        global $conn;
        $stmt = $conn->prepare("UPDATE document_groups SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $groupId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Cập nhật trạng thái thành công'
            ]);
        } else {
            throw new Exception('Không tìm thấy nhóm văn bản hoặc không có thay đổi');
        }
        
    } catch (Exception $e) {
        throw new Exception('Lỗi cập nhật trạng thái: ' . $e->getMessage());
    }
}

/**
 * Delete group and associated documents
 */
function handleDeleteGroup($ef) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['group_id'])) {
        throw new Exception('Thiếu group_id');
    }
    
    $groupId = intval($data['group_id']);
    
    try {
        global $conn;
        $conn->begin_transaction();
        
        // Check if group has active labelings
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM labelings 
            WHERE group_id = ? AND status IN ('assigned', 'in_progress')
        ");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            throw new Exception('Không thể xóa nhóm văn bản đang được gán nhãn');
        }
        
        // Delete labelings first
        $stmt = $conn->prepare("DELETE FROM labelings WHERE group_id = ?");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        
        // Delete documents
        $stmt = $conn->prepare("DELETE FROM documents WHERE group_id = ?");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        
        // Delete group
        $stmt = $conn->prepare("DELETE FROM document_groups WHERE id = ?");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'Xóa nhóm văn bản thành công'
            ]);
        } else {
            throw new Exception('Không tìm thấy nhóm văn bản');
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        throw new Exception('Lỗi xóa nhóm văn bản: ' . $e->getMessage());
    }
}

/**
 * Get labeling statistics
 */
function handleGetStats($ef) {
    $result = $ef->getLabelingStats();
    echo json_encode($result);
}

/**
 * Get available labelers for assignment
 */
function getAvailableLabelers() {
    try {
        global $conn;
        $stmt = $conn->prepare("
            SELECT id, username, full_name, email,
                   (SELECT COUNT(*) FROM labelings WHERE labeler_id = users.id AND status IN ('assigned', 'in_progress')) as active_tasks
            FROM users 
            WHERE role = 'labeler' AND status = 'active'
            ORDER BY active_tasks ASC, username ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $labelers = [];
        while ($row = $result->fetch_assoc()) {
            $labelers[] = $row;
        }
        
        return [
            'success' => true,
            'data' => $labelers
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Lỗi truy xuất danh sách labeler: ' . $e->getMessage()
        ];
    }
}

// Handle special case for getting labelers
if ($action === 'get_labelers') {
    $result = getAvailableLabelers();
    echo json_encode($result);
    exit;
}
?>