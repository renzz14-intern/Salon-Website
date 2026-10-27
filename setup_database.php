<?php
require_once 'config.php';

function executeSQL($conn, $sql) {
    try {
        if ($conn->query($sql)) {
            echo "Success: " . substr($sql, 0, 50) . "...<br>";
        } else {
            echo "Error: " . $conn->error . "<br>";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
}

// Read and execute SQL file
$sql_file = file_get_contents('database/spa_booking_tables.sql');
$sql_statements = explode(';', $sql_file);

echo "<h2>Setting up Spa Booking Database Tables</h2>";

foreach($sql_statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        executeSQL($conn, $statement);
    }
}

echo "<h3>Setup Complete!</h3>";
echo "<p>You can now return to the <a href='booking.php'>booking page</a>.</p>";
?>
