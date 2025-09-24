<?php
/**
 * Improved Upload Handler for Multi-Document Support
 * File: admin/upload_handler.php
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Ensure admin access
if (!$auth || !$auth->isLoggedIn() || $auth->getRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    if (!isset($_FILES['jsonl_file']) || $_FILES['jsonl_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }
    
    $uploadedFile = $_FILES['jsonl_file'];
    $filePath = $uploadedFile['tmp_name'];
    
    // Validate file type
    $fileInfo = pathinfo($uploadedFile['name']);
    if (strtolower($fileInfo['extension']) !== 'jsonl') {
        throw new Exception('Only JSONL files are allowed');
    }
    
    // Read and process JSONL file
    $fileContent = file_get_contents($filePath);
    if ($fileContent === false) {
        throw new Exception('Could not read uploaded file');
    }
    
    $lines = explode("\n", trim($fileContent));
    $processedCount = 0;
    $errors = [];
    $multiDocumentGroupId = null;
    
    // Start transaction
    $db->beginTransaction();
    
    foreach ($lines as $lineNumber => $line) {
        if (empty(trim($line))) {
            continue;
        }
        
        try {
            $jsonData = json_decode($line, true);
            if ($jsonData === null) {
                $errors[] = "Line " . ($lineNumber + 1) . ": Invalid JSON";
                continue;
            }
            
            // Extract required fields with fallbacks
            $title = $jsonData['title'] ?? 'Untitled Document ' . ($lineNumber + 1);
            $content = $jsonData['content'] ?? $jsonData['text'] ?? '';
            $aiSummary = $jsonData['ai_summary'] ?? $jsonData['summary'] ?? '';
            $type = $jsonData['type'] ?? 'single';
            $groupName = $jsonData['group_name'] ?? null;
            
            // Validate required fields
            if (empty($content)) {
                $errors[] = "Line " . ($lineNumber + 1) . ": Missing content";
                continue;
            }
            
            // Handle multi-document grouping
            if ($type === 'multi') {
                if ($multiDocumentGroupId === null) {
                    // Create new document group
                    $groupName = $groupName ?? 'Multi-Document Group ' . date('Y-m-d H:i:s');
                    $groupDescription = $jsonData['group_description'] ?? 'Auto-generated group for multi-document upload';
                    
                    $stmt = $db->prepare("
                        INSERT INTO document_groups (group_name, description, created_by, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$groupName, $groupDescription, $auth->getUserId()]);
                    $multiDocumentGroupId = $db->lastInsertId();
                }
            }
            
            // Insert document with all required columns
            $stmt = $db->prepare("
                INSERT INTO documents (
                    title, 
                    content, 
                    ai_summary, 
                    type, 
                    uploaded_by, 
                    status, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([
                $title,
                $content,
                $aiSummary,
                $type,
                $auth->getUserId()
            ]);
            
            $documentId = $db->lastInsertId();
            
            // If multi-document, add to group
            if ($type === 'multi' && $multiDocumentGroupId) {
                $stmt = $db->prepare("
                    INSERT INTO document_group_items (
                        group_id, 
                        document_id, 
                        sort_order, 
                        individual_ai_summary,
                        created_at
                    ) VALUES (?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $multiDocumentGroupId,
                    $documentId,
                    $processedCount,
                    $aiSummary
                ]);
                
                // Update group's total documents count
                $stmt = $db->prepare("
                    UPDATE document_groups 
                    SET total_documents = (
                        SELECT COUNT(*) FROM document_group_items WHERE group_id = ?
                    ),
                    combined_ai_summary = CONCAT(
                        COALESCE(combined_ai_summary, ''), 
                        IF(combined_ai_summary IS NULL OR combined_ai_summary = '', '', '\n\n'),
                        ?
                    )
                    WHERE id = ?
                ");
                
                $stmt->execute([$multiDocumentGroupId, $aiSummary, $multiDocumentGroupId]);
            }
            
            $processedCount++;
            
        } catch (Exception $e) {
            $errors[] = "Line " . ($lineNumber + 1) . ": " . $e->getMessage();
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => "Successfully processed $processedCount documents",
        'processed_count' => $processedCount,
        'total_lines' => count($lines),
        'errors' => $errors
    ];
    
    if ($multiDocumentGroupId) {
        $response['multi_document_group_id'] = $multiDocumentGroupId;
        $response['message'] .= " in multi-document group";
    }
    
    // Log activity
    $stmt = $db->prepare("
        INSERT INTO activity_logs (
            user_id, 
            action, 
            description, 
            table_name, 
            record_id,
            created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $auth->getUserId(),
        'document_upload',
        "Uploaded $processedCount documents" . ($multiDocumentGroupId ? " (multi-document)" : ""),
        'documents',
        $multiDocumentGroupId ?? $documentId ?? null
    ]);
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'processed_count' => $processedCount ?? 0
    ]);
}

/**
 * Helper function to validate and sanitize document data
 */
function validateDocumentData($data) {
    $required = ['content'];
    $missing = [];
    
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }
    
    return [
        'title' => trim($data['title'] ?? 'Untitled'),
        'content' => trim($data['content']),
        'ai_summary' => trim($data['ai_summary'] ?? $data['summary'] ?? ''),
        'type' => in_array($data['type'] ?? 'single', ['single', 'multi']) ? $data['type'] : 'single'
    ];
}

/**
 * Helper function to create sample JSONL format documentation
 */
function getSampleJsonlFormat() {
    return [
        'single_document' => [
            'title' => 'Document Title',
            'content' => 'Document content here...',
            'ai_summary' => 'AI generated summary',
            'type' => 'single'
        ],
        'multi_document' => [
            'title' => 'Document 1 Title',
            'content' => 'First document content...',
            'ai_summary' => 'Summary for document 1',
            'type' => 'multi',
            'group_name' => 'Related Documents Group',
            'group_description' => 'Description of the document group'
        ]
    ];
}

// If accessed directly for documentation
if (isset($_GET['help'])) {
    echo json_encode([
        'upload_endpoint' => '/admin/upload_handler.php',
        'method' => 'POST',
        'content_type' => 'multipart/form-data',
        'file_field' => 'jsonl_file',
        'supported_formats' => getSampleJsonlFormat(),
        'notes' => [
            'Each line in JSONL file should be valid JSON',
            'Multi-document uploads will be grouped automatically',
            'All documents require content field',
            'ai_summary field is optional but recommended'
        ]
    ], JSON_PRETTY_PRINT);
}
?>