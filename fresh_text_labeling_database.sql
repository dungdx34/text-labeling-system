-- Fresh Text Labeling System Database
-- Complete database structure from scratch
-- Run this on a fresh empty database

SET FOREIGN_KEY_CHECKS = 0;

-- ========================================
-- 1. USERS TABLE
-- ========================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(255) NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'labeler') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status),
    INDEX idx_users_email (email)
);

-- ========================================
-- 2. TEXT STYLES TABLE
-- ========================================
CREATE TABLE text_styles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    style_config JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_styles_active (is_active)
);

-- ========================================
-- 3. DOCUMENT GROUPS TABLE
-- ========================================
CREATE TABLE document_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    group_type ENUM('single', 'multi') DEFAULT 'single',
    ai_summary TEXT NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('pending', 'in_progress', 'completed', 'reviewed') DEFAULT 'pending',
    INDEX idx_group_status (status),
    INDEX idx_group_type (group_type),
    INDEX idx_group_uploaded_by (uploaded_by),
    INDEX idx_group_created_at (created_at),
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- 4. DOCUMENTS TABLE
-- ========================================
CREATE TABLE documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    document_order INT DEFAULT 1,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_documents_group_id (group_id),
    INDEX idx_documents_uploaded_by (uploaded_by),
    INDEX idx_documents_created_at (created_at),
    INDEX idx_documents_group_order (group_id, document_order),
    FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- 5. LABELINGS TABLE
-- ========================================
CREATE TABLE labelings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NULL,
    group_id INT NULL,
    labeler_id INT NOT NULL,
    selected_sentences JSON NULL,
    document_sentences JSON NULL,
    text_style_id INT NULL,
    edited_summary TEXT NULL,
    ai_summary_edited TEXT NULL,
    status ENUM('assigned', 'in_progress', 'completed', 'reviewed') DEFAULT 'assigned',
    assigned_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_labelings_document_id (document_id),
    INDEX idx_labelings_group_id (group_id),
    INDEX idx_labelings_labeler_id (labeler_id),
    INDEX idx_labelings_status (status),
    INDEX idx_labelings_labeler_status (labeler_id, status),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (labeler_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (text_style_id) REFERENCES text_styles(id) ON DELETE SET NULL
);

-- ========================================
-- 6. LABELING LOGS TABLE
-- ========================================
CREATE TABLE labeling_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NULL,
    document_id INT NULL,
    labeler_id INT NOT NULL,
    action ENUM('assigned', 'started', 'saved', 'completed', 'reviewed') NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_group_id (group_id),
    INDEX idx_logs_document_id (document_id),
    INDEX idx_logs_labeler_id (labeler_id),
    INDEX idx_logs_action (action),
    INDEX idx_logs_created_at (created_at),
    FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (labeler_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- 7. USER PREFERENCES TABLE
-- ========================================
CREATE TABLE user_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_preference (user_id, preference_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- 8. CREATE VIEWS
-- ========================================

-- View for group statistics
CREATE VIEW v_group_stats AS
SELECT 
    dg.id,
    dg.title,
    dg.group_type,
    dg.status,
    dg.created_at,
    COALESCE(u.full_name, u.username, 'Unknown') as uploaded_by_name,
    COUNT(DISTINCT d.id) as document_count,
    COUNT(DISTINCT l.labeler_id) as labeler_count,
    SUM(CASE WHEN l.status = 'completed' THEN 1 ELSE 0 END) as completed_labelings,
    AVG(CASE WHEN l.status = 'completed' AND l.assigned_at IS NOT NULL AND l.completed_at IS NOT NULL THEN 
        TIMESTAMPDIFF(HOUR, l.assigned_at, l.completed_at) 
        ELSE NULL END) as avg_completion_hours
FROM document_groups dg
LEFT JOIN users u ON dg.uploaded_by = u.id
LEFT JOIN documents d ON dg.id = d.group_id
LEFT JOIN labelings l ON dg.id = l.group_id
GROUP BY dg.id, dg.title, dg.group_type, dg.status, dg.created_at, u.username, u.full_name;

-- View for labeler performance
CREATE VIEW v_labeler_performance AS
SELECT 
    u.id,
    u.username,
    COALESCE(u.full_name, u.username) as display_name,
    COUNT(DISTINCT l.group_id) as total_groups_assigned,
    COUNT(DISTINCT CASE WHEN l.status = 'completed' THEN l.group_id END) as groups_completed,
    COUNT(DISTINCT CASE WHEN l.status IN ('assigned', 'in_progress') THEN l.group_id END) as groups_in_progress,
    AVG(CASE WHEN l.status = 'completed' AND l.assigned_at IS NOT NULL AND l.completed_at IS NOT NULL
        THEN TIMESTAMPDIFF(HOUR, l.assigned_at, l.completed_at) 
        ELSE NULL END) as avg_completion_hours,
    MAX(l.completed_at) as last_completion
FROM users u
LEFT JOIN labelings l ON u.id = l.labeler_id
WHERE u.role = 'labeler' AND u.status = 'active'
GROUP BY u.id, u.username, u.full_name;

-- ========================================
-- 9. CREATE STORED PROCEDURES
-- ========================================

DELIMITER $$

-- Procedure to assign a group to a labeler
CREATE PROCEDURE sp_assign_group_to_labeler(
    IN p_group_id INT,
    IN p_labeler_id INT
)
BEGIN
    DECLARE v_group_exists INT DEFAULT 0;
    DECLARE v_labeler_exists INT DEFAULT 0;
    DECLARE v_already_assigned INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Check if group exists and is pending
    SELECT COUNT(*) INTO v_group_exists 
    FROM document_groups 
    WHERE id = p_group_id AND status = 'pending';
    
    -- Check if labeler exists and is active
    SELECT COUNT(*) INTO v_labeler_exists 
    FROM users 
    WHERE id = p_labeler_id AND role = 'labeler' AND status = 'active';
    
    -- Check if already assigned
    SELECT COUNT(*) INTO v_already_assigned 
    FROM labelings 
    WHERE group_id = p_group_id AND labeler_id = p_labeler_id;
    
    IF v_group_exists = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Group not found or not available for assignment';
    ELSEIF v_labeler_exists = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Labeler not found or not active';
    ELSEIF v_already_assigned > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Group already assigned to this labeler';
    ELSE
        -- Create labeling assignment
        INSERT INTO labelings (group_id, labeler_id, status, assigned_at)
        VALUES (p_group_id, p_labeler_id, 'assigned', NOW());
        
        -- Update group status
        UPDATE document_groups 
        SET status = 'in_progress' 
        WHERE id = p_group_id;
        
        -- Log the assignment
        INSERT INTO labeling_logs (group_id, labeler_id, action, details)
        VALUES (p_group_id, p_labeler_id, 'assigned', JSON_OBJECT('assigned_at', NOW()));
        
        COMMIT;
        SELECT 'SUCCESS' as status, 'Group assigned successfully' as message;
    END IF;
END$$

-- Procedure to complete a labeling task
CREATE PROCEDURE sp_complete_labeling(
    IN p_group_id INT,
    IN p_labeler_id INT,
    IN p_document_sentences JSON,
    IN p_edited_summary TEXT
)
BEGIN
    DECLARE v_labeling_id INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Get labeling ID
    SELECT id INTO v_labeling_id 
    FROM labelings 
    WHERE group_id = p_group_id AND labeler_id = p_labeler_id;
    
    IF v_labeling_id = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Labeling assignment not found';
    ELSE
        -- Update labeling
        UPDATE labelings 
        SET 
            document_sentences = p_document_sentences,
            ai_summary_edited = p_edited_summary,
            status = 'completed',
            completed_at = NOW()
        WHERE id = v_labeling_id;
        
        -- Update group status
        UPDATE document_groups 
        SET status = 'completed' 
        WHERE id = p_group_id;
        
        -- Log the completion
        INSERT INTO labeling_logs (group_id, labeler_id, action, details)
        VALUES (p_group_id, p_labeler_id, 'completed', 
                JSON_OBJECT('completed_at', NOW(), 'sentences_count', 
                    CASE WHEN p_document_sentences IS NOT NULL 
                         THEN JSON_LENGTH(p_document_sentences) 
                         ELSE 0 END));
        
        COMMIT;
        SELECT 'SUCCESS' as status, 'Labeling completed successfully' as message;
    END IF;
END$$

DELIMITER ;

-- ========================================
-- 10. INSERT SAMPLE DATA
-- ========================================

-- Insert default admin user (password: admin123)
INSERT INTO users (username, full_name, password, email, role, status) VALUES
('admin', 'System Administrator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@textlabeling.com', 'admin', 'active'),
('labeler1', 'Nguyễn Văn A', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'labeler1@textlabeling.com', 'labeler', 'active'),
('labeler2', 'Trần Thị B', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'labeler2@textlabeling.com', 'labeler', 'active');

-- Insert text styles
INSERT INTO text_styles (id, name, description, style_config, is_active) VALUES
(1, 'Formal', 'Văn phong trang trọng, chính thức', '{"tone": "formal", "length": "detailed"}', TRUE),
(2, 'Casual', 'Văn phong thân thiện, gần gũi', '{"tone": "casual", "length": "concise"}', TRUE),
(3, 'Technical', 'Văn phong kỹ thuật, chuyên môn', '{"tone": "technical", "length": "detailed"}', TRUE),
(4, 'Summary', 'Tóm tắt ngắn gọn, súc tích', '{"tone": "neutral", "length": "brief"}', TRUE);

-- Insert sample document groups
INSERT INTO document_groups (title, description, group_type, ai_summary, uploaded_by, status) VALUES
('Nhóm văn bản về Công nghệ AI', 
'Bộ sưu tập các bài viết về trí tuệ nhân tạo và machine learning', 
'multi',
'Trí tuệ nhân tạo đang phát triển mạnh mẽ và tác động sâu rộng đến nhiều lĩnh vực như y tế, giáo dục, và kinh doanh. Công nghệ AI giúp tự động hóa quy trình, cải thiện hiệu quả và tạo ra những giải pháp thông minh cho tương lai.',
1, 'pending'),

('Văn bản đơn về Giáo dục',
'Một bài viết về xu hướng giáo dục hiện đại và công nghệ',
'single', 
'Giáo dục hiện đại đang chuyển đổi số mạnh mẽ với việc ứng dụng công nghệ vào giảng dạy và học tập. Phương pháp học tập cá nhân hóa và học trực tuyến đang trở thành xu thế chủ đạo trong thời đại 4.0.',
1, 'pending'),

('Nhóm văn bản về Blockchain',
'Các bài viết về công nghệ blockchain và ứng dụng',
'multi',
'Blockchain là công nghệ cơ sở dữ liệu phân tán, mang lại tính bảo mật cao và minh bạch. Ứng dụng trong tài chính, supply chain và nhiều lĩnh vực khác.',
1, 'pending');

-- Insert sample documents
INSERT INTO documents (title, content, group_id, document_order, uploaded_by) VALUES
('AI trong Y tế',
'Trí tuệ nhân tạo đang cách mạng hóa ngành y tế thông qua chẩn đoán hình ảnh, phát hiện bệnh sớm, và hỗ trợ điều trị. Các hệ thống AI có thể phân tích hàng triệu hình ảnh X-quang, CT scan trong thời gian ngắn với độ chính xác cao. Điều này giúp các bác sĩ đưa ra quyết định nhanh chóng và chính xác hơn. Ngoài ra, AI còn được ứng dụng trong phát triển thuốc mới, giúp rút ngắn thời gian nghiên cứu từ nhiều năm xuống còn vài tháng.',
1, 1, 1),

('AI trong Giáo dục', 
'Công nghệ AI đang thay đổi cách chúng ta học tập và giảng dạy. Hệ thống học tập thích ứng có thể cá nhân hóa trải nghiệm học tập cho từng học sinh. Chatbot giáo dục cung cấp hỗ trợ 24/7, trong khi phân tích học tập giúp giáo viên theo dõi tiến độ học sinh một cách hiệu quả. AI cũng có thể tự động chấm bài, tạo câu hỏi phù hợp với trình độ từng học sinh.',
1, 2, 1),

('AI trong Kinh doanh',
'Trong lĩnh vực kinh doanh, AI giúp tự động hóa quy trình, phân tích dữ liệu khách hàng và dự đoán xu hướng thị trường. Chatbot AI xử lý hàng nghìn câu hỏi khách hàng mỗi ngày. Hệ thống đề xuất sản phẩm tăng doanh thu lên đáng kể. AI cũng được dùng trong phát hiện gian lận, quản lý rủi ro và tối ưu hóa chuỗi cung ứng.',
1, 3, 1),

('Xu hướng Giáo dục Hiện đại',
'Giáo dục thế kỷ 21 đòi hỏi phương pháp tiếp cận mới. Học tập trực tuyến, thực tế ảo, và gamification đang tạo ra môi trường học tập tương tác và hấp dẫn. Học sinh không chỉ tiếp thu kiến thức mà còn phát triển kỹ năng tư duy phản biện, sáng tạo và giải quyết vấn đề. Giáo viên trở thành người hướng dẫn, hỗ trợ học sinh khám phá và xây dựng kiến thức. Công nghệ như AR/VR mang lại trải nghiệm học tập sinh động, trong khi big data giúp cá nhân hóa quá trình học.',
2, 1, 1),

('Blockchain trong Tài chính',
'Blockchain đang thay đổi ngành tài chính với cryptocurrency, smart contracts và DeFi. Công nghệ này loại bỏ trung gian, giảm chi phí giao dịch và tăng tính minh bạch. Bitcoin và Ethereum đã chứng minh tiềm năng to lớn. Các ngân hàng đang áp dụng blockchain cho chuyển tiền quốc tế, trong khi DeFi mở ra cơ hội cho vay vốn phi tập trung.',
3, 1, 1),

('Blockchain trong Supply Chain',
'Trong quản lý chuỗi cung ứng, blockchain cung cấp khả năng truy xuất nguồn gốc hoàn hảo. Từ nông trại đến bàn ăn, mọi bước được ghi lại bất biến. Điều này đặc biệt quan trọng với thực phẩm, dược phẩm và hàng xa xỉ. Walmart đã triển khai thành công, giảm thời gian truy xuất nguồn gốc từ vài ngày xuống vài giây.',
3, 2, 1);

-- ========================================
-- 11. SET AUTO_INCREMENT VALUES
-- ========================================
ALTER TABLE document_groups AUTO_INCREMENT = 100;
ALTER TABLE documents AUTO_INCREMENT = 1000;
ALTER TABLE labelings AUTO_INCREMENT = 10000;
ALTER TABLE labeling_logs AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================
-- 12. VERIFICATION
-- ========================================
SELECT 'DATABASE CREATED SUCCESSFULLY!' as status;

-- Show table counts
SELECT 
    'users' as table_name, COUNT(*) as row_count FROM users
UNION ALL
SELECT 'text_styles', COUNT(*) FROM text_styles
UNION ALL  
SELECT 'document_groups', COUNT(*) FROM document_groups
UNION ALL
SELECT 'documents', COUNT(*) FROM documents
UNION ALL
SELECT 'labelings', COUNT(*) FROM labelings;

-- Show sample data
SELECT 'Sample Users:' as info;
SELECT id, username, full_name, role, status FROM users;

SELECT 'Sample Document Groups:' as info;
SELECT id, title, group_type, status FROM document_groups;

SELECT 'Sample Documents:' as info;
SELECT id, title, group_id, document_order FROM documents ORDER BY group_id, document_order;