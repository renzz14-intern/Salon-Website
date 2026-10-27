<?php

// Check if mysqli extension is loaded
if (!extension_loaded('mysqli')) {
    die('MySQLi extension is not loaded. Please enable it in your PHP configuration.');
}

// ── SHARED DATABASE ──────────────────────────────────────────────────────────
// Both the website and the salon management system connect to the same database.
// The website uses this mysqli connection; the management system uses PDO via database.php.
$host     = "localhost";
$user     = "root";
$password = "";
$database = "salon_management"; // unified with management system

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>