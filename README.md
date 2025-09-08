# Text Summarization Labeling System

Hệ thống gán nhãn dữ liệu tóm tắt văn bản được xây dựng bằng PHP và MySQL.

## Tính năng chính

- **Quản lý người dùng**: Admin có thể tạo và quản lý các tài khoản với 3 vai trò khác nhau
- **Upload dữ liệu**: Admin có thể upload văn bản và bản tóm tắt AI
- **Gán nhãn 3 bước**: 
  1. Chọn câu quan trọng
  2. Xác định phong cách văn bản
  3. Chỉnh sửa bản tóm tắt
- **Review**: Reviewer có thể kiểm tra và góp ý các công việc đã hoàn thành
- **Giao diện thân thiện**: Responsive design với Bootstrap 5

## Yêu cầu hệ thống

- PHP 7.4 hoặc cao hơn
- MySQL 5.7 hoặc cao hơn
- Web server (Apache/Nginx)

## Cài đặt

1. **Tải và giải nén project**
   ```bash
   git clone hoặc tải zip về
   cd text-labeling-system
   ```

2. **Tạo cơ sở dữ liệu**
   - Tạo database mới tên `text_labeling_system`
   - Import file `database.sql`

3. **Cấu hình database**
   - Mở `config/database.php`
   - Sửa thông tin kết nối database:
   ```php
   private $host = 'localhost';
   private $db_name = 'text_labeling_system';
   private $username = 'root';
   private $password = '';
   ```

4. **Chạy trên web server**
   - Copy project vào thư mục web root
   - Truy cập qua trình duyệt

## Tài khoản mặc định

- **Admin**: username: `admin`, password: `admin123`

## Cấu trúc thư mục

```
text-labeling-system/
├── config/          # Cấu hình database
├── includes/        # Files chung (auth, functions, header)
├── css/            # Stylesheet
├── js/             # JavaScript
├── admin/          # Trang admin
├── labeler/        # Trang gán nhãn
├── reviewer/       # Trang reviewer
├── login.php       # Trang đăng nhập
├── index.php       # Trang chủ (redirect)
├── logout.php      # Đăng xuất
└── database.sql    # Cấu trúc database
```

## Quyền người dùng

- **Admin**: Quản lý người dùng, upload dữ liệu, xem báo cáo
- **Labeler**: Thực hiện gán nhãn tài liệu
- **Reviewer**: Review và góp ý các công việc đã hoàn thành

## Hỗ trợ

Nếu có vấn đề trong quá trình cài đặt hoặc sử dụng, vui lòng tạo issue hoặc liên hệ.
```

## 21. File cấu hình Apache (.htaccess)
```apache
RewriteEngine On
RewriteBase /

# Redirect to HTTPS (optional)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Hide sensitive files
<Files "config/*">
    Order allow,deny
    Deny from all
</Files>

<Files "*.sql">
    Order allow,deny
    Deny from all
</Files>

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript application/javascript text/xml application/xml
</IfModule>

# Cache static files
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
</IfModule>
```

## Kết thúc

Đây là một hệ thống hoàn chỉnh với đầy đủ tính năng được yêu cầu. Project bao gồm:

1. **Quản lý người dùng** với 3 vai trò
2. **Upload và quản lý dữ liệu** 
3. **Giao diện gán nhãn 3 bước** đẹp và dễ sử dụng
4. **Tính năng review** cho reviewer
5. **Cơ sở dữ liệu** hoàn chỉnh với các mối quan hệ
6. **Giao diện responsive** với Bootstrap 5
7. **Tự động lưu** khi làm việc
8. **Bảo mật** với session và phân quyền

Bạn chỉ cần tải về, cấu hình database và chạy trên web server là có thể sử dụng ngay!# Text Summarization Labeling System

## Cấu trúc thư mục
```
text-labeling-system/
├── config/
│   └── database.php
├── includes/
│   ├── auth.php
│   ├── functions.php
│   └── header.php
├── css/
│   └── style.css
├── js/
│   └── script.js
├── admin/
│   ├── dashboard.php
│   ├── users.php
│   ├── upload.php
│   └── reports.php
├── labeler/
│   ├── dashboard.php
│   ├── labeling.php
│   └── my_tasks.php
├── reviewer/
│   ├── dashboard.php
│   └── review.php
├── login.php
├── logout.php
├── index.php
└── database.sql
```


