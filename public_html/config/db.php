<?php
/**
 * Database Configuration
 * Compatible with InfinityFree hosting
 */

// Database configuration for InfinityFree hosting
// Updated with actual database credentials from InfinityFree
define('DB_HOST', 'sql207.infinityfree.com'); // Database host from InfinityFree
define('DB_NAME', 'if0_39883453_adxcad'); // Database name from InfinityFree
define('DB_USER', 'if0_39883453'); // Database username from InfinityFree
define('DB_PASS', 'ByQoWhpUD3dL'); // Database password from InfinityFree

// For local development, uncomment the lines below and comment the lines above
/*
define('DB_HOST', 'localhost');
define('DB_NAME', 'work_schedule_db');
define('DB_USER', 'root');
define('DB_PASS', '');
*/

// Database connection options
define('DB_CHARSET', 'utf8mb4');
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
]);

// Application configuration
define('APP_NAME', 'Work Schedule & Payroll');
define('APP_VERSION', '1.0.0');
define('SITE_URL', 'https://your-domain.infinityfreeapp.com'); // Update with your actual domain

// Session configuration
define('SESSION_LIFETIME', 7200); // 2 hours
define('SESSION_NAME', 'work_schedule_session');

// File upload configuration
define('UPLOAD_MAX_SIZE', 2097152); // 2MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('AVATAR_UPLOAD_PATH', 'assets/img/avatars/');

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Error reporting for production (disabled for live site)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Changed to 0 for production
ini_set('log_errors', 1);

/**
 * Get database connection
 * @return PDO Database connection
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Get current user ID from session
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Redirect to login page if not authenticated
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 * @param mixed $data Data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Log audit action
 * @param string $action Action performed
 * @param array $meta Additional metadata
 * @param int|null $userId User ID (defaults to current user)
 */
function logAudit($action, $meta = [], $userId = null) {
    try {
        $pdo = getDB();
        $userId = $userId ?? getCurrentUserId();
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, meta, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            json_encode($meta),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Format currency for Vietnamese locale
 * @param float $amount Amount to format
 * @return string Formatted currency
 */
function formatCurrency($amount) {
    return number_format($amount, 0, ',', '.') . ' ₫';
}

/**
 * Calculate working hours between two datetime strings
 * @param string $start Start datetime
 * @param string $end End datetime
 * @return float Hours worked
 */
function calculateWorkingHours($start, $end) {
    $startTime = new DateTime($start);
    $endTime = new DateTime($end);
    $interval = $startTime->diff($endTime);
    
    return $interval->h + ($interval->i / 60) + ($interval->s / 3600);
}

/**
 * Send JSON response
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get user's current hourly rate
 * @param int $userId User ID
 * @param string $effectiveDate Date to check rate for (defaults to now)
 * @return float Hourly rate
 */
function getUserHourlyRate($userId, $effectiveDate = null) {
    try {
        $pdo = getDB();
        $effectiveDate = $effectiveDate ?? date('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare("
            SELECT rate 
            FROM hourly_rate_history 
            WHERE user_id = ? AND effective_from <= ? 
            ORDER BY effective_from DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$userId, $effectiveDate]);
        $result = $stmt->fetch();
        
        return $result ? $result['rate'] : 0;
    } catch (Exception $e) {
        error_log("Error getting hourly rate: " . $e->getMessage());
        return 0;
    }
}
?>
