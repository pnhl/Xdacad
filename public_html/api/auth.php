<?php
require_once '../config/db.php';
require_once '../config/auth_middleware.php';

header('Content-Type: application/json');

// Handle logout GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'logout') {
    destroyUserSession();
    header('Location: ../login.php?message=logged_out');
    exit;
}

// Only handle POST requests for other actions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// CSRF token validation for sensitive actions
if (in_array($action, ['login', 'register', 'change_password', 'reset_password'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? null;
    if (!$csrfToken || !verifyCSRFToken($csrfToken)) {
        jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
    }
}

switch ($action) {
    case 'login':
        handleLogin($input);
        break;
        
    case 'register':
        handleRegister($input);
        break;
        
    case 'logout':
        handleLogout();
        break;
        
    case 'forgot_password':
        handleForgotPassword($input);
        break;
        
    case 'reset_password':
        handleResetPassword($input);
        break;
        
    case 'change_password':
        handleChangePassword($input);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleLogin($input) {
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Email và mật khẩu là bắt buộc']);
    }
    
    $user = validateCredentials($email, $password);
    if ($user) {
        initUserSession($user['id'], $user);
        jsonResponse([
            'success' => true, 
            'message' => 'Đăng nhập thành công',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);
    } else {
        logAudit('failed_login', ['email' => $email]);
        jsonResponse(['success' => false, 'message' => 'Email hoặc mật khẩu không đúng']);
    }
}

function handleRegister($input) {
    $name = sanitizeInput($input['name'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $hourlyRate = floatval($input['hourly_rate'] ?? 0);
    $workplaceDefault = sanitizeInput($input['workplace_default'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin bắt buộc']);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Email không hợp lệ']);
    }
    
    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự']);
    }
    
    if ($hourlyRate < 0) {
        jsonResponse(['success' => false, 'message' => 'Lương theo giờ không thể âm']);
    }
    
    $result = registerUser([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'hourly_rate' => $hourlyRate,
        'workplace_default' => $workplaceDefault
    ]);
    
    jsonResponse($result);
}

function handleLogout() {
    destroyUserSession();
    jsonResponse(['success' => true, 'message' => 'Đăng xuất thành công']);
}

function handleForgotPassword($input) {
    $email = sanitizeInput($input['email'] ?? '');
    
    if (empty($email)) {
        jsonResponse(['success' => false, 'message' => 'Email là bắt buộc']);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Email không hợp lệ']);
    }
    
    $result = generatePasswordResetToken($email);
    jsonResponse($result);
}

function handleResetPassword($input) {
    $token = sanitizeInput($input['token'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($token) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Token và mật khẩu mới là bắt buộc']);
    }
    
    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự']);
    }
    
    $result = resetPassword($token, $password);
    jsonResponse($result);
}

function handleChangePassword($input) {
    requireAuth();
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin']);
    }
    
    if (strlen($newPassword) < 6) {
        jsonResponse(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự']);
    }
    
    if ($newPassword !== $confirmPassword) {
        jsonResponse(['success' => false, 'message' => 'Mật khẩu xác nhận không khớp']);
    }
    
    try {
        $pdo = getDB();
        $userId = getCurrentUserId();
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            jsonResponse(['success' => false, 'message' => 'Mật khẩu hiện tại không đúng']);
        }
        
        // Update password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newPasswordHash, $userId]);
        
        logAudit('password_changed', ['user_id' => $userId]);
        
        jsonResponse(['success' => true, 'message' => 'Đã thay đổi mật khẩu thành công']);
        
    } catch (Exception $e) {
        error_log("Change password error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.']);
    }
}
?>
