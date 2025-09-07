<?php
require_once 'config/db.php';
require_once 'config/auth_middleware.php';

// Require authentication
requireAuth();

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Token bảo mật không hợp lệ. Vui lòng thử lại.';
    } else {
        switch ($action) {
            case 'update_profile':
                $result = handleUpdateProfile($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                    $user = getCurrentUser(); // Refresh user data
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'update_hourly_rate':
                $result = handleUpdateHourlyRate($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                    $user = getCurrentUser(); // Refresh user data
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'change_password':
                $result = handleChangePassword($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'upload_avatar':
                $result = handleUploadAvatar($_FILES);
                if ($result['success']) {
                    $success = $result['message'];
                    $user = getCurrentUser(); // Refresh user data
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
}

function handleUpdateProfile($data) {
    $userId = getCurrentUserId();
    $name = sanitizeInput($data['name'] ?? '');
    $workplaceDefault = sanitizeInput($data['workplace_default'] ?? '');
    $theme = sanitizeInput($data['theme'] ?? 'light');
    $locale = sanitizeInput($data['locale'] ?? 'vi-VN');
    
    if (empty($name)) {
        return ['success' => false, 'message' => 'Tên không được để trống'];
    }
    
    if (!in_array($theme, ['light', 'dark'])) {
        $theme = 'light';
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, workplace_default = ?, theme = ?, locale = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$name, $workplaceDefault, $theme, $locale, $userId]);
        
        // Update session data
        updateUserSession([
            'name' => $name,
            'theme' => $theme,
            'locale' => $locale
        ]);
        
        logAudit('profile_updated', [
            'updated_fields' => ['name', 'workplace_default', 'theme', 'locale']
        ]);
        
        return ['success' => true, 'message' => 'Đã cập nhật hồ sơ thành công'];
        
    } catch (Exception $e) {
        error_log("Update profile error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.'];
    }
}

function handleUpdateHourlyRate($data) {
    $userId = getCurrentUserId();
    $newRate = floatval($data['hourly_rate'] ?? 0);
    
    if ($newRate < 0) {
        return ['success' => false, 'message' => 'Lương theo giờ không thể âm'];
    }
    
    try {
        $pdo = getDB();
        
        // Get current rate
        $currentRate = getUserHourlyRate($userId);
        
        if ($newRate == $currentRate) {
            return ['success' => false, 'message' => 'Mức lương mới giống với mức lương hiện tại'];
        }
        
        $pdo->beginTransaction();
        
        // Update user's current rate
        $stmt = $pdo->prepare("UPDATE users SET hourly_rate = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newRate, $userId]);
        
        // Add to rate history
        $stmt = $pdo->prepare("
            INSERT INTO hourly_rate_history (user_id, rate, effective_from) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userId, $newRate]);
        
        $pdo->commit();
        
        logAudit('hourly_rate_updated', [
            'old_rate' => $currentRate,
            'new_rate' => $newRate
        ]);
        
        return ['success' => true, 'message' => 'Đã cập nhật mức lương thành công'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Update hourly rate error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.'];
    }
}

function handleChangePassword($data) {
    $userId = getCurrentUserId();
    $currentPassword = $data['current_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin'];
    }
    
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự'];
    }
    
    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'Mật khẩu xác nhận không khớp'];
    }
    
    try {
        $pdo = getDB();
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Mật khẩu hiện tại không đúng'];
        }
        
        // Update password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newPasswordHash, $userId]);
        
        logAudit('password_changed');
        
        return ['success' => true, 'message' => 'Đã thay đổi mật khẩu thành công'];
        
    } catch (Exception $e) {
        error_log("Change password error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.'];
    }
}

function handleUploadAvatar($files) {
    $userId = getCurrentUserId();
    
    if (!isset($files['avatar']) || $files['avatar']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Vui lòng chọn file ảnh'];
    }
    
    $file = $files['avatar'];
    $fileSize = $file['size'];
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file size
    if ($fileSize > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'message' => 'File ảnh quá lớn (tối đa 2MB)'];
    }
    
    // Validate file type
    if (!in_array($fileType, UPLOAD_ALLOWED_TYPES)) {
        return ['success' => false, 'message' => 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF)'];
    }
    
    try {
        // Create avatar directory if not exists
        $uploadDir = AVATAR_UPLOAD_PATH;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $fileType;
        $uploadPath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return ['success' => false, 'message' => 'Không thể lưu file ảnh'];
        }
        
        // Update user avatar in database
        $pdo = getDB();
        
        // Delete old avatar file
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldAvatar = $stmt->fetchColumn();
        
        if ($oldAvatar && file_exists($oldAvatar)) {
            unlink($oldAvatar);
        }
        
        // Update avatar path
        $stmt = $pdo->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$uploadPath, $userId]);
        
        logAudit('avatar_updated', ['filename' => $filename]);
        
        return ['success' => true, 'message' => 'Đã cập nhật ảnh đại diện thành công'];
        
    } catch (Exception $e) {
        error_log("Upload avatar error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.'];
    }
}

// Get hourly rate history
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT rate, effective_from 
        FROM hourly_rate_history 
        WHERE user_id = ? 
        ORDER BY effective_from DESC 
        LIMIT 10
    ");
    $stmt->execute([getCurrentUserId()]);
    $rateHistory = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Get rate history error: " . $e->getMessage());
    $rateHistory = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ cá nhân - <?= APP_NAME ?></title>
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .profile-sections {
            display: grid;
            gap: var(--spacing-6);
        }
        
        .avatar-section {
            text-align: center;
            padding: var(--spacing-6);
        }
        
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto var(--spacing-4);
            border: 4px solid var(--border-color);
            object-fit: cover;
            background-color: var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: var(--text-secondary);
        }
        
        .rate-history {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .rate-history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-3);
            border-bottom: 1px solid var(--border-color);
        }
        
        .rate-history-item:last-child {
            border-bottom: none;
        }
        
        .current-rate {
            background-color: rgba(16, 185, 129, 0.1);
            border-left: 4px solid var(--success-color);
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: var(--spacing-6);
        }
        
        .tab {
            padding: var(--spacing-3) var(--spacing-5);
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-size: var(--font-size-base);
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body data-theme="<?= htmlspecialchars($user['theme']) ?>">
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php" class="logo"><?= APP_NAME ?></a>
                
                <button class="mobile-menu-toggle">☰</button>
                
                <nav class="nav">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="schedule.php">Lịch làm việc</a>
                    <a href="timesheet.php">Bảng công</a>
                    <a href="profile.php" class="active">Hồ sơ</a>
                </nav>
                
                <div class="user-menu">
                    <button class="theme-toggle" title="Chuyển đổi theme">🌙</button>
                    <span class="text-secondary">Xin chào, <?= htmlspecialchars($user['name']) ?></span>
                    <a href="api/auth.php?action=logout" class="btn btn-outline btn-sm">Đăng xuất</a>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <h1 class="text-3xl font-bold mb-6">Hồ sơ cá nhân</h1>
            
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
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="showTab('general')">Thông tin chung</button>
                <button class="tab" onclick="showTab('avatar')">Ảnh đại diện</button>
                <button class="tab" onclick="showTab('salary')">Mức lương</button>
                <button class="tab" onclick="showTab('security')">Bảo mật</button>
            </div>
            
            <!-- General Information Tab -->
            <div id="general-tab" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Thông tin cá nhân</h2>
                        <p class="card-subtitle">Cập nhật thông tin cơ bản của bạn</p>
                    </div>
                    
                    <form method="POST" data-validate>
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="grid grid-cols-2 gap-6">
                            <div class="form-group">
                                <label for="name" class="form-label">Họ và tên *</label>
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($user['name']) ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" 
                                       id="email" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($user['email']) ?>"
                                       disabled>
                                <div class="form-help">Email không thể thay đổi</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="workplace-default" class="form-label">Nơi làm việc mặc định</label>
                                <input type="text" 
                                       id="workplace-default" 
                                       name="workplace_default" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($user['workplace_default']) ?>"
                                       placeholder="Ví dụ: Văn phòng chính, Chi nhánh 1...">
                            </div>
                            
                            <div class="form-group">
                                <label for="theme" class="form-label">Giao diện</label>
                                <select id="theme" name="theme" class="form-select">
                                    <option value="light" <?= $user['theme'] === 'light' ? 'selected' : '' ?>>Sáng</option>
                                    <option value="dark" <?= $user['theme'] === 'dark' ? 'selected' : '' ?>>Tối</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="locale" class="form-label">Ngôn ngữ</label>
                                <select id="locale" name="locale" class="form-select">
                                    <option value="vi-VN" <?= $user['locale'] === 'vi-VN' ? 'selected' : '' ?>>Tiếng Việt</option>
                                    <option value="en-US" <?= $user['locale'] === 'en-US' ? 'selected' : '' ?>>English</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Ngày tham gia</label>
                                <input type="text" 
                                       class="form-input" 
                                       value="<?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>"
                                       disabled>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button type="submit" class="btn btn-primary">
                                Cập nhật thông tin
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Avatar Tab -->
            <div id="avatar-tab" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Ảnh đại diện</h2>
                        <p class="card-subtitle">Tải lên ảnh đại diện của bạn</p>
                    </div>
                    
                    <div class="avatar-section">
                        <?php if ($user['avatar'] && file_exists($user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar']) ?>" 
                                 alt="Avatar" 
                                 class="avatar-preview"
                                 id="avatar-image">
                        <?php else: ?>
                            <div class="avatar-preview" id="avatar-image">
                                👤
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" id="avatar-form">
                            <input type="hidden" name="action" value="upload_avatar">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="form-group">
                                <input type="file" 
                                       id="avatar-upload" 
                                       name="avatar" 
                                       accept="image/*"
                                       class="form-input"
                                       onchange="previewAvatar(this)">
                                <div class="form-help">
                                    Chấp nhận file JPG, PNG, GIF. Tối đa 2MB.
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                Tải lên ảnh
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Salary Tab -->
            <div id="salary-tab" class="tab-content">
                <div class="grid grid-cols-2 gap-6">
                    <!-- Update Hourly Rate -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Cập nhật mức lương</h2>
                            <p class="card-subtitle">Thay đổi mức lương theo giờ hiện tại</p>
                        </div>
                        
                        <form method="POST" data-validate>
                            <input type="hidden" name="action" value="update_hourly_rate">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="form-group">
                                <label for="current-rate" class="form-label">Mức lương hiện tại</label>
                                <input type="text" 
                                       id="current-rate" 
                                       class="form-input" 
                                       value="<?= formatCurrency($user['hourly_rate']) ?>"
                                       disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="hourly-rate" class="form-label">Mức lương mới (VNĐ/giờ) *</label>
                                <input type="number" 
                                       id="hourly-rate" 
                                       name="hourly_rate" 
                                       class="form-input" 
                                       value="<?= $user['hourly_rate'] ?>"
                                       min="0"
                                       step="1000"
                                       required>
                                <div class="form-help">
                                    Thay đổi sẽ có hiệu lực từ thời điểm hiện tại
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-full">
                                Cập nhật mức lương
                            </button>
                        </form>
                    </div>
                    
                    <!-- Rate History -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Lịch sử thay đổi lương</h2>
                            <p class="card-subtitle">10 lần thay đổi gần nhất</p>
                        </div>
                        
                        <?php if (empty($rateHistory)): ?>
                            <div class="text-center text-secondary p-8">
                                <p>Chưa có lịch sử thay đổi lương</p>
                            </div>
                        <?php else: ?>
                            <div class="rate-history">
                                <?php foreach ($rateHistory as $index => $rate): ?>
                                    <div class="rate-history-item <?= $index === 0 ? 'current-rate' : '' ?>">
                                        <div>
                                            <div class="font-semibold">
                                                <?= formatCurrency($rate['rate']) ?>
                                                <?php if ($index === 0): ?>
                                                    <span class="badge badge-success">Hiện tại</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm text-secondary">
                                                <?= date('d/m/Y H:i', strtotime($rate['effective_from'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Security Tab -->
            <div id="security-tab" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Đổi mật khẩu</h2>
                        <p class="card-subtitle">Thay đổi mật khẩu đăng nhập của bạn</p>
                    </div>
                    
                    <form method="POST" data-validate>
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="form-group">
                            <label for="current-password" class="form-label">Mật khẩu hiện tại *</label>
                            <input type="password" 
                                   id="current-password" 
                                   name="current_password" 
                                   class="form-input" 
                                   required>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="new-password" class="form-label">Mật khẩu mới *</label>
                                <input type="password" 
                                       id="new-password" 
                                       name="new_password" 
                                       class="form-input" 
                                       required
                                       minlength="6">
                                <div class="form-help">Ít nhất 6 ký tự</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm-new-password" class="form-label">Xác nhận mật khẩu mới *</label>
                                <input type="password" 
                                       id="confirm-new-password" 
                                       name="confirm_password" 
                                       class="form-input" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-warning">
                                Đổi mật khẩu
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/app.js"></script>
    <script>
        // Tab management
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        // Avatar preview
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const avatarImage = document.getElementById('avatar-image');
                    if (avatarImage.tagName === 'IMG') {
                        avatarImage.src = e.target.result;
                    } else {
                        // Replace div with img
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Avatar Preview';
                        img.className = 'avatar-preview';
                        img.id = 'avatar-image';
                        avatarImage.parentNode.replaceChild(img, avatarImage);
                    }
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Password confirmation validation
        document.addEventListener('input', function(e) {
            if (e.target.name === 'confirm_password' || e.target.name === 'new_password') {
                const newPassword = document.querySelector('input[name="new_password"]');
                const confirmPassword = document.querySelector('input[name="confirm_password"]');
                
                if (newPassword && confirmPassword && newPassword.value && confirmPassword.value) {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Mật khẩu xác nhận không khớp');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
            }
        });
        
        // Theme change preview
        document.getElementById('theme').addEventListener('change', function() {
            document.documentElement.setAttribute('data-theme', this.value);
        });
        
        // Auto-submit avatar form
        document.getElementById('avatar-form').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('avatar-upload');
            if (!fileInput.files.length) {
                e.preventDefault();
                showToast('Vui lòng chọn file ảnh', 'warning');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            showLoading(submitBtn);
        });
    </script>
</body>
</html>
