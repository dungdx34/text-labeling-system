<?php
// admin/jsonl_handler.php - Complete Enhanced JSONL Handler with Optional Query Support

function processJsonlUploadEnhanced($file, $pdo) {
    $content = file_get_contents($file['tmp_name']);
    $lines = explode("\n", trim($content));
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    $warnings = [];
    $auto_generated_titles = 0;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($lines as $line_number => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $data = json_decode($line, true);
            if ($data === null) {
                $errors[] = "Dòng " . ($line_number + 1) . ": JSON không hợp lệ - " . json_last_error_msg();
                $error_count++;
                continue;
            }
            
            // Validate required fields (only summary and document are required)
            if (!isset($data['summary']) || !isset($data['document'])) {
                $errors[] = "Dòng " . ($line_number + 1) . ": Thiếu trường bắt buộc (summary, document). Query là tùy chọn.";
                $error_count++;
                continue;
            }
            
            // Validate data types
            if (!is_string($data['summary']) || empty(trim($data['summary']))) {
                $errors[] = "Dòng " . ($line_number + 1) . ": Trường 'summary' phải là string không rỗng";
                $error_count++;
                continue;
            }
            
            if (!is_array($data['document']) || empty($data['document'])) {
                $errors[] = "Dòng " . ($line_number + 1) . ": Trường 'document' phải là array không rỗng";
                $error_count++;
                continue;
            }
            
            // Handle optional query field
            $original_query = isset($data['query']) ? trim($data['query']) : '';
            
            if (empty($original_query)) {
                // Auto-generate title based on content or timestamp
                $data['query'] = generateAutoTitle($data, $line_number + 1);
                $auto_generated_titles++;
                $warnings[] = "Dòng " . ($line_number + 1) . ": Tự động tạo tiêu đề: '" . mb_substr($data['query'], 0, 80) . "'";
            }
            
            // Process based on document type
            if (is_array($data['document']) && count($data['document']) > 1) {
                // Multi-document
                $result = insertMultiDocumentEnhanced($data, $pdo, $line_number + 1);
            } else {
                // Single document
                $result = insertSingleDocumentEnhanced($data, $pdo, $line_number + 1);
            }
            
            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "Dòng " . ($line_number + 1) . ": " . $result['error'];
            }
        }
        
        $pdo->commit();
        
        $message = "Upload thành công: $success_count bản ghi";
        if ($error_count > 0) {
            $message .= ", $error_count lỗi";
        }
        if ($auto_generated_titles > 0) {
            $message .= ", $auto_generated_titles tiêu đề được tự động tạo";
        }
        
        return [
            'success' => true,
            'message' => $message,
            'errors' => $errors,
            'warnings' => $warnings,
            'stats' => [
                'success_count' => $success_count,
                'error_count' => $error_count,
                'auto_generated_titles' => $auto_generated_titles,
                'total_processed' => $success_count + $error_count
            ]
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("JSONL upload error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Lỗi database: ' . $e->getMessage(),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
}

function generateAutoTitle($data, $line_number) {
    // Try to extract meaningful title from content
    if (!empty($data['document']) && is_array($data['document'])) {
        $first_doc = $data['document'][0];
        
        // Look for patterns that might be titles
        $patterns = [
            '/^([A-ZÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴĐÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸ][^.!?]*[.!?]?)/',  // First sentence starting with capital
            '/Điều\s+\d+[.\s]+([^.!?]+)/',  // Vietnamese legal articles
            '/Chương\s+[IVX\d]+[.\s]+([^.!?]+)/',  // Vietnamese chapters
            '/Mục\s+\d+[.\s]+([^.!?]+)/',  // Vietnamese sections
            '/^([^.!?]{10,100}[^.!?])/',    // First 10-100 chars without punctuation
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($first_doc), $matches)) {
                $title = trim($matches[1]);
                if (mb_strlen($title) >= 10 && mb_strlen($title) <= 200) {
                    return $title;
                }
            }
        }
        
        // Try to extract from summary if document extraction failed
        if (!empty($data['summary'])) {
            $summary_patterns = [
                '/^([^.!?]{10,80}[^.!?])/',  // First meaningful part of summary
                '/([A-ZÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴĐÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸ][^.!?]{10,80})/u'
            ];
            
            foreach ($summary_patterns as $pattern) {
                if (preg_match($pattern, trim($data['summary']), $matches)) {
                    $title = trim($matches[1]);
                    if (mb_strlen($title) >= 10 && mb_strlen($title) <= 200) {
                        return $title . '...';
                    }
                }
            }
        }
        
        // Fallback: use first 50 characters from document
        $title = mb_substr(trim($first_doc), 0, 50, 'UTF-8');
        if (mb_strlen($title) > 10) {
            return $title . '...';
        }
    }
    
    // Ultimate fallback: timestamp-based title
    return 'Văn bản không có tiêu đề - ' . date('Y-m-d H:i:s') . ' #' . $line_number;
}

function insertSingleDocumentEnhanced($data, $pdo, $line_number) {
    try {
        // Validate document content
        $document_content = is_array($data['document']) ? $data['document'][0] : $data['document'];
        
        if (empty(trim($document_content))) {
            return [
                'success' => false,
                'error' => 'Nội dung văn bản không được rỗng'
            ];
        }
        
        // Insert into documents table
        $stmt = $pdo->prepare("
            INSERT INTO documents (title, content, type, created_by, created_at, updated_at) 
            VALUES (?, ?, 'single', 1, NOW(), NOW())
        ");
        
        $stmt->execute([
            $data['query'],
            $document_content
        ]);
        $document_id = $pdo->lastInsertId();
        
        // Insert AI summary
        $stmt = $pdo->prepare("
            INSERT INTO ai_summaries (document_id, summary, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$document_id, $data['summary']]);
        
        return ['success' => true, 'document_id' => $document_id];
        
    } catch (Exception $e) {
        error_log("Insert single document error (line $line_number): " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Lỗi khi lưu văn bản đơn: ' . $e->getMessage()
        ];
    }
}

function insertMultiDocumentEnhanced($data, $pdo, $line_number) {
    try {
        // Validate all documents have content
        foreach ($data['document'] as $index => $doc_content) {
            if (empty(trim($doc_content))) {
                return [
                    'success' => false,
                    'error' => "Văn bản thứ " . ($index + 1) . " không được rỗng"
                ];
            }
        }
        
        // Create document group
        $stmt = $pdo->prepare("
            INSERT INTO document_groups (title, description, created_by, created_at, updated_at) 
            VALUES (?, ?, 1, NOW(), NOW())
        ");
        
        $group_title = $data['query'];
        $group_description = "Nhóm " . count($data['document']) . " văn bản: " . $group_title;
        
        $stmt->execute([$group_title, $group_description]);
        $group_id = $pdo->lastInsertId();
        
        // Insert individual documents
        $document_ids = [];
        foreach ($data['document'] as $index => $doc_content) {
            $stmt = $pdo->prepare("
                INSERT INTO documents (title, content, type, group_id, created_by, created_at, updated_at) 
                VALUES (?, ?, 'multi', ?, 1, NOW(), NOW())
            ");
            
            $doc_title = $group_title . " - Phần " . ($index + 1);
            $stmt->execute([$doc_title, $doc_content, $group_id]);
            $document_ids[] = $pdo->lastInsertId();
        }
        
        // Insert group AI summary
        $stmt = $pdo->prepare("
            INSERT INTO ai_summaries (group_id, summary, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$group_id, $data['summary']]);
        
        return [
            'success' => true, 
            'group_id' => $group_id,
            'document_ids' => $document_ids
        ];
        
    } catch (Exception $e) {
        error_log("Insert multi document error (line $line_number): " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Lỗi khi lưu nhóm văn bản: ' . $e->getMessage()
        ];
    }
}

function validateJsonlFile($file_path) {
    $validation_errors = [];
    $validation_warnings = [];
    $line_count = 0;
    $valid_lines = 0;
    
    if (!file_exists($file_path)) {
        return [
            'valid' => false,
            'errors' => ['File không tồn tại'],
            'warnings' => [],
            'stats' => ['total_lines' => 0, 'valid_lines' => 0]
        ];
    }
    
    $content = file_get_contents($file_path);
    $lines = explode("\n", $content);
    
    foreach ($lines as $line_number => $line) {
        $line = trim($line);
        $line_count++;
        
        if (empty($line)) continue;
        
        // Check if line is valid JSON
        $data = json_decode($line, true);
        if ($data === null) {
            $validation_errors[] = "Dòng $line_count: JSON không hợp lệ - " . json_last_error_msg();
            continue;
        }
        
        // Check required fields
        if (!isset($data['summary']) || !isset($data['document'])) {
            $validation_errors[] = "Dòng $line_count: Thiếu trường bắt buộc (summary hoặc document)";
            continue;
        }
        
        // Check optional query field
        if (!isset($data['query']) || empty(trim($data['query']))) {
            $validation_warnings[] = "Dòng $line_count: Không có query, sẽ tự động tạo tiêu đề";
        }
        
        // Check data types
        if (!is_string($data['summary'])) {
            $validation_errors[] = "Dòng $line_count: 'summary' phải là string";
            continue;
        }
        
        if (!is_array($data['document'])) {
            $validation_errors[] = "Dòng $line_count: 'document' phải là array";
            continue;
        }
        
        if (empty($data['document'])) {
            $validation_errors[] = "Dòng $line_count: 'document' không được rỗng";
            continue;
        }
        
        $valid_lines++;
    }
    
    return [
        'valid' => empty($validation_errors),
        'errors' => $validation_errors,
        'warnings' => $validation_warnings,
        'stats' => [
            'total_lines' => $line_count,
            'valid_lines' => $valid_lines,
            'error_lines' => count($validation_errors),
            'warning_lines' => count($validation_warnings)
        ]
    ];
}

// Helper function to log upload activity
function logUploadActivity($pdo, $user_id, $file_info, $result) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO upload_logs 
            (uploaded_by, file_name, file_size, records_processed, records_success, records_failed, 
             upload_type, status, error_details, upload_date, completed_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $upload_type = 'mixed'; // Can be single, multi, or mixed
        $status = $result['success'] ? 'completed' : 'failed';
        $error_details = !empty($result['errors']) ? json_encode($result['errors']) : null;
        
        $records_processed = isset($result['stats']['total_processed']) ? $result['stats']['total_processed'] : 0;
        $records_success = isset($result['stats']['success_count']) ? $result['stats']['success_count'] : 0;
        $records_failed = isset($result['stats']['error_count']) ? $result['stats']['error_count'] : 0;
        
        $stmt->execute([
            $user_id,
            $file_info['name'],
            $file_info['size'],
            $records_processed,
            $records_success,
            $records_failed,
            $upload_type,
            $status,
            $error_details
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        error_log("Upload log error: " . $e->getMessage());
        return false;
    }
}

// Function to preview JSONL file before upload
function previewJsonlFile($file_path, $max_preview = 3) {
    $preview_data = [];
    $content = file_get_contents($file_path);
    $lines = explode("\n", trim($content));
    
    $preview_count = 0;
    foreach ($lines as $line_number => $line) {
        $line = trim($line);
        if (empty($line) || $preview_count >= $max_preview) continue;
        
        $data = json_decode($line, true);
        if ($data !== null) {
            // Sanitize for display
            $preview_item = [
                'line' => $line_number + 1,
                'query' => isset($data['query']) ? mb_substr($data['query'], 0, 100) : '[Sẽ tự động tạo]',
                'summary' => mb_substr($data['summary'], 0, 200),
                'document_count' => is_array($data['document']) ? count($data['document']) : 0,
                'type' => is_array($data['document']) && count($data['document']) > 1 ? 'multi' : 'single',
                'has_query' => isset($data['query']) && !empty(trim($data['query']))
            ];
            
            $preview_data[] = $preview_item;
            $preview_count++;
        }
    }
    
    return $preview_data;
}

// Function to get file statistics
function getJsonlFileStats($file_path) {
    $stats = [
        'total_lines' => 0,
        'valid_json_lines' => 0,
        'with_query' => 0,
        'without_query' => 0,
        'single_documents' => 0,
        'multi_documents' => 0,
        'file_size' => filesize($file_path)
    ];
    
    $content = file_get_contents($file_path);
    $lines = explode("\n", trim($content));
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $stats['total_lines']++;
        
        $data = json_decode($line, true);
        if ($data !== null) {
            $stats['valid_json_lines']++;
            
            // Check query
            if (isset($data['query']) && !empty(trim($data['query']))) {
                $stats['with_query']++;
            } else {
                $stats['without_query']++;
            }
            
            // Check document type
            if (isset($data['document']) && is_array($data['document'])) {
                if (count($data['document']) > 1) {
                    $stats['multi_documents']++;
                } else {
                    $stats['single_documents']++;
                }
            }
        }
    }
    
    return $stats;
}
?>