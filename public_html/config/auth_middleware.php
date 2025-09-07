<?php
/**
 * Authentication middleware
 * Handles user session management and authentication checks
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * Initialize user session
 * @param int $userId User ID
 * @param array $userData Additional user data to store in session
 */
function initUserSession($userId, $userData = []) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $userData['name'] ?? '';
    $_SESSION['user_email'] = $userData['email'] ?? '';
    $_SESSION['user_role'] = $userData['role'] ?? 'user';
    $_SESSION['user_theme'] = $userData['theme'] ?? 'light';
    $_SESSION['user_locale'] = $userData['locale'] ?? 'vi-VN';
    $_SESSION['login_time'] = time();
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Log login
    logAudit('user_login', [
        'user_id' => $userId,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}

/**
 * Destroy user session
 */
function destroyUserSession() {
    $userId = getCurrentUserId();
    
    // Log logout
    if ($userId) {
        logAudit('user_logout', [
            'user_id' => $userId,
            'session_duration' => time() - ($_SESSION['login_time'] ?? time())
        ]);
    }
    
    // Clear session data
    session_unset();
    session_destroy();
    
    // Start new session
    session_start();
    session_regenerate_id(true);
}

/**
 * Check if current user is admin
 * @return bool True if admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Require admin privileges
 */
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        http_response_code(403);
        die('Access denied. Admin privileges required.');
    }
}

/**
 * Get current user data
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([getCurrentUserId()]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Update user session data
 * @param array $userData Updated user data
 */
function updateUserSession($userData) {
    if (isset($userData['name'])) {
        $_SESSION['user_name'] = $userData['name'];
    }
    if (isset($userData['theme'])) {
        $_SESSION['user_theme'] = $userData['theme'];
    }
    if (isset($userData['locale'])) {
        $_SESSION['user_locale'] = $userData['locale'];
    }
}

/**
 * Check session timeout
 * @return bool True if session is valid, false if expired
 */
function checkSessionTimeout() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $loginTime = $_SESSION['login_time'] ?? 0;
    $currentTime = time();
    
    if (($currentTime - $loginTime) > SESSION_LIFETIME) {
        destroyUserSession();
        return false;
    }
    
    return true;
}

/**
 * Validate user credentials
 * @param string $email User email
 * @param string $password Plain text password
 * @return array|false User data if valid, false otherwise
 */
function validateCredentials($email, $password) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error validating credentials: " . $e->getMessage());
        return false;
    }
}

/**
 * Register new user
 * @param array $userData User registration data
 * @return array Result with success/error status
 */
function registerUser($userData) {
    try {
        $pdo = getDB();
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$userData['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email đã được sử dụng'];
        }
        
        // Hash password
        $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, hourly_rate, workplace_default) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userData['name'],
            $userData['email'],
            $passwordHash,
            $userData['hourly_rate'] ?? 0,
            $userData['workplace_default'] ?? ''
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Add initial hourly rate history
        if (isset($userData['hourly_rate']) && $userData['hourly_rate'] > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO hourly_rate_history (user_id, rate, effective_from) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$userId, $userData['hourly_rate']]);
        }
        
        // Log registration
        logAudit('user_registration', [
            'user_id' => $userId,
            'email' => $userData['email']
        ], $userId);
        
        return ['success' => true, 'user_id' => $userId];
        
    } catch (Exception $e) {
        error_log("Error registering user: " . $e->getMessage());
        return ['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.'];
    }
}

/**
 * Generate password reset token
 * @param string $email User email
 * @return array Result with success/error status
 */
function generatePasswordResetToken($email) {
    try {
        $pdo = getDB();
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Email không tồn tại'];
        }
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Delete old tokens
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        
        // Insert new token
        $stmt = $pdo->prepare("
            INSERT INTO password_resets (user_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user['id'], $token, $expiresAt]);
        
        // In production, you would send email here
        // For demo, we'll return the token
        return [
            'success' => true, 
            'token' => $token,
            'message' => 'Token reset: ' . $token . ' (expires in 1 hour)'
        ];
        
    } catch (Exception $e) {
        error_log("Error generating reset token: " . $e->getMessage());
        return ['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.'];
    }
}

/**
 * Reset password using token
 * @param string $token Reset token
 * @param string $newPassword New password
 * @return array Result with success/error status
 */
function resetPassword($token, $newPassword) {
    try {
        $pdo = getDB();
        
        // Validate token
        $stmt = $pdo->prepare("
            SELECT pr.*, u.email 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = FALSE
        ");
        $stmt->execute([$token]);
        $resetRecord = $stmt->fetch();
        
        if (!$resetRecord) {
            return ['success' => false, 'message' => 'Token không hợp lệ hoặc đã hết hạn'];
        }
        
        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $resetRecord['user_id']]);
        
        // Mark token as used
        $stmt = $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE id = ?");
        $stmt->execute([$resetRecord['id']]);
        
        // Log password reset
        logAudit('password_reset', [
            'user_id' => $resetRecord['user_id'],
            'email' => $resetRecord['email']
        ], $resetRecord['user_id']);
        
        return ['success' => true, 'message' => 'Mật khẩu đã được đặt lại thành công'];
        
    } catch (Exception $e) {
        error_log("Error resetting password: " . $e->getMessage());
        return ['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.'];
    }
}
?>
