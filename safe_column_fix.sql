-- ================================================
-- SAFE COLUMN FIX SCRIPT
-- Kiểm tra và thêm các cột thiếu một cách an toàn
-- ================================================

USE `text_labeling_system`;

-- ================================================
-- BƯỚC 1: KIỂM TRA CẤU TRÚC HIỆN TẠI
-- ================================================

SELECT 'CHECKING CURRENT TABLE STRUCTURE...' as info;

-- Hiển thị cấu trúc bảng documents hiện tại
DESCRIBE documents;

-- Kiểm tra tất cả các cột trong bảng documents
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'documents'
ORDER BY ORDINAL_POSITION;

-- ================================================
-- BƯỚC 2: THÊM CÁC CỘT THIẾU AN TOÀN
-- ================================================

-- Thêm cột uploaded_by nếu chưa có
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'documents' 
    AND COLUMN_NAME = 'uploaded_by'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE documents ADD COLUMN uploaded_by int(11) NOT NULL DEFAULT 1 AFTER ai_summary',
    'SELECT "Column uploaded_by already exists" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm cột type nếu chưa có
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'documents' 
    AND COLUMN_NAME = 'type'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE documents ADD COLUMN type enum("single","multi") NOT NULL DEFAULT "single" AFTER uploaded_by',
    'SELECT "Column type already exists" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm cột status nếu chưa có
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'documents' 
    AND COLUMN_NAME = 'status'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE documents ADD COLUMN status enum("pending","assigned","completed","reviewed") DEFAULT "pending" AFTER type',
    'SELECT "Column status already exists" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm cột updated_at nếu chưa có
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'documents' 
    AND COLUMN_NAME = 'updated_at'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE documents ADD COLUMN updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT "Column updated_at already exists" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ================================================
-- BƯỚC 3: KIỂM TRA VÀ SỬA BẢNG USERS
-- ================================================

-- Thêm cột role nếu chưa có
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'role'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE users ADD COLUMN role enum("admin","labeler","reviewer") NOT NULL DEFAULT "labeler" AFTER email',
    'SELECT "Column role already exists in users" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm cột is_active nếu chưa có
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'is_active'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE users ADD COLUMN is_active tinyint(1) DEFAULT 1 AFTER role',
    'SELECT "Column is_active already exists in users" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm cột updated_at cho users nếu chưa có
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'updated_at'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE users ADD COLUMN updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT "Column updated_at already exists in users" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ================================================
-- BƯỚC 4: KIỂM TRA VÀ SỬA BẢNG LABELING_TASKS
-- ================================================

-- Kiểm tra các cột cần thiết trong labeling_tasks
SELECT 'Checking labeling_tasks columns...' as info;

-- Thêm cột document_group_id nếu chưa có
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'labeling_tasks' 
    AND COLUMN_NAME = 'document_group_id'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE labeling_tasks ADD COLUMN document_group_id int(11) DEFAULT NULL AFTER document_id',
    'SELECT "Column document_group_id already exists in labeling_tasks" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm cột priority nếu chưa có
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'labeling_tasks' 
    AND COLUMN_NAME = 'priority'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE labeling_tasks ADD COLUMN priority enum("low","medium","high") DEFAULT "medium" AFTER status',
    'SELECT "Column priority already exists in labeling_tasks" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm cột deadline nếu chưa có
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'labeling_tasks' 
    AND COLUMN_NAME = 'deadline'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE labeling_tasks ADD COLUMN deadline datetime DEFAULT NULL AFTER priority',
    'SELECT "Column deadline already exists in labeling_tasks" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm cột notes nếu chưa có
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'labeling_tasks' 
    AND COLUMN_NAME = 'notes'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE labeling_tasks ADD COLUMN notes text DEFAULT NULL AFTER deadline',
    'SELECT "Column notes already exists in labeling_tasks" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ================================================
-- BƯỚC 5: THÊM FOREIGN KEYS AN TOÀN (CHỈ KHI TẤT CẢ CỘT ĐÃ TỒN TẠI)
-- ================================================

SET foreign_key_checks = 0;

-- Kiểm tra và thêm foreign key cho documents.uploaded_by
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'documents' 
    AND CONSTRAINT_NAME = 'fk_documents_uploaded_by'
);

SET @has_uploaded_by = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'text_labeling_system' 
    AND TABLE_NAME = 'documents' 
    AND COLUMN_NAME = 'uploaded_by'
);

SET @sql = IF(@fk_exists = 0 AND @has_uploaded_by > 0, 
    'ALTER TABLE documents ADD CONSTRAINT fk_documents_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE',
    'SELECT "Foreign key fk_documents_uploaded_by already exists or column missing" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET foreign_key_checks = 1;

-- ================================================
-- BƯỚC 6: TẠO CÁC BẢNG THIẾU KHÁC
-- ================================================

-- Tạo bảng reviews nếu chưa có
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `review_status` enum('approved','rejected','needs_revision') NOT NULL,
  `review_comments` text DEFAULT NULL,
  `review_score` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `suggestions` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reviews_task` (`task_id`),
  KEY `idx_reviews_reviewer` (`reviewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tạo bảng document_groups nếu chưa có
CREATE TABLE IF NOT EXISTS `document_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `combined_ai_summary` text DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('pending','assigned','completed','reviewed') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_document_groups_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- BƯỚC 7: HIỂN thị CẤU TRÚC SAU KHI CẬP NHẬT
-- ================================================

SELECT 'UPDATED TABLE STRUCTURES:' as info;

SELECT 'DOCUMENTS TABLE:' as table_info;
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'documents'
ORDER BY ORDINAL_POSITION;

SELECT 'USERS TABLE:' as table_info;  
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'users'
ORDER BY ORDINAL_POSITION;

SELECT 'LABELING_TASKS TABLE:' as table_info;
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT  
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'text_labeling_system' 
AND TABLE_NAME = 'labeling_tasks'
ORDER BY ORDINAL_POSITION;

-- ================================================
-- BƯỚC 8: TẠO VIEWS AN TOÀN
-- ================================================

-- Xóa views cũ
DROP VIEW IF EXISTS `user_performance`;
DROP VIEW IF EXISTS `daily_stats`;  
DROP VIEW IF EXISTS `monthly_stats`;

-- Tạo view đơn giản không phụ thuộc vào foreign key
CREATE VIEW `user_performance` AS
SELECT 
    u.id,
    u.username,
    u.role,
    COALESCE(COUNT(t.id), 0) as total_tasks,
    COALESCE(SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END), 0) as completed_tasks,
    0 as approved_tasks,  -- Tạm thời set 0 cho đến khi có dữ liệu reviews
    0 as avg_review_score, -- Tạm thời set 0
    MIN(t.assigned_at) as first_task_date,
    MAX(t.completed_at) as last_completion_date
FROM users u
LEFT JOIN labeling_tasks t ON u.id = t.assigned_to
WHERE u.role IN ('labeler', 'reviewer')
GROUP BY u.id, u.username, u.role;

CREATE VIEW `daily_stats` AS
SELECT 
    DATE(assigned_at) as stat_date,
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks
FROM labeling_tasks
GROUP BY DATE(assigned_at)
ORDER BY stat_date DESC;

CREATE VIEW `monthly_stats` AS
SELECT 
    YEAR(assigned_at) as stat_year,
    MONTH(assigned_at) as stat_month,
    CONCAT(YEAR(assigned_at), '-', LPAD(MONTH(assigned_at), 2, '0')) as year_month,
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks
FROM labeling_tasks
GROUP BY YEAR(assigned_at), MONTH(assigned_at)
ORDER BY YEAR(assigned_at) DESC, MONTH(assigned_at) DESC;

-- ================================================
-- BƯỚC 9: INSERT ADMIN USER NẾU CHƯA CÓ
-- ================================================

INSERT IGNORE INTO `users` (`username`, `password`, `email`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin');

-- ================================================
-- BƯỚC 10: KIỂM TRA KẾT QUẢ CUỐI CÙNG  
-- ================================================

SELECT 'COLUMN FIX COMPLETED!' as status;

-- Test các views
SELECT 'Testing views...' as test_info;
SELECT COUNT(*) as user_performance_count FROM user_performance;
SELECT COUNT(*) as daily_stats_count FROM daily_stats;
SELECT COUNT(*) as monthly_stats_count FROM monthly_stats;

-- Hiển thị tất cả bảng
SELECT 'ALL TABLES:' as info;
SELECT TABLE_NAME 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'text_labeling_system'
ORDER BY TABLE_NAME;

SELECT 'SUCCESS: All missing columns added, foreign keys fixed, views created!' as final_message;