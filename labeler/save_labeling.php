<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

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

$functions = new Functions();
$labeler_id = $_SESSION['user_id'];
$document_id = $data['document_id'] ?? 0;

if (!$document_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
    exit();
}

// Validate document exists and is available for labeling
$document = $functions->getDocument($document_id);
if (!$document) {
    echo json_encode(['success' => false, 'message' => 'Document not found']);
    exit();
}

// Prepare step data
$step_data = [];

// Validate and sanitize important sentences
if (isset($data['important_sentences'])) {
    $sentences = $data['important_sentences'];
    if (is_array($sentences)) {
        // Validate that all indices are numeric and within reasonable range
        $valid_sentences = array_filter($sentences, function($index) {
            return is_numeric($index) && $index >= 0 && $index < 1000; // Reasonable limit
        });
        $step_data['important_sentences'] = array_map('intval', $valid_sentences);
    }
}

// Validate text style ID
if (isset($data['text_style_id'])) {
    $style_id = $data['text_style_id'];
    if (is_numeric($style_id)) {
        // Verify the style exists
        $styles = $functions->getTextStyles();
        $valid_style = false;
        foreach ($styles as $style) {
            if ($style['id'] == $style_id) {
                $valid_style = true;
                break;
            }
        }
        if ($valid_style) {
            $step_data['text_style_id'] = intval($style_id);
        }
    }
}

// Validate and sanitize edited summary
if (isset($data['edited_summary'])) {
    $summary = trim($data['edited_summary']);
    if (strlen($summary) <= 5000) { // Reasonable limit for summary length
        $step_data['edited_summary'] = $summary;
    } else {
        echo json_encode(['success' => false, 'message' => 'Summary is too long (max 5000 characters)']);
        exit();
    }
}

// Check if this is a finalization request
$is_finalize = ($data['action'] ?? '') === 'finalize';

// Validate required data for finalization
if ($is_finalize) {
    if (empty($step_data['important_sentences'])) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one important sentence']);
        exit();
    }
    
    if (empty($step_data['text_style_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please select a text style']);
        exit();
    }
    
    if (empty($step_data['edited_summary'])) {
        echo json_encode(['success' => false, 'message' => 'Please provide an edited summary']);
        exit();
    }
    
    // Additional validation for summary quality
    $summary = $step_data['edited_summary'];
    $word_count = str_word_count($summary);
    if ($word_count < 10) {
        echo json_encode(['success' => false, 'message' => 'Summary must be at least 10 words long']);
        exit();
    }
    
    if ($word_count > 500) {
        echo json_encode(['success' => false, 'message' => 'Summary is too long (max 500 words)']);
        exit();
    }
}

try {
    // Save the labeling data
    $result = $functions->saveLabelingStep($document_id, $labeler_id, $step_data);
    
    if ($result) {
        // Update document status if finalizing
        if ($is_finalize) {
            $functions->updateDocumentStatus($document_id, 'completed');
            
            // Log the completion
            error_log("Labeling completed - Document ID: $document_id, Labeler ID: $labeler_id");
        }
        
        $response = [
            'success' => true, 
            'message' => $is_finalize ? 'Labeling completed successfully' : 'Progress saved successfully'
        ];
        
        // Add progress information
        $response['progress'] = [
            'has_sentences' => !empty($step_data['important_sentences']),
            'has_style' => !empty($step_data['text_style_id']),
            'has_summary' => !empty($step_data['edited_summary']),
            'is_completed' => $is_finalize
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save labeling data']);
    }
} catch (Exception $e) {
    error_log("Save labeling error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while saving']);
}
?>