<?php
// Simple test file to check database and create demo user
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Test & Setup</h1>";

try {
    // Database connection test
    $host = 'sql207.infinityfree.com';
    $dbname = 'if0_39883453_adxcad';
    $username = 'if0_39883453';
    $password = 'ByQoWhpUD3dL';
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Database connection successful!<br><br>";
    
    // Check if tables exist
    $tables = ['users', 'shifts', 'sessions', 'hourly_rate_history', 'audit_logs', 'password_resets'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = $stmt->fetchColumn();
            echo "✅ Table `$table` exists with $count records<br>";
        } catch (Exception $e) {
            echo "❌ Table `$table` missing or error: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br>";
    
    // Create demo user if not exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['demo@example.com']);
    
    if (!$stmt->fetch()) {
        echo "Creating demo user...<br>";
        
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, hourly_rate, workplace_default, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            'Demo User',
            'demo@example.com',
            password_hash('password', PASSWORD_DEFAULT),
            150000, // 150k VND per hour
            'Văn phòng'
        ]);
        
        if ($result) {
            echo "✅ Demo user created successfully!<br>";
        } else {
            echo "❌ Failed to create demo user<br>";
        }
    } else {
        echo "✅ Demo user already exists<br>";
    }
    
    // Show all users
    echo "<br><h2>Current Users:</h2>";
    $stmt = $pdo->query("SELECT id, name, email, hourly_rate, created_at FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    if ($users) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Hourly Rate</th><th>Created</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . number_format($user['hourly_rate']) . " VND</td>";
            echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No users found.";
    }
    
    echo "<br><br><h2>Recent Shifts:</h2>";
    $stmt = $pdo->query("
        SELECT s.id, u.name, s.date, s.planned_start, s.planned_end, s.workplace, s.status 
        FROM shifts s 
        JOIN users u ON s.user_id = u.id 
        ORDER BY s.created_at DESC 
        LIMIT 10
    ");
    $shifts = $stmt->fetchAll();
    
    if ($shifts) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>User</th><th>Date</th><th>Start</th><th>End</th><th>Workplace</th><th>Status</th></tr>";
        foreach ($shifts as $shift) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($shift['id']) . "</td>";
            echo "<td>" . htmlspecialchars($shift['name']) . "</td>";
            echo "<td>" . htmlspecialchars($shift['date']) . "</td>";
            echo "<td>" . htmlspecialchars($shift['planned_start']) . "</td>";
            echo "<td>" . htmlspecialchars($shift['planned_end']) . "</td>";
            echo "<td>" . htmlspecialchars($shift['workplace']) . "</td>";
            echo "<td>" . htmlspecialchars($shift['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No shifts found.";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>
