-- database.sql - Complete Updated Database Schema with JSONL Support
-- Text Labeling System Database Schema
-- Updated: 2025-01-01 with Optional Query Support

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS text_labeling_system 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE text_labeling_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'labeler', 'reviewer') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document groups table (for multi-document)
CREATE TABLE IF NOT EXISTS document_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_auto_generated_title BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),
    INDEX idx_auto_generated (is_auto_generated_title),
    FULLTEXT idx_title_description (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents table
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    content LONGTEXT NOT NULL,
    type ENUM('single', 'multi') NOT NULL DEFAULT 'single',
    group_id INT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_auto_generated_title BOOLEAN DEFAULT FALSE,
    word_count INT DEFAULT 0,
    char_count INT DEFAULT 0,
    FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type (type),
    INDEX idx_group_id (group_id),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),
    INDEX idx_auto_generated (is_auto_generated_title),
    INDEX idx_word_count (word_count),
    FULLTEXT idx_content (content),
    FULLTEXT idx_title_content (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI summaries table
CREATE TABLE IF NOT EXISTS ai_summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NULL,
    group_id INT NULL,
    summary LONGTEXT NOT NULL,
    summary_type ENUM('ai_generated', 'human_edited', 'mixed') DEFAULT 'ai_generated',
    word_count INT DEFAULT 0,
    char_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
    INDEX idx_document_id (document_id),
    INDEX idx_group_id (group_id),
    INDEX idx_summary_type (summary_type),
    INDEX idx_word_count (word_count),
    FULLTEXT idx_summary (summary),
    CHECK ((document_id IS NOT NULL AND group_id IS NULL) OR 
           (document_id IS NULL AND group_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Labeling tasks table
CREATE TABLE IF NOT EXISTS labeling_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NULL,
    group_id INT NULL,
    assigned_to INT NOT NULL,
    assigned_by INT,
    status ENUM('pending', 'in_progress', 'completed', 'reviewed', 'rejected') DEFAULT 'pending',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    reviewed_at TIMESTAMP NULL,
    deadline TIMESTAMP NULL,
    notes TEXT,
    completion_percentage DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_assigned_by (assigned_by),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_assigned_at (assigned_at),
    INDEX idx_deadline (deadline),
    INDEX idx_completion (completion_percentage),
    CHECK ((document_id IS NOT NULL AND group_id IS NULL) OR 
           (document_id IS NULL AND group_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sentence selections table
CREATE TABLE IF NOT EXISTS sentence_selections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    document_id INT NOT NULL,
    sentence_text LONGTEXT NOT NULL,
    sentence_order INT NOT NULL,
    sentence_start_pos INT DEFAULT 0,
    sentence_end_pos INT DEFAULT 0,
    is_selected BOOLEAN DEFAULT FALSE,
    importance_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    confidence_score DECIMAL(3,2) DEFAULT NULL,
    user_comment TEXT,
    selection_timestamp TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX idx_task_id (task_id),
    INDEX idx_document_id (document_id),
    INDEX idx_is_selected (is_selected),
    INDEX idx_importance_level (importance_level),
    INDEX idx_sentence_order (sentence_order),
    INDEX idx_confidence_score (confidence_score),
    UNIQUE KEY unique_task_doc_sentence (task_id, document_id, sentence_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Text styles table
CREATE TABLE IF NOT EXISTS text_styles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    style_type ENUM('formal', 'informal', 'technical', 'academic', 'journalistic', 'conversational', 'legal', 'medical') NOT NULL,
    confidence_level ENUM('low', 'medium', 'high', 'very_high') DEFAULT 'medium',
    style_score DECIMAL(3,2) DEFAULT NULL,
    detected_features JSON,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE,
    INDEX idx_task_id (task_id),
    INDEX idx_style_type (style_type),
    INDEX idx_confidence_level (confidence_level),
    INDEX idx_style_score (style_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User summaries table (edited by labelers)
CREATE TABLE IF NOT EXISTS user_summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    original_summary LONGTEXT NOT NULL,
    edited_summary LONGTEXT NOT NULL,
    edit_type ENUM('minor', 'major', 'complete_rewrite', 'no_change') DEFAULT 'minor',
    change_notes TEXT,
    edit_count INT DEFAULT 1,
    word_count_original INT DEFAULT 0,
    word_count_edited INT DEFAULT 0,
    similarity_score DECIMAL(5,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE,
    INDEX idx_task_id (task_id),
    INDEX idx_edit_type (edit_type),
    INDEX idx_edit_count (edit_count),
    INDEX idx_similarity_score (similarity_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    rating ENUM('excellent', 'good', 'fair', 'poor', 'needs_revision') DEFAULT 'good',
    quality_score DECIMAL(3,2) DEFAULT NULL,
    accuracy_score DECIMAL(3,2) DEFAULT NULL,
    completeness_score DECIMAL(3,2) DEFAULT NULL,
    feedback TEXT,
    suggestions TEXT,
    approved BOOLEAN DEFAULT FALSE,
    review_time_minutes INT DEFAULT NULL,
    review_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_task_id (task_id),
    INDEX idx_reviewer_id (reviewer_id),
    INDEX idx_rating (rating),
    INDEX idx_approved (approved),
    INDEX idx_quality_score (quality_score),
    INDEX idx_review_date (review_date),
    UNIQUE KEY unique_task_reviewer (task_id, reviewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upload logs table (for tracking JSONL uploads and other uploads)
CREATE TABLE IF NOT EXISTS upload_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by INT NOT NULL,
    file_name VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    file_type ENUM('jsonl', 'txt', 'docx', 'manual', 'other') DEFAULT 'other',
    records_processed INT DEFAULT 0,
    records_success INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    auto_generated_titles INT DEFAULT 0,
    upload_type ENUM('single', 'multi', 'mixed', 'manual') DEFAULT 'mixed',
    status ENUM('processing', 'completed', 'failed', 'partially_failed') DEFAULT 'processing',
    error_details LONGTEXT,
    warning_details LONGTEXT,
    processing_time_seconds INT DEFAULT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_date TIMESTAMP NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_file_type (file_type),
    INDEX idx_upload_type (upload_type),
    INDEX idx_status (status),
    INDEX idx_upload_date (upload_date),
    INDEX idx_processing_time (processing_time_seconds)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User activity logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('login', 'logout', 'create', 'update', 'delete', 'upload', 'assign', 'complete', 'review', 'approve', 'reject') NOT NULL,
    entity_type ENUM('document', 'task', 'user', 'summary', 'review', 'upload', 'system') DEFAULT 'system',
    entity_id INT DEFAULT NULL,
    description TEXT NOT NULL,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_entity_type (entity_type),
    INDEX idx_entity_id (entity_id),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'float', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance metrics table
CREATE TABLE IF NOT EXISTS performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    metric_type ENUM('task_completion_time', 'accuracy_rate', 'productivity', 'quality_score') NOT NULL,
    metric_value DECIMAL(10,4) NOT NULL,
    metric_date DATE NOT NULL,
    additional_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_metric_type (metric_type),
    INDEX idx_metric_date (metric_date),
    INDEX idx_metric_value (metric_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
INSERT IGNORE INTO users (username, password, full_name, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@textlabeling.local', 'admin');

-- Insert sample users for testing
INSERT IGNORE INTO users (username, password, full_name, email, role) VALUES 
('labeler1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Labeler One', 'labeler1@textlabeling.local', 'labeler'),
('labeler2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Labeler Two', 'labeler2@textlabeling.local', 'labeler'),
('reviewer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reviewer One', 'reviewer1@textlabeling.local', 'reviewer'),
('reviewer2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reviewer Two', 'reviewer2@textlabeling.local', 'reviewer');

-- Insert default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES 
('system_name', 'Text Labeling System', 'string', 'Name of the system', TRUE),
('max_upload_size', '50', 'integer', 'Maximum upload file size in MB', FALSE),
('auto_assign_tasks', 'false', 'boolean', 'Automatically assign tasks to available labelers', FALSE),
('task_deadline_days', '7', 'integer', 'Default deadline for tasks in days', FALSE),
('require_review', 'true', 'boolean', 'Require review for completed tasks', FALSE),
('min_sentence_length', '10', 'integer', 'Minimum sentence length for selection', FALSE),
('max_sentence_length', '500', 'integer', 'Maximum sentence length for selection', FALSE),
('auto_generate_titles', 'true', 'boolean', 'Enable automatic title generation for JSONL uploads', FALSE),
('enable_drag_drop_upload', 'true', 'boolean', 'Enable drag and drop file uploads', TRUE),
('system_version', '1.2.0', 'string', 'Current system version', TRUE);

-- Create triggers for automatic word/character counting
DELIMITER //

CREATE TRIGGER IF NOT EXISTS documents_word_count_insert
BEFORE INSERT ON documents
FOR EACH ROW
BEGIN
    SET NEW.word_count = (LENGTH(NEW.content) - LENGTH(REPLACE(NEW.content, ' ', '')) + 1);
    SET NEW.char_count = LENGTH(NEW.content);
END//

CREATE TRIGGER IF NOT EXISTS documents_word_count_update
BEFORE UPDATE ON documents
FOR EACH ROW
BEGIN
    SET NEW.word_count = (LENGTH(NEW.content) - LENGTH(REPLACE(NEW.content, ' ', '')) + 1);
    SET NEW.char_count = LENGTH(NEW.content);
END//

CREATE TRIGGER IF NOT EXISTS ai_summaries_word_count_insert
BEFORE INSERT ON ai_summaries
FOR EACH ROW
BEGIN
    SET NEW.word_count = (LENGTH(NEW.summary) - LENGTH(REPLACE(NEW.summary, ' ', '')) + 1);
    SET NEW.char_count = LENGTH(NEW.summary);
END//

CREATE TRIGGER IF NOT EXISTS ai_summaries_word_count_update
BEFORE UPDATE ON ai_summaries
FOR EACH ROW
BEGIN
    SET NEW.word_count = (LENGTH(NEW.summary) - LENGTH(REPLACE(NEW.summary, ' ', '')) + 1);
    SET NEW.char_count = LENGTH(NEW.summary);
END//

CREATE TRIGGER IF NOT EXISTS user_summaries_word_count_insert
BEFORE INSERT ON user_summaries
FOR EACH ROW
BEGIN
    SET NEW.word_count_original = (LENGTH(NEW.original_summary) - LENGTH(REPLACE(NEW.original_summary, ' ', '')) + 1);
    SET NEW.word_count_edited = (LENGTH(NEW.edited_summary) - LENGTH(REPLACE(NEW.edited_summary, ' ', '')) + 1);
END//

CREATE TRIGGER IF NOT EXISTS user_summaries_word_count_update
BEFORE UPDATE ON user_summaries
FOR EACH ROW
BEGIN
    SET NEW.word_count_original = (LENGTH(NEW.original_summary) - LENGTH(REPLACE(NEW.original_summary, ' ', '')) + 1);
    SET NEW.word_count_edited = (LENGTH(NEW.edited_summary) - LENGTH(REPLACE(NEW.edited_summary, ' ', '')) + 1);
END//

DELIMITER ;

-- Create views for easier data access
CREATE OR REPLACE VIEW view_document_summary AS
SELECT 
    d.id as document_id,
    d.title,
    d.type,
    d.word_count,
    d.char_count,
    d.created_at as document_created,
    dg.id as group_id,
    dg.title as group_title,
    ai.summary,
    ai.summary_type,
    ai.word_count as summary_word_count,
    u.username as created_by_username,
    CASE 
        WHEN d.is_auto_generated_title = TRUE THEN 'Auto-generated'
        ELSE 'Manual'
    END as title_source
FROM documents d
LEFT JOIN document_groups dg ON d.group_id = dg.id
LEFT JOIN ai_summaries ai ON (d.id = ai.document_id OR dg.id = ai.group_id)
LEFT JOIN users u ON d.created_by = u.id;

CREATE OR REPLACE VIEW view_task_progress AS
SELECT 
    t.id as task_id,
    t.status,
    t.priority,
    t.completion_percentage,
    t.assigned_at,
    t.deadline,
    DATEDIFF(t.deadline, NOW()) as days_remaining,
    u_assigned.username as assigned_to_username,
    u_assigned.full_name as assigned_to_name,
    u_creator.username as assigned_by_username,
    d.title as document_title,
    d.type as document_type,
    dg.title as group_title,
    CASE 
        WHEN t.status = 'completed' THEN 
            TIMESTAMPDIFF(HOUR, t.assigned_at, t.completed_at)
        ELSE 
            TIMESTAMPDIFF(HOUR, t.assigned_at, NOW())
    END as hours_spent
FROM labeling_tasks t
JOIN users u_assigned ON t.assigned_to = u_assigned.id
LEFT JOIN users u_creator ON t.assigned_by = u_creator.id
LEFT JOIN documents d ON t.document_id = d.id
LEFT JOIN document_groups dg ON t.group_id = dg.id;

CREATE OR REPLACE VIEW view_upload_statistics AS
SELECT 
    ul.id,
    ul.file_name,
    ul.file_type,
    ul.upload_type,
    ul.status,
    ul.records_processed,
    ul.records_success,
    ul.records_failed,
    ul.auto_generated_titles,
    ul.processing_time_seconds,
    ul.upload_date,
    u.username as uploaded_by_username,
    u.full_name as uploaded_by_name,
    ROUND((ul.records_success / NULLIF(ul.records_processed, 0)) * 100, 2) as success_rate_percentage
FROM upload_logs ul
JOIN users u ON ul.uploaded_by = u.id;

-- Add some helpful indexes for performance
CREATE INDEX idx_documents_created_month ON documents(YEAR(created_at), MONTH(created_at));
CREATE INDEX idx_tasks_status_priority ON labeling_tasks(status, priority);
CREATE INDEX idx_uploads_date_status ON upload_logs(upload_date, status);
CREATE INDEX idx_activities_user_date ON activity_logs(user_id, DATE(created_at));

-- Add constraints for data integrity
ALTER TABLE documents ADD CONSTRAINT chk_word_count_positive CHECK (word_count >= 0);
ALTER TABLE documents ADD CONSTRAINT chk_char_count_positive CHECK (char_count >= 0);
ALTER TABLE ai_summaries ADD CONSTRAINT chk_summary_word_count_positive CHECK (word_count >= 0);
ALTER TABLE labeling_tasks ADD CONSTRAINT chk_completion_percentage CHECK (completion_percentage >= 0 AND completion_percentage <= 100);
ALTER TABLE upload_logs ADD CONSTRAINT chk_file_size_positive CHECK (file_size >= 0);
ALTER TABLE upload_logs ADD CONSTRAINT chk_records_consistency CHECK (records_processed >= (records_success + records_failed));

-- Final optimizations
OPTIMIZE TABLE users;
OPTIMIZE TABLE documents;
OPTIMIZE TABLE document_groups;
OPTIMIZE TABLE ai_summaries;
OPTIMIZE TABLE labeling_tasks;
OPTIMIZE TABLE upload_logs;
OPTIMIZE TABLE activity_logs;