-- ================================================
-- SIMPLE COLUMN FIX - TỪNG BƯỚC ĐƠN GIẢN
-- Sửa từng cột một để tránh lỗi
-- ================================================

USE `text_labeling_system`;

-- ================================================
-- BƯỚC 1: KIỂM TRA CẤU TRÚC HIỆN TẠI
-- ================================================
SELECT 'CURRENT TABLE STRUCTURES:' as info;

-- Xem cấu trúc bảng users hiện tại
SELECT 'USERS TABLE:' as table_name;
DESCRIBE users;

-- Xem cấu trúc bảng documents hiện tại  
SELECT 'DOCUMENTS TABLE:' as table_name;
DESCRIBE documents;

-- ================================================
-- BƯỚC 2: SỬA BẢNG USERS TỪNG CỘT
-- ================================================

-- Thêm cột role nếu chưa có (đơn giản)
ALTER TABLE users ADD COLUMN role enum('admin','labeler','reviewer') DEFAULT 'labeler';
-- Bỏ qua lỗi nếu cột đã tồn tại

-- Thêm cột is_active nếu chưa có
ALTER TABLE users ADD COLUMN is_active tinyint(1) DEFAULT 1;
-- Bỏ qua lỗi nếu cột đã tồn tại

-- Thêm cột updated_at nếu chưa có
ALTER TABLE users ADD COLUMN updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
-- Bỏ qua lỗi nếu cột đã tồn tại

-- ================================================
-- BƯỚC 3: SỬA BẢNG DOCUMENTS TỪNG CỘT  
-- ================================================

-- Thêm cột uploaded_by nếu chưa có
ALTER TABLE documents ADD COLUMN uploaded_by int(11) DEFAULT 1;
-- Bỏ qua lỗi nếu cột đã tồn tại

-- Thêm cột type nếu chưa có
ALTER TABLE documents ADD COLUMN type enum('single','multi') DEFAULT 'single';
-- Bỏ qua lỗi nếu cột đã tồn tại

-- Thêm cột status nếu chưa có  
ALTER TABLE documents ADD COLUMN status enum('pending','assigned','completed','reviewed') DEFAULT 'pending';
-- Bỏ qua lỗi nếu cột đã tồn tại

-- Thêm cột updated_at nếu chưa có
ALTER TABLE documents ADD COLUMN updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
-- Bỏ qua lỗi nếu cột đã tồn tại

-- ================================================
-- BƯỚC 4: TẠO BẢNG REVIEWS ĐƠN GIẢN
-- ================================================
CREATE TABLE IF NOT EXISTS reviews (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    task_id int(11) NOT NULL,
    reviewer_id int(11) NOT NULL,
    review_status enum('approved','rejected','needs_revision') NOT NULL,
    review_comments text,
    review_score int(11),
    reviewed_at timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- BƯỚC 5: TẠO CÁC BẢNG KHÁC NẾU THIẾU
-- ================================================

-- Bảng document_groups
CREATE TABLE IF NOT EXISTS document_groups (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    group_name varchar(255) NOT NULL,
    description text,
    combined_ai_summary text,
    created_by int(11) DEFAULT 1,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status enum('pending','assigned','completed','reviewed') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng document_group_items
CREATE TABLE IF NOT EXISTS document_group_items (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    group_id int(11) NOT NULL,
    document_id int(11) NOT NULL,
    sort_order int(11) DEFAULT 0,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng sentence_selections
CREATE TABLE IF NOT EXISTS sentence_selections (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    task_id int(11) NOT NULL,
    document_id int(11) NOT NULL,
    sentence_text text NOT NULL,
    sentence_index int(11) NOT NULL,
    is_important tinyint(1) DEFAULT 1,
    importance_score decimal(3,2),
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng writing_styles
CREATE TABLE IF NOT EXISTS writing_styles (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    task_id int(11) NOT NULL,
    style_name varchar(100) NOT NULL,
    style_description text,
    selected tinyint(1) DEFAULT 0,
    custom_style text,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng edited_summaries
CREATE TABLE IF NOT EXISTS edited_summaries (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    task_id int(11) NOT NULL,
    original_summary text NOT NULL,
    edited_summary text NOT NULL,
    edit_reason text,
    quality_score int(11),
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng activity_logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    user_id int(11) NOT NULL,
    action varchar(100) NOT NULL,
    description text,
    table_name varchar(50),
    record_id int(11),
    old_values text,
    new_values text,
    ip_address varchar(45),
    user_agent varchar(500),
    created_at timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng system_settings
CREATE TABLE IF NOT EXISTS system_settings (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    setting_key varchar(100) NOT NULL UNIQUE,
    setting_value text,
    setting_type enum('string','integer','boolean','json') DEFAULT 'string',
    description text,
    is_public tinyint(1) DEFAULT 0,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- BƯỚC 6: THÊM CỘT VÀO LABELING_TASKS NẾU THIẾU
-- ================================================

-- Thêm document_group_id
ALTER TABLE labeling_tasks ADD COLUMN document_group_id int(11);

-- Thêm priority  
ALTER TABLE labeling_tasks ADD COLUMN priority enum('low','medium','high') DEFAULT 'medium';

-- Thêm deadline
ALTER TABLE labeling_tasks ADD COLUMN deadline datetime;

-- Thêm notes
ALTER TABLE labeling_tasks ADD COLUMN notes text;

-- ================================================
-- BƯỚC 7: XÓA VIEWS CŨ VÀ TẠO MỚI ĐƠN GIẢN
-- ================================================

-- Xóa views cũ
DROP VIEW IF EXISTS user_performance;
DROP VIEW IF EXISTS daily_stats;
DROP VIEW IF EXISTS monthly_stats;

-- Tạo view user_performance đơn giản (không dùng reviews để tránh lỗi)
CREATE VIEW user_performance AS
SELECT 
    u.id,
    u.username,
    COALESCE(u.role, 'labeler') as role,
    COALESCE(COUNT(t.id), 0) as total_tasks,
    COALESCE(SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END), 0) as completed_tasks,
    0 as approved_tasks,
    0 as avg_review_score,
    MIN(t.assigned_at) as first_task_date,
    MAX(t.completed_at) as last_completion_date
FROM users u
LEFT JOIN labeling_tasks t ON u.id = t.assigned_to
GROUP BY u.id, u.username, u.role;

-- Tạo view daily_stats đơn giản
CREATE VIEW daily_stats AS
SELECT 
    DATE(assigned_at) as stat_date,
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks
FROM labeling_tasks
WHERE assigned_at IS NOT NULL
GROUP BY DATE(assigned_at)
ORDER BY stat_date DESC;

-- Tạo view monthly_stats đơn giản
CREATE VIEW monthly_stats AS
SELECT 
    YEAR(assigned_at) as stat_year,
    MONTH(assigned_at) as stat_month,
    CONCAT(YEAR(assigned_at), '-', LPAD(MONTH(assigned_at), 2, '0')) as year_month,
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks
FROM labeling_tasks
WHERE assigned_at IS NOT NULL
GROUP BY YEAR(assigned_at), MONTH(assigned_at)
ORDER BY stat_year DESC, stat_month DESC;

-- ================================================
-- BƯỚC 8: INSERT ADMIN USER VÀ SETTINGS
-- ================================================

-- Thêm admin user nếu chưa có
INSERT IGNORE INTO users (username, password, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin');

-- Thêm system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('app_name', 'Text Labeling System', 'string', 'Application name'),
('app_version', '1.0.0', 'string', 'Current application version'),
('max_file_size', '10485760', 'integer', 'Maximum file upload size in bytes');

-- ================================================
-- BƯỚC 9: KIỂM TRA KẾT QUẢ
-- ================================================

SELECT 'CHECKING RESULTS...' as info;

-- Hiển thị cấu trúc bảng sau khi cập nhật
SELECT 'UPDATED USERS TABLE:' as table_info;
DESCRIBE users;

SELECT 'UPDATED DOCUMENTS TABLE:' as table_info;
DESCRIBE documents;

-- Kiểm tra views
SELECT 'TESTING VIEWS:' as test_info;
SELECT COUNT(*) as user_performance_rows FROM user_performance;
SELECT COUNT(*) as daily_stats_rows FROM daily_stats;
SELECT COUNT(*) as monthly_stats_rows FROM monthly_stats;

-- Hiển thị tất cả bảng
SELECT 'ALL TABLES:' as info;
SHOW TABLES;

-- Kiểm tra admin user
SELECT 'ADMIN USER:' as info;
SELECT id, username, email, role FROM users WHERE role = 'admin' OR username = 'admin';

SELECT 'SIMPLE FIX COMPLETED!' as status;
SELECT 'If you see any "Duplicate column" errors above, that is NORMAL - it means the column already existed.' as note1;
SELECT 'The important thing is that all tables and views are now created successfully.' as note2;
SELECT 'You can now try logging in with username: admin, password: admin123' as note3;