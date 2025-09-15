<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check labeler authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'labeler') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $group_id = (int)$input['group_id'];
    $selected_sentences = $input['selected_sentences'] ?? [];
    $text_style = $input['text_style'] ?? '';
    $edited_summary = $input['edited_summary'] ?? '';
    $is_draft = (bool)($input['is_draft'] ?? true);
    
    if (!$group_id) {
        throw new Exception('Group ID is required');
    }
    
    // Verify that this group is assigned to the current labeler
    $group = getDocumentGroup($group_id);
    if (!$group || $group['assigned_labeler'] != $user_id) {
        throw new Exception('Group not found or not assigned to you');
    }
    
    // Verify this is a multi-document group
    if ($group['group_type'] !== 'multi') {
        throw new Exception('This is not a multi-document group');
    }
    
    // Get documents in group to get document_id (use first document)
    $documents = getDocumentsByGroup($group_id);
    $document_id = !empty($documents) ? $documents[0]['id'] : null;
    
    // Save labeling using the multi type
    $labeling_id = saveLabeling(
        $user_id,
        $group_id,
        $document_id,
        $selected_sentences,
        $text_style,
        $edited_summary,
        'multi', // labeling_type
        !$is_draft // is_completed = opposite of is_draft
    );
    
    // Log activity
    $action = $is_draft ? 'save_multi_draft' : 'complete_multi_labeling';
    $description = $is_draft ? 'Saved draft for multi-document labeling' : 'Completed multi-document labeling task';
    logActivity($user_id, $action, 'labeling', $labeling_id, $description);
    
    echo json_encode([
        'success' => true,
        'labeling_id' => $labeling_id,
        'message' => $is_draft ? 'Multi-document draft saved successfully' : 'Multi-document labeling completed successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>