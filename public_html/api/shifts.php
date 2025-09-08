<?php
require_once '../config/db.php';
require_once '../config/auth_middleware.php';

// Require authentication
requireAuth();

header('Content-Type: application/json');

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Verify CSRF token for non-GET requests
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? $input['csrf_token'] ?? null;
if (!$csrfToken || !verifyCSRFToken($csrfToken)) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// If JSON decode fails, try form data
if ($input === null) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

switch ($action) {
    case 'create_shift':
        handleCreateShift($input);
        break;
        
    case 'update_shift':
        handleUpdateShift($input);
        break;
        
    case 'delete_shift':
        handleDeleteShift($input);
        break;
        
    case 'start_session':
        handleStartSession($input);
        break;
        
    case 'end_session':
        handleEndSession($input);
        break;
        
    case 'pause_session':
        handlePauseSession($input);
        break;
        
    case 'get_shifts':
        handleGetShifts($input);
        break;
        
    case 'get_shift_details':
        handleGetShiftDetails($input);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleCreateShift($input) {
    $userId = getCurrentUserId();
    $date = sanitizeInput($input['date'] ?? '');
    $plannedStart = sanitizeInput($input['planned_start'] ?? '');
    $plannedEnd = sanitizeInput($input['planned_end'] ?? '');
    $workplace = sanitizeInput($input['workplace'] ?? '');
    $notes = sanitizeInput($input['notes'] ?? '');
    
    // Validation
    if (empty($date) || empty($plannedStart) || empty($plannedEnd) || empty($workplace)) {
        jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin bắt buộc']);
    }
    
    // Validate date format
    if (!DateTime::createFromFormat('Y-m-d', $date)) {
        jsonResponse(['success' => false, 'message' => 'Định dạng ngày không hợp lệ']);
    }
    
    // Validate datetime format
    if (!DateTime::createFromFormat('Y-m-d H:i:s', $plannedStart) || !DateTime::createFromFormat('Y-m-d H:i:s', $plannedEnd)) {
        jsonResponse(['success' => false, 'message' => 'Định dạng thời gian không hợp lệ']);
    }
    
    // Validate dates
    $startTime = new DateTime($plannedStart);
    $endTime = new DateTime($plannedEnd);
    
    if ($startTime >= $endTime) {
        jsonResponse(['success' => false, 'message' => 'Thời gian kết thúc phải sau thời gian bắt đầu']);
    }
    
    // Check for overlapping shifts
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT id FROM shifts 
            WHERE user_id = ? 
            AND status != 'canceled'
            AND (
                (planned_start <= ? AND planned_end > ?) OR
                (planned_start < ? AND planned_end >= ?) OR
                (planned_start >= ? AND planned_end <= ?)
            )
        ");
        $stmt->execute([
            $userId, 
            $plannedStart, $plannedStart,
            $plannedEnd, $plannedEnd,
            $plannedStart, $plannedEnd
        ]);
        
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Đã có ca làm việc trùng thời gian']);
        }
        
        // Create shift
        $stmt = $pdo->prepare("
            INSERT INTO shifts (user_id, date, planned_start, planned_end, workplace, notes) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([$userId, $date, $plannedStart, $plannedEnd, $workplace, $notes]);
        
        if (!$result) {
            error_log("Failed to insert shift - SQL Error: " . implode(', ', $stmt->errorInfo()));
            jsonResponse(['success' => false, 'message' => 'Không thể tạo ca làm việc']);
        }
        
        $shiftId = $pdo->lastInsertId();
        
        logAudit('shift_created', [
            'shift_id' => $shiftId,
            'date' => $date,
            'workplace' => $workplace
        ]);
        
        jsonResponse([
            'success' => true, 
            'message' => 'Đã tạo ca làm việc thành công',
            'shift_id' => $shiftId
        ]);
        
    } catch (Exception $e) {
        error_log("Create shift error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra, vui lòng thử lại']);
    }
}

function handleUpdateShift($input) {
    $userId = getCurrentUserId();
    $shiftId = intval($input['shift_id'] ?? 0);
    $date = sanitizeInput($input['date'] ?? '');
    $plannedStart = sanitizeInput($input['planned_start'] ?? '');
    $plannedEnd = sanitizeInput($input['planned_end'] ?? '');
    $workplace = sanitizeInput($input['workplace'] ?? '');
    $notes = sanitizeInput($input['notes'] ?? '');
    $status = sanitizeInput($input['status'] ?? '');
    
    if (!$shiftId) {
        jsonResponse(['success' => false, 'message' => 'ID ca làm việc không hợp lệ']);
    }
    
    try {
        $pdo = getDB();
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT id, status FROM shifts WHERE id = ? AND user_id = ?");
        $stmt->execute([$shiftId, $userId]);
        $shift = $stmt->fetch();
        
        if (!$shift) {
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy ca làm việc']);
        }
        
        // Don't allow editing if there are active sessions
        if ($shift['status'] === 'in_progress') {
            $stmt = $pdo->prepare("SELECT id FROM sessions WHERE shift_id = ? AND end_time IS NULL");
            $stmt->execute([$shiftId]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Không thể sửa ca đang trong quá trình làm việc']);
            }
        }
        
        // Validate dates if provided
        if ($plannedStart && $plannedEnd) {
            $startTime = new DateTime($plannedStart);
            $endTime = new DateTime($plannedEnd);
            
            if ($startTime >= $endTime) {
                jsonResponse(['success' => false, 'message' => 'Thời gian kết thúc phải sau thời gian bắt đầu']);
            }
        }
        
        // Check for overlapping shifts (excluding current shift)
        if ($plannedStart && $plannedEnd) {
            $stmt = $pdo->prepare("
                SELECT id FROM shifts 
                WHERE user_id = ? AND id != ?
                AND status != 'canceled'
                AND (
                    (planned_start <= ? AND planned_end > ?) OR
                    (planned_start < ? AND planned_end >= ?) OR
                    (planned_start >= ? AND planned_end <= ?)
                )
            ");
            $stmt->execute([
                $userId, $shiftId,
                $plannedStart, $plannedStart,
                $plannedEnd, $plannedEnd,
                $plannedStart, $plannedEnd
            ]);
            
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Đã có ca làm việc khác trùng thời gian']);
            }
        }
        
        // Build update query
        $updateFields = [];
        $updateValues = [];
        
        if ($date) {
            $updateFields[] = "date = ?";
            $updateValues[] = $date;
        }
        if ($plannedStart) {
            $updateFields[] = "planned_start = ?";
            $updateValues[] = $plannedStart;
        }
        if ($plannedEnd) {
            $updateFields[] = "planned_end = ?";
            $updateValues[] = $plannedEnd;
        }
        if ($workplace) {
            $updateFields[] = "workplace = ?";
            $updateValues[] = $workplace;
        }
        if ($notes !== null) {
            $updateFields[] = "notes = ?";
            $updateValues[] = $notes;
        }
        if ($status && in_array($status, ['planned', 'in_progress', 'done', 'canceled'])) {
            $updateFields[] = "status = ?";
            $updateValues[] = $status;
        }
        
        $updateFields[] = "updated_at = NOW()";
        $updateValues[] = $shiftId;
        
        if (empty($updateFields)) {
            jsonResponse(['success' => false, 'message' => 'Không có dữ liệu để cập nhật']);
        }
        
        $sql = "UPDATE shifts SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        logAudit('shift_updated', [
            'shift_id' => $shiftId,
            'updated_fields' => array_keys($input)
        ]);
        
        jsonResponse(['success' => true, 'message' => 'Đã cập nhật ca làm việc thành công']);
        
    } catch (Exception $e) {
        error_log("Update shift error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.']);
    }
}

function handleDeleteShift($input) {
    $userId = getCurrentUserId();
    $shiftId = intval($input['shift_id'] ?? 0);
    
    if (!$shiftId) {
        jsonResponse(['success' => false, 'message' => 'ID ca làm việc không hợp lệ']);
    }
    
    try {
        $pdo = getDB();
        
        // Verify ownership and check status
        $stmt = $pdo->prepare("SELECT id, status FROM shifts WHERE id = ? AND user_id = ?");
        $stmt->execute([$shiftId, $userId]);
        $shift = $stmt->fetch();
        
        if (!$shift) {
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy ca làm việc']);
        }
        
        // Don't allow deleting if there are sessions
        $stmt = $pdo->prepare("SELECT id FROM sessions WHERE shift_id = ?");
        $stmt->execute([$shiftId]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Không thể xóa ca đã có dữ liệu chấm công']);
        }
        
        // Delete shift
        $stmt = $pdo->prepare("DELETE FROM shifts WHERE id = ?");
        $stmt->execute([$shiftId]);
        
        logAudit('shift_deleted', ['shift_id' => $shiftId]);
        
        jsonResponse(['success' => true, 'message' => 'Đã xóa ca làm việc thành công']);
        
    } catch (Exception $e) {
        error_log("Delete shift error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.']);
    }
}

function handleStartSession($input) {
    $userId = getCurrentUserId();
    $shiftId = intval($input['shift_id'] ?? 0);
    
    if (!$shiftId) {
        jsonResponse(['success' => false, 'message' => 'ID ca làm việc không hợp lệ']);
    }
    
    try {
        $pdo = getDB();
        
        // Verify ownership and check shift
        $stmt = $pdo->prepare("SELECT id, status, planned_start, planned_end FROM shifts WHERE id = ? AND user_id = ?");
        $stmt->execute([$shiftId, $userId]);
        $shift = $stmt->fetch();
        
        if (!$shift) {
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy ca làm việc']);
        }
        
        // Check if there's already an active session for this user
        $stmt = $pdo->prepare("
            SELECT s.id FROM sessions s
            JOIN shifts sh ON s.shift_id = sh.id
            WHERE sh.user_id = ? AND s.end_time IS NULL
        ");
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Bạn đang có ca làm việc khác đang hoạt động']);
        }
        
        // Start new session
        $stmt = $pdo->prepare("INSERT INTO sessions (shift_id, start_time) VALUES (?, NOW())");
        $stmt->execute([$shiftId]);
        $sessionId = $pdo->lastInsertId();
        
        // Update shift status
        $stmt = $pdo->prepare("UPDATE shifts SET status = 'in_progress', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$shiftId]);
        
        logAudit('session_started', [
            'shift_id' => $shiftId,
            'session_id' => $sessionId
        ]);
        
        jsonResponse([
            'success' => true, 
            'message' => 'Đã bắt đầu ca làm việc',
            'session_id' => $sessionId
        ]);
        
    } catch (Exception $e) {
        error_log("Start session error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.']);
    }
}

function handleEndSession($input) {
    $userId = getCurrentUserId();
    $sessionId = intval($input['session_id'] ?? 0);
    
    if (!$sessionId) {
        jsonResponse(['success' => false, 'message' => 'ID phiên làm việc không hợp lệ']);
    }
    
    try {
        $pdo = getDB();
        
        // Verify session ownership
        $stmt = $pdo->prepare("
            SELECT s.*, sh.user_id, sh.id as shift_id 
            FROM sessions s
            JOIN shifts sh ON s.shift_id = sh.id
            WHERE s.id = ? AND sh.user_id = ? AND s.end_time IS NULL
        ");
        $stmt->execute([$sessionId, $userId]);
        $session = $stmt->fetch();
        
        if (!$session) {
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy phiên làm việc đang hoạt động']);
        }
        
        // End session
        $stmt = $pdo->prepare("UPDATE sessions SET end_time = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$sessionId]);
        
        // Check if there are more active sessions for this shift
        $stmt = $pdo->prepare("SELECT id FROM sessions WHERE shift_id = ? AND end_time IS NULL");
        $stmt->execute([$session['shift_id']]);
        $hasActiveSessions = $stmt->fetch();
        
        // Update shift status if no more active sessions
        if (!$hasActiveSessions) {
            $stmt = $pdo->prepare("UPDATE shifts SET status = 'done', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$session['shift_id']]);
        }
        
        // Calculate session duration
        $stmt = $pdo->prepare("
            SELECT TIMESTAMPDIFF(MINUTE, start_time, end_time) / 60 as duration
            FROM sessions WHERE id = ?
        ");
        $stmt->execute([$sessionId]);
        $duration = $stmt->fetchColumn();
        
        logAudit('session_ended', [
            'shift_id' => $session['shift_id'],
            'session_id' => $sessionId,
            'duration_hours' => $duration
        ]);
        
        jsonResponse([
            'success' => true, 
            'message' => 'Đã kết thúc ca làm việc',
            'duration_hours' => round($duration, 2)
        ]);
        
    } catch (Exception $e) {
        error_log("End session error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.']);
    }
}

function handlePauseSession($input) {
    // This would end the current session and allow starting a new one later
    handleEndSession($input);
}

function handleGetShifts($input) {
    $userId = getCurrentUserId();
    $startDate = sanitizeInput($input['start_date'] ?? date('Y-m-01'));
    $endDate = sanitizeInput($input['end_date'] ?? date('Y-m-t'));
    
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time)), 0) / 60 as worked_hours,
                COUNT(sess.id) as session_count
            FROM shifts s
            LEFT JOIN sessions sess ON s.id = sess.shift_id AND sess.end_time IS NOT NULL
            WHERE s.user_id = ? AND s.date BETWEEN ? AND ?
            GROUP BY s.id
            ORDER BY s.date, s.planned_start
        ");
        $stmt->execute([$userId, $startDate, $endDate]);
        $shifts = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'shifts' => $shifts
        ]);
        
    } catch (Exception $e) {
        error_log("Get shifts error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra khi tải dữ liệu']);
    }
}

function handleGetShiftDetails($input) {
    $userId = getCurrentUserId();
    $shiftId = intval($input['shift_id'] ?? 0);
    
    if (!$shiftId) {
        jsonResponse(['success' => false, 'message' => 'ID ca làm việc không hợp lệ']);
    }
    
    try {
        $pdo = getDB();
        
        // Get shift details
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time)), 0) / 60 as worked_hours
            FROM shifts s
            LEFT JOIN sessions sess ON s.id = sess.shift_id AND sess.end_time IS NOT NULL
            WHERE s.id = ? AND s.user_id = ?
            GROUP BY s.id
        ");
        $stmt->execute([$shiftId, $userId]);
        $shift = $stmt->fetch();
        
        if (!$shift) {
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy ca làm việc']);
        }
        
        // Get sessions
        $stmt = $pdo->prepare("
            SELECT *, 
                   CASE 
                       WHEN end_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, start_time, end_time) / 60
                       ELSE NULL
                   END as duration_hours
            FROM sessions 
            WHERE shift_id = ? 
            ORDER BY start_time
        ");
        $stmt->execute([$shiftId]);
        $sessions = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'shift' => $shift,
            'sessions' => $sessions
        ]);
        
    } catch (Exception $e) {
        error_log("Get shift details error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra khi tải chi tiết ca làm việc']);
    }
}
?>
