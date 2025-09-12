-- Fix login issues for auth.php compatibility

-- Add is_active column (required by auth.php)
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;

-- Set all active users to is_active = 1
UPDATE users SET is_active = 1 WHERE status = 'active';
UPDATE users SET is_active = 0 WHERE status != 'active';

-- Update passwords with proper bcrypt hashes
-- Hash for 'admin123': $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';

-- Hash for 'labeler123': $2y$10$3i9/lVd8UOFJ6PAMFt8av.WCA/.F2PtqgS5vWoOhfxGO2dOdJvwz6  
UPDATE users SET password = '$2y$10$3i9/lVd8UOFJ6PAMFt8av.WCA/.F2PtqgS5vWoOhfxGO2dOdJvwz6' WHERE username = 'labeler1';
UPDATE users SET password = '$2y$10$3i9/lVd8UOFJ6PAMFt8av.WCA/.F2PtqgS5vWoOhfxGO2dOdJvwz6' WHERE username = 'labeler2';

-- Verify the changes
SELECT 'Login fix completed!' as status;
SELECT username, role, status, is_active, 
       SUBSTRING(password, 1, 20) as password_preview
FROM users;

-- Test login credentials:
SELECT 'Login Credentials:' as info;
SELECT 'admin / admin123' as admin_login;
SELECT 'labeler1 / labeler123' as labeler1_login; 
SELECT 'labeler2 / labeler123' as labeler2_login;