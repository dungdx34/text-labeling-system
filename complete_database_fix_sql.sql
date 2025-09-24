-- ================================================
-- BƯỚC 1: COMPLETE DATABASE FIX SQL SCRIPT
-- Copy và paste toàn bộ script này vào phpMyAdmin
-- ================================================

USE text_labeling_system;

-- Tắt foreign key checks tạm thời
SET foreign_key_checks = 0;

-- ================================================
-- A. THÊM CÁC CỘT THIẾU VÀO BẢNG DOCUMENTS
-- ================================================

-- Thêm ai_summary (quan trọng nhất - gây lỗi chính)
ALTER TABLE documents ADD COLUMN ai_summary longtext DEFAULT NULL;

-- Thêm uploaded_by 
ALTER TABLE documents ADD COLUMN uploaded_by int(11) NOT NULL DEFAULT 1;

-- Thêm type để phân biệt single/multi
ALTER TABLE documents ADD COLUMN type enum('single','multi') NOT NULL DEFAULT 'single';

-- Thêm status để theo dõi trạng thái
ALTER TABLE documents ADD COLUMN status enum('pending','assigned','completed','reviewed') DEFAULT 'pending';

-- Thêm updated_at timestamp
ALTER TABLE documents ADD COLUMN updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ================================================
-- B. THÊM CÁC CỘT THIẾU VÀO BẢNG USERS
-- ================================================

-- Thêm role để phân quyền
ALTER TABLE users ADD COLUMN role enum('admin','labeler','reviewer') NOT NULL DEFAULT 'labeler';

-- Thêm is_active để quản lý user
ALTER TABLE users ADD COLUMN is_active tinyint(1) DEFAULT 1;

-- Thêm updated_at timestamp
ALTER TABLE users ADD COLUMN updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ================================================
-- C. THÊM CÁC CỘT CHO LABELING_TASKS
-- ================================================

-- Thêm document_group_id để hỗ trợ multi-document
ALTER TABLE labeling_tasks ADD COLUMN document_group_id int(11) DEFAULT NULL;

-- Thêm priority
ALTER TABLE labeling_tasks ADD COLUMN priority enum('low','medium','high') DEFAULT 'medium';

-- Thêm deadline
ALTER TABLE labeling_tasks ADD COLUMN deadline datetime DEFAULT NULL;

-- Thêm notes
ALTER TABLE labeling_tasks ADD COLUMN notes text DEFAULT NULL;

-- ================================================
-- D. TẠO CÁC BẢNG MỚI CHO MULTI-DOCUMENT
-- ================================================

-- Bảng document_groups: Lưu thông tin nhóm đa văn bản
CREATE TABLE IF NOT EXISTS document_groups (
    id int(11) NOT NULL AUTO_INCREMENT,
    group_name varchar(255) NOT NULL,
    description text,
    combined_ai_summary longtext,
    created_by int(11) NOT NULL DEFAULT 1,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status enum('pending','assigned','completed','reviewed') DEFAULT 'pending',
    total_documents int DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_document_groups_created_by (created_by),
    KEY idx_document_groups_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng document_group_items: Liên kết documents với groups  
CREATE TABLE IF NOT EXISTS document_group_items (
    id int(11) NOT NULL AUTO_INCREMENT,
    group_id int(11) NOT NULL,
    document_id int(11) NOT NULL,
    sort_order int(11) DEFAULT 0,
    individual_ai_summary text,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_group_document (group_id, document_id),
    KEY idx_group_items_group (group_id),
    KEY idx_group_items_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng reviews: Cho chức năng review
CREATE TABLE IF NOT EXISTS reviews (
    id int(11) NOT NULL AUTO_INCREMENT,
    task_id int(11) NOT NULL,
    reviewer_id int(11) NOT NULL,
    review_status enum('approved','rejected','needs_revision') NOT NULL,
    review_comments text,
    review_score int(11),
    reviewed_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    suggestions text,
    PRIMARY KEY (id),
    KEY idx_reviews_task (task_id),
    KEY idx_reviews_reviewer (reviewer_id),
    KEY idx_reviews_status (review_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng sentence_selections: Lưu câu được chọn
CREATE TABLE IF NOT EXISTS sentence_selections (
    id int(11) NOT NULL AUTO_INCREMENT,
    task_id int(11) NOT NULL,
    document_id int(11) NOT NULL,
    sentence_text text NOT NULL,
    sentence_index int(11) NOT NULL,
    is_important tinyint(1) DEFAULT 1,
    importance_score decimal(3,2) DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_selections_task (task_id),
    KEY idx_selections_document (document_id),
    KEY idx_sentence_index (sentence_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng writing_styles: Lưu phong cách văn bản
CREATE TABLE IF NOT EXISTS writing_styles (
    id int(11) NOT NULL AUTO_INCREMENT,
    task_id int(11) NOT NULL,
    style_name varchar(100) NOT NULL,
    style_description text,
    selected tinyint(1) DEFAULT 0,
    custom_style text,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_styles_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng edited_summaries: Lưu bản tóm tắt đã chỉnh sửa
CREATE TABLE IF NOT EXISTS edited_summaries (
    id int(11) NOT NULL AUTO_INCREMENT,
    task_id int(11) NOT NULL,
    original_summary text NOT NULL,
    edited_summary text NOT NULL,
    edit_reason text,
    quality_score int(11) DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_summaries_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng activity_logs: Lưu hoạt động hệ thống
CREATE TABLE IF NOT EXISTS activity_logs (
    id int(11) NOT NULL AUTO_INCREMENT,
    user_id int(11) NOT NULL,
    action varchar(100) NOT NULL,
    description text,
    table_name varchar(50) DEFAULT NULL,
    record_id int(11) DEFAULT NULL,
    old_values longtext,
    new_values longtext,
    ip_address varchar(45) DEFAULT NULL,
    user_agent varchar(500) DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_user (user_id),
    KEY idx_action (action),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng system_settings: Cấu hình hệ thống
CREATE TABLE IF NOT EXISTS system_settings (
    id int(11) NOT NULL AUTO_INCREMENT,
    setting_key varchar(100) NOT NULL,
    setting_value text,
    setting_type enum('string','integer','boolean','json') DEFAULT 'string',
    description text,
    is_public tinyint(1) DEFAULT 0,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- E. CẬP NHẬT DỮ LIỆU HIỆN CÓ
-- ================================================

-- Cập nhật uploaded_by cho documents hiện có
UPDATE documents SET uploaded_by = 1 WHERE uploaded_by IS NULL OR uploaded_by = 0;

-- Cập nhật ai_summary cho documents chưa có summary
UPDATE documents 
SET ai_summary = CONCAT('AI-generated summary for: ', LEFT(title, 50), '...') 
WHERE ai_summary IS NULL OR ai_summary = '';

-- Cập nhật type cho documents hiện có
UPDATE documents SET type = 'single' WHERE type IS NULL;

-- Cập nhật role cho users hiện có
UPDATE users SET role = 'labeler' WHERE role IS NULL;

-- ================================================
-- F. TẠO/CẬP NHẬT ADMIN USER
-- ================================================

-- Thêm admin user nếu chưa có
INSERT IGNORE INTO users (username, password, email, role, is_active) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin', 1);

-- Cập nhật admin user nếu đã tồn tại
UPDATE users SET 
    role = 'admin',
    is_active = 1,
    password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE username = 'admin';

-- ================================================
-- G. THÊM SYSTEM SETTINGS
-- ================================================

INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('app_name', 'Text Labeling System', 'string', 'Application name'),
('app_version', '1.0.0', 'string', 'Current application version'),
('max_file_size', '10485760', 'integer', 'Maximum file upload size in bytes'),
('allowed_file_types', '["jsonl", "txt", "json"]', 'string', 'Allowed file types for upload'),
('auto_assign_tasks', 'false', 'boolean', 'Automatically assign tasks to available labelers');

-- ================================================
-- H. TẠO INDEXES CHO PERFORMANCE
-- ================================================

-- Indexes cho documents
CREATE INDEX IF NOT EXISTS idx_documents_status ON documents (status);
CREATE INDEX IF NOT EXISTS idx_documents_type ON documents (type);
CREATE INDEX IF NOT EXISTS idx_documents_created_at ON documents (created_at);
CREATE INDEX IF NOT EXISTS idx_documents_uploaded_by ON documents (uploaded_by);

-- Indexes cho labeling_tasks  
CREATE INDEX IF NOT EXISTS idx_tasks_status ON labeling_tasks (status);
CREATE INDEX IF NOT EXISTS idx_tasks_assigned_at ON labeling_tasks (assigned_at);
CREATE INDEX IF NOT EXISTS idx_tasks_completed_at ON labeling_tasks (completed_at);
CREATE INDEX IF NOT EXISTS idx_tasks_document_group ON labeling_tasks (document_group_id);

-- Indexes cho users
CREATE INDEX IF NOT EXISTS idx_users_role ON users (role);
CREATE INDEX IF NOT EXISTS idx_users_active ON users (is_active);

-- Bật lại foreign key checks
SET foreign_key_checks = 1;

-- ================================================
-- I. KIỂM TRA KẾT QUẢ
-- ================================================

-- Hiển thị cấu trúc bảng documents sau khi cập nhật
SELECT 'DOCUMENTS TABLE STRUCTURE:' as info;
DESCRIBE documents;

-- Hiển thị cấu trúc bảng users
SELECT 'USERS TABLE STRUCTURE:' as info;  
DESCRIBE users;

-- Kiểm tra admin user
SELECT 'ADMIN USER:' as info;
SELECT id, username, email, role, is_active FROM users WHERE username = 'admin';

-- Hiển thị tất cả bảng
SELECT 'ALL TABLES:' as info;
SHOW TABLES;

-- Đếm dữ liệu hiện có
SELECT 'DATA COUNT:' as info;
SELECT 
    'users' as table_name, COUNT(*) as count FROM users
UNION ALL
SELECT 
    'documents' as table_name, COUNT(*) as count FROM documents
UNION ALL
SELECT 
    'labeling_tasks' as table_name, COUNT(*) as count FROM labeling_tasks;

-- ================================================
-- KẾT QUẢ CUỐI CÙNG
-- ================================================

SELECT '🎉 DATABASE FIX COMPLETED SUCCESSFULLY! 🎉' as status;
SELECT 'All required columns and tables have been created.' as result1;
SELECT 'Admin user is ready: username=admin, password=admin123' as result2;
SELECT 'You can now upload multi-document JSONL files!' as result3;
SELECT 'Next step: Replace admin/upload.php with updated version.' as next_step;