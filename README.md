<div align="center">

# 🕐 Work Schedule & Payroll Management System

<img src="https://img.shields.io/badge/PHP-8%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
<img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
<img src="https://img.shields.io/badge/JavaScript-ES6%2B-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript">
<img src="https://img.shields.io/badge/CSS3-Grid%20%26%20Flexbox-1572B6?style=for-the-badge&logo=css3&logoColor=white" alt="CSS3">

<img src="https://img.shields.io/badge/Status-Production%20Ready-00C851?style=for-the-badge" alt="Status">
<img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="License">
<img src="https://img.shields.io/badge/Hosting-InfinityFree-orange?style=for-the-badge" alt="Hosting">

### 🚀 Hệ thống quản lý lịch làm việc và tính lương theo giờ chuyên nghiệp
#### Thiết kế tối ưu cho InfinityFree hosting với PHP 8+ và MySQL

[🌟 Demo Live](#-demo-live) • [📖 Tài liệu](#-tài-liệu) • [🚀 Cài đặt](#-cài-đặt--triển-khai) • [💡 Tính năng](#-tính-năng-chính)

---

</div>

## 🚀 Tính năng chính

### 📅 Quản lý lịch làm việc
- Tạo, sửa, xóa ca làm việc
- Xem lịch theo tuần/tháng
- Chống trùng ca cùng thời gian
- Ghi chú cho từng ca làm việc

### ⏰ Chấm công thời gian thực
- Bắt đầu/kết thúc ca làm việc
- Tạm dừng và tiếp tục làm việc
- Theo dõi thời gian chính xác đến phút
- Nhiều phiên làm việc trong một ca

### 💰 Tính lương tự động
- Lương theo giờ với lịch sử thay đổi
- Tính toán tăng ca tự động
- Áp dụng lương hiện tại cho cả tháng
- Báo cáo thu nhập chi tiết

### 📊 Báo cáo & Xuất dữ liệu
- Bảng công theo tháng
- Xuất CSV bảng công
- In bảng công (CSS print-friendly)
- Thống kê theo tuần/tháng/năm

### 🔐 Bảo mật
- Hash mật khẩu với bcrypt
- CSRF protection
- Session-based authentication
- Audit logs cho tất cả hoạt động
- Password reset token

### 🎨 Giao diện
- Responsive design (mobile-friendly)
- Dark/Light mode
- Toast notifications
- Modal dialogs
- Loading states

## 🛠 Công nghệ sử dụng

### Frontend
- **HTML5** - Cấu trúc semantic
- **CSS3** - Custom variables, Flexbox, Grid
- **JavaScript** - ES6+, Fetch API, DOM manipulation

### Backend
- **PHP 8+** - OOP, PDO, password hashing
- **MySQL** - Relational database với foreign keys
- **Session** - Authentication & user management

### Hosting
- **InfinityFree** - Free PHP hosting
- **phpMyAdmin** - Database management
- **File Manager** - Code deployment

## 📋 Cài đặt & Triển khai

### 1. Tạo tài khoản InfinityFree
1. Đăng ký tại [InfinityFree.com](https://app.infinityfree.com/)
2. Tạo website mới
3. Ghi nhớ thông tin database từ Control Panel

### 2. Upload source code
```bash
# Nén toàn bộ thư mục public_html
zip -r work-schedule.zip public_html/

# Upload qua File Manager hoặc FTP
# Giải nén vào thư mục htdocs/
```

### 3. Cấu hình database
1. Truy cập phpMyAdmin từ Control Panel
2. Tạo database mới
3. Import file `database.sql`
4. Cập nhật thông tin DB trong `config/db.php`:

```php
define('DB_HOST', 'sql300.infinityfree.com');
define('DB_NAME', 'if0_XXXXXXX_work_schedule');
define('DB_USER', 'if0_XXXXXXX');
define('DB_PASS', 'your_database_password');
define('SITE_URL', 'https://your-domain.infinityfreeapp.com');
```

### 4. Cấu hình permissions
```bash
# Đảm bảo thư mục upload có quyền ghi
chmod 755 assets/img/avatars/
```

### 5. Test hệ thống
- Truy cập domain của bạn
- Đăng nhập với tài khoản demo:
  - **Admin**: admin@example.com / password
  - **User**: user1@example.com / password

## 📊 Cấu trúc Database

### Bảng `users`
```sql
- id: Primary key
- name: Họ tên
- email: Email (unique)
- password_hash: Mật khẩu đã hash
- hourly_rate: Lương theo giờ hiện tại
- workplace_default: Nơi làm việc mặc định
- theme: light/dark
- locale: vi-VN/en-US
- role: user/admin
- avatar: Đường dẫn ảnh đại diện
- created_at, updated_at: Timestamps
```

### Bảng `shifts`
```sql
- id: Primary key
- user_id: Foreign key users.id
- date: Ngày làm việc
- planned_start, planned_end: Thời gian kế hoạch
- workplace: Nơi làm việc
- notes: Ghi chú
- status: planned/in_progress/done/canceled
- created_at, updated_at: Timestamps
```

### Bảng `sessions`
```sql
- id: Primary key
- shift_id: Foreign key shifts.id
- start_time: Thời gian bắt đầu
- end_time: Thời gian kết thúc (NULL nếu đang active)
- created_at, updated_at: Timestamps
```

### Bảng `hourly_rate_history`
```sql
- id: Primary key
- user_id: Foreign key users.id
- rate: Mức lương
- effective_from: Thời điểm có hiệu lực
- created_at: Timestamp
```

### Bảng `audit_logs`
```sql
- id: Primary key
- user_id: Foreign key users.id
- action: Hành động thực hiện
- meta: JSON metadata
- ip_address: Địa chỉ IP
- user_agent: Browser info
- created_at: Timestamp
```

### Bảng `password_resets`
```sql
- id: Primary key
- user_id: Foreign key users.id
- token: Reset token
- expires_at: Thời gian hết hạn
- used: Boolean đã sử dụng
- created_at: Timestamp
```

## 🔧 Cấu trúc thư mục

```
public_html/
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── app.js
│   └── img/
│       └── avatars/
├── api/
│   ├── auth.php
│   ├── shifts.php
│   └── reports.php
├── config/
│   ├── db.php
│   └── auth_middleware.php
├── index.php
├── login.php
├── register.php
├── dashboard.php
├── schedule.php
├── timesheet.php
└── profile.php
database.sql
README.md
```

## 📱 API Endpoints

### Authentication (`api/auth.php`)
- `POST /api/auth.php` - Login, register, logout
- `GET /api/auth.php?action=logout` - Logout redirect

### Shifts Management (`api/shifts.php`)
- `POST /api/shifts.php` với action:
  - `create_shift` - Tạo ca mới
  - `update_shift` - Cập nhật ca
  - `delete_shift` - Xóa ca
  - `start_session` - Bắt đầu làm việc
  - `end_session` - Kết thúc làm việc
  - `get_shifts` - Lấy danh sách ca
  - `get_shift_details` - Chi tiết ca làm việc

### Reports (`api/reports.php`)
- `POST /api/reports.php` với action:
  - `get_timesheet` - Bảng công theo tháng
  - `get_monthly_summary` - Tổng kết tháng
  - `export_csv` - Xuất CSV
  - `calculate_overtime` - Tính tăng ca

## 🎯 Acceptance Criteria

### ✅ Authentication & Authorization
- [x] Đăng ký/đăng nhập/đăng xuất
- [x] Session management
- [x] Password hashing & validation
- [x] CSRF protection

### ✅ Shift Management
- [x] Tạo/sửa/xóa ca làm việc
- [x] Kiểm tra trùng ca
- [x] Bắt đầu/kết thúc ca
- [x] Nhiều session trong một ca

### ✅ Timesheet & Payroll
- [x] Tính lương theo giờ chính xác
- [x] Lịch sử thay đổi lương
- [x] Tổng kết theo ngày/tháng
- [x] Tính toán tăng ca

### ✅ Reports & Export
- [x] Bảng công chi tiết
- [x] Xuất CSV
- [x] In ấn (CSS print-friendly)
- [x] Thống kê tổng quan

### ✅ UI/UX
- [x] Responsive design
- [x] Dark/Light mode
- [x] Toast notifications
- [x] Form validation
- [x] Loading states

### ✅ Security
- [x] SQL injection prevention (PDO)
- [x] XSS protection (htmlspecialchars)
- [x] CSRF tokens
- [x] Audit logging
- [x] Input validation

## 🐛 Troubleshooting

### Database Connection Issues
```php
// Kiểm tra thông tin kết nối trong config/db.php
// Đảm bảo database đã được tạo và import đúng
```

### File Upload Errors
```php
// Kiểm tra permissions thư mục assets/img/avatars/
// Đảm bảo PHP có quyền ghi file
```

### Session Issues
```php
// Kiểm tra session.save_path trong PHP
// Đảm bảo cookie domain được cấu hình đúng
```

## 📞 Hỗ trợ

### Demo Accounts
- **Admin**: admin@example.com / password
- **User 1**: user1@example.com / password  
- **User 2**: user2@example.com / password

### Liên hệ
- **Email**: support@example.com
- **GitHub**: https://github.com/your-repo
- **Documentation**: README.md

## 📝 License

Dự án này được phát triển cho mục đích học tập và demo. Sử dụng miễn phí cho các dự án cá nhân và thương mại.

---

**Phiên bản**: 1.0.0  
**Cập nhật**: 2024  
**Tác giả**: AI Assistant