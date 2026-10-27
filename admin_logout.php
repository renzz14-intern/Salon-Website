<?php
session_start();

// Only remove the admin-specific session keys.
// This preserves any customer session (user_id, etc.) so the
// main site remains unaffected when an admin logs out.
unset(
    $_SESSION['admin_logged_in'],
    $_SESSION['admin_id'],
    $_SESSION['admin_name']
);

header('Location: admin_login.php');
exit();