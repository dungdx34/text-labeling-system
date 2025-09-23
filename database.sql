-- Text Labeling System Database Schema
-- Run this SQL to create all required tables

CREATE DATABASE IF NOT EXISTS text_labeling_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE text_labeling_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'labeler', 'reviewer') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Document groups table (for multi-document collections)
CREATE TABLE IF NOT EXISTS document_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    ai_summary TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Documents table (can be single or part of a group)
CREATE TABLE IF NOT EXISTS documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    ai_summary TEXT NULL, -- Only for single documents
    type ENUM('single', 'multi') NOT NULL,
    group_id INT NULL, -- Only for multi-documents
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
    INDEX idx_type (type),
    INDEX idx_group_id (group_id)
);

-- Labeling tasks table
CREATE TABLE IF NOT EXISTS labeling_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NULL, -- For single documents
    group_id INT NULL, -- For multi-documents
    assigned_to INT NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_status (status)
);

-- Sentence selections table (step 1 of labeling)
CREATE TABLE IF NOT EXISTS sentence_selections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    document_id INT NOT NULL, -- Which specific document in case of multi-doc
    sentence_text TEXT NOT NULL,
    sentence_position INT NOT NULL, -- Position in the document
    is_selected BOOLEAN DEFAULT FALSE,
    importance_score INT DEFAULT 0, -- 1-5 scale
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX idx_task_document (task_id, document_id)
);

-- Text styles table (step 2 of labeling)
CREATE TABLE IF NOT EXISTS text_styles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    style_type ENUM('formal', 'informal', 'academic', 'conversational', 'technical', 'creative') NOT NULL,
    confidence_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE
);

-- Summary edits table (step 3 of labeling)
CREATE TABLE IF NOT EXISTS summary_edits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    original_summary TEXT NOT NULL,
    edited_summary TEXT NOT NULL,
    edit_type ENUM('minor', 'major', 'complete_rewrite') NOT NULL,
    edit_reason TEXT,
    quality_score INT DEFAULT 0, -- 1-10 scale
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE
);

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    status ENUM('approved', 'rejected', 'needs_revision') NOT NULL,
    overall_score INT DEFAULT 0, -- 1-10 scale
    sentence_selection_feedback TEXT,
    style_feedback TEXT,
    summary_feedback TEXT,
    general_comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES labeling_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50), -- 'document', 'task', 'user', etc.
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created_at (created_at)
);

-- Insert default admin user
INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@textlabeling.com', 'System Administrator', 'admin');

-- Insert sample users for testing
INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES 
('labeler1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'labeler1@test.com', 'Labeler User 1', 'labeler'),
('reviewer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'reviewer1@test.com', 'Reviewer User 1', 'reviewer');

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_documents_created_by ON documents(created_by);
CREATE INDEX IF NOT EXISTS idx_tasks_priority_status ON labeling_tasks(priority, status);
CREATE INDEX IF NOT EXISTS idx_reviews_status ON reviews(status);

-- Create views for common queries
CREATE OR REPLACE VIEW task_summary AS
SELECT 
    t.id as task_id,
    t.status,
    t.priority,
    COALESCE(d.title, dg.title) as title,
    COALESCE(d.type, 'multi') as type,
    u_assigned.full_name as assigned_to_name,
    u_creator.full_name as assigned_by_name,
    t.assigned_at,
    t.completed_at
FROM labeling_tasks t
LEFT JOIN documents d ON t.document_id = d.id
LEFT JOIN document_groups dg ON t.group_id = dg.id
LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
LEFT JOIN users u_creator ON t.assigned_by = u_creator.id;

-- Create view for user statistics
CREATE OR REPLACE VIEW user_stats AS
SELECT 
    u.id,
    u.username,
    u.full_name,
    u.role,
    COALESCE(task_counts.total_tasks, 0) as total_tasks,
    COALESCE(task_counts.completed_tasks, 0) as completed_tasks,
    COALESCE(task_counts.pending_tasks, 0) as pending_tasks,
    COALESCE(review_counts.total_reviews, 0) as total_reviews
FROM users u
LEFT JOIN (
    SELECT 
        assigned_to,
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks
    FROM labeling_tasks 
    GROUP BY assigned_to
) task_counts ON u.id = task_counts.assigned_to
LEFT JOIN (
    SELECT 
        reviewer_id,
        COUNT(*) as total_reviews
    FROM reviews 
    GROUP BY reviewer_id
) review_counts ON u.id = review_counts.reviewer_id;

-- Note: Default password for all users is 'password123'