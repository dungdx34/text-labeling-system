<?php
// emergency_db_fix.php - Khắc phục nhanh database connection

echo "<h1>🚨 Emergency Database Fix</h1>";

// 1. Kiểm tra và tạo lại file config
echo "<h2>1. 🔧 Tạo lại file config/database.php</h2>";

$config_dir = 'config';
if (!is_dir($config_dir)) {
    mkdir($config_dir, 0755, true);
    echo "<p>✅ Tạo thư mục config/</p>";
}

$config_content = '<?php
class Database {
    private $host = "localhost";
    private $db_name = "text_labeling_system";
    private $username = "root";
    private $password = "";
    private $charset = "utf8mb4";
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            return null;
        }
        
        return $this->conn;
    }

    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                $stmt = $conn->prepare("SELECT 1");
                $stmt->execute();
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Database test failed: " . $e->getMessage());
            return false;
        }
    }
}
?>';

file_put_contents('config/database.php', $config_content);
echo "<p>✅ Tạo lại file config/database.php</p>";

// 2. Test kết nối
echo "<h2>2. 🔌 Test kết nối database</h2>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<p style='color: green;'>✅ Kết nối database thành công!</p>";
        
        // Kiểm tra database tồn tại
        $stmt = $conn->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'text_labeling_system'");
        $stmt->execute();
        $db_exists = $stmt->rowCount() > 0;
        
        if ($db_exists) {
            echo "<p style='color: green;'>✅ Database 'text_labeling_system' tồn tại</p>";
            
            // Kiểm tra bảng users
            $stmt = $conn->prepare("SHOW TABLES LIKE 'users'");
            $stmt->execute();
            $users_table_exists = $stmt->rowCount() > 0;
            
            if ($users_table_exists) {
                echo "<p style='color: green;'>✅ Bảng 'users' tồn tại</p>";
                
                // Đếm số users
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
                $stmt->execute();
                $user_count = $stmt->fetch()['count'];
                echo "<p>📊 Số lượng users: <strong>$user_count</strong></p>";
                
            } else {
                echo "<p style='color: red;'>❌ Bảng 'users' không tồn tại!</p>";
                echo "<button onclick='createTables()'>🔧 Tạo bảng users</button>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ Database 'text_labeling_system' không tồn tại!</p>";
            echo "<button onclick='createDatabase()'>🔧 Tạo database</button>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Không thể kết nối database!</p>";
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>Các bước khắc phục:</h4>";
        echo "<ol>";
        echo "<li><strong>Kiểm tra XAMPP đang chạy:</strong> Mở XAMPP Control Panel, start Apache và MySQL</li>";
        echo "<li><strong>Kiểm tra port MySQL:</strong> Mặc định là 3306, có thể là 3307</li>";
        echo "<li><strong>Kiểm tra username/password:</strong> Thường là root/(trống)</li>";
        echo "</ol>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Lỗi: " . $e->getMessage() . "</p>";
}

// 3. Tạo script tạo database và bảng
echo "<h2>3. 🛠️ Scripts khắc phục</h2>";

?>

<script>
function createDatabase() {
    if (confirm('Tạo database text_labeling_system?')) {
        fetch('emergency_create_db.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'create_database'})
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            location.reload();
        });
    }
}

function createTables() {
    if (confirm('Tạo bảng users và các bảng cần thiết?')) {
        fetch('emergency_create_db.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'create_tables'})
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            location.reload();
        });
    }
}
</script>

<!-- Links hữu ích -->
<div style="background: #e7f3ff; padding: 20px; border-radius: 10px; margin: 20px 0;">
    <h3>🔗 Links hữu ích:</h3>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="check_accounts.php" class="btn btn-info">📋 Check Accounts</a>
        <a href="fix_all_accounts.php" class="btn btn-success">👥 Fix All Accounts</a>
        <a href="database_troubleshoot.php" class="btn btn-warning">🔧 Database Troubleshoot</a>
        <a href="login.php" class="btn btn-primary">🚀 Go to Login</a>
    </div>
</div>

<!-- Test login nhanh -->
<div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;">
    <h3>⚡ Test Login nhanh:</h3>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button onclick="testQuickLogin('admin', 'admin123')" class="btn btn-danger">🔴 Test Admin</button>
        <button onclick="testQuickLogin('labeler1', 'labeler123')" class="btn btn-primary">🔵 Test Labeler</button>
        <button onclick="testQuickLogin('reviewer1', 'reviewer123')" class="btn btn-success">🟢 Test Reviewer</button>
    </div>
</div>

<script>
function testQuickLogin(username, password) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'quick_login_test.php';
    form.target = '_blank';
    
    const userInput = document.createElement('input');
    userInput.type = 'hidden';
    userInput.name = 'username';
    userInput.value = username;
    
    const passInput = document.createElement('input');
    passInput.type = 'hidden';
    passInput.name = 'password';
    passInput.value = password;
    
    form.appendChild(userInput);
    form.appendChild(passInput);
    document.body.appendChild(form);
    
    form.submit();
    document.body.removeChild(form);
}
</script>

<style>
body {
    font-family: 'Segoe UI', sans-serif;
    max-width: 1000px;
    margin: 20px auto;
    padding: 20px;
    background: #f8f9fa;
}
.btn {
    display: inline-block;
    padding: 8px 16px;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    margin: 2px;
}
.btn:hover { background: #0056b3; }
.btn-info { background: #17a2b8; }
.btn-success { background: #28a745; }
.btn-warning { background: #ffc107; color: #212529; }
.btn-danger { background: #dc3545; }
.btn-primary { background: #007bff; }
button { 
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin: 2px;
    background: #007bff;
    color: white;
}
</style>