<?php
// Debug file for checking shift creation
require_once 'public_html/config/db.php';
require_once 'public_html/config/auth_middleware.php';

// Start session for testing
session_start();

// Mock user session for testing
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';
$_SESSION['user_email'] = 'test@example.com';
$_SESSION['login_time'] = time();

echo "<h1>Shift Creation Debug</h1>";

try {
    // Test database connection
    $pdo = getDB();
    echo "✅ Database connection successful<br>";
    
    // Test CSRF token generation
    $token = generateCSRFToken();
    echo "✅ CSRF token generated: " . substr($token, 0, 10) . "...<br>";
    
    // Test current user
    $userId = getCurrentUserId();
    echo "✅ Current user ID: $userId<br>";
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ User found: " . htmlspecialchars($user['name']) . "<br>";
    } else {
        echo "❌ User not found in database<br>";
        
        // Create test user
        echo "Creating test user...<br>";
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, hourly_rate, workplace_default) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            'Test User',
            'test@example.com',
            password_hash('password', PASSWORD_DEFAULT),
            150000,
            'Test Office'
        ]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $userId;
            echo "✅ Test user created with ID: $userId<br>";
        } else {
            echo "❌ Failed to create test user<br>";
        }
    }
    
    // Test shift creation manually
    echo "<br><h2>Testing Shift Creation</h2>";
    
    $testData = [
        'user_id' => $userId,
        'date' => date('Y-m-d'),
        'planned_start' => date('Y-m-d') . ' 09:00:00',
        'planned_end' => date('Y-m-d') . ' 17:00:00',
        'workplace' => 'Test Office',
        'notes' => 'Test shift creation'
    ];
    
    echo "Test data: <pre>" . print_r($testData, true) . "</pre>";
    
    $stmt = $pdo->prepare("
        INSERT INTO shifts (user_id, date, planned_start, planned_end, workplace, notes) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $testData['user_id'],
        $testData['date'],
        $testData['planned_start'],
        $testData['planned_end'],
        $testData['workplace'],
        $testData['notes']
    ]);
    
    if ($result) {
        $shiftId = $pdo->lastInsertId();
        echo "✅ Test shift created successfully with ID: $shiftId<br>";
        
        // Clean up - delete test shift
        $stmt = $pdo->prepare("DELETE FROM shifts WHERE id = ?");
        $stmt->execute([$shiftId]);
        echo "✅ Test shift deleted<br>";
    } else {
        echo "❌ Failed to create test shift<br>";
        echo "Error: " . implode(', ', $stmt->errorInfo()) . "<br>";
    }
    
    // Test API endpoint directly
    echo "<br><h2>Testing API Endpoint</h2>";
    
    // Simulate API call
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
    
    $testApiData = json_encode([
        'action' => 'create_shift',
        'date' => date('Y-m-d'),
        'planned_start' => date('Y-m-d') . ' 10:00:00',
        'planned_end' => date('Y-m-d') . ' 18:00:00',
        'workplace' => 'API Test Office',
        'notes' => 'API Test'
    ]);
    
    echo "API test data: <pre>$testApiData</pre>";
    
    // Save current output buffering
    ob_start();
    
    // Mock php://input
    $GLOBALS['mock_input'] = $testApiData;
    
    // Include API file (this would normally return JSON)
    include 'public_html/api/shifts.php';
    
    // Get the output
    $apiOutput = ob_get_clean();
    echo "API Response: <pre>$apiOutput</pre>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
}
?>
