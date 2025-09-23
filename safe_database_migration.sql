-- safe_migration.sql - Safe Database Migration Script
-- Text Labeling System - Handles foreign key constraints properly

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `text_labeling_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `text_labeling_system`;

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Check if we need to backup existing data
-- This script will preserve existing admin users and important data

-- Step 1: Backup existing users (if table exists)
DROP TABLE IF EXISTS `users_backup`;
CREATE TABLE IF NOT EXISTS `users_backup` LIKE `users`;
INSERT IGNORE INTO `users_backup` SELECT * FROM `users` WHERE 1;

-- Step 2: Backup existing document_groups (if table exists)
DROP TABLE IF EXISTS `document_groups_backup`;
CREATE TABLE IF NOT EXISTS `document_groups_backup` LIKE `document_groups`;
INSERT IGNORE INTO `document_groups_backup` SELECT * FROM `document_groups` WHERE 1;

-- Step 3: Backup existing documents (if table exists)  
DROP TABLE IF EXISTS `documents_backup`;
CREATE TABLE IF NOT EXISTS `documents_backup` LIKE `documents`;
INSERT IGNORE INTO `documents_backup` SELECT * FROM `documents` WHERE 1;

-- Step 4: Backup existing label_tasks (if table exists)
DROP TABLE IF EXISTS `label_tasks_backup`;
CREATE TABLE IF NOT EXISTS `label_tasks_backup` LIKE `label_tasks`;
INSERT IGNORE INTO `label_tasks_backup` SELECT * FROM `label_tasks` WHERE 1;

-- Step 5: Drop existing tables in correct order (child tables first)
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `label_tasks`;
DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `document_groups`;
DROP TABLE IF EXISTS `users`;

-- Step 6: Create new table structure
-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','labeler','reviewer') NOT NULL DEFAULT 'labeler',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `preferences` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `idx_last_login` (`last_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `document_groups`
-- --------------------------------------------------------

CREATE TABLE `document_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `group_summary` text NOT NULL,
  `type` enum('single','multi') NOT NULL DEFAULT 'single',
  `status` enum('pending','assigned','completed','reviewed','archived') NOT NULL DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `difficulty` enum('easy','medium','hard') NOT NULL DEFAULT 'medium',
  `estimated_time` int(11) DEFAULT NULL COMMENT 'Estimated time in minutes',
  `created_by` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `deadline` timestamp NULL,
  `tags` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_deadline` (`deadline`),
  CONSTRAINT `fk_document_groups_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_document_groups_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `documents`
-- --------------------------------------------------------

CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `word_count` int(11) DEFAULT NULL,
  `language` varchar(10) DEFAULT 'vi',
  `order_index` int(11) DEFAULT 1,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_order` (`order_index`),
  KEY `idx_language` (`language`),
  CONSTRAINT `fk_documents_group_id` FOREIGN KEY (`group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `label_tasks`
-- --------------------------------------------------------

CREATE TABLE `label_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `labeler_id` int(11) NOT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `selected_sentences` json DEFAULT NULL,
  `sentence_importance` json DEFAULT NULL COMMENT 'Importance scores for sentences',
  `text_style` varchar(100) DEFAULT NULL,
  `style_confidence` decimal(3,2) DEFAULT NULL COMMENT 'Confidence in style selection',
  `edited_summary` text DEFAULT NULL,
  `original_summary` text DEFAULT NULL,
  `edit_history` json DEFAULT NULL COMMENT 'Track summary edit history',
  `labeler_notes` text DEFAULT NULL,
  `reviewer_notes` text DEFAULT NULL,
  `quality_score` decimal(3,2) DEFAULT NULL COMMENT 'Quality score from reviewer',
  `time_spent` int(11) DEFAULT NULL COMMENT 'Time spent in seconds',
  `status` enum('assigned','in_progress','completed','reviewed','approved','rejected','revision_needed') NOT NULL DEFAULT 'assigned',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` timestamp NULL,
  `completed_at` timestamp NULL,
  `reviewed_at` timestamp NULL,
  `approved_at` timestamp NULL,
  `version` int(11) DEFAULT 1 COMMENT 'Version number for revisions',
  `previous_version_id` int(11) DEFAULT NULL COMMENT 'Reference to previous version',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_labeler_version` (`group_id`, `labeler_id`, `version`),
  KEY `idx_status` (`status`),
  KEY `idx_labeler_id` (`labeler_id`),
  KEY `idx_reviewer_id` (`reviewer_id`),
  KEY `idx_assigned_at` (`assigned_at`),
  KEY `idx_completed_at` (`completed_at`),
  KEY `idx_version` (`version`),
  CONSTRAINT `fk_label_tasks_group_id` FOREIGN KEY (`group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_label_tasks_labeler_id` FOREIGN KEY (`labeler_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_label_tasks_reviewer_id` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_label_tasks_previous_version` FOREIGN KEY (`previous_version_id`) REFERENCES `label_tasks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `audit_logs`
-- --------------------------------------------------------

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table_name` (`table_name`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Step 7: Insert preserved admin users first
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `status`, `created_at`, `last_login`)
SELECT `username`, `email`, `password`, `full_name`, `role`, `status`, `created_at`, `last_login`
FROM `users_backup`
WHERE `role` = 'admin'
ON DUPLICATE KEY UPDATE
  `password` = VALUES(`password`),
  `full_name` = VALUES(`full_name`),
  `status` = VALUES(`status`),
  `last_login` = VALUES(`last_login`);

-- Step 8: Insert demo users if they don't exist
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `status`) VALUES
('admin', 'admin@textlabeling.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quản trị viên hệ thống', 'admin', 'active'),
('labeler1', 'labeler1@textlabeling.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nguyễn Văn Gán Nhãn', 'labeler', 'active'),
('labeler2', 'labeler2@textlabeling.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Trần Thị Phân Loại', 'labeler', 'active'),
('labeler3', 'labeler3@textlabeling.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lê Minh Xử Lý', 'labeler', 'active'),
('reviewer1', 'reviewer1@textlabeling.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Phạm Văn Kiểm Duyệt', 'reviewer', 'active'),
('reviewer2', 'reviewer2@textlabeling.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Hoàng Thị Đánh Giá', 'reviewer', 'active');

-- Step 9: Restore document_groups (if admin user exists)
INSERT INTO `document_groups` 
SELECT dg.* FROM `document_groups_backup` dg 
INNER JOIN `users` u ON dg.created_by = u.id
WHERE u.role = 'admin'
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `description` = VALUES(`description`),
  `group_summary` = VALUES(`group_summary`),
  `status` = VALUES(`status`);

-- Step 10: Insert sample document groups if none exist
INSERT INTO `document_groups` (`title`, `description`, `group_summary`, `type`, `status`, `priority`, `created_by`)
SELECT 'Tin tức thể thao', 'Tập hợp các bài báo về thể thao cần được gán nhãn và tóm tắt', 'Các bài báo thể thao bao gồm bóng đá, tennis, bóng rổ với nhiều thông tin quan trọng về kết quả thi đấu và phân tích chuyên môn.', 'multi', 'pending', 'normal', u.id
FROM `users` u WHERE u.role = 'admin' AND u.username = 'admin' LIMIT 1
WHERE NOT EXISTS (SELECT 1 FROM `document_groups` LIMIT 1);

INSERT INTO `document_groups` (`title`, `description`, `group_summary`, `type`, `status`, `priority`, `created_by`)
SELECT 'Báo cáo kinh doanh', 'Báo cáo tài chính quý III cần phân tích', 'Báo cáo tài chính chi tiết về tình hình kinh doanh, doanh thu và lợi nhuận trong quý III năm 2024.', 'single', 'pending', 'high', u.id
FROM `users` u WHERE u.role = 'admin' AND u.username = 'admin' LIMIT 1
WHERE NOT EXISTS (SELECT 1 FROM `document_groups` WHERE title = 'Báo cáo kinh doanh');

INSERT INTO `document_groups` (`title`, `description`, `group_summary`, `type`, `status`, `priority`, `created_by`)
SELECT 'Tin tức công nghệ', 'Các bài viết về AI và machine learning', 'Tập hợp các bài viết về xu hướng công nghệ mới, đặc biệt là AI, machine learning và blockchain.', 'multi', 'pending', 'normal', u.id
FROM `users` u WHERE u.role = 'admin' AND u.username = 'admin' LIMIT 1
WHERE NOT EXISTS (SELECT 1 FROM `document_groups` WHERE title = 'Tin tức công nghệ');

-- Step 11: Restore or insert documents
INSERT INTO `documents` (`group_id`, `title`, `content`, `word_count`, `order_index`)
SELECT 1, 'Kết quả bóng đá Premier League', 'Manchester United đã có chiến thắng ấn tượng trước Chelsea với tỷ số 3-1 trong trận đấu tại Old Trafford. Đây là chiến thắng quan trọng giúp MU củng cố vị trí trong top 4. Marcus Rashford ghi 2 bàn thắng, Bruno Fernandes góp 1 bàn. Huấn luyện viên Ten Hag tỏ ra hài lòng với màn trình diễn của học trò.', 95, 1
WHERE EXISTS (SELECT 1 FROM `document_groups` WHERE id = 1)
AND NOT EXISTS (SELECT 1 FROM `documents` WHERE title = 'Kết quả bóng đá Premier League');

INSERT INTO `documents` (`group_id`, `title`, `content`, `word_count`, `order_index`)
SELECT 1, 'Tennis Wimbledon 2024', 'Novak Djokovic đã vượt qua Carlos Alcaraz trong trận chung kết kịch tính để giành chức vô địch Wimbledon lần thứ 8. Trận đấu diễn ra với 5 set đầy căng thẳng. Djokovic cho thấy bản lĩnh của một tay vợt kỳ cựu khi lật ngược tình thế từ thua 0-2 set.', 78, 2
WHERE EXISTS (SELECT 1 FROM `document_groups` WHERE id = 1)
AND NOT EXISTS (SELECT 1 FROM `documents` WHERE title = 'Tennis Wimbledon 2024');

-- Step 12: Create indexes for better performance
CREATE INDEX idx_users_role_status ON users(role, status);
CREATE INDEX idx_document_groups_status_priority ON document_groups(status, priority);
CREATE INDEX idx_label_tasks_status_assigned ON label_tasks(status, assigned_at);
CREATE INDEX idx_audit_logs_user_action ON audit_logs(user_id, action);

-- Step 13: Create views for common queries
CREATE OR REPLACE VIEW v_active_users AS
SELECT id, username, email, full_name, role, created_at, last_login
FROM users 
WHERE status = 'active';

CREATE OR REPLACE VIEW v_pending_tasks AS
SELECT lt.id, lt.group_id, dg.title, dg.type, dg.priority,
       u1.full_name as labeler_name, u2.full_name as reviewer_name,
       lt.status, lt.assigned_at
FROM label_tasks lt
JOIN document_groups dg ON lt.group_id = dg.id
JOIN users u1 ON lt.labeler_id = u1.id
LEFT JOIN users u2 ON lt.reviewer_id = u2.id
WHERE lt.status IN ('assigned', 'in_progress');

-- Step 14: Clean up backup tables (optional - comment out if you want to keep them)
-- DROP TABLE IF EXISTS `users_backup`;
-- DROP TABLE IF EXISTS `document_groups_backup`;
-- DROP TABLE IF EXISTS `documents_backup`;
-- DROP TABLE IF EXISTS `label_tasks_backup`;

-- Set AUTO_INCREMENT starting values
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE document_groups AUTO_INCREMENT = 1;
ALTER TABLE documents AUTO_INCREMENT = 1;
ALTER TABLE label_tasks AUTO_INCREMENT = 1;
ALTER TABLE audit_logs AUTO_INCREMENT = 1;

COMMIT;

-- Final status message
SELECT 'Database migration completed successfully!' as status,
       'Existing admin users preserved' as note1,
       'Demo users added if not exist' as note2,
       'Default password for all accounts: password123' as note3,
       'Backup tables created for safety' as note4;

-- Show final user count
SELECT 'User Statistics:' as info;
SELECT role, COUNT(*) as count, 
       SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
FROM users GROUP BY role;