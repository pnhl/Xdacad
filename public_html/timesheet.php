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

// Get current month/year parameters
$currentMonth = intval($_GET['month'] ?? date('n'));
$currentYear = intval($_GET['year'] ?? date('Y'));
$useCurrentRate = ($_GET['use_current_rate'] ?? 'false') === 'true';

// Handle export request
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Location: api/reports.php?action=export_csv&month=' . $currentMonth . '&year=' . $currentYear . '&use_current_rate=' . ($useCurrentRate ? 'true' : 'false'));
    exit;
}

// Validate month and year
if ($currentMonth < 1 || $currentMonth > 12 || $currentYear < 2020 || $currentYear > 2030) {
    $currentMonth = date('n');
    $currentYear = date('Y');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng công - <?= APP_NAME ?></title>
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .timesheet-table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--font-size-sm);
        }
        
        .timesheet-table th,
        .timesheet-table td {
            padding: var(--spacing-3);
            border: 1px solid var(--border-color);
            text-align: left;
        }
        
        .timesheet-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .timesheet-table tr:nth-child(even) {
            background-color: var(--border-color);
        }
        
        .timesheet-table tr:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }
        
        .summary-row {
            background-color: var(--success-color) !important;
            color: white;
            font-weight: 600;
        }
        
        .summary-row td {
            border-color: var(--success-color);
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .month-navigation {
            display: flex;
            align-items: center;
            gap: var(--spacing-4);
            margin-bottom: var(--spacing-6);
        }
        
        .controls-section {
            background-color: var(--surface-color);
            padding: var(--spacing-4);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-6);
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-4);
            margin-bottom: var(--spacing-6);
        }
        
        .summary-card {
            background-color: var(--surface-color);
            padding: var(--spacing-4);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        .summary-card-value {
            font-size: var(--font-size-2xl);
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: var(--spacing-2);
        }
        
        .summary-card-label {
            color: var(--text-secondary);
            font-size: var(--font-size-sm);
        }
        
        .loading-state {
            text-align: center;
            padding: var(--spacing-8);
            color: var(--text-secondary);
        }
        
        .overtime-section {
            background-color: var(--surface-color);
            padding: var(--spacing-4);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            margin-top: var(--spacing-6);
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .timesheet-table {
                font-size: 12px;
            }
            
            .summary-cards {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body data-theme="<?= htmlspecialchars($user['theme']) ?>">
    <header class="header no-print">
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php" class="logo"><?= APP_NAME ?></a>
                
                <button class="mobile-menu-toggle">☰</button>
                
                <nav class="nav">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="schedule.php">Lịch làm việc</a>
                    <a href="timesheet.php" class="active">Bảng công</a>
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
            <!-- Controls Section -->
            <div class="controls-section no-print">
                <div class="flex justify-between items-center mb-4">
                    <h1 class="text-2xl font-bold">Bảng công tháng <?= $currentMonth ?>/<?= $currentYear ?></h1>
                    
                    <div class="flex gap-3">
                        <button onclick="openModal('overtime-modal')" class="btn btn-secondary">
                            📊 Tính tăng ca
                        </button>
                        <button onclick="exportCSV()" class="btn btn-success">
                            📄 Xuất CSV
                        </button>
                        <button onclick="printTimesheet()" class="btn btn-primary">
                            🖨️ In bảng công
                        </button>
                    </div>
                </div>
                
                <div class="flex flex-wrap items-center gap-4">
                    <!-- Month Navigation -->
                    <div class="month-navigation">
                        <button onclick="navigateMonth(-1)" class="btn btn-outline">◀</button>
                        <select id="month-select" class="form-select" onchange="changeMonth()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $currentMonth ? 'selected' : '' ?>>
                                    Tháng <?= $m ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select id="year-select" class="form-select" onchange="changeMonth()">
                            <?php for ($y = 2020; $y <= 2030; $y++): ?>
                                <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>>
                                    <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <button onclick="navigateMonth(1)" class="btn btn-outline">▶</button>
                    </div>
                    
                    <!-- Rate Options -->
                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" 
                                   id="use-current-rate" 
                                   <?= $useCurrentRate ? 'checked' : '' ?>
                                   onchange="toggleCurrentRate()">
                            <span>Áp dụng lương hiện tại cho cả tháng</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div id="summary-cards" class="summary-cards">
                <div class="loading-state">
                    <div class="loading"></div>
                    <p>Đang tải dữ liệu...</p>
                </div>
            </div>

            <!-- Timesheet Table -->
            <div id="timesheet-container" class="card">
                <div class="loading-state">
                    <div class="loading"></div>
                    <p>Đang tải bảng công...</p>
                </div>
            </div>
            
            <!-- Overtime Section -->
            <div id="overtime-container" class="overtime-section" style="display: none;">
                <!-- Will be populated when overtime is calculated -->
            </div>
        </div>
    </main>

    <!-- Overtime Calculation Modal -->
    <div id="overtime-modal" class="modal-overlay">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <h3 class="modal-title">Tính toán tăng ca</h3>
                <button class="modal-close">&times;</button>
            </div>
            
            <form id="overtime-form">
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="regular-hours" class="form-label">Giờ làm chuẩn/tuần</label>
                        <input type="number" 
                               id="regular-hours" 
                               name="regular_hours_per_week" 
                               class="form-input" 
                               value="40" 
                               min="1" 
                               max="168"
                               step="0.5"
                               required>
                        <div class="form-help">Số giờ làm việc chuẩn trong 1 tuần</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="overtime-multiplier" class="form-label">Hệ số tăng ca</label>
                        <input type="number" 
                               id="overtime-multiplier" 
                               name="overtime_multiplier" 
                               class="form-input" 
                               value="1.5" 
                               min="1" 
                               max="3"
                               step="0.1"
                               required>
                        <div class="form-help">Hệ số nhân lương cho giờ tăng ca</div>
                    </div>
                </div>
                
                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="closeModal()" class="btn btn-outline">Hủy</button>
                    <button type="submit" class="btn btn-primary">Tính toán</button>
                </div>
            </form>
            
            <div id="overtime-results" style="display: none;">
                <!-- Results will be populated here -->
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        let currentMonth = <?= $currentMonth ?>;
        let currentYear = <?= $currentYear ?>;
        let useCurrentRate = <?= $useCurrentRate ? 'true' : 'false' ?>;
        
        // Load timesheet data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTimesheetData();
        });
        
        // Navigation functions
        function navigateMonth(direction) {
            currentMonth += direction;
            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            } else if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            
            updateURL();
            loadTimesheetData();
        }
        
        function changeMonth() {
            currentMonth = parseInt(document.getElementById('month-select').value);
            currentYear = parseInt(document.getElementById('year-select').value);
            
            updateURL();
            loadTimesheetData();
        }
        
        function toggleCurrentRate() {
            useCurrentRate = document.getElementById('use-current-rate').checked;
            updateURL();
            loadTimesheetData();
        }
        
        function updateURL() {
            const url = new URL(window.location);
            url.searchParams.set('month', currentMonth);
            url.searchParams.set('year', currentYear);
            url.searchParams.set('use_current_rate', useCurrentRate);
            window.history.replaceState({}, '', url);
        }
        
        // Load timesheet data
        async function loadTimesheetData() {
            try {
                const response = await fetch('api/reports.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        action: 'get_timesheet',
                        month: currentMonth,
                        year: currentYear,
                        use_current_rate: useCurrentRate
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    renderSummaryCards(result.summary);
                    renderTimesheetTable(result.timesheet, result.summary);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                showToast('Có lỗi xảy ra khi tải dữ liệu bảng công', 'error');
            }
        }
        
        // Render summary cards
        function renderSummaryCards(summary) {
            const container = document.getElementById('summary-cards');
            container.innerHTML = `
                <div class="summary-card">
                    <div class="summary-card-value">${summary.total_days}</div>
                    <div class="summary-card-label">Ngày làm việc</div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-value">${summary.total_hours.toFixed(1)}h</div>
                    <div class="summary-card-label">Tổng giờ làm</div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-value">${formatCurrency(summary.total_earnings)}</div>
                    <div class="summary-card-label">Tổng thu nhập</div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-value">${summary.average_hours_per_day.toFixed(1)}h</div>
                    <div class="summary-card-label">Trung bình/ngày</div>
                </div>
            `;
        }
        
        // Render timesheet table
        function renderTimesheetTable(timesheet, summary) {
            const container = document.getElementById('timesheet-container');
            
            if (timesheet.length === 0) {
                container.innerHTML = `
                    <div class="text-center p-8">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                        <h3>Chưa có dữ liệu bảng công</h3>
                        <p class="text-secondary">Tháng ${currentMonth}/${currentYear} chưa có ca làm việc nào</p>
                        <a href="schedule.php" class="btn btn-primary mt-4">Tạo ca làm việc</a>
                    </div>
                `;
                return;
            }
            
            let tableHTML = `
                <table class="timesheet-table" id="printable-table">
                    <thead>
                        <tr>
                            <th>Ngày</th>
                            <th>Nơi làm việc</th>
                            <th>Giờ KH</th>
                            <th>Giờ thực tế</th>
                            <th>Lương/giờ</th>
                            <th>Tổng lương</th>
                            <th>Trạng thái</th>
                            <th class="no-print">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            timesheet.forEach(entry => {
                const plannedHours = calculateHours(entry.planned_start, entry.planned_end);
                tableHTML += `
                    <tr>
                        <td>${formatDate(entry.date)}</td>
                        <td>${entry.workplace}</td>
                        <td>${plannedHours.toFixed(1)}h</td>
                        <td>${parseFloat(entry.worked_hours).toFixed(1)}h</td>
                        <td class="text-right">${formatCurrency(entry.hourly_rate)}</td>
                        <td class="text-right">${formatCurrency(entry.daily_earnings)}</td>
                        <td>
                            <span class="badge badge-${entry.status}">${getStatusText(entry.status)}</span>
                        </td>
                        <td class="no-print">
                            <button onclick="showShiftDetails(${entry.id})" class="btn btn-sm btn-outline">
                                Chi tiết
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            // Summary row
            tableHTML += `
                    <tr class="summary-row">
                        <td colspan="3"><strong>TỔNG KẾT THÁNG</strong></td>
                        <td><strong>${summary.total_hours.toFixed(1)}h</strong></td>
                        <td></td>
                        <td class="text-right"><strong>${formatCurrency(summary.total_earnings)}</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
                </table>
            `;
            
            container.innerHTML = tableHTML;
        }
        
        // Helper functions
        function calculateHours(start, end) {
            const startTime = new Date(start);
            const endTime = new Date(end);
            return (endTime - startTime) / (1000 * 60 * 60);
        }
        
        function getStatusText(status) {
            const statusMap = {
                'planned': 'Kế hoạch',
                'in_progress': 'Đang làm',
                'done': 'Hoàn thành',
                'canceled': 'Đã hủy'
            };
            return statusMap[status] || status;
        }
        
        // Export functions
        function exportCSV() {
            const url = `api/reports.php?action=export_csv&month=${currentMonth}&year=${currentYear}&use_current_rate=${useCurrentRate}`;
            window.open(url, '_blank');
        }
        
        function printTimesheet() {
            window.print();
        }
        
        // Overtime calculation
        document.getElementById('overtime-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const submitBtn = this.querySelector('button[type="submit"]');
                showLoading(submitBtn);
                
                const response = await fetch('api/reports.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        action: 'calculate_overtime',
                        month: currentMonth,
                        year: currentYear,
                        regular_hours_per_week: parseFloat(formData.get('regular_hours_per_week')),
                        overtime_multiplier: parseFloat(formData.get('overtime_multiplier'))
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    renderOvertimeResults(result.overtime_calculation);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                showToast('Có lỗi xảy ra khi tính toán tăng ca', 'error');
            } finally {
                const submitBtn = this.querySelector('button[type="submit"]');
                hideLoading(submitBtn);
            }
        });
        
        function renderOvertimeResults(overtimeData) {
            const resultsContainer = document.getElementById('overtime-results');
            const weeklyData = overtimeData.weekly_data;
            const monthlyTotals = overtimeData.monthly_totals;
            
            let resultsHTML = `
                <div class="mt-6">
                    <h4 class="font-semibold mb-4">Kết quả tính tăng ca</h4>
                    
                    <!-- Monthly Summary -->
                    <div class="grid grid-cols-4 gap-4 mb-4">
                        <div class="text-center p-3 bg-blue-50 rounded">
                            <div class="font-bold text-lg">${monthlyTotals.regular_hours.toFixed(1)}h</div>
                            <div class="text-sm text-gray-600">Giờ chuẩn</div>
                        </div>
                        <div class="text-center p-3 bg-orange-50 rounded">
                            <div class="font-bold text-lg">${monthlyTotals.overtime_hours.toFixed(1)}h</div>
                            <div class="text-sm text-gray-600">Giờ tăng ca</div>
                        </div>
                        <div class="text-center p-3 bg-green-50 rounded">
                            <div class="font-bold text-lg">${formatCurrency(monthlyTotals.regular_pay)}</div>
                            <div class="text-sm text-gray-600">Lương chuẩn</div>
                        </div>
                        <div class="text-center p-3 bg-purple-50 rounded">
                            <div class="font-bold text-lg">${formatCurrency(monthlyTotals.overtime_pay)}</div>
                            <div class="text-sm text-gray-600">Phụ cấp tăng ca</div>
                        </div>
                    </div>
                    
                    <!-- Weekly Breakdown -->
                    <div class="mt-4">
                        <h5 class="font-semibold mb-2">Chi tiết theo tuần:</h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tuần</th>
                                    <th>Giờ chuẩn</th>
                                    <th>Giờ tăng ca</th>
                                    <th>Lương chuẩn</th>
                                    <th>Phụ cấp tăng ca</th>
                                    <th>Tổng</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            weeklyData.forEach(week => {
                if (week.total_hours > 0) {
                    resultsHTML += `
                        <tr>
                            <td>Tuần ${week.week}/${week.year}</td>
                            <td>${week.regular_hours.toFixed(1)}h</td>
                            <td>${week.overtime_hours.toFixed(1)}h</td>
                            <td>${formatCurrency(week.regular_pay)}</td>
                            <td>${formatCurrency(week.overtime_pay)}</td>
                            <td><strong>${formatCurrency(week.total_pay)}</strong></td>
                        </tr>
                    `;
                }
            });
            
            resultsHTML += `
                            </tbody>
                            <tfoot>
                                <tr style="background-color: var(--success-color); color: white;">
                                    <td><strong>TỔNG</strong></td>
                                    <td><strong>${monthlyTotals.regular_hours.toFixed(1)}h</strong></td>
                                    <td><strong>${monthlyTotals.overtime_hours.toFixed(1)}h</strong></td>
                                    <td><strong>${formatCurrency(monthlyTotals.regular_pay)}</strong></td>
                                    <td><strong>${formatCurrency(monthlyTotals.overtime_pay)}</strong></td>
                                    <td><strong>${formatCurrency(monthlyTotals.total_pay)}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            `;
            
            resultsContainer.innerHTML = resultsHTML;
            resultsContainer.style.display = 'block';
            
            // Show in main page as well
            const overtimeContainer = document.getElementById('overtime-container');
            overtimeContainer.innerHTML = resultsHTML;
            overtimeContainer.style.display = 'block';
        }
        
        // Show shift details (reuse from schedule page)
        async function showShiftDetails(shiftId) {
            // Implementation similar to schedule.php
            // For brevity, redirecting to schedule page
            window.location.href = `schedule.php#shift-${shiftId}`;
        }
    </script>
</body>
</html>
