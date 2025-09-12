-- Enhanced database structure for single and multi-document labeling

-- Document groups table for multi-document labeling
CREATE TABLE document_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    group_type ENUM('single', 'multi') DEFAULT 'single',
    ai_summary TEXT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'in_progress', 'completed', 'reviewed') DEFAULT 'pending',
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Updated documents table to support document groups
ALTER TABLE documents ADD COLUMN group_id INT NULL;
ALTER TABLE documents ADD COLUMN document_order INT DEFAULT 1;
ALTER TABLE documents ADD FOREIGN KEY (group_id) REFERENCES document_groups(id);

-- Updated labelings table to work with document groups
ALTER TABLE labelings ADD COLUMN group_id INT NULL;
ALTER TABLE labelings ADD COLUMN document_sentences JSON; -- Store sentences for each document in group
ALTER TABLE labelings ADD FOREIGN KEY (group_id) REFERENCES document_groups(id);

-- Create indexes for better performance
CREATE INDEX idx_documents_group_id ON documents(group_id);
CREATE INDEX idx_labelings_group_id ON labelings(group_id);
CREATE INDEX idx_document_groups_status ON document_groups(status);

-- Insert sample multi-document data
INSERT INTO document_groups (title, description, group_type, ai_summary, uploaded_by) VALUES
('Nhóm văn bản về Công nghệ AI', 'Bộ sưu tập các bài viết về trí tuệ nhân tạo', 'multi', 
'Trí tuệ nhân tạo đang phát triển mạnh mẽ và tác động sâu rộng đến nhiều lĩnh vực như y tế, giáo dục, và kinh doanh. Công nghệ AI giúp tự động hóa quy trình, cải thiện hiệu quả và tạo ra những giải pháp thông minh.', 1),
('Văn bản đơn lẻ về Giáo dục', 'Một bài viết về xu hướng giáo dục hiện đại', 'single',
'Giáo dục hiện đại đang chuyển đổi số mạnh mẽ với việc ứng dụng công nghệ vào teaching và learning. Phương pháp học tập cá nhân hóa và học trực tuyến đang trở thành xu thế chủ đạo.', 1);

-- Sample documents for multi-document group
INSERT INTO documents (title, content, group_id, document_order, uploaded_by) VALUES
('AI trong Y tế', 
'Trí tuệ nhân tạo đang cách mạng hóa ngành y tế. Các hệ thống AI có thể chẩn đoán bệnh chính xác hơn con người trong nhiều trường hợp. Máy học giúp phân tích hình ảnh y khoa, dự đoán bệnh và đề xuất phương pháp điều trị tối ưu. Việc ứng dụng AI trong y tế không chỉ giúp tiết kiệm thời gian mà còn nâng cao chất lượng chăm sóc sức khỏe.', 
1, 1, 1),
('AI trong Giáo dục',
'Công nghệ AI đang thay đổi cách chúng ta học và dạy. Hệ thống học tập thích ứng có thể cá nhân hóa trải nghiệm học tập cho từng học sinh. Chatbot giáo dục giúp trả lời câu hỏi 24/7. Phân tích dữ liệu học tập giúp giáo viên hiểu rõ hơn về tiến độ của học sinh và điều chỉnh phương pháp giảng dạy phù hợp.',
1, 2, 1),
('AI trong Kinh doanh',
'Doanh nghiệp đang sử dụng AI để tối ưu hóa quy trình và tăng hiệu quả. Từ chatbot chăm sóc khách hàng đến hệ thống dự đoán nhu cầu thị trường, AI giúp doanh nghiệp đưa ra quyết định thông minh hơn. Automation và machine learning đang giúp giảm chi phí và tăng năng suất.',
1, 3, 1);

-- Sample single document
INSERT INTO documents (title, content, group_id, document_order, uploaded_by) VALUES
('Xu hướng Giáo dục số',
'Giáo dục số đang trở thành xu hướng không thể đảo ngược trong thời đại công nghệ 4.0. Việc tích hợp công nghệ vào giảng dạy không chỉ giúp học sinh tiếp cận kiến thức một cách sinh động mà còn phát triển kỹ năng số cần thiết cho tương lai. Các nền tảng học trực tuyến, thực tế ảo, và trí tuệ nhân tạo đang mở ra những cơ hội học tập mới. Tuy nhiên, việc chuyển đổi số trong giáo dục cũng đặt ra nhiều thách thức về cơ sở hạ tầng, đào tạo giáo viên và đảm bảo công bằng trong tiếp cận giáo dục.',
2, 1, 1);