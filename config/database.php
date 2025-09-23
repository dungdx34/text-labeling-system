<?php
// config/database.php - Enhanced Database Configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'text_labeling_system';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}",
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 30
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            
            // In production, don't show detailed error messages
            if (defined('DEBUG') && DEBUG === true) {
                echo "Connection error: " . $exception->getMessage();
            } else {
                echo "Database connection failed. Please try again later.";
            }
            exit();
        }
        
        return $this->conn;
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                return ['success' => true, 'message' => 'Database connection successful'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown connection error'];
    }
    
    /**
     * Initialize database tables
     */
    public function initializeDatabase() {
        try {
            $conn = $this->getConnection();
            
            // Check if tables exist
            $tables = ['users', 'document_groups', 'documents', 'label_tasks'];
            $existing_tables = [];
            
            foreach ($tables as $table) {
                $stmt = $conn->prepare("SHOW TABLES LIKE :table");
                $stmt->bindParam(':table', $table);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $existing_tables[] = $table;
                }
            }
            
            if (count($existing_tables) === count($tables)) {
                return ['success' => true, 'message' => 'All tables exist'];
            } else {
                $missing_tables = array_diff($tables, $existing_tables);
                return [
                    'success' => false, 
                    'message' => 'Missing tables: ' . implode(', ', $missing_tables),
                    'missing_tables' => $missing_tables
                ];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Create database backup
     */
    public function createBackup($backup_path = null) {
        if (!$backup_path) {
            $backup_path = __DIR__ . '/../backups/';
        }
        
        if (!is_dir($backup_path)) {
            mkdir($backup_path, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_path . $filename;
        
        $command = "mysqldump --user={$this->username} --password={$this->password} " .
                  "--host={$this->host} {$this->db_name} > {$filepath}";
        
        exec($command, $output, $return_code);
        
        if ($return_code === 0) {
            return ['success' => true, 'file' => $filepath];
        } else {
            return ['success' => false, 'message' => 'Backup failed'];
        }
    }
    
    /**
     * Get database statistics
     */
    public function getStatistics() {
        try {
            $conn = $this->getConnection();
            
            $stats = [];
            
            // User counts
            $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
            $user_stats = $stmt->fetchAll();
            $stats['users'] = array_column($user_stats, 'count', 'role');
            
            // Document group counts
            $stmt = $conn->query("SELECT type, COUNT(*) as count FROM document_groups GROUP BY type");
            $group_stats = $stmt->fetchAll();
            $stats['document_groups'] = array_column($group_stats, 'count', 'type');
            
            // Task counts
            $stmt = $conn->query("SELECT status, COUNT(*) as count FROM label_tasks GROUP BY status");
            $task_stats = $stmt->fetchAll();
            $stats['tasks'] = array_column($task_stats, 'count', 'status');
            
            // Total counts
            $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
            $stats['total_users'] = $stmt->fetch()['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM document_groups");
            $stats['total_documents'] = $stmt->fetch()['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM label_tasks");
            $stats['total_tasks'] = $stmt->fetch()['count'];
            
            return ['success' => true, 'data' => $stats];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// Global database instance
$database = new Database();

// Set debug mode (set to false in production)
define('DEBUG', true);
?>