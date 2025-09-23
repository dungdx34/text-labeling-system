-- Enhanced Database Schema for Text Labeling System
-- Fix authentication and role management issues

-- Drop existing tables if they exist
DROP TABLE IF EXISTS `label_tasks`;
DROP TABLE IF EXISTS `document_groups`;
DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `users`;

-- Create users table with enhanced security
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
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create document_groups table
CREATE TABLE `document_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `group_summary` text NOT NULL,
  `type` enum('single','multi') NOT NULL DEFAULT 'single',
  `status` enum('pending','assigned','completed','reviewed') NOT NULL DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_document_groups_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create documents table
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `order_index` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_order` (`order_index`),
  CONSTRAINT `fk_documents_group_id` FOREIGN KEY (`group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create label_tasks table
CREATE TABLE `label_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `labeler_id` int(11) NOT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `selected_sentences` json DEFAULT NULL,
  `text_style` varchar(100) DEFAULT NULL,
  `edited_summary` text DEFAULT NULL,
  `labeler_notes` text DEFAULT NULL,
  `reviewer_notes` text DEFAULT NULL,
  `status` enum('assigned','in_progress','completed','reviewed','approved','rejected') NOT NULL DEFAULT 'assigned',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` timestamp NULL,
  `completed_at` timestamp NULL,
  `reviewed_at` timestamp NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_labeler` (`group_id`, `labeler_id`),
  KEY `idx_status` (`status`),
  KEY `idx_labeler_id` (`labeler_id`),
  KEY `idx_reviewer_id` (`reviewer_id`),
  CONSTRAINT `fk_label_tasks_group_id` FOREIGN KEY (`group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_label_tasks_labeler_id` FOREIGN KEY (`labeler_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_label_tasks_reviewer_id` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample users with properly hashed passwords
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `status`) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 'active'),
('labeler1', 'labeler1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Người gán nhãn 1', 'labeler', 'active'),
('labeler2', 'labeler2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Người gán nhãn 2', 'labeler', 'active'),
('reviewer1', 'reviewer1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Người review 1', 'reviewer', 'active'),
('reviewer2', 'reviewer2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Người review 2', 'reviewer', 'active');

-- Note: Default password for all accounts is 'password123'
-- In production, users should be required to change passwords on first login