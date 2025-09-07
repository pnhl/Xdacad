<?php
require_once 'config/db.php';
require_once 'config/auth_middleware.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Quản lý lịch làm việc & tính lương</title>
    <meta name="description" content="Hệ thống quản lý lịch làm việc và tính lương theo giờ dành cho nhân viên">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: var(--spacing-12) 0;
            text-align: center;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 var(--spacing-4);
        }
        
        .hero-title {
            font-size: var(--font-size-3xl);
            font-weight: 700;
            margin-bottom: var(--spacing-4);
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: var(--font-size-lg);
            margin-bottom: var(--spacing-8);
            opacity: 0.9;
        }
        
        .hero-actions {
            display: flex;
            gap: var(--spacing-4);
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .features-section {
            padding: var(--spacing-12) 0;
            background-color: var(--surface-color);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-8);
            margin-top: var(--spacing-8);
        }
        
        .feature-card {
            background-color: var(--background-color);
            padding: var(--spacing-6);
            border-radius: var(--radius-lg);
            text-align: center;
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: var(--spacing-4);
        }
        
        .feature-title {
            font-size: var(--font-size-xl);
            font-weight: 600;
            margin-bottom: var(--spacing-3);
            color: var(--text-primary);
        }
        
        .feature-description {
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        .demo-section {
            padding: var(--spacing-12) 0;
            background-color: var(--background-color);
        }
        
        .demo-accounts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-6);
            margin-top: var(--spacing-8);
        }
        
        .demo-account {
            background-color: var(--surface-color);
            padding: var(--spacing-6);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }
        
        .demo-account h3 {
            color: var(--primary-color);
            margin-bottom: var(--spacing-3);
        }
        
        .demo-credentials {
            background-color: var(--border-color);
            padding: var(--spacing-3);
            border-radius: var(--radius);
            font-family: monospace;
            font-size: var(--font-size-sm);
            margin: var(--spacing-3) 0;
        }
        
        .footer {
            background-color: var(--surface-color);
            padding: var(--spacing-8) 0;
            border-top: 1px solid var(--border-color);
            text-align: center;
            color: var(--text-secondary);
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: var(--font-size-2xl);
            }
            
            .hero-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .hero-actions .btn {
                width: 200px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo"><?= APP_NAME ?></div>
                
                <div class="user-menu">
                    <button class="theme-toggle" title="Chuyển đổi theme">🌙</button>
                    <a href="login.php" class="btn btn-outline">Đăng nhập</a>
                    <a href="register.php" class="btn btn-primary">Đăng ký</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">
                Quản lý lịch làm việc & tính lương thông minh
            </h1>
            <p class="hero-subtitle">
                Hệ thống toàn diện giúp bạn theo dõi thời gian làm việc, quản lý ca trực 
                và tính toán lương một cách chính xác và hiệu quả.
            </p>
            <div class="hero-actions">
                <a href="register.php" class="btn btn-lg" style="background-color: white; color: var(--primary-color);">
                    Bắt đầu miễn phí
                </a>
                <a href="login.php" class="btn btn-lg btn-outline" style="border-color: white; color: white;">
                    Đăng nhập
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="text-center">
                <h2 class="text-3xl font-bold mb-4">Tính năng nổi bật</h2>
                <p class="text-lg text-secondary">
                    Mọi công cụ bạn cần để quản lý thời gian làm việc hiệu quả
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3 class="feature-title">Quản lý lịch làm việc</h3>
                    <p class="feature-description">
                        Tạo và quản lý ca làm việc dễ dàng với giao diện calendar trực quan. 
                        Xem lịch theo tuần hoặc tháng.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">⏰</div>
                    <h3 class="feature-title">Chấm công thời gian thực</h3>
                    <p class="feature-description">
                        Bắt đầu và kết thúc ca làm việc với một cú click. 
                        Theo dõi thời gian làm việc chính xác đến từng phút.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h3 class="feature-title">Tính lương tự động</h3>
                    <p class="feature-description">
                        Tự động tính toán lương theo giờ với lịch sử thay đổi mức lương. 
                        Hỗ trợ tính tăng ca và phụ cấp.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3 class="feature-title">Báo cáo chi tiết</h3>
                    <p class="feature-description">
                        Xem bảng công chi tiết theo tháng, xuất CSV, in ấn. 
                        Thống kê tổng quan theo tuần/tháng/năm.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🌓</div>
                    <h3 class="feature-title">Giao diện thân thiện</h3>
                    <p class="feature-description">
                        Responsive design, hỗ trợ dark/light mode. 
                        Tối ưu cho cả desktop và mobile.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3 class="feature-title">Bảo mật cao</h3>
                    <p class="feature-description">
                        Mã hóa mật khẩu, CSRF protection, audit logs. 
                        Dữ liệu cá nhân được bảo vệ tuyệt đối.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Demo Section -->
    <section class="demo-section">
        <div class="container">
            <div class="text-center">
                <h2 class="text-3xl font-bold mb-4">Dùng thử ngay</h2>
                <p class="text-lg text-secondary mb-6">
                    Sử dụng các tài khoản demo để trải nghiệm hệ thống
                </p>
            </div>
            
            <div class="demo-accounts">
                <div class="demo-account">
                    <h3>👨‍💼 Tài khoản Admin</h3>
                    <p class="text-secondary">Quyền quản trị viên, xem được tất cả dữ liệu</p>
                    <div class="demo-credentials">
                        <strong>Email:</strong> admin@example.com<br>
                        <strong>Mật khẩu:</strong> password
                    </div>
                    <a href="login.php" class="btn btn-primary w-full">Đăng nhập Admin</a>
                </div>
                
                <div class="demo-account">
                    <h3>👤 Tài khoản User 1</h3>
                    <p class="text-secondary">Nhân viên thường, quản lý lịch cá nhân</p>
                    <div class="demo-credentials">
                        <strong>Email:</strong> user1@example.com<br>
                        <strong>Mật khẩu:</strong> password
                    </div>
                    <a href="login.php" class="btn btn-secondary w-full">Đăng nhập User</a>
                </div>
                
                <div class="demo-account">
                    <h3>👤 Tài khoản User 2</h3>
                    <p class="text-secondary">Nhân viên khác với dữ liệu mẫu</p>
                    <div class="demo-credentials">
                        <strong>Email:</strong> user2@example.com<br>
                        <strong>Mật khẩu:</strong> password
                    </div>
                    <a href="login.php" class="btn btn-secondary w-full">Đăng nhập User</a>
                </div>
            </div>
            
            <div class="text-center mt-8">
                <p class="text-secondary mb-4">
                    Hoặc tạo tài khoản mới để bắt đầu
                </p>
                <a href="register.php" class="btn btn-success btn-lg">
                    Đăng ký tài khoản mới
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="grid grid-cols-3 gap-8 mb-6">
                <div>
                    <h4 class="font-semibold mb-3">Về hệ thống</h4>
                    <p class="text-sm">
                        <?= APP_NAME ?> là giải pháp quản lý thời gian làm việc 
                        và tính lương toàn diện dành cho các doanh nghiệp và cá nhân.
                    </p>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-3">Tính năng</h4>
                    <ul class="text-sm space-y-1">
                        <li>• Quản lý ca làm việc</li>
                        <li>• Chấm công thời gian thực</li>
                        <li>• Tính lương tự động</li>
                        <li>• Báo cáo chi tiết</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-3">Công nghệ</h4>
                    <ul class="text-sm space-y-1">
                        <li>• PHP 8+ & MySQL</li>
                        <li>• HTML5, CSS3, JavaScript</li>
                        <li>• Responsive Design</li>
                        <li>• InfinityFree Hosting</li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-300 pt-6">
                <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Phát triển bởi AI Assistant.</p>
                <p class="text-sm mt-2">
                    Phiên bản <?= APP_VERSION ?> - 
                    <a href="https://github.com" class="text-primary">GitHub</a> | 
                    <a href="mailto:support@example.com" class="text-primary">Hỗ trợ</a>
                </p>
            </div>
        </div>
    </footer>

    <script src="assets/js/app.js"></script>
    <script>
        // Auto-fill demo credentials when clicking demo login buttons
        document.addEventListener('click', function(e) {
            if (e.target.matches('a[href="login.php"]')) {
                const demoAccount = e.target.closest('.demo-account');
                if (demoAccount) {
                    const credentials = demoAccount.querySelector('.demo-credentials').textContent;
                    const email = credentials.match(/Email:\s*([^\s]+)/)?.[1];
                    
                    if (email) {
                        // Store email in sessionStorage to auto-fill on login page
                        sessionStorage.setItem('demo_email', email);
                    }
                }
            }
        });
    </script>
</body>
</html>
