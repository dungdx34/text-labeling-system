<?php
require_once '../includes/auth.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$type = $_GET['type'] ?? 'all';

// Database connection
$conn = new mysqli('localhost', 'root', '', 'text_labeling_system');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $type . '_export_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

switch ($type) {
    case 'users':
        fputcsv($output, ['ID', 'Username', 'Full Name', 'Email', 'Role', 'Status', 'Created']);
        
        $result = $conn->query("SELECT id, username, full_name, email, role, status, created_at FROM users ORDER BY created_at DESC");
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        break;
        
    case 'documents':
        fputcsv($output, ['ID', 'Title', 'Upload Date', 'Uploaded By', 'Labelings Count']);
        
        $result = $conn->query("
            SELECT d.id, d.title, d.created_at, u.username as uploaded_by,
                   COUNT(l.id) as labeling_count
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            LEFT JOIN labelings l ON d.id = l.document_id
            GROUP BY d.id
            ORDER BY d.created_at DESC
        ");
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        break;
        
    case 'labelings':
        fputcsv($output, ['ID', 'Status', 'Assigned Date', 'Completed Date', 'Labeler', 'Document Title']);
        
        $result = $conn->query("
            SELECT l.id, l.status, l.assigned_at, l.completed_at,
                   u.username as labeler, d.title as document_title
            FROM labelings l
            LEFT JOIN users u ON l.labeler_id = u.id
            LEFT JOIN documents d ON l.document_id = d.id
            ORDER BY l.created_at DESC
        ");
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        break;
        
    case 'groups':
        fputcsv($output, ['ID', 'Title', 'Type', 'Status', 'Documents Count', 'Created Date']);
        
        // Check if table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'document_groups'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $conn->query("
                SELECT dg.id, dg.title, dg.group_type, dg.status, 
                       COUNT(d.id) as document_count, dg.created_at
                FROM document_groups dg
                LEFT JOIN documents d ON dg.id = d.group_id
                GROUP BY dg.id
                ORDER BY dg.created_at DESC
            ");
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
        } else {
            fputcsv($output, ['No data', 'Table not exists', '', '', '', '']);
        }
        break;
        
    default:
        // Export basic statistics
        fputcsv($output, ['Statistic', 'Count']);
        
        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        $count = $result->fetch_assoc()['count'];
        fputcsv($output, ['Active Users', $count]);
        
        $result = $conn->query("SELECT COUNT(*) as count FROM documents");
        $count = $result->fetch_assoc()['count'];
        fputcsv($output, ['Total Documents', $count]);
        
        $result = $conn->query("SELECT COUNT(*) as count FROM labelings");
        $count = $result->fetch_assoc()['count'];
        fputcsv($output, ['Total Labelings', $count]);
        
        break;
}

fclose($output);
$conn->close();
?>