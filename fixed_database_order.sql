-- ================================================
-- FIXED DATABASE SCRIPT - CORRECT ORDER
-- Tạo bảng trước, views sau để tránh lỗi
-- ================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `text_labeling_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `text_labeling_system`;

-- ================================================
-- BƯỚC 1: XÓA CÁC VIEWS CŨ (NẾU CÓ)
-- ================================================
DROP VIEW IF EXISTS `monthly_stats`;
DROP VIEW IF EXISTS `daily_stats`;
DROP VIEW IF EXISTS `user_performance`;

-- ================================================
-- BƯỚC 2: TẠO TẤT CẢ CÁC BẢNG TRƯỚC
-- ================================================

-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','labeler','reviewer') NOT NULL DEFAULT 'labeler',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: documents
CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `ai_summary` text DEFAULT NULL,
  `type` enum('single','multi') NOT NULL DEFAULT 'single',
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('pending','assigned','completed','reviewed') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `fk_documents_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: document_groups
CREATE TABLE IF NOT EXISTS `document_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `combined_ai_summary` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('pending','assigned','completed','reviewed') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `fk_document_groups_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: document_group_items
CREATE TABLE IF NOT EXISTS `document_group_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_document` (`group_id`, `document_id`),
  KEY `fk_group_items_group` (`group_id`),
  KEY `fk_group_items_document` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: labeling_tasks
CREATE TABLE IF NOT EXISTS `labeling_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) DEFAULT NULL,
  `document_group_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','in_progress','completed','reviewed') DEFAULT 'pending',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `deadline` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_tasks_document` (`document_id`),
  KEY `fk_tasks_document_group` (`document_group_id`),
  KEY `fk_tasks_assigned_to` (`assigned_to`),
  KEY `fk_tasks_assigned_by` (`assigned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sentence_selections
CREATE TABLE IF NOT EXISTS `sentence_selections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `sentence_text` text NOT NULL,
  `sentence_index` int(11) NOT NULL,
  `is_important` tinyint(1) DEFAULT 1,
  `importance_score` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_selections_task` (`task_id`),
  KEY `fk_selections_document` (`document_id`),
  KEY `idx_sentence_index` (`sentence_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: writing_styles
CREATE TABLE IF NOT EXISTS `writing_styles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `style_name` varchar(100) NOT NULL,
  `style_description` text DEFAULT NULL,
  `selected` tinyint(1) DEFAULT 0,
  `custom_style` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_styles_task` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: edited_summaries
CREATE TABLE IF NOT EXISTS `edited_summaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `original_summary` text NOT NULL,
  `edited_summary` text NOT NULL,
  `edit_reason` text DEFAULT NULL,
  `quality_score` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_summaries_task` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: reviews (QUAN TRỌNG - TẠO TRƯỚC KHI TẠO VIEWS)
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
  KEY `fk_reviews_task` (`task_id`),
  KEY `fk_reviews_reviewer` (`reviewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: activity_logs
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext DEFAULT NULL,
  `new_values` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_logs_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: system_settings
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- BƯỚC 3: THÊM FOREIGN KEY CONSTRAINTS
-- ================================================

-- Add foreign keys với kiểm tra tồn tại
SET @foreign_key_checks = @@foreign_key_checks;
SET foreign_key_checks = 0;

-- Documents foreign keys
ALTER TABLE `documents` DROP FOREIGN KEY IF EXISTS `fk_documents_uploaded_by`;
ALTER TABLE `documents` ADD CONSTRAINT `fk_documents_uploaded_by` 
  FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Document groups foreign keys  
ALTER TABLE `document_groups` DROP FOREIGN KEY IF EXISTS `fk_document_groups_created_by`;
ALTER TABLE `document_groups` ADD CONSTRAINT `fk_document_groups_created_by` 
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Document group items foreign keys
ALTER TABLE `document_group_items` DROP FOREIGN KEY IF EXISTS `fk_group_items_group`;
ALTER TABLE `document_group_items` ADD CONSTRAINT `fk_group_items_group` 
  FOREIGN KEY (`group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE;
  
ALTER TABLE `document_group_items` DROP FOREIGN KEY IF EXISTS `fk_group_items_document`;
ALTER TABLE `document_group_items` ADD CONSTRAINT `fk_group_items_document` 
  FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

-- Labeling tasks foreign keys
ALTER TABLE `labeling_tasks` DROP FOREIGN KEY IF EXISTS `fk_tasks_document`;
ALTER TABLE `labeling_tasks` ADD CONSTRAINT `fk_tasks_document` 
  FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;
  
ALTER TABLE `labeling_tasks` DROP FOREIGN KEY IF EXISTS `fk_tasks_document_group`;
ALTER TABLE `labeling_tasks` ADD CONSTRAINT `fk_tasks_document_group` 
  FOREIGN KEY (`document_group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE;
  
ALTER TABLE `labeling_tasks` DROP FOREIGN KEY IF EXISTS `fk_tasks_assigned_to`;
ALTER TABLE `labeling_tasks` ADD CONSTRAINT `fk_tasks_assigned_to` 
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE;
  
ALTER TABLE `labeling_tasks` DROP FOREIGN KEY IF EXISTS `fk_tasks_assigned_by`;
ALTER TABLE `labeling_tasks` ADD CONSTRAINT `fk_tasks_assigned_by` 
  FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Sentence selections foreign keys
ALTER TABLE `sentence_selections` DROP FOREIGN KEY IF EXISTS `fk_selections_task`;
ALTER TABLE `sentence_selections` ADD CONSTRAINT `fk_selections_task` 
  FOREIGN KEY (`task_id`) REFERENCES `labeling_tasks` (`id`) ON DELETE CASCADE;
  
ALTER TABLE `sentence_selections` DROP FOREIGN KEY IF EXISTS `fk_selections_document`;
ALTER TABLE `sentence_selections` ADD CONSTRAINT `fk_selections_document` 
  FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

-- Writing styles foreign keys
ALTER TABLE `writing_styles` DROP FOREIGN KEY IF EXISTS `fk_styles_task`;
ALTER TABLE `writing_styles` ADD CONSTRAINT `fk_styles_task` 
  FOREIGN KEY (`task_id`) REFERENCES `labeling_tasks` (`id`) ON DELETE CASCADE;

-- Edited summaries foreign keys
ALTER TABLE `edited_summaries` DROP FOREIGN KEY IF EXISTS `fk_summaries_task`;
ALTER TABLE `edited_summaries` ADD CONSTRAINT `fk_summaries_task` 
  FOREIGN KEY (`task_id`) REFERENCES `labeling_tasks` (`id`) ON DELETE CASCADE;

-- Reviews foreign keys
ALTER TABLE `reviews` DROP FOREIGN KEY IF EXISTS `fk_reviews_task`;
ALTER TABLE `reviews` ADD CONSTRAINT `fk_reviews_task` 
  FOREIGN KEY (`task_id`) REFERENCES `labeling_tasks` (`id`) ON DELETE CASCADE;
  
ALTER TABLE `reviews` DROP FOREIGN KEY IF EXISTS `fk_reviews_reviewer`;
ALTER TABLE `reviews` ADD CONSTRAINT `fk_reviews_reviewer` 
  FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Activity logs foreign keys
ALTER TABLE `activity_logs` DROP FOREIGN KEY IF EXISTS `fk_logs_user`;
ALTER TABLE `activity_logs` ADD CONSTRAINT `fk_logs_user` 
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

SET foreign_key_checks = @foreign_key_checks;

-- ================================================
-- BƯỚC 4: TẠO CÁC VIEWS SAU KHI ĐÃ CÓ TẤT CẢ BẢNG
-- ================================================

-- View: User performance statistics (BÂY GIỜ MỚI TẠO)
CREATE OR REPLACE VIEW `user_performance` AS
SELECT 
    u.id,
    u.username,
    u.role,
    COALESCE(COUNT(DISTINCT t.id), 0) as total_tasks,
    COALESCE(COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END), 0) as completed_tasks,
    COALESCE(COUNT(DISTINCT CASE WHEN r.review_status = 'approved' THEN t.id END), 0) as approved_tasks,
    COALESCE(AVG(CASE WHEN r.review_score IS NOT NULL THEN r.review_score END), 0) as avg_review_score,
    MIN(t.assigned_at) as first_task_date,
    MAX(t.completed_at) as last_completion_date
FROM users u
LEFT JOIN labeling_tasks t ON u.id = t.assigned_to
LEFT JOIN reviews r ON t.id = r.task_id
WHERE u.role IN ('labeler', 'reviewer')
GROUP BY u.id, u.username, u.role;

-- View: Daily statistics
CREATE OR REPLACE VIEW `daily_stats` AS
SELECT 
    DATE(created_at) as stat_date,
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks
FROM labeling_tasks
GROUP BY DATE(created_at)
ORDER BY stat_date DESC;

-- View: Monthly statistics (CÚ PHÁP FIXED)
CREATE OR REPLACE VIEW `monthly_stats` AS
SELECT 
    YEAR(created_at) as stat_year,
    MONTH(created_at) as stat_month,
    CONCAT(YEAR(created_at), '-', LPAD(MONTH(created_at), 2, '0')) as year_month,
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks
FROM labeling_tasks
GROUP BY YEAR(created_at), MONTH(created_at)
ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC;

-- ================================================
-- BƯỚC 5: TẠO INDEXES
-- ================================================

CREATE INDEX IF NOT EXISTS `idx_documents_status` ON `documents` (`status`);
CREATE INDEX IF NOT EXISTS `idx_documents_type` ON `documents` (`type`);
CREATE INDEX IF NOT EXISTS `idx_documents_created_at` ON `documents` (`created_at`);
CREATE INDEX IF NOT EXISTS `idx_tasks_status` ON `labeling_tasks` (`status`);
CREATE INDEX IF NOT EXISTS `idx_tasks_assigned_at` ON `labeling_tasks` (`assigned_at`);
CREATE INDEX IF NOT EXISTS `idx_tasks_completed_at` ON `labeling_tasks` (`completed_at`);
CREATE INDEX IF NOT EXISTS `idx_reviews_status` ON `reviews` (`review_status`);
CREATE INDEX IF NOT EXISTS `idx_reviews_reviewed_at` ON `reviews` (`reviewed_at`);

-- ================================================
-- BƯỚC 6: INSERT DỮ LIỆU MẶC ĐỊNH
-- ================================================

-- Insert admin user nếu chưa có
INSERT IGNORE INTO `users` (`username`, `password`, `email`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin');

-- Insert system settings
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('app_name', 'Text Labeling System', 'string', 'Application name'),
('app_version', '1.0.0', 'string', 'Current application version'),
('max_file_size', '10485760', 'integer', 'Maximum file upload size in bytes'),
('allowed_file_types', '["jsonl", "txt", "json"]', 'string', 'Allowed file types for upload'),
('auto_assign_tasks', 'false', 'boolean', 'Automatically assign tasks to available labelers');

-- ================================================
-- BƯỚC 7: TẠO TRIGGERS
-- ================================================

DROP TRIGGER IF EXISTS `update_document_status_after_task_completion`;
DROP TRIGGER IF EXISTS `log_task_changes`;

DELIMITER //
CREATE TRIGGER `update_document_status_after_task_completion`
    AFTER UPDATE ON `labeling_tasks`
    FOR EACH ROW
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        INSERT IGNORE INTO activity_logs (user_id, action, description) 
        VALUES (NEW.assigned_to, 'trigger_error', 'Error in update_document_status_after_task_completion');
    END;
    
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        -- Update single document status
        IF NEW.document_id IS NOT NULL THEN
            UPDATE documents 
            SET status = 'completed', updated_at = CURRENT_TIMESTAMP
            WHERE id = NEW.document_id 
            AND NOT EXISTS (
                SELECT 1 FROM labeling_tasks 
                WHERE document_id = NEW.document_id 
                AND status != 'completed'
                AND id != NEW.id
            );
        END IF;
        
        -- Update document group status
        IF NEW.document_group_id IS NOT NULL THEN
            UPDATE document_groups 
            SET status = 'completed', updated_at = CURRENT_TIMESTAMP
            WHERE id = NEW.document_group_id 
            AND NOT EXISTS (
                SELECT 1 FROM labeling_tasks 
                WHERE document_group_id = NEW.document_group_id 
                AND status != 'completed'
                AND id != NEW.id
            );
        END IF;
    END IF;
END//

CREATE TRIGGER `log_task_changes`
    AFTER UPDATE ON `labeling_tasks`
    FOR EACH ROW
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Ignore errors in logging
    END;
    
    INSERT INTO activity_logs (user_id, action, description, table_name, record_id, old_values, new_values)
    VALUES (
        NEW.assigned_to,
        'task_updated',
        CONCAT('Task status changed from ', OLD.status, ' to ', NEW.status),
        'labeling_tasks',
        NEW.id,
        CONCAT('{"status":"', OLD.status, '","completed_at":"', IFNULL(OLD.completed_at, 'NULL'), '"}'),
        CONCAT('{"status":"', NEW.status, '","completed_at":"', IFNULL(NEW.completed_at, 'NULL'), '"}')
    );
END//
DELIMITER ;

-- ================================================
-- KIỂM TRA KẾT QUẢ
-- ================================================

SELECT 'TABLES CREATED:' as INFO;
SELECT TABLE_NAME 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'text_labeling_system'
ORDER BY TABLE_NAME;

SELECT 'VIEWS CREATED:' as INFO;
SELECT TABLE_NAME as VIEW_NAME
FROM INFORMATION_SCHEMA.VIEWS 
WHERE TABLE_SCHEMA = 'text_labeling_system'
ORDER BY TABLE_NAME;

SELECT 'TRIGGERS CREATED:' as INFO;
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM INFORMATION_SCHEMA.TRIGGERS 
WHERE TRIGGER_SCHEMA = 'text_labeling_system'
ORDER BY TRIGGER_NAME;

-- Test views
SELECT 'Testing views...' as INFO;
SELECT COUNT(*) as user_performance_rows FROM user_performance;
SELECT COUNT(*) as daily_stats_rows FROM daily_stats;  
SELECT COUNT(*) as monthly_stats_rows FROM monthly_stats;

COMMIT;

SELECT 'DATABASE SETUP COMPLETED SUCCESSFULLY!' as STATUS;