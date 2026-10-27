<?php
/**
 * Admin Settings
 * Change admin name and password
 */

require_once '../config/auth.php';
requireAdmin();

$pdo = getPDOConnection();
$success = '';
$error = '';

// Get current admin info
$admin_id = getCurrentUserId();
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Check if admin exists
if (!$admin) {
    die("Admin account not found in database!");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_name') {
        // Update admin name
        $full_name = sanitizeInput($_POST['full_name']);
        
        if (empty($full_name)) {
            $error = 'Name cannot be empty.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
                $stmt->execute([$full_name, $admin_id]);
                
                // Update session
                $_SESSION['full_name'] = $full_name;
                
                $success = 'Name updated successfully!';
                
                // Refresh admin data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$admin_id]);
                $admin = $stmt->fetch();
            } catch (Exception $e) {
                $error = 'Error updating name: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_password') {
        // Update admin password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirmation do not match.';
        } elseif (strlen($new_password) < 4) {
            $error = 'New password must be at least 4 characters long.';
        } elseif (!password_verify($current_password, $admin['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$hashed_password, $admin_id]);
                
                $success = 'Password changed successfully!';
            } catch (Exception $e) {
                $error = 'Error updating password: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Salon Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/icon.png">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Salon Admin</h2>
                <p><?php echo getCurrentUserName(); ?></p>
            </div>
            <nav class="sidebar-menu">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="appointments_admin.php">&#x1F4C5; Appointments</a>
                <a href="pos.php">💰 Point of Sales</a>
                <a href="sales_reports.php">📈 Sales Reports</a>
                <a href="staff_reports_admin.php">&#x1F4CB; Staff Reports</a>
                <a href="inventory.php">📦 Inventory</a>
                <a href="staff_duties.php">📋 Staff Duties</a>
                <a href="staff_management.php">👥 Staff Management</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h1>⚙️ Admin Settings</h1>
                <div class="user-info">
                    <span style="margin-right: 15px;">Welcome, <strong><?php echo getCurrentUserName(); ?></strong></span>
                    <span class="btn btn-primary" style="opacity: 0.7; cursor: default;">⚙️ Settings</span>
                    <a href="../logout.php" class="btn-logout">Logout</a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Account Information -->
            <div class="content-box">
                <h2>📋 Account Information</h2>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($admin['username']); ?></p>
                    <p><strong>Full Name:</strong> <?php echo htmlspecialchars($admin['full_name']); ?></p>
                    <p><strong>Role:</strong> <span class="badge badge-success">Administrator</span></p>
                    <p><strong>Account Created:</strong> <?php echo date('F d, Y', strtotime($admin['created_at'])); ?></p>
                </div>
            </div>

            <!-- Change Name -->
            <div class="content-box">
                <h2>✏️ Change Display Name</h2>
                <p style="color: #666; margin-bottom: 20px;">Update your display name that appears throughout the system.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_name">
                    
                    <div class="form-row">
                        <div class="form-group" style="max-width: 500px;">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($admin['full_name']); ?>" 
                                   placeholder="Enter your full name" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">💾 Update Name</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="content-box">
                <h2>🔐 Change Password</h2>
                <p style="color: #666; margin-bottom: 20px;">For security, please enter your current password to set a new one.</p>
                
                <form method="POST" action="" id="passwordForm">
                    <input type="hidden" name="action" value="update_password">
                    
                    <div class="form-row">
                        <div class="form-group" style="max-width: 400px;">
                            <label>Current Password *</label>
                            <input type="password" name="current_password" class="form-control" 
                                   placeholder="Enter current password" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="max-width: 400px;">
                            <label>New Password *</label>
                            <input type="password" name="new_password" id="newPassword" class="form-control" 
                                   placeholder="Enter new password (min. 4 characters)" required>
                            <small style="color: #666;">Minimum 4 characters</small>
                        </div>

                        <div class="form-group" style="max-width: 400px;">
                            <label>Confirm New Password *</label>
                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control" 
                                   placeholder="Re-enter new password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success">🔒 Change Password</button>
                </form>
            </div>

            <!-- Security Tips -->
            <div class="content-box">
                <h2>🛡️ Security Tips</h2>
                <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; border-radius: 4px;">
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Use a strong password with a mix of letters, numbers, and symbols</li>
                        <li>Don't share your admin password with anyone</li>
                        <li>Change your password regularly (every 3-6 months)</li>
                        <li>Don't use the same password as other accounts</li>
                        <li>Log out when you're done using the system</li>
                    </ul>
                </div>
            </div>

            <!-- System Information -->
            <div class="content-box">
                <h2>ℹ️ System Information</h2>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <p><strong>System Name:</strong> Salon Management System</p>
                    <p><strong>Version:</strong> 1.0.0</p>
                    <p><strong>Database:</strong> salon_management</p>
                    <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Password confirmation validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirmation do not match!');
                return false;
            }
            
            if (newPassword.length < 4) {
                e.preventDefault();
                alert('Password must be at least 4 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>