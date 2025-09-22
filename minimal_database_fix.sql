-- minimal_database_fix.sql - Ultra Simple Fix for JSONL Upload
-- This creates only essential columns and tables needed for JSONL upload

USE text_labeling_system;

-- Drop any problematic triggers first
DROP TRIGGER IF EXISTS documents_word_count_insert;
DROP TRIGGER IF EXISTS documents_word_count_update;
DROP TRIGGER IF EXISTS ai_summaries_word_count_insert;
DROP TRIGGER IF EXISTS ai_summaries_word_count_update;
DROP TRIGGER IF EXISTS user_summaries_word_count_insert;
DROP TRIGGER IF EXISTS user_summaries_word_count_update;

-- Drop problematic views
DROP VIEW IF EXISTS view_document_summary;
DROP VIEW IF EXISTS view_upload_statistics;
DROP VIEW IF EXISTS view_task_progress;

-- Create document_groups table first (required for foreign key)
CREATE TABLE IF NOT EXISTS document_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    created_by INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_auto_generated_title BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing columns to documents table
ALTER TABLE documents ADD COLUMN IF NOT EXISTS group_id INT NULL;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS word_count INT DEFAULT 0;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS char_count INT DEFAULT 0;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS is_auto_generated_title BOOLEAN DEFAULT FALSE;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS type ENUM('single', 'multi') NOT NULL DEFAULT 'single';

-- Add foreign key constraint for group_id (ignore error if already exists)
SET @sql = 'ALTER TABLE documents ADD CONSTRAINT fk_documents_group_id FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE';
-- Try to add foreign key, ignore if already exists
SET foreign_key_checks = 0;
SET @sql_safe = CONCAT('SET foreign_key_checks = 1; ', @sql);
-- Just add the constraint without checking if it exists (will fail silently if exists)
-- ALTER TABLE documents ADD FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE;

-- Add missing columns to ai_summaries table
ALTER TABLE ai_summaries ADD COLUMN IF NOT EXISTS group_id INT NULL;
ALTER TABLE ai_summaries ADD COLUMN IF NOT EXISTS word_count INT DEFAULT 0;
ALTER TABLE ai_summaries ADD COLUMN IF NOT EXISTS char_count INT DEFAULT 0;
ALTER TABLE ai_summaries ADD COLUMN IF NOT EXISTS summary_type ENUM('ai_generated', 'human_edited', 'mixed') DEFAULT 'ai_generated';

-- Create upload_logs table (essential for JSONL upload tracking)
CREATE TABLE IF NOT EXISTS upload_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by INT NOT NULL DEFAULT 1,
    file_name VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    file_type ENUM('jsonl', 'txt', 'docx', 'manual', 'other') DEFAULT 'jsonl',
    records_processed INT DEFAULT 0,
    records_success INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    auto_generated_titles INT DEFAULT 0,
    upload_type ENUM('single', 'multi', 'mixed', 'manual') DEFAULT 'mixed',
    status ENUM('processing', 'completed', 'failed', 'partially_failed') DEFAULT 'completed',
    error_details LONGTEXT,
    warning_details LONGTEXT,
    processing_time_seconds INT DEFAULT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure admin user exists
INSERT IGNORE INTO users (username, password, full_name, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@textlabeling.local', 'admin');

-- Add some basic indexes for performance
ALTER TABLE documents ADD INDEX IF NOT EXISTS idx_type (type);
ALTER TABLE documents ADD INDEX IF NOT EXISTS idx_group_id (group_id);
ALTER TABLE documents ADD INDEX IF NOT EXISTS idx_auto_generated (is_auto_generated_title);

ALTER TABLE document_groups ADD INDEX IF NOT EXISTS idx_auto_generated (is_auto_generated_title);
ALTER TABLE document_groups ADD INDEX IF NOT EXISTS idx_created_at (created_at);

ALTER TABLE ai_summaries ADD INDEX IF NOT EXISTS idx_group_id (group_id);
ALTER TABLE ai_summaries ADD INDEX IF NOT EXISTS idx_summary_type (summary_type);

ALTER TABLE upload_logs ADD INDEX IF NOT EXISTS idx_upload_date (upload_date);
ALTER TABLE upload_logs ADD INDEX IF NOT EXISTS idx_status (status);

-- Create simple views after all tables are ready
CREATE VIEW view_documents_simple AS
SELECT 
    d.id,
    d.title,
    d.type,
    d.is_auto_generated_title,
    d.created_at,
    COALESCE(d.group_id, 0) as group_id
FROM documents d;

CREATE VIEW view_uploads_simple AS  
SELECT 
    ul.id,
    ul.file_name,
    ul.records_success,
    ul.records_failed,
    ul.auto_generated_titles,
    ul.upload_date,
    ul.status
FROM upload_logs ul;

-- Update word counts for existing records (simple calculation)
UPDATE documents 
SET word_count = CASE 
    WHEN content IS NOT NULL AND TRIM(content) != '' THEN
        (LENGTH(content) - LENGTH(REPLACE(content, ' ', '')) + 1)
    ELSE 0 
END
WHERE word_count = 0;

UPDATE documents 
SET char_count = CASE 
    WHEN content IS NOT NULL THEN LENGTH(content)
    ELSE 0 
END
WHERE char_count = 0;

UPDATE ai_summaries 
SET word_count = CASE 
    WHEN summary IS NOT NULL AND TRIM(summary) != '' THEN
        (LENGTH(summary) - LENGTH(REPLACE(summary, ' ', '')) + 1)
    ELSE 0 
END
WHERE word_count = 0;

-- Final check - show table structure
SELECT 'Database structure updated successfully!' as status;
SELECT 'Essential tables for JSONL upload:' as info;
SHOW TABLES LIKE '%document%';
SHOW TABLES LIKE '%upload%';

-- Show sample counts
SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_users,
    (SELECT COUNT(*) FROM documents) as total_documents,
    (SELECT COUNT(*) FROM document_groups) as total_groups,
    (SELECT COUNT(*) FROM upload_logs) as total_uploads;
