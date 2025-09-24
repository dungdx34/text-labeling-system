-- ================================================
-- FIX DOCUMENT_GROUPS TABLE STRUCTURE
-- Chạy SQL này để sửa cấu trúc bảng
-- ================================================

USE text_labeling_system;

-- Option 1: Add missing column group_name (khuyến nghị)
ALTER TABLE document_groups ADD COLUMN group_name varchar(255) NOT NULL DEFAULT '' AFTER id;

-- Update existing records
UPDATE document_groups SET group_name = title WHERE group_name = '';

-- Option 2: Hoặc xóa và tạo lại bảng (nếu không có dữ liệu quan trọng)
-- DROP TABLE IF EXISTS document_groups;
-- CREATE TABLE document_groups (
--     id int(11) NOT NULL AUTO_INCREMENT,
--     group_name varchar(255) NOT NULL,
--     title varchar(255) NOT NULL,
--     description text,
--     ai_summary text,
--     combined_ai_summary text,
--     created_by int(11) DEFAULT 1,
--     created_at timestamp DEFAULT CURRENT_TIMESTAMP,
--     updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     status enum('pending','assigned','completed','reviewed') DEFAULT 'pending',
--     PRIMARY KEY (id)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Check result
SELECT 'FIXED TABLE STRUCTURE:' as info;
DESCRIBE document_groups;

SELECT 'SUCCESS: document_groups table now has group_name column!' as result;