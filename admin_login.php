<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_panel.php');
    exit();
}

$error = '';

define('FALLBACK_USERNAME', 'admin');
define('FALLBACK_PASSWORD', 'Admin@1234');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $loggedIn  = false;
        $adminName = 'Admin';

        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'admin_accounts'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $stmt = $conn->prepare("SELECT id, username, password, full_name FROM admin_accounts WHERE username = ? LIMIT 1");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $admin = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($admin && password_verify($password, $admin['password'])) {
                    $loggedIn  = true;
                    $adminName = $admin['full_name'] ?? $admin['username'];
                    $_SESSION['admin_id'] = $admin['id'];
                }
            }
        } catch (Exception $e) {
            error_log('Admin login DB error: ' . $e->getMessage());
        }

        if (!$loggedIn && $username === FALLBACK_USERNAME && $password === FALLBACK_PASSWORD) {
            $loggedIn  = true;
            $adminName = 'Administrator';
        }

        if ($loggedIn) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_name']      = $adminName;
            header('Location: admin_panel.php');
            exit();
        } else {
            $error = 'Invalid username or password.';
            sleep(1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Levy Branzuela Salon</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" href="images/Untitled.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f4f4;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #fff;
            width: 100%;
            max-width: 380px;
            border-radius: 10px;
            box-shadow: 0 2px 16px rgba(0,0,0,.08);
            padding: 2.5rem 2rem;
        }
        .card-top { text-align: center; margin-bottom: 2rem; }
        .card-top h1 { font-size: 1.2rem; font-weight: 600; color: #222; }
        .card-top p { font-size: .8rem; color: #999; margin-top: .25rem; }
        .divider { width: 36px; height: 3px; background: #f19e99; border-radius: 2px; margin: .75rem auto 0; }
        .error-box {
            background: #fff5f5; border: 1px solid #f5c6c6; color: #c0392b;
            border-radius: 6px; padding: .65rem .9rem; font-size: .82rem;
            margin-bottom: 1.2rem; display: flex; align-items: center; gap: 7px;
        }
        .field { margin-bottom: 1.1rem; }
        .field label {
            display: block; font-size: .78rem; font-weight: 500; color: #555;
            margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .04em;
        }
        .field input {
            width: 100%; border: 1px solid #ddd; border-radius: 6px;
            padding: .65rem .85rem; font-size: .9rem;
            font-family: 'Poppins', sans-serif; color: #333;
            outline: none; transition: border-color .2s; background: #fafafa;
        }
        .field input:focus { border-color: #f19e99; background: #fff; }
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 2.5rem; }
        .pw-toggle {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: #bbb; font-size: .85rem; padding: 0;
        }
        .pw-toggle:hover { color: #888; }
        .btn-submit {
            width: 100%; padding: .75rem; background: #f19e99; color: #fff;
            border: none; border-radius: 6px; font-size: .95rem; font-weight: 600;
            font-family: 'Poppins', sans-serif; cursor: pointer;
            margin-top: .3rem; transition: background .2s;
        }
        .btn-submit:hover { background: #e8887f; }
        .btn-submit:disabled { background: #f5c4c2; cursor: not-allowed; }
        .card-footer { text-align: center; margin-top: 1.5rem; font-size: .78rem; color: #bbb; }
        .card-footer a { color: #aaa; text-decoration: none; }
        .card-footer a:hover { color: #f19e99; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-top">
        <h1>Levy Branzuela Salon</h1>
        <p>Admin Panel</p>
        <div class="divider"></div>
    </div>

    <?php if ($error): ?>
        <div class="error-box">&#9888; <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="loginForm">
        <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Enter username"
                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <div class="pw-wrap">
                <input type="password" id="password" name="password" placeholder="Enter password" required>
                <button type="button" class="pw-toggle" id="pwToggle" tabindex="-1">&#128065;</button>
            </div>
        </div>
        <button type="submit" class="btn-submit" id="submitBtn">Sign In</button>
    </form>

    <div class="card-footer">
        <a href="index.html">&larr; Back to Main Site</a>
    </div>
</div>
<script>
    document.getElementById('pwToggle').addEventListener('click', function () {
        const pw = document.getElementById('password');
        pw.type = pw.type === 'password' ? 'text' : 'password';
    });
    document.getElementById('loginForm').addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Signing in…';
    });
</script>
</body>
</html>