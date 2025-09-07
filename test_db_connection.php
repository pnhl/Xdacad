<?php
// Test database connection and error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...<br>";

// Include config
require_once 'public_html/config/db.php';

try {
    $pdo = getDB();
    echo "✅ Database connection successful!<br>";
    
    // Test if tables exist
    $tables = ['users', 'shifts', 'sessions', 'hourly_rate_history', 'audit_logs', 'password_resets'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ Table '$table' exists with $count records<br>";
    }
    
    // Test CSRF token generation
    session_start();
    $token = generateCSRFToken();
    echo "✅ CSRF token generated: " . substr($token, 0, 10) . "...<br>";
    
    // Test current user (should be null if not logged in)
    $userId = getCurrentUserId();
    echo "Current user ID: " . ($userId ? $userId : 'Not logged in') . "<br>";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    echo "Error details: " . $e->getFile() . " line " . $e->getLine() . "<br>";
}
?>
