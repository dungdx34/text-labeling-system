<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Session Fix Tool</h2>";
echo "<pre>";

try {
    if (isset($_SESSION['user_id'])) {
        echo "Current session user ID: " . $_SESSION['user_id'] . "\n";
        echo "Current session data:\n";
        foreach ($_SESSION as $key => $value) {
            echo "  $key: $value\n";
        }
        
        echo "\nFetching complete user data from database...\n";
        
        $stmt = $db->prepare("SELECT id, username, email, full_name, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "Database user data:\n";
            foreach ($user as $key => $value) {
                echo "  $key: $value\n";
            }
            
            echo "\nUpdating session with complete data...\n";
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            echo "✅ Session updated successfully!\n";
            echo "\nUpdated session data:\n";
            foreach ($_SESSION as $key => $value) {
                if (strpos($key, 'password') === false) { // Don't show passwords
                    echo "  $key: $value\n";
                }
            }
        } else {
            echo "❌ User not found in database!\n";
            echo "This might happen if:\n";
            echo "- User was deleted\n";
            echo "- Database was reset\n";
            echo "- Session contains invalid user_id\n";
            
            echo "\nClearing session and redirecting to login...\n";
            session_destroy();
            echo "Session cleared. Please login again.\n";
        }
    } else {
        echo "No user session found. Please login first.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

if (isset($_SESSION['user_id'])) {
    echo '<div style="margin: 20px;">';
    echo '<a href="admin/dashboard.php" style="background: #28a745; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-size: 16px;">Go to Dashboard</a>';
    echo '</div>';
} else {
    echo '<div style="margin: 20px;">';
    echo '<a href="login.php" style="background: #007bff; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-size: 16px;">Go to Login</a>';
    echo '</div>';
}
?>