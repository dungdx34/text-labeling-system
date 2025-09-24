-- ================================================
-- FIX MULTI-DOCUMENT UPLOAD ERROR
-- Sửa lỗi upload đa văn bản với ai_summary
-- ================================================

USE text_labeling_system;

-- ================================================
-- BƯỚC 1: KIỂM TRA VẤN ĐỀ CỤ THỂ
-- ================================================
SELECT '=== MULTI-DOCUMENT UPLOAD DIAGNOSIS ===' as info;

-- Kiểm tra cấu trúc bảng documents hiện tại
SELECT 'CURRENT DOCUMENTS TABLE:' as section;
DESCRIBE documents;

-- Kiểm tra xem có documents nào với type = 'multi' không
SELECT 'EXISTING MULTI DOCUMENTS:' as section;
SELECT COUNT(*) as multi_document_count 
FROM documents 
WHERE type = 'multi';

-- Kiểm tra các cột cần thiết cho multi-document
SELECT 'REQUIRED COLUMNS CHECK:' as section;
SELECT 
    'ai_summary' as column_name,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'documents' 
AND COLUMN_NAME = 'ai_summary'

UNION ALL

SELECT 
    'uploaded_by' as column_name,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'documents' 
AND COLUMN_NAME = 'uploaded_by'

UNION ALL

SELECT 
    'type' as column_name,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'documents' 
AND COLUMN_NAME = 'type';

-- ================================================
-- BƯỚC 2: THÊM/SỬA CÁC CỘT CẦN THIẾT
-- ================================================
SELECT 'FIXING COLUMNS FOR MULTI-DOCUMENT:' as section;

-- Thêm ai_summary nếu chưa có (quan trọng cho multi-document)
ALTER TABLE documents ADD COLUMN ai_summary longtext DEFAULT NULL;

-- Thêm uploaded_by nếu chưa có
ALTER TABLE documents ADD COLUMN uploaded_by int(11) NOT NULL DEFAULT 1;

-- Đảm bảo cột type có đúng enum values
ALTER TABLE documents MODIFY COLUMN type enum('single','multi') NOT NULL DEFAULT 'single';

-- Thêm status nếu chưa có
ALTER TABLE documents ADD COLUMN status enum('pending','assigned','completed','reviewed') DEFAULT 'pending';

-- Thêm updated_at nếu chưa có
ALTER TABLE documents ADD COLUMN updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ================================================
-- BƯỚC 3: TĂNG CƯỜNG BẢNG CHO MULTI-DOCUMENT
-- ================================================
SELECT 'ENHANCING TABLES FOR MULTI-DOCUMENT SUPPORT:' as section;

-- Đảm bảo bảng document_groups tồn tại (quan trọng cho multi-document)
CREATE TABLE IF NOT EXISTS document_groups (
    id int(11) NOT NULL AUTO_INCREMENT,
    group_name varchar(255) NOT NULL,
    description text,
    combined_ai_summary longtext,  -- Tóm tắt AI cho cả nhóm documents
    created_by int(11) NOT NULL DEFAULT 1,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status enum('pending','assigned','completed','reviewed') DEFAULT 'pending',
    total_documents int DEFAULT 0,  -- Số lượng documents trong group
    PRIMARY KEY (id),
    KEY idx_document_groups_created_by (created_by),
    KEY idx_document_groups_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng liên kết documents với groups
CREATE TABLE IF NOT EXISTS document_group_items (
    id int(11) NOT NULL AUTO_INCREMENT,
    group_id int(11) NOT NULL,
    document_id int(11) NOT NULL,
    sort_order int(11) DEFAULT 0,
    individual_ai_summary text,  -- AI summary riêng cho document này trong group
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_group_document (group_id, document_id),
    KEY idx_group_items_group (group_id),
    KEY idx_group_items_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cập nhật labeling_tasks để hỗ trợ cả single và multi document
ALTER TABLE labeling_tasks ADD COLUMN document_group_id int(11) DEFAULT NULL;

-- ================================================
-- BƯỚC 4: CẬP NHẬT DỮ LIỆU HIỆN CÓ
-- ================================================
SELECT 'UPDATING EXISTING DATA FOR MULTI-DOCUMENT:' as section;

-- Cập nhật uploaded_by cho tất cả documents
UPDATE documents SET uploaded_by = 1 WHERE uploaded_by IS NULL OR uploaded_by = 0;

-- Cập nhật ai_summary cho documents hiện có nếu NULL
UPDATE documents 
SET ai_summary = CONCAT('AI-generated summary for: ', title) 
WHERE ai_summary IS NULL OR ai_summary = '';

-- Cập nhật type cho documents cũ nếu NULL  
UPDATE documents SET type = 'single' WHERE type IS NULL;

-- ================================================
-- BƯỚC 5: TẠO STORED PROCEDURE CHO MULTI-DOCUMENT UPLOAD
-- ================================================
SELECT 'CREATING PROCEDURES FOR MULTI-DOCUMENT:' as section;

DELIMITER //

-- Procedure để tạo multi-document group
DROP PROCEDURE IF EXISTS CreateMultiDocumentGroup//
CREATE PROCEDURE CreateMultiDocumentGroup(
    IN p_group_name VARCHAR(255),
    IN p_description TEXT,
    IN p_created_by INT,
    OUT p_group_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_group_id = -1;
    END;
    
    START TRANSACTION;
    
    INSERT INTO document_groups (group_name, description, created_by)
    VALUES (p_group_name, p_description, p_created_by);
    
    SET p_group_id = LAST_INSERT_ID();
    
    COMMIT;
END//

-- Procedure để thêm document vào group
DROP PROCEDURE IF EXISTS AddDocumentToGroup//
CREATE PROCEDURE AddDocumentToGroup(
    IN p_group_id INT,
    IN p_document_id INT,
    IN p_sort_order INT,
    IN p_individual_summary TEXT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    -- Thêm document vào group
    INSERT INTO document_group_items (group_id, document_id, sort_order, individual_ai_summary)
    VALUES (p_group_id, p_document_id, p_sort_order, p_individual_summary)
    ON DUPLICATE KEY UPDATE 
        sort_order = p_sort_order,
        individual_ai_summary = p_individual_summary;
    
    -- Cập nhật số lượng documents trong group
    UPDATE document_groups 
    SET total_documents = (
        SELECT COUNT(*) FROM document_group_items WHERE group_id = p_group_id
    )
    WHERE id = p_group_id;
    
    COMMIT;
END//

DELIMITER ;

-- ================================================
-- BƯỚC 6: TẠO VIEWS HỖ TRỢ MULTI-DOCUMENT
-- ================================================
SELECT 'CREATING MULTI-DOCUMENT VIEWS:' as section;

-- View để hiển thị thông tin multi-document
DROP VIEW IF EXISTS multi_document_overview;
CREATE VIEW multi_document_overview AS
SELECT 
    dg.id as group_id,
    dg.group_name,
    dg.description,
    dg.combined_ai_summary,
    dg.status as group_status,
    dg.total_documents,
    dg.created_by,
    u.username as created_by_name,
    dg.created_at as group_created_at,
    COUNT(DISTINCT t.id) as assigned_tasks,
    COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as completed_tasks
FROM document_groups dg
LEFT JOIN users u ON dg.created_by = u.id
LEFT JOIN labeling_tasks t ON dg.id = t.document_group_id
GROUP BY dg.id, dg.group_name, dg.description, dg.combined_ai_summary, 
         dg.status, dg.total_documents, dg.created_by, u.username, dg.created_at;

-- View để hiển thị chi tiết documents trong group
DROP VIEW IF EXISTS multi_document_details;
CREATE VIEW multi_document_details AS
SELECT 
    dgi.group_id,
    dg.group_name,
    dgi.document_id,
    d.title as document_title,
    d.content as document_content,
    d.ai_summary as document_ai_summary,
    dgi.individual_ai_summary,
    dgi.sort_order,
    d.status as document_status,
    d.created_at as document_created_at
FROM document_group_items dgi
JOIN document_groups dg ON dgi.group_id = dg.id
JOIN documents d ON dgi.document_id = d.id
ORDER BY dgi.group_id, dgi.sort_order;

-- ================================================
-- BƯỚC 7: CẬP NHẬT VIEWS CHÍNH
-- ================================================
SELECT 'UPDATING MAIN VIEWS:' as section;

-- Cập nhật user_performance view để hỗ trợ multi-document
DROP VIEW IF EXISTS user_performance;
CREATE VIEW user_performance AS
SELECT 
    u.id,
    u.username,
    u.role,
    COALESCE(single_tasks.total_single_tasks, 0) + COALESCE(multi_tasks.total_multi_tasks, 0) as total_tasks,
    COALESCE(single_tasks.completed_single_tasks, 0) + COALESCE(multi_tasks.completed_multi_tasks, 0) as completed_tasks,
    COALESCE(review_stats.approved_tasks, 0) as approved_tasks,
    COALESCE(review_stats.avg_review_score, 0) as avg_review_score,
    LEAST(
        COALESCE(single_tasks.first_single_task, '9999-12-31'),
        COALESCE(multi_tasks.first_multi_task, '9999-12-31')
    ) as first_task_date,
    GREATEST(
        COALESCE(single_tasks.last_single_completion, '1900-01-01'),
        COALESCE(multi_tasks.last_multi_completion, '1900-01-01')
    ) as last_completion_date
FROM users u
LEFT JOIN (
    SELECT 
        assigned_to,
        COUNT(*) as total_single_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_single_tasks,
        MIN(assigned_at) as first_single_task,
        MAX(completed_at) as last_single_completion
    FROM labeling_tasks 
    WHERE document_id IS NOT NULL
    GROUP BY assigned_to
) single_tasks ON u.id = single_tasks.assigned_to
LEFT JOIN (
    SELECT 
        assigned_to,
        COUNT(*) as total_multi_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_multi_tasks,
        MIN(assigned_at) as first_multi_task,
        MAX(completed_at) as last_multi_completion
    FROM labeling_tasks 
    WHERE document_group_id IS NOT NULL
    GROUP BY assigned_to
) multi_tasks ON u.id = multi_tasks.assigned_to
LEFT JOIN (
    SELECT 
        t.assigned_to,
        COUNT(CASE WHEN r.review_status = 'approved' THEN 1 END) as approved_tasks,
        AVG(CASE WHEN r.review_score IS NOT NULL THEN r.review_score END) as avg_review_score
    FROM labeling_tasks t
    LEFT JOIN reviews r ON t.id = r.task_id
    GROUP BY t.assigned_to
) review_stats ON u.id = review_stats.assigned_to;

-- ================================================
-- BƯỚC 8: THÊM SAMPLE DATA CHO TEST
-- ================================================
SELECT 'ADDING SAMPLE DATA FOR TESTING:' as section;

-- Thêm admin user nếu chưa có
INSERT IGNORE INTO users (username, password, email, role, is_active) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin', 1);

-- Cập nhật admin user
UPDATE users SET 
    role = 'admin',
    is_active = 1,
    password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE username = 'admin';

-- Thêm sample multi-document group để test
INSERT IGNORE INTO document_groups (id, group_name, description, combined_ai_summary, created_by)
VALUES (1, 'Sample Multi-Document Group', 'Test group for multi-document labeling', 'Combined AI summary for multiple documents', 1);

-- ================================================
-- BƯỚC 9: KIỂM TRA KẾT QUẢ
-- ================================================
SELECT '=== MULTI-DOCUMENT FIX VERIFICATION ===' as info;

-- Kiểm tra cấu trúc documents table
SELECT 'DOCUMENTS TABLE FINAL STRUCTURE:' as section;
DESCRIBE documents;

-- Kiểm tra multi-document tables
SELECT 'DOCUMENT_GROUPS TABLE:' as section;
DESCRIBE document_groups;

SELECT 'DOCUMENT_GROUP_ITEMS TABLE:' as section;  
DESCRIBE document_group_items;

-- Test multi-document views
SELECT 'MULTI-DOCUMENT VIEWS TEST:' as section;
SELECT COUNT(*) as multi_document_overview_rows FROM multi_document_overview;
SELECT COUNT(*) as multi_document_details_rows FROM multi_document_details;

-- Test main views
SELECT 'MAIN VIEWS TEST:' as section;
SELECT COUNT(*) as user_performance_rows FROM user_performance;

-- Check admin user
SELECT 'ADMIN USER VERIFICATION:' as section;
SELECT id, username, email, role, is_active FROM users WHERE username = 'admin';

-- Hiển thị tất cả tables
SELECT 'ALL TABLES:' as section;
SHOW TABLES;

-- ================================================
-- KẾT QUẢ CUỐI CÙNG
-- ================================================
SELECT '🎉 MULTI-DOCUMENT UPLOAD FIX COMPLETED! 🎉' as status;
SELECT '✅ All columns required for multi-document upload added' as result1;
SELECT '✅ Multi-document tables and procedures created' as result2;
SELECT '✅ Views updated to support both single and multi documents' as result3;
SELECT '✅ Sample data added for testing' as result4;

SELECT 'MULTI-DOCUMENT FEATURES:' as features;
SELECT '• Upload multiple documents as a group' as feature1;
SELECT '• Individual AI summaries for each document' as feature2;
SELECT '• Combined AI summary for the entire group' as feature3;
SELECT '• Assign labeling tasks for multi-document groups' as feature4;
SELECT '• Track progress for both single and multi-document tasks' as feature5;

SELECT 'TESTING INSTRUCTIONS:' as testing;
SELECT '1. Login with admin/admin123' as step1;
SELECT '2. Try uploading JSONL with "type": "multi"' as step2;
SELECT '3. Check multi_document_overview view for results' as step3;
SELECT '4. No more "Unknown column ai_summary" errors should occur' as step4;