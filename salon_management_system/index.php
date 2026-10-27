<?php
/**
 * Login Page
 * Handles user authentication and redirects based on role
 */

require_once 'config/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: staff/pos.php");
    }
    exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $user = authenticateUser($username, $password);
        
        if ($user) {
            createUserSession($user);
            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: staff/dashboard.php");
            }
            exit();
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Salon Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/icon.png">
    <style>
        /* ── Logo circle ─────────────────────────────────────────── */
        .salon-logo-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 24px;
        }

        .salon-logo {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            object-position: center;
            border: 4px solid #f19e99;
            box-shadow:
                0 0 0 6px rgba(241, 158, 153, 0.20),
                0 8px 28px rgba(241, 158, 153, 0.35);
            display: block;
        }

        /* ── Subtle headings tweak so they sit nicely below the logo ── */
        .login-box h1:first-of-type {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">

            <!-- Round salon logo -->
            <div class="salon-logo-wrap">
                <img
                    src="assets/images/icon.png"
                    alt="Levy Branzuela Salon"
                    class="salon-logo"
                >
            </div>

            <h1>Levy Branzuela Salon</h1>
            <h1>Management System</h1>
            <h2>Login to Your Account</h2>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter your username"
                        required
                        autofocus
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <button type="submit" class="btn-login">Login</button>
            </form>
        </div>
    </div>
</body>
</html>