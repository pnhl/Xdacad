<?php
require_once '../config/db.php';
require_once '../config/auth_middleware.php';

// Require authentication
requireAuth();

header('Content-Type: application/json');

// Handle both GET and POST requests
$method = $_SERVER['REQUEST_METHOD'];
$input = $method === 'POST' ? json_decode(file_get_contents('php://input'), true) : $_GET;
$action = $input['action'] ?? '';

// Verify CSRF token for POST requests
if ($method === 'POST') {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? null;
    if (!$csrfToken || !verifyCSRFToken($csrfToken)) {
        jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
    }
}

switch ($action) {
    case 'get_timesheet':
        handleGetTimesheet($input);
        break;
        
    case 'get_monthly_summary':
        handleGetMonthlySummary($input);
        break;
        
    case 'export_csv':
        handleExportCSV($input);
        break;
        
    case 'get_yearly_summary':
        handleGetYearlySummary($input);
        break;
        
    case 'calculate_overtime':
        handleCalculateOvertime($input);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleGetTimesheet($input) {
    $userId = getCurrentUserId();
    $month = intval($input['month'] ?? date('n'));
    $year = intval($input['year'] ?? date('Y'));
    $useCurrentRate = ($input['use_current_rate'] ?? 'false') === 'true';
    
    // Validate month and year
    if ($month < 1 || $month > 12 || $year < 2020 || $year > 2030) {
        jsonResponse(['success' => false, 'message' => 'Tháng hoặc năm không hợp lệ']);
    }
    
    try {
        $pdo = getDB();
        
        // Get current user's hourly rate if using current rate
        $currentRate = 0;
        if ($useCurrentRate) {
            $currentRate = getUserHourlyRate($userId);
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                s.id,
                s.date,
                s.planned_start,
                s.planned_end,
                s.workplace,
                s.notes,
                s.status,
                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time)), 0) / 60 as worked_hours,
                CASE 
                    WHEN ? > 0 THEN ?
                    ELSE COALESCE(hr.rate, u.hourly_rate, 0)
                END as hourly_rate,
                CASE 
                    WHEN ? > 0 THEN (COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time)), 0) / 60) * ?
                    ELSE (COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time)), 0) / 60) * COALESCE(hr.rate, u.hourly_rate, 0)
                END as daily_earnings,
                COUNT(sess.id) as session_count
            FROM shifts s
            LEFT JOIN sessions sess ON s.id = sess.shift_id AND sess.end_time IS NOT NULL
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN hourly_rate_history hr ON s.user_id = hr.user_id 
                AND hr.effective_from <= s.planned_start
                AND hr.effective_from = (
                    SELECT MAX(effective_from) 
                    FROM hourly_rate_history 
                    WHERE user_id = s.user_id AND effective_from <= s.planned_start
                )
            WHERE s.user_id = ? 
            AND MONTH(s.date) = ? 
            AND YEAR(s.date) = ?
            GROUP BY s.id
            ORDER BY s.date, s.planned_start
        ");
        
        $stmt->execute([
            $currentRate, $currentRate, $currentRate, $currentRate,
            $userId, $month, $year
        ]);
        
        $timesheet = $stmt->fetchAll();
        
        // Calculate totals
        $totalHours = 0;
        $totalEarnings = 0;
        $totalDays = 0;
        
        foreach ($timesheet as $entry) {
            if ($entry['worked_hours'] > 0) {
                $totalHours += $entry['worked_hours'];
                $totalEarnings += $entry['daily_earnings'];
                $totalDays++;
            }
        }
        
        jsonResponse([
            'success' => true,
            'timesheet' => $timesheet,
            'summary' => [
                'total_days' => $totalDays,
                'total_hours' => round($totalHours, 2),
                'total_earnings' => round($totalEarnings, 0),
                'average_hours_per_day' => $totalDays > 0 ? round($totalHours / $totalDays, 2) : 0,
                'month' => $month,
                'year' => $year,
                'use_current_rate' => $useCurrentRate,
                'current_rate' => $currentRate
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get timesheet error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra khi tải bảng công']);
    }
}

function handleGetMonthlySummary($input) {
    $userId = getCurrentUserId();
    $year = intval($input['year'] ?? date('Y'));
    
    if ($year < 2020 || $year > 2030) {
        jsonResponse(['success' => false, 'message' => 'Năm không hợp lệ']);
    }
    
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT 
                MONTH(s.date) as month,
                COUNT(DISTINCT s.id) as total_shifts,
                COUNT(DISTINCT CASE WHEN sess.end_time IS NOT NULL THEN s.id END) as completed_shifts,
                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time)), 0) / 60 as total_hours,
                COALESCE(SUM(
                    (TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time) / 60) * 
                    COALESCE(hr.rate, u.hourly_rate, 0)
                ), 0) as total_earnings
            FROM shifts s
            LEFT JOIN sessions sess ON s.id = sess.shift_id AND sess.end_time IS NOT NULL
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN hourly_rate_history hr ON s.user_id = hr.user_id 
                AND hr.effective_from <= s.planned_start
                AND hr.effective_from = (
                    SELECT MAX(effective_from) 
                    FROM hourly_rate_history 
                    WHERE user_id = s.user_id AND effective_from <= s.planned_start
                )
            WHERE s.user_id = ? AND YEAR(s.date) = ?
            GROUP BY MONTH(s.date)
            ORDER BY MONTH(s.date)
        ");
        
        $stmt->execute([$userId, $year]);
        $monthlySummary = $stmt->fetchAll();
        
        // Fill in missing months with zeros
        $fullSummary = [];
        for ($month = 1; $month <= 12; $month++) {
            $found = false;
            foreach ($monthlySummary as $summary) {
                if ($summary['month'] == $month) {
                    $fullSummary[] = $summary;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $fullSummary[] = [
                    'month' => $month,
                    'total_shifts' => 0,
                    'completed_shifts' => 0,
                    'total_hours' => 0,
                    'total_earnings' => 0
                ];
            }
        }
        
        jsonResponse([
            'success' => true,
            'monthly_summary' => $fullSummary,
            'year' => $year
        ]);
        
    } catch (Exception $e) {
        error_log("Get monthly summary error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra khi tải tổng kết tháng']);
    }
}

function handleExportCSV($input) {
    $userId = getCurrentUserId();
    $month = intval($input['month'] ?? date('n'));
    $year = intval($input['year'] ?? date('Y'));
    $useCurrentRate = ($input['use_current_rate'] ?? 'false') === 'true';
    
    // Get timesheet data
    $input['action'] = 'get_timesheet';
    ob_start();
    handleGetTimesheet($input);
    $jsonOutput = ob_get_clean();
    
    $data = json_decode($jsonOutput, true);
    
    if (!$data['success']) {
        jsonResponse(['success' => false, 'message' => 'Không thể xuất dữ liệu']);
    }
    
    // Prepare CSV data
    $csvData = [];
    $csvData[] = [
        'Ngày',
        'Nơi làm việc',
        'Giờ bắt đầu',
        'Giờ kết thúc',
        'Giờ làm thực tế',
        'Lương/giờ',
        'Tổng lương',
        'Trạng thái',
        'Ghi chú'
    ];
    
    foreach ($data['timesheet'] as $entry) {
        $csvData[] = [
            date('d/m/Y', strtotime($entry['date'])),
            $entry['workplace'],
            date('H:i', strtotime($entry['planned_start'])),
            date('H:i', strtotime($entry['planned_end'])),
            number_format($entry['worked_hours'], 2) . ' giờ',
            number_format($entry['hourly_rate'], 0) . ' ₫',
            number_format($entry['daily_earnings'], 0) . ' ₫',
            ucfirst($entry['status']),
            $entry['notes'] ?: '-'
        ];
    }
    
    // Add summary row
    $csvData[] = [];
    $csvData[] = [
        'TỔNG KẾT',
        '',
        '',
        '',
        number_format($data['summary']['total_hours'], 2) . ' giờ',
        '',
        number_format($data['summary']['total_earnings'], 0) . ' ₫',
        '',
        ''
    ];
    
    // Generate filename
    $filename = "bang_cong_{$month}_{$year}.csv";
    
    // Output CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Add BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    foreach ($csvData as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function handleGetYearlySummary($input) {
    $userId = getCurrentUserId();
    $year = intval($input['year'] ?? date('Y'));
    
    if ($year < 2020 || $year > 2030) {
        jsonResponse(['success' => false, 'message' => 'Năm không hợp lệ']);
    }
    
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT s.id) as total_shifts,
                COUNT(DISTINCT CASE WHEN sess.end_time IS NOT NULL THEN s.id END) as completed_shifts,
                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time)), 0) / 60 as total_hours,
                COALESCE(SUM(
                    (TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time) / 60) * 
                    COALESCE(hr.rate, u.hourly_rate, 0)
                ), 0) as total_earnings,
                COUNT(DISTINCT s.workplace) as unique_workplaces,
                AVG(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time) / 60) as avg_hours_per_shift
            FROM shifts s
            LEFT JOIN sessions sess ON s.id = sess.shift_id AND sess.end_time IS NOT NULL
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN hourly_rate_history hr ON s.user_id = hr.user_id 
                AND hr.effective_from <= s.planned_start
                AND hr.effective_from = (
                    SELECT MAX(effective_from) 
                    FROM hourly_rate_history 
                    WHERE user_id = s.user_id AND effective_from <= s.planned_start
                )
            WHERE s.user_id = ? AND YEAR(s.date) = ?
        ");
        
        $stmt->execute([$userId, $year]);
        $yearlySummary = $stmt->fetch();
        
        // Get workplace breakdown
        $stmt = $pdo->prepare("
            SELECT 
                s.workplace,
                COUNT(DISTINCT s.id) as shifts,
                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time)), 0) / 60 as hours,
                COALESCE(SUM(
                    (TIMESTAMPDIFF(MINUTE, sess.start_time, sess.end_time) / 60) * 
                    COALESCE(hr.rate, u.hourly_rate, 0)
                ), 0) as earnings
            FROM shifts s
            LEFT JOIN sessions sess ON s.id = sess.shift_id AND sess.end_time IS NOT NULL
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN hourly_rate_history hr ON s.user_id = hr.user_id 
                AND hr.effective_from <= s.planned_start
                AND hr.effective_from = (
                    SELECT MAX(effective_from) 
                    FROM hourly_rate_history 
                    WHERE user_id = s.user_id AND effective_from <= s.planned_start
                )
            WHERE s.user_id = ? AND YEAR(s.date) = ?
            GROUP BY s.workplace
            ORDER BY hours DESC
        ");
        
        $stmt->execute([$userId, $year]);
        $workplaceBreakdown = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'yearly_summary' => $yearlySummary,
            'workplace_breakdown' => $workplaceBreakdown,
            'year' => $year
        ]);
        
    } catch (Exception $e) {
        error_log("Get yearly summary error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra khi tải tổng kết năm']);
    }
}

function handleCalculateOvertime($input) {
    $userId = getCurrentUserId();
    $month = intval($input['month'] ?? date('n'));
    $year = intval($input['year'] ?? date('Y'));
    $regularHoursPerWeek = floatval($input['regular_hours_per_week'] ?? 40);
    $overtimeMultiplier = floatval($input['overtime_multiplier'] ?? 1.5);
    
    try {
        $pdo = getDB();
        
        // Get timesheet for the month
        $input['action'] = 'get_timesheet';
        $input['use_current_rate'] = 'false'; // Use historical rates for overtime calculation
        
        ob_start();
        handleGetTimesheet($input);
        $jsonOutput = ob_get_clean();
        
        $data = json_decode($jsonOutput, true);
        
        if (!$data['success']) {
            jsonResponse(['success' => false, 'message' => 'Không thể tính toán tăng ca']);
        }
        
        // Group by week and calculate overtime
        $weeklyData = [];
        foreach ($data['timesheet'] as $entry) {
            $date = new DateTime($entry['date']);
            $weekOfYear = $date->format('W');
            $year = $date->format('Y');
            $weekKey = "{$year}-W{$weekOfYear}";
            
            if (!isset($weeklyData[$weekKey])) {
                $weeklyData[$weekKey] = [
                    'week' => $weekOfYear,
                    'year' => $year,
                    'regular_hours' => 0,
                    'overtime_hours' => 0,
                    'regular_pay' => 0,
                    'overtime_pay' => 0,
                    'total_hours' => 0,
                    'total_pay' => 0,
                    'entries' => []
                ];
            }
            
            $weeklyData[$weekKey]['entries'][] = $entry;
            $weeklyData[$weekKey]['total_hours'] += $entry['worked_hours'];
        }
        
        // Calculate overtime for each week
        foreach ($weeklyData as &$week) {
            if ($week['total_hours'] <= $regularHoursPerWeek) {
                $week['regular_hours'] = $week['total_hours'];
                $week['overtime_hours'] = 0;
            } else {
                $week['regular_hours'] = $regularHoursPerWeek;
                $week['overtime_hours'] = $week['total_hours'] - $regularHoursPerWeek;
            }
            
            // Calculate pay for each entry in the week
            $remainingRegularHours = $week['regular_hours'];
            $remainingOvertimeHours = $week['overtime_hours'];
            
            foreach ($week['entries'] as $entry) {
                $entryHours = $entry['worked_hours'];
                $hourlyRate = $entry['hourly_rate'];
                
                if ($remainingRegularHours > 0) {
                    $regularHoursForEntry = min($entryHours, $remainingRegularHours);
                    $week['regular_pay'] += $regularHoursForEntry * $hourlyRate;
                    $remainingRegularHours -= $regularHoursForEntry;
                    $entryHours -= $regularHoursForEntry;
                }
                
                if ($entryHours > 0 && $remainingOvertimeHours > 0) {
                    $overtimeHoursForEntry = min($entryHours, $remainingOvertimeHours);
                    $week['overtime_pay'] += $overtimeHoursForEntry * $hourlyRate * $overtimeMultiplier;
                    $remainingOvertimeHours -= $overtimeHoursForEntry;
                }
            }
            
            $week['total_pay'] = $week['regular_pay'] + $week['overtime_pay'];
        }
        
        // Calculate monthly totals
        $monthlyTotals = [
            'regular_hours' => 0,
            'overtime_hours' => 0,
            'regular_pay' => 0,
            'overtime_pay' => 0,
            'total_hours' => 0,
            'total_pay' => 0
        ];
        
        foreach ($weeklyData as $week) {
            $monthlyTotals['regular_hours'] += $week['regular_hours'];
            $monthlyTotals['overtime_hours'] += $week['overtime_hours'];
            $monthlyTotals['regular_pay'] += $week['regular_pay'];
            $monthlyTotals['overtime_pay'] += $week['overtime_pay'];
            $monthlyTotals['total_hours'] += $week['total_hours'];
            $monthlyTotals['total_pay'] += $week['total_pay'];
        }
        
        jsonResponse([
            'success' => true,
            'overtime_calculation' => [
                'weekly_data' => array_values($weeklyData),
                'monthly_totals' => $monthlyTotals,
                'settings' => [
                    'regular_hours_per_week' => $regularHoursPerWeek,
                    'overtime_multiplier' => $overtimeMultiplier
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Calculate overtime error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Đã có lỗi xảy ra khi tính toán tăng ca']);
    }
}
?>
