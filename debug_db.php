<?php
// Create a temporary debug file to test database connection
// Save this as debug_db.php and run it to check your setup

require_once 'config.php';

echo "<h2>Database Connection Test</h2>";

// Test database connection
if ($conn) {
    echo "✅ Database connection successful<br>";
    
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "✅ Users table exists<br>";
        
        // Check table structure
        $result = $conn->query("DESCRIBE users");
        echo "<h3>Users Table Structure:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count existing users
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        $count = $result->fetch_assoc()['count'];
        echo "<br>Total users in database: " . $count . "<br>";
        
    } else {
        echo "❌ Users table does not exist<br>";
        echo "<h3>Create Users Table:</h3>";
        echo "<pre>";
        echo "CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";
        echo "</pre>";
    }
} else {
    echo "❌ Database connection failed: " . mysqli_connect_error();
}
?>