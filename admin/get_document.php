<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use absolute paths
$auth_path = __DIR__ . '/../includes/auth.php';
$functions_path = __DIR__ . '/../includes/functions.php';

if (!file_exists($auth_path) || !file_exists($functions_path)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'System files not found. Please run quick-fix.php']);
    exit();
}

require_once $auth_path;
require_once $functions_path;

try {
    $auth = new Auth();
    $auth->requireRole('admin');

    header('Content-Type: application/json');

    $document_id = $_GET['id'] ?? 0;

    if (!$document_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
        exit();
    }

    $functions = new Functions();
    $document = $functions->getDocument($document_id);

    if ($document) {
        echo json_encode([
            'success' => true, 
            'document' => $document
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Document not found'
        ]);
    }

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>