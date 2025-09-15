-- Text Labeling System Database
-- Created: 2025
-- Version: 2.0 (Complete with multi-document support)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database
CREATE DATABASE IF NOT EXISTS `text_labeling_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `text_labeling_system`;

-- --------------------------------------------------------

-- Table structure for table `users`
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `role` enum('admin','labeler','reviewer') NOT NULL DEFAULT 'labeler',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `document_groups`
CREATE TABLE `document_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `group_type` enum('single','multi') NOT NULL DEFAULT 'single',
  `ai_summary` text NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `assigned_labeler` int(11) DEFAULT NULL,
  `assigned_reviewer` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','reviewed') NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `deadline` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_assigned_labeler` (`assigned_labeler`),
  KEY `idx_assigned_reviewer` (`assigned_reviewer`),
  KEY `idx_status` (`status`),
  KEY `idx_group_type` (`group_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_groups_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_groups_labeler` FOREIGN KEY (`assigned_labeler`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_groups_reviewer` FOREIGN KEY (`assigned_reviewer`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `documents`
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `document_order` int(11) NOT NULL DEFAULT 1,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `word_count` int(11) DEFAULT NULL,
  `sentence_count` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_document_order` (`document_order`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_documents_group` FOREIGN KEY (`group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_documents_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `labelings`
CREATE TABLE `labelings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `selected_sentences` json DEFAULT NULL,
  `document_sentences` json DEFAULT NULL,
  `text_style` enum('formal','informal','academic','news','technical','casual') DEFAULT NULL,
  `edited_summary` text,
  `labeling_type` enum('single','multi') NOT NULL DEFAULT 'single',
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completion_time` int(11) DEFAULT NULL COMMENT 'Time in seconds',
  `quality_score` decimal(3,2) DEFAULT NULL COMMENT 'Score from 0.00 to 10.00',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_document_id` (`document_id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_labeling_type` (`labeling_type`),
  KEY `idx_is_completed` (`is_completed`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_labelings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_labelings_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_labelings_group` FOREIGN KEY (`group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `reviews`
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `labeling_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `document_id` int(11) DEFAULT NULL,
  `reviewer_id` int(11) NOT NULL,
  `review_status` enum('pending','approved','rejected','needs_revision') NOT NULL DEFAULT 'pending',
  `quality_rating` int(11) DEFAULT NULL COMMENT 'Rating 1-5',
  `accuracy_rating` int(11) DEFAULT NULL COMMENT 'Rating 1-5',
  `completeness_rating` int(11) DEFAULT NULL COMMENT 'Rating 1-5',
  `review_comments` text,
  `suggested_changes` text,
  `review_time` int(11) DEFAULT NULL COMMENT 'Time in seconds',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_labeling_id` (`labeling_id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_document_id` (`document_id`),
  KEY `idx_reviewer_id` (`reviewer_id`),
  KEY `idx_review_status` (`review_status`),
  KEY `idx_reviewed_at` (`reviewed_at`),
  CONSTRAINT `fk_reviews_labeling` FOREIGN KEY (`labeling_id`) REFERENCES `labelings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_group` FOREIGN KEY (`group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `system_settings`
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text,
  `setting_type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `description` text,
  `is_editable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `activity_logs`
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL COMMENT 'document, labeling, review, user',
  `entity_id` int(11) DEFAULT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Insert default admin user
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `is_active`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@textlabeling.com', 'admin', 1),
(2, 'labeler1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Labeler User 1', 'labeler1@textlabeling.com', 'labeler', 1),
(3, 'reviewer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reviewer User 1', 'reviewer1@textlabeling.com', 'reviewer', 1);

-- Note: Default password for all users is 'password'

-- --------------------------------------------------------

-- Insert system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('site_name', 'Text Labeling System', 'string', 'Name of the application'),
('max_file_size', '10485760', 'integer', 'Maximum file upload size in bytes (10MB)'),
('allowed_file_types', 'txt,docx,pdf', 'string', 'Allowed file extensions for upload'),
('auto_save_interval', '30', 'integer', 'Auto-save interval in seconds'),
('session_timeout', '3600', 'integer', 'Session timeout in seconds'),
('enable_email_notifications', '1', 'boolean', 'Enable email notifications'),
('default_language', 'vi', 'string', 'Default system language'),
('items_per_page', '20', 'integer', 'Default items per page for pagination');

-- --------------------------------------------------------

-- Insert sample document groups
INSERT INTO `document_groups` (`id`, `title`, `description`, `group_type`, `ai_summary`, `uploaded_by`, `assigned_labeler`, `assigned_reviewer`, `status`) VALUES
(1, 'Nhóm văn bản về Công nghệ AI', 'Bộ sưu tập các bài viết về trí tuệ nhân tạo và ứng dụng trong đời sống', 'multi', 
'Trí tuệ nhân tạo đang phát triển mạnh mẽ và tác động sâu rộng đến nhiều lĩnh vực như y tế, giáo dục, và kinh doanh. Công nghệ AI giúp tự động hóa quy trình, cải thiện hiệu quả và tạo ra những giải pháp thông minh cho tương lai. Từ chatbot đến xe tự lái, AI đang thay đổi cách chúng ta sống và làm việc.', 
1, 2, 3, 'pending'),

(2, 'Văn bản về Giáo dục số', 'Một bài viết về xu hướng giáo dục hiện đại và chuyển đổi số', 'single',
'Giáo dục hiện đại đang chuyển đổi số mạnh mẽ với việc ứng dụng công nghệ vào teaching và learning. Phương pháp học tập cá nhân hóa và học trực tuyến đang trở thành xu thế chủ đạo. Các nền tảng e-learning giúp học sinh tiếp cận kiến thức dễ dàng hơn và giáo viên có thể theo dõi tiến độ học tập hiệu quả.',
1, 2, 3, 'pending'),

(3, 'Nhóm văn bản về Môi trường', 'Các bài viết về bảo vệ môi trường và phát triển bền vững', 'multi',
'Bảo vệ môi trường là vấn đề cấp bách của nhân loại. Biến đổi khí hậu, ô nhiễm không khí và nước đang đe dọa nghiêm trọng đến sự sống trên Trái Đất. Cần có những giải pháp toàn diện từ chính sách đến hành động cá nhân để bảo vệ hành tinh xanh cho thế hệ tương lai.',
1, 2, 3, 'pending');

-- --------------------------------------------------------

-- Insert sample documents
INSERT INTO `documents` (`id`, `title`, `content`, `group_id`, `document_order`, `uploaded_by`, `word_count`, `sentence_count`) VALUES
(1, 'AI trong Y tế', 
'Trí tuệ nhân tạo đang cách mạng hóa ngành y tế một cách đáng kinh ngạc. Các hệ thống AI có thể phân tích hình ảnh y khoa với độ chính xác cao, vượt xa khả năng của con người trong một số trường hợp. Chúng hỗ trợ chẩn đoán bệnh sớm, đặc biệt là trong việc phát hiện ung thư qua hình ảnh CT, MRI và X-quang.

Công nghệ machine learning giúp dự đoán tình trạng bệnh nhân và đề xuất phương pháp điều trị phù hợp dựa trên dữ liệu lớn từ hàng triệu ca bệnh tương tự. Robot phẫu thuật được điều khiển bởi AI đang giúp các bác sĩ thực hiện các ca phẫu thuật phức tạp với độ chính xác cao hơn và ít xâm lấn hơn.

Ngoài ra, AI còn được ứng dụng trong phát triển thuốc mới, giúp rút ngắn thời gian nghiên cứu từ nhiều năm xuống còn vài tháng. Điều này mở ra hy vọng mới cho việc điều trị các bệnh hiểm nghèo.', 
1, 1, 1, 185, 8),

(2, 'AI trong Giáo dục', 
'Giáo dục thông minh với sự hỗ trợ của AI đang thay đổi căn bản cách chúng ta học tập và giảng dạy. Hệ thống học tập thích ứng có thể cá nhân hóa nội dung theo khả năng và tốc độ học của từng học sinh. Điều này giúp tối ưu hóa quá trình học tập và đảm bảo không có học sinh nào bị bỏ lại phía sau.

Chatbot giáo dục cung cấp hỗ trợ 24/7 cho học sinh, trả lời các câu hỏi cơ bản và hướng dẫn bài tập. Phân tích dữ liệu học tập giúp giáo viên hiểu rõ hơn về tiến độ và khó khăn của học sinh, từ đó điều chỉnh phương pháp giảng dạy phù hợp.

Công nghệ AI cũng giúp tự động chấm điểm và đánh giá bài tập, giải phóng thời gian cho giáo viên tập trung vào việc phát triển kỹ năng tư duy phản biện và sáng tạo cho học sinh. Thực tế ảo và thực tế tăng cường được tích hợp AI tạo ra những trải nghiệm học tập sống động và thú vị.',
1, 2, 1, 201, 9),

(3, 'AI trong Kinh doanh', 
'Doanh nghiệp hiện đại đang ứng dụng AI một cách rộng rãi để tối ưu hóa hoạt động kinh doanh và nâng cao năng suất. Hệ thống CRM thông minh giúp phân tích hành vi khách hàng và dự đoán xu hướng mua sắm với độ chính xác cao. Điều này cho phép doanh nghiệp đưa ra các chiến lược marketing hiệu quả và cá nhân hóa trải nghiệm khách hàng.

Chatbot chăm sóc khách hàng tự động xử lý các truy vấn cơ bản, giảm tải cho nhân viên và cải thiện thời gian phản hồi. Phân tích dữ liệu lớn với AI giúp doanh nghiệp đưa ra quyết định chiến lược chính xác dựa trên insights từ dữ liệu thị trường và hành vi người tiêu dùng.

Tự động hóa quy trình với RPA (Robotic Process Automation) và AI giúp tiết kiệm chi phí đáng kể và nâng cao hiệu quả trong các tác vụ lặp đi lặp lại. Điều này cho phép nhân viên tập trung vào các công việc có giá trị gia tăng cao hơn.',
1, 3, 1, 178, 8),

(4, 'Chuyển đổi số trong Giáo dục', 
'Giáo dục số đang trở thành xu hướng không thể đảo ngược trong thời đại công nghệ 4.0. Lớp học trực tuyến cho phép học sinh truy cập kiến thức mọi lúc, mọi nơi, phá vỡ rào cản về thời gian và không gian. Điều này đặc biệt có ý nghĩa trong bối cảnh đại dịch COVID-19 khi việc học tập từ xa trở thành cần thiết.

Thực tế ảo (VR) và thực tế tăng cường (AR) mang lại trải nghiệm học tập sống động và chân thực. Học sinh có thể "du hành" trong lịch sử, khám phá bên trong tế bào sống, hay thực hành các thí nghiệm khoa học nguy hiểm một cách an toàn.

Nền tảng học tập điện tử cung cấp khóa học đa dạng và linh hoạt, cho phép người học tự định hướng con đường học tập của mình. Công nghệ blockchain đảm bảo tính xác thực của bằng cấp số, chống gian lận và tạo niềm tin trong hệ thống giáo dục trực tuyến.',
2, 1, 1, 156, 7),

(5, 'Bảo vệ Rừng nhiệt đới', 
'Rừng nhiệt đới được mệnh danh là "lá phổi của Trái Đất" và đóng vai trò quan trọng trong việc điều hòa khí hậu toàn cầu. Chúng hấp thụ một lượng lớn CO2 và sản xuất oxy, giúp duy trì cân bằng khí quyển. Tuy nhiên, tỷ lệ phá rừng đang gia tăng đáng báo động do nhu cầu mở rộng đất nông nghiệp và khai thác gỗ.

Việc mất rừng không chỉ góp phần vào biến đổi khí hậu mà còn đe dọa sự tồn tại của hàng triệu loài động thực vật. Nhiều loài quý hiếm đang đứng trước nguy cơ tuyệt chủng vì mất môi trường sống tự nhiên.

Cần có những chính sách bảo vệ rừng hiệu quả và sự hợp tác quốc tế để ngăn chặn nạn phá rừng. Phát triển du lịch sinh thái có thể mang lại nguồn thu nhập bền vững cho cộng đồng địa phương, từ đó tạo động lực bảo vệ rừng.',
3, 1, 1, 167, 8),

(6, 'Năng lượng Tái tạo', 
'Năng lượng tái tạo đang trở thành giải pháp then chốt cho cuộc khủng hoảng năng lượng và biến đổi khí hậu toàn cầu. Năng lượng mặt trời, gió, và thủy điện không chỉ sạch mà còn có tiềm năng phát triển không giới hạn. Chi phí sản xuất năng lượng tái tạo đã giảm đáng kể trong những năm gần đây, khiến chúng trở nên cạnh tranh với nhiên liệu hóa thạch.

Công nghệ lưu trữ năng lượng như pin lithium-ion đang được cải tiến liên tục, giải quyết vấn đề gián đoạn của năng lượng tái tạo. Mạng lưới điện thông minh cho phép tích hợp hiệu quả các nguồn năng lượng tái tạo vào hệ thống điện quốc gia.

Nhiều quốc gia đã đặt mục tiêu đạt carbon trung hòa vào năm 2050, và năng lượng tái tạo sẽ đóng vai trò chủ đạo trong việc đạt được mục tiêu này. Đầu tư vào năng lượng sạch không chỉ bảo vệ môi trường mà còn tạo ra hàng triệu việc làm mới.',
3, 2, 1, 189, 9);

-- --------------------------------------------------------

-- Insert sample labeling data
INSERT INTO `labelings` (`id`, `user_id`, `document_id`, `group_id`, `selected_sentences`, `text_style`, `edited_summary`, `labeling_type`, `is_completed`) VALUES
(1, 2, NULL, 1, 
'["1_0", "1_2", "2_1", "3_0"]', 
'formal', 
'Trí tuệ nhân tạo đang cách mạng hóa nhiều lĩnh vực quan trọng của đời sống. Trong y tế, AI hỗ trợ chẩn đoán chính xác và điều trị hiệu quả. Giáo dục thông minh với AI giúp cá nhân hóa việc học. Doanh nghiệp ứng dụng AI để tối ưu hóa hoạt động và ra quyết định chiến lược.',
'multi', 1),

(2, 2, 4, 2, 
'["4_0", "4_2", "4_4"]',
'academic',
'Chuyển đổi số trong giáo dục mang lại những thay đổi căn bản trong cách tiếp cận tri thức. Công nghệ VR/AR tạo ra trải nghiệm học tập sống động và thực tế. Nền tảng e-learning và blockchain đảm bảo tính linh hoạt và xác thực trong hệ thống giáo dục hiện đại.',
'single', 1);

-- --------------------------------------------------------

-- Insert sample reviews
INSERT INTO `reviews` (`id`, `labeling_id`, `group_id`, `reviewer_id`, `review_status`, `quality_rating`, `accuracy_rating`, `completeness_rating`, `review_comments`, `reviewed_at`) VALUES
(1, 1, 1, 3, 'approved', 4, 5, 4, 
'Bản tóm tắt chính xác và bao quát các điểm chính. Việc chọn câu phù hợp và phong cách trang trọng phù hợp với nội dung học thuật.',
'2024-01-15 10:30:00'),

(2, 2, 2, 3, 'needs_revision', 3, 4, 3,
'Nội dung tóm tắt tốt nhưng cần bổ sung thêm về tác động của blockchain trong giáo dục. Một số câu được chọn chưa thể hiện rõ tầm quan trọng của chủ đề.',
'2024-01-15 14:45:00');

-- --------------------------------------------------------

-- Insert sample activity logs
INSERT INTO `activity_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`) VALUES
(1, 'login', 'user', 1, 'Admin user logged in', '127.0.0.1'),
(1, 'upload_document_group', 'document_group', 1, 'Uploaded multi-document group: AI Technology', '127.0.0.1'),
(2, 'login', 'user', 2, 'Labeler user logged in', '127.0.0.1'),
(2, 'complete_labeling', 'labeling', 1, 'Completed labeling for group: AI Technology', '127.0.0.1'),
(3, 'login', 'user', 3, 'Reviewer user logged in', '127.0.0.1'),
(3, 'submit_review', 'review', 1, 'Reviewed and approved labeling', '127.0.0.1');

-- --------------------------------------------------------

-- Create useful views

-- View for labeling statistics
CREATE VIEW `labeling_stats` AS
SELECT 
    u.username,
    u.full_name,
    COUNT(l.id) as total_labelings,
    SUM(CASE WHEN l.is_completed = 1 THEN 1 ELSE 0 END) as completed_labelings,
    AVG(l.quality_score) as avg_quality,
    AVG(l.completion_time) as avg_completion_time
FROM users u
LEFT JOIN labelings l ON u.id = l.user_id
WHERE u.role = 'labeler'
GROUP BY u.id;

-- View for review statistics  
CREATE VIEW `review_stats` AS
SELECT 
    u.username,
    u.full_name,
    COUNT(r.id) as total_reviews,
    SUM(CASE WHEN r.review_status = 'approved' THEN 1 ELSE 0 END) as approved_reviews,
    SUM(CASE WHEN r.review_status = 'rejected' THEN 1 ELSE 0 END) as rejected_reviews,
    SUM(CASE WHEN r.review_status = 'needs_revision' THEN 1 ELSE 0 END) as revision_reviews,
    AVG(r.quality_rating) as avg_quality_rating,
    AVG(r.review_time) as avg_review_time
FROM users u
LEFT JOIN reviews r ON u.id = r.reviewer_id
WHERE u.role = 'reviewer'
GROUP BY u.id;

-- View for document group details
CREATE VIEW `group_details` AS
SELECT 
    dg.id,
    dg.title,
    dg.description,
    dg.group_type,
    dg.status,
    dg.priority,
    dg.created_at,
    dg.updated_at,
    u1.username as uploaded_by_user,
    u1.full_name as uploaded_by_name,
    u2.username as assigned_labeler_user,
    u2.full_name as assigned_labeler_name,
    u3.username as assigned_reviewer_user,
    u3.full_name as assigned_reviewer_name,
    COUNT(d.id) as document_count,
    SUM(d.word_count) as total_words,
    SUM(d.sentence_count) as total_sentences,
    l.is_completed as labeling_completed,
    l.updated_at as labeling_updated_at,
    r.review_status,
    r.reviewed_at
FROM document_groups dg
LEFT JOIN users u1 ON dg.uploaded_by = u1.id
LEFT JOIN users u2 ON dg.assigned_labeler = u2.id  
LEFT JOIN users u3 ON dg.assigned_reviewer = u3.id
LEFT JOIN documents d ON dg.id = d.group_id
LEFT JOIN labelings l ON dg.id = l.group_id
LEFT JOIN reviews r ON l.id = r.labeling_id
GROUP BY dg.id;

-- --------------------------------------------------------

-- Create stored procedures for common operations

DELIMITER //

-- Procedure to assign labeler to document group
CREATE PROCEDURE `AssignLabeler`(
    IN group_id INT,
    IN labeler_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    UPDATE document_groups 
    SET assigned_labeler = labeler_id, 
        status = 'in_progress',
        updated_at = CURRENT_TIMESTAMP
    WHERE id = group_id;
    
    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
    VALUES (labeler_id, 'assigned_labeling', 'document_group', group_id, 
            CONCAT('Assigned to labeling task: group ID ', group_id));
    
    COMMIT;
END //

-- Procedure to assign reviewer to document group
CREATE PROCEDURE `AssignReviewer`(
    IN group_id INT,
    IN reviewer_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    UPDATE document_groups 
    SET assigned_reviewer = reviewer_id,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = group_id;
    
    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
    VALUES (reviewer_id, 'assigned_review', 'document_group', group_id,
            CONCAT('Assigned to review task: group ID ', group_id));
    
    COMMIT;
END //

-- Procedure to complete labeling
CREATE PROCEDURE `CompleteLabelingTask`(
    IN labeling_id INT,
    IN completion_time_seconds INT
)
BEGIN
    DECLARE group_id_var INT;
    DECLARE user_id_var INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Get group_id and user_id from labeling
    SELECT group_id, user_id INTO group_id_var, user_id_var
    FROM labelings WHERE id = labeling_id;
    
    -- Update labeling as completed
    UPDATE labelings 
    SET is_completed = 1,
        completion_time = completion_time_seconds,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = labeling_id;
    
    -- Update document group status
    UPDATE document_groups 
    SET status = 'completed',
        updated_at = CURRENT_TIMESTAMP
    WHERE id = group_id_var;
    
    -- Log activity
    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
    VALUES (user_id_var, 'complete_labeling', 'labeling', labeling_id,
            CONCAT('Completed labeling task: ID ', labeling_id));
    
    COMMIT;
END //

-- Procedure to submit review
CREATE PROCEDURE `SubmitReview`(
    IN review_id INT,
    IN status VARCHAR(20),
    IN quality_rating INT,
    IN accuracy_rating INT,
    IN completeness_rating INT,
    IN comments TEXT,
    IN review_time_seconds INT
)
BEGIN
    DECLARE labeling_id_var INT;
    DECLARE group_id_var INT;
    DECLARE reviewer_id_var INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Get related IDs
    SELECT labeling_id, group_id, reviewer_id 
    INTO labeling_id_var, group_id_var, reviewer_id_var
    FROM reviews WHERE id = review_id;
    
    -- Update review
    UPDATE reviews 
    SET review_status = status,
        quality_rating = quality_rating,
        accuracy_rating = accuracy_rating,
        completeness_rating = completeness_rating,
        review_comments = comments,
        review_time = review_time_seconds,
        reviewed_at = CURRENT_TIMESTAMP,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = review_id;
    
    -- Update document group status
    UPDATE document_groups 
    SET status = 'reviewed',
        updated_at = CURRENT_TIMESTAMP
    WHERE id = group_id_var;
    
    -- Log activity
    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
    VALUES (reviewer_id_var, 'submit_review', 'review', review_id,
            CONCAT('Submitted review with status: ', status));
    
    COMMIT;
END //

DELIMITER ;

-- --------------------------------------------------------

-- Create triggers for automatic logging

DELIMITER //

-- Trigger for user login logging
CREATE TRIGGER `log_user_creation` 
AFTER INSERT ON `users`
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
    VALUES (NEW.id, 'user_created', 'user', NEW.id, 
            CONCAT('New user created: ', NEW.username, ' (', NEW.role, ')'));
END //

-- Trigger for document group creation
CREATE TRIGGER `log_group_creation`
AFTER INSERT ON `document_groups`
FOR EACH ROW  
BEGIN
    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
    VALUES (NEW.uploaded_by, 'create_document_group', 'document_group', NEW.id,
            CONCAT('Created document group: ', NEW.title));
END //

-- Trigger for document creation
CREATE TRIGGER `log_document_creation`
AFTER INSERT ON `documents`
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
    VALUES (NEW.uploaded_by, 'upload_document', 'document', NEW.id,
            CONCAT('Uploaded document: ', NEW.title));
END //

-- Trigger to update word/sentence count
CREATE TRIGGER `update_document_stats`
BEFORE INSERT ON `documents`
FOR EACH ROW
BEGIN
    SET NEW.word_count = (
        SELECT CHAR_LENGTH(NEW.content) - CHAR_LENGTH(REPLACE(NEW.content, ' ', '')) + 1
    );
    SET NEW.sentence_count = (
        SELECT CHAR_LENGTH(NEW.content) - CHAR_LENGTH(REPLACE(REPLACE(REPLACE(NEW.content, '.', ''), '!', ''), '?', '')) 
    );
END //

DELIMITER ;

-- --------------------------------------------------------

-- Create indexes for better performance
CREATE INDEX idx_users_role_active ON users(role, is_active);
CREATE INDEX idx_groups_status_priority ON document_groups(status, priority);
CREATE INDEX idx_groups_assigned_users ON document_groups(assigned_labeler, assigned_reviewer);
CREATE INDEX idx_documents_group_order ON documents(group_id, document_order);
CREATE INDEX idx_labelings_user_completed ON labelings(user_id, is_completed);
CREATE INDEX idx_labelings_group_type ON labelings(group_id, labeling_type);
CREATE INDEX idx_reviews_status_reviewer ON reviews(review_status, reviewer_id);
CREATE INDEX idx_logs_user_action_date ON activity_logs(user_id, action, created_at);

-- --------------------------------------------------------

-- Insert additional sample data for testing

-- More test users
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`) VALUES
('labeler2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Labeler User 2', 'labeler2@textlabeling.com', 'labeler'),
('labeler3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Labeler User 3', 'labeler3@textlabeling.com', 'labeler'),
('reviewer2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reviewer User 2', 'reviewer2@textlabeling.com', 'reviewer');

-- Additional activity logs
INSERT INTO `activity_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`) VALUES
(2, 'start_labeling', 'labeling', 1, 'Started working on labeling task', '127.0.0.1'),
(2, 'save_draft', 'labeling', 1, 'Saved draft for labeling task', '127.0.0.1'),
(3, 'start_review', 'review', 1, 'Started reviewing completed labeling', '127.0.0.1');

-- --------------------------------------------------------

-- Final setup and verification queries

-- Show all tables
SHOW TABLES;

-- Show table sizes
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'text_labeling_system'
ORDER BY TABLE_NAME;

-- Show foreign key relationships
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'text_labeling_system'
    AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Verify sample data
SELECT 'Users' as entity, COUNT(*) as count FROM users
UNION ALL
SELECT 'Document Groups', COUNT(*) FROM document_groups  
UNION ALL
SELECT 'Documents', COUNT(*) FROM documents
UNION ALL  
SELECT 'Labelings', COUNT(*) FROM labelings
UNION ALL
SELECT 'Reviews', COUNT(*) FROM reviews
UNION ALL
SELECT 'Activity Logs', COUNT(*) FROM activity_logs;

-- --------------------------------------------------------

COMMIT;

-- =====================================================
-- DATABASE SETUP COMPLETE!
-- =====================================================
-- 
-- Default Login Credentials:
-- Admin:    username: admin    password: password
-- Labeler:  username: labeler1 password: password  
-- Reviewer: username: reviewer1 password: password
--
-- Features included:
-- ✅ Complete user management (admin, labeler, reviewer)
-- ✅ Single and multi-document labeling support
-- ✅ Document groups with AI summaries
-- ✅ Three-step labeling process (select sentences, style, edit summary)
-- ✅ Review system with ratings and comments
-- ✅ Activity logging and audit trail
-- ✅ System settings and configuration
-- ✅ Performance optimized with indexes
-- ✅ Stored procedures for common operations
-- ✅ Triggers for automatic logging
-- ✅ Views for statistics and reporting
-- ✅ Sample data for testing
--
-- Total Tables: 7
-- Total Views: 3  
-- Total Procedures: 4
-- Total Triggers: 4
-- =====================================================
    