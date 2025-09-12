-- Fix Enhanced Database Structure - Handle Missing Columns
-- Run this BEFORE the main enhanced_database.sql script

-- ========================================
-- 1. CHECK AND CREATE MISSING COLUMNS
-- ========================================

-- First, let's see what columns exist in labelings table
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'labelings';

-- Add missing columns to labelings table if they don't exist
ALTER TABLE labelings 
ADD COLUMN IF NOT EXISTS selected_sentences JSON NULL,
ADD COLUMN IF NOT EXISTS text_style_id INT NULL,
ADD COLUMN IF NOT EXISTS edited_summary TEXT NULL;

-- Add missing columns that the enhanced script expects
ALTER TABLE labelings
ADD COLUMN IF NOT EXISTS group_id INT NULL AFTER document_id,
ADD COLUMN IF NOT EXISTS document_sentences JSON NULL AFTER selected_sentences,
ADD COLUMN IF NOT EXISTS ai_summary_edited TEXT NULL AFTER edited_summary,
ADD COLUMN IF NOT EXISTS assigned_at TIMESTAMP NULL AFTER updated_at,
ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL AFTER assigned_at;

-- Check if documents table has the necessary columns
ALTER TABLE documents 
ADD COLUMN IF NOT EXISTS group_id INT NULL AFTER id,
ADD COLUMN IF NOT EXISTS document_order INT DEFAULT 1 AFTER group_id;

-- Check if users table has the necessary columns
ALTER TABLE users
ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) NULL AFTER username,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER created_at,
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'suspended') DEFAULT 'active' AFTER email;

-- ========================================
-- 2. CREATE DOCUMENT_GROUPS TABLE
-- ========================================

CREATE TABLE IF NOT EXISTS document_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    group_type ENUM('single', 'multi') DEFAULT 'single',
    ai_summary TEXT NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('pending', 'in_progress', 'completed', 'reviewed') DEFAULT 'pending'
);

-- ========================================
-- 3. CREATE OTHER MISSING TABLES
-- ========================================

-- Labeling activity logs table
CREATE TABLE IF NOT EXISTS labeling_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NULL,
    document_id INT NULL,
    labeler_id INT NOT NULL,
    action ENUM('assigned', 'started', 'saved', 'completed', 'reviewed') NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Text styles table for labeling options
CREATE TABLE IF NOT EXISTS text_styles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    style_config JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User preferences table
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ========================================
-- 4. ADD FOREIGN KEYS AND INDEXES
-- ========================================

-- Add indexes first (they don't have IF NOT EXISTS, so we'll use a different approach)

-- Check and add indexes for documents
SET @query = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME = 'documents' 
         AND INDEX_NAME = 'idx_documents_group_id') = 0,
        'CREATE INDEX idx_documents_group_id ON documents(group_id);',
        'SELECT "Index idx_documents_group_id already exists" as notice;'
    )
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add indexes for labelings
SET @query = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME = 'labelings' 
         AND INDEX_NAME = 'idx_labelings_group_id') = 0,
        'CREATE INDEX idx_labelings_group_id ON labelings(group_id);',
        'SELECT "Index idx_labelings_group_id already exists" as notice;'
    )
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add more indexes
CREATE INDEX IF NOT EXISTS idx_document_groups_status ON document_groups(status);
CREATE INDEX IF NOT EXISTS idx_document_groups_type ON document_groups(group_type);
CREATE INDEX IF NOT EXISTS idx_labelings_status ON labelings(status);

-- ========================================
-- 5. ADD FOREIGN KEYS (WITH ERROR HANDLING)
-- ========================================

-- Add foreign keys with proper error handling
-- We'll check if foreign keys exist before adding them

-- Function to safely add foreign key
DELIMITER $$

CREATE OR REPLACE PROCEDURE AddForeignKeyIfNotExists(
    IN tableName VARCHAR(64),
    IN constraintName VARCHAR(64),
    IN foreignKeyDef TEXT
)
BEGIN
    DECLARE fk_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO fk_count
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = tableName
    AND CONSTRAINT_NAME = constraintName;
    
    IF fk_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', tableName, ' ADD CONSTRAINT ', constraintName, ' ', foreignKeyDef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('Added foreign key: ', constraintName) as result;
    ELSE
        SELECT CONCAT('Foreign key already exists: ', constraintName) as result;
    END IF;
END$$

DELIMITER ;

-- Add foreign keys safely
CALL AddForeignKeyIfNotExists('document_groups', 'fk_document_groups_uploaded_by', 
    'FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE');

CALL AddForeignKeyIfNotExists('documents', 'fk_documents_group_id', 
    'FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE');

CALL AddForeignKeyIfNotExists('labelings', 'fk_labelings_group_id', 
    'FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE');

CALL AddForeignKeyIfNotExists('labeling_logs', 'fk_logs_group_id', 
    'FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE');

CALL AddForeignKeyIfNotExists('labeling_logs', 'fk_logs_document_id', 
    'FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE');

CALL AddForeignKeyIfNotExists('labeling_logs', 'fk_logs_labeler_id', 
    'FOREIGN KEY (labeler_id) REFERENCES users(id) ON DELETE CASCADE');

CALL AddForeignKeyIfNotExists('user_preferences', 'fk_preferences_user_id', 
    'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');

-- ========================================
-- 6. INSERT SAMPLE DATA
-- ========================================

-- Insert default text styles
INSERT IGNORE INTO text_styles (id, name, description, style_config) VALUES
(1, 'Formal', 'Văn phong trang trọng, chính thức', '{"tone": "formal", "length": "detailed"}'),
(2, 'Casual', 'Văn phong thân thiện, gần gũi', '{"tone": "casual", "length": "concise"}'),
(3, 'Technical', 'Văn phong kỹ thuật, chuyên môn', '{"tone": "technical", "length": "detailed"}'),
(4, 'Summary', 'Tóm tắt ngắn gọn, súc tích', '{"tone": "neutral", "length": "brief"}');

-- Insert sample document groups (only if users table has data)
INSERT IGNORE INTO document_groups (id, title, description, group_type, ai_summary, uploaded_by, status) 
SELECT 
    1,
    'Nhóm văn bản về Công nghệ AI',
    'Bộ sưu tập các bài viết về trí tuệ nhân tạo và machine learning',
    'multi',
    'Trí tuệ nhân tạo đang phát triển mạnh mẽ và tác động sâu rộng đến nhiều lĩnh vực như y tế, giáo dục, và kinh doanh. Công nghệ AI giúp tự động hóa quy trình, cải thiện hiệu quả và tạo ra những giải pháp thông minh cho tương lai.',
    (SELECT id FROM users WHERE role = 'admin' LIMIT 1),
    'pending'
WHERE EXISTS (SELECT 1 FROM users WHERE role = 'admin' LIMIT 1);

INSERT IGNORE INTO document_groups (id, title, description, group_type, ai_summary, uploaded_by, status)
SELECT 
    2,
    'Văn bản đơn về Giáo dục',
    'Một bài viết về xu hướng giáo dục hiện đại và công nghệ',
    'single',
    'Giáo dục hiện đại đang chuyển đổi số mạnh mẽ với việc ứng dụng công nghệ vào giảng dạy và học tập. Phương pháp học tập cá nhân hóa và học trực tuyến đang trở thành xu thế chủ đạo trong thời đại 4.0.',
    (SELECT id FROM users WHERE role = 'admin' LIMIT 1),
    'pending'
WHERE EXISTS (SELECT 1 FROM users WHERE role = 'admin' LIMIT 1);

-- ========================================
-- 7. CREATE VIEWS
-- ========================================

-- View for group statistics
CREATE OR REPLACE VIEW v_group_stats AS
SELECT 
    dg.id,
    dg.title,
    dg.group_type,
    dg.status,
    dg.created_at,
    COALESCE(u.username, u.full_name, 'Unknown') as uploaded_by_name,
    COUNT(DISTINCT d.id) as document_count,
    COUNT(DISTINCT l.labeler_id) as labeler_count,
    SUM(CASE WHEN l.status = 'completed' THEN 1 ELSE 0 END) as completed_labelings,
    AVG(CASE WHEN l.status = 'completed' AND l.assigned_at IS NOT NULL AND l.completed_at IS NOT NULL THEN 
        TIMESTAMPDIFF(HOUR, l.assigned_at, l.completed_at) 
        ELSE NULL END) as avg_completion_hours
FROM document_groups dg
LEFT JOIN users u ON dg.uploaded_by = u.id
LEFT JOIN documents d ON dg.id = d.group_id
LEFT JOIN labelings l ON dg.id = l.group_id
GROUP BY dg.id, dg.title, dg.group_type, dg.status, dg.created_at, u.username, u.full_name;

-- View for labeler performance
CREATE OR REPLACE VIEW v_labeler_performance AS
SELECT 
    u.id,
    u.username,
    COALESCE(u.full_name, u.username) as display_name,
    COUNT(DISTINCT l.group_id) as total_groups_assigned,
    COUNT(DISTINCT CASE WHEN l.status = 'completed' THEN l.group_id END) as groups_completed,
    COUNT(DISTINCT CASE WHEN l.status IN ('assigned', 'in_progress') THEN l.group_id END) as groups_in_progress,
    AVG(CASE WHEN l.status = 'completed' AND l.assigned_at IS NOT NULL AND l.completed_at IS NOT NULL
        THEN TIMESTAMPDIFF(HOUR, l.assigned_at, l.completed_at) 
        ELSE NULL END) as avg_completion_hours,
    MAX(l.completed_at) as last_completion
FROM users u
LEFT JOIN labelings l ON u.id = l.labeler_id
WHERE u.role = 'labeler' AND u.status = 'active'
GROUP BY u.id, u.username, u.full_name;

-- ========================================
-- 8. CREATE STORED PROCEDURES
-- ========================================

DELIMITER $$

-- Procedure to assign a group to a labeler
CREATE OR REPLACE PROCEDURE sp_assign_group_to_labeler(
    IN p_group_id INT,
    IN p_labeler_id INT
)
BEGIN
    DECLARE v_group_exists INT DEFAULT 0;
    DECLARE v_labeler_exists INT DEFAULT 0;
    DECLARE v_already_assigned INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Check if group exists and is pending
    SELECT COUNT(*) INTO v_group_exists 
    FROM document_groups 
    WHERE id = p_group_id AND status = 'pending';
    
    -- Check if labeler exists and is active
    SELECT COUNT(*) INTO v_labeler_exists 
    FROM users 
    WHERE id = p_labeler_id AND role = 'labeler' AND status = 'active';
    
    -- Check if already assigned
    SELECT COUNT(*) INTO v_already_assigned 
    FROM labelings 
    WHERE group_id = p_group_id AND labeler_id = p_labeler_id;
    
    IF v_group_exists = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Group not found or not available for assignment';
    ELSEIF v_labeler_exists = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Labeler not found or not active';
    ELSEIF v_already_assigned > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Group already assigned to this labeler';
    ELSE
        -- Create labeling assignment
        INSERT INTO labelings (group_id, labeler_id, status, assigned_at)
        VALUES (p_group_id, p_labeler_id, 'assigned', NOW());
        
        -- Update group status
        UPDATE document_groups 
        SET status = 'in_progress' 
        WHERE id = p_group_id;
        
        -- Log the assignment
        INSERT INTO labeling_logs (group_id, labeler_id, action, details)
        VALUES (p_group_id, p_labeler_id, 'assigned', JSON_OBJECT('assigned_at', NOW()));
        
        COMMIT;
        SELECT 'SUCCESS' as status, 'Group assigned successfully' as message;
    END IF;
END$$

-- Procedure to complete a labeling task
CREATE OR REPLACE PROCEDURE sp_complete_labeling(
    IN p_group_id INT,
    IN p_labeler_id INT,
    IN p_document_sentences JSON,
    IN p_edited_summary TEXT
)
BEGIN
    DECLARE v_labeling_id INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Get labeling ID
    SELECT id INTO v_labeling_id 
    FROM labelings 
    WHERE group_id = p_group_id AND labeler_id = p_labeler_id;
    
    IF v_labeling_id = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Labeling assignment not found';
    ELSE
        -- Update labeling
        UPDATE labelings 
        SET 
            document_sentences = p_document_sentences,
            ai_summary_edited = p_edited_summary,
            status = 'completed',
            completed_at = NOW()
        WHERE id = v_labeling_id;
        
        -- Update group status
        UPDATE document_groups 
        SET status = 'completed' 
        WHERE id = p_group_id;
        
        -- Log the completion
        INSERT INTO labeling_logs (group_id, labeler_id, action, details)
        VALUES (p_group_id, p_labeler_id, 'completed', 
                JSON_OBJECT('completed_at', NOW(), 'sentences_count', 
                    CASE WHEN p_document_sentences IS NOT NULL 
                         THEN JSON_LENGTH(p_document_sentences) 
                         ELSE 0 END));
        
        COMMIT;
        SELECT 'SUCCESS' as status, 'Labeling completed successfully' as message;
    END IF;
END$$

DELIMITER ;

-- Clean up the helper procedure
DROP PROCEDURE IF EXISTS AddForeignKeyIfNotExists;

-- ========================================
-- 9. FINAL VERIFICATION
-- ========================================

-- Show final table structure
SELECT 'VERIFICATION: Tables created successfully' as status;

SELECT 
    TABLE_NAME, 
    TABLE_ROWS,
    CREATE_TIME
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('document_groups', 'documents', 'labelings', 'labeling_logs', 'text_styles', 'users')
ORDER BY TABLE_NAME;

SELECT 'Database structure updated successfully!' as final_status;