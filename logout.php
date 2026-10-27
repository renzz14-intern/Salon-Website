<?php
session_start();

// Store user name for goodbye message
$userName = $_SESSION['name'] ?? 'User';

// Destroy all session data
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to main website with logout parameters
$userNameEncoded = urlencode($userName);
header("Location: index.html?logout_success=1&user_name=" . $userNameEncoded);
exit();
?>