-- Text Labeling System Database
CREATE DATABASE text_labeling_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE text_labeling_system;

-- Bảng người dùng
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'labeler', 'reviewer') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Bảng dữ liệu văn bản
CREATE TABLE documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    ai_summary TEXT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'in_progress', 'completed', 'reviewed') DEFAULT 'pending',
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Bảng phong cách văn bản
CREATE TABLE text_styles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

-- Thêm 5 phong cách văn bản mặc định
INSERT INTO text_styles (name, description) VALUES
('Tường thuật', 'Văn bản mô tả sự kiện, hiện tượng theo thời gian'),
('Nghị luận', 'Văn bản trình bày quan điểm, lập luận về một vấn đề'),
('Miêu tả', 'Văn bản tả lại hình ảnh, đặc điểm của sự vật, hiện tượng'),
('Biểu cảm', 'Văn bản thể hiện cảm xúc, tâm trạng của tác giả'),
('Thuyết minh', 'Văn bản giải thích, làm rõ về một sự vật, hiện tượng');

-- Bảng gán nhãn
CREATE TABLE labelings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    labeler_id INT NOT NULL,
    reviewer_id INT,
    important_sentences TEXT,
    text_style_id INT,
    edited_summary TEXT,
    labeling_notes TEXT,
    review_notes TEXT,
    status ENUM('pending', 'completed', 'reviewed', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id),
    FOREIGN KEY (labeler_id) REFERENCES users(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    FOREIGN KEY (text_style_id) REFERENCES text_styles(id)
);

-- Tạo tài khoản admin mặc định (password: admin123)
INSERT INTO users (username, email, password, role, full_name) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrator');

-- Tạo thêm tài khoản demo
INSERT INTO users (username, email, password, role, full_name) VALUES
('labeler1', 'labeler1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'labeler', 'Người gán nhãn 1'),
('reviewer1', 'reviewer1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'reviewer', 'Người review 1');