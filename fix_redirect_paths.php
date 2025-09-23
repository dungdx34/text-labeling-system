<?php
/**
 * Fix Redirect Paths for Localhost - Text Labeling System
 * This will fix the redirect paths to work with localhost/text-labeling-system/
 */

echo "<h1>🔧 Fix Redirect Paths</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

// Check current setup
echo "<h2>🔍 Current Setup Analysis</h2>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "<br>";

// Detect project path
$script_path = $_SERVER['SCRIPT_NAME'];
$project_path = dirname($script_path);
if ($project_path === '/') {
    $project_path = '';
}

echo "Detected Project Path: <strong>$project_path</strong><br>";

// Check if auth.php exists
$auth_file = 'includes/auth.php';
if (file_exists($auth_file)) {
    echo "✅ Auth file found: $auth_file<br>";
    
    // Read current content
    $content = file_get_contents($auth_file);
    
    // Check current redirect URLs
    if (strpos($content, "'/admin/dashboard.php'") !== false) {
        echo "❌ Found absolute paths (starting with /) in redirect URLs<br>";
        echo "This causes: http://localhost/admin/dashboard.php instead of http://localhost/text-labeling-system/admin/dashboard.php<br>";
        
        if (isset($_POST['fix_paths'])) {
            echo "<br><h2>🔧 Fixing Redirect Paths</h2>";
            
            // Fix the redirect URLs
            $fixed_content = str_replace(
                [
                    "'/admin/dashboard.php'",
                    "'/labeler/dashboard.php'",
                    "'/reviewer/dashboard.php'",
                    "'/index.php'",
                    "'/login.php'",
                    "'/unauthorized.php'"
                ],
                [
                    "'admin/dashboard.php'",
                    "'labeler/dashboard.php'",
                    "'reviewer/dashboard.php'",
                    "'index.php'",
                    "'login.php'",
                    "'unauthorized.php'"
                ],
                $content
            );
            
            // Also fix other redirect locations
            $fixed_content = str_replace(
                [
                    "header('Location: /login.php",
                    "header('Location: /unauthorized.php')"
                ],
                [
                    "header('Location: login.php",
                    "header('Location: unauthorized.php')"
                ],
                $fixed_content
            );
            
            // Backup original file
            $backup_file = 'includes/auth.php.backup.' . date('Y-m-d_H-i-s');
            copy($auth_file, $backup_file);
            echo "✅ Backed up original file to: $backup_file<br>";
            
            // Write fixed content
            if (file_put_contents($auth_file, $fixed_content)) {
                echo "✅ <strong>Successfully fixed redirect paths!</strong><br>";
                echo "Now URLs will be relative (e.g., 'admin/dashboard.php' instead of '/admin/dashboard.php')<br>";
                
                echo "<br><h3>🧪 Test Login Now</h3>";
                echo "<a href='login.php' style='padding: 10px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>🔑 Test Login</a><br>";
                
            } else {
                echo "❌ Failed to write fixed content<br>";
            }
        }
        
    } else {
        echo "✅ Redirect URLs appear to be using relative paths already<br>";
    }
    
} else {
    echo "❌ Auth file not found: $auth_file<br>";
}

// Also create dashboard files quickly
if (isset($_POST['create_dashboards'])) {
    echo "<br><h2>🏗️ Creating Dashboard Files</h2>";
    
    $dashboard_templates = [
        'admin/dashboard.php' => '<?php
require_once "../includes/auth.php";
$auth->requireLogin(["admin"]);
$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-danger">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-shield-alt me-2"></i>Admin Dashboard</span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
            </a>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h1 class="card-title">
                            <i class="fas fa-user-shield text-danger me-3"></i>
                            Chào mừng, <?= htmlspecialchars($user["full_name"]) ?>!
                        </h1>
                        <p class="card-text">Bạn đã đăng nhập thành công với vai trò <strong>Admin</strong></p>
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h5><i class="fas fa-users me-2"></i>Quản lý người dùng</h5>
                                        <p>Tạo và quản lý tài khoản</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h5><i class="fas fa-upload me-2"></i>Upload dữ liệu</h5>
                                        <p>Tải lên văn bản và tóm tắt</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h5><i class="fas fa-chart-bar me-2"></i>Báo cáo</h5>
                                        <p>Xem thống kê hệ thống</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>',

        'labeler/dashboard.php' => '<?php
require_once "../includes/auth.php";
$auth->requireLogin(["labeler"]);
$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Labeler Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-success">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-tags me-2"></i>Labeler Dashboard</span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
            </a>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title">
                    <i class="fas fa-tags text-success me-3"></i>
                    Chào mừng, <?= htmlspecialchars($user["full_name"]) ?>!
                </h1>
                <p class="card-text">Bạn đã đăng nhập thành công với vai trò <strong>Labeler</strong></p>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i>Nhiệm vụ của bạn:</h5>
                    <ul>
                        <li>Gán nhãn văn bản theo hướng dẫn</li>
                        <li>Chọn các câu quan trọng trong văn bản</li>
                        <li>Chỉnh sửa bản tóm tắt AI</li>
                    </ul>
                </div>
                <div class="mt-4">
                    <h5>📋 Danh sách nhiệm vụ</h5>
                    <div class="text-muted">Hiện tại chưa có nhiệm vụ nào được giao.</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>',

        'reviewer/dashboard.php' => '<?php
require_once "../includes/auth.php";
$auth->requireLogin(["reviewer"]);
$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Reviewer Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-check-circle me-2"></i>Reviewer Dashboard</span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i>Đăng xuất
            </a>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title">
                    <i class="fas fa-check-circle text-primary me-3"></i>
                    Chào mừng, <?= htmlspecialchars($user["full_name"]) ?>!
                </h1>
                <p class="card-text">Bạn đã đăng nhập thành công với vai trò <strong>Reviewer</strong></p>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-clipboard-check me-2"></i>Nhiệm vụ của bạn:</h5>
                    <ul>
                        <li>Review và đánh giá công việc gán nhãn</li>
                        <li>Đưa ra phản hồi cho labeler</li>
                        <li>Phê duyệt hoặc yêu cầu chỉnh sửa</li>
                    </ul>
                </div>
                <div class="mt-4">
                    <h5>📝 Danh sách cần review</h5>
                    <div class="text-muted">Hiện tại chưa có công việc nào cần review.</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>'
    ];
    
    foreach ($dashboard_templates as $path => $content) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "✅ Created directory: $dir<br>";
        }
        
        if (file_put_contents($path, $content)) {
            echo "✅ Created: $path<br>";
        } else {
            echo "❌ Failed to create: $path<br>";
        }
    }
}

echo "</div>";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Fix Redirect Paths</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .action-form { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .btn { padding: 15px 25px; margin: 10px 5px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-primary { background: #007bff; color: white; }
        .btn:hover { opacity: 0.8; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 15px 0; }
    </style>
</head>
<body>

<div class="action-form">
    <h3>🔧 Fix Localhost Redirect Issues</h3>
    <div class="warning">
        <strong>⚠️ Problem:</strong> Redirect URLs use absolute paths (/) instead of relative paths.<br>
        <strong>Result:</strong> Redirects to http://localhost/admin/ instead of http://localhost/text-labeling-system/admin/
    </div>
    
    <form method="POST">
        <button type="submit" name="fix_paths" class="btn btn-danger">
            🔧 Fix Redirect Paths
        </button>
    </form>
</div>

<div class="action-form">
    <h3>🏗️ Create Dashboard Files</h3>
    <p>Create all missing dashboard files with proper Bootstrap styling</p>
    
    <form method="POST">
        <button type="submit" name="create_dashboards" class="btn btn-success">
            🏗️ Create All Dashboards
        </button>
    </form>
</div>

<div class="action-form">
    <h3>🧪 Test After Fixing</h3>
    <a href="login.php" class="btn btn-primary">🔑 Test Login Page</a>
    <a href="admin/dashboard.php" class="btn btn-primary">🏠 Direct Admin Dashboard</a>
    <a href="labeler/dashboard.php" class="btn btn-primary">📋 Direct Labeler Dashboard</a>
    <a href="reviewer/dashboard.php" class="btn btn-primary">✅ Direct Reviewer Dashboard</a>
</div>

</body>
</html>