-- quick_database_fix.sql - Simple Fix Without Complex Triggers
-- Use this if you want to avoid trigger issues completely

USE text_labeling_system;

-- Drop any existing problematic triggers
DROP TRIGGER IF EXISTS documents_word_count_insert;
DROP TRIGGER IF EXISTS documents_word_count_update;
DROP TRIGGER IF EXISTS ai_summaries_word_count_insert;
DROP TRIGGER IF EXISTS ai_summaries_word_count_update;
DROP TRIGGER IF EXISTS user_summaries_word_count_insert;
DROP TRIGGER IF EXISTS user_summaries_word_count_update;

-- Add the missing columns if they don't exist
ALTER TABLE documents 
ADD COLUMN IF NOT EXISTS word_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS char_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_auto_generated_title BOOLEAN DEFAULT FALSE;

ALTER TABLE document_groups
ADD COLUMN IF NOT EXISTS is_auto_generated_title BOOLEAN DEFAULT FALSE;

ALTER TABLE ai_summaries 
ADD COLUMN IF NOT EXISTS word_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS char_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS summary_type ENUM('ai_generated', 'human_edited', 'mixed') DEFAULT 'ai_generated';

ALTER TABLE user_summaries
ADD COLUMN IF NOT EXISTS word_count_original INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS word_count_edited INT DEFAULT 0;

-- Create upload_logs table if it doesn't exist
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
    INDEX idx_upload_date (upload_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing indexes if they don't exist
ALTER TABLE documents ADD INDEX IF NOT EXISTS idx_auto_generated (is_auto_generated_title);
ALTER TABLE documents ADD INDEX IF NOT EXISTS idx_word_count (word_count);
ALTER TABLE document_groups ADD INDEX IF NOT EXISTS idx_auto_generated (is_auto_generated_title);
ALTER TABLE ai_summaries ADD INDEX IF NOT EXISTS idx_summary_type (summary_type);
ALTER TABLE ai_summaries ADD INDEX IF NOT EXISTS idx_word_count (word_count);

-- Insert default users if they don't exist
INSERT IGNORE INTO users (username, password, full_name, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@textlabeling.local', 'admin'),
('labeler1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Labeler One', 'labeler1@textlabeling.local', 'labeler'),
('reviewer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reviewer One', 'reviewer1@textlabeling.local', 'reviewer');

-- Create system_settings table if it doesn't exist
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'float', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES 
('system_name', 'Text Labeling System', 'string', 'Name of the system', TRUE),
('max_upload_size', '50', 'integer', 'Maximum upload file size in MB', FALSE),
('auto_generate_titles', 'true', 'boolean', 'Enable automatic title generation for JSONL uploads', FALSE),
('system_version', '1.2.0', 'string', 'Current system version', TRUE);

-- Create simple views without complex joins
CREATE OR REPLACE VIEW view_document_summary AS
SELECT 
    d.id as document_id,
    d.title,
    d.type,
    d.word_count,
    d.char_count,
    d.is_auto_generated_title,
    d.created_at as document_created,
    d.group_id,
    u.username as created_by_username
FROM documents d
LEFT JOIN users u ON d.created_by = u.id;

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
    ul.upload_date,
    u.username as uploaded_by_username
FROM upload_logs ul
JOIN users u ON ul.uploaded_by = u.id;

-- Update word counts for existing data (without triggers)
UPDATE documents SET 
    word_count = CASE 
        WHEN content IS NOT NULL AND content != '' THEN
            (CHAR_LENGTH(content) - CHAR_LENGTH(REPLACE(content, ' ', '')) + 1)
        ELSE 0
    END,
    char_count = CASE 
        WHEN content IS NOT NULL THEN CHAR_LENGTH(content)
        ELSE 0
    END
WHERE word_count = 0;

UPDATE ai_summaries SET 
    word_count = CASE 
        WHEN summary IS NOT NULL AND summary != '' THEN
            (CHAR_LENGTH(summary) - CHAR_LENGTH(REPLACE(summary, ' ', '')) + 1)
        ELSE 0
    END,
    char_count = CASE 
        WHEN summary IS NOT NULL THEN CHAR_LENGTH(summary)
        ELSE 0
    END
WHERE word_count = 0;

-- Optimize tables
OPTIMIZE TABLE users;
OPTIMIZE TABLE documents;
OPTIMIZE TABLE document_groups;
OPTIMIZE TABLE ai_summaries;
OPTIMIZE TABLE upload_logs;

SELECT 'Database updated successfully! Ready for JSONL upload.' as status;
