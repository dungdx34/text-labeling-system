<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Very simple upload test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['jsonl_file'])) {
    $file = $_FILES['jsonl_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
        $content = file_get_contents($file['tmp_name']);
        
        // Try to insert a simple test record
        try {
            $query = "INSERT INTO documents (title, content, ai_summary, type, created_by, created_at) 
                     VALUES ('Test Upload', :content, 'Test AI Summary', 'single', :user_id, NOW())";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $message = "✅ Test upload successful! Document ID: " . $db->lastInsertId();
            } else {
                $error = "❌ Database insert failed: " . print_r($stmt->errorInfo(), true);
            }
        } catch (Exception $e) {
            $error = "❌ Exception: " . $e->getMessage();
        }
    } else {
        $error = "❌ File upload failed. Error: " . $file['error'] . ", Size: " . $file['size'];
    }
}

// Check current database content
try {
    $stmt = $db->prepare("SELECT id, title, type, created_at FROM documents ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $recent_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_docs = [];
    $error .= "<br>Database query error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Upload Test</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4>Simple Upload Test</h4>
                        <small class="text-muted">Test cơ bản để kiểm tra upload và database insert</small>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Upload bất kỳ file nào (sẽ được lưu như test content):</label>
                                <input type="file" class="form-control" name="jsonl_file" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Test Upload</button>
                        </form>
                        
                        <hr>
                        
                        <h5>Database Status:</h5>
                        <p><strong>User ID:</strong> <?php echo $_SESSION['user_id']; ?></p>
                        <p><strong>Username:</strong> <?php echo $_SESSION['username']; ?></p>
                        
                        <h6>Recent Documents:</h6>
                        <?php if (!empty($recent_docs)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_docs as $doc): ?>
                                            <tr>
                                                <td><?php echo $doc['id']; ?></td>
                                                <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                                <td><?php echo $doc['type']; ?></td>
                                                <td><?php echo $doc['created_at']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Không có documents nào trong database</p>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="upload_debug.php" class="btn btn-outline-info">Back to Full Debug</a>
                            <a href="upload.php" class="btn btn-outline-secondary">Back to Upload</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>