<?php
require_once 'config/db.php';
require_once 'config/auth_middleware.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $hourlyRate = floatval($_POST['hourly_rate'] ?? 0);
    $workplaceDefault = sanitizeInput($_POST['workplace_default'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Token bảo mật không hợp lệ. Vui lòng thử lại.';
    } elseif (empty($name) || empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin bắt buộc.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif ($hourlyRate < 0) {
        $error = 'Lương theo giờ không thể âm.';
    } else {
        // Register user
        $result = registerUser([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'hourly_rate' => $hourlyRate,
            'workplace_default' => $workplaceDefault
        ]);
        
        if ($result['success']) {
            $success = 'Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.';
            // Clear form data
            $name = $email = $workplaceDefault = '';
            $hourlyRate = 0;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - <?= APP_NAME ?></title>
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="main-content">
            <div class="flex justify-center items-center" style="min-height: 80vh;">
                <div class="card" style="width: 100%; max-width: 500px;">
                    <div class="card-header text-center">
                        <h1 class="card-title">Đăng ký tài khoản</h1>
                        <p class="card-subtitle">Tạo tài khoản mới để quản lý lịch làm việc</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" data-validate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="form-group">
                            <label for="name" class="form-label">Họ và tên *</label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                class="form-input" 
                                value="<?= htmlspecialchars($name ?? '') ?>"
                                required
                                placeholder="Nhập họ và tên"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email *</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-input" 
                                value="<?= htmlspecialchars($email ?? '') ?>"
                                required
                                placeholder="Nhập email"
                            >
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="password" class="form-label">Mật khẩu *</label>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="form-input" 
                                    required
                                    placeholder="Ít nhất 6 ký tự"
                                    minlength="6"
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Xác nhận mật khẩu *</label>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="form-input" 
                                    required
                                    placeholder="Nhập lại mật khẩu"
                                >
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="workplace_default" class="form-label">Nơi làm việc mặc định</label>
                            <input 
                                type="text" 
                                id="workplace_default" 
                                name="workplace_default" 
                                class="form-input" 
                                value="<?= htmlspecialchars($workplaceDefault ?? '') ?>"
                                placeholder="Ví dụ: Văn phòng chính, Chi nhánh 1..."
                            >
                            <div class="form-help">Bạn có thể thay đổi sau khi đăng ký</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="hourly_rate" class="form-label">Lương theo giờ (VNĐ)</label>
                            <input 
                                type="number" 
                                id="hourly_rate" 
                                name="hourly_rate" 
                                class="form-input" 
                                value="<?= $hourlyRate ?? 0 ?>"
                                min="0"
                                step="1000"
                                placeholder="Ví dụ: 50000"
                            >
                            <div class="form-help">Lương tính theo giờ làm việc</div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary w-full">
                                Đăng ký
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center">
                        <p class="text-secondary">
                            Đã có tài khoản? 
                            <a href="login.php" class="text-primary">Đăng nhập ngay</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Password confirmation validation
        document.addEventListener('input', function(e) {
            if (e.target.name === 'confirm_password' || e.target.name === 'password') {
                const password = document.querySelector('input[name="password"]');
                const confirmPassword = document.querySelector('input[name="confirm_password"]');
                
                if (password.value && confirmPassword.value) {
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Mật khẩu xác nhận không khớp');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
            }
        });
    </script>
</body>
</html>
