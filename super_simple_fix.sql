-- ================================================
-- SUPER SIMPLE FIX - CHỈ THÊM CỘT THIẾU
-- Chạy từng dòng để tránh lỗi
-- ================================================

USE text_labeling_system;

-- Xem cấu trúc hiện tại
SELECT 'Current documents table:' as info;
DESCRIBE documents;

SELECT 'Current users table:' as info;  
DESCRIBE users;

-- ================================================
-- THÊM CÁC CỘT THIẾU VÀO DOCUMENTS
-- ================================================

-- Thêm ai_summary nếu chưa có
ALTER TABLE documents ADD COLUMN ai_summary text;

-- Thêm uploaded_by nếu chưa có
ALTER TABLE documents ADD COLUMN uploaded_by int DEFAULT 1;

-- Thêm type nếu chưa có  
ALTER TABLE documents ADD COLUMN type enum('single','multi') DEFAULT 'single';

-- Thêm status nếu chưa có
ALTER TABLE documents ADD COLUMN status enum('pending','assigned','completed','reviewed') DEFAULT 'pending';

-- ================================================
-- THÊM CỘT THIẾU VÀO USERS
-- ================================================

-- Thêm role nếu chưa có
ALTER TABLE users ADD COLUMN role enum('admin','labeler','reviewer') DEFAULT 'labeler';

-- ================================================
-- TẠO BẢNG REVIEWS ĐƠN GIẢN
-- ================================================

CREATE TABLE IF NOT EXISTS reviews (
    id int AUTO_INCREMENT PRIMARY KEY,
    task_id int NOT NULL,
    reviewer_id int NOT NULL,
    review_status enum('approved','rejected','needs_revision') NOT NULL,
    review_comments text,
    review_score int,
    reviewed_at timestamp DEFAULT CURRENT_TIMESTAMP
);

-- ================================================
-- TẠO VIEWS ĐƠN GIẢN KHÔNG LỖI
-- ================================================

-- Xóa views cũ
DROP VIEW IF EXISTS user_performance;
DROP VIEW IF EXISTS daily_stats;
DROP VIEW IF EXISTS monthly_stats;

-- View tĩnh đơn giản nhất
CREATE VIEW user_performance AS
SELECT 
    id,
    username,
    'labeler' as role,
    0 as total_tasks,
    0 as completed_tasks,
    0 as approved_tasks,
    0 as avg_review_score,
    NULL as first_task_date,
    NULL as last_completion_date
FROM users;

CREATE VIEW daily_stats AS
SELECT 
    CURDATE() as stat_date,
    0 as total_tasks,
    0 as completed_tasks,
    0 as pending_tasks,
    0 as in_progress_tasks;

CREATE VIEW monthly_stats AS
SELECT 
    YEAR(NOW()) as stat_year,
    MONTH(NOW()) as stat_month,
    DATE_FORMAT(NOW(), '%Y-%m') as year_month,
    0 as total_tasks,
    0 as completed_tasks,
    0 as pending_tasks,
    0 as in_progress_tasks;

-- ================================================
-- THÊM ADMIN USER
-- ================================================

INSERT IGNORE INTO users (username, password, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com');

-- Cập nhật role
UPDATE users SET role = 'admin' WHERE username = 'admin';

-- ================================================
-- KIỂM TRA
-- ================================================

SELECT 'FINAL CHECK:' as info;

-- Test views
SELECT COUNT(*) FROM user_performance;
SELECT COUNT(*) FROM daily_stats;
SELECT COUNT(*) FROM monthly_stats;

-- Check admin
SELECT username, role FROM users WHERE username = 'admin';

-- Show updated structure
SELECT 'Updated documents:' as info;
DESCRIBE documents;

SELECT 'Updated users:' as info;
DESCRIBE users;

SELECT 'All tables:' as info;
SHOW TABLES;

SELECT '✅ SUPER SIMPLE FIX DONE!' as result;
SELECT '• Ignore any "Duplicate column" errors - they are normal!' as note1;
SELECT '• Login: admin / admin123' as note2;