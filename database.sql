-- Text Labeling System Database
-- Tạo database
CREATE DATABASE IF NOT EXISTS text_labeling_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE text_labeling_system;

-- Bảng users (người dùng)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'labeler', 'reviewer') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bảng documents (văn bản)
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    ai_summary TEXT NOT NULL,
    type ENUM('single', 'multi') DEFAULT 'single',
    group_title VARCHAR(255) NULL,
    group_description TEXT NULL,
    group_summary TEXT NULL,
    uploaded_by INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Bảng document_groups (nhóm văn bản cho đa văn bản)
CREATE TABLE document_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    ai_summary TEXT NOT NULL,
    uploaded_by INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Bảng group_documents (liên kết văn bản với nhóm)
CREATE TABLE group_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    document_id INT NOT NULL,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_document (group_id, document_id)
);

-- Bảng assignments (phân công công việc)
CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT NULL,
    group_id INT NULL,
    type ENUM('single', 'multi') NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'reviewed') DEFAULT 'pending',
    assigned_by INT NOT NULL,
    deadline DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES document_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    CHECK ((document_id IS NOT NULL AND group_id IS NULL AND type = 'single') OR 
           (document_id IS NULL AND group_id IS NOT NULL AND type = 'multi'))
);

-- Bảng labeling_results (kết quả gán nhãn)
CREATE TABLE labeling_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    document_id INT NOT NULL,
    selected_sentences JSON,
    writing_style VARCHAR(100),
    edited_summary TEXT,
    step1_completed BOOLEAN DEFAULT FALSE,
    step2_completed BOOLEAN DEFAULT FALSE,
    step3_completed BOOLEAN DEFAULT FALSE,
    auto_saved_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
);

-- Bảng reviews (đánh giá của reviewer)
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comments TEXT,
    feedback JSON,
    status ENUM('pending', 'approved', 'rejected', 'needs_revision') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Bảng activity_logs (nhật ký hoạt động)
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    target_type VARCHAR(50),
    target_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Chèn dữ liệu mẫu
-- Tạo tài khoản admin mặc định
INSERT INTO users (username, password, full_name, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@example.com', 'admin'),
('labeler1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Người gán nhãn 1', 'labeler1@example.com', 'labeler'),
('labeler2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Người gán nhãn 2', 'labeler2@example.com', 'labeler'),
('reviewer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Người đánh giá 1', 'reviewer1@example.com', 'reviewer');

-- Password mặc định cho tất cả tài khoản: "password" (đã hash)

-- Chèn văn bản mẫu
INSERT INTO documents (title, content, ai_summary, type, uploaded_by) VALUES 
('Tin tức về AI', 'Trí tuệ nhân tạo (AI) đang phát triển mạnh mẽ trong nhiều lĩnh vực. Các công ty lớn như Google, Microsoft đang đầu tư mạnh vào nghiên cứu AI. AI được ứng dụng trong y tế, giáo dục, giao thông và nhiều ngành khác. Tuy nhiên, cũng có những lo ngại về tác động của AI đối với việc làm và quyền riêng tư.', 'AI đang phát triển mạnh với đầu tư lớn từ các công ty công nghệ, ứng dụng rộng rãi nhưng cũng có lo ngại về tác động xã hội.', 'single', 1),
('Bài viết về môi trường', 'Biến đổi khí hậu là một trong những thách thức lớn nhất của thế kỷ 21. Nhiệt độ toàn cầu đang tăng do hoạt động của con người. Cần có những biện pháp mạnh mẽ để giảm phát thải khí nhà kính. Các quốc gia cần hợp tác chặt chẽ để bảo vệ môi trường.', 'Biến đổi khí hậu là thách thức lớn do hoạt động con người, cần biện pháp giảm phát thải và hợp tác quốc tế.', 'single', 1);

-- Tạo nhóm văn bản mẫu
INSERT INTO document_groups (title, description, ai_summary, uploaded_by) VALUES 
('Công nghệ và Tương lai', 'Tập hợp các bài viết về xu hướng công nghệ', 'Các bài viết trong nhóm này thảo luận về những xu hướng công nghệ mới nhất và tác động của chúng đến tương lai.', 1);

-- Chèn thêm văn bản cho nhóm
INSERT INTO documents (title, content, ai_summary, type, uploaded_by) VALUES 
('Blockchain và Cryptocurrency', 'Blockchain là công nghệ chuỗi khối đang được quan tâm rộng rãi. Cryptocurrency như Bitcoin đang thay đổi cách chúng ta nghĩ về tiền tệ. Nhiều ngân hàng đang nghiên cứu ứng dụng blockchain. Tuy nhiên, vẫn có nhiều thách thức về bảo mật và quy định.', 'Blockchain và cryptocurrency đang thu hút sự quan tâm lớn, có tiềm năng thay đổi hệ thống tài chính nhưng còn nhiều thách thức.', 'single', 1),
('Internet of Things (IoT)', 'Internet vạn vật (IoT) đang kết nối hàng tỷ thiết bị. Smart home, smart city đang trở thành hiện thực. IoT mang lại tiện ích nhưng cũng đặt ra vấn đề về bảo mật dữ liệu. Cần có tiêu chuẩn chung cho các thiết bị IoT.', 'IoT đang kết nối nhiều thiết bị, tạo ra smart home và smart city, nhưng cần giải quyết vấn đề bảo mật.', 'single', 1);

-- Liên kết văn bản với nhóm
INSERT INTO group_documents (group_id, document_id, order_index) VALUES 
(1, 3, 1),
(1, 4, 2);

-- Tạo assignments mẫu
INSERT INTO assignments (user_id, document_id, type, assigned_by, deadline, notes) VALUES 
(2, 1, 'single', 1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Hãy chú ý đến việc chọn câu quan trọng'),
(2, 2, 'single', 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Văn bản về môi trường cần gán nhãn cẩn thận'),
(3, 1, 'single', 1, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'Assignment cho labeler2');

INSERT INTO assignments (user_id, group_id, type, assigned_by, deadline, notes) VALUES 
(2, 1, 'multi', 1, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Nhóm văn bản về công nghệ - cần xem xét tổng thể');

-- Tạo một số kết quả gán nhãn mẫu
INSERT INTO labeling_results (assignment_id, document_id, selected_sentences, writing_style, edited_summary, step1_completed, step2_completed, step3_completed) VALUES 
(1, 1, '["Trí tuệ nhân tạo (AI) đang phát triển mạnh mẽ trong nhiều lĩnh vực.", "AI được ứng dụng trong y tế, giáo dục, giao thông và nhiều ngành khác."]', 'formal', 'AI đang có sự phát triển mạnh mẽ và được ứng dụng rộng rãi trong nhiều lĩnh vực quan trọng như y tế, giáo dục và giao thông.', TRUE, TRUE, TRUE);

-- Tạo reviews mẫu
INSERT INTO reviews (assignment_id, reviewer_id, rating, comments, status) VALUES 
(1, 4, 4, 'Công việc gán nhãn tốt, cần chú ý thêm về việc chọn câu quan trọng.', 'approved');

-- Tạo activity logs mẫu
INSERT INTO activity_logs (user_id, action, description, target_type, target_id) VALUES 
(1, 'user_login', 'Admin đăng nhập vào hệ thống', 'user', 1),
(1, 'document_upload', 'Upload văn bản mới', 'document', 1),
(1, 'assignment_create', 'Tạo assignment mới', 'assignment', 1),
(2, 'user_login', 'Labeler đăng nhập vào hệ thống', 'user', 2),
(2, 'labeling_complete', 'Hoàn thành gán nhãn', 'assignment', 1);

-- Indexes để tối ưu hiệu suất
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_documents_type ON documents(type);
CREATE INDEX idx_documents_uploaded_by ON documents(uploaded_by);
CREATE INDEX idx_assignments_user_id ON assignments(user_id);
CREATE INDEX idx_assignments_status ON assignments(status);
CREATE INDEX idx_assignments_type ON assignments(type);
CREATE INDEX idx_labeling_results_assignment_id ON labeling_results(assignment_id);
CREATE INDEX idx_reviews_assignment_id ON reviews(assignment_id);
CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

-- Thêm ràng buộc và triggers
DELIMITER //

-- Trigger để cập nhật status assignment khi labeling hoàn thành
CREATE TRIGGER update_assignment_status 
AFTER UPDATE ON labeling_results
FOR EACH ROW
BEGIN
    IF NEW.step1_completed = TRUE AND NEW.step2_completed = TRUE AND NEW.step3_completed = TRUE THEN
        UPDATE assignments 
        SET status = 'completed', updated_at = CURRENT_TIMESTAMP 
        WHERE id = NEW.assignment_id;
    END IF;
END//

-- Trigger để log hoạt động khi tạo assignment
CREATE TRIGGER log_assignment_create
AFTER INSERT ON assignments
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, description, target_type, target_id)
    VALUES (NEW.assigned_by, 'assignment_create', CONCAT('Tạo assignment cho user ID: ', NEW.user_id), 'assignment', NEW.id);
END//

-- Trigger để log hoạt động khi hoàn thành labeling
CREATE TRIGGER log_labeling_complete
AFTER UPDATE ON labeling_results
FOR EACH ROW
BEGIN
    IF OLD.step3_completed = FALSE AND NEW.step3_completed = TRUE THEN
        INSERT INTO activity_logs (user_id, action, description, target_type, target_id)
        SELECT user_id, 'labeling_complete', 'Hoàn thành gán nhãn', 'assignment', NEW.assignment_id
        FROM assignments WHERE id = NEW.assignment_id;
    END IF;
END//

DELIMITER ;

-- Tạo view để thống kê
CREATE VIEW assignment_statistics AS
SELECT 
    u.id as user_id,
    u.full_name,
    u.role,
    COUNT(a.id) as total_assignments,
    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_assignments,
    SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_assignments,
    SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_assignments,
    ROUND(
        (SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id)), 2
    ) as completion_rate
FROM users u
LEFT JOIN assignments a ON u.id = a.user_id
WHERE u.role IN ('labeler', 'reviewer')
GROUP BY u.id, u.full_name, u.role;

-- View để xem thông tin assignment chi tiết
CREATE VIEW assignment_details AS
SELECT 
    a.id,
    a.type,
    a.status,
    a.deadline,
    a.created_at,
    u.full_name as assignee_name,
    u.role as assignee_role,
    admin.full_name as assigned_by_name,
    CASE 
        WHEN a.type = 'single' THEN d.title 
        WHEN a.type = 'multi' THEN dg.title 
    END as title,
    CASE 
        WHEN a.type = 'single' THEN d.content 
        WHEN a.type = 'multi' THEN dg.description 
    END as content,
    lr.step1_completed,
    lr.step2_completed,
    lr.step3_completed,
    r.rating as review_rating,
    r.status as review_status
FROM assignments a
JOIN users u ON a.user_id = u.id
JOIN users admin ON a.assigned_by = admin.id
LEFT JOIN documents d ON a.document_id = d.id AND a.type = 'single'
LEFT JOIN document_groups dg ON a.group_id = dg.id AND a.type = 'multi'
LEFT JOIN labeling_results lr ON a.id = lr.assignment_id
LEFT JOIN reviews r ON a.id = r.assignment_id;

-- Tạo stored procedures
DELIMITER //

-- Procedure để tạo assignment
CREATE PROCEDURE CreateAssignment(
    IN p_user_id INT,
    IN p_document_id INT,
    IN p_group_id INT,
    IN p_type ENUM('single', 'multi'),
    IN p_assigned_by INT,
    IN p_deadline DATE,
    IN p_notes TEXT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    INSERT INTO assignments (user_id, document_id, group_id, type, assigned_by, deadline, notes)
    VALUES (p_user_id, p_document_id, p_group_id, p_type, p_assigned_by, p_deadline, p_notes);
    
    -- Tự động tạo labeling_results entries
    IF p_type = 'single' THEN
        INSERT INTO labeling_results (assignment_id, document_id)
        VALUES (LAST_INSERT_ID(), p_document_id);
    ELSE
        INSERT INTO labeling_results (assignment_id, document_id)
        SELECT LAST_INSERT_ID(), gd.document_id
        FROM group_documents gd
        WHERE gd.group_id = p_group_id;
    END IF;
    
    COMMIT;
END//

-- Procedure để lấy dashboard statistics
CREATE PROCEDURE GetDashboardStats(IN p_user_id INT, IN p_role VARCHAR(20))
BEGIN
    IF p_role = 'admin' THEN
        SELECT 
            (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users,
            (SELECT COUNT(*) FROM documents) as total_documents,
            (SELECT COUNT(*) FROM assignments) as total_assignments,
            (SELECT COUNT(*) FROM assignments WHERE status = 'completed') as completed_assignments;
    ELSEIF p_role = 'labeler' THEN
        SELECT 
            (SELECT COUNT(*) FROM assignments WHERE user_id = p_user_id) as total_assignments,
            (SELECT COUNT(*) FROM assignments WHERE user_id = p_user_id AND status = 'completed') as completed_assignments,
            (SELECT COUNT(*) FROM assignments WHERE user_id = p_user_id AND status = 'in_progress') as in_progress_assignments,
            (SELECT COUNT(*) FROM assignments WHERE user_id = p_user_id AND status = 'pending') as pending_assignments;
    ELSEIF p_role = 'reviewer' THEN
        SELECT 
            (SELECT COUNT(*) FROM assignments WHERE status = 'completed') as available_for_review,
            (SELECT COUNT(*) FROM reviews WHERE reviewer_id = p_user_id) as total_reviews,
            (SELECT COUNT(*) FROM reviews WHERE reviewer_id = p_user_id AND status = 'approved') as approved_reviews,
            (SELECT COUNT(*) FROM reviews WHERE reviewer_id = p_user_id AND status = 'rejected') as rejected_reviews;
    END IF;
END//

DELIMITER ;

-- Thêm cấu hình MySQL
SET GLOBAL sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';

-- Thông báo hoàn thành
SELECT 'Database setup completed successfully!' as message;