-- ================================================
-- DATABASE STRUCTURE CHECK SCRIPT
-- Chạy script này TRƯỚC để xem cấu trúc hiện tại
-- ================================================

USE `text_labeling_system`;

SELECT '=== CURRENT DATABASE STRUCTURE ANALYSIS ===' as info;

-- ================================================
-- KIỂM TRA TẤT CẢ BẢNG HIỆN CÓ
-- ================================================
SELECT 'EXISTING TABLES:' as section;
SELECT 
    TABLE_NAME as table_name,
    TABLE_ROWS as estimated_rows,
    CREATE_TIME as created_time,
    TABLE_COMMENT as comment
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'text_labeling_system'
ORDER BY TABLE_NAME;

-- ================================================
-- KIỂM TRA CẤU TRÚC BẢNG USERS
-- ================================================
SELECT 'USERS TABLE STRUCTURE:' as section;
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    EXTRA
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'users'
ORDER BY ORDINAL_POSITION;

-- ================================================
-- KIỂM TRA CẤU TRÚC BẢNG DOCUMENTS  
-- ================================================
SELECT 'DOCUMENTS TABLE STRUCTURE:' as section;
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    EXTRA
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'documents'
ORDER BY ORDINAL_POSITION;

-- ================================================
-- KIỂM TRA CẤU TRÚC BẢNG LABELING_TASKS
-- ================================================
SELECT 'LABELING_TASKS TABLE STRUCTURE:' as section;
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    EXTRA
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'labeling_tasks'
ORDER BY ORDINAL_POSITION;

-- ================================================
-- KIỂM TRA TẤT CẢ FOREIGN KEYS
-- ================================================
SELECT 'EXISTING FOREIGN KEYS:' as section;
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'text_labeling_system'
AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- ================================================
-- KIỂM TRA VIEWS HIỆN CÓ
-- ================================================
SELECT 'EXISTING VIEWS:' as section;
SELECT TABLE_NAME as view_name
FROM INFORMATION_SCHEMA.VIEWS 
WHERE TABLE_SCHEMA = 'text_labeling_system'
ORDER BY TABLE_NAME;

-- ================================================
-- KIỂM TRA TRIGGERS HIỆN CÓ
-- ================================================
SELECT 'EXISTING TRIGGERS:' as section;
SELECT 
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    EVENT_OBJECT_TABLE,
    TRIGGER_SCHEMA
FROM INFORMATION_SCHEMA.TRIGGERS 
WHERE TRIGGER_SCHEMA = 'text_labeling_system'
ORDER BY TRIGGER_NAME;

-- ================================================
-- KIỂM TRA DỮ LIỆU HIỆN CÓ
-- ================================================
SELECT 'DATA COUNT IN MAIN TABLES:' as section;

-- Users count
SELECT 'users' as table_name, COUNT(*) as record_count 
FROM users
UNION ALL

-- Documents count  
SELECT 'documents' as table_name, COUNT(*) as record_count 
FROM documents
UNION ALL

-- Tasks count
SELECT 'labeling_tasks' as table_name, COUNT(*) as record_count 
FROM labeling_tasks
UNION ALL

-- Reviews count (nếu bảng tồn tại)
SELECT 'reviews' as table_name, 
       CASE 
           WHEN EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'text_labeling_system' AND TABLE_NAME = 'reviews')
           THEN (SELECT COUNT(*) FROM reviews)
           ELSE -1
       END as record_count;

-- ================================================
-- KIỂM TRA CÁC CỘT QUAN TRỌNG CÓ TỒN TẠI KHÔNG
-- ================================================
SELECT 'CRITICAL COLUMNS CHECK:' as section;

-- Kiểm tra cột uploaded_by trong documents
SELECT 
    'documents.uploaded_by' as column_check,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'documents' 
AND COLUMN_NAME = 'uploaded_by'

UNION ALL

-- Kiểm tra cột role trong users
SELECT 
    'users.role' as column_check,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'users' 
AND COLUMN_NAME = 'role'

UNION ALL

-- Kiểm tra cột review_status trong reviews
SELECT 
    'reviews.review_status' as column_check,
    CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END as status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'reviews' 
AND COLUMN_NAME = 'review_status';

-- ================================================
-- KIỂM TRA ADMIN USER  
-- ================================================
SELECT 'ADMIN USER CHECK:' as section;
SELECT 
    id,
    username,
    email,
    role,
    is_active,
    created_at
FROM users 
WHERE role = 'admin' OR username = 'admin'
LIMIT 5;

-- ================================================
-- KẾT LUẬN VÀ KHUYẾN NGHỊ
-- ================================================
SELECT '=== ANALYSIS COMPLETE ===' as info;
SELECT 'Check the results above to understand your current database structure' as instruction1;
SELECT 'Then choose the appropriate fix script based on what is missing' as instruction2;

-- Tự động đưa ra khuyến nghị
SELECT 
    CASE 
        WHEN NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = 'text_labeling_system' 
            AND TABLE_NAME = 'documents' 
            AND COLUMN_NAME = 'uploaded_by'
        ) THEN 'RECOMMENDATION: Run "Safe Column Fix" script to add missing columns'
        
        WHEN NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = 'text_labeling_system' 
            AND TABLE_NAME = 'reviews'
        ) THEN 'RECOMMENDATION: Run "Quick Fix Script" to create missing reviews table'
        
        ELSE 'RECOMMENDATION: Your database structure looks good, you may just need to recreate views'
    END as recommendation;