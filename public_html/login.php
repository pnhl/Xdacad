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
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Token bảo mật không hợp lệ. Vui lòng thử lại.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        // Validate credentials
        $user = validateCredentials($email, $password);
        if ($user) {
            // Initialize session
            initUserSession($user['id'], $user);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Email hoặc mật khẩu không đúng.';
            logAudit('failed_login', ['email' => $email]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - <?= APP_NAME ?></title>
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="main-content">
            <div class="flex justify-center items-center" style="min-height: 80vh;">
                <div class="card" style="width: 100%; max-width: 400px;">
                    <div class="card-header text-center">
                        <h1 class="card-title"><?= APP_NAME ?></h1>
                        <p class="card-subtitle">Đăng nhập vào tài khoản của bạn</p>
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
                            <label for="email" class="form-label">Email</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-input" 
                                value="<?= htmlspecialchars($email ?? '') ?>"
                                required
                                placeholder="Nhập email của bạn"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label">Mật khẩu</label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input" 
                                required
                                placeholder="Nhập mật khẩu"
                            >
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary w-full">
                                Đăng nhập
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center">
                        <p class="text-secondary">
                            Chưa có tài khoản? 
                            <a href="register.php" class="text-primary">Đăng ký ngay</a>
                        </p>
                        <p class="text-secondary mt-2">
                            <a href="forgot-password.php" class="text-primary">Quên mật khẩu?</a>
                        </p>
                    </div>
                    
                    <div class="mt-6 p-4" style="background-color: var(--border-color); border-radius: var(--radius);">
                        <h4 class="font-semibold mb-2">Tài khoản demo:</h4>
                        <div class="text-sm text-secondary">
                            <p><strong>Admin:</strong> admin@example.com / password</p>
                            <p><strong>User:</strong> user1@example.com / password</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
