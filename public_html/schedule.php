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

// Get current date parameters
$currentDate = date('Y-m-d');
$viewDate = $_GET['date'] ?? $currentDate;
$viewMode = $_GET['view'] ?? 'month'; // week, month

// Parse view date
$viewDateTime = new DateTime($viewDate);
$viewYear = $viewDateTime->format('Y');
$viewMonth = $viewDateTime->format('m');
$viewWeek = $viewDateTime->format('W');

// Calculate date ranges based on view mode
if ($viewMode === 'week') {
    // Get start and end of week
    $startDate = clone $viewDateTime;
    $startDate->setISODate($viewYear, $viewWeek, 1); // Monday
    $endDate = clone $startDate;
    $endDate->add(new DateInterval('P6D')); // Sunday
} else {
    // Get start and end of month
    $startDate = new DateTime($viewYear . '-' . $viewMonth . '-01');
    $endDate = clone $startDate;
    $endDate->add(new DateInterval('P1M'))->sub(new DateInterval('P1D'));
}

// Get shifts for the date range
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time)), 0) / 60 as worked_hours,
               (SELECT COUNT(*) FROM sessions WHERE shift_id = s.id AND end_time IS NULL) as has_active_session
        FROM shifts s
        LEFT JOIN sessions sess ON s.id = sess.shift_id AND sess.end_time IS NOT NULL
        WHERE s.user_id = ? 
        AND s.date BETWEEN ? AND ?
        GROUP BY s.id
        ORDER BY s.date, s.planned_start
    ");
    $stmt->execute([getCurrentUserId(), $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    $shifts = $stmt->fetchAll();
    
    // Group shifts by date
    $shiftsByDate = [];
    foreach ($shifts as $shift) {
        $date = $shift['date'];
        if (!isset($shiftsByDate[$date])) {
            $shiftsByDate[$date] = [];
        }
        $shiftsByDate[$date][] = $shift;
    }
    
} catch (Exception $e) {
    error_log("Schedule error: " . $e->getMessage());
    $shifts = [];
    $shiftsByDate = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch làm việc - <?= APP_NAME ?></title>
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background-color: var(--border-color);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            overflow: hidden;
        }
        
        .calendar-header {
            background-color: var(--primary-color);
            color: white;
            padding: var(--spacing-3);
            text-align: center;
            font-weight: 600;
            font-size: var(--font-size-sm);
        }
        
        .calendar-day {
            background-color: var(--surface-color);
            min-height: 120px;
            padding: var(--spacing-2);
            position: relative;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .calendar-day:hover {
            background-color: var(--border-color);
        }
        
        .calendar-day.other-month {
            background-color: var(--background-color);
            color: var(--text-secondary);
        }
        
        .calendar-day.today {
            background-color: rgba(59, 130, 246, 0.1);
            border: 2px solid var(--primary-color);
        }
        
        .day-number {
            font-weight: 600;
            margin-bottom: var(--spacing-2);
        }
        
        .shift-item {
            background-color: var(--primary-color);
            color: white;
            padding: 2px 4px;
            border-radius: var(--radius-sm);
            font-size: 11px;
            margin-bottom: 2px;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .shift-item.in-progress {
            background-color: var(--warning-color);
        }
        
        .shift-item.done {
            background-color: var(--success-color);
        }
        
        .shift-item.canceled {
            background-color: var(--error-color);
        }
        
        .week-view {
            display: grid;
            grid-template-columns: 80px repeat(7, 1fr);
            gap: 1px;
            background-color: var(--border-color);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            overflow: hidden;
        }
        
        .time-slot {
            background-color: var(--surface-color);
            padding: var(--spacing-2);
            border-bottom: 1px solid var(--border-color);
            font-size: var(--font-size-sm);
            text-align: center;
        }
        
        .week-day-header {
            background-color: var(--primary-color);
            color: white;
            padding: var(--spacing-3);
            text-align: center;
            font-weight: 600;
        }
        
        .week-day-cell {
            background-color: var(--surface-color);
            padding: var(--spacing-1);
            min-height: 60px;
            position: relative;
        }
        
        .navigation-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-6);
        }
        
        .view-controls {
            display: flex;
            gap: var(--spacing-2);
        }
        
        .date-navigation {
            display: flex;
            align-items: center;
            gap: var(--spacing-4);
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
                    <a href="schedule.php" class="active">Lịch làm việc</a>
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
            <div class="navigation-controls">
                <div class="date-navigation">
                    <button onclick="navigateDate(-1)" class="btn btn-outline">◀</button>
                    <h1 id="current-period-title">
                        <?php 
                        if ($viewMode === 'week') {
                            echo 'Tuần ' . $viewWeek . ', ' . $viewYear;
                        } else {
                            echo 'Tháng ' . $viewMonth . '/' . $viewYear;
                        }
                        ?>
                    </h1>
                    <button onclick="navigateDate(1)" class="btn btn-outline">▶</button>
                    <button onclick="goToToday()" class="btn btn-secondary">Hôm nay</button>
                </div>
                
                <div class="view-controls">
                    <button onclick="changeView('week')" class="btn <?= $viewMode === 'week' ? 'btn-primary' : 'btn-outline' ?>">
                        Tuần
                    </button>
                    <button onclick="changeView('month')" class="btn <?= $viewMode === 'month' ? 'btn-primary' : 'btn-outline' ?>">
                        Tháng
                    </button>
                    <button onclick="openModal('shift-modal')" class="btn btn-success">
                        + Tạo ca mới
                    </button>
                </div>
            </div>

            <!-- Calendar View -->
            <div id="calendar-container">
                <?php if ($viewMode === 'month'): ?>
                    <!-- Month View -->
                    <div class="calendar">
                        <!-- Calendar Headers -->
                        <div class="calendar-header">Thứ 2</div>
                        <div class="calendar-header">Thứ 3</div>
                        <div class="calendar-header">Thứ 4</div>
                        <div class="calendar-header">Thứ 5</div>
                        <div class="calendar-header">Thứ 6</div>
                        <div class="calendar-header">Thứ 7</div>
                        <div class="calendar-header">Chủ nhật</div>
                        
                        <?php
                        // Get first day of month and calculate calendar start
                        $firstDay = new DateTime($viewYear . '-' . $viewMonth . '-01');
                        $firstDayOfWeek = ($firstDay->format('N') == 7) ? 0 : $firstDay->format('N'); // Convert to 0-6 (Monday = 0)
                        $calendarStart = clone $firstDay;
                        $calendarStart->sub(new DateInterval('P' . $firstDayOfWeek . 'D'));
                        
                        // Generate 42 days (6 weeks)
                        for ($i = 0; $i < 42; $i++) {
                            $currentDay = clone $calendarStart;
                            $currentDay->add(new DateInterval('P' . $i . 'D'));
                            $dayString = $currentDay->format('Y-m-d');
                            $isCurrentMonth = $currentDay->format('m') == $viewMonth;
                            $isToday = $dayString === $currentDate;
                            $dayShifts = $shiftsByDate[$dayString] ?? [];
                            
                            $classes = ['calendar-day'];
                            if (!$isCurrentMonth) $classes[] = 'other-month';
                            if ($isToday) $classes[] = 'today';
                        ?>
                            <div class="<?= implode(' ', $classes) ?>" onclick="selectDate('<?= $dayString ?>')">
                                <div class="day-number"><?= $currentDay->format('j') ?></div>
                                <?php foreach ($dayShifts as $shift): ?>
                                    <div class="shift-item <?= $shift['status'] ?>" 
                                         onclick="event.stopPropagation(); showShiftDetails(<?= $shift['id'] ?>)"
                                         title="<?= htmlspecialchars($shift['workplace']) ?> (<?= date('H:i', strtotime($shift['planned_start'])) ?>-<?= date('H:i', strtotime($shift['planned_end'])) ?>)">
                                        <?= htmlspecialchars($shift['workplace']) ?>
                                        <?php if ($shift['has_active_session']): ?>
                                            <span style="color: #fbbf24;">●</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php else: ?>
                    <!-- Week View -->
                    <div class="week-view">
                        <!-- Time column header -->
                        <div class="time-slot"></div>
                        
                        <!-- Day headers -->
                        <?php 
                        for ($i = 0; $i < 7; $i++) {
                            $dayDate = clone $startDate;
                            $dayDate->add(new DateInterval('P' . $i . 'D'));
                            $dayString = $dayDate->format('Y-m-d');
                            $isToday = $dayString === $currentDate;
                        ?>
                            <div class="week-day-header <?= $isToday ? 'today' : '' ?>">
                                <?= $dayDate->format('D j/n') ?>
                            </div>
                        <?php } ?>
                        
                        <!-- Time slots -->
                        <?php for ($hour = 6; $hour <= 22; $hour++): ?>
                            <div class="time-slot"><?= sprintf('%02d:00', $hour) ?></div>
                            
                            <?php for ($i = 0; $i < 7; $i++): 
                                $dayDate = clone $startDate;
                                $dayDate->add(new DateInterval('P' . $i . 'D'));
                                $dayString = $dayDate->format('Y-m-d');
                                $dayShifts = $shiftsByDate[$dayString] ?? [];
                            ?>
                                <div class="week-day-cell" onclick="selectDate('<?= $dayString ?>', <?= $hour ?>)">
                                    <?php foreach ($dayShifts as $shift): 
                                        $startHour = intval(date('H', strtotime($shift['planned_start'])));
                                        $endHour = intval(date('H', strtotime($shift['planned_end'])));
                                        if ($hour >= $startHour && $hour < $endHour):
                                    ?>
                                        <div class="shift-item <?= $shift['status'] ?>" 
                                             onclick="event.stopPropagation(); showShiftDetails(<?= $shift['id'] ?>)"
                                             style="<?= $hour === $startHour ? 'margin-top: 0;' : '' ?>">
                                            <?php if ($hour === $startHour): ?>
                                                <?= htmlspecialchars($shift['workplace']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; endforeach; ?>
                                </div>
                            <?php endfor; ?>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Shift Creation/Edit Modal -->
    <div id="shift-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Tạo ca làm việc mới</h3>
                <button class="modal-close">&times;</button>
            </div>
            
            <form id="shift-form" data-validate>
                <input type="hidden" id="shift-id" name="shift_id">
                
                <div class="form-group">
                    <label for="shift-date" class="form-label">Ngày làm việc *</label>
                    <input type="date" id="shift-date" name="date" class="form-input" required>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="planned-start" class="form-label">Giờ bắt đầu *</label>
                        <input type="time" id="planned-start" name="planned_start" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="planned-end" class="form-label">Giờ kết thúc *</label>
                        <input type="time" id="planned-end" name="planned_end" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="workplace" class="form-label">Nơi làm việc *</label>
                    <input type="text" 
                           id="workplace" 
                           name="workplace" 
                           class="form-input" 
                           required
                           placeholder="Ví dụ: Văn phòng chính, Chi nhánh 1..."
                           value="<?= htmlspecialchars($user['workplace_default']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="notes" class="form-label">Ghi chú</label>
                    <textarea id="notes" 
                              name="notes" 
                              class="form-textarea" 
                              rows="3"
                              placeholder="Ghi chú về ca làm việc (tùy chọn)"></textarea>
                </div>
                
                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="closeModal()" class="btn btn-outline">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu ca làm việc</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Shift Details Modal -->
    <div id="shift-details-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Chi tiết ca làm việc</h3>
                <button class="modal-close">&times;</button>
            </div>
            
            <div id="shift-details-content">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        let currentViewMode = '<?= $viewMode ?>';
        let currentViewDate = '<?= $viewDate ?>';
        
        // Navigation functions
        function navigateDate(direction) {
            const date = new Date(currentViewDate);
            
            if (currentViewMode === 'week') {
                date.setDate(date.getDate() + (direction * 7));
            } else {
                date.setMonth(date.getMonth() + direction);
            }
            
            const newDate = date.toISOString().split('T')[0];
            window.location.href = `schedule.php?view=${currentViewMode}&date=${newDate}`;
        }
        
        function goToToday() {
            const today = new Date().toISOString().split('T')[0];
            window.location.href = `schedule.php?view=${currentViewMode}&date=${today}`;
        }
        
        function changeView(newView) {
            window.location.href = `schedule.php?view=${newView}&date=${currentViewDate}`;
        }
        
        function selectDate(date, hour = null) {
            // Set form date
            document.getElementById('shift-date').value = date;
            
            if (hour !== null) {
                // Set default start time if hour is specified
                document.getElementById('planned-start').value = `${hour.toString().padStart(2, '0')}:00`;
                document.getElementById('planned-end').value = `${(hour + 1).toString().padStart(2, '0')}:00`;
            }
            
            // Clear form for new shift
            document.getElementById('shift-form').reset();
            document.getElementById('shift-date').value = date;
            document.getElementById('workplace').value = '<?= htmlspecialchars($user['workplace_default']) ?>';
            document.querySelector('#shift-modal .modal-title').textContent = 'Tạo ca làm việc mới';
            
            openModal('shift-modal');
        }
        
        // Form submission
        document.getElementById('shift-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {
                action: formData.get('shift_id') ? 'update_shift' : 'create_shift',
                date: formData.get('date'),
                planned_start: formData.get('date') + ' ' + formData.get('planned_start') + ':00',
                planned_end: formData.get('date') + ' ' + formData.get('planned_end') + ':00',
                workplace: formData.get('workplace'),
                notes: formData.get('notes')
            };
            
            if (formData.get('shift_id')) {
                data.shift_id = parseInt(formData.get('shift_id'));
            }
            
            try {
                const submitBtn = this.querySelector('button[type="submit"]');
                
                // Show loading state
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Đang lưu...';
                submitBtn.disabled = true;
                
                const response = await fetch('api/shifts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('HTTP Error:', response.status, errorText);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('API Response:', result);
                
                if (result.success) {
                    if (typeof showToast === 'function') {
                        showToast(result.message, 'success');
                    } else {
                        alert(result.message);
                    }
                    if (typeof closeModal === 'function') {
                        closeModal();
                    }
                    setTimeout(() => location.reload(), 1000);
                } else {
                    if (typeof showToast === 'function') {
                        showToast(result.message, 'error');
                    } else {
                        alert(result.message);
                    }
                }
                
                // Reset button
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                
            } catch (error) {
                console.error('Create shift error:', error);
                
                // Reset button
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.getAttribute('data-original-text') || 'Lưu ca làm việc';
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                
                if (typeof showToast === 'function') {
                    showToast('Có lỗi xảy ra khi lưu ca làm việc', 'error');
                } else {
                    alert('Có lỗi xảy ra khi lưu ca làm việc');
                }
            }
        });
        
        // Show shift details
        async function showShiftDetails(shiftId) {
            try {
                const response = await fetch('api/shifts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        action: 'get_shift_details',
                        shift_id: shiftId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const shift = result.shift;
                    const sessions = result.sessions;
                    
                    const content = `
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <strong>Ngày:</strong> ${formatDate(shift.date)}
                                </div>
                                <div>
                                    <strong>Trạng thái:</strong> 
                                    <span class="badge badge-${shift.status}">${shift.status}</span>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <strong>Kế hoạch:</strong> ${shift.planned_start.split(' ')[1].substring(0,5)} - ${shift.planned_end.split(' ')[1].substring(0,5)}
                                </div>
                                <div>
                                    <strong>Thực tế:</strong> ${shift.worked_hours}h
                                </div>
                            </div>
                            
                            <div>
                                <strong>Nơi làm việc:</strong> ${shift.workplace}
                            </div>
                            
                            ${shift.notes ? `<div><strong>Ghi chú:</strong> ${shift.notes}</div>` : ''}
                            
                            ${sessions.length > 0 ? `
                                <div>
                                    <strong>Phiên làm việc:</strong>
                                    <div class="mt-2 space-y-2">
                                        ${sessions.map(session => `
                                            <div class="p-2 bg-gray-50 rounded">
                                                ${session.start_time.split(' ')[1].substring(0,5)} - 
                                                ${session.end_time ? session.end_time.split(' ')[1].substring(0,5) : 'Đang làm việc'}
                                                ${session.duration_hours ? ` (${session.duration_hours}h)` : ''}
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : ''}
                            
                            <div class="flex gap-2 justify-end pt-4 border-t">
                                ${shift.status === 'planned' ? `
                                    <button onclick="startShift(${shift.id})" class="btn btn-success btn-sm">Bắt đầu</button>
                                ` : ''}
                                ${shift.status !== 'done' && shift.status !== 'canceled' ? `
                                    <button onclick="editShift(${shift.id})" class="btn btn-primary btn-sm">Sửa</button>
                                ` : ''}
                                <button onclick="deleteShift(${shift.id})" class="btn btn-danger btn-sm">Xóa</button>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('shift-details-content').innerHTML = content;
                    openModal('shift-details-modal');
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                showToast('Có lỗi xảy ra khi tải chi tiết ca làm việc', 'error');
            }
        }
        
        // Edit shift
        async function editShift(shiftId) {
            try {
                const response = await fetch('api/shifts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        action: 'get_shift_details',
                        shift_id: shiftId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const shift = result.shift;
                    
                    // Populate form
                    document.getElementById('shift-id').value = shift.id;
                    document.getElementById('shift-date').value = shift.date;
                    document.getElementById('planned-start').value = shift.planned_start.split(' ')[1].substring(0,5);
                    document.getElementById('planned-end').value = shift.planned_end.split(' ')[1].substring(0,5);
                    document.getElementById('workplace').value = shift.workplace;
                    document.getElementById('notes').value = shift.notes || '';
                    
                    document.querySelector('#shift-modal .modal-title').textContent = 'Sửa ca làm việc';
                    
                    closeModal(); // Close details modal
                    openModal('shift-modal');
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                showToast('Có lỗi xảy ra khi tải dữ liệu ca làm việc', 'error');
            }
        }
        
        // Start shift
        async function startShift(shiftId) {
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
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                showToast('Có lỗi xảy ra khi bắt đầu ca làm việc', 'error');
            }
        }
        
        // Delete shift
        async function deleteShift(shiftId) {
            if (!confirm('Bạn có chắc muốn xóa ca làm việc này?')) {
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
                        action: 'delete_shift',
                        shift_id: shiftId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                showToast('Có lỗi xảy ra khi xóa ca làm việc', 'error');
            }
        }
        
        // Helper functions
        function showToast(message, type = 'info', duration = 5000) {
            if (window.app && typeof window.app.showToast === 'function') {
                window.app.showToast(message, type, duration);
            } else {
                // Fallback to simple alert
                alert(message);
            }
        }
        
        function closeModal() {
            if (window.app && typeof window.app.closeModal === 'function') {
                window.app.closeModal();
            } else {
                // Fallback modal close
                const modal = document.querySelector('.modal.active');
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        }
        
        function openModal(modalId) {
            if (window.app && typeof window.app.openModal === 'function') {
                window.app.openModal(modalId);
            } else {
                // Fallback modal open
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            }
        }
    </script>
</body>
</html>
