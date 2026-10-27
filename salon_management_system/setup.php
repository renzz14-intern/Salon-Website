<?php
/**
 * SETUP SCRIPT - Run this ONCE to create the default admin account
 * Access: http://localhost/salon_management_system/setup.php
 * After running successfully, DELETE this file for security!
 */

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'salon_management';

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if admin already exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        // Update existing admin password
        $hashed_password = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $stmt->execute([$hashed_password]);
        echo "<h2 style='color: green;'>✅ Admin password has been reset!</h2>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin</p>";
        echo "<p>New password hash: <code>" . $hashed_password . "</code></p>";
    } else {
        // Create new admin account
        $hashed_password = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, role, status)
            VALUES ('admin', ?, 'System Administrator', 'admin', 'active')
        ");
        $stmt->execute([$hashed_password]);
        echo "<h2 style='color: green;'>✅ Admin account created successfully!</h2>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin</p>";
        echo "<p>Password hash: <code>" . $hashed_password . "</code></p>";
    }
    
    echo "<hr>";
    echo "<p><a href='index.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    echo "<hr>";
    echo "<p style='color: red;'><strong>⚠️ IMPORTANT:</strong> Delete this setup.php file after successful setup for security!</p>";
    
    // Test password verification
    echo "<h3>Password Verification Test:</h3>";
    $test_verify = password_verify('admin', $hashed_password);
    echo "<p>Verification test: " . ($test_verify ? "✅ PASS" : "❌ FAIL") . "</p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>❌ Database Error</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<hr>";
    echo "<h3>Troubleshooting Steps:</h3>";
    echo "<ol>";
    echo "<li>Make sure XAMPP MySQL is running</li>";
    echo "<li>Make sure you imported database.sql in phpMyAdmin</li>";
    echo "<li>Check database name is: <strong>salon_management</strong></li>";
    echo "<li>Verify database credentials in config/database.php</li>";
    echo "</ol>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup - Salon Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        code {
            background: #eee;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h1>🔧 Salon Management System - Setup</h1>
</body>
</html>