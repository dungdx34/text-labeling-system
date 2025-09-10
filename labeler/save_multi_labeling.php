<?php
// labeler/save_multi_labeling.php - Save multi-document labeling data
require_once '../includes/auth.php';
require_once '../includes/enhanced_functions.php';

$auth = new Auth();
$auth->requireRole('labeler');

header('Content-Type: application/json');

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

$enhancedFunctions = new EnhancedFunctions();
$labeler_id = $_SESSION['user_id'];
$group_id = $data['group_id'] ?? 0;

if (!$group_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid group ID']);
    exit();
}

// Validate group exists and is available for labeling
$group = $enhancedFunctions->getDocumentGroup($group_id);
if (!$group) {
    echo json_encode(['success' => false, 'message' => 'Document group not found']);
    exit();
}

// Prepare labeling data
$labeling_data = [];

// Validate and sanitize selected sentences for each document
if (isset($data['document_sentences'])) {
    $document_sentences = $data['document_sentences'];
    
    if (is_array($document_sentences)) {
        $validated_sentences = [];
        
        foreach ($document_sentences as $doc_index => $sentences) {
            if (is_numeric($doc_index) && is_array($sentences)) {
                $validated_doc_sentences = [];
                
                foreach ($sentences as $sentence_index) {
                    if (is_numeric($sentence_index) && $sentence_index >= 0) {
                        $validated_doc_sentences[] = intval($sentence_index);
                    }
                }
                
                if (!empty($validated_doc_sentences)) {
                    $validated_sentences[intval($doc_index)] = $validated_doc_sentences;
                }
            }
        }
        
        $labeling_data['document_sentences'] = $validated_sentences;
    }
}

// Validate text style ID
if (isset($data['text_style_id'])) {
    $style_id = $data['text_style_id'];
    if (is_numeric($style_id)) {
        // Verify the style exists
        $functions = new Functions();
        $styles = $functions->getTextStyles();
        $valid_style = false;
        
        foreach ($styles as $style) {
            if ($style['id'] == $style_id) {
                $valid_style = true;
                break;
            }
        }
        
        if ($valid_style) {
            $labeling_data['text_style_id'] = intval($style_id);
        }
    }
}

// Validate and sanitize edited summary
if (isset($data['edited_summary'])) {
    $summary = trim($data['edited_summary']);
    if (strlen($summary) <= 10000) { // Reasonable limit for summary length
        $labeling_data['edited_summary'] = $summary;
    } else {
        echo json_encode(['success' => false, 'message' => 'Summary is too long (max 10000 characters)']);
        exit();
    }
}

// Check if this is a finalization request
$is_finalize = ($data['action'] ?? '') === 'finalize';

// Validate required data for finalization
if ($is_finalize) {
    // Check if any sentences are selected
    $total_selected = 0;
    if (isset($labeling_data['document_sentences'])) {
        foreach ($labeling_data['document_sentences'] as $sentences) {
            $total_selected += count($sentences);
        }
    }
    
    if ($total_selected === 0) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one important sentence from the documents']);
        exit();
    }
    
    if (empty($labeling_data['text_style_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please select a text style']);
        exit();
    }
    
    if (empty($labeling_data['edited_summary'])) {
        echo json_encode(['success' => false, 'message' => 'Please provide an edited summary']);
        exit();
    }
    
    // Additional validation for summary quality
    $summary = $labeling_data['edited_summary'];
    $word_count = str_word_count($summary);
    
    if ($word_count < 15) {
        echo json_encode(['success' => false, 'message' => 'Summary must be at least 15 words long']);
        exit();
    }
    
    if ($word_count > 1000) {
        echo json_encode(['success' => false, 'message' => 'Summary is too long (max 1000 words)']);
        exit();
    }
    
    // Set status for finalization
    $labeling_data['status'] = 'completed';
} else {
    $labeling_data['status'] = 'pending';
}

try {
    // Save the multi-document labeling data
    $result = $enhancedFunctions->saveMultiDocumentLabeling($group_id, $labeler_id, $labeling_data);
    
    if ($result) {
        // Update group status if finalizing
        if ($is_finalize) {
            $enhancedFunctions->updateDocumentGroupStatus($group_id, 'completed');
            
            // Log the completion
            error_log("Multi-document labeling completed - Group ID: $group_id, Labeler ID: $labeler_id");
        }
        
        $response = [
            'success' => true, 
            'message' => $is_finalize ? 'Multi-document labeling completed successfully' : 'Progress saved successfully'
        ];
        
        // Add progress information
        $total_selected = 0;
        if (isset($labeling_data['document_sentences'])) {
            foreach ($labeling_data['document_sentences'] as $sentences) {
                $total_selected += count($sentences);
            }
        }
        
        $documents_processed = isset($labeling_data['document_sentences']) 
            ? count($labeling_data['document_sentences']) 
            : 0;
        
        $response['progress'] = [
            'total_sentences_selected' => $total_selected,
            'documents_processed' => $documents_processed,
            'has_style' => !empty($labeling_data['text_style_id']),
            'has_summary' => !empty($labeling_data['edited_summary']),
            'is_completed' => $is_finalize
        ];
        
        // Add summary statistics if available
        if (!empty($labeling_data['edited_summary'])) {
            $summary = $labeling_data['edited_summary'];
            $response['summary_stats'] = [
                'word_count' => str_word_count($summary),
                'char_count' => mb_strlen($summary, 'UTF-8'),
                'sentence_count' => count(preg_split('/[.!?]+/', $summary, -1, PREG_SPLIT_NO_EMPTY)),
                'paragraph_count' => count(array_filter(explode("\n", $summary)))
            ];
        }
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save multi-document labeling data']);
    }
} catch (Exception $e) {
    error_log("Save multi-document labeling error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while saving: ' . $e->getMessage()]);
}
?>