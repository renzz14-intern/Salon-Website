<?php
/**
 * Staff Dashboard — Landing page after login.
 * Shows only the pages the admin has granted access to.
 * If no permissions are granted, shows a "contact admin" notice.
 */

require_once '../config/auth.php';
requireLogin();

// Redirect admin away from staff area
if (isAdmin()) {
    header("Location: /salon_management_system/admin/dashboard.php");
    exit();
}

$perms = getStaffPermissions();
$nav   = getStaffNavLinks();
$hasAnyAccess = in_array(true, $perms, true);

// If exactly one permission, redirect straight to that page
if (count($nav) === 1) {
    header("Location: " . $nav[0]['href']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/icon.png">
    <style>
        /* No-access card */
        .no-access-card {
            max-width: 520px;
            margin: 60px auto;
            background: #fff;
            border-radius: 14px;
            padding: 40px 36px;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            border-top: 5px solid #dc3545;
        }
        .no-access-card .icon { font-size: 3rem; margin-bottom: 14px; }
        .no-access-card h2 { margin: 0 0 10px; color: #dc3545; }
        .no-access-card p  { color: #555; line-height: 1.6; margin: 0 0 22px; }
        .no-access-card .info-box {
            background: #fff3cd; border: 1px solid #ffc107;
            border-radius: 8px; padding: 14px 18px;
            color: #856404; font-size: .92rem; text-align: left;
            margin-bottom: 24px;
        }

        /* Access cards grid */
        .access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .access-card {
            background: #fff;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            border: 2px solid transparent;
        }
        .access-card:hover {
            border-color: #4a90e2;
            box-shadow: 0 6px 20px rgba(74,144,226,.18);
        }
        .access-card .ac-icon { font-size: 2.4rem; margin-bottom: 12px; }
        .access-card .ac-label { font-size: 1rem; font-weight: 600; }
    </style>
</head>
<body>
<div class="dashboard-container">

    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Salon Staff</h2>
            <p><?php echo getCurrentUserName(); ?></p>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="active">🏠 Dashboard</a>
            <?php foreach ($nav as $link): ?>
                <a href="<?php echo $link['href']; ?>"><?php echo $link['icon'] . ' ' . $link['label']; ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <h1>Welcome, <?php echo htmlspecialchars(getCurrentUserName()); ?>!</h1>
            <div class="user-info">
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <?php if (!$hasAnyAccess): ?>
            <!-- ── NO PERMISSIONS ───────────────────────────────────────── -->
            <div class="no-access-card">
                <div class="icon">🔒</div>
                <h2>No System Access Yet</h2>
                <p>Your account has been created but you haven't been granted access to any part of the system.</p>
                <div class="info-box">
                    <strong>What to do:</strong><br>
                    Please contact your admin and request access to the features you need
                    (e.g., Inventory, My Report). Once the admin updates your
                    permissions, log out and log back in to see your accessible pages.
                </div>
            </div>

        <?php else: ?>
            <!-- ── HAS PERMISSIONS — show access cards ──────────────────── -->
            <div class="content-box">
                <h2>Your Access</h2>
                <div class="access-grid">
                    <?php foreach ($nav as $link): ?>
                        <a href="<?php echo $link['href']; ?>" class="access-card">
                            <div class="ac-icon"><?php echo $link['icon']; ?></div>
                            <div class="ac-label"><?php echo htmlspecialchars($link['label']); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>