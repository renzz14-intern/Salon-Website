<?php
// Test file to debug appointments page
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debugging Appointments Page</h2>";

// Test 1: Check if config.php exists
echo "<h3>1. Checking config.php...</h3>";
if (file_exists('config.php')) {
    echo "✓ config.php exists<br>";
    require_once 'config.php';
} else {
    echo "✗ config.php NOT found<br>";
    die("Please create config.php file");
}

// Test 2: Check database connection
echo "<h3>2. Checking database connection...</h3>";
if (isset($conn)) {
    if ($conn->connect_error) {
        echo "✗ Database connection failed: " . $conn->connect_error . "<br>";
        die();
    } else {
        echo "✓ Database connected successfully<br>";
    }
} else {
    echo "✗ Connection object not created<br>";
    die();
}

// Test 3: Check if bookings table exists
echo "<h3>3. Checking bookings table...</h3>";
$result = $conn->query("SHOW TABLES LIKE 'bookings'");
if ($result->num_rows > 0) {
    echo "✓ Bookings table exists<br>";
} else {
    echo "✗ Bookings table NOT found<br>";
    echo "Please create the bookings table in your database<br>";
}

// Test 4: Check bookings table structure
echo "<h3>4. Checking bookings table structure...</h3>";
$result = $conn->query("DESCRIBE bookings");
if ($result) {
    echo "✓ Bookings table structure:<br>";
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "</pre>";
} else {
    echo "✗ Cannot read bookings table structure<br>";
}

// Test 5: Check session
echo "<h3>5. Checking session...</h3>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "✓ User is logged in (User ID: " . $_SESSION['user_id'] . ")<br>";
    
    // Test 6: Try to fetch appointments
    echo "<h3>6. Fetching appointments...</h3>";
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo "✓ Found " . $row['count'] . " appointment(s) for this user<br>";
    
    // Show sample appointment
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo "<pre>";
        print_r($result->fetch_assoc());
        echo "</pre>";
    }
} else {
    echo "✗ No user logged in<br>";
    echo "Please log in first<br>";
}

// Test 7: Check if cancel_appointment.php exists
echo "<h3>7. Checking cancel_appointment.php...</h3>";
if (file_exists('cancel_appointment.php')) {
    echo "✓ cancel_appointment.php exists<br>";
} else {
    echo "✗ cancel_appointment.php NOT found<br>";
    echo "Please upload cancel_appointment.php file<br>";
}

echo "<h3>Tests Complete!</h3>";
echo "<p><a href='appointments.php'>Go to Appointments Page</a></p>";
?>
