<?php
require_once 'config/db.php';
require_once 'config/auth_middleware.php';

// Redirect to login if not authenticated
requireAuth();

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Get recent shifts for quick overview
try {
    $pdo = getDB();
    
    // Get today's shifts
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time)), 0) / 60 as worked_hours
        FROM shifts s
        LEFT JOIN sessions sess ON s.id = sess.shift_id AND sess.end_time IS NOT NULL
        WHERE s.user_id = ? AND s.date = CURDATE()
        GROUP BY s.id
        ORDER BY s.planned_start
    ");
    $stmt->execute([getCurrentUserId()]);
    $todayShifts = $stmt->fetchAll();
    
    // Get this week's summary
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_shifts,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time)), 0) / 60 as total_hours,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time) / 60 * hr.rate), 0) as total_earnings
        FROM shifts s
        LEFT JOIN sessions sess ON s.id = sess.shift_id AND sess.end_time IS NOT NULL
        LEFT JOIN hourly_rate_history hr ON s.user_id = hr.user_id 
            AND hr.effective_from <= s.planned_start
            AND hr.effective_from = (
                SELECT MAX(effective_from) 
                FROM hourly_rate_history 
                WHERE user_id = s.user_id AND effective_from <= s.planned_start
            )
        WHERE s.user_id = ? 
        AND s.date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
        AND s.date <= DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)
    ");
    $stmt->execute([getCurrentUserId()]);
    $weekSummary = $stmt->fetch();
    
    // Get active session (if any)
    $stmt = $pdo->prepare("
        SELECT s.*, sess.start_time, sess.id as session_id
        FROM shifts s
        JOIN sessions sess ON s.id = sess.shift_id
        WHERE s.user_id = ? AND sess.end_time IS NULL
        ORDER BY sess.start_time DESC
        LIMIT 1
    ");
    $stmt->execute([getCurrentUserId()]);
    $activeSession = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $todayShifts = [];
    $weekSummary = [
        'total_shifts' => 0,
        'total_hours' => 0,
        'total_earnings' => 0
    ];
    $activeSession = null;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= APP_NAME ?></title>
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-theme="<?= htmlspecialchars($user['theme']) ?>">
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php" class="logo"><?= APP_NAME ?></a>
                
                <button class="mobile-menu-toggle">☰</button>
                
                <nav class="nav">
                    <a href="dashboard.php" class="active">Dashboard</a>
                    <a href="schedule.php">Lịch làm việc</a>
                    <a href="timesheet.php">Bảng công</a>
                    <a href="profile.php">Hồ sơ</a>
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
            <!-- Quick Stats -->
            <div class="grid grid-cols-4 gap-6 mb-8">
                <div class="card">
                    <div class="flex items-center gap-4">
                        <div style="font-size: 2rem;">📊</div>
                        <div>
                            <div class="text-2xl font-bold"><?= $weekSummary['total_shifts'] ?></div>
                            <div class="text-secondary text-sm">Ca làm tuần này</div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="flex items-center gap-4">
                        <div style="font-size: 2rem;">⏰</div>
                        <div>
                            <div class="text-2xl font-bold"><?= number_format($weekSummary['total_hours'], 1) ?>h</div>
                            <div class="text-secondary text-sm">Giờ làm tuần này</div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="flex items-center gap-4">
                        <div style="font-size: 2rem;">💰</div>
                        <div>
                            <div class="text-2xl font-bold"><?= formatCurrency($weekSummary['total_earnings']) ?></div>
                            <div class="text-secondary text-sm">Thu nhập tuần này</div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="flex items-center gap-4">
                        <div style="font-size: 2rem;">💵</div>
                        <div>
                            <div class="text-2xl font-bold"><?= formatCurrency($user['hourly_rate']) ?></div>
                            <div class="text-secondary text-sm">Lương theo giờ</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Session Alert -->
            <?php if ($activeSession): ?>
                <div class="alert alert-warning mb-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <strong>Đang trong ca làm việc!</strong>
                            <div class="text-sm">
                                Ca: <?= htmlspecialchars($activeSession['workplace']) ?> - 
                                Bắt đầu: <?= date('H:i', strtotime($activeSession['start_time'])) ?>
                                <span id="working-duration"></span>
                            </div>
                        </div>
                        <button onclick="endWorkSession(<?= $activeSession['session_id'] ?>)" class="btn btn-danger btn-sm">
                            Kết thúc ca
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-8">
                <!-- Today's Schedule -->
                <div class="card">
                    <div class="card-header">
                        <div class="flex justify-between items-center">
                            <h2 class="card-title">Lịch hôm nay</h2>
                            <a href="schedule.php" class="btn btn-primary btn-sm">
                                + Thêm ca mới
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($todayShifts)): ?>
                        <div class="text-center text-secondary p-8">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                            <p>Chưa có ca làm việc nào hôm nay</p>
                            <a href="schedule.php" class="btn btn-primary mt-4">Tạo ca mới</a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($todayShifts as $shift): ?>
                                <div class="flex items-center justify-between p-3 border border-gray-200 rounded">
                                    <div>
                                        <div class="font-medium"><?= htmlspecialchars($shift['workplace']) ?></div>
                                        <div class="text-sm text-secondary">
                                            <?= date('H:i', strtotime($shift['planned_start'])) ?> - 
                                            <?= date('H:i', strtotime($shift['planned_end'])) ?>
                                            <?php if ($shift['worked_hours'] > 0): ?>
                                                <span class="text-success">
                                                    (Đã làm: <?= number_format($shift['worked_hours'], 1) ?>h)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="badge badge-<?= $shift['status'] ?>">
                                            <?= ucfirst($shift['status']) ?>
                                        </span>
                                        <?php if ($shift['status'] === 'planned'): ?>
                                            <button onclick="startWorkSession(<?= $shift['id'] ?>)" class="btn btn-success btn-sm">
                                                Bắt đầu
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Thao tác nhanh</h2>
                    </div>
                    
                    <div class="space-y-3">
                        <a href="schedule.php" class="btn btn-primary w-full">
                            📅 Tạo ca làm việc mới
                        </a>
                        
                        <a href="timesheet.php" class="btn btn-secondary w-full">
                            📊 Xem bảng công tháng này
                        </a>
                        
                        <a href="timesheet.php?action=export" class="btn btn-outline w-full">
                            📄 Xuất báo cáo CSV
                        </a>
                        
                        <a href="profile.php" class="btn btn-outline w-full">
                            ⚙️ Cập nhật hồ sơ
                        </a>
                    </div>
                    
                    <div class="mt-6 p-4 bg-gray-50 rounded">
                        <h4 class="font-semibold mb-2">Thông tin tài khoản</h4>
                        <div class="text-sm text-secondary space-y-1">
                            <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                            <p><strong>Nơi làm việc:</strong> <?= htmlspecialchars($user['workplace_default'] ?: 'Chưa thiết lập') ?></p>
                            <p><strong>Ngày tham gia:</strong> <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/app.js"></script>
    <script>
        // Update working duration in real-time
        <?php if ($activeSession): ?>
        function updateWorkingDuration() {
            const startTime = new Date('<?= $activeSession['start_time'] ?>');
            const now = new Date();
            const duration = now - startTime;
            
            const hours = Math.floor(duration / (1000 * 60 * 60));
            const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60));
            
            document.getElementById('working-duration').textContent = 
                ` - Đã làm: ${hours}h ${minutes}m`;
        }
        
        // Update every minute
        updateWorkingDuration();
        setInterval(updateWorkingDuration, 60000);
        <?php endif; ?>
        
        // Start work session
        async function startWorkSession(shiftId) {
            try {
                const response = await fetch('api/shifts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        action: 'start_session',
                        shift_id: shiftId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Đã bắt đầu ca làm việc!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Có lỗi xảy ra', 'error');
                }
            } catch (error) {
                showToast('Có lỗi xảy ra khi bắt đầu ca làm việc', 'error');
            }
        }
        
        // End work session
        async function endWorkSession(sessionId) {
            if (!confirm('Bạn có chắc muốn kết thúc ca làm việc?')) {
                return;
            }
            
            try {
                const response = await fetch('api/shifts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        action: 'end_session',
                        session_id: sessionId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Đã kết thúc ca làm việc!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Có lỗi xảy ra', 'error');
                }
            } catch (error) {
                showToast('Có lỗi xảy ra khi kết thúc ca làm việc', 'error');
            }
        }
    </script>
</body>
</html>
